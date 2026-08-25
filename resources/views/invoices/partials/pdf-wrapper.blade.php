{{-- ห่อ invoices/partials/sheet.blade.php สำหรับ mPDF โดยเฉพาะ - ไม่มี toolbar/page-wrapper/ขนาด
     A4 แบบหน้าจอ (mPDF กำหนดระยะขอบหน้ากระดาษเองผ่าน margin_top/right/bottom/left ใน config
     ไม่ใช่ CSS) ตัด .invoice-sheet ที่มีขนาดตายตัวสำหรับพรีวิวบนเบราว์เซอร์ออก เหลือแค่สไตล์ตารางที่จำเป็น
     ต่อการจัดวางจริง - เหมือนแพทเทิร์นของ happyest print-company.blade.php ที่แยก CSS สำหรับ
     forPdf ออกจาก CSS หน้าจอ --}}
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-size: 9.5pt; color: #000; line-height: 1.45; }

.items-table { width: 100%; border-collapse: collapse; border: 1.5px solid #000; }
.items-table th { border: 1px solid #000; padding: 4px 5px; text-align: center; font-size: 8.5pt; font-weight: bold; line-height: 1.45; background: #fff; }
.items-table td { border-left: 1px solid #000; border-right: 1px solid #000; padding: 5px 6px; font-size: 9pt; vertical-align: top; }
.items-table td.num { text-align: center; }
.items-table td.amt { text-align: right; white-space: nowrap; }
.items-table tr.filler td { height: 20px; border-top: none; border-bottom: none; }
.items-table tr.note-row td { border-top: 1px solid #ccc; border-bottom: 1px solid #000; font-size: 8.5pt; color: #333; padding: 4px 6px; }
</style>
</head>
<body>
@include('invoices.partials.sheet', ['invoice' => $invoice, 'company' => $company, 'happyestPublic' => $happyestPublic])
</body>
</html>
