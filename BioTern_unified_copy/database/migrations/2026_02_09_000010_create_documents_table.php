<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internship_id')->constrained('internships')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->enum('type', ['application_letter', 'endorsement_letter', 'waiver', 'resume', 'moa', 'dtr', 'certificate'])->default('application_letter');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_mime_type');
            $table->integer('file_size');
            $table->enum('status', ['generated', 'downloaded', 'submitted', 'archived'])->default('generated');
            $table->foreignId('generated_by')->constrained('users')->onDelete('set null');
            $table->timestamp('generated_at');
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};