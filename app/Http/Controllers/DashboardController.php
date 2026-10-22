<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Bill;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\QueueEntry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'doctor') {
            return redirect()->route('appointments.index');
        }

        if ($user->role === 'patient') {
            $patient = $user->patient()->firstOrFail();

            return view('dashboard.patient', [
                'nextAppointment' => $patient->appointments()
                    ->with(['service', 'services'])
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

        $today = today(config('clinic.timezone'));
        $isAdmin = $user->role === 'admin';
        $hasTestPaymentsToday = Payment::query()
            ->whereDate('created_at', $today)
            ->where('provider', 'paymongo')
            ->where('is_test', true)
            ->exists();
        $appointments = Appointment::query()
            ->with(['patient.user', 'service', 'services', 'queueEntry'])
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
                ["Today's revenue", '₱'.number_format((float) Payment::whereDate('created_at', $today)->sum('applied_amount'), 2), $hasTestPaymentsToday ? 'Includes PayMongo TEST MODE payments' : 'Payments applied today'],
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

        $statistics = [];
        if ($isAdmin) {
            $filters = $request->validate([
                'statistics_period' => ['nullable', 'in:week,month,year'],
            ]);
            $statistics = $this->dashboardStatistics($filters['statistics_period'] ?? 'week', $today);
        }

        return view('dashboard.clinic', [
            'metrics' => $metrics,
            'isAdmin' => $isAdmin,
            'appointments' => $appointments,
            'statistics' => $statistics,
        ]);
    }

    /**
     * @return array{
     *     period:string,
     *     rangeLabel:string,
     *     timeline:array<int, array{label:string, appointments:int, revenue:float}>,
     *     statuses:array<int, array{label:string, count:int, percentage:float}>,
     *     appointmentMaximum:int,
     *     revenueMaximum:float
     * }
     */
    private function dashboardStatistics(string $period, Carbon $today): array
    {
        $usesMonths = $period === 'year';
        $rangeStart = $usesMonths
            ? $today->copy()->startOfMonth()->subMonths(11)
            : $today->copy()->startOfDay()->subDays($period === 'week' ? 6 : 29);
        $rangeEnd = $today->copy()->endOfDay();
        $bucketKey = static fn (Carbon $date): string => $usesMonths
            ? $date->format('Y-m')
            : $date->toDateString();
        $timeline = [];
        $cursor = $rangeStart->copy();

        while ($cursor->lessThanOrEqualTo($today)) {
            $timeline[$bucketKey($cursor)] = [
                'label' => $usesMonths ? $cursor->format('M Y') : $cursor->format('D j'),
                'appointments' => 0,
                'revenue' => 0.0,
            ];
            $usesMonths ? $cursor->addMonth() : $cursor->addDay();
        }

        $appointmentRows = Appointment::query()
            ->selectRaw('DATE(starts_at) as activity_date, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get();

        foreach ($appointmentRows as $row) {
            $key = $bucketKey(Carbon::parse($row->activity_date, config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['appointments'] = (int) $row->total;
            }
        }

        $revenueRows = DB::table('payments')
            ->selectRaw('DATE(created_at) as activity_date, SUM(applied_amount) as total')
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->groupBy('activity_date')
            ->get();

        foreach ($revenueRows as $row) {
            $key = $bucketKey(Carbon::parse($row->activity_date, config('clinic.timezone')));
            if (isset($timeline[$key])) {
                $timeline[$key]['revenue'] = (float) $row->total;
            }
        }

        $statusCounts = Appointment::query()
            ->selectRaw('status, COUNT(*) as total')
            ->whereBetween('starts_at', [$rangeStart, $rangeEnd])
            ->groupBy('status')
            ->orderBy('status')
            ->get();
        $appointmentTotal = max(1, (int) $statusCounts->sum('total'));
        $statuses = $statusCounts->map(fn (object $row): array => [
            'label' => str_replace('_', ' ', ucfirst($row->status)),
            'count' => (int) $row->total,
            'percentage' => (float) $row->total / $appointmentTotal * 100,
        ])->all();
        $timeline = array_values($timeline);

        return [
            'period' => $period,
            'rangeLabel' => match ($period) {
                'month' => 'Last 30 days',
                'year' => 'Last 12 months',
                default => 'Last 7 days',
            },
            'timeline' => $timeline,
            'statuses' => $statuses,
            'appointmentMaximum' => max(1, ...array_column($timeline, 'appointments')),
            'revenueMaximum' => max(1.0, ...array_column($timeline, 'revenue')),
        ];
    }
}
