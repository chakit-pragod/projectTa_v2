<?php

namespace App\Http\Controllers;

use App\Models\Attendances;
use Illuminate\Support\Facades\Hash;
use App\Models\Classes;
use App\Models\Courses;
use App\Models\CourseTaClasses;
use App\Models\ExtraAttendances;
use App\Models\Teaching;
use Illuminate\Http\Request;
use App\Models\Subjects;
use App\Models\Students;
use App\Models\Announce;
use App\Models\Requests;
use App\Models\CourseTas;
use App\Models\User;
use App\Models\Semesters;
use App\Models\Major;
use App\Models\Teachers;
use Illuminate\Support\Facades\{Auth, DB, Log};
use Illuminate\Support\Str;
use App\Services\TDBMApiService;
use Yoeunes\Toastr\Facades\Toastr;


class TaController extends Controller
{
    private $tdbmService;

    public function __construct(TDBMApiService $tdbmService)
    {
        $this->middleware('auth');
        $this->tdbmService = $tdbmService;
    }

    public function request()
    {
        try {
            $currentSemester = collect($this->tdbmService->getSemesters())
                // ตรวจสอบว่าวันที่ปัจจุบันอยู่ระหว่าง start_date และ end_date
                ->filter(function ($semester) {
                    $startDate = \Carbon\Carbon::parse($semester['start_date']);
                    $endDate = \Carbon\Carbon::parse($semester['end_date']);
                    $now = \Carbon\Carbon::now();
                    return $now->between($startDate, $endDate);
                })->first();

            if (!$currentSemester) { //ตรวจสอบว่ามีเทอมปัจจุบันหรือไม่
                return redirect()->back()->with('error', 'ขณะนี้ไม่อยู่ในช่วงเวลารับสมัคร TA');
            }

            //ดึงข้อมูลที่เกี่ยวข้องกับเทอมปัจจุบัน
            $subjects = collect($this->tdbmService->getSubjects());
            $courses = collect($this->tdbmService->getCourses())
                ->where('semester_id', $currentSemester['semester_id']);
            $studentClasses = collect($this->tdbmService->getStudentClasses())
                ->where('semester_id', $currentSemester['semester_id']);

            // ดึงข้อมูล major จากฐานข้อมูล
            $majors = Major::all();

            // กรองเฉพาะวิชาที่มีการเปิดสอนในเทอมปัจจุบัน
            $subjectsWithSections = $subjects
                ->filter(function ($subject) use ($courses) {
                    return $courses->where('subject_id', $subject['subject_id'])->isNotEmpty();
                })
                ->map(function ($subject) use ($courses, $studentClasses, $majors) {
                    $course = $courses->where('subject_id', $subject['subject_id'])->first();
                    $sections = $studentClasses->where('course_id', $course['course_id'])
                        ->map(function ($class) use ($majors) {
                            $major = $majors->firstWhere('major_id', $class['major_id']);
                            return [
                                'section_num' => $class['section_num'],
                                'major' => $major ? $major['name_th'] : 'ไม่ระบุสาขา'
                            ];
                        })
                        ->unique('section_num')
                        ->values()
                        ->toArray();

                    return [
                        'subject' => [
                            'subject_id' => $subject['subject_id'],
                            'subject_name_en' => $subject['name_en'],
                            'subject_name_th' => $subject['name_th']
                        ],
                        'sections' => $sections
                    ];
                })->values();

            return view('layouts.ta.request', compact('subjectsWithSections', 'currentSemester'));
        } catch (\Exception $e) {
            Log::error('Error in request method: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการดึงข้อมูล');
        }
    }
    public function taSubject()
    {
        return view('layouts.ta.taSubject');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function attendances()
    {
        return view('layouts.ta.attendances');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function showAnnounces()
    {
        $announces = Announce::orderBy('created_at', 'desc')->get();
        return view('home', compact('announces'));
    }
    public function showRequestForm()
    {
        $user = Auth::user();
        return view('layouts.ta.request', compact('user'));
    }

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
            $courses = collect($this->tdbmService->getCourses());
            $studentClasses = collect($this->tdbmService->getStudentClasses());
            $subjects = collect($this->tdbmService->getSubjects());

            $currentSemester = collect($this->tdbmService->getSemesters())
                ->filter(function ($semester) {
                    $startDate = \Carbon\Carbon::parse($semester['start_date']);
                    $endDate = \Carbon\Carbon::parse($semester['end_date']);
                    return now()->between($startDate, $endDate);
                })->first();

            if (!$currentSemester) {
                Toastr::error('ไม่อยู่ในช่วงเวลารับสมัคร', 'เกิดข้อผิดพลาด!');
                return redirect()->back();
            }

            $localSemester = Semesters::firstOrCreate(
                [
                    'semester_id' => $currentSemester['semester_id']
                ],
                [
                    'year' => intval($currentSemester['year']),
                    'semesters' => intval($currentSemester['semester']), // แก้จาก semester เป็น semesters
                    'start_date' => $currentSemester['start_date'],
                    'end_date' => $currentSemester['end_date']
                ]
            );

            $student = Students::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'prefix' => $user->prefix,
                    'name' => $user->name,
                    'student_id' => $user->student_id,
                    'email' => $user->email,
                    'card_id' => $user->card_id,
                    'phone' => $user->phone,
                    'degree_level' => 'bachelor'
                ]
            );

