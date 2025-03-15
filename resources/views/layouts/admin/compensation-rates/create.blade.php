@extends('layouts.adminLayout')

@section('title', 'เพิ่มอัตราค่าตอบแทนใหม่')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">เพิ่มอัตราค่าตอบแทนใหม่</h4>
                </div>
                <div class="card-body">
                    @if($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.compensation-rates.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="teaching_type" class="form-label">ประเภทการสอน <span class="text-danger">*</span></label>
                            <select name="teaching_type" id="teaching_type" class="form-select" required>
                                <option value="regular">โครงการปกติ</option>
                                <option value="special">โครงการพิเศษ</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="class_type" class="form-label">ประเภทคลาส <span class="text-danger">*</span></label>
                            <select name="class_type" id="class_type" class="form-select" required>
                                <option value="LECTURE">บรรยาย</option>
                                <option value="LAB">ปฏิบัติการ</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="degree_level" class="form-label">ระดับการศึกษา <span class="text-danger">*</span></label>
                            <select name="degree_level" id="degree_level" class="form-select" required>
                                <option value="undergraduate">ระดับปริญญาตรี</option>
                                <option value="graduate">ระดับบัณฑิตศึกษา</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="is_fixed_payment" class="form-label">รูปแบบการจ่าย</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_fixed_payment" name="is_fixed_payment" value="1">
                                <label class="form-check-label" for="is_fixed_payment">
                                    จ่ายแบบเหมาจ่ายรายเดือน
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="fixed_amount_container" style="display: none;">
                            <label for="fixed_amount" class="form-label">จำนวนเงินเหมาจ่าย (บาท)</label>
                            <input type="number" min="0" step="0.01" name="fixed_amount" id="fixed_amount" class="form-control">
                        </div>
                        
                        <div class="mb-3">
                            <label for="rate_per_hour" class="form-label">อัตราต่อชั่วโมง (บาท)</label>
                            <input type="number" min="0" step="0.01" name="rate_per_hour" id="rate_per_hour" class="form-control">
                            <small class="form-text text-muted">จำเป็นต้องกรอกถ้าไม่ได้เลือกแบบเหมาจ่าย</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">สถานะ <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active">ใช้งาน</option>
                                <option value="inactive">ไม่ใช้งาน</option>
                            </select>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">บันทึก</button>
                            <a href="{{ route('admin.compensation-rates.index') }}" class="btn btn-secondary">ยกเลิก</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
   document.addEventListener('DOMContentLoaded', function() {
    const isFixedPayment = document.getElementById('is_fixed_payment');
    const fixedAmountContainer = document.getElementById('fixed_amount_container');
    const ratePerHourContainer = document.getElementById('rate_per_hour').parentElement;
    const ratePerHourInput = document.getElementById('rate_per_hour');
    const fixedAmountInput = document.getElementById('fixed_amount');
    
    isFixedPayment.addEventListener('change', function() {
        if (this.checked) {
            fixedAmountContainer.style.display = 'block';
            ratePerHourContainer.style.display = 'none';
            // ยกเลิก required สำหรับ rate_per_hour และเพิ่ม required สำหรับ fixed_amount
            ratePerHourInput.removeAttribute('required');
            fixedAmountInput.setAttribute('required', 'required');
        } else {
            fixedAmountContainer.style.display = 'none';
            ratePerHourContainer.style.display = 'block';
            // เพิ่ม required กลับคืนสำหรับ rate_per_hour และยกเลิก required สำหรับ fixed_amount
            ratePerHourInput.setAttribute('required', 'required');
            fixedAmountInput.removeAttribute('required');
        }
    });
});
</script>

@endsection