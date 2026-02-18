<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('frontend/assets/images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('frontend/assets/css/theme.min.css') }}">
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3">Welcome, {{ $user->name ?? $user->username ?? 'Student' }}</h1>
                <p class="text-muted">Student dashboard summary</p>
                <a href="{{ route('student.dashboard') }}" class="btn btn-sm btn-primary mt-2">My Dashboard</a>
            </div>
            <div>
                <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-outline-secondary">Logout</a>
                <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none">{{ csrf_field() }}</form>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card p-3">
                    <h5 class="mb-1">Profile</h5>
                    <p class="mb-0">Username: <strong>{{ $user->username ?? '-' }}</strong></p>
                    <p class="mb-0">Email: <strong>{{ $user->email ?? '-' }}</strong></p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <h5 class="mb-1">Internships</h5>
                    @if(isset($internships) && count($internships) > 0)
                        <ul class="mb-0">
                            @foreach($internships as $int)
                                <li>{{ $int->title ?? 'Internship' }} — <strong>{{ $int->status ?? 'unknown' }}</strong></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mb-0">No internships found.</p>
                    @endif
                </div>
            </div>
            <div class="col-md-4">
                <div class="card p-3">
                    <h5 class="mb-1">Attendance</h5>
                    <p class="mb-0">Pending: <strong>{{ $attendancePending ?? 0 }}</strong></p>
                    <p class="mb-0">Approved: <strong>{{ $attendanceApproved ?? 0 }}</strong></p>
                    <p class="mb-0">Rejected: <strong>{{ $attendanceRejected ?? 0 }}</strong></p>
                </div>
            </div>
        </div>

        <div class="mt-4">
            <h5>Recent Attendance</h5>
            @if(isset($recentAttendance) && count($recentAttendance) > 0)
                <table class="table table-sm">
                    <thead>
                        <tr><th>Date</th><th>In</th><th>Out</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach($recentAttendance as $a)
                        <tr>
                            <td>{{ $a->attendance_date }}</td>
                            <td>{{ $a->morning_time_in ?? '-' }}</td>
                            <td>{{ $a->morning_time_out ?? '-' }}</td>
                            <td>{{ $a->status }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p>No recent attendance records.</p>
            @endif

            <div class="mt-3">
                <h5>Supervisor</h5>
                @if(isset($supervisor) && $supervisor)
                    <p class="mb-0">{{ $supervisor->name }} — {{ $supervisor->email }}</p>
                    <p class="mb-0">Dept: {{ $supervisor->department ?? '—' }} Phone: {{ $supervisor->phone ?? '—' }}</p>
                @else
                    <p>No supervisor assigned.</p>
                @endif
            </div>

            <div class="mt-4">
                <a href="{{ url('/') }}" class="btn btn-link">Back to Home</a>
            </div>
        </div>
    </div>
</body>
</html>
