<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

/**
 * Pengujian backup & restore: skenario "data hilang/rusak lalu dipulihkan",
 * cek integritas file, dan pembatasan akses.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private BackupService $backup;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->backup = app(BackupService::class);
    }

    private function owner(): User
    {
        return User::where('role', 'owner')->firstOrFail();
    }

    public function test_backup_contains_database_uploads_and_checksum(): void
    {
        Storage::disk('public')->put('products/foto.jpg', 'FOTO');
        $b = $this->backup->create();

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path('backups/'.$b['name']));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);

        $this->assertSame('FOTO', $zip->getFromName('uploads/products/foto.jpg'));
        $this->assertSame(Payment::count(), $manifest['tables']['payments']);
        $this->assertArrayNotHasKey('sessions', $manifest['tables']);
        $this->assertSame(hash('sha256', $zip->getFromName('database.json')), $manifest['database_sha256']);
    }

    public function test_restore_recovers_deleted_and_tampered_data(): void
    {
        Storage::disk('public')->put('products/foto.jpg', 'FOTO');
        $paymentCount = Payment::count();
        $bank = Setting::get('bank_account');
        $b = $this->backup->create();

        // Skenario insiden: pembayaran terhapus, rekening diganti, foto hilang.
        Payment::query()->delete();
        Setting::put('bank_account', 'REKENING-PALSU');
        Storage::disk('public')->delete('products/foto.jpg');

        $result = $this->backup->restore($b['name']);

        $this->assertSame($paymentCount, Payment::count());
        $this->assertSame($bank, Setting::get('bank_account'));
        $this->assertSame('FOTO', Storage::disk('public')->get('products/foto.jpg'));
        $this->assertStringStartsWith('gie-backup-pre-restore-', $result['safety_backup']);
    }

    public function test_safety_backup_allows_undoing_a_wrong_restore(): void
    {
        $old = $this->backup->create();
        Booking::first()->update(['event_name' => 'Data terbaru']);

        $result = $this->backup->restore($old['name']);      // salah pilih backup lama
        $this->assertNotSame('Data terbaru', Booking::first()->event_name);

        $this->backup->restore($result['safety_backup']);    // batalkan pakai backup pengaman
        $this->assertSame('Data terbaru', Booking::first()->event_name);
    }

    public function test_tampered_backup_is_rejected(): void
    {
        $b = $this->backup->create();
        $path = Storage::disk('local')->path('backups/'.$b['name']);
        $zip = new ZipArchive;
        $zip->open($path);
        $zip->addFromString('database.json', str_replace('"owner"', '"customer"', $zip->getFromName('database.json')));
        $zip->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum tidak cocok');
        $this->backup->restore($b['name']);
    }

    public function test_restore_keeps_audit_log_and_records_itself(): void
    {
        $b = $this->backup->create();
        ActivityLog::record('login', 'aktivitas setelah backup');

        $this->backup->restore($b['name']);

        $this->assertTrue(ActivityLog::where('description', 'aktivitas setelah backup')->exists(), 'Audit log tidak boleh ikut tertimpa');
        $this->assertTrue(ActivityLog::where('action', 'backup_restored')->exists());
    }

    public function test_path_traversal_names_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->backup->pathFor('../../.env');
    }

    public function test_retention_keeps_only_latest_automatic_backups(): void
    {
        config(['backup.keep' => 2]);
        for ($i = 0; $i < 4; $i++) {
            $this->travel(1)->minutes();
            $this->backup->create('auto');
        }
        $this->backup->create('manual');

        $all = collect($this->backup->all());
        $this->assertCount(2, $all->where('type', 'auto'));
        $this->assertCount(1, $all->where('type', 'manual'), 'Backup manual tidak ikut dihapus retensi');
    }

    public function test_owner_can_backup_and_restore_from_admin_page(): void
    {
        $this->actingAs($this->owner());
        $this->post('/admin/backups')->assertSessionHasNoErrors();
        $name = $this->backup->all()[0]['name'];

        $this->get('/admin/backups')->assertOk()->assertSee($name);
        $this->get("/admin/backups/{$name}/download")->assertOk();

        // Tanpa ketik RESTORE → ditolak
        $this->post("/admin/backups/{$name}/restore", ['confirm' => 'ya'])->assertSessionHasErrors('confirm');

        Payment::query()->delete();
        $this->post("/admin/backups/{$name}/restore", ['confirm' => 'RESTORE'])->assertRedirect(route('admin.backups.index'));
        $this->assertGreaterThan(0, Payment::count());
    }

    public function test_uploaded_backup_is_verified_before_saving(): void
    {
        $this->actingAs($this->owner());
        $b = $this->backup->create();
        $valid = new UploadedFile(Storage::disk('local')->path('backups/'.$b['name']), 'cadangan.zip', 'application/zip', null, true);
        $this->post('/admin/backups/upload', ['file' => $valid])->assertSessionHasNoErrors();
        $this->assertCount(1, collect($this->backup->all())->where('type', 'upload'));

        $fake = UploadedFile::fake()->createWithContent('palsu.zip', 'bukan zip');
        $this->post('/admin/backups/upload', ['file' => $fake])->assertSessionHasErrors('file');
    }

    public function test_only_owner_can_access_backups(): void
    {
        foreach (['finance', 'operations', 'customer'] as $role) {
            $user = User::where('role', $role)->first();
            $this->actingAs($user)->get('/admin/backups')->assertForbidden();
            $this->actingAs($user)->post('/admin/backups')->assertForbidden();
        }
    }
}
