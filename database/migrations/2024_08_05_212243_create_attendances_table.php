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
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->char('status');
            $table->char('approve_status')->nullable();
            $table->char('approve_note')->nullable();
            $table->dateTime('approve_at', precision: 0)->nullable();
            $table->integer('approve_user_id')->nullable();
            $table->string('note');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('teaching_id');
            $table->unsignedBigInteger('student_id');

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('teaching_id')->references('teaching_id')->on('teaching')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
