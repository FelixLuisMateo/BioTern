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

        $supervisorsRaw = DB::table('students')->whereNotNull('supervisor_name')->distinct()->orderBy('supervisor_name')->pluck('supervisor_name')->all();
        $supervisors = array_map(function ($s) { return ['name' => $s]; }, $supervisorsRaw);

        $coordinatorsRaw = DB::table('students')->whereNotNull('coordinator_name')->distinct()->orderBy('coordinator_name')->pluck('coordinator_name')->all();
        $coordinators = array_map(function ($c) { return ['name' => $c]; }, $coordinatorsRaw);

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
            'internal_total_hours' => $request->input('internal_total_hours') !== '' ? intval($request->input('internal_total_hours')) : null,
            'internal_total_hours_remaining' => $request->input('internal_total_hours_remaining') !== '' ? intval($request->input('internal_total_hours_remaining')) : null,
            'external_total_hours' => $request->input('external_total_hours') !== '' ? intval($request->input('external_total_hours')) : null,
            'external_total_hours_remaining' => $request->input('external_total_hours_remaining') !== '' ? intval($request->input('external_total_hours_remaining')) : null,
            'assignment_track' => in_array($request->input('assignment_track'), ['internal','external']) ? $request->input('assignment_track') : 'internal',
            'course_id' => $request->input('course_id') ? intval($request->input('course_id')) : null,
            'status' => $request->input('status') ? intval($request->input('status')) : 1,
            'supervisor_name' => $request->input('supervisor_id'),
            'coordinator_name' => $request->input('coordinator_id'),
            'updated_at' => DB::raw('NOW()'),
        ];

        if ($profilePath) {
            $data['profile_picture'] = $profilePath;
        }

        DB::table('students')->where('id', $id)->update($data);

        return redirect()->route('students.edit', ['id' => $id])->with('success_message', '✓ Student information updated successfully!');
    }
}
