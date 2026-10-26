@extends('layouts.portal')

@section('title', 'Booking Status | SmartDalaw')

@section('content')
    <section class="mx-auto max-w-4xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <div class="mb-7">
            <p class="text-xs font-semibold uppercase text-[#0A2472]">My bookings</p>
            <h1 class="mt-2 text-3xl font-bold text-[#102044]">Booking status</h1>
        </div>

        <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-col gap-4 border-b border-slate-100 pb-5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-medium text-slate-500">Reference number</p>
                    <p class="mt-1 break-all text-xl font-bold text-[#0A2472]">{{ $booking->reference_code }}</p>
                </div>
                @if($booking->status === 'Approved')
                    <span class="w-fit rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800">Confirmed</span>
                @elseif($booking->status === 'Rejected')
                    <span class="w-fit rounded-full bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-800">Rejected</span>
                @else
                    <span class="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Pending review</span>
                @endif
            </div>

            <dl class="grid gap-x-8 sm:grid-cols-2">
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Facility</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->facility->name }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Dormitory</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->dormitory?->name ?? 'Not assigned' }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Person you are visiting</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->pdl_name }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Visitor</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Visit date</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visit_date->format('M d, Y') }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Time</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->time_slot }}</dd></div>
                <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Submitted</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->created_at->format('M d, Y g:i A') }}</dd></div>
            </dl>

            @if($booking->accompanyingVisitors->count() > 0)
                <div class="mt-4 border-t border-slate-100 pt-4">
                    <dt class="text-xs text-slate-500">Accompanying Visitors ({{ $booking->accompanyingVisitors->count() }})</dt>
                    <dd class="mt-2 space-y-2">
                        @foreach($booking->accompanyingVisitors as $visitor)
                            <div class="rounded-md bg-slate-50 p-3 text-sm">
                                <p class="font-medium text-slate-900">{{ $visitor->full_name }}</p>
                                <p class="mt-1 text-xs text-slate-600">{{ $visitor->age }} years old · {{ $visitor->sex }} · {{ $visitor->relationship_to_pdl }}</p>
                            </div>
                        @endforeach
                    </dd>
                </div>
            @endif

            @include('bookings.partials.id-photos', ['booking' => $booking])
        </article>

        @include('bookings.partials.status-history', ['booking' => $booking])

        @if($booking->status === 'Approved')
            <div class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm leading-6 text-emerald-900" role="status"><strong>Visit approved.</strong> Bring a valid ID matching your booking and arrive at the facility on time.</div>
        @elseif($booking->status === 'Rejected')
            <div class="mt-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm leading-6 text-red-900" role="status"><strong>Visit not approved.</strong> Review any facility remarks above, or contact the facility if you have questions.</div>
        @else
            <div class="mt-5 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-950" role="status"><strong>Pending review.</strong> The facility administrator is reviewing your request. Check this page again for updates.</div>
        @endif

        <div class="mt-7 flex flex-col gap-3 sm:flex-row">
            <a href="{{ route('booking.success', ['code' => $booking->reference_code]) }}" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#0A2472] px-5 py-3 text-sm font-semibold text-white hover:bg-[#071D54]">View booking QR</a>
            <a href="{{ route('bookings.track') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-[#0A2472] hover:bg-blue-50">Track another booking</a>
        </div>
    </section>
@endsection