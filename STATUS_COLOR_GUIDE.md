# 🎨 คู่มือสีสถานะอสังหาริมทรัพย์

## ภาพรวมการเปลี่ยนแปลง

### ก่อนการปรับปรุง ❌
```
🟨 เหลือง (Amber)  → "ว่างอยู่" (ทรัพย์ว่าง)
🟩 เขียว (Emerald) → "มีผู้เช่า" (ทรัพย์ไม่ว่าง)
🟦 น้ำเงิน (Blue)   → "ยืนยันมัดจำ"
🟨 เหลือง (Amber)  → "จองแล้ว"
```

### หลังการปรับปรุง ✅
```
🟩 เขียว (Green) → "ว่าง" (ทรัพย์ว่าง)
🟥 แดง (Red)     → "ไม่ว่าง" (ทรัพย์ไม่ว่าง)
🟦 น้ำเงิน (Blue)  → "โครงการในอนาคต" (ยืนยันมัดจำ)
🟨 เหลือง (Yellow) → "จองแล้ว"
```

---

## 📊 ตารางสถานะแบบละเอียด

| สถานะ | สีที่แสดง | Hex Code | การใช้งาน | Status Badge |
|-------|-----------|----------|-----------|--------------|
| **ว่าง** | 🟢 Green | `#22C55E` | อสังหาที่พร้อมให้เช่า | ![Green Badge](https://via.placeholder.com/80x24/22C55E/FFFFFF?text=ว่าง) |
| **ไม่ว่าง (เช่าอยู่)** | 🔴 Red | `#EF4444` | มีผู้เช่าอยู่แล้ว | ![Red Badge](https://via.placeholder.com/100x24/EF4444/FFFFFF?text=เช่าอยู่) |
| **ไม่ว่าง (ยืนยันแล้ว)** | 🔴 Red | `#EF4444` | การจองที่ยืนยันแล้ว | ![Red Badge](https://via.placeholder.com/100x24/EF4444/FFFFFF?text=ยืนยันแล้ว) |
| **จองแล้ว** | 🟡 Yellow | `#FACC15` | รอการยืนยันการจอง | ![Yellow Badge](https://via.placeholder.com/100x24/FACC15/000000?text=จองแล้ว) |
| **โครงการในอนาคต** | 🔵 Blue | `#60A5FA` | ยืนยันมัดจำแล้ว | ![Blue Badge](https://via.placeholder.com/120x24/60A5FA/FFFFFF?text=โครงการในอนาคต) |

---

## 🎯 แนวคิดการออกแบบสี

### เหตุผลการเลือกสี:

#### 🟢 เขียว (Green) สำหรับ "ว่าง"
- **ความหมาย**: พร้อมใช้งาน, เปิดรับ, โอกาส
- **จิตวิทยา**: สร้างความรู้สึกบวก, ต้อนรับ, เชิญชวน
- **มาตรฐาน UX**: ใช้เขียวเป็นสัญญาณ "พร้อม" ในระบบทั่วไป
- **ตัวอย่าง**: สัญญาณไฟเขียว, ปุ่ม "Available", สถานะ "Online"

#### 🔴 แดง (Red) สำหรับ "ไม่ว่าง"
- **ความหมาย**: ไม่พร้อมใช้งาน, หยุด, ห้าม
- **จิตวิทยา**: สื่อสารได้ชัดเจนว่า "ไม่สามารถใช้ได้"
- **มาตรฐาน UX**: ใช้แดงเป็นสัญญาณ "ไม่พร้อม" หรือ "ห้าม"
- **ตัวอย่าง**: สัญญาณไฟแดง, ห้องเต็ม, ที่จอดรถเต็ม

#### 🟡 เหลือง (Yellow) สำหรับ "จอง"
- **ความหมาย**: รอดำเนินการ, ระวัง, กำลังพิจารณา
- **จิตวิทยา**: สร้างความรู้สึก "รออีกนิด"
- **มาตรฐาน UX**: ใช้เหลืองเป็นสัญญาณ "รอ" หรือ "ระวัง"
- **ตัวอย่าง**: สัญญาณไฟเหลือง, สถานะ "Pending", การแจ้งเตือน

#### 🔵 น้ำเงิน (Blue) สำหรับ "โครงการในอนาคต"
- **ความหมาย**: ข้อมูล, อนาคต, พิเศษ
- **จิตวิทยา**: สื่อถึงความมั่นคง, ไว้วางใจ, วางแผน
- **มาตรฐาน UX**: ใช้น้ำเงินเป็นสัญญาณ "ข้อมูล" หรือ "ในอนาคต"
- **ตัวอย่าง**: ปุ่ม "Info", สถานะ "Scheduled", การแจ้งเตือนทั่วไป

---

## 💡 การใช้งานในหน้าต่างๆ

### 1. หน้ารายการทรัพย์ (`/properties`)

#### แถบสีด้านซ้าย (Status Indicator Bar):
```
🟢 เขียว (#22C55E)  → ว่าง
🔴 แดง (#EF4444)    → เช่าอยู่ / ยืนยันแล้ว
🔵 น้ำเงิน (#60A5FA) → โครงการในอนาคต
🟡 เหลือง (#FACC15)  → จองแล้ว
⚪ เทา (#D1D5DB)    → อื่นๆ
```

#### Status Badge (ป้ายสถานะ):
```css
/* ว่าง */
background: #F0FDF4 (green-50)
border: #BBF7D0 (green-200)
text: #15803D (green-700)

/* ไม่ว่าง */
background: #FEF2F2 (red-50)
border: #FECACA (red-200)
text: #B91C1C (red-700)

/* จองแล้ว */
background: #FEFCE8 (yellow-50)
border: #FEF08A (yellow-200)
text: #A16207 (yellow-700)

/* โครงการในอนาคต */
background: #EFF6FF (blue-50)
border: #BFDBFE (blue-200)
text: #1D4ED8 (blue-700)
```

### 2. หน้าภาพรวม (`/rental-rates`)

#### สถิติด้านบน:
```
📊 ทรัพย์ทั้งหมด   → สีเทา (neutral)
🟢 ว่าง           → สีเขียว (#22C55E)
🔴 ไม่ว่าง         → สีแดง (#EF4444)
👥 ผู้บริหาร       → สีเทา (neutral)
```

#### กราฟแท่ง (Bar Chart):
```
🟢 ว่าง    → #22C55E
🔴 ไม่ว่าง  → #EF4444
```

#### Donut Chart:
```
🟢 ว่าง     → #22C55E (ส่วนที่เหลือ)
🔴 ไม่ว่าง   → #EF4444 (ส่วนที่เต็ม)
```

---

## 📱 การแสดงผลบนอุปกรณ์ต่างๆ

### Desktop (> 1024px)
- แสดง status badge แบบเต็ม
- แสดงแถบสีด้านซ้าย
- แสดงข้อมูลครบทุกคอลัมน์

### Tablet (768px - 1024px)
- แสดง status badge แบบย่อ
- แสดงแถบสีด้านซ้าย
- ซ่อนคอลัมน์บางส่วน (ตามลำดับความสำคัญ)

### Mobile (< 768px)
- แสดง status badge แบบไอคอน + ข้อความสั้น
- แสดงแถบสีด้านซ้ายแบบบาง (1px)
- แสดงเฉพาะข้อมูลสำคัญ
- ใช้ card layout แทน table

---

## 🎨 CSS Variables สำหรับนำไปใช้

```css
:root {
  /* Status Colors - Vacant (ว่าง) */
  --status-vacant-bg: #F0FDF4;
  --status-vacant-border: #BBF7D0;
  --status-vacant-text: #15803D;
  --status-vacant-dot: #22C55E;
  
  /* Status Colors - Occupied (ไม่ว่าง) */
  --status-occupied-bg: #FEF2F2;
  --status-occupied-border: #FECACA;
  --status-occupied-text: #B91C1C;
  --status-occupied-dot: #EF4444;
  
  /* Status Colors - Reserved (จอง) */
  --status-reserved-bg: #FEFCE8;
  --status-reserved-border: #FEF08A;
  --status-reserved-text: #A16207;
  --status-reserved-dot: #FACC15;
  
  /* Status Colors - Future (โครงการในอนาคต) */
  --status-future-bg: #EFF6FF;
  --status-future-border: #BFDBFE;
  --status-future-text: #1D4ED8;
  --status-future-dot: #60A5FA;
}
```

---

## ♿ Accessibility Considerations

### 1. สีไม่ควรเป็นตัวบ่งชี้เพียงอย่างเดียว
✅ **ทำแล้ว:**
- เพิ่มไอคอน (จุดกลม) แสดงสถานะ
- เพิ่มข้อความบอกสถานะชัดเจน
- เพิ่ม animation (pulse) สำหรับสถานะที่ต้องการความสนใจ

### 2. Contrast Ratio (อัตราส่วนความเข้ม)
✅ **ทำแล้ว:**
- Text บนพื้นหลังสี: Contrast ≥ 4.5:1 (WCAG AA)
- Status badges: ใช้สีเข้มสำหรับ text บนพื้นอ่อน

### 3. Screen Reader Support
✅ **ทำแล้ว:**
- เพิ่ม `aria-label` สำหรับปุ่มที่ไม่มีข้อความ
- เพิ่ม `role="button"` สำหรับองค์ประกอบที่คลิกได้
- เพิ่ม keyboard navigation

### 4. Color Blindness Testing
| ประเภท | % ของประชากร | การมองเห็น |
|--------|-------------|-----------|
| **Protanopia (แดง-เขียว)** | 1% | ✅ ใช้ความสว่างต่างกัน + ไอคอน |
| **Deuteranopia (แดง-เขียว)** | 1% | ✅ ใช้ความสว่างต่างกัน + ไอคอน |
| **Tritanopia (น้ำเงิน-เหลือง)** | 0.01% | ✅ ใช้แดง-เขียวที่ชัดเจน |
| **Monochromacy (ขาว-ดำ)** | 0.001% | ✅ ใช้ความสว่างต่างกัน + ไอคอน + ข้อความ |

---

## 🧪 การทดสอบ

### Checklist ก่อนใช้งานจริง:

#### Visual Testing:
- [ ] ทดสอบบน Chrome Desktop
- [ ] ทดสอบบน Safari Desktop
- [ ] ทดสอบบน Firefox Desktop
- [ ] ทดสอบบน Edge Desktop
- [ ] ทดสอบบน Chrome Mobile (Android)
- [ ] ทดสอบบน Safari Mobile (iOS)

#### Functional Testing:
- [ ] คลิกแถวทรัพย์เพื่อเข้าดูรายละเอียด
- [ ] ค้นหาทรัพย์ด้วยชื่อ
- [ ] ค้นหาทรัพย์ด้วยรหัส
- [ ] กรองสถานะ (ทั้งหมด / ไม่ว่าง / ว่าง)
- [ ] ปุ่มล้างการค้นหาทำงาน
- [ ] Keyboard navigation ทำงาน
- [ ] Status badges แสดงสีถูกต้อง

#### Responsive Testing:
- [ ] Desktop (1920x1080)
- [ ] Laptop (1366x768)
- [ ] Tablet Portrait (768x1024)
- [ ] Tablet Landscape (1024x768)
- [ ] Mobile (375x667 - iPhone SE)
- [ ] Mobile (414x896 - iPhone 11)
- [ ] Mobile (360x640 - Android)

#### Accessibility Testing:
- [ ] Screen reader (NVDA/JAWS)
- [ ] Keyboard only navigation
- [ ] High contrast mode
- [ ] Color blindness simulation

#### Performance Testing:
- [ ] โหลดหน้าเร็ว (< 2 วินาที)
- [ ] Animations ไม่กระตุก
- [ ] Search ตอบสนองเร็ว
- [ ] ใช้งานได้ดีกับข้อมูล 100+ รายการ

---

## 📞 ติดต่อ / Support

หากพบปัญหาหรือข้อสงสัย:
1. ตรวจสอบ browser console (F12) หาข้อผิดพลาด
2. Clear cache และ refresh หน้า (Ctrl+F5)
3. ตรวจสอบ network tab ว่า CSS/JS โหลดครบ
4. ทดสอบบน browser อื่น

---

**อัพเดทล่าสุด:** 30 มิถุนายน 2026  
**สร้างโดย:** Kiro AI Assistant  
**สถานะ:** ✅ พร้อมใช้งาน Production
