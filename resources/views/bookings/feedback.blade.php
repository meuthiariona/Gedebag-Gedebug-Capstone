<section id="feedback" class="mt-5 rounded-2xl border border-line p-5">
    <h2 class="text-lg font-semibold">Feedback booking</h2>
    @if ($booking->feedback)
        <p class="text-sm mt-2">Terima kasih. Rating Anda {{ $booking->feedback->rating }} / 5.</p>
        @if ($booking->feedback->comment)
            <p class="text-sm mt-2 whitespace-pre-wrap break-words">{{ $booking->feedback->comment }}</p>
        @endif
    @else
        <form method="POST" action="{{ route('bookings.feedback.store', $booking) }}" class="mt-3 max-w-md space-y-3">
            @csrf
            <label class="block text-sm font-semibold">Rating
                <select name="rating" required class="block mt-1 rounded-lg border border-line bg-white px-3 py-2">
                    <option value="">Pilih rating</option>
                    @foreach ([1, 2, 3, 4, 5] as $rating)
                        <option value="{{ $rating }}" @selected((string) old('rating') === (string) $rating)>{{ $rating }} / 5</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold">Komentar (opsional)
                <textarea name="comment" maxlength="1000" rows="3" class="w-full mt-1 rounded-lg border border-line p-3 font-normal">{{ old('comment') }}</textarea>
            </label>
            <button class="rounded-full bg-ink text-white px-4 py-2 text-sm font-semibold">Kirim feedback</button>
        </form>
    @endif
</section>
