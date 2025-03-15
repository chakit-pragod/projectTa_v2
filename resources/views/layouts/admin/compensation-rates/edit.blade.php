@extends('layouts.adminLayout')

@section('title', 'แก้ไขอัตราค่าตอบแทน')

@section('content')
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">แก้ไขอัตราค่าตอบแทน</h4>
                    </div>
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('admin.compensation-rates.update', $rate->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-3">
                                <label class="form-label">ประเภทการสอน</label>
                                <input type="text" class="form-control" readonly
                                    value="{{ $rate->teaching_type == 'regular' ? 'โครงการปกติ' : 'โครงการพิเศษ' }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ประเภทคลาส</label>
                                <input type="text" class="form-control" readonly
                                    value="{{ $rate->class_type == 'LECTURE' ? 'บรรยาย' : 'ปฏิบัติการ' }}">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">ระดับการศึกษา</label>
                                <input type="text" class="form-control" readonly
                                    value="{{ $rate->degree_level == 'undergraduate' ? 'ระดับปริญญาตรี' : 'ระดับบัณฑิตศึกษา' }}">
                            </div>

                            <div class="mb-3">
                                <label for="is_fixed_payment" class="form-label">รูปแบบการจ่าย</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="is_fixed_payment"
                                        name="is_fixed_payment" value="1"
                                        {{ $rate->is_fixed_payment ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_fixed_payment">
                                        จ่ายแบบเหมาจ่ายรายเดือน
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="fixed_amount_container"
                                style="{{ $rate->is_fixed_payment ? '' : 'display: none;' }}">
                                <label for="fixed_amount" class="form-label">จำนวนเงินเหมาจ่าย (บาท)</label>
                                <input type="number" min="0" step="0.01" name="fixed_amount" id="fixed_amount"
                                    class="form-control" value="{{ $rate->fixed_amount }}">
                            </div>

                            <div class="mb-3">
                                <label for="rate_per_hour" class="form-label">อัตราต่อชั่วโมง (บาท) <span
                                        class="text-danger">*</span></label>
                                <input type="number" min="0" step="0.01" name="rate_per_hour" id="rate_per_hour"
                                    class="form-control" value="{{ $rate->rate_per_hour }}" required>
                            </div>

                            <div class="mb-3">
                                <label for="status" class="form-label">สถานะ <span class="text-danger">*</span></label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="active" {{ $rate->status == 'active' ? 'selected' : '' }}>ใช้งาน</option>
                                    <option value="inactive" {{ $rate->status == 'inactive' ? 'selected' : '' }}>ไม่ใช้งาน
                                    </option>
                                </select>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">บันทึก</button>
                                <a href="{{ route('admin.compensation-rates.index') }}"
                                    class="btn btn-secondary">ยกเลิก</a>
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

            // ตั้งค่าเริ่มต้น
            if (isFixedPayment.checked) {
                ratePerHourContainer.style.display = 'none';
            }

            isFixedPayment.addEventListener('change', function() {
                if (this.checked) {
                    fixedAmountContainer.style.display = 'block';
                    ratePerHourContainer.style.display = 'none';
                } else {
                    fixedAmountContainer.style.display = 'none';
                    ratePerHourContainer.style.display = 'block';
                }
            });
        });
    </script>
@endsection
