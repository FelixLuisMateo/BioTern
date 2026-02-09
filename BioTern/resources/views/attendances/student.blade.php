<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Student Attendance</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
    <style>
        .student-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
        }
        .time-cell {
            font-family: 'Courier New', monospace;
            text-align: center;
        }
    </style>
</head>

<body>
    <!--! Navigation !-->
    <nav class="nxl-navigation">
        <div class="navbar-wrapper">
            <div class="m-header">
                <a href="{{ route('index') }}" class="b-brand">
                    <img src="{{ asset('assets/images/logo-full.png') }}" alt="" class="logo logo-lg">
                    <img src="{{ asset('assets/images/logo-abbr.png') }}" alt="" class="logo logo-sm">
                </a>
            </div>
        </div>
    </nav>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Student Header -->
            <div class="student-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <img src="{{ asset('assets/images/avatar/1.png') }}" alt="" class="img-fluid rounded-circle" style="width: 120px; height: 120px;">
                        </div>
                        <div class="col-md-10">
                            <h3 class="mb-1">{{ $student->name }}</h3>
                            <p class="mb-1">Student ID: <strong>{{ $student->id }}</strong></p>
                            <p class="mb-0">Course: <strong>{{ $student->course ?? 'N/A' }}</strong></p>
                        </div>
                        <div class="col-12 mt-3">
                            <a href="{{ route('attendances.index') }}" class="btn btn-light btn-sm">
                                <i class="feather-arrow-left me-2"></i>Back to Attendance
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-header">
                                <h6 class="card-title">Attendance Records</h6>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th class="text-center">Morning<br><small>In - Out</small></th>
                                                <th class="text-center">Break<br><small>In - Out</small></th>
                                                <th class="text-center">Afternoon<br><small>In - Out</small></th>
                                                <th class="text-center">Total Hours</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($attendances as $record)
                                                <tr>
                                                    <td><strong>{{ $record->attendance_date->format('M d, Y') }}</strong></td>
                                                    <td class="time-cell">
                                                        {{ $record->morning_time_in ? $record->morning_time_in->format('H:i') : '-' }} - {{ $record->morning_time_out ? $record->morning_time_out->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="time-cell">
                                                        {{ $record->break_time_in ? $record->break_time_in->format('H:i') : '-' }} - {{ $record->break_time_out ? $record->break_time_out->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="time-cell">
                                                        {{ $record->afternoon_time_in ? $record->afternoon_time_in->format('H:i') : '-' }} - {{ $record->afternoon_time_out ? $record->afternoon_time_out->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="text-center"><strong>{{ $record->calculateTotalHours() }}h</strong></td>
                                                    <td>
                                                        <span class="badge {{ $record->getStatusBadgeClass() }}">
                                                            {{ ucfirst($record->status) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('attendances.edit', $record->id) }}" class="btn btn-sm btn-outline-primary">
                                                            <i class="feather-edit me-1"></i>Edit
                                                        </a>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <p class="text-muted">No attendance records found</p>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-3">
                            {{ $attendances->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <p class="fs-11 text-muted fw-medium text-uppercase mb-0 copyright">
                <span>Copyright ©</span>
                <script>
                    document.write(new Date().getFullYear());
                </script>
            </p>
        </footer>
    </main>

    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
</body>

</html>