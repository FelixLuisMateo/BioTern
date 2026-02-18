<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    /**
     * Show the dashboard page.
     * Redirect students to the student dashboard view.
     */
    public function index()
    {
        $user = Auth::user();
        if ($user && isset($user->role) && $user->role === 'student') {
            // Load student-specific data
            $studentId = $user->id;

            // Attendance counts for this student
            $attendancePending = \Illuminate\Support\Facades\DB::table('attendances')
                ->where('student_id', $studentId)->where('status', 'pending')->count();
            $attendanceApproved = \Illuminate\Support\Facades\DB::table('attendances')
                ->where('student_id', $studentId)->where('status', 'approved')->count();
            $attendanceRejected = \Illuminate\Support\Facades\DB::table('attendances')
                ->where('student_id', $studentId)->where('status', 'rejected')->count();

            // Recent attendance records
            $recentAttendance = \Illuminate\Support\Facades\DB::table('attendances as a')
                ->leftJoin('students as s', 'a.student_id', '=', 's.id')
                ->select('a.id','a.attendance_date','a.morning_time_in','a.morning_time_out','a.status','a.created_at')
                ->where('a.student_id', $studentId)
                ->orderBy('a.attendance_date', 'desc')
                ->limit(10)
                ->get();

            // Internships for this student
            $internships = \Illuminate\Support\Facades\DB::table('internships')
                ->where('student_id', $studentId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Supervisor lookup: try students.supervisor_id then internships.supervisor_id
            $supervisor = null;
            $supId = \Illuminate\Support\Facades\DB::table('students')->where('id', $studentId)->value('supervisor_id');
            if ($supId) {
                $supervisor = \Illuminate\Support\Facades\DB::table('users as u')
                    ->leftJoin('supervisors as s', 'u.id', '=', 's.user_id')
                    ->select('u.id','u.name','u.email','s.phone','s.department')
                    ->where('u.id', $supId)
                    ->first();
            } else {
                // fallback: check internships for supervisor_id
                $supId = \Illuminate\Support\Facades\DB::table('internships')->where('student_id', $studentId)->value('supervisor_id');
                if ($supId) {
                    $supervisor = \Illuminate\Support\Facades\DB::table('users as u')
                        ->leftJoin('supervisors as s', 'u.id', '=', 's.user_id')
                        ->select('u.id','u.name','u.email','s.phone','s.department')
                        ->where('u.id', $supId)
                        ->first();
                }
            }

            return view('dashboard_student')->with([
                'user' => $user,
                'attendancePending' => $attendancePending,
                'attendanceApproved' => $attendanceApproved,
                'attendanceRejected' => $attendanceRejected,
                'recentAttendance' => $recentAttendance,
                'internships' => $internships,
                'supervisor' => $supervisor,
            ]);
        }

        return view('dashboard_admin');
    }
}
