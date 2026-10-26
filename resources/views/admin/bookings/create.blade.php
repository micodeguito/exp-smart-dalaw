@extends('layouts.admin')

@section('title', 'Add Walk-in Visitor | SmartDalaw')

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
        <div class="mt-3">
            <h1 class="mt-1 text-2xl font-bold text-[#102044]">Add Walk-in Visitor</h1>
            <p class="mt-2 text-sm text-slate-600">Register a visitor who arrived at the facility without a prior booking. This will automatically approve the visit.</p>
        </div>

        @if($errors->any())
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <p class="font-semibold">Please correct the following errors:</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('admin.bookings.store') }}" method="POST" class="mt-7 space-y-5 border-y border-slate-200 bg-white py-6">
            @csrf

            <div>
                <label for="facility_id" class="mb-2 block text-sm font-semibold text-slate-800">Facility</label>
                <select id="facility_id" name="facility_id" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="">Select a facility</option>
                    @foreach($facilities as $facility)
                        <option value="{{ $facility->id }}" @selected(old('facility_id') == $facility->id)>{{ $facility->name }}</option>
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
                <input type="text" id="pdl_name" name="pdl_name" value="{{ old('pdl_name') }}" required maxlength="255" placeholder="Juan A. Dela Cruz" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visit_date" class="mb-2 block text-sm font-semibold text-slate-800">Visit date</label>
                    <input type="date" id="visit_date" name="visit_date" value="{{ old('visit_date') }}" min="{{ today()->format('Y-m-d') }}" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <p class="mt-1 text-xs text-slate-500">Can be today or a future date</p>
                </div>
                <div>
                    <label for="time_slot" class="mb-2 block text-sm font-semibold text-slate-800">Time slot</label>
                    <select id="time_slot" name="time_slot" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select a time slot</option>
                        @foreach(['8:00 AM - 9:00 AM', '9:00 AM - 10:00 AM', '10:00 AM - 11:00 AM', '1:00 PM - 2:00 PM', '2:00 PM - 3:00 PM', '3:00 PM - 4:00 PM'] as $timeSlot)
                            <option value="{{ $timeSlot }}" @selected(old('time_slot') === $timeSlot)>{{ $timeSlot }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label for="visitor_full_name" class="mb-2 block text-sm font-semibold text-slate-800">Visitor full name</label>
                <input type="text" id="visitor_full_name" name="visitor_full_name" value="{{ old('visitor_full_name') }}" required maxlength="255" placeholder="Juan A. Dela Cruz" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_contact" class="mb-2 block text-sm font-semibold text-slate-800">Contact number</label>
                    <input type="tel" id="visitor_contact" name="visitor_contact" value="{{ old('visitor_contact') }}" required maxlength="20" placeholder="09123456789" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
                <div>
                    <label for="visitor_email" class="mb-2 block text-sm font-semibold text-slate-800">Email <span class="font-normal text-slate-500">(optional)</span></label>
                    <input type="email" id="visitor_email" name="visitor_email" value="{{ old('visitor_email') }}" maxlength="255" placeholder="juan@example.com" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_sex" class="mb-2 block text-sm font-semibold text-slate-800">Sex</label>
                    <select id="visitor_sex" name="visitor_sex" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select sex</option>
                        <option value="Male" @selected(old('visitor_sex') === 'Male')">Male</option>
                        <option value="Female" @selected(old('visitor_sex') === 'Female')">Female</option>
                    </select>
                </div>
                <div>
                    <label for="visitor_age" class="mb-2 block text-sm font-semibold text-slate-800">Age</label>
                    <input type="number" id="visitor_age" name="visitor_age" value="{{ old('visitor_age') }}" required min="1" max="120" placeholder="25" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="visitor_id_type" class="mb-2 block text-sm font-semibold text-slate-800">Type of valid ID</label>
                    <select id="visitor_id_type" name="visitor_id_type" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="">Select ID type</option>
                        <option value="PhilSys National ID" @selected(old('visitor_id_type') === 'PhilSys National ID')">PhilSys National ID</option>
                        <option value="Driver's License" @selected(old('visitor_id_type') === "Driver's License")>Driver's License</option>
                        <option value="Passport" @selected(old('visitor_id_type') === 'Passport')">Passport</option>
                        <option value="UMID" @selected(old('visitor_id_type') === 'UMID')">UMID</option>
                        <option value="Postal ID" @selected(old('visitor_id_type') === 'Postal ID')">Postal ID</option>
                        <option value="Voter's ID" @selected(old('visitor_id_type') === "Voter's ID")>Voter's ID</option>
                        <option value="SSS ID" @selected(old('visitor_id_type') === 'SSS ID')">SSS ID</option>
                        <option value="GSIS ID" @selected(old('visitor_id_type') === 'GSIS ID')">GSIS ID</option>
                        <option value="PhilHealth ID" @selected(old('visitor_id_type') === 'PhilHealth ID')">PhilHealth ID</option>
                        <option value="Senior Citizen ID" @selected(old('visitor_id_type') === 'Senior Citizen ID')">Senior Citizen ID</option>
                        <option value="Others" @selected(old('visitor_id_type') === 'Others')">Others</option>
                    </select>
                </div>
                <div>
                    <label for="visitor_id_number" class="mb-2 block text-sm font-semibold text-slate-800">ID number</label>
                    <input type="text" id="visitor_id_number" name="visitor_id_number" value="{{ old('visitor_id_number') }}" required maxlength="50" placeholder="XXXXX-XXXXX" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                </div>
            </div>

            <div>
                <label for="relationship_to_pdl" class="mb-2 block text-sm font-semibold text-slate-800">Relationship to PDL</label>
                <select id="relationship_to_pdl" name="relationship_to_pdl" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="">Select relationship</option>
                    <option value="Spouse" @selected(old('relationship_to_pdl') === 'Spouse')">Spouse</option>
                    <option value="Parent" @selected(old('relationship_to_pdl') === 'Parent')">Parent</option>
                    <option value="Child" @selected(old('relationship_to_pdl') === 'Child')">Child</option>
                    <option value="Sibling" @selected(old('relationship_to_pdl') === 'Sibling')">Sibling</option>
                    <option value="Relative" @selected(old('relationship_to_pdl') === 'Relative')">Relative</option>
                    <option value="Friend" @selected(old('relationship_to_pdl') === 'Friend')">Friend</option>
                    <option value="Legal Counsel" @selected(old('relationship_to_pdl') === 'Legal Counsel')">Legal Counsel</option>
                    <option value="Others" @selected(old('relationship_to_pdl') === 'Others')">Others</option>
                </select>
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
                <div id="accompanying-visitors-container" class="space-y-4"></div>
            </div>

            <div class="flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:justify-end">
                <a href="{{ route('admin.dashboard') }}" class="inline-flex min-h-10 items-center justify-center rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                <button type="submit" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Register Walk-in Visitor</button>
            </div>
        </form>
    </div>

    <script>
        const DORMITORIES_BY_FACILITY = @json($dormitoryOptions);
        const PREVIOUS_DORMITORY = @json(old('dormitory_id'));

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
        let accompanyingVisitorCount = 0;

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
                        <select name="accompanying_visitors[${index}][id_type]" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
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
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">ID number</label>
                        <input type="text" name="accompanying_visitors[${index}][id_number]" required maxlength="50" class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="mb-1.5 block text-xs font-semibold text-slate-700">Relationship to PDL</label>
                        <select name="accompanying_visitors[${index}][relationship_to_pdl]" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
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
    </script>
@endsection
