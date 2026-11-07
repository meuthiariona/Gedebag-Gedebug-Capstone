<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Header keamanan HTTP — perbaikan atas temuan OWASP ZAP:
 * CSP tidak ada, anti-clickjacking tidak ada, X-Content-Type-Options tidak ada,
 * dan bocornya versi PHP lewat X-Powered-By.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Sembunyikan versi PHP (temuan "Server Leaks Information via X-Powered-By")
        header_remove('X-Powered-By');
        $response->headers->remove('X-Powered-By');

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');               // anti-clickjacking
        $response->headers->set('X-Content-Type-Options', 'nosniff');           // cegah MIME sniffing
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $response->headers->set('Content-Security-Policy', $this->csp());

        return $response;
    }

    /**
     * Daftar sumber yang diizinkan memuat skrip/gaya/gambar.
     * Catatan: 'unsafe-inline' & 'unsafe-eval' masih dibutuhkan karena Tailwind & Alpine dimuat dari CDN
     * (versi CDN menyuntik style dan mengevaluasi ekspresi). Untuk produksi, aset di-build lewat Vite
     * supaya kedua pengecualian ini bisa dihapus (dicatat sebagai risiko sisa di laporan).
     */
    private function csp(): string
    {
        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "frame-src https://maps.google.com https://www.google.com",
            "connect-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
