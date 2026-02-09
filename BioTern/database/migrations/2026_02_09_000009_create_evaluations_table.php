<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('supervisor_id')->constrained('users')->onDelete('restrict');
            $table->integer('punctuality_rating')->nullable(); // 1-5
            $table->integer('quality_of_work_rating')->nullable();
            $table->integer('teamwork_rating')->nullable();
            $table->integer('initiative_rating')->nullable();
            $table->integer('communication_rating')->nullable();
            $table->integer('professionalism_rating')->nullable();
            $table->integer('problem_solving_rating')->nullable();
            $table->integer('technical_skills_rating')->nullable();
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->text('strengths')->nullable();
            $table->text('areas_for_improvement')->nullable();
            $table->text('comments')->nullable();
            $table->boolean('passed')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};