@extends('layouts.portal')

@section('title', 'Book a Visit | SmartDalaw')

@push('head')
    <style>
        input[type="date"] {
            color-scheme: light;
        }
    </style>
@endpush

@php
    // Single source of truth for the visiting schedule.
    // Keys follow JavaScript's Date#getDay(): 0 = Sunday ... 6 = Saturday.
    $afternoonSlots = [
        '1:00 PM - 2:00 PM',
        '2:00 PM - 3:00 PM',
        '3:00 PM - 4:00 PM',
        '4:00 PM - 5:00 PM',
    ];
    $morningSlots = [
        '8:00 AM - 9:00 AM',
        '9:00 AM - 10:00 AM',
        '10:00 AM - 11:00 AM',
        '11:00 AM - 12:00 PM',
    ];
    $visitingSchedule = [
        0 => array_merge($morningSlots, $afternoonSlots), // Sunday
        1 => [],                                          // Monday: no visitation
        2 => $afternoonSlots,                             // Tuesday
        3 => $afternoonSlots,                             // Wednesday
        4 => $afternoonSlots,                             // Thursday
        5 => $afternoonSlots,                             // Friday
        6 => array_merge($morningSlots, $afternoonSlots), // Saturday
    ];
    $minVisitDate = now()->addDay()->format('Y-m-d'); // today and past dates are never allowed

    $dormitoryOptions = $facilities->mapWithKeys(fn ($facility) => [
        $facility->id => $facility->dormitories->map(fn ($dormitory) => [
            'id' => $dormitory->id,
            'name' => $dormitory->name,
            'badge_url' => $dormitory->badge_image_path
                ? \Illuminate\Support\Facades\Storage::disk('public')->url($dormitory->badge_image_path)
                : '',
        ])->values(),
    ]);
@endphp

