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
        if (!Schema::hasTable('compensation_rates')) {
            Schema::create('compensation_rates', function (Blueprint $table) {
                $table->id();
                $table->string('teaching_type'); // 'regular' (ภาคปกติ) หรือ 'special' (ภาคพิเศษ)
                $table->string('class_type');    // 'LECTURE', 'LAB' หรือประเภทอื่นๆ 
                $table->enum('degree_level', ['undergraduate', 'graduate'])->default('undergraduate');
                $table->decimal('rate_per_hour', 10, 2)->nullable();
                $table->boolean('is_fixed_payment')->default(false);
                $table->decimal('fixed_amount', 10, 2)->nullable();
                $table->enum('status', ['active', 'inactive'])->default('active');
                $table->timestamps();
                
                $table->index(['teaching_type', 'class_type', 'status']);
            });
        } else {
            Schema::table('compensation_rates', function (Blueprint $table) {
                if (!Schema::hasColumn('compensation_rates', 'degree_level')) {
                    $table->enum('degree_level', ['undergraduate', 'graduate'])->after('class_type')->default('undergraduate');
                }
                if (!Schema::hasColumn('compensation_rates', 'is_fixed_payment')) {
                    $table->boolean('is_fixed_payment')->default(false)->after('rate_per_hour');
                }
                if (!Schema::hasColumn('compensation_rates', 'fixed_amount')) {
                    $table->decimal('fixed_amount', 10, 2)->nullable()->after('is_fixed_payment');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('compensation_rates');
        
        Schema::table('compensation_rates', function (Blueprint $table) {
            $table->dropColumn(['degree_level', 'is_fixed_payment', 'fixed_amount']);
        });
    }
};