# การแก้ไขปัญหาการแสดงรูปภาพตัวแทน (Avatar)

## ปัญหาที่พบ
รูปภาพตัวแทนไม่แสดงใน Sidebar, Navbar และ Profile เนื่องจาก path ไม่ถูกต้อง

## สาเหตุ
1. การบันทึกไฟล์ใช้ prefix `public/` แต่ควรเป็นแบบเดียวกับ payment slips (ไม่มี prefix)
2. Path ในหน้า Profile ใช้ hardcoded URL แทนที่จะใช้ route helper
3. ไม่มี backward compatibility สำหรับรูปภาพเก่าที่อาจบันทึกด้วย path แตกต่างกัน

## การแก้ไข

### 1. ProfileController (`app/Http/Controllers/ProfileController.php`)

#### uploadPhoto()
- **เดิม:** บันทึกไฟล์ไปที่ `public/avatars/{id}/filename`
- **ใหม่:** บันทึกไฟล์ไปที่ `avatars/{id}/filename` (ไม่มี public/ prefix)
- เหตุผล: ให้สอดคล้องกับวิธีการจัดเก็บ payment slips

```php
// เดิม
Storage::disk('payment_storage')->putFileAs('public/' . $dir, $file, $name);
Storage::disk('payment_storage')->delete('public/' . $agent->avatar);

// ใหม่
Storage::disk('payment_storage')->putFileAs($dir, $file, $name);
Storage::disk('payment_storage')->delete($agent->avatar);
```

#### serveAvatar()
- เพิ่ม backward compatibility โดยลอง 2 path:
  1. `avatars/{id}/filename` (แบบใหม่)
  2. `public/avatars/{id}/filename` (แบบเก่า)

```php
public function serveAvatar($agentId)
{
    $agent = HrAgent::findOrFail($agentId);
    
    if (!$agent->avatar) {
        abort(404, 'ไม่พบรูปภาพ');
    }
    
    $possiblePaths = [
        $agent->avatar,
        'public/' . $agent->avatar,
    ];
    
    foreach ($possiblePaths as $path) {
        if (Storage::disk('payment_storage')->exists($path)) {
            return response()->file(Storage::disk('payment_storage')->path($path));
        }
    }
    
    abort(404, 'ไม่พบไฟล์รูปภาพ');
}
```

### 2. Routes (`routes/web.php`)
- ย้าย route `/avatar/{agentId}` ออกจาก auth middleware
- เหตุผล: ให้สามารถเข้าถึงได้แบบ public เพื่อแสดงรูปใน context ต่างๆ

```php
// Public Routes
Route::get('/avatar/{agentId}', [ProfileController::class, 'serveAvatar'])->name('avatar.serve');
```

### 3. Profile View (`resources/views/profile/show.blade.php`)
- **เดิม:** ใช้ hardcoded URL `{{ $happyestPublic }}/storage/{{ $agent->avatar }}`
- **ใหม่:** ใช้ route helper `{{ route('avatar.serve', $agent->id) }}`
- เหตุผล: ใช้ consistent method ในการแสดงไฟล์

### 4. Layout (`resources/views/layouts/app.blade.php`)
- เพิ่มการแสดงรูปภาพตัวแทนใน 3 จุด:
  1. **Sidebar (Desktop)** - User Info at Bottom
  2. **Navbar (Desktop)** - User Dropdown  
  3. **Bottom Sheet (Mobile)** - User Info

```blade
@if(session('agent_avatar'))
    <img src="{{ route('avatar.serve', session('agent_id')) }}"
         alt="{{ session('agent_name', 'ผู้ใช้') }}"
         class="w-9 h-9 rounded-full object-cover ring-2 ring-brand-600">
@else
    <div class="w-9 h-9 bg-brand-600 rounded-full flex items-center justify-center">
        <!-- SVG Icon -->
    </div>
@endif
```

### 5. AuthController (`app/Http/Controllers/AuthController.php`)
- เพิ่ม `agent_avatar` ใน session เมื่อ login

```php
session([
    'agent_logged_in' => true,
    'agent_id'        => $agent->id,
    'agent_name'      => $fullName ?: $agent->agent_code,
    'agent_code'      => $agent->agent_code,
    'agent_avatar'    => $agent->avatar,  // เพิ่มบรรทัดนี้
]);
```

## โครงสร้างไฟล์

### Storage Path
```
happyest/storage/app/
├── avatars/
│   ├── 1/
│   │   └── av_6694d2c3a1b2c3.12345678.jpg
│   ├── 2/
│   │   └── av_6694d2c3a1b2c4.87654321.png
│   └── ...
└── payment_slips/
    ├── 123/
    └── ...
```

### Database
```sql
-- hr_agents table
avatar: "avatars/1/av_6694d2c3a1b2c3.12345678.jpg"
```

### URL
```
GET /avatar/1  →  ProfileController@serveAvatar
    → อ่านไฟล์จาก: happyest/storage/app/avatars/1/av_xxx.jpg
    → response()->file()
```

## การทดสอบ

### 1. ทดสอบอัปโหลดรูปใหม่
- ไปที่หน้า Profile
- คลิกที่รูปโปรไฟล์
- เลือกรูปภาพ (JPG, PNG, WebP ไม่เกิน 3MB)
- ตรวจสอบว่ารูปแสดงใน:
  - หน้า Profile
  - Sidebar (Desktop)
  - Navbar (Desktop)
  - Bottom Sheet (Mobile)

### 2. ทดสอบ Fallback
- Login ด้วย agent ที่ไม่มีรูปภาพ
- ตรวจสอบว่าแสดง icon SVG แทน

### 3. ทดสอบ Backward Compatibility
- สำหรับ agent ที่มีรูปภาพเก่าที่บันทึกด้วย path `public/avatars/...`
- ตรวจสอบว่ารูปยังแสดงได้ปกติ

## หมายเหตุ

- Avatar ถูกเก็บใน `payment_storage` disk ซึ่งชี้ไปที่ `happyest/storage/app`
- ไม่ใช้ Laravel's `storage:link` เพราะไฟล์อยู่ใน external storage
- Route `/avatar/{agentId}` เป็น public route (ไม่ต้อง login)
- Session `agent_avatar` จะถูกอัพเดตทันทีหลังอัปโหลดรูปใหม่
