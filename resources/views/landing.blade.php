<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Doc Brigs Dental Care offers dental services with a welcoming, patient-focused approach in General Trias, Cavite.">
    <title>Doc Brigs Dental Care · Dental care in General Trias</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
            <a href="#home" class="flex min-w-0 items-center gap-3" aria-label="Doc Brigs Dental Care home">
                <img src="{{ asset('images/landing/doc-brigs-logo.png') }}" alt="Doc Brigs Dental Care logo" class="h-14 w-12 shrink-0 object-contain sm:h-16 sm:w-14">
                <span class="max-w-40 text-sm font-bold leading-tight text-slate-900 sm:max-w-none sm:text-base">Doc Brigs Dental Care</span>
            </a>

            <nav class="hidden items-center gap-7 md:flex" aria-label="Main navigation">
                <a class="text-sm font-medium text-slate-600 hover:text-teal-800" href="#home">Home</a>
                <a class="text-sm font-medium text-slate-600 hover:text-teal-800" href="#about">About Us</a>
                <a class="text-sm font-medium text-slate-600 hover:text-teal-800" href="#services">Services</a>
                <a class="text-sm font-medium text-slate-600 hover:text-teal-800" href="#contact">Contact Us</a>
                <a class="btn-primary" href="{{ route('appointments.index') }}">Book an Appointment</a>
            </nav>

            <details class="relative md:hidden">
                <summary class="btn-secondary cursor-pointer list-none">Menu</summary>
                <nav class="absolute right-0 top-full z-50 mt-2 grid w-56 gap-1 rounded-2xl border border-slate-200 bg-white p-3 shadow-lg" aria-label="Mobile navigation">
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#home">Home</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#about">About Us</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#services">Services</a>
                    <a class="rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-teal-50" href="#contact">Contact Us</a>
                    <a class="btn-primary mt-2" href="{{ route('appointments.index') }}">Book an Appointment</a>
                </nav>
            </details>
        </div>
    </header>

    <main>
        <section id="home" class="scroll-mt-24">
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-2 lg:gap-16 lg:px-8 lg:py-20">
                <div class="max-w-xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">Doc Brigs Dental Care</p>
                    <h1 class="mt-4 text-4xl font-bold tracking-tight text-slate-950 sm:text-5xl">Dental care with your comfort in mind.</h1>
                    <p class="mt-5 max-w-lg text-lg leading-8 text-slate-600">A welcoming place for the dental services you need, with care focused on you at every visit.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a class="btn-primary min-h-12 px-5" href="{{ route('appointments.index') }}">Book an Appointment</a>
                        <a class="btn-secondary min-h-12 px-5" href="#about">Learn More</a>
                    </div>
                    <p class="mt-5 text-sm text-slate-500">Unit F, QMRC Building · General Trias, Cavite</p>
                </div>
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                    <img src="{{ asset('images/landing/clinic-exterior.png') }}" alt="Doc Brigs Dental Care clinic entrance" class="aspect-[4/3] w-full object-cover object-center">
                </div>
            </div>
        </section>

        <section id="about" class="scroll-mt-24 border-y border-slate-100 bg-slate-50">
            <div class="mx-auto grid max-w-7xl items-center gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[0.9fr_1.1fr] lg:gap-14 lg:px-8">
                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <img src="{{ asset('images/landing/clinic-interior.png') }}" alt="A welcoming area inside Doc Brigs Dental Care" class="aspect-[4/3] w-full object-cover object-center">
                </div>
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">About Us</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Care that starts with listening.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Doc Brigs Dental Care provides dental services in a friendly clinic setting. We aim to help every patient feel informed and comfortable when arranging care.</p>
                    <p class="mt-3 leading-7 text-slate-600">Contact the clinic or request an appointment to get started.</p>
                </div>
            </div>
        </section>

        <section id="services" class="scroll-mt-24">
            <div class="mx-auto grid max-w-7xl gap-9 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,0.72fr)] lg:items-center lg:gap-14 lg:px-8">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">Services</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">Dental services for your care</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-slate-600">Explore a selection from the Doc Brigs Dental Care service catalog. Ask the clinic about a service and its current fee.</p>

                    @if ($services->isNotEmpty())
                        <ul class="mt-7 grid gap-3 sm:grid-cols-2">
                            @foreach ($services as $service)
                                <li class="rounded-xl border border-slate-200 bg-white p-4">
                                    @if ($service->category)
                                        <p class="text-xs font-semibold uppercase tracking-wide text-teal-800">{{ $service->category }}</p>
                                    @endif
                                    <h3 class="mt-1 font-semibold text-slate-900">{{ $service->name }}</h3>
                                    @if ($service->description)
                                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $service->description }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-7 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">Please contact the clinic for information about available dental services.</p>
                    @endif

                    <a class="mt-6 inline-flex min-h-11 items-center font-semibold text-teal-800 hover:text-teal-950" href="{{ route('appointments.index') }}">Ask about a service <span class="ml-2" aria-hidden="true">→</span></a>
                </div>
                <figure class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                    <img src="{{ asset('images/landing/clinic-services.png') }}" alt="Dental services display at Doc Brigs Dental Care" class="aspect-[4/5] w-full object-cover object-center">
                    <figcaption class="border-t border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">Dental services at Doc Brigs Dental Care</figcaption>
                </figure>
            </div>
        </section>

        <section id="contact" class="scroll-mt-24 border-t border-slate-200 bg-slate-50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
                <div class="max-w-2xl">
                    <p class="text-sm font-semibold uppercase tracking-[0.16em] text-teal-800">Contact Us</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-slate-950">We’re here to help you plan your visit.</h2>
                </div>
                <div class="mt-7 grid gap-4 sm:grid-cols-2">
                    <article class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900">Call the clinic</h3>
                        <a class="mt-2 inline-flex min-h-11 items-center text-lg font-semibold text-teal-800 underline-offset-4 hover:underline" href="tel:+639171600541">0917 160 0541</a>
                    </article>
                    <article class="rounded-2xl border border-slate-200 bg-white p-5">
                        <h3 class="font-semibold text-slate-900">Visit Doc Brigs Dental Care</h3>
                        <a class="mt-2 inline-flex min-h-11 items-center leading-6 text-teal-800 underline-offset-4 hover:underline" href="https://www.bing.com/maps/default.aspx?v=2&amp;pc=FACEBK&amp;mid=8100&amp;where1=14.313687869546%2C%20120.91615498066&amp;FORM=FBKPL1&amp;name=Unit%20F%202nd%20Floor%2C%20QMRC%20Building%2C%20Brookeside%20Lane%2C%20General%20Trias%2C%20Philippines%2C%204107&amp;mkt=en-US" target="_blank" rel="noopener noreferrer">Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107</a>
                    </article>
                </div>
                <div class="mt-7">
                    <a class="btn-primary min-h-12 px-5" href="{{ route('appointments.index') }}">Book an Appointment</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="border-t border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-7 sm:px-6 lg:flex-row lg:items-center lg:justify-between lg:px-8">
            <a href="#home" class="flex items-center gap-3" aria-label="Doc Brigs Dental Care home">
                <img src="{{ asset('images/landing/doc-brigs-logo.png') }}" alt="" class="h-12 w-10 object-contain">
                <span class="font-semibold text-slate-900">Doc Brigs Dental Care</span>
            </a>
            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Footer navigation">
                <a class="text-slate-600 hover:text-teal-800" href="#home">Home</a>
                <a class="text-slate-600 hover:text-teal-800" href="#about">About Us</a>
                <a class="text-slate-600 hover:text-teal-800" href="#services">Services</a>
                <a class="text-slate-600 hover:text-teal-800" href="#contact">Contact Us</a>
                <a class="text-slate-600 hover:text-teal-800" href="{{ route('appointments.index') }}">Book an Appointment</a>
            </nav>
            <div class="max-w-xl text-sm leading-6 text-slate-600">
                <a class="font-medium text-teal-800 hover:underline" href="tel:+639171600541">0917 160 0541</a>
                <span aria-hidden="true"> · </span>
                <a class="text-teal-800 hover:underline" href="https://www.bing.com/maps/default.aspx?v=2&amp;pc=FACEBK&amp;mid=8100&amp;where1=14.313687869546%2C%20120.91615498066&amp;FORM=FBKPL1&amp;name=Unit%20F%202nd%20Floor%2C%20QMRC%20Building%2C%20Brookeside%20Lane%2C%20General%20Trias%2C%20Philippines%2C%204107&amp;mkt=en-US" target="_blank" rel="noopener noreferrer">Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107</a>
            </div>
        </div>
        <p class="border-t border-slate-100 px-4 py-4 text-center text-xs text-slate-500">© {{ now()->year }} Doc Brigs Dental Care. All rights reserved.</p>
    </footer>
</body>
</html>
