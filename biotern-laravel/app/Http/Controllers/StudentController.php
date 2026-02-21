<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class StudentController extends Controller
{
    public function edit($id)
    {
        $id = intval($id);
        $studentQuery = "
            SELECT
                s.id,
                s.student_id,
                s.profile_picture,
                s.first_name,
                s.last_name,
                s.middle_name,
                s.email,
                s.phone,
                s.date_of_birth,
                s.gender,
                s.address,
                s.emergency_contact,
                s.internal_total_hours,
                s.internal_total_hours_remaining,
                s.external_total_hours,
                s.external_total_hours_remaining,
                s.assignment_track,
                s.status,
                s.biometric_registered,
                s.biometric_registered_at,
                s.created_at,
                s.supervisor_name,
                s.coordinator_name,
                c.name as course_name,
                c.id as course_id,
                i.id as internship_id,
                i.supervisor_id,
                i.coordinator_id
            FROM students s
            LEFT JOIN courses c ON s.course_id = c.id
            LEFT JOIN internships i ON s.id = i.student_id AND i.status = 'ongoing'
            WHERE s.id = ?
            LIMIT 1
        ";

        $rows = DB::select($studentQuery, [$id]);
        if (empty($rows)) {
            abort(404, 'Student not found');
        }

        $student = json_decode(json_encode($rows[0]), true);

        $courses = DB::table('courses')->where('is_active', 1)->orderBy('name')->get()->map(function ($c) {
            return (array) $c;
        })->all();

        $supervisors = DB::table('supervisors')
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name")
            ->whereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''")
            ->orderBy('name')
            ->get()
            ->map(function ($s) {
                return ['id' => (int) $s->id, 'name' => $s->name];
            })
            ->all();

        $coordinators = DB::table('coordinators')
            ->selectRaw("id, TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name")
            ->whereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) <> ''")
            ->orderBy('name')
            ->get()
            ->map(function ($c) {
                return ['id' => (int) $c->id, 'name' => $c->name];
            })
            ->all();

        // Determine role from session (compatibility with original app)
        $currentRole = strtolower(trim((string) (session('role') ?? session('user_role') ?? session('account_role') ?? session('user_type') ?? session('type') ?? '')));
        $canEditSensitiveHours = ($currentRole === '' || in_array($currentRole, ['admin', 'coordinator', 'supervisor'], true));

        return view('students-edit', [
            'student' => $student,
            'courses' => $courses,
            'supervisors' => $supervisors,
            'coordinators' => $coordinators,
            'can_edit_sensitive_hours' => $canEditSensitiveHours,
        ]);
    }

    public function update(Request $request, $id)
    {
        $id = intval($id);

        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
        ];

        $validated = $request->validate($rules);

        // Check duplicate email
        $exists = DB::table('students')->where('email', $validated['email'])->where('id', '<>', $id)->exists();
        if ($exists) {
            return back()->with('error_message', 'Email address already exists!')->withInput();
        }

        $profilePath = null;
        if ($request->hasFile('profile_picture') && $request->file('profile_picture')->isValid()) {
            $file = $request->file('profile_picture');
            $ext = $file->getClientOriginalExtension();
            $name = 'student_' . $id . '_' . time() . '.' . $ext;
            $file->move(public_path('uploads/profile_pictures'), $name);
            $profilePath = 'uploads/profile_pictures/' . $name;
        }

        $supervisorId = $request->input('supervisor_id') !== '' ? intval($request->input('supervisor_id')) : null;
        $coordinatorId = $request->input('coordinator_id') !== '' ? intval($request->input('coordinator_id')) : null;

        $selectedSupervisorName = null;
        if ($supervisorId !== null) {
            $selectedSupervisorName = DB::table('supervisors')
                ->where('id', $supervisorId)
                ->selectRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name")
                ->value('name');
        }

        $selectedCoordinatorName = null;
        if ($coordinatorId !== null) {
            $selectedCoordinatorName = DB::table('coordinators')
                ->where('id', $coordinatorId)
                ->selectRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name)) AS name")
                ->value('name');
        }

        $assignmentTrack = in_array($request->input('assignment_track'), ['internal', 'external'], true)
            ? $request->input('assignment_track')
            : 'internal';
        $courseId = $request->input('course_id') ? intval($request->input('course_id')) : null;
        $status = $request->filled('status') ? intval($request->input('status')) : 1;
        $internalTotalHours = $request->input('internal_total_hours') !== '' ? intval($request->input('internal_total_hours')) : null;
        $internalTotalHoursRemaining = $request->input('internal_total_hours_remaining') !== '' ? intval($request->input('internal_total_hours_remaining')) : null;
        $externalTotalHours = $request->input('external_total_hours') !== '' ? intval($request->input('external_total_hours')) : null;
        $externalTotalHoursRemaining = $request->input('external_total_hours_remaining') !== '' ? intval($request->input('external_total_hours_remaining')) : null;

        if ($assignmentTrack === 'external' && ($internalTotalHoursRemaining === null || $internalTotalHoursRemaining > 0)) {
            return back()
                ->with('error_message', 'Cannot assign student to External unless Internal is completed (Internal Total Hours Remaining must be 0).')
                ->withInput();
        }

        $data = [
            'student_id' => $request->input('student_id'),
            'first_name' => $request->input('first_name'),
            'last_name' => $request->input('last_name'),
            'middle_name' => $request->input('middle_name'),
            'email' => $request->input('email'),
            'phone' => $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth') ?: null,
            'gender' => $request->input('gender'),
            'address' => $request->input('address'),
            'emergency_contact' => $request->input('emergency_contact'),
            'internal_total_hours' => $internalTotalHours,
            'internal_total_hours_remaining' => $internalTotalHoursRemaining,
            'external_total_hours' => $externalTotalHours,
            'external_total_hours_remaining' => $externalTotalHoursRemaining,
            'assignment_track' => $assignmentTrack,
            'course_id' => $courseId,
            'status' => $status,
            'supervisor_name' => $selectedSupervisorName,
            'coordinator_name' => $selectedCoordinatorName,
            'updated_at' => DB::raw('NOW()'),
        ];

        if ($profilePath) {
            $data['profile_picture'] = $profilePath;
        }

        DB::table('students')->where('id', $id)->update($data);

        // Keep supervisor/coordinator IDs in internships as source of truth.
        $student = DB::table('students')->where('id', $id)->first();
        $ongoingInternship = DB::table('internships')
            ->where('student_id', $id)
            ->where('status', 'ongoing')
            ->orderByDesc('id')
            ->first();

        if ($ongoingInternship) {
            DB::table('internships')
                ->where('id', $ongoingInternship->id)
                ->update([
                    'supervisor_id' => $supervisorId,
                    'coordinator_id' => $coordinatorId,
                    'status' => 'ongoing',
                    'updated_at' => DB::raw('NOW()'),
                ]);
        } else {
            $latestInternship = DB::table('internships')
                ->where('student_id', $id)
                ->orderByRaw("(status = 'ongoing') DESC")
                ->orderByDesc('id')
                ->first();

            if ($latestInternship) {
                DB::table('internships')
                    ->where('id', $latestInternship->id)
                    ->update([
                        'supervisor_id' => $supervisorId,
                        'coordinator_id' => $coordinatorId,
                        'status' => 'ongoing',
                        'updated_at' => DB::raw('NOW()'),
                    ]);
            } elseif ($coordinatorId !== null) {
                $department = DB::table('departments')->orderBy('id')->first();
                $courseForIntern = $courseId ?: intval($student->course_id ?? 0);

                if ($department && $courseForIntern > 0) {
                    $today = date('Y-m-d');
                    $year = intval(date('Y'));
                    $schoolYear = $year . '-' . ($year + 1);
                    $type = $assignmentTrack === 'external' ? 'external' : 'internal';
                    $requiredHours = $assignmentTrack === 'external'
                        ? intval($externalTotalHours ?? 250)
                        : intval($internalTotalHours ?? 600);
                    if ($requiredHours <= 0) {
                        $requiredHours = $assignmentTrack === 'external' ? 250 : 600;
                    }

                    $remaining = $assignmentTrack === 'external'
                        ? intval($externalTotalHoursRemaining ?? $requiredHours)
                        : intval($internalTotalHoursRemaining ?? $requiredHours);
                    $renderedHours = max(0, $requiredHours - max(0, $remaining));
                    $completionPct = $requiredHours > 0 ? min(100, ($renderedHours / $requiredHours) * 100) : 0;

                    DB::table('internships')->insert([
                        'student_id' => $id,
                        'course_id' => $courseForIntern,
                        'department_id' => intval($department->id),
                        'coordinator_id' => $coordinatorId,
                        'supervisor_id' => $supervisorId,
                        'type' => $type,
                        'start_date' => $today,
                        'status' => 'ongoing',
                        'school_year' => $schoolYear,
                        'required_hours' => $requiredHours,
                        'rendered_hours' => $renderedHours,
                        'completion_percentage' => $completionPct,
                        'created_at' => DB::raw('NOW()'),
                        'updated_at' => DB::raw('NOW()'),
                    ]);
                }
            }
        }

        return redirect()->route('students.edit', ['id' => $id])->with('success_message', '✓ Student information updated successfully!');
    }
}
