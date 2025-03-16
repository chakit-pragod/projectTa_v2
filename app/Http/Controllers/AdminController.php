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
    Attendances,
    CourseBudget
};
use Barryvdh\DomPDF\Facade\Pdf;
use App\Exports\TaAttendanceExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\{Auth, DB, Log, Storage};
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Services\TDBMSyncService;


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

    // function for sync all data from api into database
    public function syncAllData()
    {
        try {
            set_time_limit(300);

            // ดึง TDBMSyncService จาก service container
            $syncService = app(TDBMSyncService::class);

            // เรียกใช้ syncAll method
            $result = $syncService->syncAll();

            if ($result) {
                return redirect()->back()->with('success', 'ซิงค์ข้อมูลทั้งหมดสำเร็จ');
            } else {
                return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการซิงค์ข้อมูล');
            }
        } catch (\Exception $e) {
            Log::error('Error syncing all data: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการซิงค์ข้อมูล: ' . $e->getMessage());
        }
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
                    'userSelectedSemester' => $userSelectedSemester,
                    'curriculums' => collect()
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
                    'curriculums', // เพิ่มความสัมพันธ์กับตาราง curriculums
                    'course_tas.student',
                    'course_tas.courseTaClasses.requests' => function ($query) {
                        $query->where('status', 'A')
                            ->whereNotNull('approved_at');
                    }
                ])
                ->get();

            // ดึงข้อมูลหลักสูตรทั้งหมดเพื่อใช้ในการกรอง
            $curriculums = \App\Models\Curriculums::orderBy('name_th')->get();

            Log::info('จำนวนรายวิชาที่มี TA: ' . $coursesWithTAs->count());

            return view('layouts.admin.taUsers', [
                'coursesWithTAs' => $coursesWithTAs,
                'semesters' => $semesters,
                'selectedSemester' => $selectedSemester,
                'userSelectedSemester' => $userSelectedSemester,
                'curriculums' => $curriculums
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

    /**
     * ดึงอัตราค่าตอบแทนจากฐานข้อมูล
     */
    private function getCompensationRate($teachingType, $classType, $degreeLevel = 'undergraduate')
    {
        // แปลงค่า degree_level ให้ตรงกับที่เก็บในฐานข้อมูล
        $mappedDegreeLevel = $degreeLevel;
        if (in_array($degreeLevel, ['master', 'doctoral'])) {
            $mappedDegreeLevel = 'graduate';
        } else if (in_array($degreeLevel, ['bachelor', 'bachelor_degree'])) {
            $mappedDegreeLevel = 'undergraduate';
        }

        // ลองค้นหาในฐานข้อมูลก่อน
        $rate = CompensationRate::where('teaching_type', $teachingType)
            ->where('class_type', $classType)
            ->where('degree_level', $mappedDegreeLevel)  // ใช้ค่าที่แปลงแล้ว
            ->where('status', 'active')
            ->where('is_fixed_payment', false)
            ->first();

        // ถ้าพบข้อมูลในฐานข้อมูล ให้ใช้ค่าจากฐานข้อมูล
        if ($rate) {
            return $rate->rate_per_hour;
        }

        // กรณีไม่พบข้อมูลในฐานข้อมูล ให้ใช้ค่าเริ่มต้น
        if ($mappedDegreeLevel === 'undergraduate') {
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


    private function getFixedCompensationRate($teachingType, $degreeLevel)
    {
        // แปลงค่า degree_level ให้ตรงกับที่เก็บในฐานข้อมูล
        $mappedDegreeLevel = $degreeLevel;
        if (in_array($degreeLevel, ['master', 'doctoral'])) {
            $mappedDegreeLevel = 'graduate';
        } else if (in_array($degreeLevel, ['bachelor', 'bachelor_degree'])) {
            $mappedDegreeLevel = 'undergraduate';
        }

        // ค้นหาข้อมูลในฐานข้อมูล
        $rate = CompensationRate::where('teaching_type', $teachingType)
            ->where('degree_level', $mappedDegreeLevel)  // ใช้ค่าที่แปลงแล้ว
            ->where('status', 'active')
            ->where('is_fixed_payment', true)
            ->first();

        // ถ้าพบข้อมูลในฐานข้อมูล ให้ใช้ค่าจากฐานข้อมูล
        if ($rate) {
            return $rate->fixed_amount;
        }

        // ใช้ค่าเริ่มต้นถ้าไม่พบในฐานข้อมูล
        if ($mappedDegreeLevel === 'graduate') {
            if ($teachingType === 'special') {
                return 4000; // ผู้ช่วยสอน บัณฑิต ที่สอน ภาคพิเศษ เหมาจ่ายรายเดือน
            }
        }

        return null; // กรณีไม่ใช่ประเภทที่เหมาจ่าย
    }

    /**
     * ดึงชื่อเต็มของอาจารย์ผู้สอน
     */
    private function getTeacherFullTitle($course)
    {
        if (!$course || !$course->teachers) {
            return 'อาจารย์ผู้สอน';
        }

        $teacherName = $course->teachers->name ?? 'อาจารย์ผู้สอน';
        $teacherPosition = $course->teachers->position ?? '';
        $teacherDegree = $course->teachers->degree ?? '';

        return trim($teacherPosition . ' ' . $teacherDegree . '.' . ' ' . $teacherName);
    }

    public function taDetail($student_id)
    {
        try {
            $student = Students::find($student_id);
            $course_id = request('course_id');

            // ดึงข้อมูลภาคการศึกษาที่เจ้าหน้าที่กำหนดให้แสดง
            $setting = DB::table('setting_semesters')->where('key', 'user_active_semester_id')->first();
            $userSelectedSemesterId = $setting ? $setting->value : null;

            if (!$userSelectedSemesterId) {
                // ถ้าไม่พบการตั้งค่า ใช้ภาคการศึกษาล่าสุด
                $semester = Semesters::orderBy('year', 'desc')
                    ->orderBy('semesters', 'desc')
                    ->first();
            } else {
                $semester = Semesters::find($userSelectedSemesterId);
            }

            if (!$semester) {
                return back()->with('error', 'ไม่พบข้อมูลภาคการศึกษาที่กำหนดให้แสดง');
            }

            $ta = CourseTas::where('student_id', $student_id)
                ->where('course_id', $course_id)
                ->first();

            if (!$ta) {
                // Create a proper error message
                Log::warning("No CourseTas record found for student_id=$student_id, course_id=$course_id");
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอนสำหรับรายวิชานี้');
            }

            $course = $ta->course;
            $student = $ta->student;

            // ตรวจสอบว่าคอร์สอยู่ในภาคการศึกษาที่กำหนดหรือไม่
            if ($course->semester_id != $semester->semester_id) {
                return back()->with('error', 'รายวิชานี้ไม่อยู่ในภาคการศึกษาที่กำหนดให้แสดง');
            }

            $start = \Carbon\Carbon::parse($semester->start_date)->startOfDay();
            $end = \Carbon\Carbon::parse($semester->end_date)->endOfDay();

            // if (!\Carbon\Carbon::now()->between($start, $end)) {
            //     return back()->with('error', 'ไม่อยู่ในช่วงภาคการศึกษาปัจจุบัน');
            // }

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

                $extraTeachings = ExtraTeaching::with([
                    'class.major',
                    'attendance' => function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                            ->where('approve_status', 'a');
                    }
                ])
                    ->whereIn('extra_class_id', $extraTeachingIds)
                    ->whereBetween('class_date', [$startDate, $endDate])
                    ->whereHas('attendance', function ($query) use ($student) {
                        $query->where('student_id', $student->id)
                            ->where('approve_status', 'a');
                    })
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
            $extraAttendances = ExtraAttendances::with(['classes.course.subjects', 'classes.major'])
                ->where('student_id', $student->id)
                ->where('approve_status', 'A') // ตรวจสอบว่าใช้ 'A' หรือ 'a'
                ->whereYear('start_work', $selectedDate->year)
                ->whereMonth('start_work', $selectedDate->month)
                ->get();

            // ประมวลผลการสอนพิเศษ
            foreach ($extraAttendances as $extraAttendance) {
                $hours = $extraAttendance->duration / 60;
                $majorType = $extraAttendance->classes && $extraAttendance->classes->major
                    ? $extraAttendance->classes->major->major_type
                    : 'N';
                $classType = $extraAttendance->class_type === 'L' ? 'LAB' : 'LECTURE';

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

                // เพิ่มข้อมูลในคอลเลกชัน allAttendances
                $allAttendances->push([
                    'type' => 'special', // หรือใช้ชื่อที่ชัดเจนกว่า เช่น 'extra_attendance'
                    'section' => $extraAttendance->classes ? $extraAttendance->classes->section_num : 'N/A',
                    'date' => $extraAttendance->start_work,
                    'data' => $extraAttendance,
                    'hours' => $hours,
                    'teaching_type' => $majorType === 'N' ? 'regular' : 'special',
                    'class_type' => $classType
                ]);
            }
            // บันทึก log สรุปชั่วโมงทั้งหมด
            Log::debug('Hour summary:', [
                'regularLecture' => $regularLectureHours,
                'regularLab' => $regularLabHours,
                'specialLecture' => $specialLectureHours,
                'specialLab' => $specialLabHours,
                'total' => $regularLectureHours + $regularLabHours + $specialLectureHours + $specialLabHours
            ]);

            $regularLectureRate = $this->getCompensationRate('regular', 'LECTURE', $student->degree_level ?? 'undergraduate');
            $regularLabRate = $this->getCompensationRate('regular', 'LAB', $student->degree_level ?? 'undergraduate');
            $specialLectureRate = $this->getCompensationRate('special', 'LECTURE', $student->degree_level ?? 'undergraduate');
            $specialLabRate = $this->getCompensationRate('special', 'LAB', $student->degree_level ?? 'undergraduate');

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

            // ในเมธอด taDetail ส่วนที่จะต้องแก้ไข

            $degreeLevel = $student->degree_level ?? 'undergraduate';
            $isGraduate = in_array($degreeLevel, ['master', 'doctoral', 'graduate']);
            $isFixedPayment = false;
            $fixedAmount = null;

            // ตรวจสอบว่าเป็นผู้ช่วยสอนระดับบัณฑิตศึกษาและสอนในโครงการพิเศษหรือไม่
            if ($isGraduate && ($compensation['specialLectureHours'] > 0 || $compensation['specialLabHours'] > 0)) {
                // ดึงอัตราเหมาจ่ายจากฐานข้อมูล
                $fixedAmount = $this->getFixedCompensationRate('special', $degreeLevel);

                if ($fixedAmount) {
                    $isFixedPayment = true;
                    // ปรับค่าตอบแทนให้เป็นแบบเหมาจ่าย
                    $compensation['specialPay'] = $fixedAmount;
                    $totalPay = $compensation['regularPay'] + $fixedAmount;
                    $compensation['totalPay'] = $totalPay;

                    Log::debug("Using fixed payment: {$fixedAmount}");
                } else {
                    Log::debug("No fixed compensation rate found, using hourly rate");
                }
            }

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
                'fixedAmount',
                'regularLectureRate',
                'regularLabRate',
                'specialLectureRate',
                'specialLabRate',
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


    // version 3
    public function exportTaDetailPDF($id)
    {
        try {
            $selectedYearMonth = request('month');
            $course_id = request('course_id');

            if (!$course_id) {
                return back()->with('error', 'กรุณาระบุรหัสรายวิชา');
            }

            // Get student data
            $student = Students::findOrFail($id);

            // Find the course_ta record
            $ta = CourseTas::where('student_id', $id)
                ->where('course_id', $course_id)
                ->first();

            if (!$ta) {
                Log::warning("No CourseTas record found for student_id=$id, course_id=$course_id");
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอนสำหรับรายวิชานี้');
            }

            $course = $ta->course;
            $semester = $course->semesters;

            // Get attendance data using the same method used in the web view
            $attendanceData = $this->getAttendancesData($id, $course_id, $selectedYearMonth);

            $data = [
                'student' => $student,
                'course' => $course,
                'semester' => $semester,
                'attendancesBySection' => $attendanceData['attendancesBySection'],
                'regularAttendances' => $attendanceData['regularAttendances'],
                'specialAttendances' => $attendanceData['specialAttendances'],
                'selectedYearMonth' => $selectedYearMonth,
                'monthText' => \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->locale('th')->monthName,
                'year' => \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->year + 543,
                'compensationRates' => [
                    'regularLecture' => $this->getCompensationRate('regular', 'LECTURE', $student->degree_level ?? 'undergraduate'),
                    'regularLab' => $this->getCompensationRate('regular', 'LAB', $student->degree_level ?? 'undergraduate'),
                    'specialLecture' => $this->getCompensationRate('special', 'LECTURE', $student->degree_level ?? 'undergraduate'),
                    'specialLab' => $this->getCompensationRate('special', 'LAB', $student->degree_level ?? 'undergraduate')
                ],
                'headName' => 'ผศ. ดร.คำรณ สุนัติ',
                'formattedDate' => [
                    'day' => \Carbon\Carbon::now()->day,
                    'month' => \Carbon\Carbon::now()->locale('th')->monthName,
                    'year' => \Carbon\Carbon::now()->year + 543
                ],
                'teacherFullTitle' => $this->getTeacherFullTitle($course),
                'hasRegularProject' => $attendanceData['hasRegularProject'],
                'hasSpecialProject' => $attendanceData['hasSpecialProject'],
                'regularLectureHoursSum' => $attendanceData['regularLectureHoursSum'],
                'regularLabHoursSum' => $attendanceData['regularLabHoursSum'],
                'specialLectureHoursSum' => $attendanceData['specialLectureHoursSum'],
                'specialLabHoursSum' => $attendanceData['specialLabHoursSum'],
                'isFixedPayment' => $attendanceData['isFixedPayment'],
                'fixedAmount' => $attendanceData['fixedAmount'],
                'specialPay' => $attendanceData['specialPay'],
                'regularPay' => $attendanceData['regularPay']
            ];

            // Get transaction data if it exists
            $transaction = CompensationTransaction::where('student_id', $id)
                ->where('course_id', $course_id)
                ->where('month_year', $selectedYearMonth)
                ->first();

            if ($transaction) {
                $data['transaction'] = $transaction;
                $data['actualRegularPay'] = $transaction->actual_amount * ($data['regularPay'] / ($data['regularPay'] + $data['specialPay']));
                $data['actualSpecialPay'] = $transaction->actual_amount * ($data['specialPay'] / ($data['regularPay'] + $data['specialPay']));
            }

            $pdf = PDF::loadView('exports.detailPDF', $data);
            $pdf->setPaper('A4');

            return $pdf->download('TA-Compensation-Detail-' . $student->student_id . '-' . $selectedYearMonth . '.pdf');
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้าง PDF: ' . $e->getMessage());
        }
    }

    private function convertNumberToThaiBaht($number)
    {
        $numberText = number_format($number, 2, '.', '');
        $textBaht = \App\Helpers\ThaiNumberHelper::convertToText($numberText);
        return $textBaht . 'ถ้วน';
    }


    // version 2
    public function exportResultPDF($id)
    {
        try {
            $selectedYearMonth = request('month');
            $course_id = request('course_id');

            if (!$course_id) {
                return back()->with('error', 'กรุณาระบุรหัสรายวิชา');
            }

            // Get student data
            $student = Students::findOrFail($id);

            // Find the course_ta record
            $ta = CourseTas::where('student_id', $id)
                ->where('course_id', $course_id)
                ->first();

            if (!$ta) {
                Log::warning("No CourseTas record found for student_id=$id, course_id=$course_id");
                return back()->with('error', 'ไม่พบข้อมูลผู้ช่วยสอนสำหรับรายวิชานี้');
            }

            $course = $ta->course;
            $semester = $ta->course->semesters;
            $teacherFullTitle = $this->getTeacherFullTitle($course);
            $headName = 'ผศ. ดร.คำรณ สุนัติ';

            // Get attendance data using the same method used in the web view
            $attendanceData = $this->getAttendancesData($id, $course_id, $selectedYearMonth);

            // Get transaction data if it exists
            $transaction = CompensationTransaction::where('student_id', $student->id)
                ->where('course_id', $ta->course_id)
                ->where('month_year', $selectedYearMonth)
                ->first();

            $actualRegularPay = $attendanceData['regularPay'];
            $actualSpecialPay = $attendanceData['specialPay'];

            if ($transaction) {
                $total = $attendanceData['regularPay'] + $attendanceData['specialPay'];
                if ($total > 0) {
                    $regularProportion = $attendanceData['regularPay'] / $total;
                    $specialProportion = $attendanceData['specialPay'] / $total;

                    $actualRegularPay = $transaction->actual_amount * $regularProportion;
                    $actualSpecialPay = $transaction->actual_amount * $specialProportion;
                }
            }

            $data = [
                'student' => $student,
                'course' => $course,
                'semester' => $semester,
                'attendancesBySection' => $attendanceData['attendancesBySection'],
                'selectedYearMonth' => $selectedYearMonth,
                'monthText' => \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->locale('th')->monthName,
                'year' => \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth)->year + 543,
                'compensationRates' => [
                    'regularLecture' => $this->getCompensationRate('regular', 'LECTURE', $student->degree_level ?? 'undergraduate'),
                    'regularLab' => $this->getCompensationRate('regular', 'LAB', $student->degree_level ?? 'undergraduate'),
                    'specialLecture' => $this->getCompensationRate('special', 'LECTURE', $student->degree_level ?? 'undergraduate'),
                    'specialLab' => $this->getCompensationRate('special', 'LAB', $student->degree_level ?? 'undergraduate')
                ],
                'headName' => $headName,
                'formattedDate' => [
                    'day' => \Carbon\Carbon::now()->day,
                    'month' => \Carbon\Carbon::now()->locale('th')->monthName,
                    'year' => \Carbon\Carbon::now()->year + 543
                ],
                'teacherFullTitle' => $teacherFullTitle,
                'hasRegularProject' => $attendanceData['hasRegularProject'],
                'hasSpecialProject' => $attendanceData['hasSpecialProject'],
                'isFixedPayment' => $attendanceData['isFixedPayment'],
                'fixedAmount' => $attendanceData['fixedAmount'],
                'specialPay' => $attendanceData['specialPay'],
                'regularPay' => $attendanceData['regularPay'],
                'transaction' => $transaction,
                'actualRegularPay' => $actualRegularPay,
                'actualSpecialPay' => $actualSpecialPay,
                'regularLectureHours' => $attendanceData['regularLectureHoursSum'],
                'regularLabHours' => $attendanceData['regularLabHoursSum'],
                'specialLectureHours' => $attendanceData['specialLectureHoursSum'],
                'specialLabHours' => $attendanceData['specialLabHoursSum'],
                'totalPay' => $attendanceData['regularPay'] + $attendanceData['specialPay'],
                'hourlySpecialPay' => ($attendanceData['specialLectureHoursSum'] * $this->getCompensationRate('special', 'LECTURE', $student->degree_level ?? 'undergraduate')) +
                    ($attendanceData['specialLabHoursSum'] * $this->getCompensationRate('special', 'LAB', $student->degree_level ?? 'undergraduate')),
                'fixedSpecialPay' => $attendanceData['isFixedPayment'] ? ($attendanceData['fixedAmount'] ?? 4000) : 0
            ];

            // คำนวณงบประมาณคงเหลือ
            $courseBudget = CourseBudget::where('course_id', $ta->course_id)->first();
            if ($courseBudget) {
                $data['remainingBudget'] = $courseBudget->remaining_budget;
            } else {
                // ถ้ายังไม่มีข้อมูลงบประมาณ
                $totalStudents = $course->classes->sum('enrolled_num');
                $totalBudget = $totalStudents * 300; // 300 บาทต่อคน
                $data['remainingBudget'] = $totalBudget;
            }

            // ตรวจสอบว่ามีข้อมูลการสอนในเดือนนี้หรือไม่
            if (!$attendanceData['hasRegularProject'] && !$attendanceData['hasSpecialProject']) {
                $data['noAttendanceData'] = true;
            }

            $pdf = PDF::loadView('exports.resultPDF', $data);
            $pdf->setPaper('A4', 'landscape');
            $fileName = 'TA-Result-' . $student->student_id . '-' . $selectedYearMonth . '.pdf';

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            Log::error('PDF Export Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
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

    /**
     * ดึงและคำนวณข้อมูลการสอนของผู้ช่วยสอนแยกตามประเภท
     *
     * @param int $studentId รหัสผู้ช่วยสอน
     * @param string $courseId รหัสรายวิชา
     * @param string $yearMonth ปี-เดือน (Y-m)
     * @return array ข้อมูลการสอนและชั่วโมงแยกตามประเภท
     */
    private function getAttendancesData($studentId, $courseId, $yearMonth)
    {
        $student = Students::findOrFail($studentId);
        $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $yearMonth);

        $allAttendances = collect();
        $regularAttendances = collect();
        $specialAttendances = collect();

        $hasRegularProject = false;
        $hasSpecialProject = false;

        $regularLectureHoursSum = 0;
        $regularLabHoursSum = 0;
        $specialLectureHoursSum = 0;
        $specialLabHoursSum = 0;

        // ดึงข้อมูลการสอนปกติ
        $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects', 'class.major'])
            ->where('class_id', 'LIKE', $courseId . '%')
            ->whereYear('start_time', $selectedDate->year)
            ->whereMonth('start_time', $selectedDate->month)
            ->whereHas('attendance', function ($query) use ($student) {
                $query->where('student_id', $student->id)
                    ->where('approve_status', 'A');
            })
            ->get();

        // จัดกลุ่มตาม section
        $groupedTeachings = $teachings->groupBy(function ($teaching) {
            return $teaching->class->section_num ?? 'ไม่ระบุ';
        });

        foreach ($groupedTeachings as $section => $sectionTeachings) {
            foreach ($sectionTeachings as $teaching) {
                $startTime = \Carbon\Carbon::parse($teaching->start_time);
                $endTime = \Carbon\Carbon::parse($teaching->end_time);
                $hours = $endTime->diffInMinutes($startTime) / 60;

                // ตรวจสอบว่ามีข้อมูล class และ major หรือไม่
                if (!$teaching->class || !$teaching->class->major) {
                    Log::warning("Teaching ID {$teaching->id} missing class or major data");
                    continue;
                }

                $majorType = $teaching->class->major->major_type ?? 'N';
                $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

                if ($majorType === 'N' || $majorType === '') {
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
                    'teaching_type' => $majorType === 'N' || $majorType === '' ? 'regular' : 'special',
                    'class_type' => $classType
                ]);
            }
        }

        // ดึงข้อมูลการสอนชดเชย (ExtraTeaching)
        $extraTeachingIds = Attendances::where('student_id', $student->id)
            ->where('is_extra', true)
            ->where('approve_status', 'A')
            ->whereNotNull('extra_teaching_id')
            ->pluck('extra_teaching_id')
            ->toArray();

        $extraTeachings = ExtraTeaching::with(['class.major', 'class.course.subjects'])
            ->whereIn('extra_class_id', $extraTeachingIds)
            ->whereBetween('class_date', [
                $selectedDate->startOfMonth()->format('Y-m-d'),
                $selectedDate->endOfMonth()->format('Y-m-d')
            ])
            ->get();

        foreach ($extraTeachings as $teaching) {
            $startTime = \Carbon\Carbon::parse($teaching->start_time);
            $endTime = \Carbon\Carbon::parse($teaching->end_time);
            $hours = $endTime->diffInMinutes($startTime) / 60;

            // ตรวจสอบว่ามีข้อมูล class และ major หรือไม่
            if (!$teaching->class || !$teaching->class->major) {
                Log::warning("ExtraTeaching ID {$teaching->extra_class_id} missing class or major data");
                continue;
            }

            $majorType = $teaching->class->major->major_type ?? 'N';
            $classType = $teaching->class_type === 'L' ? 'LAB' : 'LECTURE';

            if ($majorType === 'N' || $majorType === '') {
                $hasRegularProject = true;

                if ($classType === 'LECTURE') {
                    $regularLectureHoursSum += $hours;
                } else {
                    $regularLabHoursSum += $hours;
                }

                $regularAttendances->push([
                    'type' => 'extra',
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
                    'type' => 'extra',
                    'data' => $teaching,
                    'hours' => $hours,
                    'class_type' => $classType
                ]);
            }

            $allAttendances->push([
                'type' => 'extra',
                'section' => $teaching->class->section_num ?? 'ไม่ระบุ',
                'date' => $teaching->class_date . ' ' . $teaching->start_time,
                'data' => $teaching,
                'hours' => $hours,
                'teaching_type' => $majorType === 'N' || $majorType === '' ? 'regular' : 'special',
                'class_type' => $classType
            ]);
        }

        // ดึงข้อมูลการทำงานพิเศษ (ExtraAttendances)
        $extraAttendances = ExtraAttendances::with(['classes.course.subjects', 'classes.major'])
            ->where('student_id', $student->id)
            ->where('approve_status', 'A')
            ->whereYear('start_work', $selectedDate->year)
            ->whereMonth('start_work', $selectedDate->month)
            ->get();

        foreach ($extraAttendances as $attendance) {
            $hours = $attendance->duration / 60;

            // ตรวจสอบและป้องกันกรณีข้อมูลไม่ครบถ้วน
            if (!$attendance->classes || !$attendance->classes->major) {
                // กรณีไม่มีข้อมูล major เราถือว่าเป็นโครงการปกติ (N)
                Log::warning("ExtraAttendance ID {$attendance->id} missing classes or major data");
                $majorType = 'N';
                $section = 'ไม่ระบุ';
            } else {
                $majorType = $attendance->classes->major->major_type ?? 'N';
                $section = $attendance->classes->section_num ?? 'ไม่ระบุ';
            }

            $classType = $attendance->class_type === 'L' ? 'LAB' : 'LECTURE';

            if ($majorType === 'N' || $majorType === '') {
                $hasRegularProject = true;

                if ($classType === 'LECTURE') {
                    $regularLectureHoursSum += $hours;
                } else {
                    $regularLabHoursSum += $hours;
                }

                $regularAttendances->push([
                    'type' => 'special',
                    'data' => $attendance,
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
                    'data' => $attendance,
                    'hours' => $hours,
                    'class_type' => $classType
                ]);
            }

            $allAttendances->push([
                'type' => 'special',
                'section' => $section,
                'date' => $attendance->start_work,
                'data' => $attendance,
                'hours' => $hours,
                'teaching_type' => $majorType === 'N' || $majorType === '' ? 'regular' : 'special',
                'class_type' => $classType
            ]);
        }

        // เช็คว่าเป็นแบบเหมาจ่ายหรือไม่ (สำหรับนักศึกษาบัณฑิตที่สอนในโครงการพิเศษ)
        $isFixedPayment = false;
        $fixedAmount = null;
        $specialPay = 0;

        // คำนวณค่าตอบแทนจากชั่วโมงที่ได้
        $regularLectureRate = $this->getCompensationRate('regular', 'LECTURE', $student->degree_level ?? 'undergraduate');
        $regularLabRate = $this->getCompensationRate('regular', 'LAB', $student->degree_level ?? 'undergraduate');
        $specialLectureRate = $this->getCompensationRate('special', 'LECTURE', $student->degree_level ?? 'undergraduate');
        $specialLabRate = $this->getCompensationRate('special', 'LAB', $student->degree_level ?? 'undergraduate');

        $regularLecturePay = $regularLectureHoursSum * $regularLectureRate;
        $regularLabPay = $regularLabHoursSum * $regularLabRate;
        $specialLecturePay = $specialLectureHoursSum * $specialLectureRate;
        $specialLabPay = $specialLabHoursSum * $specialLabRate;

        $regularPay = $regularLecturePay + $regularLabPay;
        $specialPay = $specialLecturePay + $specialLabPay;

        // ตรวจสอบว่าเป็นผู้ช่วยสอนระดับบัณฑิตศึกษาและสอนในโครงการพิเศษหรือไม่
        $degreeLevel = $student->degree_level ?? 'undergraduate';
        $isGraduate = in_array($degreeLevel, ['master', 'doctoral', 'graduate']);

        if ($isGraduate && $hasSpecialProject) {
            // ดึงอัตราเหมาจ่ายจากฐานข้อมูล
            $fixedRate = $this->getFixedCompensationRate('special', $degreeLevel);
            if ($fixedRate) {
                $isFixedPayment = true;
                $fixedAmount = $fixedRate;

                // ตรวจสอบว่าเกิน 4,000 บาทหรือไม่
                if ($fixedAmount > 4000) {
                    $fixedAmount = 4000; // จำกัดไม่ให้เกิน 4,000 บาท
                }

                $specialPay = $fixedAmount; // ใช้ค่าเหมาจ่ายแทนการคำนวณตามชั่วโมง
            }
        }

        // เรียงข้อมูลตามวันที่และจัดกลุ่มตาม section
        $attendancesBySection = $allAttendances->sortBy('date')->groupBy('section');

        // บันทึกข้อมูลสรุปเพื่อตรวจสอบ
        Log::debug('Attendance data summary', [
            'regularLectureHours' => $regularLectureHoursSum,
            'regularLabHours' => $regularLabHoursSum,
            'specialLectureHours' => $specialLectureHoursSum,
            'specialLabHours' => $specialLabHoursSum,
            'hasRegularProject' => $hasRegularProject,
            'hasSpecialProject' => $hasSpecialProject,
            'isFixedPayment' => $isFixedPayment,
            'fixedAmount' => $fixedAmount,
            'regularPay' => $regularPay,
            'specialPay' => $specialPay
        ]);

        return [
            'allAttendances' => $allAttendances,
            'regularAttendances' => $regularAttendances,
            'specialAttendances' => $specialAttendances,
            'attendancesBySection' => $attendancesBySection,
            'hasRegularProject' => $hasRegularProject,
            'hasSpecialProject' => $hasSpecialProject,
            'regularLectureHoursSum' => $regularLectureHoursSum,
            'regularLabHoursSum' => $regularLabHoursSum,
            'specialLectureHoursSum' => $specialLectureHoursSum,
            'specialLabHoursSum' => $specialLabHoursSum,
            'isFixedPayment' => $isFixedPayment,
            'fixedAmount' => $fixedAmount,
            'specialPay' => $specialPay,
            'regularPay' => $regularPay,
            'regularLecturePay' => $regularLecturePay,
            'regularLabPay' => $regularLabPay,
            'specialLecturePay' => $specialLecturePay,
            'specialLabPay' => $specialLabPay,
            'totalPay' => $regularPay + $specialPay
        ];
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

            // คำนวณจำนวนนักศึกษาและงบประมาณ
            $course = Courses::with('classes')->find($ta->course_id);
            $totalStudents = $course ? $course->classes->sum('enrolled_num') : 0;
            $totalBudget = $totalStudents * 300; // 300 บาทต่อคน

            $selectedDate = \Carbon\Carbon::createFromFormat('Y-m', $selectedYearMonth);

            // ดึงข้อมูลงบประมาณคงเหลือรายวิชา
            $courseBudget = CourseBudget::where('course_id', $ta->course_id)->first();
            $remainingBudget = $courseBudget ? $courseBudget->remaining_budget : 0;

            // ถ้ายังไม่มีข้อมูลงบประมาณ ให้สร้างขึ้นใหม่
            if (!$courseBudget) {
                $remainingBudget = $totalBudget; // ยังไม่มีการใช้เลย คงเหลือเท่ากับงบทั้งหมด
            }

            // ดึงรายการเบิกจ่ายของเดือนนี้ (ถ้ามี)
            $transaction = CompensationTransaction::where('student_id', $student->id)
                ->where('course_id', $ta->course_id)
                ->where('month_year', $selectedYearMonth)
                ->first();

            $allAttendances = collect();
            $regularLectureHours = 0;
            $regularLabHours = 0;
            $specialLectureHours = 0;
            $specialLabHours = 0;

            $teachings = Teaching::with(['attendance', 'teacher', 'class.course.subjects', 'class.major'])
                ->where('class_id', 'LIKE', $ta->course_id . '%')
                ->whereYear('start_time', $selectedDate->year)
                ->whereMonth('start_time', $selectedDate->month)
                ->whereHas('attendance', function ($query) use ($student) {
                    $query->where('student_id', $student->id)
                        ->where('approve_status', 'A');
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

            // ดึงอัตราค่าตอบแทนตามระดับการศึกษา
            $degreeLevel = $student->degree_level ?? 'undergraduate';
            $isGraduate = in_array($degreeLevel, ['master', 'doctoral', 'graduate']);

            // ดึงอัตราค่าตอบแทนจากฐานข้อมูล
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


            // ตรวจสอบการเหมาจ่ายสำหรับนักศึกษาระดับบัณฑิตศึกษาที่สอนในโครงการพิเศษ
            $isFixedPayment = false;
            $fixedAmount = null;

            if ($isGraduate && ($specialLectureHours > 0 || $specialLabHours > 0)) {
                // ดึงอัตราเหมาจ่ายจากฐานข้อมูล
                $fixedRate = CompensationRate::where('teaching_type', 'special')
                    ->where('degree_level', $degreeLevel)
                    ->where('is_fixed_payment', true)
                    ->where('status', 'active')
                    ->first();

                if ($fixedRate && $fixedRate->fixed_amount > 0) {
                    $isFixedPayment = true;
                    $fixedAmount = $fixedRate->fixed_amount;

                    // จำกัดไม่เกิน 4,000 บาท
                    if ($fixedAmount > 4000) {
                        $fixedAmount = 4000;
                    }

                    $specialPay = $fixedAmount;
                } else {
                    // ใช้ค่าเริ่มต้นถ้าไม่พบในฐานข้อมูล
                    $isFixedPayment = true;
                    $fixedAmount = 4000;
                    $specialPay = $fixedAmount;
                }
            } else {
                $specialPay = $specialLecturePay + $specialLabPay;
            }

            $totalPay = $regularPay + $specialPay;

            // คำนวณสัดส่วนเงินระหว่างโครงการปกติและโครงการพิเศษ
            $regularProportion = 0;
            $specialProportion = 0;

            if ($totalPay > 0) {
                $regularProportion = $regularPay / $totalPay;
                $specialProportion = $specialPay / $totalPay;
            }

            // ตรวจสอบว่ามีการเบิกจ่ายแล้วหรือไม่ และตรวจสอบงบประมาณคงเหลือ
            $isExceeded = $totalPay > $remainingBudget;
            $actualTotalPay = $totalPay;
            $actualRegularPay = $regularPay;
            $actualSpecialPay = $specialPay;

            if ($transaction) {
                // ถ้ามีการบันทึกรายการเบิกจ่ายแล้ว ใช้ยอดตามที่บันทึก และคำนวณตามสัดส่วน
                $actualTotalPay = $transaction->actual_amount;
                $actualRegularPay = $actualTotalPay * $regularProportion;
                $actualSpecialPay = $actualTotalPay * $specialProportion;
            } else if ($isExceeded) {
                // ถ้ายังไม่มีการบันทึกและเกินงบประมาณคงเหลือ จำกัดไม่เกินงบประมาณคงเหลือ และคำนวณตามสัดส่วน
                $actualTotalPay = $remainingBudget;
                $actualRegularPay = $actualTotalPay * $regularProportion;
                $actualSpecialPay = $actualTotalPay * $specialProportion;
            }



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

            $templatePath = storage_path('app/public/templates/template-1.xls');

            if (!file_exists($templatePath)) {
                return back()->with('error', 'ไม่พบไฟล์ Template Excel');
            }


            $spreadsheet = IOFactory::load($templatePath);

            $regularSheet = $spreadsheet->getSheet(0);
            $regularSheet->setTitle('ปกติ');

            $regularSheet->setCellValue('A1', 'แบบใบเบิกค่าตอบแทนผู้ช่วยสอนและผู้ช่วยปฏิบัติงาน');
            $regularSheet->setCellValue('A2', 'วิทยาลัยการคอมพิวเตอร์ มหาวิทยาลัยขอนแก่น');
            $regularSheet->setCellValue('A3', $semesterText);
            $regularSheet->setCellValue('A4', 'ประจำเดือน ' . $dateRangeText);

            // กำหนดประเภทโครงการที่เหมาะสมสำหรับแต่ละแผ่นงาน
            $regularSheet->setCellValue('C6', '(/) โครงการปกติ');
            $regularSheet->setCellValue('F6', '( ) โครงการพิเศษ');

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
                        // กำหนดข้อมูลผู้ช่วยสอนและระดับการศึกษาที่ถูกต้อง
                        $regularSheet->setCellValue('B' . $row, $student->name);
                        $regularSheet->setCellValue('C' . $row, $isGraduate ? 'บัณฑิต' : 'ป.ตรี');
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

            $rateRow = 16;
            // กำหนดอัตราค่าตอบแทนตามระดับการศึกษา
            $regularSheet->setCellValue('G' . $rateRow, number_format($regularLectureRate, 2));
            $regularSheet->setCellValue('H' . $rateRow, number_format($regularLabRate, 2));

            if ($transaction && $transaction->is_adjusted) {
                // กรณีมีการปรับยอด ใช้ยอดที่ปรับแล้ว
                // $regularSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                $regularSheet->setCellValue('C21', number_format($transaction->actual_amount, 2));
                // $regularSheet->setCellValue('D21', 'บาท');
            } elseif ($isExceeded) {
                // กรณีเกินงบประมาณที่เหลือ แต่ยังไม่มีการเบิกจ่าย
                // $regularSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                $regularSheet->setCellValue('C21', number_format($remainingBudget, 2));
                // $regularSheet->setCellValue('D21', 'บาท');
            } else {
                // กรณีปกติ ไม่มีการปรับยอด
                // $regularSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                $regularSheet->setCellValue('C21', number_format($regularPay, 2));
                // $regularSheet->setCellValue('D21', 'บาท');
            }

            $payRow = 17;
            // แสดงยอดเงินรับจริงสำหรับโครงการปกติ
            // $regularSheet->setCellValue('I' . $payRow, number_format($actualRegularPay, 2));

            $regularSheet->setCellValueExplicit(
                'I' . $payRow,
                '=' . number_format($regularLectureHours, 2) . '*' . number_format($regularLectureRate, 2) . '+' . number_format($regularLabHours, 2) . '*' . number_format($regularLabRate, 2),
                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA
            );

            $signatureRow = 26;
            $regularSheet->setCellValue('A' . $signatureRow, '(' . $student->name . ')');
            $regularSheet->setCellValue('D' . $signatureRow, '(' . $teacherFullTitle . ')');
            $regularSheet->setCellValue('G' . $signatureRow, '(ผศ. ดร.คำรณ สุนัติ)');

            // สร้างแผ่นงานสำหรับโครงการพิเศษ ถ้ามีข้อมูล
            if ($regularAttendances->isEmpty() && $specialAttendances->isNotEmpty()) {
                $specialSheet = clone $spreadsheet->getSheet(0);
                $specialSheet->setTitle('พิเศษ');
                $spreadsheet->addSheet($specialSheet);

                // แก้ไขข้อความแสดงประเภทโครงการ
                $specialSheet->setCellValue('C6', '( ) โครงการปกติ');
                $specialSheet->setCellValue('F6', '(/) โครงการพิเศษ');

                // ล้างข้อมูลเดิม
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
                            $specialSheet->setCellValue('C' . $row, $isGraduate ? 'บัณฑิต' : 'ป.ตรี');
                        }

                        $specialSheet->setCellValue('D' . $row, $date);
                        $specialSheet->setCellValue('E' . $row, $courseId);
                        $specialSheet->setCellValue('F' . $row, $time);

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

                $rateRow = 16;
                // กำหนดอัตราค่าตอบแทนตามระดับการศึกษา
                if ($isFixedPayment) {
                    // กรณีเหมาจ่าย แสดงคำว่า "เหมาจ่าย" ตรงช่องอัตรา
                    $specialSheet->setCellValue('G' . $rateRow, "เหมาจ่าย");
                    $specialSheet->setCellValue('H' . $rateRow, "");
                } else {
                    $specialSheet->setCellValue('G' . $rateRow, number_format($specialLectureRate, 2));
                    $specialSheet->setCellValue('H' . $rateRow, number_format($specialLabRate, 2));
                }
                if ($transaction && $transaction->is_adjusted) {
                    // กรณีมีการปรับยอด ใช้ยอดที่ปรับแล้ว
                    // $specialSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                    $specialSheet->setCellValue('C21', number_format($transaction->actual_amount, 2));
                    // $specialSheet->setCellValue('D21', 'บาท');
                } elseif ($isExceeded) {
                    // กรณีเกินงบประมาณที่เหลือ แต่ยังไม่มีการเบิกจ่าย
                    // $specialSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                    $specialSheet->setCellValue('C21', number_format($remainingBudget, 2));
                    // $specialSheet->setCellValue('D21', 'บาท');
                } else {
                    // กรณีปกติ ไม่มีการปรับยอด
                    $amountToDisplay = $isFixedPayment ? $fixedAmount : $specialPay;
                    // $specialSheet->setCellValue('B21', 'ขอเบิกจ่ายเพียง');
                    $specialSheet->setCellValue('C21', number_format($amountToDisplay, 2));
                    // $specialSheet->setCellValue('D21', 'บาท');
                }

                $payRow = 17;
                // แสดงยอดเงินรับจริงสำหรับโครงการพิเศษโดยใช้สูตร Excel
                if ($isFixedPayment) {
                    // กรณีเหมาจ่าย ไม่ต้องใช้สูตร ใช้ค่าคงที่แทน
                    $specialSheet->setCellValue('I' . $payRow, number_format($fixedAmount, 2));
                } else {
                    // กรณีคิดตามชั่วโมง ใช้สูตรคำนวณ
                    $specialSheet->setCellValueExplicit(
                        'I' . $payRow,
                        '=' . number_format($specialLectureHours, 2) . '*' . number_format($specialLectureRate, 2) . '+' . number_format($specialLabHours, 2) . '*' . number_format($specialLabRate, 2),
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA
                    );
                }

                // สร้างหน้าหลักฐานการเบิกจ่าย
                $hasEvidenceSheet = false;
                foreach ($spreadsheet->getSheetNames() as $sheetName) {
                    if ($sheetName === 'หลักฐาน-ปกติ') {
                        $hasEvidenceSheet = true;
                        break;
                    }
                }

                if ($hasEvidenceSheet) {
                    $normalEvidenceSheet = $spreadsheet->getSheetByName('หลักฐาน-ปกติ');
                    $specialEvidenceSheet = clone $normalEvidenceSheet;
                    $specialEvidenceSheet->setTitle('หลักฐาน-พิเศษ');
                    $spreadsheet->addSheet($specialEvidenceSheet);

                    // ปรับแบบฟอร์มสำหรับโครงการพิเศษ
                    $specialEvidenceSheet->setCellValue('E7', '( ) โครงการปกติ');
                    $specialEvidenceSheet->setCellValue('H7', '(/) โครงการพิเศษ');

                    // กำหนดข้อมูลระดับการศึกษาที่ถูกต้อง
                    $specialEvidenceSheet->setCellValue('B10', $student->name);
                    $specialEvidenceSheet->setCellValue('C10', $isGraduate ? 'บัณฑิต' : 'ป.ตรี');

                    $specialTotalHours = $specialLectureHours + $specialLabHours;
                    $specialEvidenceSheet->setCellValue('D10', number_format($specialTotalHours, 1));

                    // แสดงข้อมูลอัตราค่าตอบแทน
                    if ($isFixedPayment) {
                        $specialEvidenceSheet->setCellValue('E10', "เหมาจ่าย");
                    } else {
                        $specialEvidenceSheet->setCellValue('E10', number_format($specialLectureRate, 2));
                    }

                    // แสดงจำนวนเงินที่ได้รับจริง
                    // $specialEvidenceSheet->setCellValue('F10', number_format($actualSpecialPay, 2));
                    if ($transaction && $transaction->is_adjusted) {
                        // กรณีมีการปรับยอด ใช้ยอดที่ปรับแล้ว
                        $specialEvidenceSheet->setCellValue('F10', number_format($transaction->actual_amount, 2));
                    } elseif ($isExceeded) {
                        // กรณีเกินงบประมาณที่เหลือ แต่ยังไม่มีการเบิกจ่าย
                        $specialEvidenceSheet->setCellValue('F10', number_format($remainingBudget, 2));
                    } else {
                        // กรณีปกติ ไม่มีการปรับยอด
                        if ($isFixedPayment) {
                            // กรณีเหมาจ่าย ใช้ค่า fixedAmount
                            $specialEvidenceSheet->setCellValue('F10', number_format($fixedAmount, 2));
                        } else {
                            // กรณีคิดตามชั่วโมง ใช้สูตรคำนวณ
                            $specialEvidenceSheet->setCellValueExplicit(
                                'F10',
                                '=' . number_format($specialLectureHours, 2) . '*' . number_format($specialLectureRate, 2) . '+' . number_format($specialLabHours, 2) . '*' . number_format($specialLabRate, 2),
                                \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA
                            );
                        }
                    }

                    // แสดงยอดรวมเป็นตัวอักษร
                    $specialEvidenceSheet->setCellValue('C17', '(' . $this->convertNumberToThaiBaht($actualSpecialPay) . ')');
                } else {
                    // สร้างหน้าหลักฐานเบิกจ่ายใหม่
                    $specialEvidenceSheet = $spreadsheet->createSheet();
                    $specialEvidenceSheet->setTitle('หลักฐาน-พิเศษ');

                    $specialEvidenceSheet->setCellValue('A1', 'หลักฐานการจ่ายเงิน ฯ');
                    $specialEvidenceSheet->setCellValue('B2', 'เขียนที่วิทยาลัยการคอมพิวเตอร์           วันที่           เดือน                     พ.ศ.           ');
                    $specialEvidenceSheet->setCellValue('A3', 'ข้าพเจ้าผู้มีรายนามข้างท้ายนี้ ได้รับเงินจากส่วนราชการ  วิทยาลัยการคอมพิวเตอร์  มหาวิทยาลัยขอนแก่น  เป็นค่าตอบแทนผู้ช่วยสอนและผู้ช่วยปฏิบัติงาน');
                    $specialEvidenceSheet->setCellValue('A4', 'สาขาวิชาวิทยาการคอมพิวเตอร์ ประจำภาค' . ($semesterValue == '1' ? 'ต้น' : ($semesterValue == '2' ? 'ปลาย' : 'ฤดูร้อน')) . ' ปีการศึกษา ' . ($semester->year + 543));
                    $specialEvidenceSheet->setCellValue('A5', 'ตามหนังสืออนุมัติที่          ลงวันที่       เดือน              พ.ศ. 256    ได้รับการอุดหนุนแล้วจึงลงลายมือชื่อไว้เป็นสำคัญ');

                    // เลือกระดับการศึกษาที่เหมาะสม
                    // เลือกระดับการศึกษาที่เหมาะสม
                    if ($isGraduate) {
                        $specialEvidenceSheet->setCellValue('C6', '( ) ปริญญาตรี');
                        $specialEvidenceSheet->setCellValue('H6', '(/) บัณฑิตศึกษา');
                    } else {
                        $specialEvidenceSheet->setCellValue('C6', '(/) ปริญญาตรี');
                        $specialEvidenceSheet->setCellValue('H6', '( ) บัณฑิตศึกษา');
                    }

                    // กำหนดประเภทโครงการ
                    $specialEvidenceSheet->setCellValue('E7', '( ) โครงการปกติ');
                    $specialEvidenceSheet->setCellValue('H7', '(/) โครงการพิเศษ');

                    $specialEvidenceSheet->setCellValue('A8', 'ลำดับที่');
                    $specialEvidenceSheet->setCellValue('B8', 'ชื่อผู้สอน');
                    $specialEvidenceSheet->setCellValue('C8', 'ระดับ');
                    $specialEvidenceSheet->setCellValue('D8', 'จำนวนชั่วโมง');
                    $specialEvidenceSheet->setCellValue('E8', 'อัตราค่าตอบแทน');
                    $specialEvidenceSheet->setCellValue('F8', 'จำนวนเงิน');
                    $specialEvidenceSheet->setCellValue('G8', 'วันเวลาที่รับเงิน');
                    $specialEvidenceSheet->setCellValue('H8', 'ลายมือชื่อผู้รับเงิน');
                    $specialEvidenceSheet->setCellValue('I8', 'หมายเหตุ');

                    $specialEvidenceSheet->setCellValue('A10', '1');
                    $specialEvidenceSheet->setCellValue('B10', $student->name);
                    $specialEvidenceSheet->setCellValue('C10', $isGraduate ? 'บัณฑิต' : 'ป.ตรี');

                    $specialTotalHours = $specialLectureHours + $specialLabHours;
                    $specialEvidenceSheet->setCellValue('D10', number_format($specialTotalHours, 1));

                    // แสดงข้อมูลอัตราค่าตอบแทน
                    if ($isFixedPayment) {
                        $specialEvidenceSheet->setCellValue('E10', "เหมาจ่าย");
                    } else {
                        $specialEvidenceSheet->setCellValue('E10', number_format($specialLectureRate, 2));
                    }

                    // แสดงจำนวนเงินที่ได้รับจริง
                    $specialEvidenceSheet->setCellValue('G10', number_format($actualSpecialPay, 2));

                    // ส่วนรวมเงินทั้งสิ้น
                    $specialEvidenceSheet->setCellValue('A16', 'รวมเป็นเงินทั้งสิ้น');
                    $specialEvidenceSheet->setCellValue('G16', number_format($actualSpecialPay, 2));

                    // แสดงยอดรวมเป็นตัวอักษร
                    $specialEvidenceSheet->setCellValue('C17', '(' . $this->convertNumberToThaiBaht($actualSpecialPay) . ')');

                    $specialEvidenceSheet->setCellValue('B20', 'ลงชื่อ.........................................ผู้จ่ายเงิน');
                    $specialEvidenceSheet->setCellValue('F22', 'ตำแหน่ง หัวหน้าสาขาวิชาวิทยาการคอมพิวเตอร์');
                }
            }

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fileName = 'TA-Reimbursement-' . $student->student_id . '-' . $selectedYearMonth . '.xlsx';
            $tempPath = storage_path('app/temp/' . $fileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer->save($tempPath);

            return response()->download($tempPath, $fileName)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Template Export Error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return back()->with('error', 'เกิดข้อผิดพลาดในการสร้างไฟล์ Excel: ' . $e->getMessage());
        }
    }
}
