<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · {{ config('clinic.name') }}</title>
    @stack('head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    @auth
        <div class="app-shell min-h-screen lg:grid lg:grid-cols-[250px_minmax(0,1fr)]">
            <aside class="app-sidebar border-b border-sky-100 bg-gradient-to-b from-white via-sky-50 to-emerald-50/60 px-4 py-3 lg:sticky lg:top-0 lg:h-screen lg:overflow-y-auto lg:border-r lg:border-b-0 lg:px-5 lg:py-6">
                <div class="flex items-center justify-between gap-3">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 font-bold text-xl text-teal-800">
                    <img src="{{ asset('images/landing/doc-brigs-dental-care-logo.jpg') }}" alt="Doc Brigs Dental Care logo" class="h-16 w-12 shrink-0 object-contain">
                    <span class="min-w-0 leading-tight">{{ config('clinic.name') }}</span>
                </a>
                <details class="relative lg:hidden">
                    <summary class="btn-secondary cursor-pointer list-none">Menu</summary>
                    <div class="absolute right-0 z-50 mt-2 w-[min(20rem,calc(100vw-2rem))] rounded-2xl border border-slate-200 bg-white p-3 shadow-xl">
                        <p class="mb-2 px-3 pt-2 text-xs uppercase tracking-widest text-slate-400">{{ str_replace('_', ' ', auth()->user()->role) }} portal</p>
                        <nav class="grid gap-1 text-sm">@include('layouts.navigation')</nav>
                        <div class="mt-3 border-t border-slate-100 px-3 pt-3">
                            <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                            <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-sm font-semibold text-teal-800 hover:text-teal-950">Sign out</button></form>
                        </div>
                    </div>
                </details>
                </div>
                <p class="mt-2 hidden text-xs uppercase tracking-widest text-slate-400 lg:block">{{ str_replace('_', ' ', auth()->user()->role) }} portal</p>
                <nav class="mt-8 hidden gap-1 text-sm lg:grid">@include('layouts.navigation')</nav>
                <div class="mt-10 hidden rounded-2xl bg-teal-50 p-4 lg:block">
                    <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                    <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}" class="mt-4">
                        @csrf
                        <button class="text-sm font-semibold text-teal-800 hover:text-teal-950">Sign out</button>
                    </form>
                </div>
            </aside>
            <main class="min-w-0 px-4 py-5 sm:px-8 sm:py-6 lg:px-10">
                <div class="mx-auto w-full max-w-screen-2xl">
                @if (auth()->user()->hasRole('admin', 'staff', 'doctor'))
                    <div @class(['mb-5 flex justify-end', 'print-hidden' => request()->routeIs('admin.reports')])>
                        @include('layouts.clinic-notifications')
                    </div>
                @endif
                @yield('content')
                </div>
            </main>
        </div>
    @else
        <main class="min-h-screen px-4 py-10">
            @yield('content')
        </main>
    @endauth

    @if ($errors->any())
        <template data-app-initial-modal data-modal-title="Please correct the following" data-modal-variant="error">
            <ul class="list-inside list-disc space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </template>
    @elseif (session('status'))
        <template data-app-initial-modal data-modal-title="Notification" data-modal-variant="success">
            <p>{{ session('status') }}</p>
        </template>
    @endif

    <dialog
        id="app-message-modal"
        class="m-auto w-[min(28rem,calc(100vw-2rem))] max-w-none rounded-2xl border border-slate-200 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
        aria-labelledby="app-modal-title"
        aria-describedby="app-modal-message"
    >
        <div class="flex items-start gap-4 p-6">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-teal-50 text-lg font-bold text-teal-800" aria-hidden="true" data-modal-icon>i</span>
            <div class="min-w-0 flex-1">
                <h2 class="text-lg font-semibold text-slate-900" id="app-modal-title" data-modal-title>Notice</h2>
                <div class="mt-2 max-h-[50vh] overflow-y-auto text-sm leading-6 text-slate-600" id="app-modal-message" data-modal-message></div>
            </div>
        </div>
        <div class="flex justify-end gap-3 border-t border-slate-100 bg-slate-50 px-6 py-4">
            <button class="btn-secondary" type="button" data-modal-cancel hidden>Cancel</button>
            <button class="btn-primary min-w-20 justify-center" type="button" data-modal-confirm>OK</button>
        </div>
    </dialog>
</body>
</html>
