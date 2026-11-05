<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Service;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $filters = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);
        $selectedBranchId = $user->hasRole('doctor')
            ? (int) $user->branch_id
            : ($filters['branch_id'] ?? null);

        return view('services.index', [
            'branches' => Branch::query()->orderBy('name')->get(),
            'selectedBranchId' => $selectedBranchId,
            'services' => Service::query()
                ->with('branch')
                ->when($selectedBranchId !== null, fn ($query) => $query->forBranch($selectedBranchId))
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin', 'doctor'), 403);
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);
        $branchId = $user->hasRole('doctor')
            ? (int) $user->branch_id
            : ($request->input('branch_id') === '' ? null : $request->input('branch_id'));
        $branchIdRules = ['sometimes', 'nullable', 'integer'];
        $branchIdRules[] = $user->hasRole('doctor')
            ? Rule::in([(int) $user->branch_id])
            : Rule::exists('branches', 'id');
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')->where(fn ($query) => $query->where('branch_id', $branchId)),
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_display' => [Rule::requiredIf($request->boolean('requires_price_confirmation')), 'nullable', 'string', 'max:150'],
            'requires_price_confirmation' => ['required', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'in:'.implode(',', range(10, 240, 10))],
            'branch_id' => $branchIdRules,
        ]);

        if (! $request->boolean('requires_price_confirmation')) {
            $data['price_display'] = null;
        }

        if (! Schema::hasColumn('services', 'category')) {
            unset($data['category']);
        }

        $baseServiceId = $branchId === null
            ? null
            : Service::query()
                ->whereNull('branch_id')
                ->where('name', $data['name'])
                ->value('id');
        $service = Service::create([
            ...$data,
            'branch_id' => $branchId,
            'base_service_id' => $baseServiceId,
            'is_active' => true,
        ]);
        $branchName = $branchId === null
            ? 'all branches (shared service)'
            : (Branch::query()->whereKey($branchId)->value('name') ?? 'selected branch');
        $auditTrail->record(
            $user,
            'service_created',
            'services',
            $service,
            sprintf(
                'Created service "%s" at PHP %s, duration %d minutes, for %s.',
                $service->name,
                number_format((float) $service->price, 2),
                $service->duration_minutes,
                $branchName,
            ),
        );

        return back()->with('status', 'Service added.');
    }

    public function update(Request $request, Service $service, AuditTrail $auditTrail): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->hasRole('admin', 'doctor'), 403);
        abort_if($user->hasRole('doctor') && $user->branch_id === null, 403);

        if ($user->hasRole('doctor')) {
            abort_unless($service->branch_id === null || (int) $service->branch_id === (int) $user->branch_id, 404);
        }

        $requestedBranchId = $request->input('branch_id');
        $branchId = $user->hasRole('doctor')
            ? (int) $user->branch_id
            : ($service->branch_id !== null
                ? (int) $service->branch_id
                : ($request->exists('branch_id')
                    ? ($requestedBranchId === '' ? null : $requestedBranchId)
                    : null));

        if ($service->branch_id !== null && $request->exists('branch_id')
            && ! $user->hasRole('doctor')
            && (string) $branchId !== (string) $service->branch_id) {
            throw ValidationException::withMessages([
                'branch_id' => 'An existing branch-specific service cannot be moved to another branch.',
            ]);
        }

        $branchIdRules = ['sometimes', 'nullable', 'integer'];
        $branchIdRules[] = $user->hasRole('doctor')
            ? Rule::in([(int) $user->branch_id])
            : Rule::exists('branches', 'id');
        $uniqueNameRule = Rule::unique('services', 'name')
            ->where(fn ($query) => $query->where('branch_id', $branchId));
        if ($service->branch_id !== null || $branchId === null) {
            $uniqueNameRule->ignore($service->id);
        }

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                $uniqueNameRule,
            ],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_display' => [Rule::requiredIf($request->boolean('requires_price_confirmation')), 'nullable', 'string', 'max:150'],
            'requires_price_confirmation' => ['required', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'in:'.implode(',', range(10, 240, 10))],
            'is_active' => ['required', 'boolean'],
            'branch_id' => $branchIdRules,
        ]);

        if (! $request->boolean('requires_price_confirmation')) {
            $data['price_display'] = null;
        }

        if (! Schema::hasColumn('services', 'category')) {
            unset($data['category']);
        }

        $previousService = $service->replicate();
        $previousService->is_active = $service->is_active;

        if ($service->branch_id === null && $branchId !== null) {
            $branchService = $service->replicate();
            $branchService->fill([...$data, 'branch_id' => $branchId, 'base_service_id' => $service->id]);
            $branchService->save();
            $service = $branchService;
        } else {
            $service->update([...$data, 'branch_id' => $branchId]);
        }
        [$action, $description] = $this->serviceUpdateAuditDetails($user->name, $previousService, $service);
        $auditTrail->record($user, $action, 'services', $service, $description);

        return back()->with('status', 'Service updated.');
    }

    /**
     * @return array{string, string}
     */
    private function serviceUpdateAuditDetails(string $actorName, Service $before, Service $after): array
    {
        $changes = [];

        if ($before->name !== $after->name) {
            $changes[] = 'name changed from "'.$before->name.'" to "'.$after->name.'"';
        }

        if ((float) $before->price !== (float) $after->price) {
            $changes[] = 'price changed from PHP '.number_format((float) $before->price, 2)
                .' to PHP '.number_format((float) $after->price, 2);
        }

        foreach ([
            'category' => 'category',
            'description' => 'description',
            'duration_minutes' => 'duration',
            'price_display' => 'displayed price',
            'requires_price_confirmation' => 'price confirmation requirement',
        ] as $attribute => $label) {
            $beforeValue = $before->getAttribute($attribute);
            $afterValue = $after->getAttribute($attribute);

            if ($beforeValue !== $afterValue) {
                $changes[] = $label.' changed from '.($beforeValue === null || $beforeValue === '' ? 'none' : '"'.$beforeValue.'"')
                    .' to '.($afterValue === null || $afterValue === '' ? 'none' : '"'.$afterValue.'"');
            }
        }

        $statusChanged = $before->is_active !== $after->is_active;
        if ($statusChanged && $changes === []) {
            $action = $after->is_active ? 'service_activated' : 'service_deactivated';
            $verb = $after->is_active ? 'activated' : 'deactivated';

            return [
                $action,
                $actorName.' '.$verb.' service "'.$after->name.'".',
            ];
        }

        if ($statusChanged) {
            $changes[] = $after->is_active ? 'service activated' : 'service deactivated';
        }

        if ($changes === []) {
            return [
                'service_updated',
                $actorName.' saved service "'.$after->name.'" without changing its details.',
            ];
        }

        return [
            'service_updated',
            $actorName.' updated service "'.$after->name.'": '.implode('; ', $changes).'.',
        ];
    }
}
