@extends('layouts.adminLayout')

@section('title', 'จัดการงบประมาณรายวิชา')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">งบประมาณรายวิชา</h4>
                    <a href="{{ route('admin.compensation-rates.index') }}" class="btn btn-info">
                        <i class="fas fa-money-bill-wave"></i> จัดการอัตราค่าตอบแทน
                    </a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>รหัสวิชา</th>
                                    <th>ชื่อวิชา</th>
                                    <th class="text-center">จำนวนนักศึกษา</th>
                                    <th class="text-center">งบประมาณรวม</th>
                                    <th class="text-center">ใช้ไปแล้ว</th>
                                    <th class="text-center">คงเหลือ</th>
                                    <th class="text-center">ผู้ช่วยสอน</th>
                                    <th class="text-center">สถานะ</th>
                                    <th class="text-center">การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($courses as $course)
                                    <tr>
                                        <td>{{ $course['subject_id'] }}</td>
                                        <td>{{ $course['subject_name'] }}</td>
                                        <td class="text-center">{{ number_format($course['total_students']) }} คน</td>
                                        <td class="text-end">{{ number_format($course['total_budget'], 2) }} บาท</td>
                                        <td class="text-end">{{ number_format($course['used_budget'], 2) }} บาท</td>
                                        <td class="text-end">{{ number_format($course['remaining_budget'], 2) }} บาท</td>
                                        <td class="text-center">{{ $course['total_tas'] }} คน</td>
                                        <td class="text-center">
                                            @if($course['has_budget'])
                                                <span class="badge bg-success">คำนวณแล้ว</span>
                                            @else
                                                <span class="badge bg-warning">ยังไม่คำนวณ</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <form action="{{ route('admin.course-budgets.calculate') }}" method="POST" class="me-1">
                                                    @csrf
                                                    <input type="hidden" name="course_id" value="{{ $course['course_id'] }}">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-calculator"></i> คำนวณงบประมาณ
                                                    </button>
                                                </form>
                                                
                                                <a href="{{ route('admin.course-budgets.details', $course['course_id']) }}" class="btn btn-sm btn-info">
                                                    <i class="fas fa-search"></i> รายละเอียด
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center">ไม่พบข้อมูลรายวิชา</td>
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