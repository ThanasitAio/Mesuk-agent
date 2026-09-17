<?php
// ====== ปิดการแสดง Error (สำหรับ Production) ======
ini_set('display_errors', 0);
error_reporting(0);

// ====== เพิ่ม memory และ time limit สำหรับข้อมูลเยอะ ======
ini_set('memory_limit', '512M');
set_time_limit(300); // 5 นาที

try {
    require_once(__DIR__ . '/../../../PHPExcel/Classes/PHPExcel.php');
    require_once("../../../config/database.php");
    require_once("../../../app/core/Database.php");

    // รับพารามิเตอร์
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $role = isset($_GET['role']) ? trim($_GET['role']) : '';
    $code = isset($_GET['code']) ? trim($_GET['code']) : '';
    $startMonth = isset($_GET['startMonth']) ? trim($_GET['startMonth']) : '';
    $startYear = isset($_GET['startYear']) ? trim($_GET['startYear']) : '';
    $endMonth = isset($_GET['endMonth']) ? trim($_GET['endMonth']) : '';
    $endYear = isset($_GET['endYear']) ? trim($_GET['endYear']) : '';

    // ตรวจสอบค่าที่จำเป็น
    if (empty($startMonth) || empty($startYear) || empty($endMonth) || empty($endYear)) {
        throw new Exception('ข้อมูลไม่ครบถ้วน กรุณาระบุเดือนและปี');
    }

    // ====== กำหนดค่าต่าง ๆ ======
    $excelFileName = 'รายงานมิเตอร์_' . $startMonth . '_' . $startYear . '_ถึง_' . $endMonth . '_' . $endYear . '.xlsx';
    $sheetName = 'รายงานมิเตอร์';

    // ====== สร้างไฟล์ Excel และชีตแรก ======
    $objPHPExcel = new PHPExcel();
    $sheet1 = $objPHPExcel->getActiveSheet();
    $sheet1->setTitle($sheetName);

    // ====== สร้างเงื่อนไขการค้นหา ======
    $whrData = "";
    if (!empty($search)) {
        $search = str_replace(array("'", "\\"), array("''", "\\\\"), $search);
        $whrData .= " AND (p.pcode LIKE '%$search%' OR p.pdesc LIKE '%$search%')";
    }
    if (!empty($role) && $role == 'agent') {
        $whrData .= " AND (p.sales_rep_code = '$code')";
    }

    // ====== ดึงข้อมูลสินค้า ======
    $sqlProducts = "SELECT DISTINCT
        p.pcode,
        p.pdesc,
        pc.cate_name,
        COALESCE(pg1.groupname, pg2.groupname) as groupname,
        COALESCE(p.meter_1_ppu, 0) as electricity_ppu,
        COALESCE(p.meter_0_ppu, 0) as water_ppu
    FROM ali_productcategory pc
    LEFT JOIN ali_productgroup pg1 ON pc.id = pg1.id_cate
    LEFT JOIN ali_product p ON pg1.id = p.group_id
    LEFT JOIN ali_productgroup pg2 ON p.group_id = pg2.id
    WHERE (pc.id = 34 OR pc.id = 54) AND p.sh = 1 $whrData
    ORDER BY pc.cate_name, groupname, p.pcode";

    $stmtProducts = Database::query($sqlProducts);
    $products = $stmtProducts->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
        throw new Exception('ไม่พบข้อมูลสินค้า');
    }

    // ====== ฟังก์ชันคำนวณหน่วยมิเตอร์ ======
    function calculateMeterUnits($current, $previous, $meterReset, $meterChanged, $oldMeterFinal, $newMeterStart) {
        $meterReset = (int)$meterReset;
        $meterChanged = (int)$meterChanged;
        $oldMeterFinal = $oldMeterFinal !== null ? (float)$oldMeterFinal : null;
        $newMeterStart = $newMeterStart !== null ? (float)$newMeterStart : null;
        
        // กรณีมิเตอร์รีเซ็ต
        if ($meterReset == 1) {
            $maxMeterValue = 9999;
            if ($previous >= 10000) $maxMeterValue = 99999;
            else if ($previous >= 1000) $maxMeterValue = 9999;
            else if ($previous >= 100) $maxMeterValue = 999;
            
            $percentFull = ($previous / $maxMeterValue) * 100;
            if ($percentFull >= 95) {
                return ($maxMeterValue - $previous) + $current;
            }
        }
        
        // กรณีเปลี่ยนมิเตอร์
        if ($meterChanged == 1 && $oldMeterFinal !== null && $newMeterStart !== null) {
            $unitsFromOldMeter = $oldMeterFinal - $previous;
            $unitsFromNewMeter = $current - $newMeterStart;
            return $unitsFromOldMeter + $unitsFromNewMeter;
        }
        
        // กรณีปกติ
        return max(0, $current - $previous);
    }

    // ====== สร้างหัวตาราง ======
    $headers = array(
        'ลำดับ',
        'รหัสห้อง',
        'รายละเอียด',
        'เดือน',
        'ปี',
        'วันที่อ่านไฟ',
        'มิเตอร์ไฟก่อนหน้า',
        'มิเตอร์ไฟปัจจุบัน',
        'หน่วยไฟใช้',
        'ราคา/หน่วย(ไฟ)',
        'ค่าไฟ',
        'วันที่อ่านน้ำ',
        'มิเตอร์น้ำก่อนหน้า',
        'มิเตอร์น้ำปัจจุบัน',
        'หน่วยน้ำใช้',
        'ราคา/หน่วย(น้ำ)',
        'ค่าน้ำ',
        'ค่าขยะ',
        'ค่าส่วนกลาง',
        'รวม',
        'สถานะ',
        'หมายเหตุ'
    );

    $colChar = 'A';
    $colIndex = 1;
    foreach ($headers as $header) {
        $sheet1->setCellValue($colChar . '1', $header);
        $colChar++;
    }

    // ====== จัดรูปแบบหัวตาราง ======
    $headerStyle = array(
        'font' => array(
            'bold' => true,
            'color' => array('rgb' => 'FFFFFF'),
            'size' => 12,
            'name' => 'TH Sarabun New'
        ),
        'fill' => array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array('rgb' => '4472C4')
        ),
        'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
        ),
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => '000000')
            )
        )
    );
    $sheet1->getStyle('A1:V1')->applyFromArray($headerStyle);

    // ====== ดึงข้อมูลมิเตอร์ตามช่วงเวลา ======
    $rowNum = 2;
    $orderNum = 1;

    foreach ($products as $product) {
        $pcode = $product['pcode'];
        
        // วนลูปตามช่วงเดือน-ปีที่เลือก
        $currentYear = (int)$startYear;
        $currentMonth = (int)$startMonth;
        $lastYear = (int)$endYear;
        $lastMonth = (int)$endMonth;
        
        while (true) {
            // ตรวจสอบว่าเกินช่วงที่กำหนดหรือไม่
            if ($currentYear > $lastYear || ($currentYear == $lastYear && $currentMonth > $lastMonth)) {
                break;
            }
            
            // ดึงข้อมูลมิเตอร์สำหรับ pcode และเดือน-ปีนั้น
            $sqlMeter = "SELECT 
                meter_type, 
                reading_value, 
                reading_date,
                remark,
                meter_reset,
                meter_changed,
                old_meter_final,
                new_meter_start
            FROM me_meter 
            WHERE pcode = ? AND month = ? AND year = ?";
            
            $stmtMeter = Database::query($sqlMeter, [$pcode, $currentMonth, $currentYear]);
            $meterData = $stmtMeter->fetchAll(PDO::FETCH_ASSOC);
            
            // จัดระเบียบข้อมูลมิเตอร์
            $electricityReading = 0;
            $waterReading = 0;
            $garbageCost = 0;
            $commonAreaCost = 0;
            $dateElectricity = '';
            $dateWater = '';
            $remark = '';
            $electricityReset = 0;
            $electricityChanged = 0;
            $electricityOldFinal = null;
            $electricityNewStart = null;
            $waterReset = 0;
            $waterChanged = 0;
            $waterOldFinal = null;
            $waterNewStart = null;
            
            foreach ($meterData as $meter) {
                if ($meter['meter_type'] == 'ค่าไฟ') {
                    $electricityReading = (int)$meter['reading_value'];
                    $dateElectricity = $meter['reading_date'];
                    $electricityReset = (int)$meter['meter_reset'];
                    $electricityChanged = (int)$meter['meter_changed'];
                    $electricityOldFinal = $meter['old_meter_final'];
                    $electricityNewStart = $meter['new_meter_start'];
                } elseif ($meter['meter_type'] == 'ค่าน้ำ') {
                    $waterReading = (int)$meter['reading_value'];
                    $dateWater = $meter['reading_date'];
                    $waterReset = (int)$meter['meter_reset'];
                    $waterChanged = (int)$meter['meter_changed'];
                    $waterOldFinal = $meter['old_meter_final'];
                    $waterNewStart = $meter['new_meter_start'];
                } elseif ($meter['meter_type'] == 'ค่าขยะ') {
                    $garbageCost = (float)$meter['reading_value'];
                } elseif ($meter['meter_type'] == 'ค่าส่วนกลาง') {
                    $commonAreaCost = (float)$meter['reading_value'];
                }
                
                if (!empty($meter['remark'])) {
                    $remark = $meter['remark'];
                }
            }
            
            // ดึงข้อมูลเดือนก่อนหน้า
            $prevMonth = $currentMonth - 1;
            $prevYear = $currentYear;
            if ($prevMonth < 1) {
                $prevMonth = 12;
                $prevYear--;
            }
            
            $sqlPrevMeter = "SELECT meter_type, reading_value 
                            FROM me_meter 
                            WHERE pcode = ? AND month = ? AND year = ?";
            $stmtPrevMeter = Database::query($sqlPrevMeter, [$pcode, $prevMonth, $prevYear]);
            $prevMeterData = $stmtPrevMeter->fetchAll(PDO::FETCH_ASSOC);
            
            $prevElectricity = 0;
            $prevWater = 0;
            foreach ($prevMeterData as $prevMeter) {
                if ($prevMeter['meter_type'] == 'ค่าไฟ') {
                    $prevElectricity = (int)$prevMeter['reading_value'];
                } elseif ($prevMeter['meter_type'] == 'ค่าน้ำ') {
                    $prevWater = (int)$prevMeter['reading_value'];
                }
            }
            
            // คำนวณหน่วยและค่าใช้จ่าย
            $electricityUnits = calculateMeterUnits(
                $electricityReading, 
                $prevElectricity, 
                $electricityReset, 
                $electricityChanged, 
                $electricityOldFinal, 
                $electricityNewStart
            );
            $waterUnits = calculateMeterUnits(
                $waterReading, 
                $prevWater, 
                $waterReset, 
                $waterChanged, 
                $waterOldFinal, 
                $waterNewStart
            );
            
            $electricityCost = $electricityUnits * (float)$product['electricity_ppu'];
            $waterCost = $waterUnits * (float)$product['water_ppu'];
            $totalCost = $electricityCost + $waterCost + $garbageCost + $commonAreaCost;
            
            // กำหนดสถานะ
            $status = (count($meterData) > 0) ? 'บันทึกแล้ว' : 'ยังไม่บันทึก';
            
            // ฟังก์ชันทำความสะอาดข้อความ
            $cleanText = function($text) {
                if (empty($text)) return '';
                $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
                $text = trim($text);
                return $text;
            };
            
            // แปลงวันที่เป็นรูปแบบไทย
            $formatThaiDate = function($date) {
                if (empty($date)) return '';
                $timestamp = strtotime($date);
                if ($timestamp === false) return $date;
                return date('d/m/Y', $timestamp);
            };
            
            // เพิ่มข้อมูลลงในแถว
            $sheet1->setCellValue('A' . $rowNum, $orderNum);
            $sheet1->setCellValue('B' . $rowNum, $cleanText($pcode));
            $sheet1->setCellValue('C' . $rowNum, $cleanText($product['pdesc']));
            $sheet1->setCellValue('D' . $rowNum, str_pad($currentMonth, 2, '0', STR_PAD_LEFT));
            $sheet1->setCellValue('E' . $rowNum, $currentYear);
            $sheet1->setCellValue('F' . $rowNum, $formatThaiDate($dateElectricity));
            $sheet1->setCellValue('G' . $rowNum, $prevElectricity);
            $sheet1->setCellValue('H' . $rowNum, $electricityReading);
            $sheet1->setCellValue('I' . $rowNum, $electricityUnits);
            $sheet1->setCellValue('J' . $rowNum, $product['electricity_ppu']);
            $sheet1->setCellValue('K' . $rowNum, $electricityCost);
            $sheet1->setCellValue('L' . $rowNum, $formatThaiDate($dateWater));
            $sheet1->setCellValue('M' . $rowNum, $prevWater);
            $sheet1->setCellValue('N' . $rowNum, $waterReading);
            $sheet1->setCellValue('O' . $rowNum, $waterUnits);
            $sheet1->setCellValue('P' . $rowNum, $product['water_ppu']);
            $sheet1->setCellValue('Q' . $rowNum, $waterCost);
            $sheet1->setCellValue('R' . $rowNum, $garbageCost);
            $sheet1->setCellValue('S' . $rowNum, $commonAreaCost);
            $sheet1->setCellValue('T' . $rowNum, $totalCost);
            $sheet1->setCellValue('U' . $rowNum, $status);
            $sheet1->setCellValue('V' . $rowNum, $cleanText($remark));
            
            $rowNum++;
            $orderNum++;
            
            // เพิ่มเดือน
            $currentMonth++;
            if ($currentMonth > 12) {
                $currentMonth = 1;
                $currentYear++;
            }
        }
    }

    // ====== จัดรูปแบบข้อมูล ======
    $dataStyle = array(
        'font' => array(
            'size' => 11,
            'name' => 'TH Sarabun New'
        ),
        'alignment' => array(
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
        ),
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => 'D0D0D0')
            )
        )
    );

    if ($rowNum > 2) {
        $sheet1->getStyle('A2:V' . ($rowNum - 1))->applyFromArray($dataStyle);
        
        // สลับสีแถว
        for ($i = 2; $i < $rowNum; $i++) {
            if ($i % 2 == 0) {
                $sheet1->getStyle('A' . $i . ':V' . $i)->getFill()
                    ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F2F2F2');
            }
        }
        
        // จัดตำแหน่งตัวเลขให้ชิดขวา
        $sheet1->getStyle('G2:K' . ($rowNum - 1))
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        $sheet1->getStyle('M2:T' . ($rowNum - 1))
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        
        // จัดรูปแบบตัวเลขทศนิยม
        $sheet1->getStyle('J2:K' . ($rowNum - 1))
            ->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet1->getStyle('P2:T' . ($rowNum - 1))
            ->getNumberFormat()->setFormatCode('#,##0.00');
        
        // จัดตำแหน่งกลางสำหรับบางคอลัมน์
        $sheet1->getStyle('A2:A' . ($rowNum - 1))
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('D2:E' . ($rowNum - 1))
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet1->getStyle('U2:U' . ($rowNum - 1))
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    }

    // ====== ตั้งความกว้างคอลัมน์ ======
    $sheet1->getColumnDimension('A')->setWidth(8);   // ลำดับ
    $sheet1->getColumnDimension('B')->setWidth(12);  // รหัสห้อง
    $sheet1->getColumnDimension('C')->setWidth(30);  // รายละเอียด
    $sheet1->getColumnDimension('D')->setWidth(8);   // เดือน
    $sheet1->getColumnDimension('E')->setWidth(8);   // ปี
    $sheet1->getColumnDimension('F')->setWidth(12);  // วันที่อ่านไฟ
    $sheet1->getColumnDimension('G')->setWidth(12);  // มิเตอร์ไฟก่อน
    $sheet1->getColumnDimension('H')->setWidth(12);  // มิเตอร์ไฟปัจจุบัน
    $sheet1->getColumnDimension('I')->setWidth(10);  // หน่วยไฟ
    $sheet1->getColumnDimension('J')->setWidth(12);  // ราคา/หน่วยไฟ
    $sheet1->getColumnDimension('K')->setWidth(12);  // ค่าไฟ
    $sheet1->getColumnDimension('L')->setWidth(12);  // วันที่อ่านน้ำ
    $sheet1->getColumnDimension('M')->setWidth(12);  // มิเตอร์น้ำก่อน
    $sheet1->getColumnDimension('N')->setWidth(12);  // มิเตอร์น้ำปัจจุบัน
    $sheet1->getColumnDimension('O')->setWidth(10);  // หน่วยน้ำ
    $sheet1->getColumnDimension('P')->setWidth(12);  // ราคา/หน่วยน้ำ
    $sheet1->getColumnDimension('Q')->setWidth(12);  // ค่าน้ำ
    $sheet1->getColumnDimension('R')->setWidth(10);  // ค่าขยะ
    $sheet1->getColumnDimension('S')->setWidth(12);  // ค่าส่วนกลาง
    $sheet1->getColumnDimension('T')->setWidth(12);  // รวม
    $sheet1->getColumnDimension('U')->setWidth(12);  // สถานะ
    $sheet1->getColumnDimension('V')->setWidth(30);  // หมายเหตุ

    // ====== ตั้งความสูงแถวหัวตาราง ======
    $sheet1->getRowDimension('1')->setRowHeight(25);

    // ====== ตรึงหัวตาราง (Freeze Pane) ======
    $sheet1->freezePane('A2');
    
    $sheet1->setTitle('รายงานตามช่วงเดือน');

    // ============================================
    // SHEET 2: รายละเอียดมิเตอร์
    // ============================================
    $sheet2 = $objPHPExcel->createSheet();
    $sheet2->setTitle('รายละเอียดมิเตอร์');
    $objPHPExcel->setActiveSheetIndex(1);

    // ====== ดึงรายการเดือนทั้งหมดในช่วงที่เลือก ======
    $availableMonths = array();
    $tempYear = (int)$startYear;
    $tempMonth = (int)$startMonth;
    
    while (true) {
        if ($tempYear > (int)$endYear || ($tempYear == (int)$endYear && $tempMonth > (int)$endMonth)) {
            break;
        }
        $availableMonths[] = array('month' => $tempMonth, 'year' => $tempYear);
        
        $tempMonth++;
        if ($tempMonth > 12) {
            $tempMonth = 1;
            $tempYear++;
        }
    }
    
    // ตรวจสอบว่ามีข้อมูลเดือนหรือไม่
    if (empty($availableMonths)) {
        $availableMonths = array(
            array('month' => (int)$startMonth, 'year' => (int)$startYear)
        );
    }
    
    // ====== ดึงช่วงวันที่อ่านมิเตอร์ของแต่ละเดือน ======
    $dateRangeByMonth = array();
    foreach ($availableMonths as $monthData) {
        $m = $monthData['month'];
        $y = $monthData['year'];
        $key = $m . '_' . $y;
        
        $sqlDateRange = "SELECT 
                            MIN(reading_date) as min_date,
                            MAX(reading_date) as max_date
                         FROM me_meter
                         WHERE month = ? AND year = ? AND reading_date IS NOT NULL";
        $stmtDateRange = Database::query($sqlDateRange, [$m, $y]);
        $dateRange = $stmtDateRange->fetch(PDO::FETCH_ASSOC);
        
        if ($dateRange && $dateRange['min_date']) {
            $dateRangeByMonth[$key] = array(
                'min' => $dateRange['min_date'],
                'max' => $dateRange['max_date']
            );
        }
    }

    // ====== สร้างหัวตาราง Sheet 2 ======
    $sheet2->mergeCells('A1:A3');
    $sheet2->setCellValue('A1', 'ลำดับที่');
    $sheet2->getStyle('A1:A3')
        ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

    $sheet2->mergeCells('B1:C3');
    $sheet2->setCellValue('B1', 'รายการ');
    $sheet2->getStyle('B1:C3')
        ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
    $sheet2->mergeCells('D1:D3');
    $sheet2->setCellValue('D1', 'สถานะมิเตอร์');
    $sheet2->getStyle('D1:D3')
        ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
    
    // ====== สร้างหัวคอลัมน์แบบ dynamic ตามเดือนที่มีข้อมูล ======
    $thaiMonths = array(
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    );
    
    $startColIndex = 4; // เริ่มจากคอลัมน์ E (index 4)
    foreach ($availableMonths as $index => $monthData) {
        $m = $monthData['month'];
        $y = $monthData['year'];
        $thaiYear = $y + 543;
        $monthName = isset($thaiMonths[$m]) ? $thaiMonths[$m] : 'เดือน ' . $m;
        
        // คำนวณคอลัมน์เริ่มต้นและสิ้นสุดสำหรับเดือนนี้ (แต่ละเดือนใช้ 5 คอลัมน์)
        $col1 = $startColIndex + ($index * 5);
        $col5 = $col1 + 4;
        
        // แถวที่ 1: ชื่อเดือน
        $sheet2->mergeCellsByColumnAndRow($col1, 1, $col5, 1);
        $sheet2->setCellValueByColumnAndRow($col1, 1, $monthName . ' ' . $thaiYear);
        
        // แถวที่ 2: หัวคอลัมน์ย่อย
        $sheet2->setCellValueByColumnAndRow($col1, 2, 'มิเตอร์ก่อน');
        $sheet2->setCellValueByColumnAndRow($col1 + 1, 2, 'มิเตอร์ปัจจุบัน');
        $sheet2->setCellValueByColumnAndRow($col1 + 2, 2, 'หน่วย');
        $sheet2->setCellValueByColumnAndRow($col1 + 3, 2, 'ราคา/หน่วย');
        $sheet2->setCellValueByColumnAndRow($col1 + 4, 2, 'รวมยอด');
        
        // แถวที่ 3: วันที่อ่านมิเตอร์ (แสดงช่วงวันที่)
        $sheet2->mergeCellsByColumnAndRow($col1, 3, $col5, 3);
        $key = $m . '_' . $y;
        if (isset($dateRangeByMonth[$key])) {
            $minDate = date('d/m/Y', strtotime($dateRangeByMonth[$key]['min']));
            $maxDate = date('d/m/Y', strtotime($dateRangeByMonth[$key]['max']));
            
            if ($minDate == $maxDate) {
                $dateDisplay = $minDate;
            } else {
                $dateDisplay = $minDate . ' - ' . $maxDate;
            }
            $sheet2->setCellValueByColumnAndRow($col1, 3, $dateDisplay);
        } else {
            $sheet2->setCellValueByColumnAndRow($col1, 3, '');
        }
    }
    
    // คำนวณคอลัมน์สุดท้าย
    $lastColIndex = $startColIndex + (count($availableMonths) * 5) - 1;
    $lastCol = PHPExcel_Cell::stringFromColumnIndex($lastColIndex);

    // ====== จัดรูปแบบหัวตาราง Sheet 2 ======
    $sheet2HeaderStyle = array(
        'font' => array(
            'bold' => true,
            'size' => 11,
            'color' => array('rgb' => 'FFFFFF'),
            'name' => 'TH Sarabun New'
        ),
        'fill' => array(
            'type' => PHPExcel_Style_Fill::FILL_SOLID,
            'color' => array('rgb' => '5B9BD5')
        ),
        'alignment' => array(
            'horizontal' => PHPExcel_Style_Alignment::HORIZONTAL_CENTER,
            'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ),
        'borders' => array(
            'allborders' => array(
                'style' => PHPExcel_Style_Border::BORDER_THIN,
                'color' => array('rgb' => 'FFFFFF')
            )
        )
    );
    
    // ใช้สไตล์สำหรับหัวตาราง 3 แถว
    $sheet2->getStyle('A1:' . $lastCol . '3')->applyFromArray($sheet2HeaderStyle);
    
    // สีสำหรับหัวข้อเดือนต่างๆ (สีเขียว)
    foreach ($availableMonths as $index => $monthData) {
        $col1 = $startColIndex + ($index * 5);
        $col5 = $col1 + 4;
        $rangeCol1 = PHPExcel_Cell::stringFromColumnIndex($col1);
        $rangeCol5 = PHPExcel_Cell::stringFromColumnIndex($col5);
        
        $sheet2->getStyle($rangeCol1 . '1:' . $rangeCol5 . '1')
            ->getFill()->setFillType(PHPExcel_Style_Fill::FILL_SOLID)->getStartColor()->setRGB('00B050');
    }

    // ====== เพิ่มข้อมูลลง Sheet 2 ======
    $sheet2Row = 4;
    $sequence = 1;
    
    // ====== ดึงข้อมูลมิเตอร์ทั้งหมดครั้งเดียว (Optimize Query) ======
    $pcodeList = array_column($products, 'pcode');
    $placeholders = str_repeat('?,', count($pcodeList) - 1) . '?';
    
    $sqlAllMeters = "SELECT meter_type, pcode, month, year, reading_value, reading_date, img, 
                            meter_reset, meter_changed, old_meter_final, new_meter_start, remark 
                     FROM me_meter 
                     WHERE pcode IN ($placeholders)
                     AND ((year > ? OR (year = ? AND month >= ?))
                          AND (year < ? OR (year = ? AND month <= ?)))
                     ORDER BY pcode ASC, year ASC, month ASC";
    
    $params = array_merge(
        $pcodeList,
        [(int)$startYear, (int)$startYear, (int)$startMonth, (int)$endYear, (int)$endYear, (int)$endMonth]
    );
    $stmtAllMeters = Database::query($sqlAllMeters, $params);
    
    // จัดกลุ่มข้อมูลตาม pcode และ month_year
    $allMeterData = array();
    while ($meterRow = $stmtAllMeters->fetch(PDO::FETCH_ASSOC)) {
        $pcode = $meterRow['pcode'];
        $key = $meterRow['month'] . '_' . $meterRow['year'];
        
        if (!isset($allMeterData[$pcode])) {
            $allMeterData[$pcode] = array();
        }
        if (!isset($allMeterData[$pcode][$key])) {
            $allMeterData[$pcode][$key] = array();
        }
        $allMeterData[$pcode][$key][$meterRow['meter_type']] = $meterRow;
    }
    
    foreach ($products as $index => $row) {
        $pcode = isset($row['pcode']) ? $row['pcode'] : '';
        $wh_st_status = isset($row['wh_st_status']) ? $row['wh_st_status'] : '';
        
        // ใช้ข้อมูลที่โหลดไว้แล้ว
        $meterDataByMonth = isset($allMeterData[$pcode]) ? $allMeterData[$pcode] : array();
        
        // ====== แถวที่ 1: หัวข้อมิเตอร์ไฟฟ้า ======
        $sheet2->setCellValue('A' . $sheet2Row, $sequence);
        $sheet2->setCellValue('B' . $sheet2Row, $pcode);
        $sheet2->setCellValue('C' . $sheet2Row, 'มิเตอร์ไฟฟ้า');
        $sheet2->setCellValue('D' . $sheet2Row, $wh_st_status);
        
        // จัดรูปแบบแถวหัวข้อ
        $sheet2->getStyle('A' . $sheet2Row . ':' . $lastCol . $sheet2Row)->getFill()
            ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
            ->getStartColor()->setRGB('E7E6E6');
        $sheet2->getStyle('A' . $sheet2Row . ':D' . $sheet2Row)->getFont()->setBold(true);
        
        $sheet2Row++;
        
        // ====== แถวที่ 2: ข้อมูลมิเตอร์ไฟฟ้า ======
        $electricRowStart = $sheet2Row;
        $sheet2->setCellValue('A' . $sheet2Row, '');
        $sheet2->setCellValue('B' . $sheet2Row, $pcode);
        $sheet2->setCellValue('C' . $sheet2Row, 'เลขมิเตอร์');
        $sheet2->setCellValue('D' . $sheet2Row, '');
        
        // วนลูปข้อมูลทุกเดือนที่มี
        $colOffset = 0;
        $hasElectricImage = false;
        foreach ($availableMonths as $monthData) {
            $m = $monthData['month'];
            $y = $monthData['year'];
            $key = $m . '_' . $y;
            
            // คำนวณคอลัมน์เริ่มต้นสำหรับเดือนนี้ (แต่ละเดือนใช้ 5 คอลัมน์: เลขมิเตอร์ก่อน, เลขมิเตอร์ปัจจุบัน, หน่วย, ราคา, รวม)
            $startCol = 4 + ($colOffset * 5); // เริ่มจาก E (index 4)
            
            if (isset($meterDataByMonth[$key]['ค่าไฟ'])) {
                $electricData = $meterDataByMonth[$key]['ค่าไฟ'];
                $currentReading = (int)$electricData['reading_value'];
                $meter_reset = (int)$electricData['meter_reset'];
                $meter_changed = (int)$electricData['meter_changed'];
                $old_meter_final = isset($electricData['old_meter_final']) ? (int)$electricData['old_meter_final'] : 0;
                $new_meter_start = isset($electricData['new_meter_start']) ? (int)$electricData['new_meter_start'] : 0;
                
                // คำนวณเลขมิเตอร์ก่อนหน้า และหน่วย
                if ($meter_reset == 1) {
                    // กรณีรีเซ็ต: เริ่มนับใหม่จาก 0
                    $prevReading = 0;
                    $units = $currentReading;
                } elseif ($meter_changed == 1) {
                    // กรณีเปลี่ยนมิเตอร์: ใช้เลขมิเตอร์เดิมตัวสุดท้าย + มิเตอร์ใหม่
                    $prevReading = $old_meter_final;
                    $units = ($old_meter_final - 0) + ($currentReading - $new_meter_start);
                } else {
                    // ปกติ: ดึงเลขมิเตอร์เดือนก่อน
                    $prevMonth = $m - 1;
                    $prevYear = $y;
                    if ($prevMonth < 1) {
                        $prevMonth = 12;
                        $prevYear = $y - 1;
                    }
                    $prevKey = $prevMonth . '_' . $prevYear;
                    $prevReading = isset($meterDataByMonth[$prevKey]['ค่าไฟ']) 
                                  ? (int)$meterDataByMonth[$prevKey]['ค่าไฟ']['reading_value'] 
                                  : 0;
                    $units = $currentReading - $prevReading;
                }
                
                $rate = isset($row['electricity_ppu']) ? (float)$row['electricity_ppu'] : 0;
                $total = $units * $rate;
                
                // สร้างข้อความแสดงสถานะพิเศษ
                $statusText = '';
                if ($meter_reset == 1) {
                    $statusText = 'รีเซ็ต';
                } elseif ($meter_changed == 1) {
                    $statusText = 'เปลี่ยนมิเตอร์';
                }
                
                // เขียนข้อมูล
                $sheet2->setCellValueByColumnAndRow($startCol, $sheet2Row, $prevReading . ($statusText ? ' (' . $statusText . ')' : ''));
                $sheet2->setCellValueByColumnAndRow($startCol + 1, $sheet2Row, $currentReading);
                $sheet2->setCellValueByColumnAndRow($startCol + 2, $sheet2Row, $units);
                $sheet2->setCellValueByColumnAndRow($startCol + 3, $sheet2Row, $rate);
                $sheet2->setCellValueByColumnAndRow($startCol + 4, $sheet2Row, $total);
                
                // ตรวจสอบว่ามีรูปภาพหรือไม่
                if (!empty($electricData['img'])) {
                    $hasElectricImage = true;
                }
            }
            
            $colOffset++;
        }
        
        $sheet2Row++;
        
        // ====== ฟังก์ชัน resize รูปภาพก่อนแทรก Excel ======
        if (!function_exists('resizeImageForExcel')) {
        function resizeImageForExcel($imagePath, $maxWidth = 400, $maxHeight = 400, $quality = 85) {
            if (!file_exists($imagePath)) return false;
            $imgInfo = @getimagesize($imagePath);
            if (!$imgInfo) return false;
            $srcType = $imgInfo[2];
            $srcWidth = $imgInfo[0];
            $srcHeight = $imgInfo[1];
            $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1);
            $newWidth = (int)($srcWidth * $ratio);
            $newHeight = (int)($srcHeight * $ratio);
            if ($srcType == IMAGETYPE_JPEG) {
                $srcImg = @imagecreatefromjpeg($imagePath);
            } elseif ($srcType == IMAGETYPE_PNG) {
                $srcImg = @imagecreatefrompng($imagePath);
            } elseif ($srcType == IMAGETYPE_GIF) {
                $srcImg = @imagecreatefromgif($imagePath);
            } else {
                return false;
            }
            if (!$srcImg) return false;
            $dstImg = imagecreatetruecolor($newWidth, $newHeight);
            if ($srcType == IMAGETYPE_PNG || $srcType == IMAGETYPE_GIF) {
                imagealphablending($dstImg, false);
                imagesavealpha($dstImg, true);
                $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
                imagefilledrectangle($dstImg, 0, 0, $newWidth, $newHeight, $transparent);
            }
            imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $newWidth, $newHeight, $srcWidth, $srcHeight);
            $tmpFile = tempnam(sys_get_temp_dir(), 'excelimg_');
            if ($srcType == IMAGETYPE_JPEG) {
                imagejpeg($dstImg, $tmpFile, $quality);
            } elseif ($srcType == IMAGETYPE_PNG) {
                imagepng($dstImg, $tmpFile, 6);
            } elseif ($srcType == IMAGETYPE_GIF) {
                imagegif($dstImg, $tmpFile);
            }
            imagedestroy($srcImg);
            imagedestroy($dstImg);
            return $tmpFile;
        }}

        // ====== แถวที่ 2.5: แสดงรูปภาพมิเตอร์ไฟฟ้า ======
        if ($hasElectricImage) {
            $sheet2->setCellValue('A' . $sheet2Row, '');
            $sheet2->setCellValue('B' . $sheet2Row, $pcode);
            $sheet2->setCellValue('C' . $sheet2Row, 'รูปภาพมิเตอร์');
            $sheet2->setCellValue('D' . $sheet2Row, '');
            
            $colOffset = 0;
            $imageCount = 0;
            $maxImages = 20; // จำกัดไม่เกิน 20 รูปต่อแถว
            
            foreach ($availableMonths as $monthData) {
                $m = $monthData['month'];
                $y = $monthData['year'];
                $key = $m . '_' . $y;
                $startCol = 4 + ($colOffset * 5);
                
                if ($imageCount < $maxImages && isset($meterDataByMonth[$key]['ค่าไฟ']) && !empty($meterDataByMonth[$key]['ค่าไฟ']['img'])) {
                    $electricData = $meterDataByMonth[$key]['ค่าไฟ'];
                    $imgFilename = $electricData['img'];
                    
                    // สร้าง absolute path
                    $projectRoot = realpath(dirname(dirname(dirname(__DIR__))));
                    $cleanPath = ltrim($imgFilename, '/');
                    
                    // ลองหาไฟล์ใน path ที่น่าจะใช้บ่อยสุดก่อน
                    $possiblePaths = array(
                        $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath),
                        $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath)
                    );
                    
                    $imagePath = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path) && @getimagesize($path)) {
                            $imagePath = $path;
                            break;
                        }
                    }
                    if ($imagePath) {
                        try {
                            $resizedPath = resizeImageForExcel($imagePath, 400, 400, 85);
                            if ($resizedPath) {
                                $objDrawing = new PHPExcel_Worksheet_Drawing();
                                $objDrawing->setPath($resizedPath);
                                $colLetter = PHPExcel_Cell::stringFromColumnIndex($startCol);
                                $objDrawing->setCoordinates($colLetter . $sheet2Row);
                                $objDrawing->setOffsetX(5);
                                $objDrawing->setOffsetY(5);
                                $objDrawing->setWidth(120);
                                $objDrawing->setHeight(120);
                                $objDrawing->setWorksheet($sheet2);
                                $imageCount++;
                                register_shutdown_function(function() use ($resizedPath) { @unlink($resizedPath); });
                            } else {
                                $sheet2->setCellValue($colLetter . $sheet2Row, 'รูปใหญ่เกินไป');
                            }
                        } catch (Exception $imgEx) {
                            // เงียบๆ ถ้า error
                        }
                    }
                }
                
                $colOffset++;
            }
            
            $sheet2->getRowDimension($sheet2Row)->setRowHeight(100);
            $sheet2Row++;
        }
        
        
        // ====== แถวที่ 3: หัวข้อมิเตอร์น้ำ ======
        $sheet2->setCellValue('A' . $sheet2Row, '');
        $sheet2->setCellValue('B' . $sheet2Row, $pcode);
        $sheet2->setCellValue('C' . $sheet2Row, 'มิเตอร์น้ำ');
        $sheet2->setCellValue('D' . $sheet2Row, '');
        
        // จัดรูปแบบแถวหัวข้อ
        $sheet2->getStyle('A' . $sheet2Row . ':' . $lastCol . $sheet2Row)->getFill()
            ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F2F2F2');
        $sheet2->getStyle('A' . $sheet2Row . ':D' . $sheet2Row)->getFont()->setBold(true);
        
        $sheet2Row++;
        
        // ====== แถวที่ 4: ข้อมูลมิเตอร์น้ำ ======
        $waterRowStart = $sheet2Row;
        $sheet2->setCellValue('A' . $sheet2Row, '');
        $sheet2->setCellValue('B' . $sheet2Row, $pcode);
        $sheet2->setCellValue('C' . $sheet2Row, 'เลขมิเตอร์');
        $sheet2->setCellValue('D' . $sheet2Row, '');
        
        // วนลูปข้อมูลทุกเดือนที่มี
        $colOffset = 0;
        $hasWaterImage = false;
        foreach ($availableMonths as $monthData) {
            $m = $monthData['month'];
            $y = $monthData['year'];
            $key = $m . '_' . $y;
            
            $startCol = 4 + ($colOffset * 5);
            
            if (isset($meterDataByMonth[$key]['ค่าน้ำ'])) {
                $waterData = $meterDataByMonth[$key]['ค่าน้ำ'];
                $currentReading = (int)$waterData['reading_value'];
                $meter_reset = (int)$waterData['meter_reset'];
                $meter_changed = (int)$waterData['meter_changed'];
                $old_meter_final = isset($waterData['old_meter_final']) ? (int)$waterData['old_meter_final'] : 0;
                $new_meter_start = isset($waterData['new_meter_start']) ? (int)$waterData['new_meter_start'] : 0;
                
                // คำนวณเลขมิเตอร์ก่อนหน้า และหน่วย
                if ($meter_reset == 1) {
                    $prevReading = 0;
                    $units = $currentReading;
                } elseif ($meter_changed == 1) {
                    $prevReading = $old_meter_final;
                    $units = ($old_meter_final - 0) + ($currentReading - $new_meter_start);
                } else {
                    $prevMonth = $m - 1;
                    $prevYear = $y;
                    if ($prevMonth < 1) {
                        $prevMonth = 12;
                        $prevYear = $y - 1;
                    }
                    $prevKey = $prevMonth . '_' . $prevYear;
                    $prevReading = isset($meterDataByMonth[$prevKey]['ค่าน้ำ']) 
                                  ? (int)$meterDataByMonth[$prevKey]['ค่าน้ำ']['reading_value'] 
                                  : 0;
                    $units = $currentReading - $prevReading;
                }
                
                $rate = isset($row['water_ppu']) ? (float)$row['water_ppu'] : 0;
                $total = $units * $rate;
                
                // สร้างข้อความแสดงสถานะพิเศษ
                $statusText = '';
                if ($meter_reset == 1) {
                    $statusText = 'รีเซ็ต';
                } elseif ($meter_changed == 1) {
                    $statusText = 'เปลี่ยนมิเตอร์';
                }
                
                $sheet2->setCellValueByColumnAndRow($startCol, $sheet2Row, $prevReading . ($statusText ? ' (' . $statusText . ')' : ''));
                $sheet2->setCellValueByColumnAndRow($startCol + 1, $sheet2Row, $currentReading);
                $sheet2->setCellValueByColumnAndRow($startCol + 2, $sheet2Row, $units);
                $sheet2->setCellValueByColumnAndRow($startCol + 3, $sheet2Row, $rate);
                $sheet2->setCellValueByColumnAndRow($startCol + 4, $sheet2Row, $total);
                
                // ตรวจสอบว่ามีรูปภาพหรือไม่
                if (!empty($waterData['img'])) {
                    $hasWaterImage = true;
                }
            }
            
            $colOffset++;
        }
        
        $sheet2Row++;
        
        // ====== แถวที่ 4.5: แสดงรูปภาพมิเตอร์น้ำ ======
        if ($hasWaterImage) {
            $sheet2->setCellValue('A' . $sheet2Row, '');
            $sheet2->setCellValue('B' . $sheet2Row, $pcode);
            $sheet2->setCellValue('C' . $sheet2Row, 'รูปภาพมิเตอร์');
            $sheet2->setCellValue('D' . $sheet2Row, '');
            
            $colOffset = 0;
            $imageCount = 0;
            $maxImages = 20; // จำกัดไม่เกิน 20 รูปต่อแถว
            
            foreach ($availableMonths as $monthData) {
                $m = $monthData['month'];
                $y = $monthData['year'];
                $key = $m . '_' . $y;
                $startCol = 4 + ($colOffset * 5);
                
                if ($imageCount < $maxImages && isset($meterDataByMonth[$key]['ค่าน้ำ']) && !empty($meterDataByMonth[$key]['ค่าน้ำ']['img'])) {
                    $waterData = $meterDataByMonth[$key]['ค่าน้ำ'];
                    $imgFilename = $waterData['img'];
                    
                    // สร้าง absolute path
                    $projectRoot = realpath(dirname(dirname(dirname(__DIR__))));
                    $cleanPath = ltrim($imgFilename, '/');
                    
                    // ลองหาไฟล์ใน path ที่น่าจะใช้บ่อยสุดก่อน
                    $possiblePaths = array(
                        $projectRoot . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath),
                        $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $cleanPath)
                    );
                    
                    $imagePath = null;
                    foreach ($possiblePaths as $path) {
                        if (file_exists($path) && @getimagesize($path)) {
                            $imagePath = $path;
                            break;
                        }
                    }
                    if ($imagePath) {
                        try {
                            $resizedPath = resizeImageForExcel($imagePath, 400, 400, 85);
                            if ($resizedPath) {
                                $objDrawing = new PHPExcel_Worksheet_Drawing();
                                $objDrawing->setPath($resizedPath);
                                $colLetter = PHPExcel_Cell::stringFromColumnIndex($startCol);
                                $objDrawing->setCoordinates($colLetter . $sheet2Row);
                                $objDrawing->setOffsetX(5);
                                $objDrawing->setOffsetY(5);
                                $objDrawing->setWidth(120);
                                $objDrawing->setHeight(120);
                                $objDrawing->setWorksheet($sheet2);
                                $imageCount++;
                                register_shutdown_function(function() use ($resizedPath) { @unlink($resizedPath); });
                            } else {
                                $sheet2->setCellValue($colLetter . $sheet2Row, 'รูปใหญ่เกินไป');
                            }
                        } catch (Exception $imgEx) {
                            // เงียบๆ ถ้า error
                        }
                    }
                }
                
                $colOffset++;
            }
            
            $sheet2->getRowDimension($sheet2Row)->setRowHeight(100);
            $sheet2Row++;
        }
        
        $sequence++;
        
        // เพิ่มช่องว่างระหว่างกลุ่มข้อมูล (เว้น 1 แถว)
        $sheet2Row++;
    }

    // ====== จัดรูปแบบข้อมูล Sheet 2 ======
    $lastSheet2Row = $sheet2Row - 2;
    if ($lastSheet2Row >= 4) {
        // สไตล์พื้นฐานสำหรับข้อมูล
        $sheet2DataStyle = array(
            'font' => array(
                'size' => 11,
                'name' => 'TH Sarabun New'
            ),
            'alignment' => array(
                'vertical' => PHPExcel_Style_Alignment::VERTICAL_CENTER
            ),
            'borders' => array(
                'allborders' => array(
                    'style' => PHPExcel_Style_Border::BORDER_THIN,
                    'color' => array('rgb' => 'D0D0D0')
                )
            )
        );
        
        $sheet2->getStyle('A4:' . $lastCol . $lastSheet2Row)->applyFromArray($sheet2DataStyle);
        
        // จัดตำแหน่งข้อความ
        $sheet2->getStyle('A4:A' . $lastSheet2Row)
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('B4:B' . $lastSheet2Row)
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);
        $sheet2->getStyle('C4:D' . $lastSheet2Row)
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
        
        // จัดตำแหน่งตัวเลขให้ชิดขวา (คอลัมน์ E ถึงคอลัมน์สุดท้าย)
        $firstDataCol = PHPExcel_Cell::stringFromColumnIndex(4); // E
        $sheet2->getStyle($firstDataCol . '4:' . $lastCol . $lastSheet2Row)
            ->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_RIGHT);
        
        // จัดรูปแบบตัวเลข (คอลัมน์ราคาและรวมยอด)
        foreach ($availableMonths as $index => $monthData) {
            $col1 = $startColIndex + ($index * 5);
            // คอลัมน์ราคา/หน่วย (index +3)
            $priceCol = PHPExcel_Cell::stringFromColumnIndex($col1 + 3);
            $sheet2->getStyle($priceCol . '4:' . $priceCol . $lastSheet2Row)
                ->getNumberFormat()->setFormatCode('#,##0.00');
            // คอลัมน์รวมยอด (index +4)
            $totalCol = PHPExcel_Cell::stringFromColumnIndex($col1 + 4);
            $sheet2->getStyle($totalCol . '4:' . $totalCol . $lastSheet2Row)
                ->getNumberFormat()->setFormatCode('#,##0.00');
        }
        
        // สลับสีแถวข้อมูล
        $isEven = false;
        for ($i = 4; $i <= $lastSheet2Row; $i++) {
            $cellValue = $sheet2->getCell('A' . $i)->getValue();
            
            // ข้ามแถวหัวข้อ
            if (!empty($cellValue)) {
                $isEven = !$isEven;
                continue;
            }
            
            // แถวข้อมูลปกติ
            $color = $isEven ? 'FFFFFF' : 'F8F9FA';
            $sheet2->getStyle('A' . $i . ':' . $lastCol . $i)->getFill()
                ->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color);
            
            $isEven = !$isEven;
        }
    }
    
    // ====== ตั้งความกว้างคอลัมน์ Sheet 2 (แบบ dynamic) ======
    $sheet2->getColumnDimension('A')->setWidth(8);   // ลำดับที่
    $sheet2->getColumnDimension('B')->setWidth(12);  // รายการ
    $sheet2->getColumnDimension('C')->setWidth(15);  // ประเภทมิเตอร์
    $sheet2->getColumnDimension('D')->setWidth(15);  // สถานะมิเตอร์
    
    // ตั้งความกว้างสำหรับคอลัมน์ข้อมูลแต่ละเดือน
    for ($i = 0; $i < count($availableMonths) * 5; $i++) {
        $colIndex = 4 + $i; // เริ่มจาก E (index 4)
        $colLetter = PHPExcel_Cell::stringFromColumnIndex($colIndex);
        $sheet2->getColumnDimension($colLetter)->setWidth(14);
    }
    
    // ====== ตั้งความสูงแถว Sheet 2 ======
    $sheet2->getRowDimension('1')->setRowHeight(30);
    $sheet2->getRowDimension('2')->setRowHeight(25);
    $sheet2->getRowDimension('3')->setRowHeight(25);
    
    // ====== ตรึงหัวตาราง Sheet 2 ======
    $sheet2->freezePane('A4');
    
    // ====== ตั้งค่าหน้ากระดาษ ======
    $sheet2->getPageSetup()->setOrientation(PHPExcel_Worksheet_PageSetup::ORIENTATION_LANDSCAPE);
    $sheet2->getPageSetup()->setPaperSize(PHPExcel_Worksheet_PageSetup::PAPERSIZE_A4);
    $sheet2->getPageSetup()->setFitToWidth(1);
    $sheet2->getPageSetup()->setFitToHeight(0);

    // ====== กลับไปที่ Sheet 1 เป็น active ======
    $objPHPExcel->setActiveSheetIndex(0);

    // ====== ตั้งค่า Properties ของไฟล์ ======
    $objPHPExcel->getProperties()
        ->setCreator("ระบบจัดการมิเตอร์")
        ->setTitle("รายงานข้อมูลมิเตอร์")
        ->setSubject("รายงานข้อมูลมิเตอร์")
        ->setDescription("รายงานข้อมูลมิเตอร์จากระบบ");

    // ====== ล้าง output buffer ก่อนส่งไฟล์ ======
    if (ob_get_level()) {
        ob_end_clean();
    }

    // ====== ตั้งค่า header สำหรับดาวน์โหลด ======
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $excelFileName . '"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: max-age=1');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: cache, must-revalidate');
    header('Pragma: public');

    // ====== สร้างไฟล์และส่งออก ======
    $objPHPExcel->setActiveSheetIndex(0);
    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
    $objWriter->save('php://output');
    
} catch (Exception $e) {
    // ====== จัดการ Error ======
    // ล้าง output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // แสดงข้อความ Error
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>เกิดข้อผิดพลาด</title>
        <style>
            body { font-family: "TH Sarabun New", Arial, sans-serif; padding: 50px; text-align: center; }
            .error-box { 
                max-width: 500px; 
                margin: 0 auto; 
                padding: 30px; 
                border: 2px solid #dc3545; 
                border-radius: 10px; 
                background: #fff5f5; 
            }
            h1 { color: #dc3545; }
            .btn { 
                display: inline-block; 
                margin-top: 20px; 
                padding: 10px 30px; 
                background: #4472C4; 
                color: white; 
                text-decoration: none; 
                border-radius: 5px; 
            }
            .btn:hover { background: #365a9b; }
        </style>
    </head>
    <body>
        <div class="error-box">
            <h1>⚠️ เกิดข้อผิดพลาด</h1>
            <p>ไม่สามารถสร้างไฟล์ Excel ได้</p>
            <p style="color: #666;">' . htmlspecialchars($e->getMessage()) . '</p>
            <a href="javascript:history.back()" class="btn">← ย้อนกลับ</a>
        </div>
    </body>
    </html>';
}
exit;
?>