            $applications = $request->input('applications');

            $currentCourseCount = CourseTas::where('student_id', $student->id)->count();
            if ($currentCourseCount + count($applications) > 3) {
                Toastr()->warning('คุณไม่สามารถสมัครเป็นผู้ช่วยสอนได้เกิน 3 วิชา', 'คำเตือน!');
                return redirect()->back();
            }

            foreach ($applications as $application) {
                $subjectId = $application['subject_id'];
                $sectionNums = $application['sections'];

                $course = $courses->where('subject_id', $subjectId)
                    ->where('semester_id', $currentSemester['semester_id'])
                    ->first();

                if (!$course) {
                    DB::rollBack();
                    Toastr()->error('ไม่พบรายวิชา ' . $subjectId . ' ในระบบ', 'เกิดข้อผิดพลาด!');
                    return redirect()->back();
                }

                $subjectData = $subjects->where('subject_id', $course['subject_id'])->first();
                if (!$subjectData) {
                    DB::rollBack();
                    Toastr()->error('ไม่พบข้อมูลรายวิชา ' . $subjectId, 'เกิดข้อผิดพลาด!');
                    return redirect()->back();
                }

                $existingTA = CourseTas::where('student_id', $student->id)
                    ->where('course_id', $course['course_id'])
                    ->first();

                if ($existingTA) {
                    DB::rollBack();
                    Toastr()->warning('คุณได้สมัครเป็นผู้ช่วยสอนในวิชา ' . $subjectId . ' แล้ว', 'คำเตือน!');
                    return redirect()->back();
                }

                $localSubject = Subjects::firstOrCreate(
                    ['subject_id' => $subjectData['subject_id']],
                    [
                        'name_th' => $subjectData['name_th'],
                        'name_en' => $subjectData['name_en'],
                        'credit' => $subjectData['credit'],
                        'cur_id' => $subjectData['cur_id'],
                        'weight' => $subjectData['weight'] ?? null,
                        'detail' => $subjectData['detail'] ?? null,
                        'status' => 'A'
                    ]
                );

                Log::info('Course data from API:', [
                    'course' => $course,
                    'owner_teacher_id' => $course['owner_teacher_id'] ?? null
                ]);

                if (!isset($course['owner_teacher_id']) || empty($course['owner_teacher_id'])) {
                    Log::error('Missing owner_teacher_id:', [
                        'course_id' => $course['course_id'],
                        'subject_id' => $subjectId
                    ]);
                    DB::rollBack();
                    Toastr()->error('ไม่พบข้อมูลอาจารย์ผู้สอนสำหรับรายวิชา ' . $subjectId, 'เกิดข้อผิดพลาด!');
                    return redirect()->back();
                }

                $localCourse = Courses::firstOrCreate(
                    ['course_id' => $course['course_id']],
                    [
                        'subject_id' => $localSubject->subject_id,
                        'semester_id' => $localSemester->semester_id,
                        'owner_teacher_id' => $course['owner_teacher_id'], // ต้องไม่เป็น null
                        'major_id' => $course['major_id'] ?: null,  // ถ้าไม่มีให้เป็น null
                        'cur_id' => $course['cur_id'] ?: '1',      // ถ้าไม่มีให้เป็น '1'
                        'status' => $course['status'] ?: 'A'       // ถ้าไม่มีให้เป็น 'A'
                    ]
                );

                $courseTA = CourseTas::create([
                    'student_id' => $student->id,
                    'course_id' => $localCourse->course_id,
                ]);

                foreach ($sectionNums as $sectionNum) {
                    $class = $studentClasses->where('course_id', $course['course_id'])
                        ->where('section_num', $sectionNum)
                        ->first();

                    if (!$class) {
                        DB::rollBack();
                        Toastr()->error('ไม่พบเซคชัน ' . $sectionNum . ' สำหรับวิชา ' . $subjectId, 'เกิดข้อผิดพลาด!');
                        return redirect()->back();
                    }

                    $localClass = Classes::firstOrCreate(
                        ['class_id' => $class['class_id']],
                        [
                            'section_num' => $class['section_num'],
                            'course_id' => $localCourse->course_id,
                            'title' => $class['title'] ?? null,
                            'status' => $class['status'],
                            'open_num' => $class['open_num'] ?? 0,        // เพิ่มฟิลด์ที่จำเป็น
                            'enrolled_num' => $class['enrolled_num'] ?? 0, // กำหนดค่าเริ่มต้นเป็น 0
                            'available_num' => $class['available_num'] ?? 0,
                            'teacher_id' => $class['teacher_id'],         // ต้องระบุค่า foreign key
                            'semester_id' => $class['semester_id'],       // ต้องระบุค่า foreign key
                            'major_id' => $class['major_id']             // ต้องระบุค่า foreign key
                        ]
                    );

                    $courseTaClass = CourseTaClasses::create([
                        'class_id' => $localClass->class_id,
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

            DB::beginTransaction();

            // ดึงข้อมูลการสอนจากฐานข้อมูลโดยตรงแทนการดึงจาก API
            $query = Teaching::with(['class', 'teacher'])
                ->where('class_id', $id);

            if ($selectedMonth) {
                $query->whereMonth('start_time', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
            }

            $localTeachings = $query->orderBy('start_time', 'asc')->get();

            // ดึงข้อมูลการลงเวลาของผู้ใช้ปัจจุบัน
            $userAttendances = Attendances::where('student_id', $student->id)
                ->whereIn('teaching_id', $localTeachings->pluck('teaching_id'))
                ->get()
                ->keyBy('teaching_id');

            // ตรวจสอบว่ามีข้อมูลการสอนหรือไม่
            if ($localTeachings->isEmpty()) {
                $extraAttendances = ExtraAttendances::where('class_id', $id)
                    ->when($selectedMonth, function ($query) use ($selectedMonth) {
                        return $query->whereMonth('start_work', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
                    })
                    ->get();

                if ($extraAttendances->isEmpty()) {
                    session()->flash('info', 'ยังไม่มีข้อมูลการสอนสำหรับรายวิชานี้');
                    return view('layouts.ta.teaching', [
                        'teachings' => [],
                        'selectedMonth' => $selectedMonth
                    ]);
                }
            }

            $formattedTeachings = $localTeachings->map(function ($teaching) use ($userAttendances) {
                // ดึงข้อมูลคลาสและอาจารย์จากฐานข้อมูลโดยตรง
                $class = $teaching->class;
                $teacher = $teaching->teacher;

                // ดึงข้อมูลการลงเวลาเฉพาะของผู้ใช้ปัจจุบัน
                $userAttendance = $userAttendances->get($teaching->teaching_id);

                return (object) [
                    'id' => $teaching->teaching_id,
                    'start_time' => $teaching->start_time,
                    'end_time' => $teaching->end_time,
                    'duration' => $teaching->duration,
                    'class_type' => $teaching->class_type,
                    'class_id' => (object) [
                        'title' => $class ? $class->title : 'N/A',
                    ],
                    'teacher_id' => (object) [
                        'position' => $teacher ? $teacher->position : '',
                        'degree' => $teacher ? $teacher->degree : '',
                        'name' => $teacher ? $teacher->name : 'N/A',
                    ],
                    'attendance' => $userAttendance ? (object) [
                        'status' => $userAttendance->status,
                        'note' => $userAttendance->note ?? '',
                        'approve_status' => $userAttendance->approve_status ?? null
                    ] : null,
                    'is_extra_attendance' => false,
                    'has_user_attendance' => $userAttendance ? true : false
                ];
            });

            // สำหรับ extra attendances ดึงข้อมูลจากฐานข้อมูลโดยตรง
            $userExtraAttendances = ExtraAttendances::where('class_id', $id)
                ->where('student_id', $student->id)
                ->when($selectedMonth, function ($query) use ($selectedMonth) {
                    return $query->whereMonth('start_work', \Carbon\Carbon::parse("1-{$selectedMonth}-2024")->month);
                })
                ->get();

            $formattedExtraAttendances = $userExtraAttendances->map(function ($attendance) {
                // ดึงข้อมูลคลาส
                $class = Classes::find($attendance->class_id);

                // ดึงข้อมูลคอร์สและอาจารย์
                $course = null;
                $teacher = null;

                if ($class) {
                    $course = Courses::find($class->course_id);
                    if ($course) {
                        $teacher = Teachers::find($course->owner_teacher_id);
                    }
                }

                return (object) [
                    'id' => 'extra_' . $attendance->id,
                    'start_time' => $attendance->start_work,
                    'end_time' => \Carbon\Carbon::parse($attendance->start_work)
                        ->addMinutes($attendance->duration),
                    'duration' => $attendance->duration,
                    'class_type' => $attendance->class_type,
                    'class_id' => (object) [
                        'title' => $class ? $class->title : 'N/A',
                    ],
                    'teacher_id' => (object) [
                        'position' => $teacher ? $teacher->position : '',
                        'degree' => $teacher ? $teacher->degree : '',
                        'name' => $teacher ? $teacher->name : 'N/A'
                    ],
                    'attendance' => (object) [
                        'status' => 'เข้าปฏิบัติการสอน',
                        'note' => $attendance->detail,
                        'approve_status' => $attendance->approve_status ?? null
                    ],
                    'is_extra_attendance' => true,
                    'has_user_attendance' => true
                ];
            });

            $allRecords = $formattedTeachings->concat($formattedExtraAttendances)
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

    public function showAttendanceForm($teaching_id)
    {
        try {
            $teaching = Teaching::findOrFail($teaching_id);
            $user = Auth::user();

            // ตรวจสอบว่ามีการลงเวลาแล้วหรือไม่
            $existingAttendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingAttendance) {
                return redirect()
                    ->back()
                    ->with('error', 'คุณได้ลงเวลาการสอนไปแล้ว');
            }

            return view('layouts.ta.attendances', compact('teaching'));
        } catch (\Exception $e) {
            Log::error('Error in showAttendanceForm: ' . $e->getMessage());
            return redirect()
                ->back()
                ->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มลงเวลา');
        }
    }

    public function submitAttendance(Request $request, $teaching_id)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'status' => 'required|in:เข้าปฏิบัติการสอน,ลา',
                'note' => 'required|string|max:255',
            ], [
                'status.required' => 'กรุณาเลือกสถานะการเข้าสอน',
                'note.required' => 'กรุณากรอกงานที่ปฏิบัติ',
                'note.max' => 'งานที่ปฏิบัติต้องไม่เกิน 255 ตัวอักษร'
            ]);

            $user = Auth::user();
            $student = Students::where('user_id', $user->id)->firstOrFail();
            $teaching = Teaching::findOrFail($teaching_id);

            $selectedDate = \Carbon\Carbon::parse($teaching->start_time);

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

            // ตรวจสอบว่าผู้ใช้ได้ลงเวลาแล้วหรือยัง
            $existingAttendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingAttendance) {
                return redirect()
                    ->back()
                    ->with('error', 'คุณได้ลงเวลาการสอนไปแล้ว');
            }

            Attendances::create([
                'status' => $request->status,
                'note' => $request->note,
                'teaching_id' => $teaching_id,
                'user_id' => $user->id,
                'student_id' => $student->id,
                'approve_at' => null,
                'approve_user_id' => null
            ]);

            DB::commit();

            $selectedMonth = $request->input('selected_month');

            return redirect()
                ->route('layout.ta.teaching', [
                    'id' => $teaching->class_id,
                    'month' => $selectedMonth
                ])
                ->with('success', 'บันทึกการลงเวลาสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in submitAttendance: ' . $e->getMessage());
            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล');
        }
    }

