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
        Schema::create('course_budgets', function (Blueprint $table) {
            $table->id();
            $table->string('course_id');
            $table->integer('total_students'); 
            $table->decimal('total_budget', 10, 2);
            $table->decimal('used_budget', 10, 2)->default(0); 
            $table->decimal('remaining_budget', 10, 2);
            $table->timestamps();
            $table->foreign('course_id')->references('course_id')->on('courses');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
