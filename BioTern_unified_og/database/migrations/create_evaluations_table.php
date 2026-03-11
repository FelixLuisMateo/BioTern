<?php
// Migration for evaluations table
use Illuminate\Database\Capsule\Schema as Schema;

if (!Schema::hasTable('evaluations')) {
    Schema::create('evaluations', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('student_id');
        $table->string('evaluator_name')->nullable();
        $table->date('evaluation_date');
        $table->integer('score')->nullable();
        $table->longText('feedback')->nullable();
        $table->timestamps();
        $table->softDeletes();
    });
}