    public function edit()
    {
        $user = Auth::user();
        $student = null;

        if ($user->type == 0) { // ถ้าเป็น TA
            $student = Students::where('user_id', $user->id)->first();
        }

        return view('layouts.ta.profile', compact('user', 'student'));
    }

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

            $isMonthApproved = Attendances::where('student_id', $request->student_id)
                ->whereYear('created_at', $selectedDate->year)
                ->whereMonth('created_at', $selectedDate->month)
                ->where('approve_status', 'a')
                ->exists();

            // 3. เพิ่มเงื่อนไขนี้เพื่อไม่ให้ลงเวลาเพิ่มในเดือนที่ถูก approve แล้ว
            if ($isMonthApproved) {
                return redirect()
                    ->back()
                    ->with('error', 'ไม่สามารถลงเวลาเพิ่มเติมได้เนื่องจากการลงเวลาของเดือนนี้ได้รับการอนุมัติแล้ว');
            }

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

    public function editAttendance($teaching_id)
    {
        try {
            $user = Auth::user();
            $attendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
                ->first();

            if (!$attendance) {
                return redirect()->back()->with('error', 'ไม่พบข้อมูลการลงเวลาของคุณ');
            }

            $teaching = Teaching::findOrFail($teaching_id);

            return view('layouts.ta.edit-attendance', compact('teaching', 'attendance'));
        } catch (\Exception $e) {
            Log::error('Error in editAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มแก้ไข');
        }
    }

