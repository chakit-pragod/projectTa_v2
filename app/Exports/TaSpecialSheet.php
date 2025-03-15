<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Models\CompensationRate;
use Illuminate\Support\Facades\Log;

class TaSpecialSheet implements FromCollection, WithTitle, ShouldAutoSize, WithStyles, WithColumnWidths, WithEvents
{
    protected $attendances;
    protected $compensation;
    protected $student;
    protected $semester;
    protected $selectedYearMonth;
    protected $teacherName;

    public function __construct($attendances, $compensation, $student, $semester, $selectedYearMonth, $teacherName = '')
    {
        $this->attendances = $attendances;
        $this->compensation = $compensation;
        $this->student = $student;
        $this->semester = $semester;
        $this->selectedYearMonth = $selectedYearMonth;
        $this->teacherName = $teacherName;
    }

    public function collection()
    {
        // สร้าง Collection เปล่าสำหรับส่งออก
        $data = new Collection();

        // เพิ่มข้อมูลหัวเรื่อง (จะถูกจัดรูปแบบในส่วนของ styles)
        for ($i = 1; $i <= 6; $i++) {
            $data->push(['']);
        }

        // เพิ่มหัวข้อตาราง
        $data->push([
            'ลำดับที่',
            'ชื่อ-สกุล',
            'ระดับ',
            'ว/ด/ป',
            'รหัสวิชา',
            'เวลาสอน',
            'บรรยาย',
            'ปฏิบัติการ',
            'หมายเหตุ'
        ]);

        // แปลงข้อมูลการสอนให้เป็นแถวสำหรับตาราง
        $rowNumber = 1;
        $totalLectureHours = 0;
        $totalLabHours = 0;

        $flatAttendances = collect();
        foreach ($this->attendances as $section => $attendanceGroup) {
            foreach ($attendanceGroup as $attendance) {
                $flatAttendances->push($attendance);
            }
        }

        // เรียงลำดับข้อมูลตามวันที่
        $flatAttendances = $flatAttendances->sortBy(function ($attendance) {
            return $attendance['type'] === 'regular'
                ? $attendance['data']->start_time
                : $attendance['data']->start_work;
        });
$isGraduate = in_array($this->student->degree_level, ['master', 'doctoral', 'graduate']);
    $isFixedPayment = $isGraduate && ($this->compensation['specialLectureHours'] > 0 || $this->compensation['specialLabHours'] > 0);

    if ($isFixedPayment) {
        $fixedAmount = $this->getFixedCompensationRate('special', $this->student->degree_level);
        $this->compensation['specialPay'] = $fixedAmount ?? 4000; // ค่าเริ่มต้น 4,000 บาท
    }
        foreach ($flatAttendances as $attendance) {
            $date = $attendance['type'] === 'regular'
                ? Carbon::parse($attendance['data']->start_time)->format('d-m-y')
                : Carbon::parse($attendance['data']->start_work)->format('d-m-y');

            $time = $attendance['type'] === 'regular'
                ? Carbon::parse($attendance['data']->start_time)->format('H:i') . '-' . Carbon::parse($attendance['data']->end_time)->format('H:i')
                : Carbon::parse($attendance['data']->start_work)->format('H:i');

            $courseId = $attendance['type'] === 'regular'
                ? $attendance['data']->class->course->subjects->subject_id ?? '-'
                : $attendance['data']->classes->course->subjects->subject_id ?? '-';

            $lectureHours = 0;
            $labHours = 0;

            if ($attendance['class_type'] === 'LECTURE') {
                $lectureHours = $attendance['hours'];
                $totalLectureHours += $lectureHours;
            } else {
                $labHours = $attendance['hours'];
                $totalLabHours += $labHours;
            }

            $note = $attendance['type'] === 'regular'
                ? ($attendance['data']->attendance->note ?? 'ตรวจงาน / เช็คชื่อ')
                : ($attendance['data']->detail ?? 'ตรวจงาน / เช็คชื่อ');

            $data->push([
                $rowNumber,
                $rowNumber === 1 ? $this->student->name : '',
                $rowNumber === 1 ? 'ป.ตรี' : '',
                $date,
                $courseId,
                $time,
                $lectureHours > 0 ? number_format($lectureHours, 2) : '',
                $labHours > 0 ? number_format($labHours, 2) : '',
                $note
            ]);

            $rowNumber++;
        }

        // เพิ่มแถวว่างให้ตารางมีขนาดตามที่กำหนด (ประมาณ 20 แถว)
        $emptyRowsNeeded = 20 - $flatAttendances->count();
        for ($i = 0; $i < $emptyRowsNeeded; $i++) {
            $data->push([
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                ''
            ]);
        }

        // เพิ่มแถวสรุป
        $data->push(['', 'รวมเวลา', 'ปริญญาตรี', '', '', '', number_format($totalLectureHours, 2), number_format($totalLabHours, 2), '']);
        $data->push(['', 'ที่สอน', 'ปริญญาโท/เอก', '', '', '', '', '', '']);

        // เพิ่มข้อมูลการคำนวณค่าตอบแทน
        $data->push(['']);
        $data->push(['จำนวนเงินที่ขอเบิก']);

        // ภาคปกติ (แถวว่าง)
        $data->push([
            '- ปริญญาตรี (ภาคปกติ)',
            '',
            'ชั่วโมง',
            'อัตราชั่วโมงละ',
            '',
            'บาท',
            'เป็นเงิน',
            '',
            ''
        ]);

        $specialTotalHours = $this->compensation['specialLectureHours'] + $this->compensation['specialLabHours'];

        // โครงการพิเศษ
        $data->push([
            '- ปริญญาตรี (โครงการพิเศษ)',
            number_format($specialTotalHours, 1),
            'ชั่วโมง',
            'อัตราชั่วโมงละ',
            number_format($this->compensation['rates']['specialLecture'], 2),
            'บาท',
            'เป็นเงิน',
            number_format($this->compensation['specialPay'], 2),
            ''
        ]);

        // ปริญญาโท/เอก (แถวว่าง)
        $data->push([
            '- ปริญญาโท/เอก (เหมาจ่าย)',
            '',
            '',
            '',
            '',
            '',
            'เป็นเงิน',
            '',
            ''
        ]);

        // สรุปรวม
        $data->push(['']);
        $data->push(['รวมเป็นเงินทั้งสิ้น', number_format($this->compensation['specialPay'], 2), 'บาท', '', '= ' . $this->convertNumberToThaiBaht($this->compensation['specialPay']) . ' =', '', '', '', '']);

        // หมายเหตุ
        $data->push(['หมายเหตุ :', 'ขอเบิกจ่ายเพียง', '', 'บาท', '', '', '', '', '']);

        // ส่วนลงนาม
        $data->push(['']);
        $data->push(['ผู้ปฏิบัติงาน', '', 'อาจารย์ผู้สอน', '', 'ผู้รับรอง', '', '', '', '']);

        // เว้นช่องว่างให้เซ็น
        $data->push(['', '', '', '', '', '', '', '', '']);
        $data->push(['', '', '', '', '', '', '', '', '']);

        // ลายเซ็น
        $data->push(['ลงชื่อ.................................', '', 'ลงชื่อ.................................', '', 'ลงชื่อ.................................', '', '', '', '']);
        $data->push(['(' . $this->student->name . ')', '', '(' . $this->teacherName . ')', '', '(ผศ. ดร.คำรณ สุนัติ)', '', '', '', '']);
        $data->push(['วันที่.....เดือน...............พ.ศ........', '', 'วันที่.....เดือน...............พ.ศ........', '', 'ตำแหน่ง หัวหน้าสาขาวิชาวิทยาการคอมพิวเตอร์', '', '', '', '']);
        $data->push(['', '', '', '', 'วันที่.....เดือน...............พ.ศ........', '', '', '', '']);

        return $data;
    }

