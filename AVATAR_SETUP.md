# คำแนะนำการตั้งค่ารูปภาพตัวแทน (Avatar)

## โครงสร้างระบบ

ระบบ Agent Management ใช้รูปภาพตัวแทนร่วมกับระบบ Happy Realestate โดย:

```
C:/laragon/www/
├── happyest/                     # ระบบหลัก Happy Realestate
│   └── storage/app/
│       ├── avatars/              # รูปภาพตัวแทน
│       │   ├── 1/
│       │   ├── 2/
│       │   └── ...
│       ├── payment_slips/        # สลิปการชำระเงิน
│       └── public/
│           └── avatars/          # (รูปภาพเก่าอาจอยู่ที่นี่)
│
└── agent/                        # ระบบ Agent Management (โปรเจคนี้)
    ├── storage/app/
    └── ...
```

## การตั้งค่า

### 1. แก้ไขไฟล์ `.env`

```env
# Path ไปยัง storage ของระบบ happyest
HAPPYEST_STORAGE_PATH=C:/laragon/www/happyest/storage/app

# URL ของระบบ happyest (สำหรับ fallback หรือ redirect)
HAPPYEST_APP_URL=http://127.0.0.1/happyest/public
```

**หมายเหตุ:** 
- ใช้ forward slash (`/`) แทน backslash (`\`) แม้ใน Windows
- Path ต้องชี้ไปที่ folder `storage/app` ของระบบ happyest

### 2. ตรวจสอบว่า Path ถูกต้อง

เปิด Terminal และรันคำสั่ง:

```bash
# Windows (PowerShell)
Test-Path "C:\laragon\www\happyest\storage\app"

# หรือใน CMD
dir C:\laragon\www\happyest\storage\app
```

ถ้าไม่มี error แสดงว่า path ถูกต้อง

### 3. Rebuild Autoload (ถ้าจำเป็น)

```bash
composer dump-autoload
```

หรือถ้าไม่มี composer ใน PATH ให้รัน:

```bash
php artisan optimize:clear
```

## โครงสร้างการทำงาน

### การบันทึกรูปภาพ

เมื่อตัวแทนอัปโหลดรูปโปรไฟล์:

```php
// ProfileController@uploadPhoto()
$path = "avatars/{agent_id}/av_xxxxx.jpg";
Storage::disk('payment_storage')->putFileAs($dir, $file, $name);
$agent->avatar = $path;
$agent->save();
```

ไฟล์จะถูกบันทึกที่:
```
C:/laragon/www/happyest/storage/app/avatars/{agent_id}/av_xxxxx.jpg
```

### การแสดงรูปภาพ

เมื่อเรียก URL `/avatar/{agentId}`:

```php
// ProfileController@serveAvatar()
$fullPath = getAvatarPath($agent->avatar);
return response()->file($fullPath);
```

Helper `getAvatarPath()` จะลองหาไฟล์จาก:
1. `avatars/1/file.jpg` (แบบใหม่)
2. `public/avatars/1/file.jpg` (แบบเก่า - backward compatibility)

### ตัวอย่าง URL

```
http://127.0.0.1/agent/public/avatar/385
```

จะแสดงรูปภาพของตัวแทน ID 385 จาก:
```
C:/laragon/www/happyest/storage/app/avatars/385/av_xxxxx.jpg
```

## การแสดงรูปในระบบ

รูปภาพตัวแทนจะแสดงใน:

1. **Sidebar (Desktop)** - ด้านล่างซ้าย
2. **Navbar (Desktop)** - มุมขวาบน
3. **Bottom Sheet (Mobile)** - เมื่อคลิก "เพิ่มเติม"
4. **Profile Page** - หน้าโปรไฟล์ของฉัน

### ตัวอย่างโค้ดใน Blade

```blade
@if(session('agent_avatar'))
    <img src="{{ route('avatar.serve', session('agent_id')) }}"
         alt="{{ session('agent_name') }}"
         class="w-9 h-9 rounded-full object-cover ring-2 ring-brand-600">
@else
    <div class="w-9 h-9 bg-brand-600 rounded-full flex items-center justify-center">
        <!-- SVG Icon -->
    </div>
@endif
```

## การแก้ไขปัญหา

### ปัญหา: รูปภาพไม่แสดง

1. ตรวจสอบว่า `.env` ตั้งค่า `HAPPYEST_STORAGE_PATH` ถูกต้อง
2. ตรวจสอบว่าไฟล์มีอยู่จริงใน `happyest/storage/app/avatars/`
3. ตรวจสอบว่า field `avatar` ในตาราง `hr_agents` มีค่า
4. ดู error ใน Laravel log: `storage/logs/laravel.log`

### ปัญหา: Permission Denied

ตั้งค่า permission ของ folder:

```bash
# Linux/Mac
chmod -R 755 storage/app/avatars

# Windows - ไม่จำเป็นต้องทำ
```

### ปัญหา: 404 Not Found

ตรวจสอบ routes:

```bash
php artisan route:list | grep avatar
```

ควรเห็น:
```
GET|HEAD  avatar/{agentId}  avatar.serve  › ProfileController@serveAvatar
```

### Debug Mode

เปิด debug mode ชั่วคราวใน `.env`:

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

จากนั้นเปิด browser และดู error message

## Helper Functions

### getAvatarPath()

```php
$fullPath = getAvatarPath($agent->avatar);
// Returns: "C:/laragon/www/happyest/storage/app/avatars/1/file.jpg"
// หรือ null ถ้าไม่พบไฟล์
```

### getAvatarUrl()

```php
$url = getAvatarUrl($agent->id);
// Returns: "http://127.0.0.1/agent/public/avatar/1"
```

## ข้อควรระวัง

1. **ใช้ path เดียวกัน**: ระบบ agent และ happyest ต้องอ้างถึง folder เดียวกัน
2. **ไม่ delete ไฟล์**: เมื่อลบตัวแทนในระบบหลัก ไฟล์ avatar อาจยังคงอยู่
3. **Cache**: Browser อาจ cache รูปภาพ ใช้ Ctrl+F5 เพื่อ hard refresh
4. **URL เป็น Public**: Route `/avatar/{agentId}` ไม่ต้อง login (เพื่อให้แสดงได้ทุกที่)

## สรุป

- ✅ ระบบ agent ใช้รูปภาพจากระบบ happyest ไม่ต้อง sync หรือ copy
- ✅ เมื่ออัปโหลดรูปใหม่ จะบันทึกไปที่ `happyest/storage/app/avatars/`
- ✅ รองรับ backward compatibility สำหรับรูปเก่า
- ✅ แสดงผลได้ทุกที่ผ่าน route `/avatar/{agentId}`
