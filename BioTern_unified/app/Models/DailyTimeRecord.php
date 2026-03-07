<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyTimeRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'daily_time_records';

    protected $fillable = [
        'internship_id',
        'student_id',
        'date',
        'morning_time_in',
        'morning_time_out',
        'break_time_in',
        'break_time_out',
        'afternoon_time_in',
        'afternoon_time_out',
        'total_hours',
        'source',
        'status',
        'remarks',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'date' => 'date',
        'approved_at' => 'datetime',
    ];

    // Relationships
    public function internship()
    {
        return $this->belongsTo(Internship::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Methods
    public function calculateTotalHours()
    {
        $morningHours = 0;
        $afternoonHours = 0;

        if ($this->morning_time_in && $this->morning_time_out) {
            $morningHours = $this->getTimeDifference($this->morning_time_in, $this->morning_time_out);
        }

        if ($this->afternoon_time_in && $this->afternoon_time_out) {
            $afternoonHours = $this->getTimeDifference($this->afternoon_time_in, $this->afternoon_time_out);
        }

        return round($morningHours + $afternoonHours, 2);
    }

    private function getTimeDifference($timeIn, $timeOut)
    {
        $inSeconds = strtotime($timeIn);
        $outSeconds = strtotime($timeOut);
        return ($outSeconds - $inSeconds) / 3600;
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeByDate($query, $date)
    {
        return $query->where('date', $date);
    }
}