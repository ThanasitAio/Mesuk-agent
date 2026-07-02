-- ย้ายอสังหาริมทรัพย์ทั้งหมดที่อยู่ภายใต้ผู้บริหารโครงการ (manager_agent_code)
-- จากรหัส 0000001 ไปอยู่ภายใต้รหัส 0000305
-- ตาราง: hr_properties (โปรเจกต์ happyest — เขียนเฉพาะข้อมูล ไม่แก้ไขไฟล์ happyest)

START TRANSACTION;

-- 1) ตรวจสอบว่า agent ปลายทาง (0000305) มีอยู่จริงและ active
SELECT agent_code, name, is_active
FROM hr_agents
WHERE agent_code IN ('0000001', '0000305');

-- 2) ดูจำนวน property ที่จะได้รับผลกระทบ ก่อนอัปเดตจริง
SELECT COUNT(*) AS properties_to_move
FROM hr_properties
WHERE manager_agent_code = '0000001';

-- 3) อัปเดตจริง
UPDATE hr_properties
SET manager_agent_code = '0000305'
WHERE manager_agent_code = '0000001';

-- 4) ตรวจผลลัพธ์หลังอัปเดต
SELECT COUNT(*) AS remaining_under_0000001
FROM hr_properties
WHERE manager_agent_code = '0000001';

SELECT COUNT(*) AS now_under_0000305
FROM hr_properties
WHERE manager_agent_code = '0000305';

-- ถ้าผลลัพธ์ถูกต้อง ให้ COMMIT; ถ้าไม่ถูกต้อง ให้ ROLLBACK
COMMIT;
-- ROLLBACK;
