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
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
        ]);

        $service = Service::create([...$data, 'is_active' => true]);
        $auditTrail->record($request->user(), 'service_created', 'services', $service);

        return back()->with('status', 'Service added.');
    }

    public function update(Request $request, Service $service, AuditTrail $auditTrail): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('services', 'name')->ignore($service->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $service->update($data);
        $auditTrail->record($request->user(), 'service_updated', 'services', $service);

        return back()->with('status', 'Service updated.');
    }
}
