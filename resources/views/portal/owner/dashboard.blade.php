@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ $owner->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Vehicle Owner Portal &bull; Monitor attached fleet, trip settlements, approved deductions, and ledger balances.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('portal.owner.settlements') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                View Settlements &rarr;
            </a>
        </div>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Attached Vehicles</span>
            <div class="mt-1 text-2xl font-extrabold text-indigo-600">{{ $attachedVehiclesCount }}</div>
            <span class="text-xs text-gray-400">Total trucks registered</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Active Trips</span>
            <div class="mt-1 text-2xl font-extrabold text-blue-600">{{ $activeTripsCount }}</div>
            <span class="text-xs text-gray-400">Currently in-transit</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Pending Settlements</span>
            <div class="mt-1 text-2xl font-extrabold text-amber-600">{{ $unsettledSettlementsCount }}</div>
            <span class="text-xs text-gray-400">Awaiting disbursement</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Payable Balance</span>
            <div class="mt-1 text-2xl font-extrabold text-green-600">PKR {{ number_format($totalPayableBalance, 2) }}</div>
            <span class="text-xs text-gray-400">Due from company</span>
        </div>
    </div>

    <!-- Attached Vehicles & Recent Settlements -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Attached Vehicles -->
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-900">Your Vehicles</h3>
                <a href="{{ route('portal.owner.vehicles') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">All Vehicles &rarr;</a>
            </div>
            <div class="divide-y divide-gray-200 text-xs">
                @forelse($vehicles as $veh)
                    <div class="p-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <span class="font-mono font-bold text-gray-900">{{ $veh->registration_number }}</span>
                            <div class="text-gray-500">{{ $veh->make }} {{ $veh->model }} ({{ $veh->manufacturing_year }})</div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-800">
                                {{ strtoupper($veh->status) }}
                            </span>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ number_format($veh->payload_capacity_tons, 1) }} Tons</div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No attached vehicles registered.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Settlements -->
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-900">Recent Trip Settlements</h3>
                <a href="{{ route('portal.owner.settlements') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">All Settlements &rarr;</a>
            </div>
            <div class="divide-y divide-gray-200 text-xs">
                @forelse($recentSettlements as $stl)
                    <div class="p-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <span class="font-mono font-bold text-indigo-600">{{ $stl->settlement_number }}</span>
                            <div class="text-gray-500">Vehicle: {{ $stl->vehicle?->registration_number ?? 'Attached' }}</div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-gray-900">PKR {{ number_format($stl->balance_due, 2) }}</span>
                            <div>
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-medium {{ $stl->balance_due > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800' }}">
                                    {{ strtoupper(str_replace('_', ' ', $stl->status)) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No settlements generated yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

