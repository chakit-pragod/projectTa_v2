@extends('layouts.adminLayout')

@section('title', 'ตัวอย่างการคำนวณค่าตอบแทน')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">ตัวอย่างการคำนวณค่าตอบแทน</h4>
                    </div>
                    <div class="card-body">
                        @if (isset($compensationData['error']))
                            <div class="alert alert-danger">
                                {{ $compensationData['error'] }}
                            </div>
                        @else
                            <h5>ข้อมูลผู้ช่วยสอน</h5>
                            <p>ชื่อ: {{ $compensationData['student']->name }}</p>
                            <p>รหัสนักศึกษา: {{ $compensationData['student']->student_id }}</p>

                            <h5>ข้อมูลรายวิชา</h5>
                            <p>รหัสวิชา: {{ $compensationData['course']->subjects->subject_id }}</p>
                            <p>ชื่อวิชา: {{ $compensationData['course']->subjects->name_en }}</p>

                            <h5>งบประมาณคงเหลือ</h5>
                            <p>งบประมาณคงเหลือ: {{ number_format($compensationData['remainingBudgetForTA'], 2) }} บาท</p>

                            <h5>จำนวนเงินที่ต้องการเบิกจ่าย</h5>
                            <form action="{{ route('admin.course-budgets.compensation.save') }}" method="POST">
                                @csrf
                                <input type="hidden" name="student_id" value="{{ $compensationData['student']->id }}">
                                <input type="hidden" name="course_id" value="{{ $compensationData['course']->course_id }}">
                                <input type="hidden" name="month_year" value="{{ $compensationData['year_month'] }}">
                                <input type="hidden" name="hours_worked" value="{{ $compensationData['total_hours'] }}">
                                <input type="hidden" name="calculated_amount" value="{{ $compensationData['pay']['total'] }}">

                                <div class="form-group">
                                    <label for="actual_amount">จำนวนเงินที่จะเบิกจ่ายจริง:</label>
                                    <input type="number" id="actual_amount" name="actual_amount" class="form-control"
                                           value="{{ $compensationData['final_amount'] }}" max="{{ $compensationData['remainingBudgetForTA'] }}" step="0.01" required>
                                    @if ($compensationData['is_exceeded'])
                                        <small class="text-danger">จำนวนเงินเกินงบประมาณที่เหลืออยู่ กรุณาปรับจำนวนเงินที่ต้องการเบิกจ่าย</small>
                                    @endif
                                </div>

                                <div class="form-group">
                                    <label for="adjustment_reason">เหตุผลในการปรับยอด (ถ้ามี):</label>
                                    <textarea id="adjustment_reason" name="adjustment_reason" class="form-control" rows="2">{{ $compensationData['adjustment_reason'] ?? '' }}</textarea>
                                </div>

                                <button type="submit" class="btn btn-primary">บันทึกการเบิกจ่าย</button>
                                <a href="{{ route('admin.ta.profile', $compensationData['student']->id) }}" class="btn btn-secondary">ยกเลิก</a>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection