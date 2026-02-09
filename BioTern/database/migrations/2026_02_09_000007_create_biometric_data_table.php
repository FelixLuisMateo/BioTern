<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometric_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->string('biometric_template')->nullable();
            $table->string('biometric_type')->default('fingerprint'); // fingerprint, face, iris
            $table->enum('registration_status', ['pending', 'registered', 'failed'])->default('pending');
            $table->timestamp('registered_at')->nullable();
            $table->string('device_id')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'biometric_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biometric_data');
    }
};