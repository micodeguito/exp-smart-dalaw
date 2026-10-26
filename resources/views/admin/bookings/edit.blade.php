@extends('layouts.admin')

@section('title', 'Edit Visit | SmartDalaw')

@section('content')
    @php
        $dormitoryOptions = $facilities->mapWithKeys(fn ($facility) => [
            $facility->id => $facility->dormitories->map(fn ($dormitory) => [
                'id' => $dormitory->id,
                'name' => $dormitory->name,
            ])->values(),
        ]);
    @endphp

    <div class="max-w-3xl">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Booking requests</a>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-[#0A2472]">{{ $booking->reference_code }}</p>
                <h1 class="mt-1 text-2xl font-bold text-[#102044]">Edit visit</h1>
            </div>
            <span class="w-fit rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-[#0A2472]">Current status: {{ $booking->status }}</span>
        </div>
        <p class="mt-2 text-sm text-slate-600">Update schedule or visitor details. Editing does not change the approval status.</p>

        @include('bookings.partials.status-history', ['booking' => $booking])

        <form action="{{ route('admin.bookings.update', $booking) }}" method="POST" enctype="multipart/form-data" class="mt-7 space-y-5 border-y border-slate-200 bg-white py-6">
            @csrf
            @method('PUT')

            <div>
                <label for="facility_id" class="mb-2 block text-sm font-semibold text-slate-800">Facility</label>
                <select id="facility_id" name="facility_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    @foreach($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected(old('facility_id', $booking->facility_id) == $facility->id)>{{ $facility->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="dormitory_id" class="mb-2 block text-sm font-semibold text-slate-800">Dormitory</label>
                <select id="dormitory_id" name="dormitory_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="">Select a facility first</option>
                </select>
            </div>

            <div>
                <label for="pdl_name" class="mb-2 block text-sm font-semibold text-slate-800">Person deprived of liberty (PDL) Name</label>
                <input type="text" id="pdl_name" name="pdl_name" value="{{ old('pdl_name', $booking->pdl_name) }}" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visit_date" class="mb-2 block text-sm font-semibold text-slate-800">Visit date</label>
                    <input type="date" id="visit_date" name="visit_date" value="{{ old('visit_date', $booking->visit_date->format('Y-m-d')) }}" min="{{ today()->addDay()->format('Y-m-d') }}" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="time_slot" class="mb-2 block text-sm font-semibold text-slate-800">Time slot</label>
                    <select id="time_slot" name="time_slot" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        @foreach(['8:00 AM - 9:00 AM', '9:00 AM - 10:00 AM', '10:00 AM - 11:00 AM', '1:00 PM - 2:00 PM', '2:00 PM - 3:00 PM', '3:00 PM - 4:00 PM'] as $timeSlot)
                            <option value="{{ $timeSlot }}" @selected(old('time_slot', $booking->time_slot) === $timeSlot)>{{ $timeSlot }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="visitor_full_name" class="mb-2 block text-sm font-semibold text-slate-800">Visitor full name</label>
                <input type="text" id="visitor_full_name" name="visitor_full_name" value="{{ old('visitor_full_name', $booking->visitor_full_name) }}" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_contact" class="mb-2 block text-sm font-semibold text-slate-800">Contact number</label>
                    <input type="tel" id="visitor_contact" name="visitor_contact" value="{{ old('visitor_contact', $booking->visitor_contact) }}" required maxlength="20" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_email" class="mb-2 block text-sm font-semibold text-slate-800">Email <span class="font-normal text-slate-500">(optional)</span></label>
                    <input type="email" id="visitor_email" name="visitor_email" value="{{ old('visitor_email', $booking->visitor_email) }}" maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_sex" class="mb-2 block text-sm font-semibold text-slate-800">
                        Sex
                    </label>

                    <select
                        id="visitor_sex"
                        name="visitor_sex"
                        required
                        class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100"
                    >
                        <option value="">Select sex</option>

                        <option value="Male"
                            @selected(old('visitor_sex', $booking->visitor_sex) === 'Male')>
                            Male
                        </option>

                        <option value="Female"
                            @selected(old('visitor_sex', $booking->visitor_sex) === 'Female')>
                            Female
                        </option>
                    </select>
                </div>
                <div>
                    <label for="visitor_age" class="mb-2 block text-sm font-semibold text-slate-800">Age</label>
                    <input type="number" id="visitor_age" name="visitor_age" value="{{ old('visitor_age', $booking->visitor_age) }}" required min="1" max="120" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_id_type" class="mb-2 block text-sm font-semibold text-slate-800">Type of valid ID</label>
                    <input type="text" id="visitor_id_type" name="visitor_id_type" value="{{ old('visitor_id_type', $booking->visitor_id_type) }}" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_id_number" class="mb-2 block text-sm font-semibold text-slate-800">ID number</label>
                    <input type="text" id="visitor_id_number" name="visitor_id_number" value="{{ old('visitor_id_number', $booking->visitor_id_number) }}" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div>
                <label for="relationship_to_pdl" class="mb-2 block text-sm font-semibold text-slate-800">Relationship to PDL</label>
                <input type="text" id="relationship_to_pdl" name="relationship_to_pdl" value="{{ old('relationship_to_pdl', $booking->relationship_to_pdl) }}" required maxlength="100" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="rounded-md border border-slate-200 bg-slate-50 p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-800">Primary visitor ID photo</h3>
                        <p class="mt-1 text-xs text-slate-500">The existing photo stays on file unless you choose a replacement.</p>
                    </div>
                    @if($booking->visitor_id_photo_path)
                        <a href="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[#0A2472] hover:underline">Open full size &rarr;</a>
                    @endif
                </div>

                @if($booking->visitor_id_photo_path)
                    <img src="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']) }}" alt="Current valid ID photo for {{ $booking->visitor_full_name }}" class="mt-3 h-56 w-full max-w-md rounded-md border border-slate-200 bg-white object-contain">
                @else
                    <p class="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">No ID photo is on file for this visitor.</p>
                @endif

                <div class="mt-3">
                    <label for="visitor_id_photo" class="mb-1.5 block text-xs font-semibold text-slate-700">Replace ID photo <span class="font-normal text-slate-500">(optional)</span></label>
                    <input type="file" id="visitor_id_photo" name="visitor_id_photo" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-[#0A2472] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, or WEBP up to 2 MB. Leave empty to keep the current photo.</p>
                </div>
            </div>

            <div class="space-y-4 border-t border-slate-200 pt-5">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-slate-800">Accompanying Visitors <span class="font-normal text-slate-500">(optional)</span></h3>
                    <button type="button" id="add-accompanying-visitor" class="inline-flex items-center gap-1.5 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Add Visitor
                    </button>
                </div>
                <p class="text-xs text-slate-500">Add other people who will accompany the visitor during the visit.</p>
                <div id="accompanying-visitors-container" class="space-y-4">
                    @foreach($booking->accompanyingVisitors as $index => $visitor)
                        <div class="rounded-md border border-slate-200 bg-slate-50 p-4" data-accompanying-index="{{ $index }}">
                            <input type="hidden" name="accompanying_visitors[{{ $index }}][id]" value="{{ $visitor->id }}">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-sm font-semibold text-slate-800">Visitor {{ $index + 1 }}</h4>
                                <button type="button" class="remove-accompanying-visitor text-sm text-red-600 hover:text-red-800" data-index="{{ $index }}">Remove</button>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Full name</label>
                                    <input type="text" name="accompanying_visitors[{{ $index }}][full_name]" value="{{ old("accompanying_visitors.{$index}.full_name", $visitor->full_name) }}" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Age</label>
                                    <input type="number" name="accompanying_visitors[{{ $index }}][age]" value="{{ old("accompanying_visitors.{$index}.age", $visitor->age) }}" required min="1" max="120" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Sex</label>
                                    <select name="accompanying_visitors[{{ $index }}][sex]" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                        <option value="">Select sex</option>
                                        <option value="Male" @selected(old("accompanying_visitors.{$index}.sex", $visitor->sex) === 'Male')">Male</option>
                                        <option value="Female" @selected(old("accompanying_visitors.{$index}.sex", $visitor->sex) === 'Female')">Female</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Type of valid ID</label>
                                    <input type="text" name="accompanying_visitors[{{ $index }}][id_type]" value="{{ old("accompanying_visitors.{$index}.id_type", $visitor->id_type) }}" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div>
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID number</label>
                                    <input type="text" name="accompanying_visitors[{{ $index }}][id_number]" value="{{ old("accompanying_visitors.{$index}.id_number", $visitor->id_number) }}" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="mb-1.5 block text-xs font-semibold text-slate-700">Relationship to PDL</label>
                                    <input type="text" name="accompanying_visitors[{{ $index }}][relationship_to_pdl]" value="{{ old("accompanying_visitors.{$index}.relationship_to_pdl", $visitor->relationship_to_pdl) }}" required maxlength="100" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                                </div>
                                <div class="sm:col-span-2 rounded-md border border-slate-200 bg-white p-3">
                                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                        <p class="text-xs font-semibold text-slate-700">ID photo</p>
                                        @if($visitor->id_photo_path)
                                            <a href="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $visitor->id]) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-[#0A2472] hover:underline">Open full size &rarr;</a>
                                        @endif
                                    </div>
                                    @if($visitor->id_photo_path)
                                        <img src="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $visitor->id]) }}" alt="Current valid ID photo for {{ $visitor->full_name }}" class="mt-2 h-40 w-full max-w-xs rounded-md border border-slate-200 bg-white object-contain">
                                    @else
                                        <p class="mt-2 text-xs text-amber-800">No ID photo is on file for this visitor.</p>
                                    @endif
                                    <label class="mb-1.5 mt-3 block text-xs font-semibold text-slate-700">Replace ID photo <span class="font-normal text-slate-500">(optional)</span></label>
                                    <input type="file" name="accompanying_visitors[{{ $index }}][id_photo]" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-[#0A2472] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                    <p class="mt-1 text-xs text-slate-500">JPG, PNG, or WEBP up to 2 MB. Leave empty to keep the current photo.</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Save booking</button>
            </div>
        </form>
    </div>

    <script>
        const DORMITORIES_BY_FACILITY = @json($dormitoryOptions);
        const PREVIOUS_DORMITORY = @json(old('dormitory_id', $booking->dormitory_id));

        const facilitySelect = document.getElementById('facility_id');
        const dormitorySelect = document.getElementById('dormitory_id');

        function setDormitoryOptions(preferred = '') {
            const dormitories = DORMITORIES_BY_FACILITY[facilitySelect.value] || [];
            dormitorySelect.innerHTML = '';

            const placeholderOption = document.createElement('option');
            placeholderOption.value = '';
            placeholderOption.textContent = facilitySelect.value ? 'Select a dormitory' : 'Select a facility first';
            dormitorySelect.appendChild(placeholderOption);

            dormitories.forEach((dormitory) => {
                const option = document.createElement('option');
                option.value = dormitory.id;
                option.textContent = dormitory.name;
                dormitorySelect.appendChild(option);
            });

            const preferredValue = String(preferred ?? '');
            dormitorySelect.value = dormitories.some((dormitory) => String(dormitory.id) === preferredValue)
                ? preferredValue
                : '';
        }

        facilitySelect.addEventListener('change', () => setDormitoryOptions());
        setDormitoryOptions(PREVIOUS_DORMITORY || '');

        const accompanyingVisitorsContainer = document.getElementById('accompanying-visitors-container');
        const addAccompanyingVisitorBtn = document.getElementById('add-accompanying-visitor');
        let accompanyingVisitorCount = {{ $booking->accompanyingVisitors->count() }};

        function createAccompanyingVisitorForm(index) {
            const div = document.createElement('div');
            div.className = 'rounded-md border border-slate-200 bg-slate-50 p-4';
            div.dataset.accompanyingIndex = index;
            div.innerHTML = `
                <div class="mb-3 flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-slate-800">Visitor ${index + 1}</h4>
                    <button type="button" class="remove-accompanying-visitor text-sm text-red-600 hover:text-red-800" data-index="${index}">Remove</button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Full name</label>
                        <input type="text" name="accompanying_visitors[${index}][full_name]" required maxlength="255" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Age</label>
                        <input type="number" name="accompanying_visitors[${index}][age]" required min="1" max="120" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Sex</label>
                        <select name="accompanying_visitors[${index}][sex]" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="">Select sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Type of valid ID</label>
                        <input type="text" name="accompanying_visitors[${index}][id_type]" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID number</label>
                        <input type="text" name="accompanying_visitors[${index}][id_number]" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Relationship to PDL</label>
                        <input type="text" name="accompanying_visitors[${index}][relationship_to_pdl]" required maxlength="100" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID photo <span class="font-normal text-slate-500">(optional)</span></label>
                        <input type="file" name="accompanying_visitors[${index}][id_photo]" accept="image/jpeg,image/png,image/webp" class="block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm file:mr-3 file:rounded file:border-0 file:bg-[#0A2472] file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
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
    </script>
@endsection