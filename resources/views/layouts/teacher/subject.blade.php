@extends('layouts.teacherLayout')

@section('title', 'Teacher')
@section('break', 'ข้อมูลรายวิชา')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h4>รายวิชาทั้งหมดที่มีผู้ช่วยสอน</h4>

                    @if (isset($currentSemester))
                        <div class="alert alert-info">
                            กำลังแสดงข้อมูลภาคการศึกษา: {{ $currentSemester->year }}/{{ $currentSemester->semesters }}
                            ({{ \Carbon\Carbon::parse($currentSemester->start_date)->format('d/m/Y') }} -
                            {{ \Carbon\Carbon::parse($currentSemester->end_date)->format('d/m/Y') }})
                        </div>
                    @endif

                    <div class="container shadow-lg bg-body rounded p-5">
                        @if (empty($subjects))
                            <p>ไม่พบรายวิชาที่มีผู้ช่วยสอนในภาคการศึกษานี้</p>
                        @else
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col">ลำดับ</th>
                                        <th scope="col">รหัสวิชา</th>
                                        <th scope="col">ชื่อวิชา</th>
                                        <th scope="col">จำนวนผู้ช่วยสอน</th>
                                        <th scope="col">รายการรออนุมัติ</th>
                                        <th scope="col"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($subjects as $index => $subject)
                                        <tr>
                                            <th scope="row">{{ $index + 1 }}</th>
                                            <td>{{ $subject['subject_id'] }}</td>
                                            <td>{{ $subject['name_en'] }}</td>
                                            <td>{{ $subject['ta_count'] }}</td>
                                            <td>
                                                @if(isset($subject['pending_attendances']) && $subject['pending_attendances'] > 0)
                                                    <span class="badge bg-danger">{{ $subject['pending_attendances'] }}</span>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                            <td>
                                                <a class="btn btn-primary btn-sm"
                                                    href="{{ url('/teacher/subjectDetail/' . $subject['courses'][0]['course_id']) }}">
                                                    รายละเอียดผู้ช่วยสอน
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
