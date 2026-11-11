@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Trip Settlements & Payables</h1>
            <p class="mt-1 text-sm text-gray-500">Breakdown of agreed owner freight, deductions, advance recoveries, and net balance payable.</p>
        </div>
        <div class="text-right">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Payable Due</span>
            <div class="text-2xl font-black text-green-600">PKR {{ number_format($totalBalanceDue, 2) }}</div>
        </div>
    </div>

    <!-- Settlements Table -->
    <div class="bg-white shadow rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Settlement #</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Vehicle</th>
                    <th class="px-4 py-3 text-right">Agreed Freight</th>
                    <th class="px-4 py-3 text-right">Deductions</th>
                    <th class="px-4 py-3 text-right">Advances</th>
                    <th class="px-4 py-3 text-right">Net Payable</th>
                    <th class="px-4 py-3 text-right">Paid</th>
                    <th class="px-4 py-3 text-right">Balance Due</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($settlements as $stl)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            <a href="{{ route('portal.owner.settlement.detail', $stl->id) }}" class="hover:underline">
                                {{ $stl->settlement_number }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ \Carbon\Carbon::parse($stl->settlement_date)->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 font-mono text-gray-800">
                            {{ $stl->vehicle?->registration_number ?? 'Attached' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-900 font-medium">
                            PKR {{ number_format($stl->agreed_owner_payable, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-red-600">
                            -{{ number_format($stl->approved_deductions, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-amber-600">
                            -{{ number_format($stl->advances, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-gray-900">
                            PKR {{ number_format($stl->net_payable, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-green-600 font-semibold">
                            PKR {{ number_format($stl->paid_amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-black text-indigo-600">
                            PKR {{ number_format($stl->balance_due, 2) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold
                                {{ $stl->balance_due <= 0 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ strtoupper(str_replace('_', ' ', $stl->status)) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">No owner settlements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($settlements->hasPages())
            <div class="p-3 border-t border-gray-200">
                {{ $settlements->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

