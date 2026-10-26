@extends('layouts.admin')

@section('title', 'Admin Dashboard | SmartDalaw')

@section('content')
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-semibold uppercase text-[#0A2472]">Overview</p>
            <h1 class="mt-1 text-2xl font-bold text-[#102044]">Booking dashboard</h1>
            <p class="mt-2 text-sm text-slate-600">Review requests and monitor scheduled visits.</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('admin.daily-summary') }}" class="text-sm font-semibold text-[#0A2472] hover:underline">Daily summary &rarr;</a>
            <a href="{{ route('admin.calendar') }}" class="text-sm font-semibold text-[#0A2472] hover:underline">Visit calendar &rarr;</a>
        </div>
    </div>

    <section class="mt-6" aria-label="Booking analytics">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <article class="rounded-md border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium text-slate-500">Total bookings</p>
                <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $analytics['total'] }}</p>
                <p class="mt-1 text-xs text-slate-500">All booking requests</p>
            </article>
            <article class="rounded-md border border-amber-200 bg-white p-4">
                <p class="text-xs font-medium text-amber-800">Pending review</p>
                <p class="mt-2 text-2xl font-bold text-amber-900">{{ $analytics['pending'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Awaiting an admin decision</p>
            </article>
            <article class="rounded-md border border-emerald-200 bg-white p-4">
                <p class="text-xs font-medium text-emerald-800">Approved</p>
                <p class="mt-2 text-2xl font-bold text-emerald-900">{{ $analytics['approved'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Approved visit requests</p>
            </article>
            <article class="rounded-md border border-red-200 bg-white p-4">
                <p class="text-xs font-medium text-red-800">Rejected</p>
                <p class="mt-2 text-2xl font-bold text-red-900">{{ $analytics['rejected'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Rejected visit requests</p>
            </article>
            <article class="rounded-md border border-slate-200 bg-white p-4">
                <p class="text-xs font-medium text-slate-500">Visits today</p>
                <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $analytics['today'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Scheduled for today</p>
            </article>
            <article class="rounded-md border border-blue-200 bg-white p-4">
                <p class="text-xs font-medium text-[#0A2472]">Upcoming approved</p>
                <p class="mt-2 text-2xl font-bold text-[#0A2472]">{{ $analytics['upcoming'] }}</p>
                <p class="mt-1 text-xs text-slate-500">Approved visits from today onward</p>
            </article>
        </div>

        <div class="mt-4 border-y border-slate-200 bg-white px-4 py-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-slate-900">Request status distribution</h2>
                <span class="text-xs text-slate-500">{{ $analytics['total'] }} total</span>
            </div>
            <div class="flex h-2 overflow-hidden rounded-full bg-slate-100" aria-label="Booking status distribution">
                @foreach(['pending' => 'bg-amber-400', 'approved' => 'bg-emerald-500', 'rejected' => 'bg-red-500'] as $statusKey => $barColor)
                    @if($analytics[$statusKey] > 0)
                        <span class="{{ $barColor }}" style="width: {{ $analytics['total'] > 0 ? ($analytics[$statusKey] / $analytics['total']) * 100 : 0 }}%"></span>
                    @endif
                @endforeach
            </div>
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-600">
                <span>Pending {{ $analytics['pending'] }}</span>
                <span>Approved {{ $analytics['approved'] }}</span>
                <span>Rejected {{ $analytics['rejected'] }}</span>
            </div>
        </div>
    </section>

    <section class="mt-9" aria-labelledby="booking-requests-heading">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 id="booking-requests-heading" class="text-lg font-bold text-[#102044]">Booking requests</h2>
                <p class="mt-1 text-sm text-slate-600">Filter and update visit requests.</p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <a href="{{ route('admin.bookings.create') }}" class="inline-flex min-h-10 items-center justify-center rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Add Walk-in Visitor</a>
                <form action="{{ route('admin.dashboard') }}" method="GET" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select name="status" aria-label="Filter by status" class="min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                    <option value="">All statuses</option>
                    <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
                    <option value="Approved" @selected(request('status') === 'Approved')>Approved</option>
                    <option value="Rejected" @selected(request('status') === 'Rejected')>Rejected</option>
                </select>
                <input type="date" name="date" value="{{ request('date') }}" aria-label="Filter by visit date" class="min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <button type="submit" class="min-h-10 rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Apply filters</button>
                @if(request()->hasAny(['status', 'date']))
                    <a href="{{ route('admin.dashboard') }}" class="min-h-10 rounded-md px-3 py-2 text-center text-sm font-medium text-slate-600 hover:bg-slate-100">Clear</a>
                @endif
            </form>
            </div>
        </div>

        <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Reference</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Visitor</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">PDL</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Facility</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Dormitory</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Visit</th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Status</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($bookings as $booking)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-4 font-mono text-xs font-semibold text-[#0A2472]">
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="hover:underline">{{ $booking->reference_code }}</a>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $booking->visitor_contact }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm text-slate-800">{{ $booking->pdl_name }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->facility->name }}</td>
                            <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->dormitory?->name ?? 'Not assigned' }}</td>
                            <td class="whitespace-nowrap px-4 py-4">
                                <p class="text-sm text-slate-800">{{ $booking->visit_date->format('M d, Y') }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $booking->time_slot }}</p>
                            </td>
                            <td class="whitespace-nowrap px-4 py-4">
                                @if($booking->status === 'Approved')
                                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800">Approved</span>
                                @elseif($booking->status === 'Rejected')
                                    <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800">Rejected</span>
                                @else
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Pending</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-4 text-right text-xs font-semibold">
                                <a href="{{ route('admin.bookings.show', $booking) }}" class="text-[#0A2472] hover:underline">View</a>
                                <a href="{{ route('admin.bookings.edit', $booking) }}" class="ml-3 text-[#0A2472] hover:underline">Edit</a>
                                <button type="button" data-status-booking="{{ $booking->id }}" data-current-status="{{ $booking->status }}" class="ml-3 text-slate-700 hover:text-[#0A2472] hover:underline">Change status</button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No booking requests found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($bookings->hasPages())
            <div class="mt-5">{{ $bookings->links() }}</div>
        @endif
    </section>

    <div id="statusModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="status-title">
        <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-5 shadow-xl">
            <h2 id="status-title" class="text-lg font-semibold text-slate-900">Change booking status</h2>
            <p class="mt-1 text-sm text-slate-600">A reason is required for Pending or Rejected changes and is saved in the booking history.</p>
            <form action="" method="POST" id="statusForm" class="mt-5">
                @csrf
                <p class="mb-3 text-xs text-slate-500">Current status: <span id="current-booking-status" class="font-semibold text-slate-800"></span></p>
                <div>
                    <label for="status" class="mb-2 block text-sm font-semibold text-slate-800">New status</label>
                    <select id="status" name="status" required class="w-full rounded-md border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                        <option value="" selected disabled>Choose a status</option>
                        <option value="Pending">Pending</option>
                        <option value="Approved">Approved</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div id="status-reason-field" class="mt-4 hidden">
                    <label for="status_reason" class="mb-2 block text-sm font-semibold text-slate-800">Reason for change</label>
                    <textarea id="status_reason" name="reason" maxlength="500" rows="4" class="w-full rounded-md border border-slate-300 px-3 py-2.5 text-sm focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100" placeholder="Explain why this status is changing"></textarea>
                    <p class="mt-1 text-xs text-slate-500">Required when setting the status to Pending or Rejected.</p>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" onclick="closeStatusModal()" class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-md bg-[#0A2472] px-3 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">Save status</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const statusForm = document.getElementById('statusForm');
        const statusSelect = document.getElementById('status');
        const statusReasonField = document.getElementById('status-reason-field');
        const statusReasonInput = document.getElementById('status_reason');

        function statusRequiresReason() {
            return ['Pending', 'Rejected'].includes(statusSelect.value);
        }

        function syncStatusReasonField() {
            const requiresReason = statusRequiresReason();
            statusReasonField.classList.toggle('hidden', !requiresReason);
            statusReasonInput.required = requiresReason;

            if (!requiresReason) {
                statusReasonInput.value = '';
            }
        }

        function openStatusModal(bookingId, currentStatus) {
            statusForm.action = '{{ route('admin.bookings.status.update', ':id') }}'.replace(':id', bookingId);
            document.getElementById('current-booking-status').textContent = currentStatus;
            [...statusSelect.options].forEach((option) => {
                option.disabled = option.value === currentStatus;
            });
            statusSelect.value = '';
            statusReasonInput.value = '';
            syncStatusReasonField();
            document.getElementById('statusModal').classList.remove('hidden');
            document.getElementById('statusModal').classList.add('flex');
            statusSelect.focus();
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
            document.getElementById('statusModal').classList.remove('flex');
            statusReasonInput.value = '';
            statusReasonInput.required = false;
            statusReasonField.classList.add('hidden');
        }

        statusSelect.addEventListener('change', syncStatusReasonField);

        document.querySelectorAll('[data-status-booking]').forEach((button) => {
            button.addEventListener('click', () => {
                openStatusModal(button.dataset.statusBooking, button.dataset.currentStatus);
            });
        });

        document.getElementById('statusModal').addEventListener('click', (event) => {
            if (event.target === event.currentTarget) {
                closeStatusModal();
            }
        });
    </script>
@endsection