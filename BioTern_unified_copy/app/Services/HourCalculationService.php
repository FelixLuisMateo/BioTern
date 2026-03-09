<?php

namespace App\Services;

use App\Models\Internship;
use App\Models\HourLog;
use App\Models\DailyTimeRecord;

class HourCalculationService
{
    public function calculateAndUpdateHours(Internship $internship)
    {
        $approvedDTRs = $internship->dailyTimeRecords()
            ->where('status', 'approved')
            ->get();

        $totalHours = $approvedDTRs->sum('total_hours');

        $internship->update([
            'rendered_hours' => $totalHours,
            'completion_percentage' => $this->calculatePercentage($totalHours, $internship->required_hours),
        ]);

        $this->logHours($internship, $totalHours);

        return $internship;
    }

    public function calculatePercentage($rendered, $required)
    {
        return round(($rendered / $required) * 100, 2);
    }

    public function getRemainingHours(Internship $internship)
    {
        return max(0, $internship->required_hours - $internship->rendered_hours);
    }

    private function logHours(Internship $internship, $totalHours)
    {
        HourLog::updateOrCreate(
            [
                'internship_id' => $internship->id,
                'student_id' => $internship->student_id,
                'log_date' => now()->toDateString(),
            ],
            [
                'hours_rendered' => $totalHours,
                'cumulative_hours' => $totalHours,
            ]
        );
    }
}