<?php

namespace App\Helpers;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ดึงไฟล์ PDF ใบแจ้งหนี้ตัวจริงจาก happyest (/admin/invoices/{id}/print?output=stream) แทนการสร้าง PDF
 * ซ้ำในแอปนี้เอง (ของเดิมคัดลอก Blade + คำนวณภาษีเองใน sheet.blade.php เสี่ยงตกหล่น/ไม่ตรงกับกฎที่
 * InvoicePdfService ฝั่ง happyest คำนวณจริง) - route นี้ป้องกันด้วย permission:finance.invoices.view
 * ของแอดมิน (ดู happyest routes/web.php) จึงต้อง login เป็นบัญชีแอดมินบริการ
 * (HAPPYEST_ADMIN_EMAIL/HAPPYEST_ADMIN_PASSWORD ใน .env) แบบ server-to-server ก่อนทุกครั้งที่ session
 * cookie ที่แคชไว้หมดอายุ/ยังไม่มี
 */
class HappyestAdminPdf
{
    private const COOKIE_CACHE_KEY = 'happyest_admin_pdf_cookies';

    public static function fetchInvoicePdf(int $invoiceId, string $format): string
    {
        $base = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
        $jar = self::loadCookieJar();

        $content = self::requestPdf($base, $invoiceId, $format, $jar, 'cached-session');

        if ($content === null) {
            self::login($base, $jar);
            $content = self::requestPdf($base, $invoiceId, $format, $jar, 'after-login');
        }

        if ($content === null) {
            throw new RuntimeException('ไม่สามารถดึงไฟล์ PDF ใบแจ้งหนี้จาก happyest ได้ (เข้าสู่ระบบหรือสิทธิ์บัญชีแอดมินบริการไม่ผ่าน) - ดูรายละเอียดใน storage/logs/laravel.log (HappyestAdminPdf)');
        }

        self::saveCookieJar($jar);

        return $content;
    }

    /** PendingRequest ตัวเดียวกันทุกจุด (User-Agent จริงกันบาง WAF/CDN บล็อก request ที่ไม่มี UA, timeout กันค้าง) */
    private static function client(CookieJar $jar)
    {
        return Http::withOptions(['cookies' => $jar])
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; MesukAgentPdfBridge/1.0)'])
            ->timeout(20);
    }

    /**
     * คืน null เมื่อยังไม่ได้ login/session หมดอายุ (redirect ไปหน้า login จะได้ HTML ไม่ใช่
     * application/pdf) - log สถานะ + ตัวอย่างเนื้อหาที่ได้กลับมาไว้เสมอตอนไม่ใช่ PDF เพื่อวินิจฉัยปัญหา
     * บน production ได้ (เช่น ถูก WAF บล็อก, permission ไม่พอ, session ไม่ persist ข้าม request)
     */
    private static function requestPdf(string $base, int $invoiceId, string $format, CookieJar $jar, string $attempt): ?string
    {
        $response = self::client($jar)->get("{$base}/admin/invoices/{$invoiceId}/print", [
            'format' => $format,
            'output' => 'stream',
        ]);

        $contentType = (string) $response->header('Content-Type');

        if (! $response->ok() || ! str_starts_with($contentType, 'application/pdf')) {
            Log::error('HappyestAdminPdf: print request did not return a PDF', [
                'attempt' => $attempt,
                'invoice_id' => $invoiceId,
                'base' => $base,
                'status' => $response->status(),
                'content_type' => $contentType,
                'body_snippet' => mb_substr(strip_tags($response->body()), 0, 300),
            ]);

            return null;
        }

        return $response->body();
    }

    private static function login(string $base, CookieJar $jar): void
    {
        $email = env('HAPPYEST_ADMIN_EMAIL');
        $password = env('HAPPYEST_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('ยังไม่ได้ตั้งค่า HAPPYEST_ADMIN_EMAIL/HAPPYEST_ADMIN_PASSWORD ใน .env');
        }

        $loginPage = self::client($jar)->get("{$base}/admin/login");

        if (! preg_match('/name="_token"\s+value="([^"]+)"/', $loginPage->body(), $matches)) {
            Log::error('HappyestAdminPdf: could not find CSRF token on happyest admin login page', [
                'base' => $base,
                'status' => $loginPage->status(),
                'body_snippet' => mb_substr(strip_tags($loginPage->body()), 0, 300),
            ]);

            throw new RuntimeException('อ่าน CSRF token จากหน้า login ของ happyest ไม่ได้');
        }

        $loginResponse = self::client($jar)->asForm()->post("{$base}/admin/login", [
            '_token' => $matches[1],
            'email' => $email,
            'password' => $password,
        ]);

        // Guzzle ตาม redirect (302) ให้อัตโนมัติ ปลายทางจึงเป็น 200 เสมอไม่ว่า login สำเร็จ (→ dashboard)
        // หรือไม่สำเร็จ (→ กลับหน้า login พร้อม error flash ใน session) - เช็ค status อย่างเดียวแยกไม่ออก
        // ต้องมองข้อความ error จริงในหน้าที่ redirect กลับมา (ฝังใน <script> Swal.fire ของ login.blade.php)
        $body = $loginResponse->body();
        $knownErrors = [
            'อีเมลหรือรหัสผ่านไม่ถูกต้อง' => 'invalid_credentials',
            'บัญชีของคุณถูกปิดการใช้งาน' => 'account_inactive',
            'เข้าสู่ระบบล้มเหลวหลายครั้ง' => 'rate_limited',
        ];
        $matchedError = null;
        foreach ($knownErrors as $needle => $reason) {
            if (str_contains($body, $needle)) {
                $matchedError = $reason;
                break;
            }
        }

        Log::error('HappyestAdminPdf: admin login POST result', [
            'base' => $base,
            'status' => $loginResponse->status(),
            'matched_error' => $matchedError,
            'still_on_login_page' => str_contains($body, 'เข้าสู่ระบบผู้ดูแล'),
        ]);
    }

    private static function loadCookieJar(): CookieJar
    {
        $stored = Cache::get(self::COOKIE_CACHE_KEY);

        return $stored ? new CookieJar(false, $stored) : new CookieJar;
    }

    private static function saveCookieJar(CookieJar $jar): void
    {
        Cache::put(self::COOKIE_CACHE_KEY, $jar->toArray(), now()->addHours(2));
    }
}
