<a @class(['nav-link', 'nav-link-active' => request()->routeIs('dashboard')]) href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Overview</a>
@if (auth()->user()->role === 'patient')
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('appointments.*')]) href="{{ route('appointments.index') }}" @if (request()->routeIs('appointments.*')) aria-current="page" @endif>Appointments</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('patient.rfid.*')]) href="{{ route('patient.rfid.index') }}" @if (request()->routeIs('patient.rfid.*')) aria-current="page" @endif>My RFID card</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('patient.records')]) href="{{ route('patient.records') }}" @if (request()->routeIs('patient.records')) aria-current="page" @endif>Dental records</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('patient.billing')]) href="{{ route('patient.billing') }}" @if (request()->routeIs('patient.billing')) aria-current="page" @endif>My billing</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('patient.notifications')]) href="{{ route('patient.notifications') }}" @if (request()->routeIs('patient.notifications')) aria-current="page" @endif>Notifications</a>
@else
    @if (auth()->user()->hasRole('admin', 'staff'))
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.patients.*')]) href="{{ route('clinic.patients.index') }}" @if (request()->routeIs('clinic.patients.*')) aria-current="page" @endif>Patients</a>
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.services.*')]) href="{{ route('clinic.services.index') }}" @if (request()->routeIs('clinic.services.*')) aria-current="page" @endif>Services</a>
    @endif
    @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.availability.*')]) href="{{ route('clinic.availability.index') }}" @if (request()->routeIs('clinic.availability.*')) aria-current="page" @endif>Appointment availability</a>
    @endif
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('appointments.*', 'clinic.appointments.*')]) href="{{ route('appointments.index') }}" @if (request()->routeIs('appointments.*', 'clinic.appointments.*')) aria-current="page" @endif>Appointments</a>
    @if (auth()->user()->hasRole('admin', 'staff'))
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.rfid.index')]) href="{{ route('clinic.rfid.index') }}" @if (request()->routeIs('clinic.rfid.index')) aria-current="page" @endif>RFID cards</a>
    @endif
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.rfid.scan*')]) href="{{ route('clinic.rfid.scan') }}" @if (request()->routeIs('clinic.rfid.scan*')) aria-current="page" @endif>RFID check-in</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.queue.*')]) href="{{ route('clinic.queue.index') }}" @if (request()->routeIs('clinic.queue.*')) aria-current="page" @endif>Patient queue</a>
    @if (auth()->user()->hasRole('admin', 'staff'))
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.appointment-records')]) href="{{ route('clinic.appointment-records') }}" @if (request()->routeIs('clinic.appointment-records')) aria-current="page" @endif>Appointment Records</a>
        <a @class(['nav-link', 'nav-link-active' => request()->routeIs('clinic.billing.*', 'clinic.payments.*')]) href="{{ route('clinic.billing.index') }}" @if (request()->routeIs('clinic.billing.*', 'clinic.payments.*')) aria-current="page" @endif>Billing & payments</a>
    @endif
@endif
@if (auth()->user()->role === 'admin')
    <div class="my-3 flex items-center gap-3 px-3" aria-hidden="true">
        <span class="h-px flex-1 bg-slate-200"></span>
        <span class="text-[0.65rem] font-semibold uppercase tracking-widest text-slate-400">Administration</span>
        <span class="h-px flex-1 bg-slate-200"></span>
    </div>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.staff.*')]) href="{{ route('admin.staff.index') }}" @if (request()->routeIs('admin.staff.*')) aria-current="page" @endif>Staff & doctor accounts</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.reports')]) href="{{ route('admin.reports') }}" @if (request()->routeIs('admin.reports')) aria-current="page" @endif>Reports</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.audit-logs')]) href="{{ route('admin.audit-logs') }}" @if (request()->routeIs('admin.audit-logs')) aria-current="page" @endif>Audit logs</a>
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.sms-logs')]) href="{{ route('admin.sms-logs') }}" @if (request()->routeIs('admin.sms-logs')) aria-current="page" @endif>SMS logs</a>
@elseif (auth()->user()->role === 'doctor')
    <a @class(['nav-link', 'nav-link-active' => request()->routeIs('admin.reports')]) href="{{ route('admin.reports') }}" @if (request()->routeIs('admin.reports')) aria-current="page" @endif>Reports</a>
@endif
