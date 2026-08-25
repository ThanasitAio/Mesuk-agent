@extends('layouts.app')

@section('title', $monthLabel . ' · ใบแจ้งหนี้ผู้เช่า')
@section('breadcrumb', 'ใบแจ้งหนี้ผู้เช่า งวด ' . $monthLabel)

@section('content')

@php
    $typeStyleFor = function ($invoice) {
        $key = match (true) {
            ($invoice->invoice_sub_type ?? null) === 'land_tax' => 'land_tax',
            ($invoice->invoice_sub_type ?? null) === 'stamp_duty' => 'stamp_duty',
            ($invoice->invoice_sub_type ?? null) === 'side_area' => 'side_area',
            $invoice->invoice_type === 'monthly_rent' => 'rent',
            $invoice->invoice_type === 'deposit' => 'deposit',
            $invoice->invoice_type === 'service_fee' => 'service_fee',
            default => 'other',
        };

        return match ($key) {
            'rent'         => ['bg-emerald-50 text-emerald-700', 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
            'land_tax'     => ['bg-amber-50 text-amber-700', 'M3 21h18M5 21V7l8-4v18M13 9h6v12M9 9v.01M9 12v.01M9 15v.01M9 18v.01'],
            'stamp_duty'   => ['bg-violet-50 text-violet-700', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            'side_area'    => ['bg-cyan-50 text-cyan-700', 'M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4'],
            'deposit'      => ['bg-sky-50 text-sky-700', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
            'service_fee'  => ['bg-amber-50 text-amber-700', 'M9 7h6m0 10v-3m-3 3v-1m-3 1v-2m9-4H5a2 2 0 00-2 2v6a2 2 0 002 2h14a2 2 0 002-2v-6a2 2 0 00-2-2z'],
            default        => ['bg-gray-100 text-gray-600', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        };
    };

    $payStyleFor = function (?string $status) {
        return match ($status) {
            'paid' => ['bg-emerald-50 text-emerald-700', 'ชำระแล้ว', 'M5 13l4 4L19 7'],
            'pending_verification' => ['bg-blue-50 text-blue-700', 'รอตรวจสอบ', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            default => ['bg-gray-100 text-gray-500', 'ยังไม่แนบสลิป', 'M6 18L18 6M6 6l12 12'],
        };
    };
@endphp

{{-- ── Header ──────────────────────────────────────────────────────────────── --}}
<div class="flex items-center justify-between gap-3 mb-4">
    <div class="min-w-0">
        <a href="{{ route('tenant-invoices.index') }}" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-400 hover:text-brand-600 transition-colors mb-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            ใบแจ้งหนี้ผู้เช่าทั้งหมด
        </a>
        <h2 class="text-xl font-black text-gray-800 truncate">{{ $monthLabel }}</h2>
    </div>
</div>

{{-- ── Search + Filters ────────────────────────────────────────────────────── --}}
<form method="GET" action="{{ route('tenant-invoices.show', $month) }}" id="ti-filter-form" class="mb-4">
    <input type="hidden" name="pay_status" value="{{ $payFilter }}">
    <div class="relative border border-gray-300 rounded-xl bg-white transition-all focus-within:ring-2 focus-within:ring-brand-500/20 focus-within:border-brand-500 mb-2.5">
        <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
        </svg>
        <input type="text" name="q" id="ti-search-input" value="{{ $search }}" placeholder="ค้นหารหัสทรัพย์ / ผู้เช่า / เลขที่ใบแจ้งหนี้..."
               class="w-full pl-11 pr-9 py-2.5 text-sm bg-transparent focus:outline-none text-gray-800 placeholder-gray-400">
        @if($search !== '')
            <button type="button" onclick="document.getElementById('ti-search-input').value=''; document.getElementById('ti-filter-form').submit();"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        @endif
    </div>

    @php
        $filterLink = fn ($status) => route('tenant-invoices.show', array_merge(['month' => $month], request()->except(['pay_status', 'page']), ['pay_status' => $status]));
    @endphp
    <div class="flex gap-1.5 bg-gray-100 rounded-xl p-1.5 overflow-x-auto w-fit">
        <a href="{{ $filterLink('all') }}" class="flex-shrink-0 flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $payFilter === 'all' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            ทั้งหมด
            <span class="text-[10px] font-bold bg-gray-400 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none">{{ $payCounts['all'] }}</span>
        </a>
        <a href="{{ $filterLink('paid') }}" class="flex-shrink-0 flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $payFilter === 'paid' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            ชำระแล้ว
            <span class="text-[10px] font-bold bg-emerald-500 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none">{{ $payCounts['paid'] }}</span>
        </a>
        <a href="{{ $filterLink('unpaid') }}" class="flex-shrink-0 flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $payFilter === 'unpaid' ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-700' }}">
            ยังไม่แนบสลิป
            <span class="text-[10px] font-bold bg-gray-400 text-white rounded-full min-w-[18px] h-[18px] px-1 flex items-center justify-center leading-none">{{ $payCounts['unpaid'] }}</span>
        </a>
    </div>
</form>

{{-- ── Toolbar (select-all + bulk download) ───────────────────────────────── --}}
<div id="ti-toolbar" class="flex items-center justify-between gap-2 py-2 px-1 border-b border-gray-100 mb-3">
    <div class="flex items-center gap-2.5">
        <input type="checkbox" id="ti-select-all" class="w-4 h-4 rounded accent-brand-600 cursor-pointer" {{ $invoices->isEmpty() ? 'disabled' : '' }}>
        <span id="ti-toolbar-count" class="text-xs font-semibold text-gray-500">{{ $invoices->total() }} รายการ</span>
    </div>
    <div id="ti-toolbar-actions">
        <button type="button" id="ti-download-all-btn" @if($invoices->isEmpty()) disabled @endif
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-full text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            ดาวน์โหลดทั้งหมด
        </button>
    </div>
</div>

{{-- ── List ─────────────────────────────────────────────────────────────── --}}
<div id="ti-list" class="flex flex-col gap-2 mb-4">
    @forelse($invoices as $invoice)
        @php
            $propCode = $invoice->snapshot_property['property_code'] ?? $invoice->property?->property_code ?? '-';
            $tenantName = $invoice->snapshot_customer['name'] ?? $invoice->customer?->full_name ?? '-';
            $bookingCode = $invoice->booking?->booking_code ?? '-';
            $downloadUrl = route('tenant-invoices.download', $invoice->id);
            [$typeClasses, $typeIcon] = $typeStyleFor($invoice);
            [$payClasses, $payLabel, $payIcon] = $payStyleFor($invoice->pay_summary['status'] ?? null);
            $transferDates = $invoice->pay_summary['transfer_dates'] ?? collect();
        @endphp
        <div class="ti-row flex flex-col gap-2 bg-white border border-gray-100 rounded-xl px-3.5 py-3 shadow-sm hover:shadow-md transition-shadow"
             data-id="{{ $invoice->id }}" data-invoice-code="{{ $invoice->invoice_code }}" data-download-url="{{ $downloadUrl }}">
            <div class="flex items-center gap-3">
                <input type="checkbox" class="ti-row-check w-4 h-4 rounded accent-brand-600 cursor-pointer flex-shrink-0" aria-label="เลือก {{ $invoice->invoice_code }}">
                <span class="w-9 h-9 rounded-lg bg-red-50 text-red-500 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div class="flex-1 min-w-0 flex items-center gap-2 flex-wrap">
                    <span class="font-mono text-xs font-bold text-amber-700 bg-amber-50 rounded-full px-2 py-0.5 flex-shrink-0">{{ $propCode }}</span>
                    <span class="text-sm font-semibold text-gray-800 truncate">{{ $tenantName }}</span>
                </div>
                <button type="button" class="ti-row-dl flex-shrink-0 w-8 h-8 flex items-center justify-center rounded-full text-gray-400 hover:bg-brand-50 hover:text-brand-600 transition-colors disabled:opacity-50"
                        data-download-url="{{ $downloadUrl }}" data-invoice-code="{{ $invoice->invoice_code }}" title="ดาวน์โหลด">
                    <svg class="w-4 h-4 ti-dl-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                </button>
            </div>
            <div class="pl-[52px] flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $typeClasses }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $typeIcon }}"/></svg>
                    {{ $invoice->detailed_type_label }}
                </span>
                <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2 py-0.5 rounded-full {{ $payClasses }}">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $payIcon }}"/></svg>
                    {{ $payLabel }}
                </span>
                <span class="text-[11px] text-gray-400 whitespace-nowrap">จอง {{ $bookingCode }} · เลขที่ {{ $invoice->invoice_code }} · {{ ($invoice->issued_date ?? $invoice->created_at)->format('d/m/Y') }}</span>
                @if($transferDates->isNotEmpty())
                    <span class="text-[11px] text-gray-400 w-full">โอนวันที่ {{ $transferDates->map(fn ($d) => $d->format('d/m/Y'))->implode(', ') }}</span>
                @endif
            </div>
        </div>
    @empty
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-10 text-center">
            <div class="w-12 h-12 bg-gray-50 rounded-xl flex items-center justify-center mx-auto mb-3">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>
            <p class="text-gray-700 font-semibold text-sm">ไม่พบใบแจ้งหนี้ที่ตรงกับคำค้นหา</p>
            <p class="text-gray-400 text-xs mt-1">ลองคำค้นหาหรือตัวกรองอื่น</p>
        </div>
    @endforelse
</div>

<x-pagination :paginator="$invoices->withQueryString()" label="ใบแจ้งหนี้" />

{{-- ── Bulk-download progress overlay ─────────────────────────────────────── --}}
<div id="ti-progress-overlay" class="fixed inset-0 z-[10700] hidden items-center justify-center p-4" style="background:rgba(28,53,20,0.45);">
    <div class="bg-white rounded-2xl p-5 w-full max-w-xs text-center">
        <h5 class="font-bold text-gray-800 text-sm mb-1">กำลังดาวน์โหลด</h5>
        <p id="ti-progress-sub" class="text-xs text-gray-500 mb-3.5">กำลังเตรียมไฟล์...</p>
        <div class="bg-brand-50 rounded-full h-2 overflow-hidden mb-2">
            <div id="ti-progress-bar" class="bg-brand-600 h-full transition-all" style="width:0%"></div>
        </div>
        <div class="flex justify-between text-xs text-gray-500">
            <span>สำเร็จ: <strong id="ti-progress-ok">0</strong></span>
            <span>ทั้งหมด: <strong id="ti-progress-total">0</strong></span>
        </div>
        <button type="button" id="ti-progress-close" style="display:none;" class="mt-3.5 w-full py-2 text-xs font-bold rounded-full text-white bg-brand-600 hover:bg-brand-700">ปิด</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const currentMonth = @js($month);
    const currentQuery = @js($search);
    const currentPayStatus = @js($payFilter);
    const totalFilteredCount = {{ (int) $invoices->total() }};
    const bulkListUrl = @js(route('tenant-invoices.bulk-list'));

    // ── Persistent cross-page selection cart (sessionStorage, keyed per month) - pagination
    // is a full page reload, so a selected item must stay selected/counted after visiting
    // page 2 and back, and "select all" must cover every page, not just the one on screen. ──
    const STORAGE_KEY = 'ti_sel_' + currentMonth;
    function loadSelection() {
        try { return JSON.parse(sessionStorage.getItem(STORAGE_KEY) || '[]'); } catch (e) { return []; }
    }
    function saveSelection() {
        try { sessionStorage.setItem(STORAGE_KEY, JSON.stringify(selection)); } catch (e) {}
    }
    let selection = loadSelection();
    function isSelected(id) { return selection.some(s => s.id === id); }
    function addSelection(item) { if (!isSelected(item.id)) { selection.push(item); saveSelection(); } }
    function removeSelection(id) { selection = selection.filter(s => s.id !== id); saveSelection(); }
    function emptySelection() { selection = []; saveSelection(); }

    async function fetchAllMatchingItems() {
        const url = bulkListUrl + '?month=' + encodeURIComponent(currentMonth)
            + '&q=' + encodeURIComponent(currentQuery)
            + '&pay_status=' + encodeURIComponent(currentPayStatus);
        const res = await fetch(url, { credentials: 'same-origin' });
        const data = await res.json();
        return (data.items || []).map(it => ({ id: it.invoice_id, invoice_code: it.invoice_code, download_url: it.download_url }));
    }

    // ── Row selection ──
    const toolbarCount = document.getElementById('ti-toolbar-count');
    const toolbarActions = document.getElementById('ti-toolbar-actions');
    const selectAllBox = document.getElementById('ti-select-all');
    const rows = Array.from(document.querySelectorAll('.ti-row'));
    const totalCountLabel = totalFilteredCount + ' รายการ';

    rows.forEach(row => {
        const id = parseInt(row.dataset.id, 10);
        if (isSelected(id)) {
            row.querySelector('.ti-row-check').checked = true;
            row.classList.add('ring-2', 'ring-brand-200', 'bg-brand-50/40');
        }
    });

    function renderToolbar() {
        const count = selection.length;
        if (count > 0) {
            toolbarCount.textContent = count + ' รายการที่เลือก';
            toolbarActions.innerHTML = '<div style="display:flex;align-items:center;gap:8px;"></div>';
            const inner = toolbarActions.firstElementChild;

            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'w-7 h-7 flex items-center justify-center rounded-full text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition-colors';
            clearBtn.setAttribute('aria-label', 'ล้างการเลือก');
            clearBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>';
            clearBtn.addEventListener('click', clearSelection);

            const dlBtn = document.createElement('button');
            dlBtn.type = 'button';
            dlBtn.className = 'inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-full text-white bg-brand-600 hover:bg-brand-700 transition-colors';
            dlBtn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>ดาวน์โหลด (' + count + ')';
            dlBtn.addEventListener('click', () => downloadSelected(selection.slice()));

            inner.appendChild(clearBtn);
            inner.appendChild(dlBtn);
            selectAllBox.checked = count >= totalFilteredCount;
            selectAllBox.indeterminate = count > 0 && count < totalFilteredCount;
        } else {
            toolbarCount.textContent = totalCountLabel;
            selectAllBox.checked = false;
            selectAllBox.indeterminate = false;
            toolbarActions.innerHTML = '<button type="button" id="ti-download-all-btn" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-bold rounded-full text-white bg-brand-600 hover:bg-brand-700 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"' + (totalFilteredCount === 0 ? ' disabled' : '') + '><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>ดาวน์โหลดทั้งหมด</button>';
            document.getElementById('ti-download-all-btn')?.addEventListener('click', downloadAllForMonth);
        }
    }

    function clearSelection() {
        emptySelection();
        rows.forEach(r => { r.querySelector('.ti-row-check').checked = false; r.classList.remove('ring-2', 'ring-brand-200', 'bg-brand-50/40'); });
        renderToolbar();
    }

    rows.forEach(row => {
        const box = row.querySelector('.ti-row-check');
        const id = parseInt(row.dataset.id, 10);
        box.addEventListener('click', e => e.stopPropagation());
        row.querySelector('.ti-row-dl').addEventListener('click', e => {
            e.stopPropagation();
            downloadSingle(e.currentTarget);
        });
        box.addEventListener('change', () => {
            row.classList.toggle('ring-2', box.checked);
            row.classList.toggle('ring-brand-200', box.checked);
            row.classList.toggle('bg-brand-50/40', box.checked);
            if (box.checked) {
                addSelection({ id: id, invoice_code: row.dataset.invoiceCode, download_url: row.dataset.downloadUrl });
            } else {
                removeSelection(id);
            }
            renderToolbar();
        });
    });

    selectAllBox?.addEventListener('change', async () => {
        if (selectAllBox.checked) {
            selectAllBox.disabled = true;
            try {
                selection = await fetchAllMatchingItems();
                saveSelection();
            } catch (e) {
                selectAllBox.checked = false;
                selectAllBox.disabled = false;
                return;
            }
            selectAllBox.disabled = false;
        } else {
            emptySelection();
        }
        rows.forEach(r => {
            const id = parseInt(r.dataset.id, 10);
            const box = r.querySelector('.ti-row-check');
            box.checked = isSelected(id);
            r.classList.toggle('ring-2', box.checked);
            r.classList.toggle('ring-brand-200', box.checked);
            r.classList.toggle('bg-brand-50/40', box.checked);
        });
        renderToolbar();
    });

    renderToolbar(); // reflect any selection restored from the cart immediately on load

    // ── Real file download (fetch + blob) - mirrors happyest investor.invoices exactly, so
    // mobile behaves the same way that page already does in production: showSaveFilePicker/
    // showDirectoryPicker only exist on desktop Chrome/Edge, so on mobile (and Safari/Firefox)
    // both helpers below immediately fall through to the a[download] blob branch, which is
    // what actually triggers a real file save on Android Chrome and iOS Safari. ──
    function parseFilename(headerValue, fallback) {
        if (!headerValue) return fallback;
        const starMatch = headerValue.match(/filename\*=UTF-8''([^;]+)/i);
        if (starMatch) { try { return decodeURIComponent(starMatch[1].trim()); } catch (e) {} }
        const plainMatch = headerValue.match(/filename="([^"]+)"/i);
        return plainMatch ? plainMatch[1] : fallback;
    }

    async function pickSaveHandle(suggestedName) {
        if (!window.showSaveFilePicker) return { handle: null, cancelled: false };
        try {
            const handle = await window.showSaveFilePicker({
                suggestedName,
                types: [{ description: 'PDF', accept: { 'application/pdf': ['.pdf'] } }],
            });
            return { handle, cancelled: false };
        } catch (e) {
            if (e && e.name === 'AbortError') return { handle: null, cancelled: true };
            return { handle: null, cancelled: false };
        }
    }

    async function pickDirectoryHandle() {
        if (!window.showDirectoryPicker) return { handle: null, cancelled: false };
        try {
            const handle = await window.showDirectoryPicker();
            return { handle, cancelled: false };
        } catch (e) {
            if (e && e.name === 'AbortError') return { handle: null, cancelled: true };
            return { handle: null, cancelled: false };
        }
    }

    async function downloadSingle(btn) {
        if (btn.disabled) return;
        const url = btn.dataset.downloadUrl;
        const invoiceCode = btn.dataset.invoiceCode;
        const fallbackName = 'invoice-' + invoiceCode + '.pdf';

        // showSaveFilePicker ต้องเรียกอยู่ใน user-gesture เดียวกับคลิกปุ่มเสมอ (ก่อน await fetch ใดๆ)
        // ไม่งั้น activation จะหมดไปกับการรอ mPDF render แล้วเบราว์เซอร์โยน SecurityError
        const { handle, cancelled } = await pickSaveHandle(fallbackName);
        if (cancelled) return;

        btn.disabled = true;
        btn.querySelector('.ti-dl-icon')?.classList.add('animate-spin');
        try {
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) throw new Error('bad response');
            const blob = await res.blob();
            if (handle) {
                const writable = await handle.createWritable();
                await writable.write(blob);
                await writable.close();
            } else {
                const filename = parseFilename(res.headers.get('content-disposition'), fallbackName);
                const blobUrl = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = blobUrl;
                a.download = filename;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
            }
        } catch (e) {
            alert('สร้าง PDF ไม่สำเร็จ กรุณาลองใหม่');
        } finally {
            btn.disabled = false;
            btn.querySelector('.ti-dl-icon')?.classList.remove('animate-spin');
        }
    }

    // ── Bulk download (progress overlay + sequential fetch+blob, one real file per invoice) ──
    const overlay = document.getElementById('ti-progress-overlay');
    const barFill = document.getElementById('ti-progress-bar');
    const sub = document.getElementById('ti-progress-sub');
    const okEl = document.getElementById('ti-progress-ok');
    const totalEl = document.getElementById('ti-progress-total');
    const closeBtn = document.getElementById('ti-progress-close');

    // dirHandle มาจาก showDirectoryPicker ที่เรียกครั้งเดียวก่อนเริ่ม loop (ไม่ใช่ถามทีละไฟล์) ถ้าเบราว์เซอร์
    // ไม่รองรับ (มือถือทุกตัว/Safari/Firefox) หรือผู้ใช้ไม่ได้เลือกโฟลเดอร์ จะ fallback ไปที่ a.download ทีละไฟล์
    async function downloadItems(items, dirHandle) {
        closeBtn.style.display = 'none';
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        barFill.style.width = '0%';
        okEl.textContent = '0';
        totalEl.textContent = items.length;

        let ok = 0;
        for (let i = 0; i < items.length; i++) {
            sub.textContent = 'กำลังดาวน์โหลด ' + items[i].invoice_code + '...';
            try {
                const res = await fetch(items[i].download_url, { credentials: 'same-origin' });
                if (!res.ok) throw new Error('bad response');
                const blob = await res.blob();
                const filename = parseFilename(res.headers.get('content-disposition'), 'invoice-' + items[i].invoice_code + '.pdf');
                if (dirHandle) {
                    const fileHandle = await dirHandle.getFileHandle(filename, { create: true });
                    const writable = await fileHandle.createWritable();
                    await writable.write(blob);
                    await writable.close();
                } else {
                    const blobUrl = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = blobUrl;
                    a.download = filename;
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    setTimeout(() => URL.revokeObjectURL(blobUrl), 2000);
                }
                ok++;
            } catch (e) {
                console.error('[ti] download failed:', items[i].invoice_code, e);
            }
            okEl.textContent = ok;
            barFill.style.width = Math.round(((i + 1) / items.length) * 100) + '%';
        }
        sub.textContent = 'ดาวน์โหลดเสร็จสิ้น';
        closeBtn.style.display = 'inline-flex';
    }

    async function downloadSelected(items) {
        if (items.length === 0) return;
        const { handle, cancelled } = await pickDirectoryHandle();
        if (cancelled) return;
        await downloadItems(items, handle);
    }

    async function downloadAllForMonth() {
        const { handle, cancelled } = await pickDirectoryHandle();
        if (cancelled) return;

        closeBtn.style.display = 'none';
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');
        sub.textContent = 'กำลังโหลดรายการ...';
        barFill.style.width = '0%';
        okEl.textContent = '0';
        totalEl.textContent = '0';

        let items = [];
        try {
            items = await fetchAllMatchingItems();
        } catch (e) {
            sub.textContent = 'โหลดรายการไม่สำเร็จ กรุณาลองใหม่';
            closeBtn.style.display = 'inline-flex';
            return;
        }

        if (items.length === 0) {
            sub.textContent = 'ไม่มีใบแจ้งหนี้ให้ดาวน์โหลดตามตัวกรองนี้';
            closeBtn.style.display = 'inline-flex';
            return;
        }

        await downloadItems(items, handle);
    }

    closeBtn?.addEventListener('click', () => {
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    });

    // renderToolbar() (above) always (re)builds #ti-download-all-btn and binds this listener
    // itself, including on the initial call already made above - no separate top-level binding
    // needed (and adding one here would double-fire the picker on click).
})();
</script>
@endpush

@endsection
