<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'course_id',
        'student_id',
        'first_name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'date_of_birth',
        'gender',
        'address',
        'emergency_contact',
        'biometric_registered',
        'biometric_registered_at',
        'is_active',
    ];

    protected $casts = [
        'biometric_registered' => 'boolean',
        'biometric_registered_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function internships()
    {
        return $this->hasMany(Internship::class);
    }

    public function biometricData()
    {
        return $this->hasOne(BiometricData::class);
    }

    public function dailyTimeRecords()
    {
        return $this->hasMany(DailyTimeRecord::class);
    }

    public function hourLogs()
    {
        return $this->hasMany(HourLog::class);
    }

    public function evaluations()
    {
        return $this->hasMany(Evaluation::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function certificates()
    {
        return $this->hasMany(Certificate::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCourse($query, $courseId)
    {
        return $query->where('course_id', $courseId);
    }
}