    private function getFixedCompensationRate($teachingType, $degreeLevel)
{
    try {
        $fixedRate = CompensationRate::where('teaching_type', $teachingType)
            ->where('degree_level', $degreeLevel)
            ->where('is_fixed_payment', true)
            ->where('status', 'active')
            ->first();

        return $fixedRate ? $fixedRate->fixed_amount : null;
    } catch (\Exception $e) {
        Log::error('Error getting fixed compensation rate: ' . $e->getMessage());
        return null;
    }
}

    public function convertNumberToThaiBaht($number)
    {
        // ในการใช้งานจริง ควรใช้ฟังก์ชันแปลงตัวเลขเป็นคำอ่านเงินบาทที่สมบูรณ์
        if (method_exists('\\App\\Helpers\\ThaiNumberHelper', 'convertToText')) {
            return \App\Helpers\ThaiNumberHelper::convertToText($number);
        }

        // ตัวอย่างเงื่อนไขแบบง่าย
        if ($number == 900) {
            return "เก้าร้อยบาทถ้วน";
        } else if ($number == 1440) {
            return "หนึ่งพันสี่ร้อยสี่สิบบาทถ้วน";
        }

        return "จำนวนเงินเป็นตัวอักษร";
    }

    public function title(): string
    {
        return "ภาคพิเศษ";
    }

