<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Student;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Display a listing of all attendances.
     */
    public function index(Request $request)
    {
        $query = Attendance::with(['student', 'approver']);

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('attendance_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by student
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Search by student name
        if ($request->filled('search')) {
            $query->whereHas('student', function ($q) {
                $q->where('name', 'like', '%' . request('search') . '%');
            });
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->paginate(15);
        $students = Student::select('id', 'name')->orderBy('name')->get();

        return view('attendances.index', compact('attendances', 'students'));
    }

    /**
     * Display a specific student's attendance records.
     */
    public function studentAttendance($studentId, Request $request)
    {
        $student = Student::findOrFail($studentId);
        $query = $student->attendances();

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('attendance_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->paginate(10);

        return view('attendances.student', compact('student', 'attendances'));
    }

    /**
     * Show the form for creating a new attendance record (biometric system would handle this).
     */
    public function create()
    {
        $students = Student::all();
        return view('attendances.create', compact('students'));
    }

    /**
     * Store a newly created attendance record from biometric system.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:students,id',
            'attendance_date' => 'required|date',
            'time_in_type' => 'required|in:morning_time_in,break_time_out,afternoon_time_in', // biometric triggers
            'time' => 'required|date_format:H:i:s',
        ]);

        $attendance = Attendance::firstOrCreate(
            [
                'student_id' => $validated['student_id'],
                'attendance_date' => $validated['attendance_date'],
            ]
        );

        // Update the appropriate time field based on biometric scan
        $timeField = $validated['time_in_type'];
        $attendance->{$timeField} = $validated['time'];
        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Attendance recorded successfully'
        ]);
    }

    /**
     * Show the form for editing attendance.
     */
    public function edit(Attendance $attendance)
    {
        return view('attendances.edit', compact('attendance'));
    }

    /**
     * Update attendance record.
     */
    public function update(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'morning_time_in' => 'nullable|date_format:H:i',
            'morning_time_out' => 'nullable|date_format:H:i',
            'break_time_in' => 'nullable|date_format:H:i',
            'break_time_out' => 'nullable|date_format:H:i',
            'afternoon_time_in' => 'nullable|date_format:H:i',
            'afternoon_time_out' => 'nullable|date_format:H:i',
            'remarks' => 'nullable|string|max:500',
        ]);

        $attendance->update($validated);

        return redirect()->route('attendances.index')
            ->with('success', 'Attendance updated successfully');
    }

    /**
     * Approve attendance record.
     */
    public function approve(Attendance $attendance)
    {
        $attendance->update([
            'status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
        ]);

        return redirect()->back()
            ->with('success', 'Attendance approved successfully');
    }

    /**
     * Reject attendance record.
     */
    public function reject(Request $request, Attendance $attendance)
    {
        $validated = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $attendance->update([
            'status' => 'rejected',
            'approved_by' => auth()->id(),
            'approved_at' => Carbon::now(),
            'remarks' => $validated['remarks'],
        ]);

        return redirect()->back()
            ->with('success', 'Attendance rejected successfully');
    }

    /**
     * Bulk approve attendances.
     */
    public function bulkApprove(Request $request)
    {
        $validated = $request->validate([
            'attendance_ids' => 'required|array',
            'attendance_ids.*' => 'exists:attendances,id',
        ]);

        Attendance::whereIn('id', $validated['attendance_ids'])
            ->update([
                'status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => Carbon::now(),
            ]);

        return redirect()->back()
            ->with('success', 'Selected attendances approved successfully');
    }

    /**
     * Export attendance to CSV.
     */
    public function export(Request $request)
    {
        $query = Attendance::with('student');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('attendance_date', [
                $request->start_date,
                $request->end_date
            ]);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->get();

        $filename = 'attendance_' . date('Y-m-d_His') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
        ];

        $callback = function() use ($attendances) {
            $file = fopen('php://output', 'w');
            
            // Header row
            fputcsv($file, [
                'Date',
                'Student ID',
                'Student Name',
                'Course',
                'Morning In',
                'Morning Out',
                'Break In',
                'Break Out',
                'Afternoon In',
                'Afternoon Out',
                'Total Hours',
                'Status',
                'Approved By',
                'Remarks'
            ]);

            foreach ($attendances as $attendance) {
                fputcsv($file, [
                    $attendance->attendance_date->format('Y-m-d'),
                    $attendance->student->id,
                    $attendance->student->name,
                    $attendance->student->course ?? 'N/A',
                    $attendance->morning_time_in ?? '-',
                    $attendance->morning_time_out ?? '-',
                    $attendance->break_time_in ?? '-',
                    $attendance->break_time_out ?? '-',
                    $attendance->afternoon_time_in ?? '-',
                    $attendance->afternoon_time_out ?? '-',
                    $attendance->calculateTotalHours() . 'h',
                    ucfirst($attendance->status),
                    $attendance->approver?->name ?? '-',
                    $attendance->remarks ?? '-',
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}