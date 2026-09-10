<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.agent' => \App\Http\Middleware\AgentAuthMiddleware::class,
        ]);

        // บังคับ https + host เดียวกับ APP_URL ก่อนที่ session/CSRF จะทำงาน
        // (ถ้าเข้าด้วย host อื่นหรือ http เบราว์เซอร์จะทิ้ง session cookie -> ล็อกอินไม่ได้)
        $middleware->prepend(\App\Http\Middleware\CanonicalUrl::class);

        // Trust Cloudflare/reverse proxy so Laravel reads X-Forwarded-Proto correctly
        // (without this, HTTPS requests behind the proxy look like HTTP internally,
        // which drops the Secure session cookie right after login).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // แทนหน้า "419 Page Expired" ด้วยการกลับไปหน้าล็อกอินพร้อมข้อความภาษาไทย
        // (เกิดเมื่อเปิดหน้าค้างไว้นานแล้วค่อยกดเข้าสู่ระบบ - พบบ่อยบนมือถือ)
        // หมายเหตุ: Laravel แปลง TokenMismatchException เป็น HttpException 419 ก่อนถึง callback
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'CSRF token mismatch.'], 419);
            }

            return redirect()->route('login')
                ->withInput($request->except(['password', '_token']))
                ->with('error', 'หน้าเข้าสู่ระบบหมดอายุ กรุณากรอกรหัสและกดเข้าสู่ระบบอีกครั้ง');
        });
    })->create();
