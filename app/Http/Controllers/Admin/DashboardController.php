<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'Booking aktif' => Booking::whereIn('status', ['pending', 'confirmed'])->count(),
            'Menunggu verifikasi bayar' => Payment::where('status', 'pending')->count(),
            'Pendapatan diterima' => rupiah(Payment::where('status', 'verified')->sum('amount')),
            'Piutang berjalan' => rupiah(Booking::whereIn('status', ['pending', 'confirmed'])->get()->sum(fn ($b) => $b->remaining)),
            'Produk & talent' => Product::count(),
            'Paket' => Package::count(),
            'Customer' => User::where('role', 'customer')->count(),
        ];
        $pendingPayments = Payment::with('booking.user')->where('status', 'pending')->latest()->take(10)->get();
        $upcoming = Booking::with('user')->whereIn('status', ['pending', 'confirmed'])->where('end_date', '>=', today())->orderBy('start_date')->take(10)->get();

        return view('admin.dashboard', compact('stats', 'pendingPayments', 'upcoming'));
    }

    /** Daftar setting yang bisa diubah dari panel admin. */
    public const SETTINGS = [
        'Identitas' => [
            'site_name' => ['Nama brand', 'text'],
            'company_name' => ['Nama perusahaan (invoice)', 'text'],
            'tagline' => ['Deskripsi footer', 'textarea'],
            'logo' => ['Logo', 'image'],
            'npwp' => ['NPWP', 'text'],
            'company_address' => ['Alamat perusahaan', 'textarea'],
        ],
        'Kontak' => [
            'support_email' => ['Email support', 'text'],
            'support_phone' => ['Telepon', 'text'],
            'whatsapp' => ['Nomor WhatsApp (format 62xxx)', 'text'],
            'support_region' => ['Wilayah layanan (footer)', 'text'],
            'ops_center_name' => ['Nama kantor operasional', 'text'],
            'ops_center_address' => ['Alamat kantor operasional / gudang', 'text'],
            'dispatch_hub' => ['Teks status dispatch (My Bookings)', 'text'],
        ],
        'Booking & Pembayaran' => [
            'logistics_fee' => ['Biaya logistik default (Rp)', 'number'],
            'dp_percent' => ['Persentase DP (%)', 'number'],
            'hold_hours' => ['Lama hold booking sebelum DP (jam)', 'number'],
            'bank_name' => ['Nama bank', 'text'],
            'bank_account' => ['Nomor rekening / Virtual Account', 'text'],
            'bank_holder' => ['Atas nama', 'text'],
            'qris_image' => ['Gambar QRIS', 'image'],
            'invoice_terms' => ['Syarat & ketentuan di invoice', 'textarea'],
        ],
        'Konten' => [
            'tech_specs_file' => ['File Tech Specs (PDF) untuk tombol Download', 'file'],
            'certified_title' => ['Judul strip sertifikasi (beranda)', 'text'],
            'certified_text' => ['Teks strip sertifikasi (beranda)', 'text'],
            'packages_title' => ['Judul halaman paket', 'text'],
            'packages_subtitle' => ['Subjudul halaman paket', 'text'],
            'cta_title' => ['Judul banner custom setup', 'text'],
            'cta_text' => ['Teks banner custom setup', 'textarea'],
            'sla_text' => ['Teks NEXA Hardware Guarantee', 'textarea'],
        ],
    ];

    public function settings()
    {
        return view('admin.settings', ['groups' => self::SETTINGS, 'values' => Setting::allCached()]);
    }

    public function saveSettings(Request $request)
    {
        foreach (self::SETTINGS as $group) {
            foreach ($group as $key => [$label, $type]) {
                if (in_array($type, ['image', 'file'])) {
                    if ($request->hasFile($key)) {
                        $request->validate([$key => $type === 'image' ? ['image', 'max:5120'] : ['file', 'max:20480']]);
                        Setting::put($key, $request->file($key)->store('settings', 'public'));
                    }
                } elseif ($request->has($key)) {
                    Setting::put($key, $request->input($key));
                }
            }
        }

        return back()->with('success', 'Pengaturan disimpan.');
    }
}
