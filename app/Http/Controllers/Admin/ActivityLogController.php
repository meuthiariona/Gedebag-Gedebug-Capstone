<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

/** Halaman Audit Log (khusus Owner, izin `audit.view`). Hanya baca — tidak ada aksi ubah/hapus. */
class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filtered($request)->paginate(30)->withQueryString();

        return view('admin.activity-logs.index', [
            'logs' => $logs,
            'filters' => $request->only(['q', 'action', 'subject', 'from', 'to']),
            'failedToday' => ActivityLog::where('action', 'login_failed')->where('created_at', '>=', today())->count(),
        ]);
    }

    /** Unduh hasil filter sebagai CSV (untuk lampiran laporan / pemeriksaan). */
    public function export(Request $request)
    {
        $logs = $this->filtered($request)->limit(5000)->get();

        return response()->streamDownload(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Waktu', 'Pengguna', 'Peran', 'Aksi', 'Data', 'ID', 'Keterangan', 'Nilai lama', 'Nilai baru', 'IP']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->created_at->format('Y-m-d H:i:s'), $log->user_name, $log->user_role, $log->action_label,
                    $log->subject_label, $log->subject_id, $log->description,
                    $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                    $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
                    $log->ip_address,
                ]);
            }
            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request)
    {
        return ActivityLog::query()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('description', 'like', '%'.$request->q.'%')
                ->orWhere('user_name', 'like', '%'.$request->q.'%')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->action))
            ->when($request->filled('subject'), fn ($q) => $q->where('subject_type', $request->subject))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->latest('id');
    }
}
