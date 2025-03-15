@extends('layouts.adminLayout')

@section('title', 'รายละเอียดผู้ช่วยสอน')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-body">
                        <h5 class="card-title">ข้อมูลรายวิชา</h5>
                        <p class="card-text">
                            <strong>ชื่อวิชา:</strong>
                            {{ $course->subjects->subject_id }} {{ $course->subjects->name_en }}
                        </p>
                        <p class="card-text">
                            <strong>ปีการศึกษา:</strong>
                            {{ $course->semesters->semesters }}/{{ $course->semesters->year }}
                        </p>
                        <p class="card-text">
                            <strong>อาจารย์ประจำวิชา:</strong>
                            {{ $course->teachers->position }} {{ $course->teachers->name }}
                        </p>
                        <p class="card-text">
                            <strong>หน่วยกิต:</strong>
                            {{ $course->subjects->credit ?? 'N/A' }}
                        </p>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">ข้อมูลผู้ช่วยสอน</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รหัสนักศึกษา</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>ระดับ</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($course->course_tas as $index => $ta)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $ta->student->student_id }}</td>
                                            <td>{{ $ta->student->name }}</td>
                                            {{-- <td>{{ $ta->student->lname }}</td> --}}
                                            <td>
                                                @if (isset($ta->student->degree_level))
                                                    @if ($ta->student->degree_level == 'bachelor')
                                                        ปริญญาตรี
                                                    @elseif($ta->student->degree_level == 'master')
                                                        ปริญญาโท
                                                    @elseif($ta->student->degree_level == 'doctoral')
                                                        ปริญญาเอก
                                                    @else
                                                        {{ $student->degree_level }}
                                                    @endif
                                                @else
                                                    ปริญญาตรี
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.ta.profile', ['student_id' => $ta->student->id, 'course_id' => $ta->course_id]) }}" class="btn btn-primary btn-sm">
                                                    ตรวจสอบข้อมูล
                                                </a>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center">ไม่พบข้อมูลผู้ช่วยสอน</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
