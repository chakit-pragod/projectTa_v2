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
                                <p><strong>ระดับการศึกษา:</strong> {{ $student->degree ?? 'ปริญญาตรี' }}</p>
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
                            {{ Carbon\Carbon::parse($semester->end_date)->format('d/m/Y') }})</h5>

                        <div class="table-responsive">
                            <div class="table-responsive">

                                <div class="mb-3">
                                    <form method="GET" class="d-flex align-items-center">
                                        <label for="month" class="me-2">เลือกเดือน:</label>
                                        <select name="month" id="month" class="form-select w-15" onchange="this.form.submit()">
                                            @foreach($monthsInSemester as $yearMonth => $monthName)
                                                <option value="{{ $yearMonth }}" {{ $selectedYearMonth == $yearMonth ? 'selected' : '' }}>
                                                    {{ $monthName }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                </div>
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>กลุ่ม</th>
                                            <th>เวลาเริ่มเรียน</th>
                                            <th>เวลาเลิกเรียน</th>
                                            <th>เวลาที่สอน(นาที)</th>
                                            <th>อาจารย์ประจำวิชา</th>
                                            <th>การปฏิบัติงาน</th>
                                            <th>รายละเอียด</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($teachings as $teaching)
                                            <tr>
                                                <td>
                                                    @if ($teaching->class_type === 'E')
                                                        <span>สอนชดเชย</span>
                                                    @else
                                                        {{ $teaching->class_type }}
                                                    @endif
                                                </td>
                                                <td>{{ \Carbon\Carbon::parse($teaching->start_time)->format('d-m-Y H:i') }}</td>
                                                <td>{{ \Carbon\Carbon::parse($teaching->end_time)->format('d-m-Y H:i') }}</td>
                                                <td>{{ $teaching->duration }}</td>
                                                <td>
                                                    {{ $teaching->teacher->position ?? '' }}
                                                    {{ $teaching->teacher->degree ?? '' }}
                                                    {{ $teaching->teacher->name ?? '' }}
                                                </td>
                                                <td>
                                                    @if ($teaching->attendance)
                                                        @if ($teaching->attendance->status === 'เข้าปฏิบัติการสอน')
                                                            <span class="badge bg-success">เข้าปฏิบัติการสอน</span>
                                                        @elseif ($teaching->attendance->status === 'ลา')
                                                            <span class="badge bg-warning">ลา</span>
                                                        @else
                                                            <span class="badge bg-secondary">รอการลงเวลา</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-secondary">รอการลงเวลา</span>
                                                    @endif
                                                </td>
                                                <td>{{ $teaching->attendance->note ?? '-' }}</td>
                                                <td>
                                                    @if ($teaching->attendance)
                                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                                            ลงเวลาแล้ว
                                                        </button>
                                                    @else
                                                        <a href="{{ route('attendances.form', ['teaching_id' => $teaching->teaching_id]) }}"
                                                            class="btn btn-outline-primary btn-sm">
                                                            ลงเวลา
                                                        </a>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center">ไม่พบข้อมูลการสอน</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

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
@endsection
