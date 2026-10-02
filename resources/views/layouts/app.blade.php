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
            <aside class="app-sidebar border-b border-slate-200 bg-white px-4 py-3 lg:border-r lg:border-b-0 lg:px-5 lg:py-6">
                <div class="flex items-center justify-between gap-3">
                <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3 font-bold text-xl text-teal-800">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-teal-700 text-white">D</span>
                    <span>DentalCare</span>
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
                @if (session('status'))
                    <div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        <p class="font-semibold">Please correct the following:</p>
                        <ul class="mt-2 list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    @else
        <main class="min-h-screen px-4 py-10">
            @if (session('status'))
                <div class="mx-auto mb-6 max-w-lg rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="mx-auto mb-6 max-w-lg rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                    <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            @yield('content')
        </main>
    @endauth
</body>
</html>
