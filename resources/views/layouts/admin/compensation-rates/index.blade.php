@extends('layouts.adminLayout')

@section('title', 'จัดการอัตราค่าตอบแทน')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">อัตราค่าตอบแทนผู้ช่วยสอน</h4>
                    <a href="{{ route('admin.compensation-rates.create') }}" class="btn btn-primary">เพิ่มอัตราค่าตอบแทนใหม่</a>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>ประเภทการสอน</th>
                                    <th>ประเภทคลาส</th>
                                    <th>ระดับการศึกษา</th>
                                    <th>รูปแบบการจ่าย</th>
                                    <th>อัตราค่าตอบแทน</th>
                                    <th>สถานะ</th>
                                    <th>อัปเดตล่าสุด</th>
                                    <th>การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rates as $rate)
                                    <tr>
                                        <td>
                                            @if($rate->teaching_type == 'regular')
                                                โครงการปกติ
                                            @else
                                                โครงการพิเศษ
                                            @endif
                                        </td>
                                        <td>
                                            @if($rate->class_type == 'LECTURE')
                                                บรรยาย
                                            @else
                                                ปฏิบัติการ
                                            @endif
                                        </td>
                                        <td>
                                            @if($rate->degree_level == 'undergraduate')
                                                ปริญญาตรี
                                            @else
                                                บัณฑิตศึกษา
                                            @endif
                                        </td>
                                        <td>
                                            @if($rate->is_fixed_payment)
                                                เหมาจ่ายรายเดือน
                                            @else
                                                รายชั่วโมง
                                            @endif
                                        </td>
                                        <td>
                                            @if($rate->is_fixed_payment)
                                                {{ number_format($rate->fixed_amount, 2) }} บาท/เดือน
                                            @else
                                                {{ number_format($rate->rate_per_hour, 2) }} บาท/ชั่วโมง
                                            @endif
                                        </td>
                                        <td>
                                            @if($rate->status == 'active')
                                                <span class="badge bg-success">ใช้งาน</span>
                                            @else
                                                <span class="badge bg-danger">ไม่ใช้งาน</span>
                                            @endif
                                        </td>
                                        <td>{{ $rate->updated_at->format('d/m/Y H:i') }}</td>
                                        <td>
                                            <a href="{{ route('admin.compensation-rates.edit', $rate->id) }}" class="btn btn-sm btn-primary">แก้ไข</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">ไม่พบข้อมูลอัตราค่าตอบแทน</td>
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