<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hour_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->date('log_date');
            $table->decimal('hours_rendered', 5, 2);
            $table->decimal('cumulative_hours', 8, 2);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->unique(['internship_id', 'log_date']);
            $table->index(['student_id', 'log_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hour_logs');
    }
};