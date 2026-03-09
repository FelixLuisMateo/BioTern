<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;

class DatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        //
    }

    public function boot()
    {
        // Log database queries in development
        if (env('APP_DEBUG')) {
            DB::listen(function ($query) {
                \Log::debug('SQL Query: ' . $query->sql);
                \Log::debug('Bindings: ' . json_encode($query->bindings));
                \Log::debug('Time: ' . $query->time . 'ms');
            });
        }
    }
}