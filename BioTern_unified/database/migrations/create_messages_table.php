<?php
// Migration for messages table
use Illuminate\Database\Capsule\Schema as Schema;

if (!Schema::hasTable('messages')) {
    Schema::create('messages', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedBigInteger('from_user_id');
        $table->unsignedBigInteger('to_user_id');
        $table->string('subject')->nullable();
        $table->longText('message');
        $table->boolean('is_read')->default(0);
        $table->timestamps();
        $table->softDeletes();
    });
}
