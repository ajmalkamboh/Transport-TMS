@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Trip History & Operations</h1>
            <p class="mt-1 text-sm text-gray-500">Operational trips conducted by your attached vehicles with settlement tracking.</p>
        </div>
        <div>
            <a href="{{ route('portal.owner.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md shadow-sm transition">
                &larr; Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Trips Table -->
    <div class="bg-white shadow rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Trip #</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Route</th>
                    <th class="px-4 py-3 text-left">Assigned Vehicle</th>
                    <th class="px-4 py-3 text-center">Operational Status</th>
                    <th class="px-4 py-3 text-right">Settlement #</th>
                    <th class="px-4 py-3 text-right">Owner Net Payable</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($trips as $trip)
                    @php
                        $primaryVeh = $trip->resources->first()?->vehicle;
                        $settlement = $trip->latestOwnerSettlement;
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            {{ $trip->trip_number }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ \Carbon\Carbon::parse($trip->trip_date)->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 font-medium text-gray-900">
                            {{ $trip->origin_city ?? 'Origin' }} &rarr; {{ $trip->destination_city ?? 'Destination' }}
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-800">
                            {{ $primaryVeh?->registration_number ?? 'Attached' }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold
                                {{ $trip->trip_status === 'operationally_completed' || $trip->trip_status === 'closed' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ strtoupper(str_replace('_', ' ', $trip->trip_status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right font-mono">
                            @if($settlement)
                                <a href="{{ route('portal.owner.settlement.detail', $settlement->id) }}" class="text-indigo-600 hover:underline font-semibold">
                                    {{ $settlement->settlement_number }}
                                </a>
                            @else
                                <span class="text-gray-400">Pending</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900">
                            @if($settlement)
                                PKR {{ number_format($settlement->net_payable, 2) }}
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">No operational trips found for your attached fleet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($trips->hasPages())
            <div class="p-3 border-t border-gray-200">
                {{ $trips->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

