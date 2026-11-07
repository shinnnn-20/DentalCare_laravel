<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Discover Doc Brigs Dental Care in Dasmariñas, General Trias, and Trece. Find your branch, explore our services, and book an appointment.">
    <title>Doc Brigs Dental Care | Dasmariñas, General Trias &amp; Trece</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-800 antialiased">
    <header class="sticky top-0 z-40 border-b border-teal-100/80 bg-white/95 backdrop-blur-xl">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3.5 sm:px-6 lg:px-8">
            <a href="#home" class="flex min-w-0 items-center gap-3" aria-label="Doc Brigs Dental Care home">
                <img src="{{ asset('images/landing/doc-brigs-dental-care-logo.jpg') }}" alt="Doc Brigs Dental Care logo" class="h-14 w-12 shrink-0 object-contain sm:h-16 sm:w-14">
                <span class="max-w-40 text-sm font-bold leading-tight text-teal-950 sm:max-w-none sm:text-base">Doc Brigs Dental Care</span>
            </a>

            <nav class="hidden items-center gap-8 lg:flex" aria-label="Main navigation">
                <a class="text-sm font-semibold text-slate-600 transition hover:text-teal-800" href="#home">Home</a>
                <a class="text-sm font-semibold text-slate-600 transition hover:text-teal-800" href="#about">About Us</a>
                <a class="text-sm font-semibold text-slate-600 transition hover:text-teal-800" href="#branches">Our Branches</a>
                <a class="text-sm font-semibold text-slate-600 transition hover:text-teal-800" href="#services">Services</a>
                <a class="btn-primary rounded-full px-5 shadow-md shadow-teal-800/15" href="{{ route('appointments.start') }}">Book an Appointment</a>
            </nav>

            <details class="relative lg:hidden">
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
            <div class="pointer-events-none absolute -right-28 -top-24 -z-10 h-[30rem] w-[30rem] rounded-full bg-emerald-100/70 blur-3xl" aria-hidden="true"></div>
            <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 sm:py-20 md:min-h-[560px] md:grid-cols-[1.02fr_0.98fr] md:gap-10 lg:min-h-[650px] lg:gap-16 lg:px-8 lg:py-24">
                <div class="max-w-2xl">
                    <p class="inline-flex items-center gap-2 rounded-full border border-teal-200 bg-white/80 px-4 py-2 text-xs font-bold uppercase tracking-[0.16em] text-teal-800 shadow-sm shadow-teal-950/5">
                        <span class="h-2 w-2 rounded-full bg-emerald-500" aria-hidden="true"></span>
                        Three locations across Cavite
                    </p>
                    <h1 class="mt-6 text-4xl font-bold leading-[1.08] tracking-tight text-teal-950 sm:text-5xl lg:text-6xl">A brighter smile begins with <span class="text-teal-700">care you can feel.</span></h1>
                    <p class="mt-6 max-w-xl text-lg leading-8 text-slate-600">Welcome to Doc Brigs Dental Care. Find a branch near you, explore our services, and take the next step toward your smile.</p>
                    <div class="mt-8 flex flex-wrap items-center gap-3">
                        <a class="btn-primary min-h-12 rounded-full px-6 shadow-lg shadow-teal-800/20" href="{{ route('appointments.start') }}">Book an Appointment <span class="ml-2" aria-hidden="true">→</span></a>
                        <a class="btn-secondary min-h-12 rounded-full border-teal-200 px-6" href="#branches">Explore Our Branches</a>
                    </div>
                    <div class="mt-9 flex flex-wrap items-center gap-x-7 gap-y-3 border-t border-teal-200/70 pt-5 text-sm font-medium text-slate-600">
                        <span class="inline-flex items-center gap-2"><span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700" aria-hidden="true">✓</span> Three convenient branches</span>
                        <span class="inline-flex items-center gap-2"><span class="grid h-5 w-5 place-items-center rounded-full bg-emerald-100 text-xs font-bold text-emerald-700" aria-hidden="true">✓</span> Care centered around you</span>
                    </div>
                </div>
                <div class="relative mx-auto w-full max-w-xl px-2 py-3 sm:px-4">
                    <div class="absolute inset-3 rotate-3 rounded-[2rem] bg-gradient-to-br from-teal-200 to-emerald-200" aria-hidden="true"></div>
                    <figure class="relative overflow-hidden rounded-[2rem] border-8 border-white bg-white shadow-2xl shadow-teal-950/15">
                        <img src="{{ asset('images/landing/general-trias-treatment-room.jpg') }}" alt="Treatment room at the General Trias Branch" class="aspect-[4/3] w-full object-cover" fetchpriority="high">
                        <figcaption class="flex items-center justify-between gap-4 bg-white px-5 py-5 sm:px-7">
                            <span>
                                <span class="block text-xs font-semibold uppercase tracking-[0.14em] text-teal-700">A warm welcome</span>
                                <span class="mt-1 block font-semibold text-slate-900">Your care, close to home</span>
                            </span>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <section id="about" class="scroll-mt-24 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                <div class="grid gap-8 rounded-[2rem] border border-teal-100 bg-gradient-to-br from-teal-50/80 to-white p-7 shadow-sm shadow-teal-950/5 sm:p-10 lg:grid-cols-[0.85fr_1.15fr] lg:items-center lg:gap-16 lg:p-14">
                    <div>
                        <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Welcome to Doc Brigs</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Good care starts with feeling at ease.</h2>
                    </div>
                    <div class="max-w-3xl">
                        <p class="text-lg leading-8 text-slate-600">We want every visit to feel welcoming. Our Dasmariñas, General Trias, and Trece clinics are here to help you find the dental care you need in a friendly setting.</p>
                        <a class="mt-5 inline-flex min-h-11 items-center font-semibold text-teal-800 transition hover:text-teal-950" href="#branches">Get to know our branches <span class="ml-2" aria-hidden="true">→</span></a>
                    </div>
                </div>
            </div>
        </section>

        <section id="branches" class="scroll-mt-24 bg-gradient-to-b from-teal-50/70 via-white to-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Find us near you</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Three branches, one caring team.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Take a look inside our clinics and choose the location that works for you.</p>
                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3 lg:gap-7">
                    <article class="flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-teal-100 bg-white shadow-md shadow-teal-950/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-950/10">
                        <div class="grid aspect-[1.5] grid-cols-3 grid-rows-2 gap-2 p-3 sm:gap-2.5 sm:p-4">
                            <img src="{{ asset('images/landing/general-trias-branch-exterior.png') }}" alt="Clinic building at the General Trias Branch, marked by yellow arrows" class="col-span-2 row-span-2 h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/general-trias-clinic-room.jpg') }}" alt="Dental chair and equipment at the General Trias Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/general-trias-waiting-area.jpg') }}" alt="Waiting area at the General Trias Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                        </div>
                        <div class="flex flex-1 flex-col gap-3 px-5 pb-6 sm:px-6">
                            <div class="flex-1">
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Doc Brigs Dental Care</p>
                                <h3 class="mt-1 text-xl font-bold text-teal-950">General Trias Branch</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">A welcoming clinic in General Trias, Cavite.</p>
                                <p class="mt-3 border-t border-slate-100 pt-3 text-sm leading-6 text-slate-600">Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107</p>
                                <span class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800">0917 160 0541</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <a class="btn-primary rounded-full px-5" href="{{ route('appointments.start') }}">Book a Visit <span class="ml-2" aria-hidden="true">→</span></a>
                                <a class="btn-secondary rounded-full px-5" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode('Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107') }}" target="_blank" rel="noopener noreferrer" aria-label="View General Trias Branch on Google Maps">View on Google Maps</a>
                            </div>
                        </div>
                    </article>

                    <article class="flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-teal-100 bg-white shadow-md shadow-teal-950/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-950/10">
                        <div class="grid aspect-[1.5] grid-cols-3 grid-rows-2 gap-2 p-3 sm:gap-2.5 sm:p-4">
                            <img src="{{ asset('images/landing/trece-reception.jpg') }}" alt="Reception area at the Trece Branch" class="col-span-2 row-span-2 h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/trece-clinic-team.jpg') }}" alt="Clinic staff at the Trece Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/trece-treatment-room.jpg') }}" alt="Dental treatment room at the Trece Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                        </div>
                        <div class="flex flex-1 flex-col gap-3 px-5 pb-6 sm:px-6">
                            <div class="flex-1">
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Doc Brigs Dental Care</p>
                                <h3 class="mt-1 text-xl font-bold text-teal-950">Trece Branch</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Visit our Trece clinic for your dental care.</p>
                                <p class="mt-3 border-t border-slate-100 pt-3 text-sm leading-6 text-slate-600">7V6G+7QX, Trece Martires - Indang Rd, Inocencio (B. Pook), Trece Martires City, Cavite</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <a class="btn-primary rounded-full px-5" href="{{ route('appointments.start') }}">Book a Visit <span class="ml-2" aria-hidden="true">→</span></a>
                                <a class="btn-secondary rounded-full px-5" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode('7V6G+7QX, Trece Martires - Indang Rd, Inocencio (B. Pook), Trece Martires City, Cavite') }}" target="_blank" rel="noopener noreferrer" aria-label="View Trece Branch on Google Maps">View on Google Maps</a>
                            </div>
                        </div>
                    </article>

                    <article class="flex h-full flex-col overflow-hidden rounded-[1.75rem] border border-teal-100 bg-white shadow-md shadow-teal-950/5 transition duration-300 hover:-translate-y-1 hover:shadow-xl hover:shadow-teal-950/10">
                        <div class="grid aspect-[1.5] grid-cols-3 grid-rows-2 gap-2 p-3 sm:gap-2.5 sm:p-4">
                            <img src="{{ asset('images/landing/dasmarinas-exterior.jpg') }}" alt="Exterior of the Dasmariñas Branch" class="col-span-2 row-span-2 h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/dasmarinas-reception.jpg') }}" alt="Reception area at the Dasmariñas Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                            <img src="{{ asset('images/landing/dasmarinas-treatment-room.jpg') }}" alt="Dental treatment room at the Dasmariñas Branch" class="h-full min-h-0 w-full rounded-2xl object-cover">
                        </div>
                        <div class="flex flex-1 flex-col gap-5 px-5 pb-6 sm:px-6">
                            <div class="flex-1">
                                <p class="text-xs font-bold uppercase tracking-[0.14em] text-emerald-700">Doc Brigs Dental Care</p>
                                <h3 class="mt-1 text-xl font-bold text-teal-950">Dasmariñas Branch</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600">Visit our Dasmariñas clinic in Cavite.</p>
                                <p class="mt-3 border-t border-slate-100 pt-3 text-sm leading-6 text-slate-600">Unit 8 CITRINE Bldg., Brgy. Sampaloc I (opposite side of Philippine Christian University - PCU), Dasmariñas, Philippines, 4114</p>
                                <span class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-teal-800">0967 284 1871</span>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <a class="btn-primary rounded-full px-5" href="{{ route('appointments.start') }}">Book a Visit <span class="ml-2" aria-hidden="true">→</span></a>
                                <a class="btn-secondary rounded-full px-5" href="https://www.google.com/maps/search/?api=1&amp;query={{ rawurlencode('Unit 8 CITRINE Bldg., Brgy. Sampaloc I (opposite side of Philippine Christian University - PCU), Dasmariñas, Philippines, 4114') }}" target="_blank" rel="noopener noreferrer" aria-label="View Dasmariñas Branch on Google Maps">View on Google Maps</a>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>

        <section id="services" class="scroll-mt-24 border-t border-slate-100 bg-white">
            <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 sm:py-20 lg:px-8">
                <div class="mx-auto max-w-2xl text-center">
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Our Services</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-teal-950 sm:text-4xl">Care for every kind of smile.</h2>
                    <p class="mt-4 leading-7 text-slate-600">Explore services offered by Doc Brigs Dental Care. Have a question? Get in touch or book an appointment to learn more.</p>
                </div>

                <ul class="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                        'Oral Prophylaxis' => 'A professional cleaning that removes plaque and tartar buildup and helps keep your teeth and gums healthy. Your dentist can recommend how often you should have a cleaning.',
                        'Restoration' => 'A dental restoration repairs a tooth affected by decay or damage. The dentist will assess the tooth and discuss a suitable filling or other restorative option.',
                        'Extraction' => 'A tooth extraction is the removal of a tooth when it cannot be preserved or when removal is otherwise recommended. Your dentist will explain the procedure and aftercare.',
                        'Dentures' => 'Dentures are removable appliances that replace missing teeth and help restore everyday function. Fit and design are planned with your dental team.',
                        'Braces' => 'Braces apply gentle, controlled pressure over time to help align teeth and improve your bite. Treatment length and options depend on an individual assessment.',
                        'Surgery' => 'Oral surgery includes procedures to address certain conditions affecting the teeth, gums, or jaw. Your dentist will explain the recommended procedure and recovery before treatment.',
                        'Root Canal Therapy' => 'Root canal therapy treats the inner part of a tooth when its pulp is inflamed or infected, with the goal of relieving symptoms and preserving the tooth when possible.',
                        'Jacket Crowns' => 'A jacket crown is a tooth-shaped cover placed over a prepared tooth to restore its shape, strength, or appearance. Suitability depends on your dental assessment.',
                        'Whitening' => 'Professional teeth whitening is a cosmetic treatment intended to lighten the appearance of natural teeth. Your dentist can discuss expected results and whether it is appropriate for you.',
                        'Implant' => 'A dental implant is a post placed in the jaw to support a replacement tooth. Planning includes an assessment of oral health and bone support.',
                    ] as $serviceName => $serviceDescription)
                        <li>
                            <button
                                class="group flex min-h-20 w-full items-center gap-4 rounded-2xl border border-teal-100/80 bg-white px-5 py-4 text-left shadow-sm shadow-teal-950/[0.03] transition duration-200 hover:-translate-y-0.5 hover:border-teal-200 hover:shadow-lg hover:shadow-teal-950/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                                type="button"
                                data-service-dialog-trigger
                                data-service-name="{{ $serviceName }}"
                                data-service-description="{{ $serviceDescription }}"
                                aria-haspopup="dialog"
                            >
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-teal-50 text-teal-700" aria-hidden="true">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.5c-1.6 0-2.5 1-4.1 1-1.8 0-3.4-1.3-5-.2-1.8 1.4-1.1 5.2-.3 7.3 1 2.8 2.3 4.9 3.3 7.7.4 1.1 1.1 2.2 2.1 2.2 1.8 0 1.3-4.9 4-4.9s2.2 4.9 4 4.9c1 0 1.7-1.1 2.1-2.2 1-2.8 2.3-4.9 3.3-7.7.8-2.1 1.5-5.9-.3-7.3-1.6-1.1-3.2.2-5 .2-1.6 0-2.5-1-4.1-1Z"/>
                                        <path stroke-linecap="round" d="M9.5 9.5h5"/>
                                    </svg>
                                </span>
                                <span class="font-semibold text-teal-950">{{ $serviceName }}</span>
                                <span class="ml-auto text-sm font-semibold text-teal-700 opacity-0 transition group-hover:opacity-100 group-focus-visible:opacity-100">Learn more</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                </div>
            </div>
        </section>

        <dialog
            class="m-auto w-[min(32rem,calc(100vw-2rem))] max-w-none rounded-2xl border border-teal-100 bg-white p-0 text-slate-800 shadow-2xl backdrop:bg-slate-950/50 backdrop:backdrop-blur-sm"
            data-service-dialog
            aria-labelledby="service-dialog-title"
            aria-describedby="service-dialog-description"
        >
            <div class="p-6 sm:p-8">
                <p class="text-sm font-bold uppercase tracking-[0.16em] text-teal-700">Dental service</p>
                <h2 class="mt-2 text-2xl font-bold text-teal-950" id="service-dialog-title" data-service-dialog-title></h2>
                <p class="mt-4 leading-7 text-slate-600" id="service-dialog-description" data-service-dialog-description></p>
            </div>
            <div class="flex flex-col-reverse gap-3 border-t border-slate-100 bg-slate-50 p-5 sm:flex-row sm:justify-end">
                <button class="btn-secondary justify-center" type="button" data-service-dialog-close>Close</button>
                <a class="btn-primary justify-center" href="{{ route('appointments.start') }}">Book an Appointment</a>
            </div>
        </dialog>

        <section id="contact" class="scroll-mt-24 bg-white px-4 py-12 sm:px-6 sm:py-16 lg:px-8">
            <div class="relative mx-auto grid max-w-7xl gap-8 overflow-hidden rounded-[2rem] bg-gradient-to-br from-teal-950 via-teal-900 to-teal-800 px-6 py-10 text-white shadow-xl shadow-teal-950/15 sm:px-10 sm:py-14 lg:grid-cols-[1fr_auto] lg:items-center lg:px-14">
                <div class="pointer-events-none absolute -right-20 -top-32 h-80 w-80 rounded-full border-[40px] border-white/5" aria-hidden="true"></div>
                <div>
                    <p class="text-sm font-bold uppercase tracking-[0.16em] text-emerald-300">Let’s get you smiling</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">Ready to plan your visit?</h2>
                    <p class="mt-3 max-w-2xl leading-7 text-teal-100">Call our General Trias clinic or book an appointment to get started.</p>
                    <div class="mt-5 flex flex-col gap-2 text-sm text-teal-100 sm:flex-row sm:gap-6">
                        <span class="min-h-11 font-semibold text-white">0917 160 0541</span>
                        <a class="min-h-11 leading-6 underline decoration-emerald-400 underline-offset-4 hover:text-emerald-200" href="https://www.bing.com/maps/default.aspx?v=2&amp;pc=FACEBK&amp;mid=8100&amp;where1=14.313687869546%2C%20120.91615498066&amp;FORM=FBKPL1&amp;name=Unit%20F%202nd%20Floor%2C%20QMRC%20Building%2C%20Brookeside%20Lane%2C%20General%20Trias%2C%20Philippines%2C%204107&amp;mkt=en-US" target="_blank" rel="noopener noreferrer">Unit F 2nd Floor, QMRC Building, Brookeside Lane, General Trias, Philippines, 4107</a>
                    </div>
                </div>
                <a class="btn-primary relative min-h-12 rounded-full px-6 py-3 shadow-lg" href="{{ route('appointments.start') }}">Book an Appointment <span class="ml-2" aria-hidden="true">→</span></a>
            </div>
        </section>
    </main>

    <footer class="border-t border-teal-100 bg-teal-50/40">
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
            <span class="text-sm font-medium text-teal-800">0917 160 0541</span>
        </div>
        <p class="border-t border-slate-100 px-4 py-4 text-center text-xs text-slate-500">© {{ now()->year }} Doc Brigs Dental Care. All rights reserved.</p>
    </footer>
</body>
</html>
