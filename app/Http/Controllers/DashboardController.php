<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        if ($user->role === 'patient') {
            $patient = $user->patient()->firstOrFail();

            return view('dashboard.patient', [
                'nextAppointment' => $patient->appointments()
                    ->with('service')
                    ->where('starts_at', '>=', now())
                    ->whereNotIn('status', ['cancelled', 'rejected', 'completed', 'no_show', 'billed'])
                    ->orderBy('starts_at')
                    ->first(),
                'recentRecords' => $patient->dentalRecords()->latest()->limit(5)->get(),
                'unpaidBills' => $patient->appointments()->with('bill')
                    ->whereHas('bill', fn ($query) => $query->whereIn('payment_status', ['unpaid', 'partially_paid']))
                    ->get()->pluck('bill')->filter(),
            ]);
        }

        $today = today();
        $isAdmin = $user->role === 'admin';
        $appointments = Appointment::query()
            ->with(['patient.user', 'service', 'queueEntry'])
            ->whereDate('starts_at', $today)
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $metrics = $isAdmin
            ? [
                ['Patients', number_format(Patient::count()), 'Registered patient accounts'],
                ["Today's appointments", number_format(Appointment::whereDate('starts_at', $today)->count()), 'Online and walk-in'],
                ['Pending approval', number_format(Appointment::where('status', 'pending')->count()), 'Online requests'],
                ['Patients in queue', number_format(QueueEntry::whereDate('queue_date', $today)->whereIn('status', ['waiting', 'called'])->count()), 'Waiting or called today'],
                ['Completed today', number_format(Appointment::whereDate('starts_at', $today)->where('status', 'completed')->count()), 'Appointments completed'],
                ["Today's revenue", '₱'.number_format((float) Payment::whereDate('created_at', $today)->sum('applied_amount'), 2), 'Payments applied today'],
                ['Unpaid bills', number_format(Bill::whereIn('payment_status', ['unpaid', 'partially_paid'])->count()), 'Including partial balances'],
                ['Active staff', number_format(User::where('role', 'staff')->where('is_active', true)->count()), 'Clinic team members'],
            ]
            : [
                ["Today's appointments", number_format(Appointment::whereDate('starts_at', $today)->count()), 'All scheduled visits'],
                ['Waiting queue', number_format(QueueEntry::whereDate('queue_date', $today)->whereIn('status', ['waiting', 'called'])->count()), 'Waiting or called'],
                ["Today's walk-ins", number_format(Appointment::where('type', 'walk_in')->whereDate('starts_at', $today)->count()), 'Registered at front desk'],
                ['Checked in', number_format(Appointment::whereDate('starts_at', $today)->whereIn('status', ['checked_in', 'waiting', 'in_consultation'])->count()), 'Checked-in or in consultation'],
                ['Pending billing', number_format(Appointment::where('status', 'completed')->whereDoesntHave('bill')->count()), 'Completed visits without a bill'],
                ["Today's payments", number_format(Payment::whereDate('created_at', $today)->count()), 'Payments processed'],
            ];

        return view('dashboard.clinic', [
            'metrics' => $metrics,
            'isAdmin' => $isAdmin,
            'appointments' => $appointments,
            'dailyAppointments' => DB::table('appointments')
                ->selectRaw('DATE(starts_at) as appointment_day, COUNT(*) as total')
                ->whereBetween('starts_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
                ->groupBy('appointment_day')
                ->orderBy('appointment_day')
                ->get(),
        ]);
    }
}
