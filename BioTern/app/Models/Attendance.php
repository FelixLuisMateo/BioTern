<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'attendance_date',
        'morning_time_in',
        'morning_time_out',
        'break_time_in',
        'break_time_out',
        'afternoon_time_in',
        'afternoon_time_out',
        'status',
        'approved_by',
        'approved_at',
        'remarks',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * Get the student associated with this attendance.
     */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who approved this attendance.
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Calculate total hours worked for the day.
     */
    public function calculateTotalHours()
    {
        $morning = 0;
        $afternoon = 0;

        if ($this->morning_time_in && $this->morning_time_out) {
            $morning = strtotime($this->morning_time_out) - strtotime($this->morning_time_in);
        }

        if ($this->afternoon_time_in && $this->afternoon_time_out) {
            $afternoon = strtotime($this->afternoon_time_out) - strtotime($this->afternoon_time_in);
        }

        $total = ($morning + $afternoon) / 3600; // Convert to hours
        return round($total, 2);
    }

    /**
     * Get status badge class.
     */
    public function getStatusBadgeClass()
    {
        return match($this->status) {
            'approved' => 'bg-success',
            'rejected' => 'bg-danger',
            default => 'bg-warning',
        };
    }
}