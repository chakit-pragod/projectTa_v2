@extends('layouts.taLayout')

@section('title', 'request')
@section('break', 'สถานะคำร้องการสมัครผู้ช่วยสอน')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="container mt-4">
                        <h4 class="mb-3">คำร้องการสมัครผู้ช่วยสอน</h4>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ลำดับ</th>
                                        <th>รหัสนักศึกษา</th>
                                        <th>ชื่อ-นามสกุล</th>
                                        <th>รายวิชาที่สมัคร</th>
                                        <th>เซคชัน</th>
                                        <th>วันที่สมัคร</th>
                                        <th>สถานะการสมัคร</th>
                                        <th>วันที่อนุมัติ</th>
                                        <th>ความคิดเห็น</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($requests as $index => $request)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $request->courseTaClass->courseTa->student->student_id }}</td>
                                            <td>{{ $request->courseTaClass->courseTa->student->fname }}
                                                {{ $request->courseTaClass->courseTa->student->lname }}</td>
                                            <td>{{ $request->courseTaClass->class->course->subjects->subject_id }}
                                                {{ $request->courseTaClass->class->course->subjects->name_en }}</td>
                                            <td>{{ $request->courseTaClass->class->section_num }}</td>
                                            <td>{{ $request->created_at ? $request->created_at->format('d-m-Y') : 'N/A' }}</td>
                                            <td>
                                                @php
                                                    $status = strtolower($request->status);
                                                @endphp
                                                @if ($status === 'w')
                                                    <span class="badge bg-warning">รอดำเนินการ</span>
                                                @elseif ($status === 'r')
                                                    <span class="badge bg-danger">ไม่อนุมัติ</span>
                                                @elseif ($status === 'a')
                                                    <span class="badge bg-success">อนุมัติ</span>
                                                @elseif ($status === 'p')
                                                    <span class="badge bg-info">กำลังพิจารณา</span>
                                                @else
                                                    <span class="badge bg-secondary">ไม่ระบุ ({{ $request->status }})</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($request->approved_at)
                                                    @if (is_string($request->approved_at))
                                                        {{ \Carbon\Carbon::parse($request->approved_at)->format('d-m-Y') }}
                                                    @else
                                                        {{ $request->approved_at->format('d-m-Y') }}
                                                    @endif
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td>{{ $request->comment ?? 'ไม่มีความคิดเห็น' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="9" class="text-center">ไม่พบข้อมูลคำร้องการสมัคร</td>
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