    public function updateAttendance(Request $request, $teaching_id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $attendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
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

    public function deleteAttendance($teaching_id)
    {
        try {
            DB::beginTransaction();

            $user = Auth::user();
            $attendance = Attendances::where('teaching_id', $teaching_id)
                ->where('user_id', $user->id)
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

    public function editExtraAttendance($id)
    {
        try {
            $extraAttendance = ExtraAttendances::findOrFail($id);
            return view('layouts.ta.edit-extra-attendance', compact('extraAttendance'));
        } catch (\Exception $e) {
            Log::error('Error in editExtraAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการแสดงฟอร์มแก้ไข');
        }
    }

    public function updateExtraAttendance(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $request->validate([
                'start_work' => 'required|date',
                'class_type' => 'required|string|in:L,C',
                'detail' => 'required|string|max:255',
                'duration' => 'required|integer|min:1',
            ]);

            $extraAttendance = ExtraAttendances::findOrFail($id);

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
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการอัปเดตข้อมูล');
        }
    }

    public function deleteExtraAttendance($id)
    {
        try {
            DB::beginTransaction();

            $extraAttendance = ExtraAttendances::findOrFail($id);
            $extraAttendance->delete();

            DB::commit();

            return redirect()
                ->back()
                ->with('success', 'ลบการลงเวลาเพิ่มเติมสำเร็จ');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in deleteExtraAttendance: ' . $e->getMessage());
            return redirect()->back()->with('error', 'เกิดข้อผิดพลาดในการลบข้อมูล');
        }
    }
}
