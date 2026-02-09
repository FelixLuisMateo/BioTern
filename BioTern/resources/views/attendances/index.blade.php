<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Attendance DTR</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
    <style>
        .time-cell {
            font-family: 'Courier New', monospace;
            text-align: center;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .total-hours {
            font-weight: 600;
            background-color: #f0f0f0;
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
            <div class="navbar-content">
                <ul class="nxl-navbar">
                    <li class="nxl-item nxl-caption">
                        <label>Navigation</label>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-airplay"></i></span>
                            <span class="nxl-mtext">Dashboards</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('index') }}">CRM</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="analytics.html">Analytics</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-users"></i></span>
                            <span class="nxl-mtext">Students</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('students.index') }}">Students</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-view.html">Students View</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="students-create.html">Students Create</a></li>
                        </ul>
                    </li>
                    <li class="nxl-item nxl-hasmenu">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-clock"></i></span>
                            <span class="nxl-mtext">Attendance</span><span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('attendances.index') }}">Attendance DTR</a></li>
                            <li class="nxl-item"><a class="nxl-link" href="{{ route('attendances.create') }}">Manual Entry</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!--! Header !-->
    <header class="nxl-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-4">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle">
                    <a href="javascript:void(0);" id="menu-mini-button">
                        <i class="feather-align-left"></i>
                    </a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                        <i class="feather-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="header-right ms-auto">
                <div class="d-flex align-items-center">
                    <div class="dropdown nxl-h-item">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar me-0">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                            <div class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar">
                                    <div>
                                        <h6 class="text-dark mb-0">{{ auth()->user()->name }}</h6>
                                        <span class="fs-12 fw-medium text-muted">{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-user"></i>
                                <span>Profile Details</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item">
                                    <i class="feather-log-out"></i>
                                    <span>Logout</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!--! Main Content !-->
    <main class="nxl-container">
        <div class="nxl-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Attendance Daily Time Record</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('index') }}">Home</a></li>
                        <li class="breadcrumb-item">Attendance DTR</li>
                    </ul>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-filter"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <form method="GET" action="{{ route('attendances.index') }}" class="px-3 py-2">
                                        <div class="mb-3">
                                            <label for="status" class="form-label">Status</label>
                                            <select name="status" id="status" class="form-select form-select-sm">
                                                <option value="">All Status</option>
                                                <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                                                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="student_id" class="form-label">Student</label>
                                            <select name="student_id" id="student_id" class="form-select form-select-sm">
                                                <option value="">All Students</option>
                                                @foreach($students as $student)
                                                    <option value="{{ $student->id }}" {{ request('student_id') == $student->id ? 'selected' : '' }}>
                                                        {{ $student->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                                    </form>
                                </div>
                            </div>
                            <div class="dropdown">
                                <a class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" data-bs-auto-close="outside">
                                    <i class="feather-download"></i>
                                </a>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a href="{{ route('attendances.export', ['start_date' => request('start_date'), 'end_date' => request('end_date')]) }}" class="dropdown-item">
                                        <i class="bi bi-filetype-csv me-3"></i>
                                        <span>Export CSV</span>
                                    </a>
                                    <a href="javascript:void(0);" class="dropdown-item" onclick="window.print()">
                                        <i class="bi bi-printer me-3"></i>
                                        <span>Print</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            @if ($message = Session::get('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <strong>Success!</strong> {{ $message }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            @if ($message = Session::get('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <strong>Error!</strong> {{ $message }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            @endif

            <!-- Main Content -->
            <div class="main-content">
                <div class="row">
                    <div class="col-lg-12">
                        <div class="card stretch stretch-full">
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="attendanceTable">
                                        <thead>
                                            <tr>
                                                <th class="wd-30">
                                                    <div class="custom-control custom-checkbox ms-1">
                                                        <input type="checkbox" class="custom-control-input" id="checkAllAttendance">
                                                        <label class="custom-control-label" for="checkAllAttendance"></label>
                                                    </div>
                                                </th>
                                                <th>Date</th>
                                                <th>Student Name</th>
                                                <th>Course</th>
                                                <th class="text-center">Morning<br><small>In - Out</small></th>
                                                <th class="text-center">Break<br><small>In - Out</small></th>
                                                <th class="text-center">Afternoon<br><small>In - Out</small></th>
                                                <th class="text-center">Total Hours</th>
                                                <th>Status</th>
                                                <th class="text-end">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($attendances as $attendance)
                                                <tr class="single-item {{ 'status-' . $attendance->status }}">
                                                    <td>
                                                        <div class="item-checkbox ms-1">
                                                            <div class="custom-control custom-checkbox">
                                                                <input type="checkbox" class="custom-control-input checkbox" id="checkBox_{{ $attendance->id }}" value="{{ $attendance->id }}">
                                                                <label class="custom-control-label" for="checkBox_{{ $attendance->id }}"></label>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong>{{ $attendance->attendance_date->format('M d, Y') }}</strong>
                                                    </td>
                                                    <td>
                                                        <a href="{{ route('attendances.student', $attendance->student_id) }}" class="hstack gap-3">
                                                            <div class="avatar-image avatar-md">
                                                                <img src="{{ asset('assets/images/avatar/1.png') }}" alt="" class="img-fluid">
                                                            </div>
                                                            <div>
                                                                <span class="text-truncate-1-line">{{ $attendance->student->name }}</span>
                                                                <small class="text-muted">ID: {{ $attendance->student->id }}</small>
                                                            </div>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        {{ $attendance->student->course ?? 'N/A' }}
                                                    </td>
                                                    <td class="time-cell">
                                                        {{ $attendance->morning_time_in ? \Carbon\Carbon::parse($attendance->morning_time_in)->format('H:i') : '-' }}
                                                        -
                                                        {{ $attendance->morning_time_out ? \Carbon\Carbon::parse($attendance->morning_time_out)->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="time-cell">
                                                        {{ $attendance->break_time_in ? \Carbon\Carbon::parse($attendance->break_time_in)->format('H:i') : '-' }}
                                                        -
                                                        {{ $attendance->break_time_out ? \Carbon\Carbon::parse($attendance->break_time_out)->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="time-cell">
                                                        {{ $attendance->afternoon_time_in ? \Carbon\Carbon::parse($attendance->afternoon_time_in)->format('H:i') : '-' }}
                                                        -
                                                        {{ $attendance->afternoon_time_out ? \Carbon\Carbon::parse($attendance->afternoon_time_out)->format('H:i') : '-' }}
                                                    </td>
                                                    <td class="text-center total-hours">
                                                        {{ $attendance->calculateTotalHours() }}h
                                                    </td>
                                                    <td>
                                                        <span class="badge {{ $attendance->getStatusBadgeClass() }}">
                                                            {{ ucfirst($attendance->status) }}
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="hstack gap-2 justify-content-end">
                                                            <a href="{{ route('attendances.edit', $attendance->id) }}" class="avatar-text avatar-md" title="Edit">
                                                                <i class="feather feather-edit"></i>
                                                            </a>
                                                            <div class="dropdown">
                                                                <a href="javascript:void(0)" class="avatar-text avatar-md" data-bs-toggle="dropdown" data-bs-offset="0,21">
                                                                    <i class="feather feather-more-horizontal"></i>
                                                                </a>
                                                                <ul class="dropdown-menu">
                                                                    @if($attendance->status === 'pending')
                                                                        <li>
                                                                            <form method="POST" action="{{ route('attendances.approve', $attendance->id) }}" class="d-inline">
                                                                                @csrf
                                                                                <button type="submit" class="dropdown-item" onclick="return confirm('Approve this attendance?')">
                                                                                    <i class="feather feather-check me-3 text-success"></i>
                                                                                    <span>Approve</span>
                                                                                </button>
                                                                            </form>
                                                                        </li>
                                                                        <li>
                                                                            <a href="javascript:void(0)" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#rejectModal{{ $attendance->id }}">
                                                                                <i class="feather feather-x me-3 text-danger"></i>
                                                                                <span>Reject</span>
                                                                            </a>
                                                                        </li>
                                                                    @endif
                                                                    <li class="dropdown-divider"></li>
                                                                    <li>
                                                                        <a href="{{ route('attendances.edit', $attendance->id) }}" class="dropdown-item">
                                                                            <i class="feather feather-edit-3 me-3"></i>
                                                                            <span>Edit Times</span>
                                                                        </a>
                                                                    </li>
                                                                    <li>
                                                                        <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item">
                                                                            <i class="feather feather-printer me-3"></i>
                                                                            <span>Print</span>
                                                                        </a>
                                                                    </li>
                                                                </ul>
                                                            </div>
                                                        </div>
                                                    </td>
                                                </tr>

                                                <!-- Reject Modal -->
                                                <div class="modal fade" id="rejectModal{{ $attendance->id }}" tabindex="-1" aria-labelledby="rejectLabel{{ $attendance->id }}" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="rejectLabel{{ $attendance->id }}">Reject Attendance</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <form method="POST" action="{{ route('attendances.reject', $attendance->id) }}">
                                                                @csrf
                                                                <div class="modal-body">
                                                                    <div class="mb-3">
                                                                        <label for="remarks{{ $attendance->id }}" class="form-label">Remarks (Required)</label>
                                                                        <textarea name="remarks" id="remarks{{ $attendance->id }}" class="form-control" rows="4" required></textarea>
                                                                    </div>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <button type="submit" class="btn btn-danger">Reject</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            @empty
                                                <tr>
                                                    <td colspan="10" class="text-center py-4">
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
            <p><span>By: <a target="_blank" href="">ACT 2A</a></span> • <span>Distributed by: <a target="_blank" href="">Group 5</a></span></p>
        </footer>
    </main>

    <!-- Scripts -->
    <script src="{{ asset('assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('assets/js/common-init.min.js') }}"></script>
    <script>
        // Initialize DataTable
        document.addEventListener('DOMContentLoaded', function() {
            $('#attendanceTable').DataTable({
                "paging": false,
                "info": false,
                "searching": false,
                "ordering": true
            });
        });

        // Check all functionality
        document.getElementById('checkAllAttendance').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    </script>
</body>

</html>