<?php

namespace App\Http\Controllers;

use App\Models\{
    Announce,
    Attendances,
    Classes,
    Courses,
    CourseTaClasses,
    CourseTas,
    ExtraAttendances,
    ExtraTeaching,
    Requests,
    Semesters,
    Students,
    Subjects,
    Teachers,
    Teaching,
    User
};
use App\Services\{TDBMApiService, TDBMSyncService};
use Illuminate\Support\Facades\{Auth, DB, Hash, Log, View};
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yoeunes\Toastr\Facades\Toastr;



class TaController extends Controller
{
    private $tdbmService;

    public function __construct(TDBMApiService $tdbmService)
    {
        $this->middleware('auth');
        $this->tdbmService = $tdbmService;
    }

    // function for get actie semesters
    private function getActiveSemester()
    {
        // ลองดึงจาก session ก่อน
        $activeSemesterId = session('user_active_semester_id');

        // ถ้าไม่มีใน session ให้ดึงจากฐานข้อมูล
        if (!$activeSemesterId) {
            $setting = DB::table('setting_semesters')
                ->where('key', 'user_active_semester_id')
                ->first();

            if ($setting) {
                $activeSemesterId = $setting->value;
                session(['user_active_semester_id' => $activeSemesterId]);
            }
        }

        // ถ้ายังไม่มีค่า ให้ใช้ semester ล่าสุด
        if (!$activeSemesterId) {
            $semester = Semesters::orderBy('year', 'desc')
                ->orderBy('semesters', 'desc')
                ->first();
        } else {
            $semester = Semesters::find($activeSemesterId);
        }

        // Log debug information
        Log::info('Active Semester ID: ' . ($semester ? $semester->semester_id : 'Not Found'));

        return $semester;
    }

    public function request()
    {
        // Get latest semester
        $currentSemester = $this->getActiveSemester();

        if (!$currentSemester) {
            return redirect()->back()->with('error', 'ไม่พบข้อมูลภาคการศึกษา');
        }

        // Get data from local database for current semester
        $subjects = Subjects::all();
        $courses = Courses::where('semester_id', $currentSemester->semester_id)->get();

        // เพิ่ม with('major') เพื่อดึงข้อมูลของสาขาไปด้วย
        $studentClasses = Classes::with('major')
            ->where('semester_id', $currentSemester->semester_id)
            ->get();

        // Filter subjects with sections for current semester
        $subjectsWithSections = $subjects
            ->filter(function ($subject) use ($courses) {
                return $courses->where('subject_id', $subject->subject_id)->isNotEmpty();
            })
            ->map(function ($subject) use ($courses, $studentClasses) {
                $course = $courses->where('subject_id', $subject->subject_id)->first();

                // สร้าง array เก็บข้อมูล section พร้อมกับข้อมูลสาขา
                $sectionsWithMajor = [];

                // กลุ่ม class ตาม section_num
                $sectionGroups = $studentClasses->where('course_id', $course->course_id)
                    ->groupBy('section_num');

                foreach ($sectionGroups as $sectionNum => $classes) {
                    $sectionInfo = [
                        'section_num' => $sectionNum,
                        'major_info' => []
                    ];

                    // เก็บข้อมูล major สำหรับแต่ละ class ใน section นี้
                    foreach ($classes as $class) {
                        if ($class->major) {
                            $majorType = $class->major->major_type == 'N' ? 'ภาคปกติ' : 'โครงการพิเศษ';
                            $sectionInfo['major_info'][] = [
                                'major_name' => $class->major->name_th,
                                'major_type_code' => $class->major->major_type,
                                'major_type_name' => $majorType
                            ];
                        }
                    }

                    $sectionsWithMajor[] = $sectionInfo;
                }

                return [
                    'subject' => [
                        'subject_id' => $subject->subject_id,
                        'subject_name_en' => $subject->name_en,
                        'subject_name_th' => $subject->name_th
                    ],
                    'sections' => $sectionsWithMajor
                ];
            })->values();

        return view('layouts.ta.request', compact('subjectsWithSections', 'currentSemester'));
    }

    // function for show announces
    public function showAnnounces()
    {
        // ใช้ getActiveSemester() เพื่อให้สอดคล้องกับวิธีอื่นๆ
        $currentSemester = $this->getActiveSemester();

        // ตรวจสอบว่า $currentSemester ไม่เป็น null
        if (!$currentSemester) {
            Log::error('No active semester found');
            return view('layouts.ta.home', ['announces' => collect()]);
        }

        $announces = Announce::where('semester_id', $currentSemester->semester_id)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        // Log debug information
        Log::info('Announces for semester ' . $currentSemester->semester_id . ': ' . $announces->count());

        return view('layouts.ta.home', compact('announces'));
    }

