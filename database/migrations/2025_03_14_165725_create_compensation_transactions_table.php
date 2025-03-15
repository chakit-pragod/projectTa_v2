<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('compensation_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('course_id');
            $table->unsignedBigInteger('student_id');
            $table->string('month_year'); // รูปแบบ YYYY-MM
            $table->decimal('hours_worked', 8, 2)->nullable(); // จำนวนชั่วโมงที่ทำงาน
            $table->decimal('calculated_amount', 10, 2); // จำนวนเงินที่คำนวณได้ตามอัตรา
            $table->decimal('actual_amount', 10, 2); // จำนวนเงินที่เบิกจ่ายจริง
            $table->boolean('is_adjusted')->default(false); // ปรับยอดหรือไม่
            $table->text('adjustment_reason')->nullable(); // เหตุผลในการปรับยอด
            $table->timestamps();
            
            $table->foreign('course_id')->references('course_id')->on('courses');
            $table->foreign('student_id')->references('id')->on('students');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('compensation_transactions');
    }
};
