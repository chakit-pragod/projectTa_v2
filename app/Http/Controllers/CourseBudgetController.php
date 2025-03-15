<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\CompensationRate;
use App\Models\CompensationTransaction;
use App\Models\CourseBudget;
use App\Models\Courses;
use App\Models\CourseTas;
use App\Models\ExtraTeaching;
use App\Models\Attendances;
use App\Models\Students;
use App\Models\Teaching;
use App\Models\ExtraAttendances;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseBudgetController extends Controller
{


    //งบวิชา
    public function index()
    {
        $courses = Courses::with(['subjects', 'classes', 'course_tas'])
            ->get()
            ->map(function ($course) {
                $totalStudents = $course->classes->sum('enrolled_num');
                $totalBudget = $totalStudents * 300; // 300 บาทต่อคน
                $budget = CourseBudget::where('course_id', $course->course_id)->first();

                return [
                    'course_id' => $course->course_id,
                    'subject_id' => $course->subject_id,
                    'subject_name' => $course->subjects->name_en ?? 'N/A',
                    'student_count ' => $totalStudents,
                    'total_budget' => $totalBudget,
                    'used_budget' => $budget ? $budget->used_budget : 0,
                    'remaining_budget' => $budget ? $budget->remaining_budget : $totalBudget,
                    'total_tas' => $course->course_tas->count(),
                    'has_budget' => $budget !== null
                ];
            });

        return view('layouts.admin.course-budgets.index', compact('courses'));
    }

    //คำนวนงบวิชา
    public function calculateBudget(Request $request)
    {
        $request->validate([
            'course_id' => 'required|exists:courses,course_id'
        ]);

        $courseId = $request->input('course_id');

        try {
            $budget = $this->calculateAndSaveCourseBudget($courseId);
            return redirect()->route('admin.course-budgets.index')
                ->with('success', "คำนวณงบประมาณรายวิชา {$budget['course_id']} เรียบร้อยแล้ว");
        } catch (\Exception $e) {
            Log::error('Error calculating course budget: ' . $e->getMessage());
            return redirect()->route('admin.course-budgets.index')
                ->with('error', 'เกิดข้อผิดพลาดในการคำนวณงบประมาณ: ' . $e->getMessage());
        }
    }

    //คำนวน บันทึกงบวิชา
    private function calculateAndSaveCourseBudget($courseId)
    {
        try {
            // ดึงข้อมูลรายวิชา
            $course = Courses::with('classes')->findOrFail($courseId);

            $totalStudents = $course->classes->sum('enrolled_num');

            $totalBudget = $totalStudents * 300; // 300 บาทต่อคน

            $courseBudget = CourseBudget::where('course_id', $courseId)->first();

            if ($courseBudget) {
                $usedBudget = $courseBudget->used_budget;
                $courseBudget->total_students = $totalStudents;
                $courseBudget->total_budget = $totalBudget;
                $courseBudget->remaining_budget = $totalBudget - $usedBudget;
                $courseBudget->save();
            } else {
                $courseBudget = new CourseBudget();
                $courseBudget->course_id = $courseId;
                $courseBudget->total_students = $totalStudents;
                $courseBudget->total_budget = $totalBudget;
                $courseBudget->used_budget = 0;
                $courseBudget->remaining_budget = $totalBudget;
                $courseBudget->save();
            }

            return [
                'course_id' => $courseId,
                'total_students ' => $totalStudents,
                'total_budget' => $totalBudget,
                'used_budget' => $courseBudget->used_budget,
                'remaining_budget' => $courseBudget->remaining_budget
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating course budget: ' . $e->getMessage());
            throw $e;
        }
    }


    public function courseBudgetDetails($courseId)
    {
        $course = Courses::with(['subjects', 'course_tas.student'])->findOrFail($courseId);
        $budget = CourseBudget::where('course_id', $courseId)->first();

        if (!$budget) {
            $budgetData = $this->calculateAndSaveCourseBudget($courseId);
            $budget = CourseBudget::where('course_id', $courseId)->first();
        }

        $totalTAs = $course->course_tas->count();
        $budgetPerTA = $budget->remaining_budget;

        $transactions = CompensationTransaction::where('course_id', $courseId)
            ->with('student')
            ->orderBy('month_year', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $taCompensations = [];
        foreach ($course->course_tas as $ta) {
            $totalUsed = $transactions->sum('actual_amount');
            $remainingBudget = $budget->remaining_budget;

            $taCompensations[$ta->student->id] = [
                'student' => $ta->student,
                'total_used' => $totalUsed,
                'remaining_budget' => $remainingBudget,
                'percentage_used' => $budgetPerTA > 0 ? ($totalUsed / $budgetPerTA) * 100 : 0
            ];
        }

        return view('layouts.admin.course-budgets.details', compact(
            'course',
            'budget',
            'totalTAs',
            'budgetPerTA',
            'transactions',
            'taCompensations'
        ));
    }

    /**
     * คำนวณค่าตอบแทนรายเดือนสำหรับผู้ช่วยสอน
     */
    public function calculateCompensation(Request $request)
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,course_id',
            'month' => 'required|date_format:Y-m'
        ]);

        $studentId = $request->input('student_id');
        $courseId = $request->input('course_id');
        $yearMonth = $request->input('month');

        try {
            $compensationData = $this->getMonthlyCompensationData($studentId, $courseId, $yearMonth);
            //dd($compensationData);

            return view('layouts.admin.compensation-preview', compact('compensationData'));
        } catch (\Exception $e) {
            Log::error('Error calculating compensation: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการคำนวณค่าตอบแทน: ' . $e->getMessage());
        }
    }

    public function saveCompensation(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'course_id' => 'required|exists:courses,course_id',
            'month_year' => 'required|date_format:Y-m',
            'calculated_amount' => 'required|numeric|min:0',
            'actual_amount' => 'required|numeric|min:0',
            'hours_worked' => 'required|numeric|min:0',
            'adjustment_reason' => 'nullable|required_if:is_adjusted,1|string',
            'is_adjusted' => 'boolean'
        ]);

        try {
            DB::beginTransaction();

            // Check if there's already a transaction for this month
            $existingTransaction = CompensationTransaction::where('student_id', $validated['student_id'])
                ->where('course_id', $validated['course_id'])
                ->where('month_year', $validated['month_year'])
                ->first();

            if ($existingTransaction) {
                throw new \Exception('มีการเบิกจ่ายค่าตอบแทนสำหรับเดือนนี้ไปแล้ว');
            }

            // Get the course budget
            $courseBudget = CourseBudget::where('course_id', $validated['course_id'])->first();

            // If there's no budget record, create one
            if (!$courseBudget) {
                // Get course details
                $course = Courses::with('classes')->findOrFail($validated['course_id']);
                $totalStudents = $course->classes->sum('enrolled_num');
                $totalBudget = $totalStudents * 300; // 300 บาทต่อคน

                $courseBudget = new CourseBudget();
                $courseBudget->course_id = $validated['course_id'];
                $courseBudget->total_students = $totalStudents;
                $courseBudget->total_budget = $totalBudget;
                $courseBudget->used_budget = 0;
                $courseBudget->remaining_budget = $totalBudget;
                $courseBudget->save();
            }

            // Check remaining budget
            if ($validated['actual_amount'] > $courseBudget->remaining_budget) {
                throw new \Exception('งบประมาณคงเหลือไม่เพียงพอ (' . number_format($courseBudget->remaining_budget, 2) . ' บาท)');
            }

            // Create transaction
            $transaction = new CompensationTransaction();
            $transaction->course_id = $validated['course_id'];
            $transaction->student_id = $validated['student_id'];
            $transaction->month_year = $validated['month_year'];
            $transaction->hours_worked = $validated['hours_worked'];
            $transaction->calculated_amount = $validated['calculated_amount'];
            $transaction->actual_amount = $validated['actual_amount'];
            $transaction->is_adjusted = $request->has('is_adjusted') ? $request->is_adjusted : ($validated['calculated_amount'] != $validated['actual_amount']);
            $transaction->adjustment_reason = $transaction->is_adjusted ? $validated['adjustment_reason'] : null;
            $transaction->save();

            // Update budget
            $courseBudget->used_budget += $validated['actual_amount'];
            $courseBudget->remaining_budget = $courseBudget->total_budget - $courseBudget->used_budget;
            $courseBudget->save();

            DB::commit();

            return redirect()->route('admin.ta.profile', [
                'student_id' => $validated['student_id'],
                'course_id' => $validated['course_id']
            ])->with('success', 'บันทึกการเบิกจ่ายค่าตอบแทนเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error saving compensation: ' . $e->getMessage());

            return back()->withInput()->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
        }
    }

    public function showCompensationPreview(Request $request)
    {
        // ดึงข้อมูลจาก request
        $studentId = $request->input('student_id');
        $courseId = $request->input('course_id');
        $yearMonth = $request->input('month');

        // ดึงข้อมูลค่าตอบแทน
        $compensationData = $this->getMonthlyCompensationData($studentId, $courseId, $yearMonth);

        // ส่งข้อมูลไปยัง view
        return view('layouts.admin.compensation-preview', compact('compensationData'));
    }

    /**
     * ยกเลิกรายการเบิกจ่ายค่าตอบแทน
     */
    public function cancelTransaction($id)
    {
        try {
            DB::beginTransaction();

            $transaction = CompensationTransaction::findOrFail($id);
            $courseId = $transaction->course_id;

            // อัปเดตงบประมาณคงเหลือ
            $courseBudget = CourseBudget::where('course_id', $courseId)->firstOrFail();
            $courseBudget->used_budget -= $transaction->actual_amount;
            $courseBudget->remaining_budget = $courseBudget->total_budget - $courseBudget->used_budget;
            $courseBudget->save();

            // ลบรายการเบิกจ่าย
            $transaction->delete();

            DB::commit();

            return redirect()->route('admin.course-budgets.details', $courseId)
                ->with('success', 'ยกเลิกรายการเบิกจ่ายค่าตอบแทนเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error canceling transaction: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการยกเลิกรายการ: ' . $e->getMessage());
        }
    }

    /**
     * คำนวณค่าตอบแทนรายเดือน
     */
    private function getMonthlyCompensationData($studentId, $courseId, $yearMonth)
    {
        // ดึงข้อมูล TA และรายวิชา
        $student = Students::findOrFail($studentId);
        $course = Courses::with(['subjects', 'classes'])->findOrFail($courseId);

        // ดึงหรือคำนวณงบประมาณรายวิชา
        $courseBudget = CourseBudget::where('course_id', $courseId)->first();
        if (!$courseBudget) {
            $this->calculateAndSaveCourseBudget($courseId);
            $courseBudget = CourseBudget::where('course_id', $courseId)->first();
        }

        $totalUsed = CompensationTransaction::where('course_id', $courseId)
            ->sum('actual_amount');


        $remainingBudgetForTA = $courseBudget->remaining_budget ?? 0;

        if ($remainingBudgetForTA < 0) {
            // หากงบประมาณคงเหลือติดลบ ให้ redirect ไปยังหน้า preview
            return [
                'is_exceeded' => true,
                'remainingBudgetForTA' => 0, // ตั้งค่าให้งบประมาณคงเหลือเป็น 0
                'final_amount' => 0, // ตั้งค่าจำนวนเงินที่จะเบิกจ่ายเป็น 0
                'error' => 'งบประมาณคงเหลือไม่เพียงพอ กรุณาปรับจำนวนเงินที่ต้องการเบิกจ่าย'
            ];
        }
        // คำนวณค่าตอบแทนจากชั่วโมงการสอนในเดือนที่เลือก
        $startDate = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $endDate = Carbon::createFromFormat('Y-m', $yearMonth)->endOfMonth();



        // ดึงข้อมูลการสอนปกติ
        $regularTeachings = Teaching::with(['attendance', 'class.major'])
            ->whereHas('attendance', function ($query) use ($studentId) {
                $query->where('student_id', $studentId)
                    ->where('approve_status', 'A');
            })
            ->whereBetween('start_time', [$startDate, $endDate])
            ->get();

        // ดึงข้อมูลการสอนพิเศษ
        $extraTeachings = ExtraAttendances::with(['classes.major'])
            ->where('student_id', $studentId)
            ->where('approve_status', 'A')
            ->whereBetween('start_work', [$startDate, $endDate])
            ->get();

        // ดึงข้อมูลการสอนชดเชย
        $extraTeachingIds = Attendances::where('student_id', $studentId)
            ->where('is_extra', true)
            ->where('approve_status', 'A')
            ->whereNotNull('extra_teaching_id')
            ->pluck('extra_teaching_id')
            ->toArray();

        $extraAttendances = ExtraAttendances::with(['classes.major'])
            ->where('student_id', $studentId)
            ->where('approve_status', 'A')
            ->whereBetween('start_work', [$startDate, $endDate])
            ->get();

        $extraTeachingsCompensation = ExtraTeaching::with(['class.major'])
            ->whereIn('extra_class_id', $extraTeachingIds)
            ->whereBetween('class_date', [$startDate, $endDate])
            ->get();

        // แยกประเภทการสอนและคำนวณชั่วโมงรวม
        $regularLectureHours = 0;
        $regularLabHours = 0;
        $specialLectureHours = 0;
        $specialLabHours = 0;

        foreach ($extraAttendances as $teaching) {
            $hours = $teaching->duration / 60;

            // ตรวจสอบว่ามีข้อมูล major และ class_type
            if (!$teaching->classes || !$teaching->classes->major) {
                Log::warning("ExtraAttendance ID {$teaching->id} missing classes or major data");
                continue;
            }

            $majorType = $teaching->classes->major->major_type ?? 'N';
            $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

            // คำนวณชั่วโมงตามประเภท
            if ($majorType === 'N') {
                if ($classType === 'LECTURE') {
                    $regularLectureHours += $hours;
                } else {
                    $regularLabHours += $hours;
                }
            } else {
                if ($classType === 'LECTURE') {
                    $specialLectureHours += $hours;
                } else {
                    $specialLabHours += $hours;
                }
            }
        }


        foreach ($regularTeachings as $teaching) {
            $startTime = Carbon::parse($teaching->start_time);
            $endTime = Carbon::parse($teaching->end_time);
            $hours = $endTime->diffInMinutes($startTime) / 60;

            $majorType = $teaching->class->major->major_type ?? 'N';
            $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

            if ($majorType === 'N') {
                if ($classType === 'LECTURE') {
                    $regularLectureHours += $hours;
                } else {
                    $regularLabHours += $hours;
                }
            } else {
                if ($classType === 'LECTURE') {
                    $specialLectureHours += $hours;
                } else {
                    $specialLabHours += $hours;
                }
            }
        }

        foreach ($extraTeachings as $teaching) {
            $hours = $teaching->duration / 60;

            $majorType = $teaching->classes->major->major_type ?? 'N';
            $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

            if ($majorType === 'N') {
                if ($classType === 'LECTURE') {
                    $regularLectureHours += $hours;
                } else {
                    $regularLabHours += $hours;
                }
            } else {
                if ($classType === 'LECTURE') {
                    $specialLectureHours += $hours;
                } else {
                    $specialLabHours += $hours;
                }
            }
        }

        // คำนวณชั่วโมงการสอนชดเชย
        foreach ($extraTeachingsCompensation as $extraTeaching) {
            $startTime = Carbon::parse($extraTeaching->start_time);
            $endTime = Carbon::parse($extraTeaching->end_time);
            $hours = $endTime->diffInMinutes($startTime) / 60;

            $majorType = $extraTeaching->class->major->major_type ?? 'N';
            $classType = $extraTeaching->class_type === 'L' ? 'LAB' : 'LECTURE';

            if ($majorType === 'N') {
                if ($classType === 'LECTURE') {
                    $regularLectureHours += $hours;
                } else {
                    $regularLabHours += $hours;
                }
            } else {
                if ($classType === 'LECTURE') {
                    $specialLectureHours += $hours;
                } else {
                    $specialLabHours += $hours;
                }
            }
        }

        // ดึงอัตราค่าตอบแทนจากฐานข้อมูล
        $degreeLevel = $student->degree_level ?? 'undergraduate';

        // ดึงอัตราค่าตอบแทนที่เกี่ยวข้อง
        $regularLectureRate = $this->getCompensationRate('regular', 'LECTURE', $degreeLevel);
        $regularLabRate = $this->getCompensationRate('regular', 'LAB', $degreeLevel);
        $specialLectureRate = $this->getCompensationRate('special', 'LECTURE', $degreeLevel);
        $specialLabRate = $this->getCompensationRate('special', 'LAB', $degreeLevel);

        // คำนวณค่าตอบแทน
        $regularLecturePay = $regularLectureHours * $regularLectureRate;
        $regularLabPay = $regularLabHours * $regularLabRate;
        $specialLecturePay = $specialLectureHours * $specialLectureRate;
        $specialLabPay = $specialLabHours * $specialLabRate;

        $regularPay = $regularLecturePay + $regularLabPay;
        $specialPay = $specialLecturePay + $specialLabPay;
        $totalPay = $regularPay + $specialPay;

        // ตรวจสอบว่ายังมีงบประมาณเพียงพอหรือไม่
        $isExceeded = $totalPay > $remainingBudgetForTA;
        $finalAmount = $isExceeded ? $remainingBudgetForTA : $totalPay;

        // หากเป็น TA บัณฑิตและสอนภาคพิเศษ ให้ใช้การจ่ายแบบเหมาจ่าย
        $isFixedPayment = false;
        $fixedAmount = null;
        $courseBudget = CourseBudget::firstOrNew(['course_id' => $courseId]);
        $remainingBudgetForTA = $courseBudget->remaining_budget ?? 0;

        if ($degreeLevel === 'graduate' && ($specialLectureHours > 0 || $specialLabHours > 0)) {
            // ดึงอัตราเหมาจ่ายจากฐานข้อมูล
            $fixedRate = $this->getFixedCompensationRate('special', $degreeLevel);
            if ($fixedRate) {
                $isFixedPayment = true;
                $fixedAmount = $fixedRate;

                // ตรวจสอบว่าเกิน 4,000 บาทหรือไม่
                if ($fixedAmount > 4000) {
                    $fixedAmount = 4000; // จำกัดไม่ให้เกิน 4,000 บาท
                }

                $totalPay = $regularPay + $fixedAmount; // รวมค่าตอบแทนภาคปกติกับค่าเหมาจ่าย

                // ตรวจสอบว่ายังมีงบประมาณเพียงพอหรือไม่อีกครั้ง
                $isExceeded = $totalPay > $remainingBudgetForTA;
                $finalAmount = $isExceeded ? $remainingBudgetForTA : $totalPay;
            }
        }

        return [
            'student' => $student,
            'course' => $course,
            'year_month' => $yearMonth,
            'month_name' => Carbon::createFromFormat('Y-m', $yearMonth)->locale('th')->monthName,
            'year' => Carbon::createFromFormat('Y-m', $yearMonth)->year + 543,
            'student_count ' => $courseBudget->student_count,
            'total_budget' => $courseBudget->total_budget,
            'remainingBudgetForTA' => $remainingBudgetForTA,
            'hours' => [
                'regularLecture' => $regularLectureHours,
                'regularLab' => $regularLabHours,
                'specialLecture' => $specialLectureHours,
                'specialLab' => $specialLabHours,
                'regular' => $regularLectureHours + $regularLabHours,
                'special' => $specialLectureHours + $specialLabHours,
                'total' => $regularLectureHours + $regularLabHours + $specialLectureHours + $specialLabHours
            ],
            'rates' => [
                'regularLecture' => $regularLectureRate,
                'regularLab' => $regularLabRate,
                'specialLecture' => $specialLectureRate,
                'specialLab' => $specialLabRate
            ],
            'pay' => [
                'regularLecture' => $regularLecturePay,
                'regularLab' => $regularLabPay,
                'specialLecture' => $specialLecturePay,
                'specialLab' => $specialLabPay,
                'regular' => $regularPay,
                'special' => $specialPay,
                'total' => $totalPay
            ],
            'is_fixed_payment' => $isFixedPayment,
            'fixed_amount' => $fixedAmount,
            'is_exceeded' => $isExceeded,
            'final_amount' => $finalAmount,
            'total_hours' => $regularLectureHours + $regularLabHours + $specialLectureHours + $specialLabHours
        ];
    }

    /**
     * ดึงอัตราค่าตอบแทนจากฐานข้อมูล
     */
    private function getCompensationRate($teachingType, $classType, $degreeLevel)
    {
        $rate = CompensationRate::where('teaching_type', $teachingType)
            ->where('class_type', $classType)
            ->where('degree_level', $degreeLevel)
            ->where('status', 'active')
            ->where('is_fixed_payment', false)
            ->first();

        if (!$rate) {
            // ใช้ค่าเริ่มต้นถ้าไม่พบอัตราในฐานข้อมูล
            if ($degreeLevel === 'undergraduate') {
                if ($teachingType === 'regular') {
                    return 40; // ผู้ช่วยสอน ป.ตรี ที่สอน ภาคปกติ
                } else {
                    return 50; // ผู้ช่วยสอน ป.ตรี ที่สอน ภาคพิเศษ
                }
            } else { // graduate
                if ($teachingType === 'regular') {
                    return 50; // ผู้ช่วยสอน บัณฑิต ที่สอน ภาคปกติ
                } else {
                    return 60; // ผู้ช่วยสอน บัณฑิต ที่สอน ภาคพิเศษ (กรณีไม่ใช้เหมาจ่าย)
                }
            }
        }

        return $rate->rate_per_hour;
    }

    /**
     * ดึงอัตราค่าตอบแทนแบบเหมาจ่ายจากฐานข้อมูล
     */
    private function getFixedCompensationRate($teachingType, $degreeLevel)
    {
        Log::debug("Fetching fixed rate for: {$teachingType}, {$degreeLevel}");

        $rate = CompensationRate::where('teaching_type', $teachingType)
            ->where('degree_level', $degreeLevel)
            ->where('status', 'active')
            ->where('is_fixed_payment', true)
            ->first();

        if ($rate) {
            Log::debug("Found fixed rate: " . $rate->fixed_amount);
            return $rate->fixed_amount;
        }

        Log::debug("No fixed rate found, using default");
        // ใช้ค่าเริ่มต้นถ้าไม่พบอัตราในฐานข้อมูล
        if ($degreeLevel === 'graduate' && $teachingType === 'special') {
            return 4000; // ผู้ช่วยสอน บัณฑิต ที่สอน ภาคพิเศษ เหมาจ่ายรายเดือน
        }

        return null;
    }
}
