<?php

namespace App\Http\Controllers;

use App\Models\HrProperty;

class AdsController extends Controller
{
    // ป้ายสถานะอสังหาฯ (ว่าง/ไม่ว่าง/จอง/โครงการในอนาคต) — อ้างอิง property_status_id
    private const STATUS_MAP = [
        'available'      => ['color' => 'green',  'label' => 'ว่าง'],
        'unavailable'    => ['color' => 'red',    'label' => 'ไม่ว่าง'],
        'booked'         => ['color' => 'yellow', 'label' => 'จอง'],
        'future_project' => ['color' => 'blue',   'label' => 'โครงการในอนาคต'],
    ];

    public function index()
    {
        $properties = HrProperty::whereNull('deleted_at')
            ->with(['propertyStatus', 'primaryImageMedia', 'manager', 'owner', 'creatorAgent'])
            ->orderByDesc('id')
            ->get()
            ->map(function (HrProperty $property) {
                $slug = $property->propertyStatus?->slug ?? 'available';
                $status = self::STATUS_MAP[$slug] ?? self::STATUS_MAP['available'];

                $property->status_slug   = $slug;
                $property->status_color  = $status['color'];
                $property->status_label  = $status['label'];
                $property->is_published  = $property->status === 'published';
                $property->search_text   = strtolower(
                    ($property->title ?? '') . ' ' .
                    ($property->property_code ?? '') . ' ' .
                    ($property->district ?? '') . ' ' .
                    ($property->province ?? '')
                );

                return $property;
            });

        logSystem(
            userType: 'agent',
            userId: session('agent_id'),
            module: 'Ads',
            action: 'VIEW',
            description: 'Agent viewed ads/listing page'
        );

        return view('ads.index', compact('properties'));
    }
}
