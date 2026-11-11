@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Consignment Tracking</h1>
            <p class="mt-1 text-sm text-gray-500">Track real-time shipment progress, delivery milestones, and POD documents.</p>
        </div>
        <a href="{{ route('portal.customer.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            &larr; Back to Dashboard
        </a>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex flex-wrap gap-3 items-center justify-between">
        <form method="GET" action="{{ route('portal.customer.consignments') }}" class="flex flex-wrap gap-3 items-center w-full sm:w-auto">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Consignment # or City..."
                   class="rounded-md border-gray-300 shadow-sm text-xs px-3 py-2 border w-64">
            <select name="status" class="rounded-md border-gray-300 shadow-sm text-xs px-3 py-2 border">
                <option value="">All Statuses</option>
                <option value="booked" {{ request('status') === 'booked' ? 'selected' : '' }}>Booked</option>
                <option value="in_transit" {{ request('status') === 'in_transit' ? 'selected' : '' }}>In Transit</option>
                <option value="delivered" {{ request('status') === 'delivered' ? 'selected' : '' }}>Delivered</option>
            </select>
            <button type="submit" class="px-3 py-2 bg-indigo-600 text-white rounded text-xs font-semibold hover:bg-indigo-500">
                Filter
            </button>
            @if(request()->hasAny(['search', 'status']))
                <a href="{{ route('portal.customer.consignments') }}" class="text-xs text-gray-500 hover:text-gray-700">Clear</a>
            @endif
        </form>
    </div>

    <!-- Consignments Table -->
    <div class="bg-white shadow rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Consignment #</th>
                    <th class="px-4 py-3 text-left">Route</th>
                    <th class="px-4 py-3 text-left">Packages / Weight</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-left">Created Date</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($consignments as $cons)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            {{ $cons->consignment_number }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-800">{{ $cons->origin_city }}</span> &rarr;
                            <span class="font-medium text-gray-800">{{ $cons->destination_city }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $cons->package_count }} pkgs &bull; {{ number_format($cons->weight_kg) }} kg
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold 
                                {{ in_array($cons->delivery_status, ['delivered', 'delivered_full']) ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                                {{ strtoupper($cons->delivery_status ?? 'BOOKED') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">
                            {{ \Carbon\Carbon::parse($cons->created_at)->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('portal.customer.consignment.detail', $cons->id) }}" class="text-indigo-600 hover:text-indigo-800 font-semibold">
                                View Tracking &rarr;
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No consignments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($consignments->hasPages())
            <div class="p-3 border-t border-gray-200">
                {{ $consignments->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

