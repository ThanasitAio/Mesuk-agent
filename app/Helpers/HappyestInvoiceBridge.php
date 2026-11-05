<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ใบแจ้งหนี้ต้องตรงข้อมูลล่าสุดก่อนแนบสลิป (งาน #81, บัญชี 2026-10-30: "หน้าต่างๆ ที่เกี่ยวกับแนบสลิปทั้งหมด รวมถึงระบบผู้บริหาร
 * โครงการ ... ต้องอัพเดทข้อมูลใบแจ้งหนี้ก่อนทุกครั้งถึงจะแนบสลิปได้ และต้องบังคับอัปเดทด้วย")
 *
 * การตรวจ/อัปเดตใบ (InvoiceRefreshService) อยู่ใน happyest เท่านั้น - แอปนี้ส่งคำขอไป POST {HAPPYEST_APP_URL}/api/agent-bridge/slip-invoices
 * (happyest AgentBridgeController) ให้ happyest อัปเดตใบที่ข้อมูลเปลี่ยนของรายการนั้นทันที แล้วตอบว่าใบไหนเพิ่งอัปเดต / ใบไหนต้องออกใหม่
 * ลายเซ็น = HMAC-SHA256 ของ "{เวลา}.{body}" ด้วยกุญแจใน hr_settings `agent_bridge.secret` (ฐานข้อมูลเดียวกัน ไม่ต้องตั้ง .env เพิ่ม
 * ฝั่งไหนเรียกก่อนสร้างกุญแจ - happyest App\Helpers\AgentBridge)
 */
class HappyestInvoiceBridge
{
    private const SETTING_KEY = 'agent_bridge.secret';

    /**
     * action: view = เปิดหน้ารอบบิล (ยอดที่เห็นต้องเป็นใบล่าสุด) · upload = ก่อนเก็บสลิปที่แนบแทนลูกค้า
     *
     * @param  int[]  $recordIds
     * @return array{ok: bool, refreshed: array<int, array{code: string, labels: string[], resend: bool}>, blocked: array<int, array{code: string, reason: string}>, error: ?string}
     */
    public static function prepareSlip(int $bookingId, array $recordIds, string $action): array
    {
        $out = ['ok' => true, 'refreshed' => [], 'blocked' => [], 'error' => null];
        $recordIds = array_values(array_unique(array_map('intval', $recordIds)));
        if (! $recordIds) {
            return $out;
        }

        $base = rtrim(env('HAPPYEST_APP_URL', 'http://127.0.0.1/happyest/public'), '/');
        $body = json_encode([
            'agent_code' => (string) session('agent_code'),
            'manager_name' => (string) session('agent_name'),
            'booking_id' => $bookingId,
            'record_ids' => $recordIds,
            'action' => $action,
        ], JSON_UNESCAPED_UNICODE);

        try {
            $timestamp = (string) time();
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'User-Agent' => 'Mozilla/5.0 (compatible; MesukAgentInvoiceBridge/1.0)',
                'X-Bridge-Timestamp' => $timestamp,
                'X-Bridge-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, self::secret()),
            ])
                ->connectTimeout(5)
                ->timeout(20)
                ->withBody($body, 'application/json')
                ->post("{$base}/api/agent-bridge/slip-invoices");
        } catch (\Throwable $e) {
            Log::error('HappyestInvoiceBridge: request did not complete', ['base' => $base, 'booking_id' => $bookingId, 'action' => $action, 'error' => $e->getMessage()]);

            return ['ok' => false, 'error' => 'เชื่อมต่อระบบหลักไม่ได้'] + $out;
        }

        if (! $response->ok() || ! $response->json('success')) {
            Log::error('HappyestInvoiceBridge: happyest refused', [
                'base' => $base,
                'booking_id' => $bookingId,
                'action' => $action,
                'status' => $response->status(),
                'body_snippet' => mb_substr(strip_tags($response->body()), 0, 300),
            ]);

            return ['ok' => false, 'error' => 'ระบบหลักตอบ HTTP '.$response->status()] + $out;
        }

        $out['refreshed'] = array_values((array) $response->json('refreshed', []));
        foreach ((array) $response->json('blocked', []) as $recordId => $block) {
            $out['blocked'][(int) $recordId] = ['code' => (string) ($block['code'] ?? ''), 'reason' => (string) ($block['reason'] ?? '')];
        }

        return $out;
    }

    /** ข้อความหนึ่งบรรทัด: ใบที่ระบบเพิ่งอัปเดต จัดกลุ่มตามหัวข้อที่เปลี่ยน (สูงสุด 3 หัวข้อ) "IRS1, IRS2 (บัญชีรับเงิน)" */
    public static function refreshedText(array $refreshed): string
    {
        return collect($refreshed)
            ->groupBy(fn ($r) => implode(', ', array_slice((array) ($r['labels'] ?? []), 0, 3)))
            ->map(fn ($items, $labels) => $items->pluck('code')->implode(', ').($labels !== '' ? " ({$labels})" : ''))
            ->implode(' · ');
    }

    private static function secret(): string
    {
        $secret = DB::table('hr_settings')->where('key', self::SETTING_KEY)->value('value');
        if (! $secret) {
            DB::table('hr_settings')->insertOrIgnore([
                'key' => self::SETTING_KEY,
                'value' => Str::random(64),
                'type' => 'string',
                'group' => 'agent_bridge',
                'description' => 'กุญแจลงลายเซ็นคำขอจากระบบผู้บริหารโครงการ (App\Helpers\AgentBridge) ห้ามแก้',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $secret = DB::table('hr_settings')->where('key', self::SETTING_KEY)->value('value');
        }

        return (string) $secret;
    }
}