    public function columnWidths(): array
    {
        return [
            'A' => 10,  // ลำดับที่
            'B' => 25,  // ชื่อ-สกุล
            'C' => 10,  // ระดับ
            'D' => 12,  // ว/ด/ป
            'E' => 12,  // รหัสวิชา
            'F' => 15,  // เวลาสอน
            'G' => 10,  // บรรยาย
            'H' => 10,  // ปฏิบัติการ
            'I' => 30,  // หมายเหตุ
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // จัดการหลังจากสร้างชีทเสร็จสิ้น
                $sheet = $event->sheet->getDelegate();

                // ตั้งค่าการแสดงเส้นกริด
                $sheet->setShowGridlines(false);
            },
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // กำหนดสไตล์ของหัวเรื่อง (แบบใบเบิกค่าตอบแทน)
        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'แบบใบเบิกค่าตอบแทนผู้ช่วยสอนและผู้ช่วยปฏิบัติงาน');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ชื่อสถาบัน
        $sheet->mergeCells('A2:I2');
        $sheet->setCellValue('A2', 'วิทยาลัยการคอมพิวเตอร์ มหาวิทยาลัยขอนแก่น');
        $sheet->getStyle('A2')->getFont()->setBold(true);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ภาคการศึกษา
        $semesterText = '';
        $yearText = '';

        if ($this->semester) {
            $semesterValue = $this->semester->semesters ?? '';
            if ($semesterValue == '1') {
                $semesterText = "ภาคการศึกษา ( / ) ต้น     (   ) ปลาย     (   ) ฤดูร้อน";
            } elseif ($semesterValue == '2') {
                $semesterText = "ภาคการศึกษา (   ) ต้น     ( / ) ปลาย     (   ) ฤดูร้อน";
            } else {
                $semesterText = "ภาคการศึกษา (   ) ต้น     (   ) ปลาย     ( / ) ฤดูร้อน";
            }

            $yearText = "ปีการศึกษา " . ($this->semester->year + 543);
        } else {
            $semesterText = "ภาคการศึกษา (   ) ต้น     (   ) ปลาย     (   ) ฤดูร้อน";
            $yearText = "ปีการศึกษา _______";
        }

