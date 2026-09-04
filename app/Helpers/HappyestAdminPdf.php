<?php

namespace App\Helpers;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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

        $content = self::requestPdf($base, $invoiceId, $format, $jar);

        if ($content === null) {
            self::login($base, $jar);
            $content = self::requestPdf($base, $invoiceId, $format, $jar);
        }

        if ($content === null) {
            throw new RuntimeException('ไม่สามารถดึงไฟล์ PDF ใบแจ้งหนี้จาก happyest ได้ (เข้าสู่ระบบหรือสิทธิ์บัญชีแอดมินบริการไม่ผ่าน)');
        }

        self::saveCookieJar($jar);

        return $content;
    }

    /** คืน null เมื่อยังไม่ได้ login/session หมดอายุ (redirect ไปหน้า login จะได้ HTML ไม่ใช่ application/pdf) */
    private static function requestPdf(string $base, int $invoiceId, string $format, CookieJar $jar): ?string
    {
        $response = Http::withOptions(['cookies' => $jar])
            ->get("{$base}/admin/invoices/{$invoiceId}/print", [
                'format' => $format,
                'output' => 'stream',
            ]);

        if (! $response->ok() || ! str_starts_with((string) $response->header('Content-Type'), 'application/pdf')) {
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

        $loginPage = Http::withOptions(['cookies' => $jar])->get("{$base}/admin/login");

        if (! preg_match('/name="_token"\s+value="([^"]+)"/', $loginPage->body(), $matches)) {
            throw new RuntimeException('อ่าน CSRF token จากหน้า login ของ happyest ไม่ได้');
        }

        Http::asForm()->withOptions(['cookies' => $jar])->post("{$base}/admin/login", [
            '_token' => $matches[1],
            'email' => $email,
            'password' => $password,
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
