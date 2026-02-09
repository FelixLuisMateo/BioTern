<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Internship extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'course_id',
        'department_id',
        'coordinator_id',
        'supervisor_id',
        'type',
        'company_name',
        'company_address',
        'position',
        'start_date',
        'end_date',
        'ojt_description',
        'status',
        'school_year',
        'required_hours',
        'rendered_hours',
        'completion_percentage',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function coordinator()
    {
        return $this->belongsTo(User::class, 'coordinator_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function dailyTimeRecords()
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    public function hourLogs()
    {
        return $this->hasMany(HourLog::class);
    }

    public function evaluation()
    {
        return $this->hasOne(Evaluation::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function certificate()
    {
        return $this->hasOne(Certificate::class);
    }

    // Scopes
    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeBySchoolYear($query, $schoolYear)
    {
        return $query->where('school_year', $schoolYear);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    // Methods
    public function isCompleted()
    {
        return $this->rendered_hours >= $this->required_hours;
    }

    public function getRemainingHours()
    {
        return max(0, $this->required_hours - $this->rendered_hours);
    }

    public function getCompletionPercentage()
    {
        return round(($this->rendered_hours / $this->required_hours) * 100, 2);
    }

    public function canStartEvaluation()
    {
        return $this->isCompleted() && $this->status === 'ongoing';
    }
}