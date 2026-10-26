@extends('layouts.admin')

@section('title', 'Facility Settings | SmartDalaw')

@section('content')
    <div>
        <p class="text-xs font-semibold uppercase text-[#0A2472]">Visitation capacity</p>
        <h1 class="mt-1 text-2xl font-bold text-[#102044]">Facility settings</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-600">Each dormitory has its own hourly limits. The online limit caps public bookings, while the combined limit caps online and officer-recorded bookings together for the same visit date and hour.</p>
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

    <div class="mt-7 divide-y divide-slate-200 border-y border-slate-200 bg-white">
        @forelse($facilities as $facility)
            <article class="grid gap-6 px-4 py-5 sm:px-5 lg:grid-cols-[minmax(14rem,0.7fr)_minmax(20rem,1.3fr)_minmax(18rem,1fr)]">
                <div class="flex min-w-0 items-start gap-3">
                    @if($facility->badge_image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($facility->badge_image_path) }}" alt="Official badge for {{ $facility->name }}" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                    @else
                        <img src="{{ asset('images/bjmp-logo.png') }}" alt="BJMP Official Seal" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                    @endif
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-900">{{ $facility->name }}</h2>
                        <p class="mt-1 text-xs text-slate-500">{{ $facility->regional_office ?: 'Regional office not set' }}</p>
                        <p class="text-xs text-slate-500">{{ $facility->jail_unit_name ?: 'Jail unit not set' }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <h3 class="text-xs font-semibold uppercase text-slate-500">Dormitories</h3>
                    @forelse($facility->dormitories as $dormitory)
                        <form action="{{ route('admin.facilities.dormitories.update', [$facility, $dormitory]) }}" method="POST" enctype="multipart/form-data" class="space-y-3 rounded-lg border border-slate-200 p-3">
                            @csrf
                            @method('PUT')
                            <div class="flex items-center gap-3">
                                @if($dormitory->badge_image_path)
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($dormitory->badge_image_path) }}" alt="Badge for {{ $dormitory->name }}" class="h-10 w-10 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                                @endif
                                <p class="font-semibold text-slate-900">{{ $dormitory->name }}</p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <label for="online-limit-{{ $dormitory->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Online bookings per hour</label>
                                    <input type="number" id="online-limit-{{ $dormitory->id }}" name="max_online_bookings_per_hour" value="{{ old('max_online_bookings_per_hour', $dormitory->max_online_bookings_per_hour) }}" min="1" max="10000" required class="input input-bordered input-sm w-full bg-base-100">
                                </div>
                                <div>
                                    <label for="combined-limit-{{ $dormitory->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Total bookings per hour</label>
                                    <input type="number" id="combined-limit-{{ $dormitory->id }}" name="max_bookings_per_hour" value="{{ old('max_bookings_per_hour', $dormitory->max_bookings_per_hour) }}" min="1" max="10000" required class="input input-bordered input-sm w-full bg-base-100">
                                </div>
                            </div>

                            <div>
                                <label for="dormitory-badge-{{ $dormitory->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Dormitory badge</label>
                                <input type="file" id="dormitory-badge-{{ $dormitory->id }}" name="badge_image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered file-input-sm w-full bg-base-100">
                                <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP. Maximum 2 MB.</p>
                            </div>

                            <p class="text-xs text-slate-500">Current limits: {{ $dormitory->max_online_bookings_per_hour }} online / {{ $dormitory->max_bookings_per_hour }} total per hour. The online limit cannot exceed the total limit.</p>
                            <button type="submit" class="btn btn-primary btn-sm">Save dormitory</button>
                        </form>
                    @empty
                        <p class="text-sm text-slate-500">No dormitories have been configured for this facility.</p>
                    @endforelse
                </div>

                <form action="{{ route('admin.facilities.branding.update', $facility) }}" method="POST" enctype="multipart/form-data" class="space-y-3 border-t border-base-300 pt-4 lg:border-l lg:border-t-0 lg:pl-5 lg:pt-0">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label for="regional-office-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Regional office (official name)</label>
                        <input type="text" id="regional-office-{{ $facility->id }}" name="regional_office" value="{{ old('regional_office', $facility->regional_office) }}" maxlength="150" placeholder="e.g. BJMP Regional Office No. III" class="input input-bordered input-sm w-full bg-base-100">
                    </div>
                    <div>
                        <label for="jail-unit-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Jail unit (official name)</label>
                        <input type="text" id="jail-unit-{{ $facility->id }}" name="jail_unit_name" value="{{ old('jail_unit_name', $facility->jail_unit_name) }}" maxlength="150" placeholder="Use the unit's approved designation" class="input input-bordered input-sm w-full bg-base-100">
                    </div>
                    <div>
                        <label for="badge-image-{{ $facility->id }}" class="mb-1 block text-xs font-semibold text-slate-700">Official facility badge</label>
                        <input type="file" id="badge-image-{{ $facility->id }}" name="badge_image" accept="image/png,image/jpeg,image/webp" class="file-input file-input-bordered file-input-sm w-full bg-base-100">
                        <p class="mt-1 text-xs text-slate-500">PNG, JPG or WebP. Maximum 2 MB. Upload the approved badge containing official insignia only.</p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Save facility identity</button>
                </form>
            </article>
        @empty
            <p class="px-4 py-10 text-center text-sm text-slate-500">No facilities have been configured.</p>
        @endforelse
    </div>
@endsection
