<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookingFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingFeedbackController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $month = $filters['month'] ?? now()->format('Y-m');
        $start = Carbon::createFromFormat('!Y-m', $month);
        $end = $start->copy()->addMonth();

        $query = BookingFeedback::where('created_at', '>=', $start)->where('created_at', '<', $end);
        $averageRating = (clone $query)->avg('rating');
        $entries = $query->with('booking')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.feedback', compact('month', 'averageRating', 'entries'));
    }
}
