<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * บังคับให้ทุก request วิ่งบน scheme + host เดียวกับ APP_URL (canonical)
 *
 * เหตุผล: session cookie ถูกออกด้วย domain/secure ตามค่าใน .env
 * ถ้าผู้ใช้เปิดเว็บด้วย host อื่น (เช่น ไม่มี www) หรือด้วย http
 * เบราว์เซอร์จะปฏิเสธ cookie ทั้งหมด -> ไม่มี session, CSRF token ไม่ตรง
 * -> ล็อกอินไม่ได้ทั้งที่กรอกรหัสถูก (พบบ่อยบนมือถือที่พิมพ์ URL เอง)
 */
class CanonicalUrl
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'));
        $host      = $canonical['host']   ?? '';
        $scheme    = $canonical['scheme'] ?? '';

        // ทำงานเฉพาะเมื่อ APP_URL เป็น https (production) - local ข้ามทั้งหมด
        if ($scheme !== 'https' || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $next($request);
        }

        // redirect ได้เฉพาะ GET/HEAD - ถ้า redirect POST ข้อมูลในฟอร์มจะหาย
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        // health check เรียกจากภายในเครื่องด้วย host ที่ไม่ใช่ canonical
        if ($request->is('up')) {
            return $next($request);
        }

        if ($request->getHost() === $host && $request->isSecure()) {
            return $next($request);
        }

        return redirect()->to('https://' . $host . $request->getRequestUri(), 301);
    }
}
