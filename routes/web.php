<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicNotificationController;
use App\Http\Controllers\ClinicWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('verification.verify');
Route::post('/email/resend-code', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('verification.resend');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storePatient'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->name('appointments.availability');
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/{appointment}/action/{action}', [AppointmentController::class, 'transition'])
        ->whereIn('action', ['approve', 'reject', 'cancel', 'check-in', 'call', 'consult', 'complete', 'no-show'])
        ->name('appointments.transition');
    Route::patch('/clinic/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware('role:admin,staff')
        ->name('clinic.appointments.reschedule');
    Route::get('/receipts/{receipt}', [PaymentController::class, 'showReceipt'])->name('receipts.show');

    Route::middleware('role:patient')->group(function (): void {
        Route::get('/my/dental-records', [ClinicWorkflowController::class, 'records'])->name('patient.records');
        Route::get('/my/billing', [ClinicWorkflowController::class, 'patientBilling'])->name('patient.billing');
        Route::get('/my/notifications', [ClinicWorkflowController::class, 'patientNotifications'])->name('patient.notifications');
    });

    Route::prefix('clinic')->name('clinic.')->middleware('role:admin,staff')->group(function (): void {
        Route::get('/notifications', [ClinicNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [ClinicNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/open', [ClinicNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/patients/create', [PatientController::class, 'create'])->name('patients.create');
        Route::post('/patients', [PatientController::class, 'store'])->name('patients.store');
        Route::get('/patients', [PatientController::class, 'index'])->name('patients.index');
        Route::get('/patients/{patient}', [PatientController::class, 'show'])->name('patients.show');
        Route::put('/patients/{patient}', [PatientController::class, 'update'])->name('patients.update');
        Route::delete('/patients/{patient}', [PatientController::class, 'destroy'])->name('patients.destroy');
        Route::put('/patients/{patient}/password', [PatientController::class, 'resetPassword'])
            ->middleware('role:admin')
            ->name('patients.password');
        Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
        Route::get('/queue', [ClinicWorkflowController::class, 'queue'])->name('queue.index');
        Route::get('/billing', [ClinicWorkflowController::class, 'billing'])->name('billing.index');
        Route::post('/bills/{bill}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::get('/rfid', [RfidController::class, 'index'])->name('rfid.index');
        Route::post('/patients/{patient}/rfid', [RfidController::class, 'assign'])->name('rfid.assign');
        Route::get('/rfid/check-in', [RfidController::class, 'scanPage'])->name('rfid.scan');
        Route::post('/rfid/check-in', [RfidController::class, 'scan'])->name('rfid.scan.submit');
        Route::post('/rfid/check-in/{appointment}', [RfidController::class, 'checkIn'])->name('rfid.check-in');
        Route::get('/appointments/{appointment}/record', [ClinicWorkflowController::class, 'createRecord'])
            ->middleware('role:admin')
            ->name('records.create');
        Route::post('/appointments/{appointment}/record', [ClinicWorkflowController::class, 'storeRecord'])
            ->middleware('role:admin')
            ->name('records.store');
        Route::get('/appointments/{appointment}/bill', [ClinicWorkflowController::class, 'createBill'])->name('bills.create');
        Route::post('/appointments/{appointment}/bill', [ClinicWorkflowController::class, 'storeBill'])->name('bills.store');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::put('/staff/{staff}/password', [StaffController::class, 'resetPassword'])->name('staff.password');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::get('/reports', [ClinicWorkflowController::class, 'reports'])->name('reports');
        Route::get('/audit-logs', [ClinicWorkflowController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/sms-logs', [ClinicWorkflowController::class, 'smsLogs'])->name('sms-logs');
    });
});
