<!DOCTYPE html>
<html lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="ACT 2A Group 5">
    <title>BioTern || Edit Attendance</title>
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('assets/images/favicon.ico') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" type="text/css" href="{{ asset('assets/css/theme.min.css') }}">
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
            <div class="page-header">
                <div class="page-header-left d-flex align-items-center">
                    <div class="page-header-title">
                        <h5 class="m-b-10">Edit Attendance</h5>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('attendances.index') }}">Attendance</a></li>
                        <li class="breadcrumb-item">Edit</li>
                    </ul>
                </div>
            </div>

            <div class="main-content">
                <div class="row">
                    <div class="col-lg-8 offset-lg-2">
                        <div class="card stretch stretch-full">
                            <div class="card-header d-flex align-items-center justify-content-between">
                                <h6 class="card-title">Attendance Details</h6>
                                <a href="{{ route('attendances.index') }}" class="btn btn-sm btn-secondary">
                                    <i class="feather-arrow-left me-2"></i>Back
                                </a>
                            </div>
                            <div class="card-body">
                                <!-- Student Info -->
                                <div class="mb-4 p-3 bg-light rounded">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <strong>Student Name:</strong>
                                            <p>{{ $attendance->student->name }}</p>
                                        </div>
                                        <div class="col-md-6">
                                            <strong>Date:</strong>
                                            <p>{{ $attendance->attendance_date->format('M d, Y') }}</p>
                                        </div>
                                    </div>
                                </div>

                                <form method="POST" action="{{ route('attendances.update', $attendance->id) }}">
                                    @csrf
                                    @method('PUT')

                                    <!-- Morning Times -->
                                    <div class="mb-4">
                                        <h6 class="card-title mb-3"><i class="feather-sunrise me-2"></i>Morning</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="morning_time_in" class="form-label">Time In</label>
                                                    <input type="time" class="form-control @error('morning_time_in') is-invalid @enderror" 
                                                           id="morning_time_in" name="morning_time_in" 
                                                           value="{{ $attendance->morning_time_in ? $attendance->morning_time_in->format('H:i') : '' }}">
                                                    @error('morning_time_in')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="morning_time_out" class="form-label">Time Out</label>
                                                    <input type="time" class="form-control @error('morning_time_out') is-invalid @enderror" 
                                                           id="morning_time_out" name="morning_time_out" 
                                                           value="{{ $attendance->morning_time_out ? $attendance->morning_time_out->format('H:i') : '' }}">
                                                    @error('morning_time_out')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Break Times -->
                                    <div class="mb-4">
                                        <h6 class="card-title mb-3"><i class="feather-coffee me-2"></i>Break</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="break_time_in" class="form-label">Time In</label>
                                                    <input type="time" class="form-control @error('break_time_in') is-invalid @enderror" 
                                                           id="break_time_in" name="break_time_in" 
                                                           value="{{ $attendance->break_time_in ? $attendance->break_time_in->format('H:i') : '' }}">
                                                    @error('break_time_in')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="break_time_out" class="form-label">Time Out</label>
                                                    <input type="time" class="form-control @error('break_time_out') is-invalid @enderror" 
                                                           id="break_time_out" name="break_time_out" 
                                                           value="{{ $attendance->break_time_out ? $attendance->break_time_out->format('H:i') : '' }}">
                                                    @error('break_time_out')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Afternoon Times -->
                                    <div class="mb-4">
                                        <h6 class="card-title mb-3"><i class="feather-sunset me-2"></i>Afternoon</h6>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="afternoon_time_in" class="form-label">Time In</label>
                                                    <input type="time" class="form-control @error('afternoon_time_in') is-invalid @enderror" 
                                                           id="afternoon_time_in" name="afternoon_time_in" 
                                                           value="{{ $attendance->afternoon_time_in ? $attendance->afternoon_time_in->format('H:i') : '' }}">
                                                    @error('afternoon_time_in')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="afternoon_time_out" class="form-label">Time Out</label>
                                                    <input type="time" class="form-control @error('afternoon_time_out') is-invalid @enderror" 
                                                           id="afternoon_time_out" name="afternoon_time_out" 
                                                           value="{{ $attendance->afternoon_time_out ? $attendance->afternoon_time_out->format('H:i') : '' }}">
                                                    @error('afternoon_time_out')
                                                        <div class="invalid-feedback">{{ $message }}</div>
                                                    @enderror
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Remarks -->
                                    <div class="mb-4">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea class="form-control @error('remarks') is-invalid @enderror" 
                                                  id="remarks" name="remarks" rows="3">{{ $attendance->remarks }}</textarea>
                                        @error('remarks')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="feather-save me-2"></i>Save Changes
                                        </button>
                                        <a href="{{ route('attendances.index') }}" class="btn btn-secondary">
                                            <i class="feather-x me-2"></i>Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
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