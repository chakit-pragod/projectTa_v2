@extends('layouts.adminLayout')

@section('title', 'ข้อมูลผู้ช่วยสอนรายบุคคล')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="mb-0">ข้อมูลผู้ช่วยสอน</h4>
                    </div>

                    <div class="card-body">
                        <!-- ข้อมูลส่วนตัวและรายวิชา -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">ข้อมูลผู้ช่วยสอน</h5>

                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">รหัสนักศึกษา:</div>
                                    <div class="col-7">{{ $student->student_id }}</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">ชื่อ-นามสกุล:</div>
                                    <div class="col-7">{{ $student->name }}</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">ระดับการศึกษา:</div>
                                    <div class="col-7">
                                        @php
                                            $degreeLevel = $student->degree_level ?? 'undergraduate';
                                            $isGraduate = in_array($degreeLevel, ['master', 'doctoral', 'graduate']);
                                            $degreeText = $isGraduate ? 'บัณฑิตศึกษา' : 'ปริญญาตรี';
                                        @endphp
                                        {{ $degreeText }}
                                    </div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">อีเมล:</div>
                                    <div class="col-7">{{ $student->email }}</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">เบอร์โทรศัพท์:</div>
                                    <div class="col-7">{{ $student->phone ?? 'ไม่ระบุ' }}</div>
                                </div>
                                @if ($student->disbursements && $student->disbursements->id)
                                    <div class="mb-2 row">
                                        <div class="col-5 fw-bold">เอกสารสำคัญ:</div>
                                        <div class="col-7">
                                            <a href="{{ route('layout.ta.download-document', $student->disbursements->id) }}"
                                                class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> ดาวน์โหลดเอกสาร
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">ข้อมูลรายวิชาและงบประมาณ</h5>

                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">รหัสวิชา:</div>
                                    <div class="col-7">{{ $course->subjects->subject_id }}</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">ชื่อวิชา:</div>
                                    <div class="col-7">{{ $course->subjects->name_en }}</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">จำนวน นศ. ทั้งหมด:</div>
                                    <div class="col-7">{{ number_format($totalStudents) }} คน</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">งบประมาณรายวิชา:</div>
                                    <div class="col-7">{{ number_format($totalBudget, 2) }} บาท</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">จำนวนผู้ช่วยสอน:</div>
                                    <div class="col-7">{{ $totalTAs }} คน</div>
                                </div>
                                <div class="mb-2 row">
                                    <div class="col-5 fw-bold">งบประมาณคงเหลือรายวิชา:</div>
                                    <div class="col-7 {{ $remainingBudget < 1000 ? 'text-danger fw-bold' : '' }}">
                                        {{ number_format($remainingBudget, 2) }} บาท
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ตัวเลือกเดือน -->
                        <form method="GET" class="d-flex align-items-center">
                            <div class="d-flex align-items-center">
                                <label for="month" class="me-2"><strong>เลือกเดือน:</strong></label>
                                <select name="month" id="month" class="form-select" style="width: 200px;">
                                    @foreach ($monthsInSemester as $yearMonth => $monthName)
                                        <option value="{{ $yearMonth }}"
                                            {{ $selectedYearMonth == $yearMonth ? 'selected' : '' }}>
                                            {{ $monthName }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <!-- เพิ่มบรรทัดนี้เพื่อส่ง course_id ไปด้วยเมื่อเปลี่ยนเดือน -->
                            <input type="hidden" name="course_id" value="{{ $course->course_id }}">
                            <button type="submit" class="btn btn-primary ms-2">แสดงข้อมูล</button>
                        </form>

                        <!-- แสดงข้อมูลการลงเวลา -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">ข้อมูลการลงเวลาประจำเดือน</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-12">
                                        <ul class="nav nav-tabs" id="attendanceTabs" role="tablist">
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link active" id="regular-tab" data-bs-toggle="tab"
                                                    data-bs-target="#regular-content" type="button" role="tab"
                                                    aria-controls="regular-content" aria-selected="true">
                                                    โครงการปกติ
                                                </button>
                                            </li>
                                            <li class="nav-item" role="presentation">
                                                <button class="nav-link" id="special-tab" data-bs-toggle="tab"
                                                    data-bs-target="#special-content" type="button" role="tab"
                                                    aria-controls="special-content" aria-selected="false">
                                                    โครงการพิเศษ
                                                </button>
                                            </li>
                                        </ul>

                                        <div class="tab-content p-3 border border-top-0 rounded-bottom">
                                            <!-- โครงการปกติ -->
                                            <div class="tab-pane fade show active" id="regular-content" role="tabpanel"
                                                aria-labelledby="regular-tab">
                                                @php
                                                    $regularAttendances = $attendancesBySection
                                                        ->map(function ($sectionAttendances) {
                                                            return $sectionAttendances
                                                                ->filter(function ($attendance) {
                                                                    // กรณี attendance ปกติ
                                                                    if ($attendance['type'] === 'regular') {
                                                                        // Check if class and major exist
                                                                        if (
                                                                            $attendance['data'] &&
                                                                            $attendance['data']->class &&
                                                                            $attendance['data']->class->major
                                                                        ) {
                                                                            return $attendance['data']->class->major
                                                                                ->major_type !== 'S';
                                                                        }
                                                                        return false; // Skip if class or major is null
                                                                    }
                                                                    // กรณี attendance พิเศษ
                                                                    elseif ($attendance['type'] === 'special') {
                                                                        // Check if classes and major exist
                                                                        if (
                                                                            $attendance['data'] &&
                                                                            $attendance['data']->classes &&
                                                                            $attendance['data']->classes->major
                                                                        ) {
                                                                            return $attendance['data']->classes->major
                                                                                ->major_type !== 'S';
                                                                        }
                                                                        return false; // Skip if classes or major is null
                                                                    }
                                                                    // กรณี attendance เป็นชดเชย (extra)
                                                                    elseif ($attendance['type'] === 'extra') {
                                                                        // ใช้ teaching_type ที่ถูกกำหนดตอนสร้าง array
                                                                        return $attendance['teaching_type'] ===
                                                                            'regular';
                                                                    }

                                                                    return false;
                                                                })
                                                                ->sortBy('date');
                                                        })
                                                        ->filter(function ($sectionAttendances) {
                                                            return $sectionAttendances->isNotEmpty();
                                                        });

                                                    // คำนวณจำนวนรายการทั้งหมดใน $regularAttendances
                                                    $regularCount = 0;
                                                    foreach ($regularAttendances as $section => $attendances) {
                                                        $regularCount += count($attendances);
                                                    }
                                                @endphp

                                                @if ($regularCount > 0)
                                                    @foreach ($regularAttendances as $section => $attendances)
                                                        <h6 class="mt-3 mb-2">Section {{ $section }}</h6>
                                                        <div class="list-group mb-3">
                                                            @foreach ($attendances as $attendance)
                                                                <div
                                                                    class="list-group-item list-group-item-action flex-column align-items-start">
                                                                    <div
                                                                        class="d-flex w-100 justify-content-between align-items-center">
                                                                        <span>
                                                                            @if ($attendance['type'] === 'regular')
                                                                                {{ \Carbon\Carbon::parse($attendance['data']->start_time)->locale('th')->translatedFormat('d F Y') }}
                                                                            @elseif ($attendance['type'] === 'special')
                                                                                @if (isset($attendance['data']->start_work))
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->start_work)->locale('th')->translatedFormat('d F Y') }}
                                                                                @else
                                                                                    ไม่ระบุวันที่
                                                                                @endif
                                                                            @elseif ($attendance['type'] === 'extra')
                                                                                {{-- สำหรับการสอนชดเชย --}}
                                                                                @php
                                                                                    $dateParts = explode(
                                                                                        ' ',
                                                                                        $attendance['date'],
                                                                                    );
                                                                                    $dateOnly = $dateParts[0] ?? null;
                                                                                @endphp
                                                                                @if ($dateOnly)
                                                                                    {{ \Carbon\Carbon::parse($dateOnly)->locale('th')->translatedFormat('d F Y') }}
                                                                                @else
                                                                                    ไม่ระบุวันที่
                                                                                @endif
                                                                            @endif
                                                                        </span>
                                                                        <span
                                                                            class="badge {{ $attendance['type'] === 'extra' ? 'bg-warning text-dark' : 'bg-success' }}">
                                                                            {{ $attendance['type'] === 'extra' ? 'สอนชดเชย' : 'อนุมัติแล้ว' }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="row mt-2">
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">เวลา:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->start_time)->format('H:i') }}
                                                                                    -
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->end_time)->format('H:i') }}
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    @if (isset($attendance['data']->start_work))
                                                                                        {{ \Carbon\Carbon::parse($attendance['data']->start_work)->format('H:i') }}
                                                                                        -
                                                                                        {{ \Carbon\Carbon::parse($attendance['data']->end_work)->format('H:i') }}
                                                                                    @else
                                                                                        ไม่ระบุเวลา
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงข้อมูลเวลาสำหรับการสอนชดเชย --}}
                                                                                    @if (isset($attendance['data']->start_time) && isset($attendance['data']->end_time))
                                                                                        {{ substr($attendance['data']->start_time, 0, 5) }}
                                                                                        -
                                                                                        {{ substr($attendance['data']->end_time, 0, 5) }}
                                                                                    @else
                                                                                        ไม่ระบุเวลา
                                                                                    @endif
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">ประเภท:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    @if ($attendance['data']->class_type === 'L')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    @if ($attendance['data']->class_type === 'L')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงข้อมูลประเภทของการสอนชดเชย --}}
                                                                                    @if ($attendance['class_type'] === 'LAB')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">ระยะเวลา:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    @php
                                                                                        $start = \Carbon\Carbon::parse(
                                                                                            $attendance['data']
                                                                                                ->start_time,
                                                                                        );
                                                                                        $end = \Carbon\Carbon::parse(
                                                                                            $attendance['data']
                                                                                                ->end_time,
                                                                                        );
                                                                                        $durationInHours =
                                                                                            $end->diffInMinutes(
                                                                                                $start,
                                                                                            ) / 60;
                                                                                    @endphp
                                                                                    {{ number_format($durationInHours, 2) }}
                                                                                    ชั่วโมง
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    {{ number_format($attendance['data']->duration / 60, 2) }}
                                                                                    ชั่วโมง
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- ใช้ชั่วโมงที่คำนวณมาจาก controller --}}
                                                                                    {{ number_format($attendance['hours'], 2) }}
                                                                                    ชั่วโมง
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">รายละเอียด:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    {{ $attendance['data']->attendance->note ?? '-' }}
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    {{ $attendance['data']->detail ?? '-' }}
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงรายละเอียดสำหรับการสอนชดเชย --}}
                                                                                    การสอนชดเชย
                                                                                    {{ $attendance['data']->note ?? '' }}
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    {{-- เพิ่มเติมสำหรับการสอนชดเชย --}}
                                                                    @if ($attendance['type'] === 'extra')
                                                                        <div class="mt-1 small">
                                                                            <span class="badge bg-secondary">
                                                                                <i class="fas fa-exchange-alt me-1"></i>
                                                                                การสอนชดเชย
                                                                            </span>
                                                                            @if (isset($attendance['data']->original_date))
                                                                                <span class="ms-2 text-muted">
                                                                                    ชดเชยวันที่:
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->original_date)->locale('th')->translatedFormat('d F Y') }}
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="alert alert-info mt-3">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        ไม่พบข้อมูลการลงเวลาโครงการปกติในช่วงเวลาที่เลือก
                                                    </div>
                                                @endif
                                            </div>

                                            <!-- โครงการพิเศษ -->
                                            <div class="tab-pane fade" id="special-content" role="tabpanel"
                                                aria-labelledby="special-tab">
                                                @php
                                                    $specialAttendances = $attendancesBySection
                                                        ->map(function ($sectionAttendances) {
                                                            return $sectionAttendances
                                                                ->filter(function ($attendance) {
                                                                    // กรณี attendance ปกติ
                                                                    if ($attendance['type'] === 'regular') {
                                                                        // Check if class and major exist
                                                                        if (
                                                                            $attendance['data'] &&
                                                                            $attendance['data']->class &&
                                                                            $attendance['data']->class->major
                                                                        ) {
                                                                            return $attendance['data']->class->major
                                                                                ->major_type === 'S';
                                                                        }
                                                                        return false; // Skip if class or major is null
                                                                    }
                                                                    // กรณี attendance พิเศษ
                                                                    elseif ($attendance['type'] === 'special') {
                                                                        // Check if classes and major exist
                                                                        if (
                                                                            $attendance['data'] &&
                                                                            $attendance['data']->classes &&
                                                                            $attendance['data']->classes->major
                                                                        ) {
                                                                            return $attendance['data']->classes->major
                                                                                ->major_type === 'S';
                                                                        }
                                                                        return false; // Skip if classes or major is null
                                                                    }
                                                                    // กรณี attendance เป็นชดเชย (extra)
                                                                    elseif ($attendance['type'] === 'extra') {
                                                                        // ใช้ teaching_type ที่ถูกกำหนดตอนสร้าง array
                                                                        return $attendance['teaching_type'] ===
                                                                            'special';
                                                                    }

                                                                    return false;
                                                                })
                                                                ->sortBy('date');
                                                        })
                                                        ->filter(function ($sectionAttendances) {
                                                            return $sectionAttendances->isNotEmpty();
                                                        });

                                                    // คำนวณจำนวนรายการทั้งหมดใน $specialAttendances
                                                    $specialCount = 0;
                                                    foreach ($specialAttendances as $section => $attendances) {
                                                        $specialCount += count($attendances);
                                                    }
                                                @endphp

                                                @if ($specialCount > 0)
                                                    @foreach ($specialAttendances as $section => $attendances)
                                                        <h6 class="mt-3 mb-2">Section {{ $section }}</h6>
                                                        <div class="list-group mb-3">
                                                            @foreach ($attendances as $attendance)
                                                                <div
                                                                    class="list-group-item list-group-item-action flex-column align-items-start">
                                                                    <div
                                                                        class="d-flex w-100 justify-content-between align-items-center">
                                                                        <span>
                                                                            @if ($attendance['type'] === 'regular')
                                                                                {{ \Carbon\Carbon::parse($attendance['data']->start_time)->locale('th')->translatedFormat('d F Y') }}
                                                                            @elseif ($attendance['type'] === 'special')
                                                                                @if (isset($attendance['data']->start_work))
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->start_work)->locale('th')->translatedFormat('d F Y') }}
                                                                                @else
                                                                                    ไม่ระบุวันที่
                                                                                @endif
                                                                            @elseif ($attendance['type'] === 'extra')
                                                                                {{-- สำหรับการสอนชดเชย --}}
                                                                                @php
                                                                                    $dateParts = explode(
                                                                                        ' ',
                                                                                        $attendance['date'],
                                                                                    );
                                                                                    $dateOnly = $dateParts[0] ?? null;
                                                                                @endphp
                                                                                @if ($dateOnly)
                                                                                    {{ \Carbon\Carbon::parse($dateOnly)->locale('th')->translatedFormat('d F Y') }}
                                                                                @else
                                                                                    ไม่ระบุวันที่
                                                                                @endif
                                                                            @endif
                                                                        </span>
                                                                        <span
                                                                            class="badge {{ $attendance['type'] === 'extra' ? 'bg-warning text-dark' : 'bg-success' }}">
                                                                            {{ $attendance['type'] === 'extra' ? 'สอนชดเชย' : 'อนุมัติแล้ว' }}
                                                                        </span>
                                                                    </div>
                                                                    <div class="row mt-2">
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">เวลา:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->start_time)->format('H:i') }}
                                                                                    -
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->end_time)->format('H:i') }}
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    @if (isset($attendance['data']->start_work))
                                                                                        {{ \Carbon\Carbon::parse($attendance['data']->start_work)->format('H:i') }}
                                                                                        -
                                                                                        {{ \Carbon\Carbon::parse($attendance['data']->end_work)->format('H:i') }}
                                                                                    @else
                                                                                        ไม่ระบุเวลา
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงข้อมูลเวลาสำหรับการสอนชดเชย --}}
                                                                                    @if (isset($attendance['data']->start_time) && isset($attendance['data']->end_time))
                                                                                        {{ substr($attendance['data']->start_time, 0, 5) }}
                                                                                        -
                                                                                        {{ substr($attendance['data']->end_time, 0, 5) }}
                                                                                    @else
                                                                                        ไม่ระบุเวลา
                                                                                    @endif
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">ประเภท:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    @if ($attendance['data']->class_type === 'L')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    @if ($attendance['data']->class_type === 'L')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงข้อมูลประเภทของการสอนชดเชย --}}
                                                                                    @if ($attendance['class_type'] === 'LAB')
                                                                                        ปฏิบัติการ
                                                                                    @else
                                                                                        บรรยาย
                                                                                    @endif
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">ระยะเวลา:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    @php
                                                                                        $start = \Carbon\Carbon::parse(
                                                                                            $attendance['data']
                                                                                                ->start_time,
                                                                                        );
                                                                                        $end = \Carbon\Carbon::parse(
                                                                                            $attendance['data']
                                                                                                ->end_time,
                                                                                        );
                                                                                        $durationInHours =
                                                                                            $end->diffInMinutes(
                                                                                                $start,
                                                                                            ) / 60;
                                                                                    @endphp
                                                                                    {{ number_format($durationInHours, 2) }}
                                                                                    ชั่วโมง
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    {{ number_format($attendance['data']->duration / 60, 2) }}
                                                                                    ชั่วโมง
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- ใช้ชั่วโมงที่คำนวณมาจาก controller --}}
                                                                                    {{ number_format($attendance['hours'], 2) }}
                                                                                    ชั่วโมง
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                        <div class="col-md-3">
                                                                            <span class="text-muted">รายละเอียด:
                                                                                @if ($attendance['type'] === 'regular')
                                                                                    {{ $attendance['data']->attendance->note ?? '-' }}
                                                                                @elseif ($attendance['type'] === 'special')
                                                                                    {{ $attendance['data']->detail ?? '-' }}
                                                                                @elseif ($attendance['type'] === 'extra')
                                                                                    {{-- แสดงรายละเอียดสำหรับการสอนชดเชย --}}
                                                                                    การสอนชดเชย
                                                                                    {{ $attendance['data']->note ?? '' }}
                                                                                @endif
                                                                            </span>
                                                                        </div>
                                                                    </div>

                                                                    {{-- เพิ่มเติมสำหรับการสอนชดเชย --}}
                                                                    @if ($attendance['type'] === 'extra')
                                                                        <div class="mt-1 small">
                                                                            <span class="badge bg-secondary">
                                                                                <i class="fas fa-exchange-alt me-1"></i>
                                                                                การสอนชดเชย
                                                                            </span>
                                                                            @if (isset($attendance['data']->original_date))
                                                                                <span class="ms-2 text-muted">
                                                                                    ชดเชยวันที่:
                                                                                    {{ \Carbon\Carbon::parse($attendance['data']->original_date)->locale('th')->translatedFormat('d F Y') }}
                                                                                </span>
                                                                            @endif
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="alert alert-info mt-3">
                                                        <i class="fas fa-info-circle me-2"></i>
                                                        ไม่พบข้อมูลการลงเวลาโครงการพิเศษในช่วงเวลาที่เลือก
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- สรุปค่าตอบแทน -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0">สรุปการทำงานและค่าตอบแทนประจำเดือน</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-7">
                                        <!-- สรุปชั่วโมงทำงานและค่าตอบแทน -->
                                        <div class="card mb-3">
                                            <div class="card-body bg-light">
                                                <h6 class="mb-3">สรุปชั่วโมงการสอน</h6>
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <i class="fas fa-chalkboard-teacher text-primary me-2"></i>
                                                        <strong>โครงการปกติ:</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        {{ number_format($compensation['regularHours'], 2) }} ชั่วโมง
                                                    </div>
                                                </div>
                                                <div class="row mb-2">
                                                    <div class="col-6">
                                                        <i class="fas fa-laptop-code text-primary me-2"></i>
                                                        <strong>โครงการพิเศษ:</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        {{ number_format($compensation['specialHours'], 2) }} ชั่วโมง
                                                    </div>
                                                </div>
                                                <div class="row fw-bold border-top pt-2 mt-2">
                                                    <div class="col-6">
                                                        <i class="fas fa-clock text-primary me-2"></i>
                                                        <strong>รวมทั้งหมด:</strong>
                                                    </div>
                                                    <div class="col-6">
                                                        {{ number_format($compensation['regularHours'] + $compensation['specialHours'], 2) }}
                                                        ชั่วโมง
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="card">
                                            <div class="card-body bg-light">
                                                <h6 class="mb-3">สรุปค่าตอบแทน</h6>
                                                @if ($compensation['regularHours'] > 0)
                                                    <div class="row mb-2">
                                                        <div class="col-7">
                                                            <i class="fas fa-money-bill text-success me-2"></i>
                                                            <strong>โครงการปกติ:</strong>
                                                        </div>
                                                        <div class="col-5 text-end">
                                                            {{ number_format($compensation['regularPay'], 2) }} บาท
                                                            <span class="d-block text-muted">
                                                                {{ number_format($compensation['rates']['regularLecture'], 2) }}
                                                                บาท/ชม.
                                                            </span>
                                                        </div>
                                                    </div>
                                                @endif

                                                @if ($compensation['specialHours'] > 0)
                                                    <div class="row mb-2">
                                                        <div class="col-7">
                                                            <i class="fas fa-money-bill-wave text-success me-2"></i>
                                                            <strong>โครงการพิเศษ:</strong>
                                                        </div>
                                                        <div class="col-5 text-end">
                                                            @if ($isGraduate)
                                                                {{ number_format($fixedAmount, 2) }} บาท
                                                                <span class="d-block text-muted">
                                                                    <span class="badge bg-info">เหมาจ่าย</span>
                                                                </span>
                                                            @else
                                                                {{ number_format($compensation['specialPay'], 2) }} บาท
                                                                <span class="d-block text-muted">
                                                                    {{ number_format($compensation['rates']['specialLecture'], 2) }}
                                                                    บาท/ชม.
                                                                </span>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif

                                                <div class="row fw-bold border-top pt-2 mt-2">
                                                    <div class="col-7">
                                                        <i class="fas fa-hand-holding-usd text-success me-2"></i>
                                                        <strong>รวมทั้งสิ้น:</strong>
                                                    </div>
                                                    <div class="col-5 text-end">
                                                        {{ number_format($compensation['totalPay'], 2) }} บาท
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        @if ($isGraduate && $compensation['specialHours'] > 0)
                                            <div class="alert alert-info mt-3">
                                                <i class="fas fa-info-circle"></i>
                                                ผู้ช่วยสอนระดับบัณฑิตศึกษาที่สอนในโครงการพิเศษ
                                                จะได้รับค่าตอบแทนแบบเหมาจ่ายในอัตรา
                                                <strong>{{ number_format($fixedAmount, 2) }} บาทต่อเดือน</strong> (ไม่เกิน
                                                4,000 บาท)
                                            </div>
                                        @endif
                                    </div>

                                    <div class="col-md-5">
                                        <!-- การเบิกจ่าย - แก้ไขตรงนี้ -->
                                        <div class="card h-100">
                                            @php
                                                // ตรวจสอบว่ามีการเบิกจ่ายแล้วหรือไม่
                                                $transaction = \App\Models\CompensationTransaction::where(
                                                    'student_id',
                                                    $student->id,
                                                )
                                                    ->where('course_id', $course->course_id)
                                                    ->where('month_year', $selectedYearMonth)
                                                    ->first();
                                                $isCompensated = $transaction ? true : false;
                                            @endphp

                                            <div
                                                class="card-header {{ $isCompensated ? 'bg-success text-white' : ($isExceeded ? 'bg-warning' : 'bg-success text-white') }}">
                                                <h6 class="mb-0">
                                                    <i class="fas fa-receipt"></i>
                                                    @if ($isCompensated)
                                                        สถานะการเบิกจ่าย
                                                    @else
                                                        {{ $isExceeded ? 'ปรับยอดการเบิกจ่าย' : 'บันทึกการเบิกจ่าย' }}
                                                    @endif
                                                </h6>
                                            </div>
                                            <div class="card-body">
                                                @if ($isCompensated)
                                                    <!-- กรณีเบิกจ่ายแล้ว -->
                                                    <div class="text-center mb-4">
                                                        <i class="fas fa-check-circle text-success"
                                                            style="font-size: 48px;"></i>
                                                        <h5 class="mt-3">เบิกจ่ายแล้ว</h5>
                                                        <p>การเบิกจ่ายสำหรับเดือนนี้ได้รับการบันทึกแล้ว</p>
                                                    </div>

                                                    <div class="mb-3">
                                                        <div class="row mb-2">
                                                            <div class="col-7 text-muted">เบิกจ่ายเมื่อ:</div>
                                                            <div class="col-5 text-end">
                                                                {{ \Carbon\Carbon::parse($transaction->created_at)->format('d/m/Y H:i') }}
                                                            </div>
                                                        </div>
                                                        <div class="row mb-2">
                                                            <div class="col-7 text-muted">จำนวนเงินที่เบิกจ่าย:</div>
                                                            <div class="col-5 text-end fw-bold">
                                                                {{ number_format($transaction->actual_amount, 2) }} บาท
                                                            </div>
                                                        </div>
                                                        @if ($transaction->is_adjusted)
                                                            <div class="row mb-2">
                                                                <div class="col-7 text-muted">หมายเหตุ:</div>
                                                                <div class="col-5 text-end">
                                                                    <span class="badge bg-warning">มีการปรับยอด</span>
                                                                </div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-12">
                                                                    <span
                                                                        class="text-muted">{{ $transaction->adjustment_reason }}</span>
                                                                </div>
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <button type="button" class="btn btn-success w-100"
                                                        data-bs-toggle="modal" data-bs-target="#exportModal">
                                                        <i class="fas fa-download"></i> ดาวน์โหลดเอกสารเบิกจ่าย
                                                    </button>
                                                @else
                                                    @if ($compensation['totalPay'] <= 0)
                                                        <!-- กรณี totalPay < 0 -->
                                                        <div class="alert alert-danger">
                                                            <i class="fas fa-exclamation-circle"></i>
                                                            <strong>ไม่สามารถเบิกจ่ายได้:</strong>
                                                            ค่าตอบแทนที่คำนวณได้มีค่าน้อยกว่า 0 บาท
                                                        </div>
                                                    @elseif ($isExceeded)
                                                        <!-- กรณีงบประมาณไม่พอ -->
                                                        <div class="alert alert-warning">
                                                            <strong>คำเตือน:</strong> ค่าตอบแทนเกินงบประมาณคงเหลือ
                                                        </div>

                                                        <form
                                                            action="{{ route('admin.course-budgets.compensation.save') }}"
                                                            method="POST">
                                                            @csrf
                                                            <input type="hidden" name="student_id"
                                                                value="{{ $student->id }}">
                                                            <input type="hidden" name="course_id"
                                                                value="{{ $course->course_id }}">
                                                            <input type="hidden" name="month_year"
                                                                value="{{ $selectedYearMonth }}">
                                                            <input type="hidden" name="calculated_amount"
                                                                value="{{ $compensation['totalPay'] }}">
                                                            <input type="hidden" name="hours_worked"
                                                                value="{{ $compensation['regularHours'] + $compensation['specialHours'] }}">
                                                            <input type="hidden" name="is_adjusted" value="1">

                                                            <div class="mb-3">
                                                                <div class="row mb-2">
                                                                    <div class="col-7 text-muted">ค่าตอบแทนที่คำนวณได้:
                                                                    </div>
                                                                    <div class="col-5 text-end">
                                                                        {{ number_format($compensation['totalPay'], 2) }}
                                                                        บาท
                                                                    </div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-7 text-muted">งบประมาณคงเหลือ:</div>
                                                                    <div class="col-5 text-end text-danger fw-bold">
                                                                        {{ number_format($remainingBudget, 2) }} บาท
                                                                    </div>
                                                                </div>
                                                                <div class="row mb-2">
                                                                    <div class="col-7 text-muted">ส่วนต่าง:</div>
                                                                    <div class="col-5 text-end text-danger">
                                                                        {{ number_format($compensation['totalPay'] - $remainingBudget, 2) }}
                                                                        บาท
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="mb-3">
                                                                <label for="actual_amount"
                                                                    class="form-label">เบิกจ่ายเพียง (บาท):</label>
                                                                <input type="number" step="0.01" name="actual_amount"
                                                                    id="actual_amount" class="form-control"
                                                                    value="{{ number_format($remainingBudget, 2, '.', '') }}"
                                                                    max="{{ $remainingBudget }}">
                                                            </div>
                                                            <div class="mb-3">
                                                                <label for="adjustment_reason"
                                                                    class="form-label">เหตุผลในการปรับยอด:</label>
                                                                <textarea name="adjustment_reason" id="adjustment_reason" class="form-control" rows="2">งบประมาณคงเหลือไม่เพียงพอ</textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-primary w-100">
                                                                <i class="fas fa-save"></i> บันทึกการเบิกจ่าย
                                                            </button>
                                                        </form>
                                                    @else
                                                        <!-- กรณีงบประมาณเพียงพอ -->
                                                        <div class="text-center mb-4">
                                                            <i class="fas fa-check-circle text-success"
                                                                style="font-size: 48px;"></i>
                                                            <h5 class="mt-3">พร้อมเบิกจ่าย</h5>
                                                            <p>งบประมาณคงเหลือเพียงพอสำหรับการเบิกจ่ายในเดือนนี้</p>
                                                        </div>

                                                        <div class="mb-3">
                                                            <div class="row mb-2">
                                                                <div class="col-7 text-muted">ค่าตอบแทนที่คำนวณได้:</div>
                                                                <div class="col-5 text-end">
                                                                    {{ number_format($compensation['totalPay'], 2) }} บาท
                                                                </div>
                                                            </div>
                                                            <div class="row mb-2">
                                                                <div class="col-7 text-muted">งบประมาณคงเหลือ:</div>
                                                                <div class="col-5 text-end">
                                                                    {{ number_format($remainingBudget, 2) }} บาท
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <form
                                                            action="{{ route('admin.course-budgets.compensation.save') }}"
                                                            method="POST">
                                                            @csrf
                                                            <input type="hidden" name="student_id"
                                                                value="{{ $student->id }}">
                                                            <input type="hidden" name="course_id"
                                                                value="{{ $course->course_id }}">
                                                            <input type="hidden" name="month_year"
                                                                value="{{ $selectedYearMonth }}">
                                                            <input type="hidden" name="calculated_amount"
                                                                value="{{ $compensation['totalPay'] }}">
                                                            <input type="hidden" name="actual_amount"
                                                                value="{{ $compensation['totalPay'] }}">
                                                            <input type="hidden" name="hours_worked"
                                                                value="{{ $compensation['regularHours'] + $compensation['specialHours'] }}">
                                                            <input type="hidden" name="is_adjusted" value="0">

                                                            <button type="submit" class="btn btn-success w-100">
                                                                <i class="fas fa-check-circle"></i>
                                                                บันทึกการเบิกจ่ายเต็มจำนวน
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ปุ่มดาวน์โหลดเอกสารและจัดการอัตราค่าตอบแทน -->
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <a href="{{ route('admin.compensation-rates.index') }}" class="btn btn-info">
                                    <i class="fas fa-money-bill-wave"></i> จัดการอัตราค่าตอบแทน
                                </a>
                            </div>
                            {{-- <div class="col-md-6 text-end">
                                @if ($isCompensated)
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                        data-bs-target="#exportModal">
                                        <i class="fas fa-download"></i> ดาวน์โหลดเอกสารเบิกจ่าย
                                    </button>
                                @endif
                            </div> --}}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal สำหรับเลือกประเภทเอกสารที่ต้องการดาวน์โหลด -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">เลือกประเภทเอกสารเบิกจ่าย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="{{ route('layout.exports.pdf', ['id' => $student->id, 'month' => $selectedYearMonth]) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                <strong>แบบใบเบิกค่าตอบแทน (PDF)</strong>
                                <p class="text-muted mb-0 span">เอกสารสำหรับการเบิกค่าตอบแทนประจำเดือน</p>
                            </div>
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="{{ route('layout.exports.result-pdf', ['id' => $student->id, 'month' => $selectedYearMonth]) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-pdf text-danger me-2"></i>
                                <strong>หลักฐานการจ่ายเงิน (PDF)</strong>
                                <p class="text-muted mb-0 span">เอกสารหลักฐานการจ่ายเงินประจำเดือน</p>
                            </div>
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="{{ route('admin.export.template', ['id' => $student->id, 'month' => $selectedYearMonth]) }}"
                            class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas fa-file-excel text-success me-2"></i>
                                <strong>ชุดเอกสารทั้งหมด (Excel)</strong>
                                <p class="text-muted mb-0 span">รวมแบบใบเบิกและหลักฐานการจ่ายเงินในรูปแบบ Excel</p>
                            </div>
                            <i class="fas fa-download"></i>
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                </div>
            </div>
        </div>
    </div>
@endsection
