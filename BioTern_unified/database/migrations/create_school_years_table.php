<?php
// Migration for school_years table
use Illuminate\Database\Capsule\Schema as Schema;

if (!Schema::hasTable('school_years')) {
    Schema::create('school_years', function ($table) {
        $table->increments('id');
        $table->string('year')->unique();
        $table->timestamps();
    });
}
