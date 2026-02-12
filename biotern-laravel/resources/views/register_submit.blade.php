@php
/*
 Simple blade view for registration endpoint. The processing logic
 has been moved to `RegisterSubmitController::handle`.
*/
@endphp

@if(request()->query('registered'))
    <p>Registered: {{ request()->query('registered') }}</p>
@else
    <p>Use the form below to POST a minimal registration (demo only).</p>

    <form method="POST" action="/register_submit">
        {{ csrf_field() }}
        <input type="hidden" name="role" value="student">
        <label>First name: <input name="first_name" value="Smoke"></label>
        <label>Last name: <input name="last_name" value="Test"></label>
        <label>Email: <input name="email" value="smoke+test@example.com"></label>
        <label>Username: <input name="username" value="smoke_test_user"></label>
        <label>Password: <input name="password" value="Secret123!"></label>
        <input type="hidden" name="student_id" value="SMK001">
        <input type="hidden" name="course_id" value="1">
        <input type="hidden" name="section" value="1">
        <input type="hidden" name="address" value="Smoke Test Address">
        <button type="submit">Submit (demo)</button>
    </form>

@endif