@section('content')
    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-12 lg:px-8">
        <div class="mb-8 max-w-2xl">
            <p class="text-xs font-semibold uppercase text-[#003366]">Official BJMP e-Service</p>
            <h1 class="mt-2 text-3xl font-bold text-[#002244] sm:text-4xl">Book a visit</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Choose a facility and schedule, then provide visitor details for review.</p>
        </div>

        @if(session('error'))
            <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div id="booking-errors" class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert" data-step="{{ $errors->hasAny(['facility_id', 'dormitory_id', 'visit_date', 'time_slot']) ? 1 : 2 }}">
                <p class="font-semibold">Please review the highlighted booking details.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_18rem]">
            <div>
                <ol id="booking-progress" class="mb-5 grid grid-cols-3 overflow-hidden rounded-lg border border-slate-200 bg-white" aria-label="Booking steps">
                    <li data-step-indicator="1" class="border-b-2 border-[#003366] bg-blue-50 px-3 py-4 text-center text-xs font-semibold text-[#003366] sm:px-5 sm:text-sm"><span class="block text-[10px] font-medium sm:inline sm:text-xs">01</span> Facility &amp; Schedule</li>
                    <li data-step-indicator="2" class="border-b-2 border-transparent px-3 py-4 text-center text-xs font-medium text-slate-500 sm:px-5 sm:text-sm"><span class="block text-[10px] font-medium sm:inline sm:text-xs">02</span> Visitor Details</li>
                    <li data-step-indicator="3" class="border-b-2 border-transparent px-3 py-4 text-center text-xs font-medium text-slate-500 sm:px-5 sm:text-sm"><span class="block text-[10px] font-medium sm:inline sm:text-xs">03</span> Review</li>
                </ol>

                <form action="{{ route('bookings.store') }}" method="POST" enctype="multipart/form-data" id="bookingForm" class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:p-7">
                    @csrf

                    <section data-booking-step="1" class="space-y-6" aria-labelledby="schedule-heading">
                        <div>
                            <p class="text-xs font-semibold text-[#0A2472]">STEP 1 OF 3</p>
                            <h2 id="schedule-heading" class="mt-1 text-xl font-bold text-slate-900">Facility &amp; Schedule</h2>
                            <p class="mt-1 text-sm text-slate-600">Choose where and when you want to visit.</p>
                        </div>

                        <div class="flex gap-3 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#003366] text-xs font-bold text-white" aria-hidden="true">i</span>
                            <div>
                                <p><strong>Reminder:</strong> Visitation slots are limited. Please arrive 15 minutes before your scheduled time.</p>
                                <ul class="mt-2 list-inside list-disc text-[13px] leading-5">
                                    <li><strong>Monday:</strong> No visitation</li>
                                    <li><strong>Tuesday – Friday:</strong> 1:00 PM – 5:00 PM</li>
                                    <li><strong>Saturday – Sunday:</strong> 8:00 AM – 12:00 PM &amp; 1:00 PM – 5:00 PM</li>
                                </ul>
                            </div>
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="facility_id" class="mb-2 block text-sm font-semibold text-slate-800">Jail facility <span class="text-red-600">*</span></label>
                                <input type="text" id="facility_search" placeholder="Search facilities..." class="mb-2 w-full rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <select id="facility_id" name="facility_id" required class="select select-bordered w-full rounded-lg bg-base-100 text-sm focus:outline-none">
                                    <option value="">Select a jail facility</option>
                                    @foreach($facilities as $facility)
                                        <option value="{{ $facility->id }}" data-badge-url="{{ $facility->badge_image_path ? \Illuminate\Support\Facades\Storage::disk('public')->url($facility->badge_image_path) : '' }}" data-regional-office="{{ $facility->regional_office }}" data-jail-unit="{{ $facility->jail_unit_name }}" data-name="{{ $facility->name }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1.5 text-xs text-slate-500">Choose the facility where the person you wish to visit is held.</p>
                                <div id="facility-brand-panel" class="mt-4 hidden items-center gap-4 rounded-lg border border-base-300 bg-base-200 p-4" aria-live="polite">
                                    <img id="facility-badge-fallback" src="{{ asset('images/bjmp-logo.png') }}" alt="BJMP Official Seal" class="h-16 w-16 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                                    <img id="facility-badge-image" class="hidden h-16 w-16 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1" alt="Official facility badge">
                                    <div class="min-w-0">
                                        <p id="facility-brand-name" class="font-semibold text-base-content"></p>
                                        <p id="facility-brand-regional" class="mt-1 text-xs text-base-content/70"></p>
                                        <p id="facility-brand-unit" class="text-xs text-base-content/70"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="sm:col-span-2">
                                <label for="dormitory_id" class="mb-2 block text-sm font-semibold text-slate-800">Dormitory <span class="text-red-600">*</span></label>
                                <select id="dormitory_id" name="dormitory_id" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select a facility first</option>
                                </select>
                                <p class="mt-1.5 text-xs text-slate-500">Choose the dormitory where the person you wish to visit is assigned.</p>
                                <div id="dormitory-brand-panel" class="mt-3 hidden items-center gap-3 rounded-lg border border-base-300 bg-base-200 p-3">
                                    <img id="dormitory-badge-image" class="hidden h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1" alt="Dormitory badge">
                                    <div class="min-w-0">
                                        <p id="dormitory-brand-name" class="text-sm font-semibold text-base-content"></p>
                                        <p class="text-xs text-base-content/70">Assigned dormitory</p>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <label for="visit_date" class="mb-2 block text-sm font-semibold text-slate-800">Visit date <span class="text-red-600">*</span></label>
                                <input type="date" id="visit_date" name="visit_date" value="{{ old('visit_date') }}" min="{{ $minVisitDate }}" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <p class="mt-1.5 text-xs text-slate-500">Choose a future date (starting tomorrow). Mondays are not available.</p>
                                <p id="visit_date_error" class="mt-1.5 hidden text-xs font-medium text-red-700" role="alert"></p>
                            </div>

                            <div>
                                <label for="time_slot" class="mb-2 block text-sm font-semibold text-slate-800">Time slot <span class="text-red-600">*</span></label>
                                <select id="time_slot" name="time_slot" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select a visit date first</option>
                                </select>
                                <p id="time_slot_hint" class="mt-1.5 text-xs text-slate-500">Available time slots depend on the day you choose.</p>
                            </div>
                        </div>

                        <div id="slotAvailability" class="hidden rounded-lg border px-4 py-3" aria-live="polite">
                            <p id="availabilityMessage" class="text-sm"></p>
                        </div>

                        <div class="flex justify-end border-t border-slate-100 pt-5">
                            <button type="button" data-go-step="2" class="btn btn-primary min-h-11">Continue to visitor details</button>
                        </div>
                    </section>

                    <section data-booking-step="2" class="hidden space-y-6" aria-labelledby="visitor-heading">
                        <div>
                            <p class="text-xs font-semibold text-[#003366]">STEP 2 OF 3</p>
                            <h2 id="visitor-heading" class="mt-1 text-xl font-bold text-slate-900">Visitor &amp; PDL Information</h2>
                            <p class="mt-1 text-sm text-slate-600">Enter your details as they appear on your valid ID.</p>
                        </div>

                        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950">
                            Please provide accurate information. Inaccurate details may result in denial of entry.
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <label for="pdl_name" class="mb-2 block text-sm font-semibold text-slate-800">Person deprived of liberty (PDL) Name <span class="text-red-600">*</span></label>
                                <input type="text" id="pdl_name" name="pdl_name" value="{{ old('pdl_name') }}" required autocomplete="off" placeholder="Juan A. Dela Cruz" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <p class="mt-1.5 text-xs text-slate-500">Enter the full name of the person you wish to visit. This information is kept private.</p>
                            </div>
                            <div class="sm:col-span-2">
                                <label for="visitor_full_name" class="mb-2 block text-sm font-semibold text-slate-800">Full name (as in valid ID) <span class="text-red-600">*</span></label>
                                <input type="text" id="visitor_full_name" name="visitor_full_name" value="{{ old('visitor_full_name') }}" required autocomplete="name" placeholder="Juan A. Dela Cruz" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label for="visitor_contact" class="mb-2 block text-sm font-semibold text-slate-800">Contact number <span class="text-red-600">*</span></label>
                                <input type="tel" id="visitor_contact" name="visitor_contact" value="{{ old('visitor_contact') }}" required autocomplete="tel" placeholder="09123456789" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label for="visitor_email" class="mb-2 block text-sm font-semibold text-slate-800">Email <span class="font-normal text-slate-500">(optional)</span></label>
                                <input type="email" id="visitor_email" name="visitor_email" value="{{ old('visitor_email') }}" autocomplete="email" placeholder="juan@example.com" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label for="visitor_sex" class="mb-2 block text-sm font-semibold text-slate-800">Sex <span class="text-red-600">*</span></label>
                                <select id="visitor_sex" name="visitor_sex" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select sex</option>
                                    <option value="Male" @selected(old('visitor_sex') === 'Male')>Male</option>
                                    <option value="Female" @selected(old('visitor_sex') === 'Female')>Female</option>
                                </select>
                            </div>
                            <div>
                                <label for="visitor_age" class="mb-2 block text-sm font-semibold text-slate-800">Age <span class="text-red-600">*</span></label>
                                <input type="number" id="visitor_age" name="visitor_age" value="{{ old('visitor_age') }}" required min="1" max="120" placeholder="25" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label for="visitor_id_type" class="mb-2 block text-sm font-semibold text-slate-800">Type of valid ID <span class="text-red-600">*</span></label>
                                <select id="visitor_id_type" name="visitor_id_type" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select ID type</option>
                                    <option value="PhilSys National ID" @selected(old('visitor_id_type') === 'PhilSys National ID')>PhilSys National ID</option>
                                    <option value="Driver's License" @selected(old('visitor_id_type') === "Driver's License")>Driver's License</option>
                                    <option value="Passport" @selected(old('visitor_id_type') === 'Passport')>Passport</option>
                                    <option value="UMID" @selected(old('visitor_id_type') === 'UMID')>UMID</option>
                                    <option value="Postal ID" @selected(old('visitor_id_type') === 'Postal ID')>Postal ID</option>
                                    <option value="Voter's ID" @selected(old('visitor_id_type') === "Voter's ID")>Voter's ID</option>
                                    <option value="SSS ID" @selected(old('visitor_id_type') === 'SSS ID')>SSS ID</option>
                                    <option value="GSIS ID" @selected(old('visitor_id_type') === 'GSIS ID')>GSIS ID</option>
                                    <option value="PhilHealth ID" @selected(old('visitor_id_type') === 'PhilHealth ID')>PhilHealth ID</option>
                                    <option value="Senior Citizen ID" @selected(old('visitor_id_type') === 'Senior Citizen ID')>Senior Citizen ID</option>
                                    <option value="Others" @selected(old('visitor_id_type') === 'Others')>Others</option>
                                </select>
                            </div>
                            <div>
                                <label for="visitor_id_number" class="mb-2 block text-sm font-semibold text-slate-800">ID number <span class="text-red-600">*</span></label>
                                <input type="text" id="visitor_id_number" name="visitor_id_number" value="{{ old('visitor_id_number') }}" required placeholder="XXXXX-XXXXX" class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            </div>
                            <div>
                                <label for="visitor_id_photo" class="mb-2 block text-sm font-semibold text-slate-800">ID photo <span class="text-red-600">*</span></label>
                                <input type="file" id="visitor_id_photo" name="visitor_id_photo" accept="image/jpeg,image/png,image/webp" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                <p class="mt-1.5 text-xs text-slate-500">JPG, PNG, or WEBP. Maximum 2 MB. Camera capture is used where supported; otherwise browse for a saved photo.</p>
                                @error('visitor_id_photo')
                                    <p class="mt-1 text-xs text-red-700">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="sm:col-span-2">
                                <label for="relationship_to_pdl" class="mb-2 block text-sm font-semibold text-slate-800">Relationship to PDL <span class="text-red-600">*</span></label>
                                <select id="relationship_to_pdl" name="relationship_to_pdl" required class="w-full rounded-lg border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                    <option value="">Select relationship</option>
                                    <option value="Spouse" @selected(old('relationship_to_pdl') === 'Spouse')>Spouse</option>
                                    <option value="Parent" @selected(old('relationship_to_pdl') === 'Parent')>Parent</option>
                                    <option value="Child" @selected(old('relationship_to_pdl') === 'Child')>Child</option>
                                    <option value="Sibling" @selected(old('relationship_to_pdl') === 'Sibling')>Sibling</option>
                                    <option value="Relative" @selected(old('relationship_to_pdl') === 'Relative')>Relative</option>
                                    <option value="Friend" @selected(old('relationship_to_pdl') === 'Friend')>Friend</option>
                                    <option value="Legal Counsel" @selected(old('relationship_to_pdl') === 'Legal Counsel')>Legal Counsel</option>
                                    <option value="Others" @selected(old('relationship_to_pdl') === 'Others')>Others</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-4 border-t border-slate-200 pt-5">
                            <div class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-slate-800">Accompanying Visitors <span class="font-normal text-slate-500">(optional)</span></h3>
                                <button type="button" id="add-accompanying-visitor" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                    Add Visitor
                                </button>
                            </div>
                            <p class="text-xs text-slate-500">Add other people who will accompany you during the visit.</p>
                            <div id="accompanying-visitors-container" class="space-y-4"></div>
                        </div>

                        <section class="space-y-3 border-t border-base-300 pt-5" aria-labelledby="agreement-heading">
                            <div>
                                <h3 id="agreement-heading" class="text-sm font-semibold text-base-content">Required agreements</h3>
                                <p class="mt-1 text-xs leading-5 text-base-content/70">Please read each notice and check both boxes to continue. Your choices and timestamps are saved with this booking.</p>
                            </div>

                            <details class="collapse collapse-arrow border border-base-300 bg-base-100">
                                <summary class="collapse-title min-h-0 py-3 text-sm font-semibold">Terms &amp; Conditions</summary>
                                <div class="collapse-content text-sm leading-6 text-base-content/80">
                                    <p>Provide accurate visitor and identification information. A booking request is not approval to enter; the assigned facility must approve it first. Visitors must bring the valid ID entered in this request, follow facility security instructions and visitation schedules, and not bring prohibited items. Entry remains subject to facility rules and security screening. Contact the facility promptly if the visitor or schedule details need correction.</p>
                                </div>
                            </details>

                            <details class="collapse collapse-arrow border border-base-300 bg-base-100">
                                <summary class="collapse-title min-h-0 py-3 text-sm font-semibold">Data Privacy Agreement (DPA) &amp; Privacy Notice (RA 10173)</summary>
                                <div class="collapse-content space-y-2 text-sm leading-6 text-base-content/80">
                                    <p><strong>Personal Information Controller:</strong> Bureau of Jail Management and Penology (BJMP), through the facility handling this visit request.</p>
                                    <p><strong>Information collected:</strong> visitor name and contact details, optional email, ID type and number, relationship to the PDL, selected PDL, facility, visit schedule, consent records, and booking/status history.</p>
                                    <p><strong>Purpose and use:</strong> process and verify visitation requests, manage visitor queues, coordinate with the selected correctional facility, enforce facility security protocols, and maintain required operational and audit records.</p>
                                    <p><strong>Access and retention:</strong> authorized BJMP personnel assigned to the relevant facility may access the information. It may be disclosed to competent authorities when required by law. Records are retained under the applicable BJMP records-retention and security schedules, then securely disposed of when no longer required.</p>
                                    <p><strong>Your rights and contact:</strong> subject to applicable legal exceptions, you may request access or correction, raise an objection or privacy concern, and exercise other rights under RA 10173 by contacting BJMP through <a class="link link-primary" href="mailto:smartdalaw@bjmp.gov.ph">smartdalaw@bjmp.gov.ph</a>. You may also raise concerns with the National Privacy Commission.</p>
                                    <p>This notice version is recorded with your consent. Consent logs support accountability and do not represent an ISO certification claim.</p>
                                </div>
                            </details>

                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 bg-base-100 p-3 text-sm">
                                <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" @checked(old('terms_accepted')) required class="checkbox checkbox-primary checkbox-sm mt-0.5">
                                <span>I have read and agree to the Terms &amp; Conditions. <span class="text-error">*</span></span>
                            </label>
                            <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-base-300 bg-base-100 p-3 text-sm">
                                <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" @checked(old('privacy_consent')) required class="checkbox checkbox-primary checkbox-sm mt-0.5">
                                <span>I acknowledge the Privacy Notice and consent to the processing described above for this visitation request. <span class="text-error">*</span></span>
                            </label>
                        </section>

                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
                            <button type="button" data-go-step="1" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to schedule</button>
                            <button type="button" data-go-step="3" class="btn btn-primary min-h-11">Review booking</button>
                        </div>
                    </section>

                    <section data-booking-step="3" class="hidden space-y-6" aria-labelledby="review-heading">
                        <div>
                            <p class="text-xs font-semibold text-[#003366]">STEP 3 OF 3</p>
                            <h2 id="review-heading" class="mt-1 text-xl font-bold text-slate-900">Review your visit</h2>
                            <p class="mt-1 text-sm text-slate-600">Check your details before sending the request for review.</p>
                        </div>
                        <dl class="divide-y divide-slate-200 rounded-lg border border-slate-200 px-4">
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Facility</dt><dd id="review_facility" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Dormitory</dt><dd id="review_dormitory" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Date &amp; time</dt><dd id="review_schedule" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Person you are visiting</dt><dd id="review_pdl" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Visitor</dt><dd id="review_visitor" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Valid ID</dt><dd id="review_id" class="text-sm font-medium text-slate-900"></dd></div>
                            <div class="flex flex-col gap-1 py-3 sm:flex-row sm:justify-between"><dt class="text-xs text-slate-500">Agreements</dt><dd class="text-sm font-medium text-slate-900">Terms and Privacy Notice acknowledged</dd></div>
                        </dl>
                        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">Your request will be marked pending until it is reviewed by the facility administrator. Save the reference code shown after submission.</div>
                        <div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:justify-between">
                            <button type="button" data-go-step="2" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to details</button>
                            <button type="submit" class="btn btn-primary min-h-11">Submit booking request</button>
                        </div>
                    </section>
                </form>
            </div>

            <aside class="rounded-lg border border-slate-200 bg-white p-5">
                <p class="text-xs font-semibold uppercase text-[#0A2472]">Before you visit</p>
                <h2 class="mt-2 text-lg font-bold text-slate-900">Bring these with you</h2>
                <ul class="mt-4 grid gap-3 text-sm text-slate-600">
                    <li class="border-t border-slate-100 pt-3">A valid government-issued ID</li>
                    <li class="border-t border-slate-100 pt-3">Your booking reference or QR code</li>
                    <li class="border-t border-slate-100 pt-3">Arrive 15 minutes before your slot</li>
                </ul>

                <h3 class="mt-6 text-sm font-bold text-slate-900">Visiting hours</h3>
                <dl class="mt-3 grid gap-3 text-sm text-slate-600">
                    <div class="border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-800">Monday</dt><dd>No visitation</dd></div>
                    <div class="border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-800">Tuesday – Friday</dt><dd>1:00 PM – 5:00 PM</dd></div>
                    <div class="border-t border-slate-100 pt-3"><dt class="font-semibold text-slate-800">Saturday – Sunday</dt><dd>8:00 AM – 12:00 PM<br>1:00 PM – 5:00 PM</dd></div>
                </dl>

                <a href="{{ route('home') }}#requirements" class="mt-5 inline-block text-sm font-semibold text-[#0A2472] underline decoration-blue-200 underline-offset-4">Read all visitation requirements</a>
            </aside>
        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // Visiting schedule (from the server). Keys: 0 = Sunday ... 6 = Saturday.
        const VISITING_SCHEDULE = @json($visitingSchedule);
        const DORMITORIES_BY_FACILITY = @json($dormitoryOptions);
        const MIN_VISIT_DATE = @json($minVisitDate); // earliest bookable date (tomorrow)
        const PREVIOUS_TIME_SLOT = @json(old('time_slot'));
        const PREVIOUS_DORMITORY = @json(old('dormitory_id'));
        const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        const bookingForm = document.getElementById('bookingForm');
        const bookingSteps = [...document.querySelectorAll('[data-booking-step]')];
        const bookingIndicators = [...document.querySelectorAll('[data-step-indicator]')];
        const facilitySelect = document.getElementById('facility_id');
        const facilitySearch = document.getElementById('facility_search');
        const dormitorySelect = document.getElementById('dormitory_id');
        const dormitoryBrandPanel = document.getElementById('dormitory-brand-panel');
        const dormitoryBadgeImage = document.getElementById('dormitory-badge-image');
        const dormitoryBrandName = document.getElementById('dormitory-brand-name');
        const visitDateInput = document.getElementById('visit_date');
        const visitDateError = document.getElementById('visit_date_error');
        const timeSlotSelect = document.getElementById('time_slot');
        const timeSlotHint = document.getElementById('time_slot_hint');
        const availabilityPanel = document.getElementById('slotAvailability');
        const availabilityMessage = document.getElementById('availabilityMessage');
        const facilityBrandPanel = document.getElementById('facility-brand-panel');
        const facilityBadgeImage = document.getElementById('facility-badge-image');
        const facilityBadgeFallback = document.getElementById('facility-badge-fallback');
        const accompanyingVisitorsContainer = document.getElementById('accompanying-visitors-container');
        const addAccompanyingVisitorBtn = document.getElementById('add-accompanying-visitor');
        let activeStep = Number(document.getElementById('booking-errors')?.dataset.step || 1);
        let accompanyingVisitorCount = 0;
        let syncedFacilityId = null;

        function showBookingStep(step) {
            activeStep = step;

            bookingSteps.forEach((section) => {
                section.classList.toggle('hidden', Number(section.dataset.bookingStep) !== step);
            });

            bookingIndicators.forEach((indicator) => {
                const isActive = Number(indicator.dataset.stepIndicator) === step;
                indicator.classList.toggle('border-[#003366]', isActive);
                indicator.classList.toggle('bg-blue-50', isActive);
                indicator.classList.toggle('text-[#003366]', isActive);
                indicator.classList.toggle('font-semibold', isActive);
                indicator.classList.toggle('border-transparent', !isActive);
                indicator.classList.toggle('text-slate-500', !isActive);
                indicator.classList.toggle('font-medium', !isActive);
            });

            if (step === 3) {
                updateBookingReview();
            }

            document.getElementById('booking-progress').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function validateActiveStep() {
            const activeSection = bookingSteps.find((section) => Number(section.dataset.bookingStep) === activeStep);
            const invalidField = [...activeSection.querySelectorAll('input, select')].find((field) => !field.checkValidity());

            if (invalidField) {
                invalidField.reportValidity();
                return false;
            }

            return true;
        }

        function selectedLabel(select) {
            return select.options[select.selectedIndex]?.textContent.trim() || '';
        }

        function updateFacilityBrand() {
            const selectedFacility = facilitySelect.options[facilitySelect.selectedIndex];

            if (!facilitySelect.value) {
                facilityBrandPanel.classList.add('hidden');
                return;
            }

            facilityBrandPanel.classList.remove('hidden');
            facilityBrandPanel.classList.add('flex');
            document.getElementById('facility-brand-name').textContent = selectedLabel(facilitySelect);
            document.getElementById('facility-brand-regional').textContent = selectedFacility.dataset.regionalOffice || 'Bureau of Jail Management and Penology';
            document.getElementById('facility-brand-unit').textContent = selectedFacility.dataset.jailUnit || '';

            if (selectedFacility.dataset.badgeUrl) {
                facilityBadgeImage.src = selectedFacility.dataset.badgeUrl;
                facilityBadgeImage.classList.remove('hidden');
                facilityBadgeFallback.classList.add('hidden');
            } else {
                facilityBadgeImage.removeAttribute('src');
                facilityBadgeImage.classList.add('hidden');
                facilityBadgeFallback.classList.remove('hidden');
            }
        }

        function updateDormitoryBrand() {
            const selectedDormitory = dormitorySelect.options[dormitorySelect.selectedIndex];

            if (!dormitorySelect.value) {
                dormitoryBrandPanel.classList.add('hidden');
                dormitoryBrandPanel.classList.remove('flex');
                return;
            }

            dormitoryBrandPanel.classList.remove('hidden');
            dormitoryBrandPanel.classList.add('flex');
            dormitoryBrandName.textContent = selectedLabel(dormitorySelect);

            if (selectedDormitory.dataset.badgeUrl) {
                dormitoryBadgeImage.src = selectedDormitory.dataset.badgeUrl;
                dormitoryBadgeImage.classList.remove('hidden');
            } else {
                dormitoryBadgeImage.removeAttribute('src');
                dormitoryBadgeImage.classList.add('hidden');
            }
        }

        function setDormitoryOptions(preferredDormitory = '') {
            const dormitories = DORMITORIES_BY_FACILITY[facilitySelect.value] || [];
            syncedFacilityId = facilitySelect.value;
            dormitorySelect.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = facilitySelect.value ? 'Select a dormitory' : 'Select a facility first';
            dormitorySelect.appendChild(placeholderOption);

            dormitories.forEach((dormitory) => {
                const option = document.createElement('option');
                option.value = dormitory.id;
                option.textContent = dormitory.name;
                option.dataset.badgeUrl = dormitory.badge_url || '';
                dormitorySelect.appendChild(option);
            });

            const preferred = String(preferredDormitory ?? '');
            dormitorySelect.value = dormitories.some((dormitory) => String(dormitory.id) === preferred)
                ? preferred
                : '';

            updateDormitoryBrand();
        }

        // Browsers can restore a facility value without firing "change"
        // (refresh, back navigation, autofill). Rebuild the dependent list
        // whenever the selected facility no longer matches the rendered one.
        function syncDormitoryOptions() {
            if (syncedFacilityId === facilitySelect.value
                && !(facilitySelect.value && dormitorySelect.options.length <= 1)) {
                return;
            }

            setDormitoryOptions(dormitorySelect.value || '');
        }

        function resetFacilityFilter() {
            for (const option of facilitySelect.options) {
                option.style.display = '';
            }
        }

        function updateBookingReview() {
            document.getElementById('review_facility').textContent = selectedLabel(facilitySelect);
            document.getElementById('review_dormitory').textContent = selectedLabel(dormitorySelect);
            document.getElementById('review_schedule').textContent = `${visitDateInput.value} · ${selectedLabel(timeSlotSelect)}`;
            document.getElementById('review_pdl').textContent = document.getElementById('pdl_name').value;
            document.getElementById('review_visitor').textContent = document.getElementById('visitor_full_name').value;
            document.getElementById('review_id').textContent = `${selectedLabel(document.getElementById('visitor_id_type'))} · ${document.getElementById('visitor_id_number').value}`;
        }

        document.querySelectorAll('[data-go-step]').forEach((button) => {
            button.addEventListener('click', () => {
                if (Number(button.dataset.goStep) > activeStep && !validateActiveStep()) {
                    return;
                }

                showBookingStep(Number(button.dataset.goStep));
            });
        });

        facilitySelect.addEventListener('change', () => {
            updateFacilityBrand();
            setDormitoryOptions();
            checkAvailability();
        });

        dormitorySelect.addEventListener('change', () => {
            updateDormitoryBrand();
            checkAvailability();
        });

        facilitySelect.addEventListener('focus', resetFacilityFilter);
        facilitySelect.addEventListener('mousedown', resetFacilityFilter);
        facilitySelect.addEventListener('pointerdown', resetFacilityFilter);
        dormitorySelect.addEventListener('focus', syncDormitoryOptions);
        dormitorySelect.addEventListener('mousedown', syncDormitoryOptions);
        dormitorySelect.addEventListener('pointerdown', syncDormitoryOptions);

        window.addEventListener('pageshow', () => {
            updateFacilityBrand();
            syncDormitoryOptions();
        });

        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                syncDormitoryOptions();
            }
        });

        /* ------------------------------------------------------------------
         * Date & time-slot rules
         * ------------------------------------------------------------------ */

        // Parse "YYYY-MM-DD" as a local date (avoids UTC off-by-one-day bugs).
        function dayOfWeek(dateString) {
            const [year, month, day] = dateString.split('-').map(Number);
            return new Date(year, month - 1, day).getDay();
        }

        function showDateError(message) {
            visitDateError.textContent = message;
            visitDateError.classList.toggle('hidden', !message);
        }

        // Rebuild the time-slot dropdown so only valid options exist.
        function setTimeSlotOptions(slots, placeholder, keepValue = '') {
            timeSlotSelect.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = placeholder;
            timeSlotSelect.appendChild(placeholderOption);

            slots.forEach((slot) => {
                const option = document.createElement('option');
                option.value = slot;
                option.textContent = slot;
                timeSlotSelect.appendChild(option);
            });

            timeSlotSelect.value = slots.includes(keepValue) ? keepValue : '';
        }

        // Applies the schedule for the currently chosen date.
        function applyDateRules(preferredSlot = '') {
            const chosenDate = visitDateInput.value;
            showDateError('');
            visitDateInput.setCustomValidity('');

            if (!chosenDate) {
                setTimeSlotOptions([], 'Select a visit date first');
                timeSlotHint.textContent = 'Available time slots depend on the day you choose.';
                return;
            }

            // Block today and past dates (string comparison works for YYYY-MM-DD).
            if (chosenDate < MIN_VISIT_DATE) {
                visitDateInput.value = '';
                showDateError('Today and past dates are not available. Please choose a future date.');
                setTimeSlotOptions([], 'Select a visit date first');
                timeSlotHint.textContent = 'Available time slots depend on the day you choose.';
                return;
            }

            const weekday = dayOfWeek(chosenDate);
            const slots = VISITING_SCHEDULE[weekday] || [];

            // Block days with no visitation (Monday).
            if (slots.length === 0) {
                visitDateInput.value = '';
                showDateError(`${DAY_NAMES[weekday]}s are non-visiting days. Please choose another date.`);
                setTimeSlotOptions([], 'Select a visit date first');
                timeSlotHint.textContent = 'Available time slots depend on the day you choose.';
                return;
            }

            const keep = preferredSlot || timeSlotSelect.value;
            setTimeSlotOptions(slots, 'Select a time slot', keep);
            timeSlotHint.textContent = `Showing available time slots for ${DAY_NAMES[weekday]}.`;
        }

        async function checkAvailability() {
            if (!facilitySelect.value || !dormitorySelect.value || !visitDateInput.value || !timeSlotSelect.value) {
                availabilityPanel.classList.add('hidden');
                return;
            }

            try {
                const response = await fetch('{{ route('api.check-availability') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        facility_id: facilitySelect.value,
                        dormitory_id: dormitorySelect.value,
                        visit_date: visitDateInput.value,
                        time_slot: timeSlotSelect.value,
                    }),
                });
                const availability = await response.json();

                if (!availability || typeof availability.available === 'undefined') {
                    availabilityPanel.classList.add('hidden');
                    return;
                }

                availabilityPanel.className = availability.available
                    ? 'rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3'
                    : 'rounded-lg border border-red-200 bg-red-50 px-4 py-3';
                availabilityMessage.className = availability.available ? 'text-sm text-emerald-800' : 'text-sm text-red-800';

                const online = availability.current_online_bookings ?? 0;
                const combined = availability.current_combined_bookings ?? 0;
                const maxOnline = availability.max_online_bookings_per_hour ?? 0;
                const maxCombined = availability.max_bookings_per_hour ?? 0;

                availabilityMessage.textContent = availability.available
                    ? `This slot is available. ${online} of ${maxOnline} online slots used, ${combined} of ${maxCombined} total.`
                    : `This slot is no longer available for online booking. ${online} of ${maxOnline} online slots and ${combined} of ${maxCombined} total slots are used.`;
            } catch {
                availabilityPanel.classList.add('hidden');
            }
        }

        visitDateInput.addEventListener('change', () => {
            applyDateRules();
            checkAvailability();
        });
        timeSlotSelect.addEventListener('change', checkAvailability);

        // Real-time facility search
        facilitySearch.addEventListener('input', () => {
            const searchTerm = facilitySearch.value.toLowerCase();
            const options = facilitySelect.options;
            for (let i = 1; i < options.length; i++) {
                const option = options[i];
                const facilityName = option.dataset.name || option.text;
                option.style.display = facilityName.toLowerCase().includes(searchTerm) ? '' : 'none';
            }
        });

        // Accompanying visitors functionality
        function createAccompanyingVisitorForm(index) {
            const div = document.createElement('div');
            div.className = 'rounded-lg border border-slate-200 bg-slate-50 p-4';
            div.dataset.accompanyingIndex = index;
            div.innerHTML = `
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-800">Visitor ${index + 1}</h4>
                    <button type="button" class="remove-accompanying-visitor text-sm text-red-600 hover:text-red-800" data-index="${index}">Remove</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Full name <span class="text-red-600">*</span></label>
                        <input type="text" name="accompanying_visitors[${index}][full_name]" required placeholder="Full name" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Age <span class="text-red-600">*</span></label>
                        <input type="number" name="accompanying_visitors[${index}][age]" required min="1" max="120" placeholder="25" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Sex <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][sex]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Type of valid ID <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][id_type]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select ID type</option>
                            <option value="PhilSys National ID">PhilSys National ID</option>
                            <option value="Driver's License">Driver's License</option>
                            <option value="Passport">Passport</option>
                            <option value="UMID">UMID</option>
                            <option value="Postal ID">Postal ID</option>
                            <option value="Voter's ID">Voter's ID</option>
                            <option value="SSS ID">SSS ID</option>
                            <option value="GSIS ID">GSIS ID</option>
                            <option value="PhilHealth ID">PhilHealth ID</option>
                            <option value="Senior Citizen ID">Senior Citizen ID</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID number <span class="text-red-600">*</span></label>
                        <input type="text" name="accompanying_visitors[${index}][id_number]" required placeholder="XXXXX-XXXXX" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID photo <span class="text-red-600">*</span></label>
                        <input type="file" name="accompanying_visitors[${index}][id_photo]" accept="image/jpeg,image/png,image/webp" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Relationship to PDL <span class="text-red-600">*</span></label>
                        <select name="accompanying_visitors[${index}][relationship_to_pdl]" required class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 focus:border-[#003366] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select relationship</option>
                            <option value="Spouse">Spouse</option>
                            <option value="Parent">Parent</option>
                            <option value="Child">Child</option>
                            <option value="Sibling">Sibling</option>
                            <option value="Relative">Relative</option>
                            <option value="Friend">Friend</option>
                            <option value="Legal Counsel">Legal Counsel</option>
                            <option value="Others">Others</option>
                        </select>
                    </div>
                </div>
            `;
            return div;
        }

        addAccompanyingVisitorBtn.addEventListener('click', () => {
            const form = createAccompanyingVisitorForm(accompanyingVisitorCount);
            accompanyingVisitorsContainer.appendChild(form);
            accompanyingVisitorCount++;
        });

        accompanyingVisitorsContainer.addEventListener('click', (e) => {
            if (e.target.classList.contains('remove-accompanying-visitor')) {
                const index = e.target.dataset.index;
                const form = accompanyingVisitorsContainer.querySelector(`[data-accompanying-index="${index}"]`);
                if (form) {
                    form.remove();
                }
            }
        });

        // Fetch the token bound to the current session before submitting. A page
        // left open past the session lifetime would otherwise fail with 419.
        async function refreshCsrfToken() {
            try {
                const response = await fetch('{{ route('csrf-token') }}', {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store',
                });

                if (!response.ok) {
                    return;
                }

                const payload = await response.json();

                if (payload.token) {
                    bookingForm.querySelector('input[name="_token"]').value = payload.token;
                }
            } catch {
                // Fall back to the token already rendered in the form.
            }
        }

        let isSubmitting = false;

        // Final safety net: never submit an invalid date/slot combination.
        bookingForm.addEventListener('submit', async (event) => {
            const chosenDate = visitDateInput.value;
            const slots = chosenDate ? (VISITING_SCHEDULE[dayOfWeek(chosenDate)] || []) : [];

            if (!chosenDate || chosenDate < MIN_VISIT_DATE || !slots.includes(timeSlotSelect.value)) {
                event.preventDefault();
                applyDateRules();
                showBookingStep(1);
                (visitDateInput.value ? timeSlotSelect : visitDateInput).reportValidity();
                return;
            }

            event.preventDefault();

            if (isSubmitting) {
                return;
            }

            isSubmitting = true;
            await refreshCsrfToken();
            bookingForm.submit();
        });

        // Initial state (also restores old input after a failed server validation).
        if (facilitySelect.value) {
            updateFacilityBrand();
        }

        setDormitoryOptions(PREVIOUS_DORMITORY || '');
        applyDateRules(PREVIOUS_TIME_SLOT || '');
        showBookingStep(activeStep);
        checkAvailability();
    </script>
@endpush