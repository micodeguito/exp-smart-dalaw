@extends('layouts.admin')

@section('title', 'Booking '.$booking->reference_code.' | SmartDalaw')

@section('content')
    <div class="max-w-5xl">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Booking requests</a>

        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-[#0A2472]">{{ $booking->reference_code }}</p>
                <h1 class="mt-1 text-2xl font-bold text-[#102044]">Booking details</h1>
                <p class="mt-2 text-sm text-slate-600">Review the visit request and the submitted valid IDs before changing its status.</p>
            </div>
            <div class="flex gap-2">
                @if($booking->status === 'Approved')
                    <span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">Approved</span>
                @elseif($booking->status === 'Rejected')
                    <span class="w-fit rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800">Rejected</span>
                @else
                    <span class="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Pending</span>
                @endif
                <a href="{{ route('admin.bookings.edit', $booking) }}" class="w-fit rounded-full bg-blue-50 px-3 py-1.5 text-xs font-semibold text-[#0A2472] hover:bg-blue-100">Edit</a>
            </div>
        </div>

        <div class="mt-6 grid gap-5 lg:grid-cols-[1.4fr_1fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">Visit information</h2>
                <dl class="mt-4 grid gap-x-8 sm:grid-cols-2">
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Facility</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->facility->name }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Dormitory</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->dormitory?->name ?? 'Not assigned' }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Person deprived of liberty</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->pdl_name }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Relationship to PDL</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->relationship_to_pdl }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Visitor</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Contact number</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_contact }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Email</dt><dd class="mt-1 break-all text-sm font-medium text-slate-900">{{ $booking->visitor_email ?: 'Not provided' }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Sex and age</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_sex }} · {{ $booking->visitor_age }} years old</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Valid ID</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_id_type }} · {{ $booking->visitor_id_number }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Booking channel</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->booking_channel === 'walk_in' ? 'Walk-in' : 'Online' }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Visit schedule</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visit_date->format('M d, Y') }} · {{ $booking->time_slot }}</dd></div>
                    <div class="border-b border-slate-100 py-3"><dt class="text-xs text-slate-500">Submitted</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->created_at->format('M d, Y g:i A') }}</dd></div>
                </dl>

                @if($booking->admin_remarks)
                    <div class="mt-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-900">
                        <p class="text-xs font-semibold uppercase">Rejection remarks</p>
                        <p class="mt-1">{{ $booking->admin_remarks }}</p>
                    </div>
                @endif
            </article>

            <aside class="h-fit rounded-lg border border-slate-200 bg-white p-5">
                <h2 class="text-sm font-semibold text-slate-900">Update status</h2>
                <p class="mt-1 text-xs leading-5 text-slate-500">A reason is required for Pending or Rejected changes and is saved in the booking history. The ID photos below are for verification only and do not change the decision automatically.</p>
                <form action="{{ route('admin.bookings.status.update', $booking) }}" method="POST" class="mt-4 space-y-4" id="details-status-form">
                    @csrf
                    <div>
                        <label for="details-status" class="mb-2 block text-sm font-semibold text-slate-800">New status</label>
                        <select id="details-status" name="status" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <option value="" selected disabled>Choose a status</option>
                            @foreach(['Pending', 'Approved', 'Rejected'] as $status)
                                @continue($status === $booking->status)
                                <option value="{{ $status }}">{{ $status }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="details-reason-field">
                        <label for="details-reason" class="mb-2 block text-sm font-semibold text-slate-800">Reason for change</label>
                        <textarea id="details-reason" name="reason" maxlength="500" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="Explain why this status is changing"></textarea>
                        <p class="mt-1 text-xs text-slate-500">Required when setting the status to Pending or Rejected.</p>
                    </div>
                    <button type="submit" class="inline-flex min-h-10 w-full items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Save status</button>
                </form>

                <script>
                    const detailsStatusSelect = document.getElementById('details-status');
                    const detailsReasonField = document.getElementById('details-reason-field');
                    const detailsReasonInput = document.getElementById('details-reason');

                    function syncDetailsReasonField() {
                        const requiresReason = ['Pending', 'Rejected'].includes(detailsStatusSelect.value);
                        detailsReasonField.classList.toggle('hidden', detailsStatusSelect.value === 'Approved');
                        detailsReasonInput.required = requiresReason;

                        if (!requiresReason) {
                            detailsReasonInput.value = '';
                        }
                    }

                    detailsStatusSelect.addEventListener('change', syncDetailsReasonField);
                    syncDetailsReasonField();
                </script>
            </aside>
        </div>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white p-5" aria-labelledby="id-photo-heading">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 id="id-photo-heading" class="text-sm font-semibold text-slate-900">Submitted valid ID photos</h2>
                    <p class="mt-1 text-xs text-slate-500">Compare each photo with the visitor named on it. Open a photo in a new tab to inspect it at full size.</p>
                </div>
                <span class="text-xs text-slate-500">{{ 1 + $booking->accompanyingVisitors->count() }} {{ Str::plural('visitor', 1 + $booking->accompanyingVisitors->count()) }}</span>
            </div>

            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                <figure class="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <figcaption class="mb-3">
                        <p class="text-sm font-semibold text-slate-900">{{ $booking->visitor_full_name }} <span class="font-normal text-slate-500">(primary visitor)</span></p>
                        <p class="mt-0.5 text-xs text-slate-600">{{ $booking->visitor_id_type }} · {{ $booking->visitor_id_number }}</p>
                    </figcaption>
                    @if($booking->visitor_id_photo_path)
                        <img src="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']) }}" alt="Valid ID photo for {{ $booking->visitor_full_name }}" class="h-72 w-full rounded-md border border-slate-200 bg-white object-contain">
                        <a href="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-xs font-semibold text-[#0A2472] hover:underline">Open full size &rarr;</a>
                    @else
                        <p class="rounded-md bg-amber-50 p-4 text-sm text-amber-900">No ID photo is on file for this visitor.</p>
                    @endif
                </figure>

                @foreach($booking->accompanyingVisitors as $visitor)
                    <figure class="rounded-md border border-slate-200 bg-slate-50 p-3">
                        <figcaption class="mb-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $visitor->full_name }} <span class="font-normal text-slate-500">(accompanying visitor)</span></p>
                            <p class="mt-0.5 text-xs text-slate-600">{{ $visitor->id_type }} · {{ $visitor->id_number }}</p>
                        </figcaption>
                        @if($visitor->id_photo_path)
                            <img src="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $visitor->id]) }}" alt="Valid ID photo for {{ $visitor->full_name }}" class="h-72 w-full rounded-md border border-slate-200 bg-white object-contain">
                            <a href="{{ route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $visitor->id]) }}" target="_blank" rel="noopener" class="mt-3 inline-block text-xs font-semibold text-[#0A2472] hover:underline">Open full size &rarr;</a>
                        @else
                            <p class="rounded-md bg-amber-50 p-4 text-sm text-amber-900">No ID photo is on file for this visitor.</p>
                        @endif
                    </figure>
                @endforeach
            </div>
        </section>

        @include('bookings.partials.status-history', ['booking' => $booking])
    </div>
@endsection
