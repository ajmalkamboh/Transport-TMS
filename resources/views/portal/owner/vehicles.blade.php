@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Attached Vehicles</h1>
            <p class="mt-1 text-sm text-gray-500">Fleet details, odometer readings, and statutory compliance status.</p>
        </div>
        <a href="{{ route('portal.owner.dashboard') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            &larr; Back to Dashboard
        </a>
    </div>

    <div class="bg-white shadow rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Reg # / Fleet #</th>
                    <th class="px-4 py-3 text-left">Make / Model / Year</th>
                    <th class="px-4 py-3 text-left">Chassis / Engine</th>
                    <th class="px-4 py-3 text-right">Capacity (Tons)</th>
                    <th class="px-4 py-3 text-right">Odometer (KM)</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($vehicles as $veh)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            {{ $veh->registration_number }}
                            <div class="text-[11px] text-gray-400 font-normal">{{ $veh->fleet_number ?? '-' }}</div>
                        </td>
                        <td class="px-4 py-3 text-gray-800">
                            {{ $veh->make }} {{ $veh->model }} ({{ $veh->manufacturing_year }})
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-[11px]">
                            CH: {{ $veh->chassis_number }}<br>
                            ENG: {{ $veh->engine_number }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">
                            {{ number_format($veh->payload_capacity_tons, 1) }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700 font-mono">
                            {{ number_format($veh->current_odometer_km) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-gray-100 text-gray-800">
                                {{ strtoupper($veh->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-400">No attached vehicles registered.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($vehicles->hasPages())
            <div class="p-3 border-t border-gray-200">
                {{ $vehicles->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

