<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover Doc Brigs Dental Care in General Trias and Trece. Find your branch, explore our services, and book an appointment.">
    <title>Doc Brigs Dental Care | General Trias &amp; Trece</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-teal-100 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="#home" class="flex min-w-0 items-center gap-3" aria-label="Doc Brigs Dental Care home">
                <img src="{{ asset('images/landing/doc-brigs-dental-care-logo.jpg') }}" alt="Doc Brigs Dental Care logo" class="h-14 w-12 shrink-0 object-contain sm:h-16 sm:w-14">
                <span class="max-w-40 text-sm font-bold leading-tight text-teal-950 sm:max-w-none sm:text-base">Doc Brigs Dental Care</span>
            </a>

            <nav class="hidden items-center gap-7 md:flex" aria-label="Main navigation">
                <a class="text-sm font-medium text-slate-600 transition hover:text-teal-800" href="#home">Home</a>
                <a class="text-sm font-medium text-slate-600 transition hover:text-teal-800" href="#about">About Us</a>
                <a class="text-sm font-medium text-slate-600 transition hover:text-teal-800" href="#branches">Our Branches</a>
                <a class="text-sm font-medium text-slate-600 transition hover:text-teal-800" href="#services">Services</a>
                <a class="btn-primary" href="{{ route('appointments.start') }}">Book an Appointment</a>
            </nav>

            <details class="relative md:hidden">
                <summary class="btn-secondary cursor-pointer list-none">Menu</summary>
                <nav class="absolute right-0 top-full z-50 mt-2 grid w-56 gap-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-xl" aria-label="Mobile navigation">
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#home">Home</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#about">About Us</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#branches">Our Branches</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#services">Services</a>
                    <a class="btn-primary mt-2" href="{{ route('appointments.start') }}">Book an Appointment</a>
                </nav>
            </details>
        </div>
    </header>

    <main>
        <section id="home" class="relative isolate scroll-mt-24 overflow-hidden bg-gradient-to-br from-teal-50 via-white to-emerald-50">
            <div class="pointer-events-none absolute -right-32 -top-40 -z-10 h-96 w-96 rounded-full bg-emerald-100/70 blur-3xl" aria-hidden="true"></div>
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[1fr_0.95fr] lg:gap-16 lg:px-8 lg:py-24">
                <div class="max-w-2xl">
                    <p class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-white/80 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-teal-800">
                        <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        General Trias &amp; Trece
                    </p>
                    <h1 class="mt-6 text-4xl font-bold leading-tight tracking-tight text-teal-950 sm:text-5xl lg:text-6xl">A brighter smile begins with care you can feel.</h1>
                    <p class="mt-5 max-w-xl text-lg leading-8 text-slate-600">Welcome to Doc Brigs Dental Care. Find a branch near you, explore our services, and take the next step toward your smile.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a class="btn-primary min-h-12 px-5 shadow-lg shadow-teal-800/15" href="{{ route('appointments.start') }}">Book an Appointment <span class="ml-2" aria-hidden="true">→</span></a>
                        <a class="btn-secondary min-h-12 px-5" href="#branches">Explore Our Branches</a>
                    </div>
                    <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm font-medium text-slate-600">
                        <span class="inline-flex items-center gap-2"><span class="text-emerald-600" aria-hidden="true">✓</span> Two convenient branches</span>
                        <span class="inline-flex items-center gap-2"><span class="text-emerald-600" aria-hidden="true">✓</span> Care centered around you</span>
                    </div>
                </div>
                <div class="relative mx-auto w-full max-w-xl">
                    <div class="absolute -inset-3 -rotate-2 rounded-[2rem] bg-gradient-to-br from-teal-200 to-emerald-200" aria-hidden="true"></div>
                    <figure class="relative overflow-hidden rounded-[1.75rem] border-4 border-white bg-white shadow-2xl shadow-teal-950/15">
                        <img src="{{ asset('images/landing/general-trias-treatment-room.jpg') }}" alt="Treatment room at the General Trias Branch" class="aspect-[4/3] w-full object-cover" fetchpriority="high">
                        <figcaption class="flex items-center justify-between gap-4 bg-white px-5 py-4">
                            <span>
                                <span class="block text-xs font-semibold uppercase tracking-[0.14em] text-teal-700">A warm welcome</span>
                                <span class="mt-1 block font-semibold text-slate-900">Your care, close to home</span>
                            </span>
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald-50 text-xl text-emerald-700" aria-hidden="true">✦</span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section id="about" class="scroll-mt-24 border-y border-teal-100 bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 sm:py-18 lg:grid-cols-[0.8fr_1.2fr] lg:items-center lg:gap-16 lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Welcome to Doc Brigs</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Good care starts with feeling at ease.</h2>
                </div>
                <div class="max-w-3xl">
                    <p class="text-lg leading-8 text-slate-600">We want every visit to feel welcoming. Our General Trias and Trece clinics are here to help you find the dental care you need in a friendly setting.</p>
                    <a class="mt-5 inline-flex min-h-11 items-center font-semibold text-teal-800 transition hover:text-teal-950" href="#branches">Get to know our branches <span class="ml-2" aria-hidden="true">→</span></a>
                </div>
            </div>
        </section>

        <section id="branches" class="scroll-mt-24 bg-gradient-to-b from-teal-50/80 to-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Find us near you</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Two branches, one caring team.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Take a look inside our clinics and choose the location that works for you.</p>
                </div>

                <div class="mt-10 grid gap-6 lg:grid-cols-2 lg:gap-8">
                    <article class="overflow-hidden rounded-3xl border border-teal-100 bg-white shadow-lg shadow-teal-950/5">
                        <div class="grid grid-cols-3 gap-2 p-3 sm:gap-3 sm:p-4">
                            <img src="{{ asset('images/landing/general-trias-treatment-room.jpg') }}" alt="Dental treatment room at the General Trias Branch" class="col-span-2 row-span-2 aspect-[4/3] h-full w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/general-trias-clinic-room.jpg') }}" alt="Dental chair and equipment at the General Trias Branch" class="aspect-[4/3] w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/general-trias-waiting-area.jpg') }}" alt="Waiting area at the General Trias Branch" class="aspect-[4/3] w-full rounded-2xl object-cover">
                        </div>
                        <div class="flex flex-col gap-5 px-5 pb-6 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Doc Brigs Dental Care</p>
                                <h3 class="mt-1 text-2xl font-bold text-teal-950">General Trias Branch</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">A welcoming clinic in General Trias, Cavite.</p>
                            </div>
                            <a class="btn-primary shrink-0" href="{{ route('appointments.start') }}">Book a Visit <span class="ml-2" aria-hidden="true">→</span></a>
                        </div>
                    </article>

                    <article class="overflow-hidden rounded-3xl border border-teal-100 bg-white shadow-lg shadow-teal-950/5">
                        <div class="grid grid-cols-3 gap-2 p-3 sm:gap-3 sm:p-4">
                            <img src="{{ asset('images/landing/trece-treatment-room.jpg') }}" alt="Dental treatment room at the Trece Branch" class="col-span-2 row-span-2 aspect-[4/3] h-full w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/trece-clinic-team.jpg') }}" alt="Clinic staff at the Trece Branch" class="aspect-[4/3] w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/trece-reception.jpg') }}" alt="Reception area at the Trece Branch" class="aspect-[4/3] w-full rounded-2xl object-cover">
                        </div>
                        <div class="flex flex-col gap-5 px-5 pb-6 sm:flex-row sm:items-end sm:justify-between sm:px-6">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Doc Brigs Dental Care</p>
                                <h3 class="mt-1 text-2xl font-bold text-teal-950">Trece Branch</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Visit our Trece clinic for your dental care.</p>
                            </div>
                            <a class="btn-primary shrink-0" href="{{ route('appointments.start') }}">Book a Visit <span class="ml-2" aria-hidden="true">→</span></a>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="services" class="scroll-mt-24 border-t border-slate-100 bg-white">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 sm:py-20 lg:grid-cols-[0.7fr_1.3fr] lg:gap-16 lg:px-8">
                <div class="max-w-xl">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Our Services</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Care for every kind of smile.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Explore services offered by Doc Brigs Dental Care. Have a question? Get in touch or book an appointment to learn more.</p>
                    <a class="btn-secondary mt-6 min-h-11" href="{{ route('appointments.start') }}">Ask about a service <span class="ml-2" aria-hidden="true">→</span></a>
                </div>

                <div>
                    @if ($services->isNotEmpty())
                        <ul class="grid gap-3 sm:grid-cols-2">
                            @foreach ($services as $service)
                                <li class="rounded-2xl border border-teal-100 bg-teal-50/50 p-5 transition hover:border-teal-200 hover:bg-white hover:shadow-md">
                                    @if ($service->category)
                                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $service->category }}</p>
                                    @endif
                                    <h3 class="mt-1 font-semibold text-teal-950">{{ $service->name }}</h3>
                                    @if ($service->description)
                                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $service->description }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="rounded-2xl border border-teal-100 bg-teal-50 p-5 leading-7 text-slate-600">Please contact the clinic for information about available dental services.</p>
                    @endif
                </div>
            </div>
        </section>

        <section id="contact" class="scroll-mt-24 bg-teal-950 text-white">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1fr_auto] lg:items-center lg:px-8">
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-emerald-300">Let’s get you smiling</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Ready to plan your visit?</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-teal-100">Call our General Trias clinic or book an appointment to get started.</p>
                    <div class="mt-5 flex flex-col gap-2 text-sm text-teal-100 sm:flex-row sm:gap-6">
                        <a class="min-h-11 font-semibold text-white underline decoration-emerald-400 underline-offset-4 hover:text-emerald-200" href="tel:+639171600541">0917 160 0541</a>
                        <a class="min-h-11 leading-6 underline decoration-emerald-400 underline-offset-4 hover:text-emerald-200" href="https://www.bing.com/maps/default.aspx?v=2&amp;pc=FACEBK&amp;mid=8100&amp;where1=14.313687869546%2C%20120.91615498066&amp;FORM=FBKPL1&amp;name=Unit%20F%202nd%20Floor%2C%20QMRC%20Building%2C%20Brookeside%20Lane%2C%20General%20Trias%2C%20Philippines%2C%204107&amp;mkt=en-US" target="_blank" rel="noopener noreferrer">Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107</a>
                    </div>
                </div>
                <a class="inline-flex min-h-12 items-center justify-center rounded-xl bg-white px-5 py-3 text-sm font-bold text-teal-900 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-teal-950" href="{{ route('appointments.start') }}">Book an Appointment <span class="ml-2" aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <footer class="border-t border-teal-100 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-7 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <a href="#home" class="flex items-center gap-3" aria-label="Doc Brigs Dental Care home">
                <img src="{{ asset('images/landing/doc-brigs-dental-care-logo.jpg') }}" alt="" class="h-12 w-10 object-contain">
                <span class="font-semibold text-teal-950">Doc Brigs Dental Care</span>
            </a>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Footer navigation">
                <a class="text-slate-600 hover:text-teal-800" href="#home">Home</a>
                <a class="text-slate-600 hover:text-teal-800" href="#about">About Us</a>
                <a class="text-slate-600 hover:text-teal-800" href="#branches">Our Branches</a>
                <a class="text-slate-600 hover:text-teal-800" href="#services">Services</a>
                <a class="text-slate-600 hover:text-teal-800" href="{{ route('appointments.start') }}">Book an Appointment</a>
            </nav>
            <a class="text-sm font-medium text-teal-800 hover:underline" href="tel:+639171600541">0917 160 0541</a>
        </div>
        <p class="border-t border-slate-100 px-4 py-4 text-center text-xs text-slate-500">© {{ now()->year }} Doc Brigs Dental Care. All rights reserved.</p>
    </footer>
</body>
</html>