        $sheet->mergeCells('A3:I3');
        $sheet->setCellValue('A3', $semesterText . '     ' . $yearText);
        $sheet->getStyle('A3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ประจำเดือน
        $date = Carbon::createFromFormat('Y-m', $this->selectedYearMonth);
        $monthName = $date->locale('th')->monthName;
        $year = $date->year + 543;
        $endMonthName = "สิงหาคม";  // กำหนดค่าตายตัวตามรูปภาพ

        $sheet->mergeCells('A4:I4');
        $sheet->setCellValue('A4', 'ประจำเดือน ' . $monthName . ' ' . $year . ' - ' . $endMonthName . ' ' . $year);
        $sheet->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // รายวิชาระดับ
        $sheet->mergeCells('A5:B5');
        $sheet->setCellValue('A5', 'รายวิชาระดับ');
        $sheet->getStyle('A5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $sheet->mergeCells('C5:E5');
        $sheet->setCellValue('C5', '( / ) ปริญญาตรี');

        $sheet->mergeCells('F5:I5');
        $sheet->setCellValue('F5', '(   ) บัณฑิตศึกษา');

        // ประเภทโครงการ
        $sheet->mergeCells('C6:E6');
        $sheet->setCellValue('C6', '(   ) ภาคปกติ');

        $sheet->mergeCells('F6:I6');
        $sheet->setCellValue('F6', '( / ) โครงการพิเศษ');

        // กำหนดสไตล์สำหรับหัวตาราง
        $headerRow = 7;
        $headerEndRow = 8;

        // ตีกรอบทุกเซลล์ในหัวตาราง
        $sheet->getStyle('A' . $headerRow . ':I' . $headerEndRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // จัดแนวกึ่งกลางทั้งแนวตั้งและแนวนอนสำหรับหัวตาราง
        $sheet->getStyle('A' . $headerRow . ':I' . $headerEndRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        // หัวตารางบนเส้นแรก    
        $sheet->mergeCells('A' . $headerRow . ':A' . $headerEndRow);
        $sheet->setCellValue('A' . $headerRow, 'ลำดับที่');

        $sheet->mergeCells('B' . $headerRow . ':B' . $headerEndRow);
        $sheet->setCellValue('B' . $headerRow, 'ชื่อ-สกุล');

        $sheet->mergeCells('C' . $headerRow . ':C' . $headerEndRow);
        $sheet->setCellValue('C' . $headerRow, 'ระดับ');

        $sheet->mergeCells('D' . $headerRow . ':F' . $headerRow);
        $sheet->setCellValue('D' . $headerRow, 'ระยะเวลาที่สอน');

        $sheet->mergeCells('G' . $headerRow . ':H' . $headerRow);
        $sheet->setCellValue('G' . $headerRow, 'จำนวนชั่วโมงที่สอน');

        $sheet->mergeCells('I' . $headerRow . ':I' . $headerEndRow);
        $sheet->setCellValue('I' . $headerRow, 'หมายเหตุ');

        // หัวตารางเส้นที่สอง
        $sheet->setCellValue('D' . $headerEndRow, 'ว/ด/ป');
        $sheet->setCellValue('E' . $headerEndRow, 'รหัสวิชา');
        $sheet->setCellValue('F' . $headerEndRow, 'เวลาสอน');
        $sheet->setCellValue('G' . $headerEndRow, 'บรรยาย');
        $sheet->setCellValue('H' . $headerEndRow, 'ปฏิบัติการ');

        // ข้อมูลในตาราง - กำหนดเส้นประในแนวนอน
        $dataStartRow = $headerEndRow + 1;
        $dataEndRow = 30; // กำหนดคร่าวๆ ให้มีพื้นที่พอ

        // เส้นขอบข้างนอก
        $sheet->getStyle('A' . $dataStartRow . ':I' . $dataEndRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // เส้นแนวตั้ง
        $sheet->getStyle('A' . $dataStartRow . ':I' . $dataEndRow)->getBorders()->getVertical()
            ->setBorderStyle(Border::BORDER_THIN);

        // เส้นประแนวนอน
        for ($i = $dataStartRow; $i <= $dataEndRow; $i++) {
            $sheet->getStyle('A' . $i . ':I' . $i)->getBorders()->getBottom()
                ->setBorderStyle(Border::BORDER_DOTTED);
        }

        // แถวสรุปรวมเวลา
        $totalRow = 31;
        $sheet->mergeCells('B' . $totalRow . ':C' . $totalRow);
        $sheet->setCellValue('B' . $totalRow, 'รวมเวลาที่สอน');
        $sheet->setCellValue('C' . $totalRow, 'ปริญญาตรี');
        $sheet->getStyle('B' . $totalRow . ':C' . $totalRow)->getFont()->setBold(true);

        // ขีดเส้นสำหรับส่วนสรุปรวมเวลา
        $sheet->getStyle('A' . $totalRow . ':I' . ($totalRow + 1))->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);

        // แถวรวมเวลาปริญญาโท/เอก
        $sheet->setCellValue('B' . ($totalRow + 1), 'ที่สอน');
        $sheet->setCellValue('C' . ($totalRow + 1), 'ปริญญาโท/เอก');

        // ส่วนจำนวนเงินที่ขอเบิก
        $paymentRow = 33;
        $sheet->setCellValue('A' . $paymentRow, 'จำนวนเงินที่ขอเบิก');
        $sheet->getStyle('A' . $paymentRow)->getFont()->setBold(true);

        // ค่าตอบแทนปริญญาตรี (ภาคปกติ)
        $regularRow = $paymentRow + 1;
        $sheet->setCellValue('A' . $regularRow, '- ปริญญาตรี (ภาคปกติ)');

        // ขีดเส้นตาราง
        $sheet->getStyle('A' . $regularRow . ':I' . $regularRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // ค่าตอบแทนโครงการพิเศษ
        $specialRow = $regularRow + 1;
        $sheet->setCellValue('A' . $specialRow, '- ปริญญาตรี (โครงการพิเศษ)');

        // ขีดเส้นตาราง
        $sheet->getStyle('A' . $specialRow . ':I' . $specialRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // ค่าตอบแทนปริญญาโท/เอก
        $masterRow = $specialRow + 1;
        $sheet->setCellValue('A' . $masterRow, '- ปริญญาโท/เอก (เหมาจ่าย)');

        // ขีดเส้นตาราง
        $sheet->getStyle('A' . $masterRow . ':I' . $masterRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // แถวรวมเงินทั้งสิ้น
        $totalAmountRow = $masterRow + 2;
        $sheet->setCellValue('A' . $totalAmountRow, 'รวมเป็นเงินทั้งสิ้น');
        $sheet->getStyle('A' . $totalAmountRow . ':I' . $totalAmountRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A' . $totalAmountRow)->getFont()->setBold(true);

        // หมายเหตุ
        $noteRow = $totalAmountRow + 1;
        $sheet->setCellValue('A' . $noteRow, 'หมายเหตุ :');
        $sheet->setCellValue('B' . $noteRow, 'ขอเบิกจ่ายเพียง');
        $sheet->getStyle('A' . $noteRow . ':E' . $noteRow)->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // ส่วนลงนาม
        $signatureRow = $noteRow + 2;
        $sheet->mergeCells('A' . $signatureRow . ':C' . $signatureRow);
        $sheet->setCellValue('A' . $signatureRow, 'ผู้ปฏิบัติงาน');

        $sheet->mergeCells('D' . $signatureRow . ':F' . $signatureRow);
        $sheet->setCellValue('D' . $signatureRow, 'อาจารย์ผู้สอน');

        $sheet->mergeCells('G' . $signatureRow . ':I' . $signatureRow);
        $sheet->setCellValue('G' . $signatureRow, 'ผู้รับรอง');

        $sheet->getStyle('A' . $signatureRow . ':I' . $signatureRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ขีดเส้นส่วนลงนาม
        $sheet->getStyle('A' . $signatureRow . ':C' . ($signatureRow + 6))->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('D' . $signatureRow . ':F' . ($signatureRow + 6))->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('G' . $signatureRow . ':I' . ($signatureRow + 6))->getBorders()->getOutline()
            ->setBorderStyle(Border::BORDER_THIN);

        // ลายเซ็น
        $sheet->mergeCells('A' . ($signatureRow + 3) . ':C' . ($signatureRow + 3));
        $sheet->setCellValue('A' . ($signatureRow + 3), 'ลงชื่อ......................................');

        $sheet->mergeCells('D' . ($signatureRow + 3) . ':F' . ($signatureRow + 3));
        $sheet->setCellValue('D' . ($signatureRow + 3), 'ลงชื่อ......................................');

        $sheet->mergeCells('G' . ($signatureRow + 3) . ':I' . ($signatureRow + 3));
        $sheet->setCellValue('G' . ($signatureRow + 3), 'ลงชื่อ......................................');

        $sheet->getStyle('A' . ($signatureRow + 3) . ':I' . ($signatureRow + 3))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ชื่อผู้ลงนาม
        $sheet->mergeCells('A' . ($signatureRow + 4) . ':C' . ($signatureRow + 4));
        $sheet->setCellValue('A' . ($signatureRow + 4), '(' . $this->student->name . ')');

        $sheet->mergeCells('D' . ($signatureRow + 4) . ':F' . ($signatureRow + 4));
        $sheet->setCellValue('D' . ($signatureRow + 4), '(' . $this->teacherName . ')');

        $sheet->mergeCells('G' . ($signatureRow + 4) . ':I' . ($signatureRow + 4));
        $sheet->setCellValue('G' . ($signatureRow + 4), '(ผศ. ดร.คำรณ สุนัติ)');

        $sheet->getStyle('A' . ($signatureRow + 4) . ':I' . ($signatureRow + 4))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // วันที่ลงนาม
        $sheet->mergeCells('A' . ($signatureRow + 5) . ':C' . ($signatureRow + 5));
        $sheet->setCellValue('A' . ($signatureRow + 5), 'วันที่.....เดือน...............พ.ศ........');

        $sheet->mergeCells('D' . ($signatureRow + 5) . ':F' . ($signatureRow + 5));
        $sheet->setCellValue('D' . ($signatureRow + 5), 'วันที่.....เดือน...............พ.ศ........');

        $sheet->mergeCells('G' . ($signatureRow + 5) . ':I' . ($signatureRow + 5));
        $sheet->setCellValue('G' . ($signatureRow + 5), 'ตำแหน่ง หัวหน้าสาขาวิชาวิทยาการคอมพิวเตอร์');

        $sheet->getStyle('A' . ($signatureRow + 5) . ':I' . ($signatureRow + 5))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->mergeCells('G' . ($signatureRow + 6) . ':I' . ($signatureRow + 6));
        $sheet->setCellValue('G' . ($signatureRow + 6), 'วันที่.....เดือน...............พ.ศ........');
        $sheet->getStyle('G' . ($signatureRow + 6))->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // ปรับแนวข้อความในคอลัมน์
        $sheet->getStyle('A9:A' . $dataEndRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('C9:C' . $dataEndRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('D9:F' . $dataEndRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_CENTER)
            ->setVertical(Alignment::VERTICAL_CENTER);

        $sheet->getStyle('G9:H' . $dataEndRow)->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT)
            ->setVertical(Alignment::VERTICAL_CENTER);

        return [];
    }
}