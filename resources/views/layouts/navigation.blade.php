<a class="nav-link" href="{{ route('dashboard') }}">Overview</a>
<a class="nav-link" href="{{ route('appointments.index') }}">Appointments</a>
@if (auth()->user()->hasRole('admin', 'staff'))
    <a class="nav-link" href="{{ route('clinic.patients.index') }}">Patients</a>
    <a class="nav-link" href="{{ route('clinic.queue.index') }}">Patient queue</a>
    <a class="nav-link" href="{{ route('clinic.rfid.index') }}">RFID cards</a>
    <a class="nav-link" href="{{ route('clinic.rfid.scan') }}">RFID check-in</a>
    <a class="nav-link" href="{{ route('clinic.services.index') }}">Services</a>
    <a class="nav-link" href="{{ route('clinic.billing.index') }}">Billing & payments</a>
@else
    <a class="nav-link" href="{{ route('patient.records') }}">Dental records</a>
    <a class="nav-link" href="{{ route('patient.billing') }}">My billing</a>
    <a class="nav-link" href="{{ route('patient.notifications') }}">Notifications</a>
@endif
@if (auth()->user()->role === 'admin')
    <div class="my-3 border-t border-slate-100"></div>
    <a class="nav-link" href="{{ route('admin.staff.index') }}">Staff accounts</a>
    <a class="nav-link" href="{{ route('admin.reports') }}">Reports</a>
    <a class="nav-link" href="{{ route('admin.audit-logs') }}">Audit logs</a>
    <a class="nav-link" href="{{ route('admin.sms-logs') }}">SMS logs</a>
@endif
