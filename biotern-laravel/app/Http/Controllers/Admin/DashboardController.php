<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function internshipSummary()
    {
        return view('dashboard_admin');
    }

    public function systemLogs()
    {
        return view('dashboard_admin');
    }
}