    // function for display subject list and Apply for TA
    public function apply(Request $request)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'applications' => 'required|array|min:1|max:3',
                'applications.*.subject_id' => 'required',
                'applications.*.sections' => 'required|array|min:1',
                'applications.*.sections.*' => 'required|numeric',
            ]);

            $user = Auth::user();

            // 2. Get data from local database
            $courses = Courses::all();
            // $studentClasses = Classes::all();
            $studentClasses = Classes::with('major')->get();
            $subjects = Subjects::all();

            // 3. Get latest semester
            $currentSemester = $this->getActiveSemester();

            if (!$currentSemester) {
                Toastr::error('ไม่พบข้อมูลภาคการศึกษา', 'เกิดข้อผิดพลาด!');
                return redirect()->back();
            }

            // 4. Update or create student
            $student = Students::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'prefix' => $user->prefix,
                    'name' => $user->name,
                    'student_id' => $user->student_id,
                    'email' => $user->email,
                    'card_id' => $user->card_id,
                    'phone' => $user->phone,
                    'degree_level' => $request->input('degree_level', 'bachelor')
                ]
            );

            $applications = $request->input('applications');

            // 5. Check course limit
            $currentCourseCount = CourseTas::where('student_id', $student->id)->count();
            if ($currentCourseCount + count($applications) > 3) {
                Toastr()->warning('คุณไม่สามารถสมัครเป็นผู้ช่วยสอนได้เกิน 3 วิชา', 'คำเตือน!');
                return redirect()->back();
            }

            // 6. Process each application
            foreach ($applications as $application) {
                $subjectId = $application['subject_id'];
                $sectionNums = $application['sections'];

                // Find course from local database
                $course = $courses->where('subject_id', $subjectId)
                    ->where('semester_id', $currentSemester->semester_id)
                    ->first();

                if (!$course) {
                    DB::rollBack();
                    Toastr()->error('ไม่พบรายวิชา ' . $subjectId . ' ในระบบ', 'เกิดข้อผิดพลาด!');
                    return redirect()->back();
                }

                // Check duplicate
                $existingTA = CourseTas::where('student_id', $student->id)
                    ->where('course_id', $course->course_id)
                    ->first();

                if ($existingTA) {
                    DB::rollBack();
                    Toastr()->warning('คุณได้สมัครเป็นผู้ช่วยสอนในวิชา ' . $subjectId . ' แล้ว', 'คำเตือน!');
                    return redirect()->back();
                }

                // Create course TA
                $courseTA = CourseTas::create([
                    'student_id' => $student->id,
                    'course_id' => $course->course_id,
                ]);

                foreach ($sectionNums as $sectionNum) {
                    $class = $studentClasses->where('course_id', $course->course_id)
                        ->where('section_num', $sectionNum)
                        ->first();

                    if (!$class) {
                        DB::rollBack();
                        Toastr()->error('ไม่พบเซคชัน ' . $sectionNum . ' สำหรับวิชา ' . $subjectId, 'เกิดข้อผิดพลาด!');
                        return redirect()->back();
                    }

                    $courseTaClass = CourseTaClasses::create([
                        'class_id' => $class->class_id,
                        'course_ta_id' => $courseTA->id,
                    ]);

                    Requests::create([
                        'course_ta_class_id' => $courseTaClass->id,
                        'status' => 'W',
                        'comment' => null,
                        'approved_at' => null,
                    ]);
                }
            }

            DB::commit();
            Toastr()->success('สมัครเป็นผู้ช่วยสอนสำเร็จ', 'สำเร็จ!');
            return redirect()->route('layout.ta.request');
        } catch (\Exception $e) {
            DB::rollBack();
            Toastr()->error('เกิดข้อผิดพลาด: ' . $e->getMessage(), 'เกิดข้อผิดพลาด!');
            return redirect()->back();
        }
    }

    public function showCourseTas()
    {
        $user = Auth::user();
        $student = Students::where('user_id', $user->id)->first();

        if (!$student) {
            return redirect()->route('layout.ta.request')->with('error', 'ไม่พบข้อมูลนักศึกษา');
        }

        $courseTaClasses = CourseTaClasses::with([
            'courseTa.course.subjects',
            'courseTa.course.semesters',
            'courseTa.course.teachers',
            'courseTa.course.curriculums',
            'class',
            'requests'
        ])
            ->whereHas('courseTa', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('requests')
                    ->whereColumn('requests.course_ta_class_id', 'course_ta_classes.id')
                    ->where('status', 'A')
                    ->whereNotNull('approved_at');
            })
            ->get();

        foreach ($courseTaClasses as $courseTaClass) {
            if (isset($courseTaClass->courseTa->course->curriculums->name_th)) {
                $courseTaClass->courseTa->course->curriculums->name_th =
                    trim(Str::before($courseTaClass->courseTa->course->curriculums->name_th, ' '));
            }
        }

        return view('layouts.ta.subjectList', compact('courseTaClasses'));
    }

    public function showSubjectDetail($id, $classId)
    {
        try {


            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->first();

            // ดึงข้อมูล CourseTaClass
            $courseTaClass = CourseTaClasses::with([
                'class.course.subjects',
                'class.course.curriculums',
                'class.teachers',
                'class.semesters'
            ])->whereHas('courseTa', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })->where('course_ta_id', $id)
                ->where('class_id', $classId)
                ->first();

            // ตรวจสอบว่าข้อมูลถูกต้องหรือไม่
            if (!$courseTaClass) {
                abort(404, 'ไม่พบข้อมูล CourseTa ที่ระบุ');
            }

            // ดึงข้อมูลเทอมจาก relation
            $semester = $courseTaClass->class->semesters;

            // สร้าง array เดือนในเทอม
            $months = [];
            if ($semester) {
                $startDate = \Carbon\Carbon::parse($semester->start_date);
                $endDate = \Carbon\Carbon::parse($semester->end_date);

                // เพิ่ม endOfMonth() เพื่อให้ครอบคลุมทั้งเดือน
                $endDate = $endDate->endOfMonth();

                $currentDate = $startDate->copy()->startOfMonth();
                while ($currentDate->lte($endDate)) {
                    $months[] = [
                        'value' => $currentDate->format('M'),
                        'name' => $currentDate->locale('th')->monthName
                    ];
                    $currentDate->addMonth();
                }
            }

            return view('layouts.ta.subjectDetail', compact('courseTaClass', 'student', 'months'));
        } catch (\Exception $e) {
            Log::error('Error in showSubjectDetail: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการแสดงข้อมูล');
        }
    }

    public function showTeachingData($id = null, Request $request)
    {
        try {
            if (!$id) {
                return redirect()->back()->with('error', 'กรุณาระบุ Class ID');
            }

            $selectedMonth = $request->query('month');
            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->first();
            $extraTeachingIdMapping = cache()->get('extra_teaching_id_mapping', []);

            DB::beginTransaction();

            // 1. ดึงข้อมูลการสอนปกติจากฐานข้อมูล
            $query = Teaching::where('class_id', $id);
            if ($selectedMonth) {
                $query->whereMonth('start_time', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
            }
            $regularTeachings = $query->get()
                ->map(function ($teaching) {
                    return [
                        'teaching_id' => $teaching->teaching_id,
                        'start_time' => $teaching->start_time,
                        'end_time' => $teaching->end_time,
                        'duration' => $teaching->duration,
                        'class_type' => $teaching->class_type,
                        'status' => $teaching->status,
                        'class_id' => $teaching->class_id,
                        'teacher_id' => $teaching->teacher_id,
                        'is_extra_teaching' => false
                    ];
                });

            // 2. ดึงข้อมูล extra teachings จากฐานข้อมูล
            $extraQuery = ExtraTeaching::where('class_id', $id);
            if ($selectedMonth) {
                $extraQuery->whereMonth('class_date', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
            }
            $extraTeachings = $extraQuery->get()
                ->map(function ($teaching) {
                    // สร้าง datetime objects แทนการเชื่อม string เพื่อความถูกต้อง
                    $classDate = $teaching->class_date;
                    $startTime = \Carbon\Carbon::parse($classDate . ' ' . $teaching->start_time);
                    $endTime = \Carbon\Carbon::parse($classDate . ' ' . $teaching->end_time);

                    return [
                        'teaching_id' => $teaching->extra_class_id,
                        'start_time' => $startTime->format('Y-m-d H:i:s'),
                        'end_time' => $endTime->format('Y-m-d H:i:s'),
                        'duration' => $teaching->duration,
                        'class_type' => 'E', // กำหนดให้เป็น 'E' เพื่อบ่งชี้ว่าเป็น extra teaching
                        'status' => $teaching->status,
                        'class_id' => $teaching->class_id,
                        'teacher_id' => $teaching->teacher_id,
                        'title' => $teaching->title,
                        'detail' => $teaching->detail,
                        'is_extra_teaching' => true
                    ];
                });

            // 3. ดึงข้อมูล extra attendances
            $extraAttQuery = ExtraAttendances::where('class_id', $id);
            if ($selectedMonth) {
                $extraAttQuery->whereMonth('start_work', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
            }
            $extraAttendances = $extraAttQuery->get()
                ->map(function ($attendance) {
                    return [
                        'teaching_id' => 'extra_' . $attendance->id,
                        'start_time' => $attendance->start_work,
                        'end_time' => \Carbon\Carbon::parse($attendance->start_work)
                            ->addMinutes($attendance->duration)
                            ->format('Y-m-d H:i:s'),
                        'duration' => $attendance->duration,
                        'class_type' => $attendance->class_type,
                        'status' => 'A',
                        'class_id' => $attendance->class_id,
                        'teacher_id' => null,
                        'is_extra_attendance' => true,
                        'detail' => $attendance->detail
                    ];
                });

            // 4. รวมข้อมูลทั้งหมด
            $allTeachings = $regularTeachings->concat($extraTeachings)->concat($extraAttendances);

            if ($allTeachings->isEmpty()) {
                session()->flash('info', 'ยังไม่มีข้อมูลการสอนสำหรับรายวิชานี้');
                return redirect()->back();
            }

            // 5. ดึงข้อมูลที่เกี่ยวข้อง
            $classes = Classes::all();
            $teachers = Teachers::with('user')->get();

            // 6. จัดเตรียมข้อมูลสำหรับแสดงผล
            // 7. จัดรูปแบบข้อมูลการสอนปกติ
            $formattedTeachings = Teaching::with(['class', 'teacher', 'attendance' => function ($query) use ($student) {
                // ดึงข้อมูลการลงเวลาเฉพาะของผู้ช่วยสอนคนปัจจุบัน
                $query->where('student_id', $student->id);
            }])
                ->where('class_id', $id)
                ->when($selectedMonth, function ($query) use ($selectedMonth) {
                    return $query->whereMonth('start_time', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
                })
                ->orderBy('start_time', 'asc')
                ->get()
                ->map(function ($teaching) use ($classes, $teachers, $student) {
                    $class = $classes->where('class_id', $teaching->class_id)->first();
                    $teacher = $teachers->where('teacher_id', $teaching->teacher_id)->first();

                    return (object) [
                        'id' => $teaching->teaching_id,
                        'start_time' => $teaching->start_time,
                        'end_time' => $teaching->end_time,
                        'duration' => $teaching->duration,
                        'class_type' => $teaching->class_type,
                        'class_id' => (object) [
                            'title' => $class->title ?? 'N/A',
                        ],
                        'teacher_id' => (object) [
                            'position' => $teacher->position ?? '',
                            'degree' => $teacher->degree ?? '',
                            'name' => $teacher->user->name ?? 'N/A',
                        ],
                        'attendance' => $teaching->attendance ? (object) [
                            'status' => $teaching->attendance->status,
                            'note' => $teaching->attendance->note ?? '',
                            'approve_status' => $teaching->attendance->approve_status ?? null
                        ] : null,
                        'is_extra_attendance' => false,
                        'is_extra_teaching' => false
                    ];
                });

            // 8. จัดการข้อมูล Extra Teachings
            $formattedExtraTeachings = ExtraTeaching::with(['class', 'teacher'])
                ->where('class_id', $id)
                ->when($selectedMonth, function ($query) use ($selectedMonth) {
                    return $query->whereMonth('class_date', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
                })
                ->get()
                ->map(function ($extraTeaching) use ($classes, $teachers, $student, $extraTeachingIdMapping) {
                    $class = $classes->where('class_id', $extraTeaching->class_id)->first();
                    $teacher = $teachers->where('teacher_id', $extraTeaching->teacher_id)->first();
                    // ค้นหา attendance โดยใช้ local ID แทน API ID
                    $localExtraTeachingId = $extraTeaching->extra_class_id;

                    // เพิ่มการกรองตาม student_id
                    // $attendance = Attendances::where('extra_teaching_id', $extraTeaching->extra_class_id)
                    //     ->where('student_id', $student->id)
                    //     ->first();
                    $attendance = Attendances::where('extra_teaching_id', $localExtraTeachingId)
                        ->where('student_id', $student->id)
                        ->first();

                    return (object) [
                        'id' => $extraTeaching->extra_class_id, // Use extra_class_id here
                        'start_time' => \Carbon\Carbon::parse($extraTeaching->class_date . ' ' . $extraTeaching->start_time),
                        'end_time' => \Carbon\Carbon::parse($extraTeaching->class_date . ' ' . $extraTeaching->end_time),
                        'duration' => $extraTeaching->duration,
                        'class_type' => 'E',
                        'class_id' => (object) [
                            'title' => $class->title ?? 'N/A',
                        ],
                        'teacher_id' => (object) [
                            'position' => $teacher->position ?? '',
                            'degree' => $teacher->degree ?? '',
                            'name' => $teacher->user->name ?? 'N/A',
                        ],
                        'attendance' => $attendance ? (object) [
                            'status' => $attendance->status,
                            'note' => $attendance->note,
                            'approve_status' => $attendance->approve_status
                        ] : null,
                        'is_extra_attendance' => false,
                        'is_extra_teaching' => true
                    ];
                });

            // 9. จัดการข้อมูล Extra Attendances
            // สำหรับ Extra Attendances
            $formattedExtraAttendances = ExtraAttendances::where('class_id', $id)
                ->where('student_id', $student->id) // เพิ่มบรรทัดนี้เพื่อกรองตาม student ID
                ->when($selectedMonth, function ($query) use ($selectedMonth) {
                    return $query->whereMonth('start_work', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
                })
                ->get()
                ->map(function ($attendance) use ($classes, $teachers) {
                    $class = $classes->where('class_id', $attendance->class_id)->first();
                    $course = Courses::where('course_id', $class->course_id ?? null)->first();
                    $teacher = null;

                    // ดึงข้อมูลคอร์สและอาจารย์
                    $course = null;
                    $teacher = null;

                    if ($class) {
                        $course = Courses::find($class->course_id);
                        if ($course) {
                            $teacher = $teachers->where('teacher_id', $course->owner_teacher_id)->first();
                        }
                    }

                    return (object) [
                        // ใช้ prefix เพื่อระบุประเภทของข้อมูล (ทำให้เราสามารถแยกประเภทได้ในหน้า blade)
                        'id' => 'extra_' . $attendance->id,
                        'start_time' => $attendance->start_work,
                        'end_time' => \Carbon\Carbon::parse($attendance->start_work)
                            ->addMinutes($attendance->duration),
                        'duration' => $attendance->duration,
                        'class_type' => $attendance->class_type,
                        'class_id' => (object) [
                            'title' => $class->title ?? 'N/A',
                        ],
                        'teacher_id' => (object) [
                            'position' => $teacher->position ?? '',
                            'degree' => $teacher->degree ?? '',
                            'name' => $teacher->user->name ?? 'N/A'
                        ],
                        'attendance' => (object) [
                            'status' => 'เข้าปฏิบัติการสอน',
                            'note' => $attendance->detail,
                            'approve_status' => $attendance->approve_status ?? null
                        ],
                        'is_extra_attendance' => true,
                        'original_id' => $attendance->id // เก็บ ID จริงไว้ในกรณีที่ต้องการใช้โดยตรง
                    ];
                });
            // 10. รวมและเรียงข้อมูล
            $allRecords = $formattedTeachings
                ->concat($formattedExtraTeachings)
                ->concat($formattedExtraAttendances)
                ->sortBy('start_time')
                ->values();

            DB::commit();

            return view('layouts.ta.teaching', [
                'teachings' => $allRecords,
                'selectedMonth' => $selectedMonth
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in showTeachingData: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการแสดงข้อมูลการสอน: ' . $e->getMessage());
        }
    }

    // function for update extra teaching data
    public function refreshTeachings($id, Request $request)
    {
        try {
            // Get the TDBM API Service
            $tdbmApiService = app(TDBMApiService::class);

            // ดึงเฉพาะข้อมูล extra teachings
            $classExtraTeachings = $tdbmApiService->getExtraTeachings();

            // Log ข้อมูลที่ได้จาก API
            Log::info("Fetched extra teachings from API: " . count($classExtraTeachings));

            // Filter extra teachings เฉพาะสำหรับคลาสนี้
            $classExtraTeachings = collect($classExtraTeachings)->filter(function ($teaching) use ($id) {
                return $teaching['class_id'] == $id;
            })->toArray();

            Log::info("Filtered extra teachings for class $id: " . count($classExtraTeachings));

            // ถ้าไม่มีข้อมูลให้แสดงข้อความและกลับไปหน้าเดิม
            if (empty($classExtraTeachings)) {
                return redirect()
                    ->route('layout.ta.teaching', ['id' => $id, 'selected_month' => $request->query('selected_month')])
                    ->with('info', 'ไม่พบข้อมูลการสอนพิเศษใหม่สำหรับคลาสนี้');
            }

            // เตรียมข้อมูลสำหรับการตรวจสอบการอัปเดต
            $updatedCount = 0;
            $newCount = 0;
            $skippedCount = 0;

            DB::beginTransaction();

            // ดึงข้อมูล extra teachings ที่มีอยู่แล้วในฐานข้อมูลเพื่อเปรียบเทียบ
            $existingExtraTeachings = ExtraTeaching::where('class_id', $id)
                ->get()
                ->keyBy('extra_class_id')
                ->toArray();

            // Update เฉพาะ extra teachings สำหรับคลาสนี้
            foreach ($classExtraTeachings as $teaching) {
                try {
                    // Log ข้อมูลที่จะใส่ในตาราง
                    Log::info("Processing extra teaching data: ", $teaching);

                    $extraClassId = $teaching['extra_class_id'];

                    // ตรวจสอบว่ามีข้อมูลนี้อยู่แล้วหรือไม่
                    if (isset($existingExtraTeachings[$extraClassId])) {
                        // ตรวจสอบว่าข้อมูลมีการเปลี่ยนแปลงหรือไม่
                        $existing = $existingExtraTeachings[$extraClassId];
                        $hasChanges = false;

                        // เปรียบเทียบฟิลด์สำคัญเพื่อตรวจสอบการเปลี่ยนแปลง
                        $fieldsToCheck = [
                            'title',
                            'detail',
                            'opt_status',
                            'status',
                            'class_date',
                            'start_time',
                            'end_time',
                            'duration',
                            'teacher_id',
                            'holiday_id',
                            'teaching_id'
                        ];

                        foreach ($fieldsToCheck as $field) {
                            $newValue = $teaching[$field] ?? null;
                            if ($field === 'holiday_id' && $newValue === null) {
                                $newValue = 0; // กำหนดค่าเริ่มต้นสำหรับ holiday_id
                            }

                            if (isset($existing[$field]) && $existing[$field] != $newValue) {
                                $hasChanges = true;
                                break;
                            }
                        }

                        if ($hasChanges) {
                            // มีการเปลี่ยนแปลง ให้อัปเดต
                            ExtraTeaching::where('extra_class_id', $extraClassId)
                                ->update([
                                    'title' => $teaching['title'] ?? 'No Title',
                                    'detail' => $teaching['detail'] ?? 'No Detail',
                                    'opt_status' => $teaching['opt_status'] ?? 'A',
                                    'status' => $teaching['status'] ?? 'A',
                                    'class_date' => $teaching['class_date'],
                                    'start_time' => $teaching['start_time'],
                                    'end_time' => $teaching['end_time'],
                                    'duration' => $teaching['duration'],
                                    'teacher_id' => $teaching['teacher_id'],
                                    'holiday_id' => $teaching['holiday_id'] ?? 0,
                                    'teaching_id' => $teaching['teaching_id'],
                                    'class_id' => $teaching['class_id']
                                ]);

                            $updatedCount++;
                            Log::info("Updated extra teaching ID: {$extraClassId}");
                        } else {
                            // ไม่มีการเปลี่ยนแปลง ข้ามไป
                            $skippedCount++;
                            Log::info("Skipped unchanged extra teaching ID: {$extraClassId}");
                        }
                    } else {
                        // ไม่มีข้อมูลนี้ ให้สร้างใหม่
                        ExtraTeaching::create([
                            'extra_class_id' => $extraClassId,
                            'title' => $teaching['title'] ?? 'No Title',
                            'detail' => $teaching['detail'] ?? 'No Detail',
                            'opt_status' => $teaching['opt_status'] ?? 'A',
                            'status' => $teaching['status'] ?? 'A',
                            'class_date' => $teaching['class_date'],
                            'start_time' => $teaching['start_time'],
                            'end_time' => $teaching['end_time'],
                            'duration' => $teaching['duration'],
                            'teacher_id' => $teaching['teacher_id'],
                            'holiday_id' => $teaching['holiday_id'] ?? 0,
                            'teaching_id' => $teaching['teaching_id'],
                            'class_id' => $teaching['class_id']
                        ]);

                        $newCount++;
                        Log::info("Created new extra teaching ID: {$extraClassId}");
                    }
                } catch (\Exception $innerException) {
                    Log::error("Error processing extra teaching {$teaching['extra_class_id']}: " . $innerException->getMessage());
                    // ไม่ throw exception เพื่อให้โค้ดทำงานต่อไปได้สำหรับรายการอื่น
                }
            }

            DB::commit();

            // สร้างข้อความสรุป
            $summaryMessage = "อัปเดตข้อมูลการสอนพิเศษสำเร็จ ";
            if ($newCount > 0) {
                $summaryMessage .= "สร้างใหม่ {$newCount} รายการ ";
            }
            if ($updatedCount > 0) {
                $summaryMessage .= "อัปเดต {$updatedCount} รายการ ";
            }
            if ($skippedCount > 0) {
                $summaryMessage .= "ข้ามที่ไม่มีการเปลี่ยนแปลง {$skippedCount} รายการ";
            }

            // Redirect back with success message
            return redirect()
                ->route('layout.ta.teaching', ['id' => $id, 'selected_month' => $request->query('selected_month')])
                ->with('success', $summaryMessage);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error refreshing extra teachings: ' . $e->getMessage());
            Log::error($e->getTraceAsString()); // เพิ่ม stack trace เพื่อการแก้ไขปัญหา
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูลการสอนพิเศษ: ' . $e->getMessage());
        }
    }

    // function for display attendance form
    public function showAttendanceForm($teaching_id, Request $request)
    {
        try {
            $isExtra = $request->query('is_extra', false);
            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->first();

            if ($isExtra) {
                // ดึงข้อมูล extra teaching โดยใช้ extra_class_id แทน id
                $extraTeaching = ExtraTeaching::where('extra_class_id', $teaching_id)->firstOrFail();

                $attendance = Attendances::where('extra_teaching_id', $teaching_id)
                    ->where('student_id', $student->id) // เพิ่มบรรทัดนี้
                    ->first();

                // สร้าง object ที่มีโครงสร้างคล้ายกับ teaching ปกติเพื่อส่งไปยัง view
                $teaching = (object)[
                    'teaching_id' => $extraTeaching->extra_class_id,
                    'start_time' => $extraTeaching->class_date . ' ' . $extraTeaching->start_time,
                    'end_time' => $extraTeaching->class_date . ' ' . $extraTeaching->end_time,
                    'duration' => $extraTeaching->duration,
                    'class_type' => 'E',
                    'class_id' => $extraTeaching->class_id,
                    'teacher_id' => $extraTeaching->teacher_id,
                    'is_extra' => true,
                    'title' => $extraTeaching->title,
                    'detail' => $extraTeaching->detail
                ];

                // ตรวจสอบว่ามีการลงเวลาแล้วหรือไม่
                $attendance = Attendances::where('teaching_id', $teaching_id)
                    ->where('is_extra', true)
                    ->first();

                if ($attendance) {
                    return redirect()
                        ->back()
                        ->with('error', 'คุณได้ลงเวลาการสอนชดเชยไปแล้ว');
                }

                return view('layouts.ta.attendances', compact('teaching', 'isExtra'));
            } else {
                // โค้ดเดิมสำหรับ teaching ปกติ
                $teaching = Teaching::with(['attendance' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                }])->findOrFail($teaching_id);

                if ($teaching->attendance) {
                    return redirect()
                        ->back()
                        ->with('error', 'คุณได้ลงเวลาการสอนไปแล้ว');
                }

                $isExtra = false;
                return view('layouts.ta.attendances', compact('teaching', 'isExtra'));
            }
        } catch (\Exception $e) {
            Log::error('Error in showAttendanceForm: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $e->getFile());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มลงเวลา: ' . $e->getMessage());
        }
    }

    // function for submit attendance
    public function submitAttendance(Request $request, $teaching_id)
    {
        try {
            DB::beginTransaction();

            // Validate request
            $request->validate([
                'status' => 'required|in:เข้าปฏิบัติการสอน,ลา',
                'note' => 'required|string|max:255',
            ]);

            $isExtra = $request->input('is_extra', '0') === '1';
            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();

            if ($isExtra) {
                // สำหรับ extra teaching
                $teaching = ExtraTeaching::where('extra_class_id', $teaching_id)->firstOrFail();
                $selectedDate = \Carbon\Carbon::parse($teaching->class_date);
                $classId = $teaching->class_id;

                // ตรวจสอบว่าเดือนนี้ได้รับการอนุมัติแล้วหรือไม่
                $isMonthApproved = Attendances::where('student_id', $student->id)
                    ->whereYear('created_at', $selectedDate->year)
                    ->whereMonth('created_at', $selectedDate->month)
                    ->where('approve_status', 'a')
                    ->exists();

                if ($isMonthApproved) {
                    return redirect()
                        ->back()
                        ->with('error', 'ไม่สามารถลงเวลาได้เนื่องจากการลงเวลาของเดือนนี้ได้รับการอนุมัติแล้ว');
                }

                // ตรวจสอบว่ามีการลงเวลาไปแล้วหรือไม่
                $exists = Attendances::where('extra_teaching_id', $teaching_id)
                    ->where('student_id', $student->id) // เพิ่ม condition นี้
                    ->exists();

                if ($exists) {
                    return redirect()
                        ->back()
                        ->with('error', 'คุณได้ลงเวลาการสอนชดเชยไปแล้ว');
                }

                // สร้าง attendance สำหรับ extra teaching ด้วยค่า extra_teaching_id
                $attendanceData = [
                    'status' => $request->status,
                    'note' => $request->note,
                    'teaching_id' => null,  // ชัดเจนว่าเป็น null
                    'extra_teaching_id' => $teaching_id,  // ใช้ extra_teaching_id
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'is_extra' => true,
                    'approve_at' => null,
                    'approve_status' => null,
                    'approve_user_id' => null
                ];

                // สร้าง log เพื่อตรวจสอบข้อมูลที่จะบันทึก
                Log::info('Creating attendance for extra teaching with data:', $attendanceData);

                Attendances::create($attendanceData);
            } else {
                // สำหรับ teaching ปกติ
                $teaching = Teaching::findOrFail($teaching_id);
                $selectedDate = \Carbon\Carbon::parse($teaching->start_time);
                $classId = $teaching->class_id;

                // ตรวจสอบว่าเดือนนี้ได้รับการอนุมัติแล้วหรือไม่
                $isMonthApproved = Attendances::where('student_id', $student->id)
                    ->whereYear('created_at', $selectedDate->year)
                    ->whereMonth('created_at', $selectedDate->month)
                    ->where('approve_status', 'a')
                    ->exists();

                if ($isMonthApproved) {
                    return redirect()
                        ->back()
                        ->with('error', 'ไม่สามารถลงเวลาได้เนื่องจากการลงเวลาของเดือนนี้ได้รับการอนุมัติแล้ว');
                }

                // ตรวจสอบว่ามีการลงเวลาไปแล้วหรือไม่
                $exists = Attendances::where('teaching_id', $teaching_id)
                    ->where('student_id', $student->id) // เพิ่ม condition นี้
                    ->exists();

                if ($exists) {
                    return redirect()
                        ->back()
                        ->with('error', 'คุณได้ลงเวลาการสอนไปแล้ว');
                }

                // สร้าง attendance สำหรับ teaching ปกติด้วยค่า teaching_id
                $attendanceData = [
                    'status' => $request->status,
                    'note' => $request->note,
                    'teaching_id' => $teaching_id,
                    'extra_teaching_id' => null,  // ชัดเจนว่าเป็น null
                    'user_id' => $user->id,
                    'student_id' => $student->id,
                    'is_extra' => false,
                    'approve_at' => null,
                    'approve_status' => null,
                    'approve_user_id' => null
                ];

                // สร้าง log เพื่อตรวจสอบข้อมูลที่จะบันทึก
                Log::info('Creating attendance for regular teaching with data:', $attendanceData);

                Attendances::create($attendanceData);
            }

            DB::commit();

            $selectedMonth = $request->input('selected_month');

            // redirect กลับไปยังหน้า teaching พร้อมกับส่งค่าเดือนที่เลือกไว้
            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $classId,
                    'month' => $selectedMonth
                ])
                ->with('success', 'บันทึกการลงเวลาสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in submitAttendance: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $e->getFile());

            // บันทึกข้อมูลเพิ่มเติมเกี่ยวกับตัวแปรที่เป็นปัญหา
            if (isset($teaching_id)) {
                Log::error('teaching_id: ' . $teaching_id);
            }
            if (isset($isExtra)) {
                Log::error('isExtra: ' . ($isExtra ? 'true' : 'false'));
            }

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
        }
    }

    // function for edit profile
    public function edit()
    {
        $user = Auth::user();
        $student = null;

        if ($user->type == 0) { // ถ้าเป็น TA
            $student = Students::where('user_id', $user->id)->first();
        }

        return view('layouts.ta.profile', compact('user', 'student'));
    }

    // function for update profile
    public function update(Request $request)
    {
        $request->validate([
            'prefix' => 'nullable|string|max:256',
            'name' => 'required|string|max:1024',
            'card_id' => 'nullable|string|max:13',
            'phone' => 'nullable|string|max:11',
            'student_id' => 'nullable|string|max:11',
            'email' => 'required|email|unique:users,email,' . Auth::id(),
            'degree_level' => 'nullable|string|max:256',
        ]);

        $user = Auth::user();

        DB::beginTransaction();
        try {
            $userUpdateData = [
                'prefix' => $request->prefix,
                'name' => $request->name,
                'card_id' => $request->card_id,
                'phone' => $request->phone,
                'student_id' => $request->student_id,
                'email' => $request->email,
                'degree_level' => $request->degree_level
            ];

            User::where('id', $user->id)->update($userUpdateData);

            if ($request->filled('password')) {
                $request->validate([
                    'password' => 'required|min:8|confirmed'
                ]);
                User::where('id', $user->id)->update([
                    'password' => Hash::make($request->password)
                ]);
            }

            if ($user->type === "user") {  // เปลี่ยนเงื่อนไขจาก type == 0 เป็น type === "user"
                $studentData = [
                    'prefix' => $request->prefix,
                    'name' => $request->name,
                    'student_id' => $request->student_id,
                    'card_id' => $request->card_id,
                    'phone' => $request->phone,
                    'email' => $request->email
                ];

                Students::updateOrCreate(
                    ['user_id' => $user->id],  // เงื่อนไขค้นหา
                    $studentData               // ข้อมูลที่จะอัพเดตหรือสร้างใหม่
                );
            }

            DB::commit();

            return redirect()->back()->with('success', 'อัพเดตข้อมูลเรียบร้อยแล้ว');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'เกิดข้อผิดพลาดในการอัพเดตข้อมูล: ' . $e->getMessage());
        }
    }

    // ส่วนของการลงเวลาปฏิบัติงานเพิ่มเติม
    // function for storing extra attendance entries
    public function storeExtraAttendance(Request $request)
    {
        try {
            $request->validate([
                'start_work' => 'required|date',
                'class_type' => 'required|string|in:L,C',
                'detail' => 'required|string|max:255',
                'duration' => 'required|integer|min:1',
                'student_id' => 'required|exists:students,id',
                'class_id' => 'required|exists:classes,class_id',
            ], [
                'start_work.required' => 'กรุณาระบุวันที่ปฏิบัติงาน',
                'class_type.required' => 'กรุณาเลือกประเภทรายวิชา',
                'detail.required' => 'กรุณากรอกรายละเอียดการปฏิบัติงาน',
                'duration.required' => 'กรุณาระบุระยะเวลาการปฏิบัติงาน'
            ]);

            DB::beginTransaction();

            $selectedDate = \Carbon\Carbon::parse($request->start_work);

            // Check if the month is already approved
            $isMonthApproved = Attendances::where('student_id', $request->student_id)
                ->whereYear('created_at', $selectedDate->year)
                ->whereMonth('created_at', $selectedDate->month)
                ->where('approve_status', 'a')
                ->exists();

            if ($isMonthApproved) {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถลงเวลาเพิ่มเติมได้เนื่องจากการลงเวลาของเดือนนี้ได้รับการอนุมัติแล้ว');
            }

            // Calculate end time based on start time and duration
            $startTime = \Carbon\Carbon::parse($request->start_work);
            $endTime = $startTime->copy()->addMinutes($request->duration);

            // Check for overlap with regular teaching sessions
            $overlappingTeaching = Teaching::where('class_id', $request->class_id)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->whereBetween('start_time', [$startTime, $endTime])
                        ->orWhereBetween('end_time', [$startTime, $endTime])
                        ->orWhere(function ($q) use ($startTime, $endTime) {
                            $q->where('start_time', '<=', $startTime)
                                ->where('end_time', '>=', $endTime);
                        });
                })
                ->first();

            if ($overlappingTeaching) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'เวลาที่เลือกซ้ำซ้อนกับตารางสอนปกติที่มีอยู่แล้ว');
            }

            // Check for overlap with extra teaching sessions
            $overlappingExtraTeaching = ExtraTeaching::where('class_id', $request->class_id)
                ->where(function ($query) use ($startTime, $endTime) {
                    // Create datetime objects for comparison
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $q->whereRaw(
                            "CONCAT(class_date, ' ', start_time) <= ? AND CONCAT(class_date, ' ', end_time) >= ?",
                            [$endTime->format('Y-m-d H:i:s'), $startTime->format('Y-m-d H:i:s')]
                        );
                    });
                })
                ->first();

            if ($overlappingExtraTeaching) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'เวลาที่เลือกซ้ำซ้อนกับตารางสอนชดเชยที่มีอยู่แล้ว');
            }

            // Check for overlap with other extra attendances
            $overlappingExtraAttendance = ExtraAttendances::where('class_id', $request->class_id)
                ->where('student_id', $request->student_id)
                ->where(function ($query) use ($startTime, $endTime) {
                    $query->where(function ($q) use ($startTime, $endTime) {
                        $durationInSeconds = 'duration * 60'; // Convert minutes to seconds
                        $q->whereRaw(
                            "start_work <= ? AND DATE_ADD(start_work, INTERVAL $durationInSeconds SECOND) >= ?",
                            [$endTime->format('Y-m-d H:i:s'), $startTime->format('Y-m-d H:i:s')]
                        );
                    });
                })
                ->first();

            if ($overlappingExtraAttendance) {
                return redirect()
                    ->back()
                    ->withInput()
                    ->with('error', 'เวลาที่เลือกซ้ำซ้อนกับการลงเวลาเพิ่มเติมที่มีอยู่แล้ว');
            }

            // Create the extra attendance record if no conflicts
            $extraAttendance = ExtraAttendances::create([
                'start_work' => $request->start_work,
                'class_type' => $request->class_type,
                'detail' => $request->detail,
                'duration' => $request->duration,
                'student_id' => $request->student_id,
                'class_id' => $request->class_id,
                'approve_status' => null,
                'approve_at' => null,
                'approve_user_id' => null,
                'approve_note' => null
            ]);

            DB::commit();

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $request->class_id,
                    'month' => $request->input('selected_month')
                ])
                ->with('success', 'บันทึกการลงเวลาเพิ่มเติมสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in storeExtraAttendance: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
        }
    }

    // function for display form edit attendance
    public function editAttendance($teaching_id, Request $request)
    {
        try {
            $isExtra = $request->query('is_extra', false);
            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->first();

            if ($isExtra) {
                // ExtraTeaching now properly recognizes extra_class_id as its primary key
                $extraTeaching = ExtraTeaching::findOrFail($teaching_id);

                // Custom query to get attendance for extra teaching
                $attendance = Attendances::where('extra_teaching_id', $teaching_id)
                    ->where('student_id', $student->id)
                    ->first();

                if (!$attendance) {
                    return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาสำหรับการสอนชดเชย');
                }

                // Create a teaching object with similar structure to regular teaching
                $teaching = (object)[
                    'teaching_id' => $extraTeaching->extra_class_id,
                    'start_time' => $extraTeaching->class_date . ' ' . $extraTeaching->start_time,
                    'end_time' => $extraTeaching->class_date . ' ' . $extraTeaching->end_time,
                    'duration' => $extraTeaching->duration,
                    'class_type' => 'E',
                    'class_id' => $extraTeaching->class_id,
                    'is_extra' => true,
                    'attendance' => $attendance
                ];

                return view('layouts.ta.edit-extra-teaching', compact('teaching', 'isExtra'));
            } else {
                // Original code for regular teaching
                $teaching = Teaching::with(['attendance' => function ($query) use ($student) {
                    $query->where('student_id', $student->id);
                }])->findOrFail($teaching_id);

                if (!$teaching->attendance) {
                    return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลา');
                }

                return view('layouts.ta.edit-attendance', compact('teaching'));
            }
        } catch (\Exception $e) {
            Log::error('Error in editAttendance: ' . $e->getMessage() . ' at line ' . $e->getLine() . ' in file ' . $e->getFile());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มแก้ไข: ' . $e->getMessage());
        }
    }

    // function for update attendance data
    public function updateAttendance(Request $request, $teaching_id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();

            $attendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
                ->where('student_id', $student->id) // เพิ่มบรรทัดนี้
                ->first();

            if (!$attendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาของคุณ');
            }

            if ($attendance->approve_status === 'a') {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถแก้ไขการลงเวลาได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            $request->validate([
                'status' => 'required|in:เข้าปฏิบัติการสอน,ลา',
                'note' => 'required|string|max:255',
            ], [
                'status.required' => 'กรุณาเลือกสถานะการเข้าสอน',
                'status.in' => 'สถานะการเข้าสอนไม่ถูกต้อง',
                'note.required' => 'กรุณากรอกงานที่ปฏิบัติ',
                'note.max' => 'งานที่ปฏิบัติต้องไม่เกิน 255 ตัวอักษร'
            ]);

            $attendance->update([
                'status' => $request->status,
                'note' => $request->note,
            ]);

            $teaching = Teaching::findOrFail($teaching_id);

            DB::commit();

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $teaching->class_id,
                    'month' => $request->input('selected_month')
                ])
                ->with('success', 'อัปเดตการลงเวลาสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateAttendance: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล');
        }
    }

    // function for update extra teaching attendance data
    public function updateExtraTeachingAttendance(Request $request, $teaching_id)
    {
        try {
            DB::beginTransaction();
            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();

            // Validate request
            $request->validate([
                'status' => 'required|in:เข้าปฏิบัติการสอน,ลา',
                'note' => 'required|string|max:255',
            ]);

            // Find the attendance record for this extra teaching
            $attendance = Attendances::where('extra_teaching_id', $teaching_id)
                ->where('student_id', $student->id) // เพิ่มบรรทัดนี้
                ->first();

            if (!$attendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาสำหรับการสอนชดเชย');
            }

            // Check if attendance is already approved
            if ($attendance->approve_status === 'a') {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถแก้ไขการลงเวลาได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            // Get the extra teaching to find the class ID for redirect
            $extraTeaching = ExtraTeaching::findOrFail($teaching_id);

            // Update attendance
            $attendance->update([
                'status' => $request->status,
                'note' => $request->note,
            ]);

            DB::commit();

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $extraTeaching->class_id,
                    'month' => $request->input('selected_month')
                ])
                ->with('success', 'อัปเดตการลงเวลาการสอนชดเชยสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateExtraTeachingAttendance: ' . $e->getMessage() . ' at line ' . $e->getLine());
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $e->getMessage());
        }
    }

    // function for delete attendance data
    public function deleteAttendance($teaching_id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();

            $attendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
                ->where('student_id', $student->id) // เพิ่มบรรทัดนี้
                ->first();

            if (!$attendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาของคุณ');
            }

            if ($attendance->approve_status === 'a') {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถลบการลงเวลาได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            $attendance->delete();

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'ลบการลงเวลาสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in deleteAttendance: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
        }
    }

    // function for delete extra teaching attendance data
    public function deleteExtraTeachingAttendance($teaching_id, Request $request)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();

            // ค้นหาข้อมูลการลงเวลาสำหรับการสอนชดเชย
            $attendance = Attendances::where('extra_teaching_id', $teaching_id)
                ->where('student_id', $student->id) // เพิ่มบรรทัดนี้
                ->first();

            if (!$attendance) {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่พบข้อมูลการลงเวลาสำหรับการสอนชดเชย');
            }

            // ตรวจสอบว่าได้รับการอนุมัติแล้วหรือไม่
            if ($attendance->approve_status === 'a') {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถลบการลงเวลาได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            // หาข้อมูล class_id เพื่อใช้ในการ redirect กลับ
            $extraTeaching = ExtraTeaching::findOrFail($teaching_id);
            $classId = $extraTeaching->class_id;

            // ลบข้อมูลการลงเวลา
            $attendance->delete();

            DB::commit();

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $classId,
                    'month' => $request->input('selected_month')
                ])
                ->with('success', 'ลบการลงเวลาการสอนชดเชยสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in deleteExtraTeachingAttendance: ' . $e->getMessage() . ' at line ' . $e->getLine());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูลการลงเวลาสอนชดเชย: ' . $e->getMessage());
        }
    }

    // function for display form edit extra attendance
    public function editExtraAttendance($id)
    {
        try {
            // ใช้ find แทน findOrFail และเพิ่มการตรวจสอบเพื่อป้องกัน error
            $extraAttendance = ExtraAttendances::find($id);

            if (!$extraAttendance) {
                Log::error("ExtraAttendance with ID {$id} not found");
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาเพิ่มเติม');
            }

            return view('layouts.ta.edit-extra-attendance', compact('extraAttendance'));
        } catch (\Exception $e) {
            Log::error('Error in editExtraAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มแก้ไข: ' . $e->getMessage());
        }
    }

    // function for update extra attendance data
    public function updateExtraAttendance(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            // ค้นหาและตรวจสอบการมีอยู่ของข้อมูล
            $extraAttendance = ExtraAttendances::find($id);
            if (!$extraAttendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาเพิ่มเติม');
            }

            $request->validate([
                'start_work' => 'required|date',
                'class_type' => 'required|string|in:L,C',
                'detail' => 'required|string|max:255',
                'duration' => 'required|integer|min:1',
            ]);

            // ตรวจสอบว่าได้รับการอนุมัติแล้วหรือไม่
            if ($extraAttendance->approve_status === 'a') {
                return redirect()->back()->with('error', 'ไม่สามารถแก้ไขการลงเวลาเพิ่มเติมได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            $extraAttendance->update([
                'start_work' => $request->start_work,
                'class_type' => $request->class_type,
                'detail' => $request->detail,
                'duration' => $request->duration,
            ]);

            DB::commit();

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $extraAttendance->class_id,
                    'month' => $request->input('selected_month')
                ])
                ->with('success', 'อัปเดตการลงเวลาเพิ่มเติมสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in updateExtraAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล: ' . $e->getMessage());
        }
    }

    // function for delete extra attendance data
    public function deleteExtraAttendance($id)
    {
        try {
            DB::beginTransaction();

            // ค้นหาและตรวจสอบการมีอยู่ของข้อมูล
            $extraAttendance = ExtraAttendances::find($id);
            if (!$extraAttendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาเพิ่มเติม');
            }

            // ตรวจสอบว่าได้รับการอนุมัติแล้วหรือไม่
            if ($extraAttendance->approve_status === 'a') {
                return redirect()->back()->with('error', 'ไม่สามารถลบการลงเวลาเพิ่มเติมได้เนื่องจากได้รับการอนุมัติแล้ว');
            }

            // เก็บ class_id ไว้สำหรับการ redirect
            $classId = $extraAttendance->class_id;

            // ลบข้อมูล
            $extraAttendance->delete();

            DB::commit();

            // รับค่า selected_month จาก request
            $selectedMonth = request('selected_month');

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $classId,
                    'month' => $selectedMonth
                ])
                ->with('success', 'ลบการลงเวลาเพิ่มเติมสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in deleteExtraAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล: ' . $e->getMessage());
        }
    }
}
