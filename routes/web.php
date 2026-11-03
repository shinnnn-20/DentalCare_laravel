<?php

use App\Http\Controllers\AdditionalServiceController;
use App\Http\Controllers\AppointmentAvailabilityController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClinicNotificationController;
use App\Http\Controllers\ClinicWorkflowController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PayMongoBillCheckoutController;
use App\Http\Controllers\PayMongoTestCheckoutController;
use App\Http\Controllers\PayMongoTestWebhookController;
use App\Http\Controllers\RfidController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StaffController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing');
})->name('home');

Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
Route::post('/email/verify', [EmailVerificationController::class, 'verify'])
    ->middleware('throttle:5,1')
    ->name('verification.verify');
Route::post('/email/resend-code', [EmailVerificationController::class, 'resend'])
    ->middleware('throttle:3,1')
    ->name('verification.resend');

Route::post('/webhooks/paymongo/test', PayMongoTestWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('paymongo.test.webhook');

Route::middleware('throttle:10,1')->group(function (): void {
    Route::get('/paymongo/test-checkout/result/success', [PayMongoTestCheckoutController::class, 'success'])
        ->name('paymongo.test.success');
    Route::get('/paymongo/test-checkout/result/cancelled', [PayMongoTestCheckoutController::class, 'cancelled'])
        ->name('paymongo.test.cancelled');
    Route::get('/paymongo/bill-checkout/result/{result}', [PayMongoBillCheckoutController::class, 'result'])
        ->whereIn('result', ['success', 'cancelled'])
        ->name('paymongo.bill.result');
});

Route::middleware(['auth', 'active', 'role:admin'])
    ->prefix('paymongo/test-checkout')
    ->name('paymongo.test.')
    ->group(function (): void {
        Route::get('/', [PayMongoTestCheckoutController::class, 'index'])->name('index');
        Route::post('/', [PayMongoTestCheckoutController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('store');
    });

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'storePatient'])->middleware('throttle:5,1')->name('register.store');
});

Route::get('/appointments/start', [AuthController::class, 'appointmentBooking'])->name('appointments.start');

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/details', [DashboardController::class, 'details'])
        ->middleware('role:admin,staff,doctor')
        ->name('dashboard.details');
    Route::get('/appointments/availability', [AppointmentController::class, 'availability'])->name('appointments.availability');
    Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
    Route::post('/appointments/{appointment}/action/{action}', [AppointmentController::class, 'transition'])
        ->whereIn('action', ['approve', 'reject', 'cancel', 'call', 'consult', 'no-show'])
        ->name('appointments.transition');
    Route::patch('/clinic/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule'])
        ->middleware('role:admin,staff,doctor')
        ->name('clinic.appointments.reschedule');
    Route::get('/receipts/{receipt}', [PaymentController::class, 'showReceipt'])->name('receipts.show');

    Route::middleware('role:patient')->group(function (): void {
        Route::get('/my/rfid', [RfidController::class, 'patientCard'])->name('patient.rfid.index');
        Route::post('/my/rfid', [RfidController::class, 'registerPatientCard'])->name('patient.rfid.store');
        Route::get('/my/dental-records', [ClinicWorkflowController::class, 'records'])->name('patient.records');
        Route::get('/my/billing', [ClinicWorkflowController::class, 'patientBilling'])->name('patient.billing');
        Route::get('/my/billing/{bill}/receipts/{receipt}/preview', [PaymentController::class, 'showPatientReceiptPreview'])
            ->name('patient.billing.receipts.preview');
        Route::get('/my/notifications', [ClinicWorkflowController::class, 'patientNotifications'])->name('patient.notifications');
    });

    Route::prefix('clinic')->name('clinic.')->middleware('role:admin,staff')->group(function (): void {
        Route::get('/notifications', [ClinicNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [ClinicNotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
        Route::post('/notifications/{notification}/open', [ClinicNotificationController::class, 'open'])->name('notifications.open');
        Route::get('/appointment-records', [AppointmentController::class, 'index'])->name('appointment-records');
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
        Route::get('/billing', [ClinicWorkflowController::class, 'billing'])->name('billing.index');
        Route::get('/bills/{bill}/additional-services', [AdditionalServiceController::class, 'create'])
            ->name('bills.additional-services.create');
        Route::post('/bills/{bill}/additional-services', [AdditionalServiceController::class, 'store'])
            ->name('bills.additional-services.store');
        Route::post('/bills/{bill}/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('/bills/{bill}/paymongo-checkout', [PayMongoBillCheckoutController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('billing.paymongo.store');
        Route::post('/paymongo-bill-checkouts/{checkout}/reconcile', [PayMongoBillCheckoutController::class, 'reconcile'])
            ->middleware('throttle:5,1')
            ->name('billing.paymongo.reconcile');
        Route::get('/rfid', [RfidController::class, 'index'])->name('rfid.index');
        Route::post('/patients/{patient}/rfid', [RfidController::class, 'assign'])->name('rfid.assign');
        Route::get('/appointments/{appointment}/bill', [ClinicWorkflowController::class, 'createBill'])->name('bills.create');
        Route::post('/appointments/{appointment}/bill', [ClinicWorkflowController::class, 'storeBill'])->name('bills.store');
    });

    Route::prefix('clinic')->name('clinic.')->middleware('role:admin,staff,doctor')->group(function (): void {
        Route::get('/queue', [ClinicWorkflowController::class, 'queue'])->name('queue.index');
        Route::get('/appointments/{appointment}/record', [ClinicWorkflowController::class, 'createRecord'])
            ->name('records.create');
        Route::post('/appointments/{appointment}/record', [ClinicWorkflowController::class, 'storeRecord'])
            ->name('records.store');
        Route::get('/rfid/check-in', [RfidController::class, 'scanPage'])->name('rfid.scan');
        Route::post('/rfid/check-in', [RfidController::class, 'scan'])->name('rfid.scan.submit');
        Route::post('/queue/{appointment}/check-in', [AppointmentController::class, 'checkInFromQueue'])
            ->name('queue.check-in');
        Route::get('/appointment-availability', [AppointmentAvailabilityController::class, 'index'])
            ->name('availability.index');
        Route::post('/appointment-availability', [AppointmentAvailabilityController::class, 'store'])
            ->name('availability.store');
        Route::patch('/appointment-availability/{availabilityBlock}', [AppointmentAvailabilityController::class, 'update'])
            ->name('availability.update');
        Route::delete('/appointment-availability/{availabilityBlock}', [AppointmentAvailabilityController::class, 'destroy'])
            ->middleware('role:admin,staff')
            ->name('availability.destroy');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin,doctor')->group(function (): void {
        Route::get('/reports', [ClinicWorkflowController::class, 'reports'])->name('reports');
        Route::get('/reports/details', [ClinicWorkflowController::class, 'reportDetails'])->name('reports.details');
    });

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function (): void {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
        Route::patch('/staff/{staff}/branch', [StaffController::class, 'updateBranch'])->name('staff.branch.update');
        Route::patch('/staff/{staff}/toggle', [StaffController::class, 'toggle'])->name('staff.toggle');
        Route::put('/staff/{staff}/password', [StaffController::class, 'resetPassword'])->name('staff.password');
        Route::post('/services', [ServiceController::class, 'store'])->name('services.store');
        Route::put('/services/{service}', [ServiceController::class, 'update'])->name('services.update');
        Route::get('/audit-logs', [ClinicWorkflowController::class, 'auditLogs'])->name('audit-logs');
        Route::get('/sms-logs', [ClinicWorkflowController::class, 'smsLogs'])->name('sms-logs');
    });
});
