<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ใบแจ้งหนี้ผู้เช่า ({{ $invoices->count() }} รายการ)</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700;800&display=swap');
*, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: 'Sarabun', sans-serif; background: #c8d4e3; font-size: 9.5pt; color: #000; line-height: 1.45; }
.toolbar { background: #1e293b; height: 52px; display: flex; align-items: center; justify-content: space-between; padding: 0 20px; position: sticky; top: 0; z-index: 200; gap: 8px; }
.toolbar-label { color: #cbd5e1; font-size: 13px; font-weight: 600; }
.tb-btn { display: inline-flex; align-items: center; gap: 5px; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 600; font-family: 'Sarabun', sans-serif; border: none; cursor: pointer; text-decoration: none; white-space: nowrap; line-height: 1; }
.tb-print { background: #3b82f6; color: #fff; }
.tb-print:hover { background: #2563eb; }
.tb-close { background: #374151; color: #d1d5db; }
.tb-close:hover { background: #4b5563; color: #fff; }
.page-wrapper { display: flex; flex-direction: column; align-items: center; padding: 30px 16px 50px; gap: 20px; }
.invoice-sheet { background: #fff; width: 210mm; min-height: 297mm; padding: 10mm 14mm; box-shadow: 0 6px 36px rgba(0,0,0,0.18); font-family: 'Sarabun', sans-serif; font-size: 9.5pt; color: #000; line-height: 1.45; flex-shrink: 0; }
.sheet-wrap:last-child { margin-bottom: 0; }

/* Items table */
.items-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
.items-table th { border: 1px solid #000; padding: 4px 5px; text-align: center; font-size: 8.5pt; font-weight: bold; line-height: 1.45; background: #fff; }
.items-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 5px 6px; font-size: 9pt; vertical-align: top; }
.items-table td.num { text-align: center; }
.items-table td.amt { text-align: right; white-space: nowrap; }
.items-table tr.filler td { height: 20px; border-top: none; border-bottom: none; }
.items-table tr.note-row td { border-top: 1px solid #ccc; border-bottom: 1px solid #000; font-size: 8.5pt; color: #333; padding: 4px 6px; }

@media print {
  @page { size: A4 portrait; margin: 0; }
  html, body { margin:0!important; padding:0!important; background:#fff!important; width:210mm!important; -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
  .toolbar { display:none!important; }
  .page-wrapper { display:block!important; padding:0!important; margin:0!important; width:210mm!important; }
  .sheet-wrap { page-break-after: always; }
  .sheet-wrap:last-child { page-break-after: auto; }
  .invoice-sheet { width:210mm!important; min-height:297mm!important; padding:10mm 14mm!important; box-shadow:none!important; font-size:9.5pt!important; line-height:1.45!important; }
  * { -webkit-text-size-adjust:none!important; text-size-adjust:none!important; }
  table,th,td { -webkit-print-color-adjust:exact!important; print-color-adjust:exact!important; }
}
</style>
</head>
<body>

{{-- ใบแจ้งหนี้หลายใบต่อกันในเอกสารเดียว (page-break-after ต่อใบ) เพื่อให้กด "พิมพ์ / Save PDF"
     ครั้งเดียวได้ไฟล์ PDF เดียวที่รวมทุกใบที่เลือกไว้ - โปรเจกต์นี้ไม่มี PDF library (mPDF/DomPDF)
     ติดตั้งอยู่ จึงใช้ browser print-to-PDF แบบเดียวกับ invoices/print.blade.php แทนการสร้างไฟล์จริงที่ server --}}
<div class="toolbar">
    <span class="toolbar-label">{{ $invoices->count() }} ใบแจ้งหนี้</span>
    <div style="display:flex; gap:8px;">
        <button onclick="window.print()" class="tb-btn tb-print">🖨 พิมพ์ / Save PDF ทั้งหมด</button>
        <button onclick="window.close()" class="tb-btn tb-close">✕ ปิด</button>
    </div>
</div>

<div class="page-wrapper">
    @forelse($invoices as $invoice)
        <div class="sheet-wrap">
            @include('invoices.partials.sheet', ['invoice' => $invoice, 'company' => $company, 'happyestPublic' => $happyestPublic])
        </div>
    @empty
        <p style="color:#374151; background:#fff; padding:16px 20px; border-radius:8px;">ไม่พบใบแจ้งหนี้ที่เลือก</p>
    @endforelse
</div>

</body>
</html>
