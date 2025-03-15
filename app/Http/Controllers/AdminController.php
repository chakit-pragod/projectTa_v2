<?php

namespace App\Http\Controllers;

use App\Models\{
    Announce,
    Classes,
    Courses,
    CourseTas,
    CourseTaClasses,
    Disbursements,
    ExtraAttendances,
    Requests,
    Students,
    Teaching,
    TeacherRequest,
    TeacherRequestsDetail,
    CompensationRate,
    CompensationTransaction,
    Semesters,
    ExtraTeaching,
    Attendances
};
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\TaAttendanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\{Auth, DB, Log, Storage};
use PhpOffice\PhpSpreadsheet\IOFactory;



use Illuminate\Http\Request;

class AdminController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function adminHome()
    {
        $coursesWithTAs = Courses::whereHas('course_tas.courseTaClasses.requests', function ($query) {
            $query->where('status', 'A')
                ->whereNotNull('approved_at');
        })
            ->with([
                'subjects',
                'teachers',
                'course_tas.student',
                'course_tas.courseTaClasses.requests' => function ($query) {
                    $query->where('status', 'A')
                        ->whereNotNull('approved_at');
                }
            ])
            ->get();

        // return view('layouts.admin.taUsers', compact('coursesWithTAs'));

        $requests = CourseTas::with([
            'student',
            'course.subjects',
            'courseTaClasses.requests' => function ($query) {
                $query->latest();
            }
        ])
            // ->where('student_id', $student->id)
            ->get()
            ->map(function ($courseTa) {
                $latestRequest = $courseTa->courseTaClasses->flatMap->requests->sortByDesc('created_at')->first();

                return [
                    'student_id' => $courseTa->student->student_id,
                    'full_name' => $courseTa->student->name,
                    'course' => $courseTa->course->subjects->subject_id . ' ' . $courseTa->course->subjects->name_en,
                    'applied_at' => $courseTa->created_at,
                    'status' => $latestRequest ? $latestRequest->status : null,
                    'approved_at' => $latestRequest ? $latestRequest->approved_at : null,
                    'comment' => $latestRequest ? $latestRequest->comment : null,
                ];
            });
        return view('layouts.admin.adminHome', compact('requests'));
    }

    public function taUsers(Request $request)
    {
        try {
            // ดึงข้อมูล semesters ทั้งหมดเพื่อให้ admin เลือก
            $semesters = Semesters::orderBy('year', 'desc')
                ->orderBy('semesters', 'desc')
                ->get();

            // ดึง semester_id ที่ admin เลือกจาก request หรือใช้ค่าที่บันทึกใน session
            $selectedSemesterId = $request->input('semester_id');

            if ($selectedSemesterId) {
                // บันทึกค่าที่เลือกลงใน session สำหรับ admin
                session(['admin_selected_semester_id' => $selectedSemesterId]);
            } else {
                $selectedSemesterId = session('admin_selected_semester_id');

                if (!$selectedSemesterId && $semesters->isNotEmpty()) {
                    $selectedSemesterId = $semesters->first()->semester_id;
                    session(['admin_selected_semester_id' => $selectedSemesterId]);
                }
            }

            // ค้นหา semester ที่ admin เลือก
            $selectedSemester = $semesters->firstWhere('semester_id', $selectedSemesterId);

            // ดึงข้อมูล semester ที่เลือกสำหรับผู้ใช้ (TA และ Teacher)
            $userSetting = DB::table('setting_semesters')->where('key', 'user_active_semester_id')->first();
            $userSelectedSemesterId = $userSetting ? $userSetting->value : null;

            if (!$userSelectedSemesterId && $semesters->isNotEmpty()) {
                $userSelectedSemesterId = $semesters->first()->semester_id;

                // บันทึกค่าเริ่มต้นลงในตาราง setting_semesters
                DB::table('setting_semesters')->updateOrInsert(
                    ['key' => 'user_active_semester_id'],
                    ['value' => $userSelectedSemesterId, 'updated_at' => now()]
                );
            }

            $userSelectedSemester = $semesters->firstWhere('semester_id', $userSelectedSemesterId);

            // ถ้าไม่พบ semester ที่ admin เลือก ให้แสดงข้อความแจ้งเตือน
            if (!$selectedSemester) {
                return view('layouts.admin.taUsers', [
                    'coursesWithTAs' => collect(),
                    'semesters' => $semesters,
                    'selectedSemester' => null,
                    'userSelectedSemester' => $userSelectedSemester
                ])->with('warning', 'ไม่พบข้อมูลภาคการศึกษาที่เลือก');
            }

            // ดึงข้อมูลรายวิชาที่มี TA ตาม semester ที่ admin เลือก
            $coursesWithTAs = Courses::where('semester_id', $selectedSemester->semester_id)
                ->whereHas('course_tas.courseTaClasses.requests', function ($query) {
                    $query->where('status', 'A')
                        ->whereNotNull('approved_at');
                })
                ->with([
                    'subjects',
                    'teachers',
                    'course_tas.student',
                    'course_tas.courseTaClasses.requests' => function ($query) {
                        $query->where('status', 'A')
                            ->whereNotNull('approved_at');
                    }
                ])
                ->get();

            Log::info('จำนวนรายวิชาที่มี TA: ' . $coursesWithTAs->count());

            return view('layouts.admin.taUsers', [
                'coursesWithTAs' => $coursesWithTAs,
                'semesters' => $semesters,
                'selectedSemester' => $selectedSemester,
                'userSelectedSemester' => $userSelectedSemester
            ]);
        } catch (\Exception $e) {
            Log::error('Error in taUsers: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
        }
    }

    private function updateActiveSemester($semesterId)
    {
        try {

            DB::table('setting_semesters')->updateOrInsert(
                ['key' => 'active_semester_id'],
                ['value' => $semesterId, 'updated_at' => now()]
            );

            session(['active_semester_id' => $semesterId]);

            return true;
        } catch (\Exception $e) {
            Log::error('Error updating active semester: ' . $e->getMessage());
            return false;
        }
    }

    public function updateUserSemester(Request $request)
    {
        try {
            $semesterId = $request->input('semester_id');

            if (!$semesterId) {
                return back()->with('error', 'กรุณาเลือกภาคการศึกษา');
            }

            $semester = Semesters::find($semesterId);
            if (!$semester) {
                return back()->with('error', 'ไม่พบภาคการศึกษาที่เลือก');
            }

            // อัปเดตค่าใน setting_semesters
            DB::table('setting_semesters')
                ->updateOrInsert(
                    ['key' => 'user_active_semester_id'],
                    [
                        'value' => $semesterId,
                        'updated_at' => now()
                    ]
                );

            // อัปเดต session
            session(['user_active_semester_id' => $semesterId]);

            // Log debug information
            Log::info('User Active Semester Updated: ' . $semesterId);

            return back()->with('success', 'บันทึกการเลือกภาคการศึกษาสำหรับผู้ใช้งานเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            Log::error('Error updating user semester: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
        }
    }

    public function getActiveSemester()
    {
        try {
            $activeSemesterId = session('active_semester_id');

            if (!$activeSemesterId) {
                $setting = DB::table('setting_semesters')->where('key', 'active_semester_id')->first();

                if ($setting) {
                    $activeSemesterId = $setting->value;
                    session(['active_semester_id' => $activeSemesterId]);
                } else {
                    $latestSemester = Semesters::orderBy('year', 'desc')
                        ->orderBy('semesters', 'desc')
                        ->first();

                    if ($latestSemester) {
                        $activeSemesterId = $latestSemester->semester_id;
                        $this->updateActiveSemester($activeSemesterId);
                    }
                }
            }

            return Semesters::find($activeSemesterId);
        } catch (\Exception $e) {
            Log::error('Error getting active semester: ' . $e->getMessage());
            return null;
        }
    }

    public function showTaDetails($course_id)
    {
        $course = Courses::with([
            'subjects',
            'teachers',
            'semesters',
            'course_tas.student',
            'course_tas.courseTaClasses.requests' => function ($query) {
                $query->where('status', 'A')
                    ->whereNotNull('approved_at');
            }
        ])
            ->where('course_id', $course_id)
            ->firstOrFail();

        return view('layouts.admin.detailsTa', compact('course'));
    }

    public function downloadDocument($id)
    {
        try {
            $disbursement = Disbursements::findOrFail($id);

            if (!Storage::exists('public' . $disbursement->file_path)) {
                return back()->with('error', 'ไม่พบไฟล์เอกสาร');
            }

            return response()->download(storage_path('app/public' . $disbursement->file_path));
        } catch (\Exception $e) {
            Log::error('Document download error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการดาวน์โหลดเอกสาร');
        }
    }


    private function getCompensationRate($teachingType, $classType)
    {
        $rate = CompensationRate::getActiveRate($teachingType, $classType);

        if (!$rate) {
            if ($teachingType === 'regular') {
                if ($classType === 'LECTURE') {
                    return 40; // ค่าเริ่มต้นสำหรับการสอนบรรยายภาคปกติ
                } else { // LAB
                    return 40; // ค่าเริ่มต้นสำหรับการสอนปฏิบัติการภาคปกติ
                }
            } else { // special
                if ($classType === 'LECTURE') {
                    return 50; // ค่าเริ่มต้นสำหรับการสอนบรรยายภาคพิเศษ
                } else { // LAB
                    return 50; // ค่าเริ่มต้นสำหรับการสอนปฏิบัติการภาคพิเศษ
                }
            }
        }

        return $rate->rate_per_hour;
    }

    public function taDetail($student_id)
    {
        try {
            $course_id = request('course_id');

            // if (!$course_id) {
            //     return back()->with('error', 'กรุณาระบุรหัสรายวิชา');
            // }

            $ta = CourseTas::with(['student', 'course.semesters'])
                ->where('student_id', $student_id)
                ->where('course_id', $course_id)
                ->firstOrFail();

            $course = $ta->course;
            $student = $ta->student;
            $semester = $ta->course->semesters;

            $start = \Carbon\Carbon::parse($semester->start_date)->startOfDay();
            $end = \Carbon\Carbon::parse($semester->end_date)->endOfDay();

            if (!\Carbon\Carbon::now()->between($start, $end)) {
                return back()->with('error', 'ไม่อยู่ในช่วงภาคการศึกษาปัจจุบัน');
            }

            $monthsInSemester = [];
            if ($start->year === $end->year) {
                for ($m = $start->month; $m <= $end->month; $m++) {
                    $date = \Carbon\Carbon::createFromDate($start->year, $m, 1);
                    $monthsInSemester[$date->format('Y-m')] = $date->locale('th')->monthName . ' ' . ($date->year + 543);
                }
            } else {
                for ($m = $start->month; $m <= 12; $m++) {
                    $date = \Carbon\Carbon::createFromDate($start->year, $m, 1);
                    $monthsInSemester[$date->format('Y-m')] = $date->locale('th')->monthName . ' ' . ($date->year + 543);
                }
                for ($m = 1; $m <= $end->month; $m++) {
                    $date = \Carbon\Carbon::createFromDate($end->year, $m, 1);
                    $monthsInSemester[$date->format('Y-m')] = $date->locale('th')->monthName . ' ' . ($date->year + 543);
                }
            }

            $selectedYearMonth = request('month', $start->format('Y-m'));
            $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);
            $attendanceType = request('type', 'all');

            $allAttendances = collect();
            $regularLectureHours = 0;
            $regularLabHours = 0;
            $specialLectureHours = 0;
            $specialLabHours = 0;

            if ($attendanceType === 'all' || $attendanceType === 'N' || $attendanceType === 'S') {
                // ดึงข้อมูลการสอนปกติที่ได้รับการอนุมัติแล้ว
                $teachings = Teaching::with([
                    'attendance' => function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                            ->where('approve_status', 'a');
                    },
                    'teacher',
                    'class.course.subjects',
                    'class.major'
                ])
                    ->where('class_id', 'LIKE', $ta->course_id . '%')
                    ->whereYear('start_time', $selectedDate->year)
                    ->whereMonth('start_time', $selectedDate->month)
                    ->whereHas('attendance', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                            ->where('approve_status', 'a');
                    })
                    ->when($attendanceType !== 'all', function ($query) use ($attendanceType) {
                        $query->whereHas('class.major', function ($q) use ($attendanceType) {
                            $q->where('major_type', $attendanceType);
                        });
                    })
                    ->get()
                    ->groupBy(function ($teaching) {
                        return $teaching->class->section_num;
                    });

                // ประมวลผลข้อมูลการสอนปกติ
                foreach ($teachings as $section => $sectionTeachings) {
                    foreach ($sectionTeachings as $teaching) {
                        $startTime = \Carbon\Carbon::parse($teaching->start_time);
                        $endTime = \Carbon\Carbon::parse($teaching->end_time);
                        $hours = $endTime->diffInMinutes($startTime) / 60;

                        $majorType = $teaching->class->major->major_type ?? 'N';
                        $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                        if ($majorType === 'N') {
                            if ($classType === 'LECTURE') {
                                $regularLectureHours += $hours;
                            } else {
                                $regularLabHours += $hours;
                            }
                        } else { // S
                            if ($classType === 'LECTURE') {
                                $specialLectureHours += $hours;
                            } else {
                                $specialLabHours += $hours;
                            }
                        }

                        $allAttendances->push([
                            'type' => 'regular',
                            'section' => $section,
                            'date' => $teaching->start_time,
                            'data' => $teaching,
                            'hours' => $hours,
                            'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                            'class_type' => $classType
                        ]);
                    }
                }

                // ดึงข้อมูลการสอนชดเชย
                $extraTeachingIds = Attendances::where('student_id', $student->id)
                    ->where('is_extra', true)
                    ->where('approve_status', 'a')
                    ->whereNotNull('extra_teaching_id')
                    ->pluck('extra_teaching_id')
                    ->toArray();
                $startDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->startOfMonth();
                $endDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->endOfMonth();
                $extraTeachings = ExtraTeaching::with(['class.major'])
                    ->whereIn('extra_class_id', $extraTeachingIds)
                    ->whereBetween('class_date', [$startDate, $endDate])
                    ->get();

                // คำนวณชั่วโมงการสอนชดเชย
                foreach ($extraTeachings as $extraTeaching) {
                    $startTime = \Carbon\Carbon::parse($extraTeaching->start_time);
                    $endTime = \Carbon\Carbon::parse($extraTeaching->end_time);
                    $hours = $endTime->diffInMinutes($startTime) / 60;

                    $majorType = $extraTeaching->class->major->major_type ?? 'N';
                    $classType = $extraTeaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        // ถ้าเป็นโครงการปกติ
                        if ($classType === 'LECTURE') {
                            $regularLectureHours += $hours;
                        } else {
                            $regularLabHours += $hours;
                        }
                    } else {
                        // ถ้าเป็นโครงการพิเศษ
                        if ($classType === 'LECTURE') {
                            $specialLectureHours += $hours;
                        } else {
                            $specialLabHours += $hours;
                        }
                    }

                    // เพิ่มข้อมูลการสอนชดเชยใน allAttendances
                    $allAttendances->push([
                        'type' => 'extra',
                        'section' => $extraTeaching->class->section_num ?? 'N/A',
                        'date' => $extraTeaching->class_date . ' ' . $extraTeaching->start_time,
                        'data' => $extraTeaching,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            // บันทึก log สรุปชั่วโมงทั้งหมด
            Log::debug('Hour summary:', [
                'regularLecture' => $regularLectureHours,
                'regularLab' => $regularLabHours,
                'specialLecture' => $specialLectureHours,
                'specialLab' => $specialLabHours,
                'total' => $regularLectureHours + $regularLabHours + $specialLectureHours + $specialLabHours
            ]);

            $regularLectureRate = $this->getCompensationRate('regular', 'LECTURE');
            $regularLabRate = $this->getCompensationRate('regular', 'LAB');
            $specialLectureRate = $this->getCompensationRate('special', 'LECTURE');
            $specialLabRate = $this->getCompensationRate('special', 'LAB');

            // คำนวณค่าตอบแทน
            $regularLecturePay = $regularLectureHours * $regularLectureRate;
            $regularLabPay = $regularLabHours * $regularLabRate;
            $specialLecturePay = $specialLectureHours * $specialLectureRate;
            $specialLabPay = $specialLabHours * $specialLabRate;

            $regularPay = $regularLecturePay + $regularLabPay;
            $specialPay = $specialLecturePay + $specialLabPay;
            $totalPay = $regularPay + $specialPay;

            $compensation = [
                'regularLectureHours' => $regularLectureHours,
                'regularLabHours' => $regularLabHours,
                'specialLectureHours' => $specialLectureHours,
                'specialLabHours' => $specialLabHours,
                'regularHours' => $regularLectureHours + $regularLabHours,
                'specialHours' => $specialLectureHours + $specialLabHours,
                'regularLecturePay' => $regularLecturePay,
                'regularLabPay' => $regularLabPay,
                'specialLecturePay' => $specialLecturePay,
                'specialLabPay' => $specialLabPay,
                'regularPay' => $regularPay,
                'specialPay' => $specialPay,
                'totalPay' => $totalPay,

                'rates' => [
                    'regularLecture' => $regularLectureRate,
                    'regularLab' => $regularLabRate,
                    'specialLecture' => $specialLectureRate,
                    'specialLab' => $specialLabRate
                ]
            ];

            $attendancesBySection = $allAttendances->sortBy('date')->groupBy('section');

            // คำนวณจำนวนนักศึกษาทั้งหมดในรายวิชา
            $course = Courses::with('classes')->find($ta->course_id);
            $totalStudents = $course ? $course->classes->sum('enrolled_num') : 0;

            // คำนวณงบประมาณรายวิชา
            $totalBudget = $totalStudents * 300; // 300 บาทต่อคน

            // ดึงข้อมูลการเบิกจ่ายทั้งหมดของรายวิชา (ทุก TA)
            $totalUsed = 0;
            try {
                $totalUsed = CompensationTransaction::where('course_id', $ta->course_id)
                    ->sum('actual_amount');
            } catch (\Exception $e) {
                // กรณีไม่มีตาราง CompensationTransaction ยังไม่ต้องทำอะไร
                Log::info('CompensationTransaction table might not exist yet: ' . $e->getMessage());
            }

            // คำนวณงบประมาณคงเหลือของรายวิชา
            $remainingBudget = $totalBudget - $totalUsed;

            // ตรวจสอบว่าค่าตอบแทนของเดือนนี้เกินงบประมาณที่เหลือหรือไม่
            $isExceeded = $totalPay > $remainingBudget;

            // สำหรับการแสดงผลข้อมูลเพิ่มเติม (อาจไม่จำเป็นต้องใช้)
            $totalTAs = CourseTas::where('course_id', $ta->course_id)->count();
            $budgetPerTA = $totalTAs > 0 ? $totalBudget / $totalTAs : 0;

            // ดึงข้อมูลการเบิกจ่ายเฉพาะของ TA คนนี้ (เพื่อแสดงประวัติการเบิกจ่าย)
            $totalUsedByTA = 0;
            try {
                $totalUsedByTA = CompensationTransaction::where('student_id', $student_id)
                    ->where('course_id', $ta->course_id)
                    ->sum('actual_amount');
            } catch (\Exception $e) {
                Log::info('Error getting TA transactions: ' . $e->getMessage());
            }

            $degreeLevel = $student->degree_level ?? 'undergraduate';
            $isFixedPayment = false;
            $fixedAmount = null;

            $degreeLevel = $student->degree_level ?? 'undergraduate';
            $isGraduate = in_array($degreeLevel, ['master', 'doctoral', 'graduate']);
            // ในเมธอด taDetail หรือ getMonthlyCompensationData
            if ($isGraduate && ($compensation['specialLectureHours'] > 0 || $compensation['specialLabHours'] > 0)) {
                // ดึงอัตราเหมาจ่ายจากฐานข้อมูล
                try {
                    $fixedRate = CompensationRate::where('teaching_type', 'special')
                        ->where('degree_level', 'graduate')
                        ->where('is_fixed_payment', true)
                        ->where('status', 'active')
                        ->first();

                    Log::debug("Found fixed rate record: ", ['record' => $fixedRate ? $fixedRate->toArray() : 'none']);

                    if ($fixedRate && $fixedRate->fixed_amount > 0) {
                        $isFixedPayment = true;
                        $fixedAmount = $fixedRate->fixed_amount;
                        // ปรับค่าตอบแทนให้เป็นแบบเหมาจ่าย
                        $compensation['specialPay'] = $fixedAmount;
                        $totalPay = $compensation['regularPay'] + $fixedAmount;
                        $compensation['totalPay'] = $totalPay;

                        Log::debug("Using fixed payment: {$fixedAmount}");
                    } else {
                        Log::debug("No valid fixed amount found in database");
                        $isFixedPayment = true;
                        $fixedAmount = 4000; // ค่าเริ่มต้น 4,000 บาท
                        $compensation['specialPay'] = $fixedAmount;
                        $totalPay = $compensation['regularPay'] + $fixedAmount;
                        $compensation['totalPay'] = $totalPay;

                        Log::debug("Using default fixed payment: {$fixedAmount}");
                    }
                } catch (\Exception $e) {
                    Log::error("Error getting fixed rate: " . $e->getMessage());
                    $isFixedPayment = false;
                    $fixedAmount = 0;
                }
            }
            // เรียงข้อมูลตามวันที่
            $allAttendances = $allAttendances->sortBy('date');

            // แล้วจึงจัดกลุ่มตาม section
            $attendancesBySection = $allAttendances->groupBy('section');
            // เพิ่มบรรทัดนี้เพื่อกำหนดค่า courseTa
            $courseTa = $ta;

            // dd($allAttendances);
            // ส่งข้อมูลทั้งหมดไปยัง view
            return view('layouts.admin.detailsById', compact(
                'student',
                'course',
                // 'courseTa',
                'semester',
                'attendancesBySection',
                'monthsInSemester',
                'selectedYearMonth',
                'attendanceType',
                'compensation',
                'totalStudents',
                'totalBudget',
                'totalTAs',
                'totalUsedByTA',
                'remainingBudget',
                'isExceeded',
                'isFixedPayment',
                'fixedAmount'
            ));
        } catch (\Exception $e) {
            Log::error('Exception in taDetail: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูล: ' . $e->getMessage());
        }
    }


    private function getAttendanceData($student_id, $selectedYearMonth, $attendanceType)
    {
        $start = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->startOfMonth();
        $end = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->endOfMonth();

        $allAttendances = collect();
        $regularHours = 0;
        $specialHours = 0;

        $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects'])
            ->whereHas('attendance', function ($query) use ($student_id) {
                $query->where('student_id', $student_id)
                    ->where('approve_status', 'A');
            })
            ->whereBetween('start_time', [$start, $end])
            ->when($attendanceType !== 'all', function ($query) use ($attendanceType) {
                $query->whereHas('class.major', function ($q) use ($attendanceType) {
                    $q->where('major_type', $attendanceType);
                });
            })
            ->get();

        foreach ($teachings as $teaching) {
            $hours = $teaching->duration / 60;
            $regularHours += $hours;

            $allAttendances->push([
                'type' => 'regular',
                'section' => $teaching->class->section_num,
                'date' => $teaching->start_time,
                'data' => $teaching,
                'hours' => $hours
            ]);
        }

        $extraAttendances = ExtraAttendances::with(['classes.course.subjects'])
            ->where('student_id', $student_id)
            ->where('approve_status', 'A')
            ->whereBetween('start_work', [$start, $end])
            ->when($attendanceType !== 'all', function ($query) use ($attendanceType) {
                $query->whereHas('classes.major', function ($q) use ($attendanceType) {
                    $q->where('major_type', $attendanceType);
                });
            })
            ->get();

        foreach ($extraAttendances as $extra) {
            $hours = $extra->duration / 60;
            $specialHours += $hours;

            $allAttendances->push([
                'type' => 'special',
                'section' => $extra->classes->section_num,
                'date' => $extra->start_work,
                'data' => $extra,
                'hours' => $hours
            ]);
        }

        return [
            'allAttendances' => $allAttendances,
            'regularHours' => $regularHours,
            'specialHours' => $specialHours
        ];
    }

    public function exportTaDetailPDF($id)
    {
        try {
            $selectedYearMonth = request('month');

            $ta = CourseTas::with(['student', 'course.semesters', 'course.teachers'])
                ->whereHas('student', function ($query) use ($id) {
                    $query->where('id', $id);
                })
                ->first()
                ->firstOrFail();

            if (!$ta) {
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอน');
            }

            $student = $ta->student;
            $semester = $ta->course->semesters;

            $teacherName = $ta->course->teachers->name ?? 'อาจารย์ผู้สอน';
            $teacherPosition = $ta->course->teachers->position ?? '';
            $teacherDegree = $ta->course->teachers->degree ?? '';
            $teacherFullTitle = trim($teacherPosition . ' ' . $teacherDegree . '.' . ' ' . $teacherName);

            $headName = 'ผศ. ดร.คำรณ สุนัติ';

            $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);

            $currentDate = \Carbon\Carbon::now();
            $thaiMonth = $currentDate->locale('th')->monthName;
            $thaiYear = $currentDate->year + 543;
            $formattedDate = [
                'day' => $currentDate->day,
                'month' => $thaiMonth,
                'year' => $thaiYear
            ];

            $monthText = $selectedDate->locale('th')->monthName . ' ' . ($selectedDate->year + 543);
            $year = $selectedDate->year + 543;

            $allAttendances = collect();
            $regularAttendances = collect();
            $specialAttendances = collect();

            $hasRegularProject = false;
            $hasSpecialProject = false;

            // Initialize variables to prevent undefined errors
            $isFixedPayment = false;
            $fixedAmount = 0;
            $specialPay = 0;
            $regularLectureHoursSum = 0;
            $regularLabHoursSum = 0;
            $specialLectureHoursSum = 0;
            $specialLabHoursSum = 0;

            $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects', 'class.major'])
                ->where('class_id', 'LIKE', $ta->course_id . '%')
                ->whereYear('start_time', $selectedDate->year)
                ->whereMonth('start_time', $selectedDate->month)
                ->whereHas('attendance', function ($query) {
                    $query->where('approve_status', 'A');
                })
                ->get()
                ->groupBy(function ($teaching) {
                    return $teaching->class->section_num;
                });

            foreach ($teachings as $section => $sectionTeachings) {
                foreach ($sectionTeachings as $teaching) {
                    $startTime = \Carbon\Carbon::parse($teaching->start_time);
                    $endTime = \Carbon\Carbon::parse($teaching->end_time);
                    $hours = $endTime->diffInMinutes($startTime) / 60;

                    $majorType = $teaching->class->major->major_type ?? 'N';
                    $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        $hasRegularProject = true;

                        if ($classType === 'LECTURE') {
                            $regularLectureHoursSum += $hours;
                        } else {
                            $regularLabHoursSum += $hours;
                        }

                        $regularAttendances->push([
                            'type' => 'regular',
                            'data' => $teaching,
                            'hours' => $hours,
                            'class_type' => $classType
                        ]);
                    } else {
                        $hasSpecialProject = true;

                        if ($classType === 'LECTURE') {
                            $specialLectureHoursSum += $hours;
                        } else {
                            $specialLabHoursSum += $hours;
                        }

                        $specialAttendances->push([
                            'type' => 'regular',
                            'data' => $teaching,
                            'hours' => $hours,
                            'class_type' => $classType
                        ]);
                    }

                    $allAttendances->push([
                        'type' => 'regular',
                        'section' => $section,
                        'date' => $teaching->start_time,
                        'data' => $teaching,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            $extraAttendances = ExtraAttendances::with(['classes.course.subjects', 'classes.major'])
                ->where('student_id', $student->id)
                ->where('approve_status', 'A')
                ->whereYear('start_work', $selectedDate->year)
                ->whereMonth('start_work', $selectedDate->month)
                ->get()
                ->groupBy('class_id');

            foreach ($extraAttendances as $classId => $extras) {
                foreach ($extras as $extra) {
                    $hours = $extra->duration / 60;
                    $class = $extra->classes;

                    $majorType = $class && $class->major ? $class->major->major_type : 'N';
                    $classType = $extra->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        $hasRegularProject = true;

                        if ($classType === 'LECTURE') {
                            $regularLectureHoursSum += $hours;
                        } else {
                            $regularLabHoursSum += $hours;
                        }

                        $regularAttendances->push([
                            'type' => 'special',
                            'data' => $extra,
                            'hours' => $hours,
                            'class_type' => $classType
                        ]);
                    } else {
                        $hasSpecialProject = true;

                        if ($classType === 'LECTURE') {
                            $specialLectureHoursSum += $hours;
                        } else {
                            $specialLabHoursSum += $hours;
                        }

                        $specialAttendances->push([
                            'type' => 'special',
                            'data' => $extra,
                            'hours' => $hours,
                            'class_type' => $classType
                        ]);
                    }

                    $allAttendances->push([
                        'type' => 'special',
                        'section' => $class ? $class->section_num : 'N/A',
                        'date' => $extra->start_work,
                        'data' => $extra,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            // ดึงอัตราค่าตอบแทน
            $regularRate = $this->getCompensationRate('regular', 'LECTURE');
            $specialRate = $this->getCompensationRate('special', 'LECTURE');

            // คำนวณค่าตอบแทน
            $regularPay = $regularLectureHoursSum + $regularLabHoursSum;
            $regularPay = $regularPay * $regularRate;

            $specialPay = $specialLectureHoursSum + $specialLabHoursSum;
            $specialPay = $specialPay * $specialRate;

            // ตรวจสอบว่าเป็นผู้ช่วยสอนระดับบัณฑิตศึกษาและสอนในโครงการพิเศษหรือไม่
            $isGraduate = in_array($student->degree_level, ['master', 'doctoral', 'graduate']);
            $isFixedPayment = $isGraduate && $hasSpecialProject;

            if ($isFixedPayment) {
                $fixedAmount = $this->getFixedCompensationRate('special', $student->degree_level);
                $specialPay = $fixedAmount ?? 4000; // ค่าเริ่มต้น 4,000 บาท
            }

            $totalPay = $regularPay + $specialPay;

            $attendancesBySection = $allAttendances->sortBy('date')->groupBy('section');

            $compensationRates = [
                'regularLecture' => $regularRate,
                'specialLecture' => $specialRate
            ];

            $pdf = PDF::loadView('exports.detailPDF', compact(
                'student',
                'semester',
                'attendancesBySection',
                'selectedYearMonth',
                'monthText',
                'year',
                'compensationRates',
                'headName',
                'formattedDate',
                'teacherName',
                'teacherPosition',
                'teacherDegree',
                'teacherFullTitle',
                'hasRegularProject',
                'hasSpecialProject',
                'isFixedPayment',
                'fixedAmount',
                'specialPay',
                'regularAttendances',
                'specialAttendances',
                'regularLectureHoursSum',
                'regularLabHoursSum',
                'specialLectureHoursSum',
                'specialLabHoursSum'
            ));

            $pdf->setPaper('A4');
            $fileName = 'TA-Compensation-' . $student->student_id . '-' . $selectedYearMonth . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
        }
    }

    private function convertNumberToThaiBaht($number)
    {
        $numberText = number_format($number, 2, '.', '');
        $textBaht = \App\Helpers\ThaiNumberHelper::convertToText($numberText);
        return $textBaht . 'ถ้วน';
    }

    public function exportResultPDF($id)
    {
        try {
            $selectedYearMonth = request('month');

            $ta = CourseTas::with(['student', 'course.semesters', 'course.teachers'])
                ->whereHas('student', function ($query) use ($id) {
                    $query->where('id', $id);
                })
                ->first();

            if (!$ta) {
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอน');
            }

            $student = $ta->student;
            $semester = $ta->course->semesters;

            $teacherName = $ta->course->teachers->name ?? 'อาจารย์ผู้สอน';
            $teacherPosition = $ta->course->teachers->position ?? '';
            $teacherDegree = $ta->course->teachers->degree ?? '';
            $teacherFullTitle = trim($teacherPosition . ' ' . $teacherDegree . '.' . ' ' . $teacherName);

            $headName = 'ผศ. ดร.คำรณ สุนัติ';

            $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);

            $currentDate = \Carbon\Carbon::now();
            $thaiMonth = $currentDate->locale('th')->monthName;
            $thaiYear = $currentDate->year + 543;
            $formattedDate = [
                'day' => $currentDate->day,
                'month' => $thaiMonth,
                'year' => $thaiYear
            ];

            $monthText = $selectedDate->locale('th')->monthName . ' ' . ($selectedDate->year + 543);
            $year = $selectedDate->year + 543;

            $allAttendances = collect();

            $hasRegularProject = false;
            $hasSpecialProject = false;

            $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects', 'class.major'])
                ->where('class_id', 'LIKE', $ta->course_id . '%')
                ->whereYear('start_time', $selectedDate->year)
                ->whereMonth('start_time', $selectedDate->month)
                ->whereHas('attendance', function ($query) {
                    $query->where('approve_status', 'A');
                })
                ->get()
                ->groupBy(function ($teaching) {
                    return $teaching->class->section_num;
                });

            foreach ($teachings as $section => $sectionTeachings) {
                foreach ($sectionTeachings as $teaching) {
                    $startTime = \Carbon\Carbon::parse($teaching->start_time);
                    $endTime = \Carbon\Carbon::parse($teaching->end_time);
                    $hours = $endTime->diffInMinutes($startTime) / 60;

                    $majorType = $teaching->class->major->major_type ?? 'N';
                    $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        $hasRegularProject = true;
                    } else {
                        $hasSpecialProject = true;
                    }

                    $allAttendances->push([
                        'type' => 'regular',
                        'section' => $section,
                        'date' => $teaching->start_time,
                        'data' => $teaching,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            $extraAttendances = ExtraAttendances::with(['classes.course.subjects', 'classes.major'])
                ->where('student_id', $student->id)
                ->where('approve_status', 'A')
                ->whereYear('start_work', $selectedDate->year)
                ->whereMonth('start_work', $selectedDate->month)
                ->get()
                ->groupBy('class_id');

            foreach ($extraAttendances as $classId => $extras) {
                foreach ($extras as $extra) {
                    $hours = $extra->duration / 60;
                    $class = $extra->classes;

                    $majorType = $class && $class->major ? $class->major->major_type : 'N';
                    $classType = $extra->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        $hasRegularProject = true;
                    } else {
                        $hasSpecialProject = true;
                    }

                    $allAttendances->push([
                        'type' => 'special',
                        'section' => $class ? $class->section_num : 'N/A',
                        'date' => $extra->start_work,
                        'data' => $extra,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            // ดึงอัตราค่าตอบแทน
            $regularRate = $this->getCompensationRate('regular', 'LECTURE');
            $specialRate = $this->getCompensationRate('special', 'LECTURE');

            // คำนวณค่าตอบแทน
            $regularPay = $allAttendances->where('teaching_type', 'regular')->sum('hours') * $regularRate;
            $specialPay = $allAttendances->where('teaching_type', 'special')->sum('hours') * $specialRate;

            $isGraduate = in_array($student->degree_level, ['master', 'doctoral', 'graduate']);
            $isFixedPayment = $isGraduate && $hasSpecialProject;

            // กำหนดค่าเริ่มต้นให้ $fixedAmount
            $fixedAmount = null;

            if ($isFixedPayment) {
                $fixedAmount = $this->getFixedCompensationRate('special', $student->degree_level);
                $specialPay = $fixedAmount ?? 4000; // ค่าเริ่มต้น 4,000 บาท
            }

            $totalPay = $regularPay + $specialPay;

            $attendancesBySection = $allAttendances->sortBy('date')->groupBy('section');

            $compensationRates = [
                'regularLecture' => $regularRate,
                'specialLecture' => $specialRate
            ];

            if (!isset($specialPay)) {
                $specialPay = $allAttendances->where('teaching_type', 'special')->sum('hours') * $specialRate;
            }

            $pdf = PDF::loadView('exports.resultPDF', compact(
                'student',
                'semester',
                'attendancesBySection',
                'selectedYearMonth',
                'monthText',
                'year',
                'compensationRates',
                'headName',
                'formattedDate',
                'teacherName',
                'teacherPosition',
                'teacherDegree',
                'teacherFullTitle',
                'hasRegularProject',
                'hasSpecialProject',
                'isFixedPayment',
                'fixedAmount',
                'specialPay'
            ));

            $pdf->setPaper('A4', 'landscape');
            $fileName = 'TA-Result-' . $student->student_id . '-' . $selectedYearMonth . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
        }
    }

    public function index()
    {
        $announces = Announce::with('semester')
            ->latest()
            ->paginate(5);

        return view('layouts.admin.index', compact('announces'))
            ->with('i', (request()->input('page', 1) - 1) * 5);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $semesters = Semesters::orderBy('year', 'desc')
            ->orderBy('semesters', 'desc')
            ->get();
        return view('layouts.admin.create', compact('semesters'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'semester_id' => 'required|exists:semesters,semester_id',
        ]);

        Announce::create([
            'title' => $request->title,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()
            ->route('announces.index')
            ->with('success', 'ประกาศถูกสร้างเรียบร้อย');
    }

    /**
     * Display the specified resource.
     */
    public function show(Announce $announce)
    {
        return view('layouts.admin.show', compact('announce'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Announce $announce)
    {
        $semesters = Semesters::orderBy('year', 'desc')
            ->orderBy('semesters', 'desc')
            ->get();

        return view('layouts.admin.edit', compact('announce', 'semesters'));
    }

    public function update(Request $request, Announce $announce)
    {
        $request->validate([
            'title' => 'required',
            'description' => 'required',
            'semester_id' => 'required|exists:semesters,semester_id',
        ]);

        $announce->update([
            'title' => $request->title,
            'description' => $request->description,
            'semester_id' => $request->semester_id,
            'is_active' => $request->has('is_active')
        ]);

        return redirect()
            ->route('announces.index')
            ->with('success', 'ประกาศถูกอัปเดตเรียบร้อย');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Announce $announce)
    {
        $announce->delete();
        return redirect()
            ->route('announces.index')
            ->with('success', 'announce deleted successfully');
    }

    public function taRequests()
    {
        try {
            $requests = TeacherRequest::with([
                'teacher:teacher_id,name,email',
                'course.subjects',
                'details.students.courseTa.student'
            ])
                ->latest()
                ->get();

            return view('layouts.admin.ta-requests.index', compact('requests'));
        } catch (\Exception $e) {
            Log::error('Error fetching TA requests: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูล');
        }
    }

    public function processTARequest(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:A,R',
            'comment' => 'nullable|string'
        ]);

        try {
            DB::beginTransaction();

            $taRequest = TeacherRequest::with(['details.students.courseTa'])
                ->findOrFail($id);

            if ($taRequest->status !== 'W') {
                return back()->with('error', 'คำร้องนี้ได้รับการดำเนินการแล้ว');
            }

            $taRequest->update([
                'status' => $validated['status'],
                'admin_processed_at' => now(),
                'admin_user_id' => Auth::id(),
                'admin_comment' => $validated['comment'] ?? null
            ]);

            foreach ($taRequest->details as $detail) {
                foreach ($detail->students as $student) {
                    // หา courseTaClasses ที่มีอยู่แล้ว
                    $courseTaClasses = CourseTaClasses::where('course_ta_id', $student->course_ta_id)->get();

                    foreach ($courseTaClasses as $courseTaClass) {
                        Requests::updateOrCreate(
                            [
                                'course_ta_class_id' => $courseTaClass->id
                            ],
                            [
                                'status' => $validated['status'],
                                'comment' => $validated['comment'] ?? null,
                                'approved_at' => now(),
                            ]
                        );
                    }
                }
            }

            DB::commit();
            $statusText = $validated['status'] === 'A' ? 'อนุมัติ' : 'ปฏิเสธ';
            return back()->with('success', "คำร้องได้รับการ{$statusText}เรียบร้อยแล้ว");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error processing TA request: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการดำเนินการ: ' . $e->getMessage());
        }
    }




    public function exportFromTemplate($id, Request $request)
    {
        try {
            $selectedYearMonth = $request->input('month');

            $ta = CourseTas::with(['student', 'course.semesters', 'course.teachers', 'course.subjects'])
                ->whereHas('student', function ($query) use ($id) {
                    $query->where('id', $id);
                })
                ->first();

            if (!$ta) {
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอน');
            }

            $student = $ta->student;
            $semester = $ta->course->semesters;
            $teacherName = $ta->course->teachers->name ?? '';
            $teacherPosition = $ta->course->teachers->position ?? '';
            $teacherDegree = $ta->course->teachers->degree ?? '';
            $teacherFullTitle = trim($teacherPosition . ' ' . $teacherDegree . '.' . ' ' . $teacherName);
            $subjectName = $ta->course->subjects->subject_id . ' ' . $ta->course->subjects->name_en;

            $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);

            $allAttendances = collect();
            $regularLectureHours = 0;
            $regularLabHours = 0;
            $specialLectureHours = 0;
            $specialLabHours = 0;

            $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects', 'class.major'])
                ->where('class_id', 'LIKE', $ta->course_id . '%')
                ->whereYear('start_time', $selectedDate->year)
                ->whereMonth('start_time', $selectedDate->month)
                ->whereHas('attendance', function ($query) {
                    $query->where('approve_status', 'A');
                })
                ->get()
                ->groupBy(function ($teaching) {
                    return $teaching->class->section_num;
                });

            foreach ($teachings as $section => $sectionTeachings) {
                foreach ($sectionTeachings as $teaching) {
                    $startTime = \Carbon\Carbon::parse($teaching->start_time);
                    $endTime = \Carbon\Carbon::parse($teaching->end_time);
                    $hours = $endTime->diffInMinutes($startTime) / 60;

                    $majorType = $teaching->class->major->major_type ?? 'N';
                    $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        if ($classType === 'LECTURE') {
                            $regularLectureHours += $hours;
                        } else {
                            $regularLabHours += $hours;
                        }
                    } else { // S
                        if ($classType === 'LECTURE') {
                            $specialLectureHours += $hours;
                        } else {
                            $specialLabHours += $hours;
                        }
                    }

                    $allAttendances->push([
                        'type' => 'regular',
                        'section' => $section,
                        'date' => $teaching->start_time,
                        'data' => $teaching,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            $extraAttendances = ExtraAttendances::with(['classes.course.subjects', 'classes.major'])
                ->where('student_id', $student->id)
                ->where('approve_status', 'A')
                ->whereYear('start_work', $selectedDate->year)
                ->whereMonth('start_work', $selectedDate->month)
                ->get()
                ->groupBy('class_id');

            foreach ($extraAttendances as $classId => $extras) {
                foreach ($extras as $extra) {
                    $hours = $extra->duration / 60;
                    $class = $extra->classes;

                    $majorType = $class && $class->major ? $class->major->major_type : 'N';
                    $classType = $extra->class_type === 'L' ? 'LAB' : 'LECTURE';

                    if ($majorType === 'N') {
                        if ($classType === 'LECTURE') {
                            $regularLectureHours += $hours;
                        } else {
                            $regularLabHours += $hours;
                        }
                    } else { // S
                        if ($classType === 'LECTURE') {
                            $specialLectureHours += $hours;
                        } else {
                            $specialLabHours += $hours;
                        }
                    }

                    $allAttendances->push([
                        'type' => 'special',
                        'section' => $class ? $class->section_num : 'N/A',
                        'date' => $extra->start_work,
                        'data' => $extra,
                        'hours' => $hours,
                        'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                        'class_type' => $classType
                    ]);
                }
            }

            // ดึงอัตราค่าตอบแทน
            $regularLectureRate = $this->getCompensationRate('regular', 'LECTURE');
            $regularLabRate = $this->getCompensationRate('regular', 'LAB');
            $specialLectureRate = $this->getCompensationRate('special', 'LECTURE');
            $specialLabRate = $this->getCompensationRate('special', 'LAB');

            // คำนวณค่าตอบแทน
            $regularLecturePay = $regularLectureHours * $regularLectureRate;
            $regularLabPay = $regularLabHours * $regularLabRate;
            $specialLecturePay = $specialLectureHours * $specialLectureRate;
            $specialLabPay = $specialLabHours * $specialLabRate;

            $regularPay = $regularLecturePay + $regularLabPay;
            $specialPay = $specialLecturePay + $specialLabPay;

            // ตรวจสอบว่าเป็นผู้ช่วยสอนระดับบัณฑิตศึกษาและสอนในโครงการพิเศษหรือไม่
            $isGraduate = in_array($student->degree_level, ['master', 'doctoral', 'graduate']);
            $isFixedPayment = $isGraduate && ($specialLectureHours > 0 || $specialLabHours > 0);

            if ($isFixedPayment) {
                $fixedAmount = $this->getFixedCompensationRate('special', $student->degree_level);
                $specialPay = $fixedAmount ?? 4000; // ค่าเริ่มต้น 4,000 บาท
            }

            $totalPay = $regularPay + $specialPay;

            $regularAttendances = $allAttendances->filter(function ($attendance) {
                return $attendance['teaching_type'] === 'regular';
            })->sortBy('date')->values();

            $specialAttendances = $allAttendances->filter(function ($attendance) {
                return $attendance['teaching_type'] === 'special';
            })->sortBy('date')->values();

            $currentDate = \Carbon\Carbon::now();
            $thaiMonth = $currentDate->locale('th')->monthName;
            $thaiYear = $currentDate->year + 543;

            $semesterValue = $semester->semesters ?? '';
            if ($semesterValue == '1') {
                $semesterText = "ภาคการศึกษา (/) ต้น     ( ) ปลาย     ( ) ฤดูร้อน";
            } elseif ($semesterValue == '2') {
                $semesterText = "ภาคการศึกษา ( ) ต้น     (/) ปลาย     ( ) ฤดูร้อน";
            } else {
                $semesterText = "ภาคการศึกษา ( ) ต้น     ( ) ปลาย     (/) ฤดูร้อน";
            }

            $yearText = "ปีการศึกษา " . ($semester->year + 543);

            $date = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);
            $monthName = $date->locale('th')->monthName;
            $year = $date->year + 543;

            $startOfMonth = $date->copy()->startOfMonth()->format('d');
            $endOfMonth = $date->copy()->endOfMonth()->format('d');

            $dateRangeText = "{$startOfMonth} {$monthName} {$year} - {$endOfMonth} {$monthName} {$year}";

            $templatePath = public_path('storage/templates/template-1.xls');

            if (!file_exists($templatePath)) {
                return back()->with('error', 'ไม่พบไฟล์ Template Excel');
            }


            $spreadsheet = IOFactory::load($templatePath);

            $exportDir = storage_path('app/public/exports');
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }

            // สร้างตัวแปร $writer ก่อนใช้งาน
            $writer = IOFactory::createWriter($spreadsheet, 'Xls');

            $fileName = 'TA-Compensation-' . $student->student_id . '-' . $selectedYearMonth . '.xls';
            $filePath = $exportDir . '/' . $fileName;

            $writer->save($filePath);
            $regularSheet = $spreadsheet->getSheet(0);
            $regularSheet->setTitle('ปกติ');

            $regularSheet->setCellValue('A1', 'แบบใบเบิกค่าตอบแทนผู้ช่วยสอนและผู้ช่วยปฏิบัติงาน');
            $regularSheet->setCellValue('A2', 'วิทยาลัยการคอมพิวเตอร์ มหาวิทยาลัยขอนแก่น');
            $regularSheet->setCellValue('A3', $semesterText);
            $regularSheet->setCellValue('A4', 'ประจำเดือน ' . $dateRangeText);

            $regularSheet->setCellValue('C6', '( ) โครงการปกติ');
            $regularSheet->setCellValue('F6', '(/) โครงการพิเศษ');

            if ($regularAttendances->isNotEmpty()) {
                $row = 9;
                $rowNum = 1;

                foreach ($regularAttendances as $attendance) {
                    $isRegular = $attendance['type'] === 'regular';

                    $dateObj = $isRegular
                        ? \Carbon\Carbon::parse($attendance['data']->start_time)
                        : \Carbon\Carbon::parse($attendance['data']->start_work);
                    $date = $dateObj->format('d-m-Y');

                    $courseId = $isRegular
                        ? ($attendance['data']->class->course->subjects->subject_id ?? '-')
                        : ($attendance['data']->classes->course->subjects->subject_id ?? '-');

                    $time = $isRegular
                        ? \Carbon\Carbon::parse($attendance['data']->start_time)->format('H:i') . '-' .
                        \Carbon\Carbon::parse($attendance['data']->end_time)->format('H:i')
                        : \Carbon\Carbon::parse($attendance['data']->start_work)->format('H:i') . '-' .
                        \Carbon\Carbon::parse($attendance['data']->start_work)
                            ->addMinutes($attendance['data']->duration)->format('H:i');

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

                    $regularSheet->setCellValue('A' . $row, $rowNum);

                    if ($rowNum === 1) {
                        $regularSheet->setCellValue('B' . $row, $student->name);
                        $regularSheet->setCellValue('C' . $row, 'ป.ตรี');
                    }

                    $regularSheet->setCellValue('D' . $row, $date);
                    $regularSheet->setCellValue('E' . $row, $courseId);
                    $regularSheet->setCellValue('F' . $row, $time);

                    if ($lectureHours > 0) {
                        $regularSheet->setCellValue('G' . $row, number_format($lectureHours, 2));
                    }

                    if ($labHours > 0) {
                        $regularSheet->setCellValue('H' . $row, number_format($labHours, 2));
                    }

                    $regularSheet->setCellValue('I' . $row, $note);

                    $row++;
                    $rowNum++;

                    if ($rowNum > 5) {
                        break;
                    }
                }
            }

            $totalRegularRow = 14;
            $regularSheet->setCellValue('G' . $totalRegularRow, number_format($regularLectureHours, 2));
            $regularSheet->setCellValue('H' . $totalRegularRow, number_format($regularLabHours, 2));

            $rateRow = 17;
            $regularSheet->setCellValue('I' . $rateRow, number_format($regularPay, 2));
            $regularSheet->setCellValue('F20', '(' . \App\Helpers\ThaiNumberHelper::convertToText($regularPay) . ')');

            $totalRow = 20;

            $signatureRow = 26;
            $regularSheet->setCellValue('A' . $signatureRow, '(' . $student->name . ')');
            $regularSheet->setCellValue('D' . $signatureRow, '(' . $teacherFullTitle . ')');
            $regularSheet->setCellValue('G' . $signatureRow, '(ผศ. ดร.คำรณ สุนัติ)');

            if ($specialAttendances->isNotEmpty() || $specialPay > 0) {
                $specialSheet = clone $spreadsheet->getSheet(0);
                $specialSheet->setTitle('พิเศษ');
                $spreadsheet->addSheet($specialSheet);

                $regularSheet->setCellValue('C6', '(/) โครงการปกติ');
                $regularSheet->setCellValue('F6', '( ) โครงการพิเศษ');

                for ($row = 9; $row <= 13; $row++) {
                    for ($col = 'A'; $col <= 'I'; $col++) {
                        $specialSheet->setCellValue($col . $row, '');
                    }
                }

                if ($specialAttendances->isNotEmpty()) {
                    $row = 9;
                    $rowNum = 1;

                    foreach ($specialAttendances as $attendance) {
                        $isRegular = $attendance['type'] === 'regular';

                        $dateObj = $isRegular
                            ? \Carbon\Carbon::parse($attendance['data']->start_time)
                            : \Carbon\Carbon::parse($attendance['data']->start_work);
                        $date = $dateObj->format('d-m-Y');

                        $courseId = $isRegular
                            ? ($attendance['data']->class->course->subjects->subject_id ?? '-')
                            : ($attendance['data']->classes->course->subjects->subject_id ?? '-');

                        $time = $isRegular
                            ? \Carbon\Carbon::parse($attendance['data']->start_time)->format('H:i') . '-' .
                            \Carbon\Carbon::parse($attendance['data']->end_time)->format('H:i')
                            : \Carbon\Carbon::parse($attendance['data']->start_work)->format('H:i') . '-' .
                            \Carbon\Carbon::parse($attendance['data']->start_work)
                                ->addMinutes($attendance['data']->duration)->format('H:i');

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

                        $specialSheet->setCellValue('A' . $row, $rowNum);

                        if ($rowNum === 1) {
                            $specialSheet->setCellValue('B' . $row, $student->name);
                            $specialSheet->setCellValue('C' . $row, 'ป.ตรี');
                        }

                        $specialSheet->setCellValue('D' . $row, $date);
                        $specialSheet->setCellValue('E' . $row, $courseId);
                        $specialSheet->setCellValue('F' . $row, $time);

                        $specialSheet->setCellValue('F20', '(' . \App\Helpers\ThaiNumberHelper::convertToText($specialPay) . ')');


                        if ($lectureHours > 0) {
                            $specialSheet->setCellValue('G' . $row, number_format($lectureHours, 2));
                        }

                        if ($labHours > 0) {
                            $specialSheet->setCellValue('H' . $row, number_format($labHours, 2));
                        }

                        $specialSheet->setCellValue('I' . $row, $note);

                        $row++;
                        $rowNum++;

                        if ($rowNum > 5) {
                            break;
                        }
                    }
                }

                $totalSpecialRow = 14;
                $specialSheet->setCellValue('G' . $totalSpecialRow, number_format($specialLectureHours, 2));
                $specialSheet->setCellValue('H' . $totalSpecialRow, number_format($specialLabHours, 2));

                $rateRow = 17;
                $specialSheet->setCellValue('I' . $rateRow, number_format($specialPay, 2));

                $totalRow = 20;

                $signatureRow = 26;
                $specialSheet->setCellValue('A' . $signatureRow, '(' . $student->name . ')');
                $specialSheet->setCellValue('D' . $signatureRow, '(' . $teacherFullTitle . ')');
                $specialSheet->setCellValue('G' . $signatureRow, '(ผศ. ดร.คำรณ สุนัติ)');
            }

            $writer = IOFactory::createWriter($spreadsheet, 'Xls');
            $fileName = 'TA-Compensation-' . $student->student_id . '-' . $selectedYearMonth . '.xls';
            $filePath = storage_path('app/public/exports/' . $fileName);

            $writer->save($filePath);

            return response()->download($filePath)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Excel Export Error: ' . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้างไฟล์ Excel: ' . $e->getMessage());
        }
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
}
