<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\BackupService;
use Illuminate\Http\Request;
use RuntimeException;

/** Halaman Backup & Restore (khusus Owner, izin `backup.manage`). */
class BackupController extends Controller
{
    public function __construct(private BackupService $backup) {}

    public function index()
    {
        return view('admin.backups.index', ['backups' => $this->backup->all()]);
    }

    public function store()
    {
        $b = $this->backup->create('manual');

        return back()->with('success', "Backup berhasil dibuat: {$b['name']}");
    }

    public function download(string $name)
    {
        try {
            $path = $this->backup->pathFor($name);
        } catch (RuntimeException) {
            abort(404);
        }
        ActivityLog::record('backup_downloaded', "Backup diunduh: {$name}");

        return response()->download($path);
    }

    public function upload(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'mimes:zip', 'max:'.config('backup.max_upload_kb')]]);

        try {
            $b = $this->backup->import($request->file('file')->getRealPath());
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', "File backup valid dan tersimpan sebagai {$b['name']}. Klik Restore untuk memulihkannya.");
    }

    public function restore(Request $request, string $name)
    {
        // Konfirmasi ketik ulang supaya restore tidak terjadi karena salah klik.
        $request->validate(['confirm' => ['required', 'in:RESTORE']], [
            'confirm.in' => 'Ketik RESTORE (huruf besar) untuk mengonfirmasi.',
            'confirm.required' => 'Ketik RESTORE untuk mengonfirmasi.',
        ]);

        try {
            $result = $this->backup->restore($name);
        } catch (RuntimeException $e) {
            return back()->withErrors(['restore' => $e->getMessage()]);
        }

        $message = 'Restore selesai: '.array_sum($result['tables']).' baris data & '.$result['uploads'].' file dipulihkan. '
            ."Kondisi sebelum restore disimpan sebagai {$result['safety_backup']}.";

        // Jika akun yang sedang login tidak ada di data hasil restore, sesi sudah diakhiri.
        if (! auth()->check()) {
            return redirect()->route('login')->withErrors(['email' => $message.' Silakan login kembali.']);
        }

        return redirect()->route('admin.backups.index')->with('success', $message);
    }

    public function destroy(string $name)
    {
        try {
            $this->backup->delete($name);
        } catch (RuntimeException) {
            abort(404);
        }

        return back()->with('success', "Backup {$name} dihapus.");
    }
}
