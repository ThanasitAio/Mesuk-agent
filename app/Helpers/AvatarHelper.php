<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('getAvatarPath')) {
    /**
     * หา path ของไฟล์ avatar ที่มีอยู่จริง
     * รองรับทั้ง path แบบเก่าและใหม่
     *
     * @param string|null $avatarPath path ของ avatar จากฐานข้อมูล
     * @return string|null full path ของไฟล์ หรือ null ถ้าไม่พบ
     */
    function getAvatarPath(?string $avatarPath): ?string
    {
        if (!$avatarPath) {
            return null;
        }

        // ลองหาไฟล์จาก storage paths ที่เป็นไปได้
        $possiblePaths = [
            $avatarPath,                    // avatars/1/file.jpg
            'public/' . $avatarPath,        // public/avatars/1/file.jpg
        ];

        foreach ($possiblePaths as $path) {
            if (Storage::disk('payment_storage')->exists($path)) {
                return Storage::disk('payment_storage')->path($path);
            }
        }

        return null;
    }
}

if (!function_exists('getAvatarUrl')) {
    /**
     * สร้าง URL สำหรับแสดง avatar
     * 
     * @param int $agentId
     * @return string
     */
    function getAvatarUrl(int $agentId): string
    {
        return route('avatar.serve', $agentId);
    }
}
