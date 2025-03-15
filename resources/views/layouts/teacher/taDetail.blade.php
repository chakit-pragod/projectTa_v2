@extends('layouts.teacherLayout')

@section('title', 'Teacher')
@section('break', 'รายวิชาทั้งหมด')
@section('break2', 'ข้อมูลรายวิชา')
@section('break3', 'ข้อมูลผู้ช่วยสอน')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h4>ข้อมูลผู้ช่วยสอน</h4>

                    <!-- ข้อมูลส่วนตัว -->
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>รหัสนักศึกษา:</strong> {{ $student->student_id }}</p>
                                <p><strong>ชื่อ-นามสกุล:</strong> {{ $student->name }}</p>
                                <p><strong>ระดับการศึกษา:</strong>
                                    @if (isset($student->degree_level))
                                        @if ($student->degree_level == 'bachelor')
                                            ปริญญาตรี
                                        @elseif($student->degree_level == 'master')
                                            ปริญญาโท
                                        @elseif($student->degree_level == 'doctoral')
                                            ปริญญาเอก
                                        @else
                                            {{ $student->degree_level }}
                                        @endif
                                    @else
                                        ปริญญาตรี
                                    @endif
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>อีเมล:</strong> {{ $student->email }}</p>
                                <p><strong>เบอร์โทรศัพท์:</strong> {{ $student->phone ?? 'ไม่ระบุ' }}</p>

                                @if ($student->disbursements)
                                    <div class="mt-3">
                                        <p><strong>เอกสารสำคัญ:
                                                @if ($student->disbursements->id)
                                                    <a href="{{ route('layout.ta.download-document', $student->disbursements->id) }}"
                                                        class="color-primary">
                                                        ดาวน์โหลดเอกสาร click!
                                                    </a>
                                                @endif
                                            </strong></p>
                                    </div>
                                @else
                                    <p class="text-muted">ยังไม่มีการอัปโหลดเอกสาร</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- ข้อมูลการลงเวลา -->
                    <div class="card-body">
                        <h5 class="card-title">ข้อมูลการลงเวลาการสอน
                            ({{ Carbon\Carbon::parse($semester->start_date)->format('d/m/Y') }} -
                            {{ Carbon\Carbon::parse($semester->end_date)->format('d/m/Y') }})
                        </h5>

                        <div class="table-responsive">
                            <!-- ตัวเลือกเดือน -->
                            <div class="mb-3">
                                <form method="GET" class="d-flex align-items-center">
                                    <label for="month" class="me-2">เลือกเดือน:</label>
                                    <select name="month" id="month" class="form-select w-15"
                                        onchange="this.form.submit()">
                                        @foreach ($monthsInSemester as $yearMonth => $monthName)
                                            <option value="{{ $yearMonth }}"
                                                {{ $selectedYearMonth == $yearMonth ? 'selected' : '' }}>
                                                {{ $monthName }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>

                            <!-- แสดงสถานะการอนุมัติ -->
                            @if ($isMonthApproved)
                                <div class="alert alert-success mb-3">
                                    การลงเวลาของเดือนนี้ได้รับการอนุมัติแล้ว
                                    @if ($approvalNote)
                                        <br>หมายเหตุการอนุมัติ: {{ $approvalNote }}
                                    @endif
                                </div>
                            @endif

                            @php
                                $allAttendances = collect();

                                // เพิ่มข้อมูลการสอนปกติ
                                foreach ($teachings as $teaching) {
                                    $allAttendances->push([
                                        'id' => isset($teaching->extra_class_id)
                                            ? 'extra-' . $teaching->extra_class_id
                                            : $teaching->teaching_id,
                                        'date' => $teaching->start_time,
                                        'type' => $teaching->class_type === 'E' ? 'สอนชดเชย' : $teaching->class_type,
                                        'start' => $teaching->start_time,
                                        'end' => $teaching->end_time,
                                        'duration' => $teaching->duration,
                                        'teacher' => $teaching->teacher,
                                        'status' => $teaching->attendance->status ?? null,
                                        'note' => $teaching->attendance->note ?? null,
                                        'approve_status' => $teaching->attendance->approve_status ?? null,
                                        'approve_note' => $teaching->attendance->approve_note ?? '-',
                                        'is_extra' => $teaching->class_type === 'E',
                                        'original_id' => isset($teaching->extra_class_id)
                                            ? $teaching->extra_class_id
                                            : $teaching->teaching_id,
                                    ]);
                                }

                                // เพิ่มข้อมูลการสอนพิเศษ
                                foreach ($extraAttendances as $extra) {
                                    $allAttendances->push([
                                        'id' => $extra->id,
                                        'date' => $extra->start_work,
                                        'type' => 'งานพิเศษ',
                                        'start' => $extra->start_work,
                                        'end' => null,
                                        'duration' => $extra->duration,
                                        'teacher' => $extra->classes->teachers ?? null,
                                        'status' => null,
                                        'note' => $extra->detail,
                                        'approve_status' => $extra->approve_status,
                                        'approve_note' => $extra->approve_note ?? '-',
                                        'is_extra' => true,
                                        'original_id' => $extra->id,
                                    ]);
                                }

                                // เรียงตามวันที่
                                $allAttendances = $allAttendances->sortBy('date');
                            @endphp

                            <form action="{{ route('teacher.approve-month', ['ta_id' => $student->id]) }}" method="POST">
                                @csrf
                                <input type="hidden" name="year_month" value="{{ $selectedYearMonth }}">

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <!-- ปุ่มเลือกทั้งหมด -->
                                    @if (!$isMonthApproved && $allAttendances->whereIn('approve_status', [null, ''])->count() > 0)
                                        <div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select-all">
                                                <label class="form-check-label fw-bold" for="select-all">
                                                    เลือกทั้งหมด
                                                </label>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            @if (!$isMonthApproved)
                                                <th>เลือก</th>
                                            @endif
                                            <th>วันที่</th>
                                            <th>ประเภท</th>
                                            <th>เวลาเริ่ม</th>
                                            <th>เวลาเลิก</th>
                                            <th>เวลาที่ทำ(นาที)</th>
                                            <th>อาจารย์ประจำวิชา</th>
                                            <th>การปฏิบัติงาน</th>
                                            <th>รายละเอียด</th>
                                            <th>สถานะการอนุมัติ</th>
                                            <th>หมายเหตุอนุมัติ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($allAttendances as $index => $attendance)
                                            <tr>
                                                @if (!$isMonthApproved)
                                                    <td>
                                                        @if ($attendance['approve_status'] !== 'a')
                                                            <div class="form-check">
                                                                @if ($attendance['type'] === 'งานพิเศษ')
                                                                    <!-- ExtraAttendance -->
                                                                    <input class="form-check-input attendance-checkbox"
                                                                        type="checkbox" name="extra_attendances[]"
                                                                        value="{{ $attendance['original_id'] }}"
                                                                        id="attendance-{{ $index }}">
                                                                @elseif ($attendance['type'] === 'สอนชดเชย')
                                                                    <input class="form-check-input attendance-checkbox"
                                                                        type="checkbox" name="normal_attendances[]"
                                                                        value="{{ $attendance['original_id'] }}"
                                                                        {{-- ใช้ original_id แทน extra- --}}
                                                                        id="attendance-{{ $index }}">
                                                                @else
                                                                    <!-- Normal Teaching -->
                                                                    <input class="form-check-input attendance-checkbox"
                                                                        type="checkbox" name="normal_attendances[]"
                                                                        value="{{ $attendance['original_id'] }}"
                                                                        id="attendance-{{ $index }}">
                                                                @endif
                                                                <label class="form-check-label"
                                                                    for="attendance-{{ $index }}"></label>
                                                            </div>
                                                        @endif
                                                    </td>
                                                @endif
                                                <td>{{ \Carbon\Carbon::parse($attendance['date'])->format('d-m-Y') }}</td>
                                                <td>{{ $attendance['type'] }}</td>
                                                <td>{{ \Carbon\Carbon::parse($attendance['start'])->format('H:i') }}</td>
                                                <td>
                                                    @if ($attendance['end'])
                                                        {{ \Carbon\Carbon::parse($attendance['end'])->format('H:i') }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $attendance['duration'] }}</td>
                                                <td>
                                                    @if ($attendance['teacher'])
                                                        {{ $attendance['teacher']->position ?? '' }}
                                                        {{ $attendance['teacher']->degree ?? '' }}
                                                        {{ $attendance['teacher']->name ?? '' }}
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (!$attendance['is_extra'] && $attendance['status'])
                                                        @if ($attendance['status'] === 'เข้าปฏิบัติการสอน')
                                                            <span class="badge bg-success">เข้าปฏิบัติการสอน</span>
                                                        @elseif($attendance['status'] === 'ลา')
                                                            <span class="badge bg-warning">ลา</span>
                                                        @endif
                                                    @else
                                                        -
                                                    @endif
                                                </td>
                                                <td>{{ $attendance['note'] }}</td>
                                                <td>
                                                    @if ($attendance['approve_status'] === 'a')
                                                        <span class="badge bg-success">อนุมัติแล้ว</span>
                                                    @else
                                                        <span class="badge bg-warning">รออนุมัติ</span>
                                                    @endif
                                                </td>
                                                <td>{{ $attendance['approve_note'] }}</td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ !$isMonthApproved ? 11 : 10 }}" class="text-center">
                                                    ไม่พบข้อมูลการลงเวลา</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>

                                @if (!$isMonthApproved && $allAttendances->whereIn('approve_status', [null, ''])->count() > 0)
                                    <div class="mb-3">
                                        <label for="approve_note" class="form-label">หมายเหตุการอนุมัติ</label>
                                        <textarea name="approve_note" id="approve_note" class="form-control" rows="3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-primary" id="approve-button" disabled>
                                        อนุมัติรายการที่เลือก
                                    </button>
                                @endif
                            </form>

                            <!-- ปุ่มย้อนกลับ -->
                            <div class="mt-3">
                                <a href="{{ url()->previous() }}" class="btn btn-secondary">
                                    ย้อนกลับ
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- เพิ่ม script ที่จำเป็นสำหรับ checkbox และปุ่มต่างๆ -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('select-all');
            const attendanceCheckboxes = document.querySelectorAll('.attendance-checkbox');
            const approveButton = document.getElementById('approve-button');

            if (selectAllCheckbox && attendanceCheckboxes.length > 0 && approveButton) {
                // ฟังก์ชั่นอัพเดทปุ่มอนุมัติ
                function updateApproveButton() {
                    const anyChecked = Array.from(attendanceCheckboxes).some(checkbox => checkbox.checked);
                    approveButton.disabled = !anyChecked;
                }

                // เมื่อกดปุ่มเลือกทั้งหมด
                selectAllCheckbox.addEventListener('change', function() {
                    attendanceCheckboxes.forEach(checkbox => {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                    updateApproveButton();
                });

                // เมื่อกดเลือกรายการใดๆ
                attendanceCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        // ตรวจสอบว่าทุกรายการถูกเลือกหรือไม่
                        const allChecked = Array.from(attendanceCheckboxes).every(cb => cb.checked);
                        selectAllCheckbox.checked = allChecked;

                        updateApproveButton();
                    });
                });

                // อัพเดทสถานะปุ่มตอนโหลดหน้า
                updateApproveButton();
            }
        });
    </script>
@endsection
