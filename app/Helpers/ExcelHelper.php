<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class ExcelHelper
{
    /**
     * โหลด Template Excel และกรอกข้อมูล
     */
    public static function fillTemplateWithData($templatePath, $data)
    {
        if (!file_exists($templatePath)) {
            throw new \Exception("Template file not found: $templatePath");
        }

        $spreadsheet = IOFactory::load($templatePath);
        
        $sheet = $spreadsheet->getActiveSheet();
        
        foreach ($data as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        return $spreadsheet;
    }

    public static function fillTableData($sheet, $startRow, $data, $columns)
    {
        $row = $startRow;
        
        foreach ($data as $item) {
            $col = 0;
            foreach ($columns as $key) {
                $cellValue = isset($item[$key]) ? $item[$key] : '';
                $sheet->setCellValueByColumnAndRow($col + 1, $row, $cellValue);
                $col++;
            }
            $row++;
        }
        
        return $row; 
    }
    

    

    public static function fillAttendanceData(Worksheet $sheet, $attendances, $student, $startRow = 9)
    {
        $row = $startRow;
        $rowNumber = 1;
        
        foreach ($attendances as $attendance) {
            $isRegular = $attendance['type'] === 'regular';
            
            $date = $isRegular 
                ? Carbon::parse($attendance['data']->start_time)->format('d-m-y')
                : Carbon::parse($attendance['data']->start_work)->format('d-m-y');
                
            $time = $isRegular
                ? Carbon::parse($attendance['data']->start_time)->format('H:i') . '-' . Carbon::parse($attendance['data']->end_time)->format('H:i')
                : Carbon::parse($attendance['data']->start_work)->format('H:i');
                
            $courseId = $isRegular
                ? ($attendance['data']->class->course->subjects->subject_id ?? '-')
                : ($attendance['data']->classes->course->subjects->subject_id ?? '-');
                
            $lectureHours = 0;
            $labHours = 0;
            
            if ($attendance['class_type'] === 'LECTURE') {
                $lectureHours = $attendance['hours'];
            } else {
                $labHours = $attendance['hours'];
            }
            
            $note = $isRegular
                ? ($attendance['data']->attendance->note ?? 'ตรวจงาน / เช็คชื่อ')
                : ($attendance['data']->detail ?? 'ตรวจงาน / เช็คชื่อ');
            
            $sheet->setCellValue('A' . $row, $rowNumber);
            
            if ($rowNumber === 1) {
                $sheet->setCellValue('B' . $row, $student->name);
                $sheet->setCellValue('C' . $row, 'ป.ตรี');
            }
            
            $sheet->setCellValue('D' . $row, $date);
            $sheet->setCellValue('E' . $row, $courseId);
            $sheet->setCellValue('F' . $row, $time);
            
            if ($lectureHours > 0) {
                $sheet->setCellValue('G' . $row, number_format($lectureHours, 2));
            }
            
            if ($labHours > 0) {
                $sheet->setCellValue('H' . $row, number_format($labHours, 2));
            }
            
            $sheet->setCellValue('I' . $row, $note);
            
            $row++;
            $rowNumber++;
        }
        
        return $row; 
    }
}