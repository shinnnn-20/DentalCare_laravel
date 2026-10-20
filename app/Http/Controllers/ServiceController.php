<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Services\AuditTrail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ServiceController extends Controller
{
    public function index(): View
    {
        return view('services.index', [
            'services' => Service::query()->orderBy('name')->paginate(20),
        ]);
    }

    public function store(Request $request, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:services,name'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_display' => [Rule::requiredIf($request->boolean('requires_price_confirmation')), 'nullable', 'string', 'max:150'],
            'requires_price_confirmation' => ['required', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'in:'.implode(',', range(10, 240, 10))],
        ]);

        if (! $request->boolean('requires_price_confirmation')) {
            $data['price_display'] = null;
        }

        $service = Service::create([...$data, 'is_active' => true]);
        $auditTrail->record($request->user(), 'service_created', 'services', $service);

        return back()->with('status', 'Service added.');
    }

    public function update(Request $request, Service $service, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($service->id)],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'price_display' => [Rule::requiredIf($request->boolean('requires_price_confirmation')), 'nullable', 'string', 'max:150'],
            'requires_price_confirmation' => ['required', 'boolean'],
            'duration_minutes' => ['required', 'integer', 'in:'.implode(',', range(10, 240, 10))],
            'is_active' => ['required', 'boolean'],
        ]);

        if (! $request->boolean('requires_price_confirmation')) {
            $data['price_display'] = null;
        }

        $service->update($data);
        $auditTrail->record($request->user(), 'service_updated', 'services', $service);

        return back()->with('status', 'Service updated.');
    }
}
