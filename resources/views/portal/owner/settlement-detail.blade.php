@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold text-gray-900 font-mono">{{ $settlement->settlement_number }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ $settlement->balance_due <= 0 ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' }}">
                    {{ strtoupper(str_replace('_', ' ', $settlement->status)) }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">Trip Settlement Statement & Breakdown</p>
        </div>
        <div>
            <a href="{{ route('portal.owner.settlements') }}" class="inline-flex items-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-md shadow-sm transition">
                &larr; Back to Settlements
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <span class="text-xs text-gray-500 font-semibold uppercase">Agreed Freight</span>
            <div class="text-lg font-bold text-gray-900 mt-1">PKR {{ number_format($settlement->agreed_owner_payable, 2) }}</div>
            <div class="text-xs text-gray-400 mt-1">Contract: {{ $settlement->contract?->contract_number ?? 'Negotiated' }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <span class="text-xs text-red-500 font-semibold uppercase">Total Deductions</span>
            <div class="text-lg font-bold text-red-600 mt-1">-PKR {{ number_format($settlement->approved_deductions, 2) }}</div>
            <div class="text-xs text-gray-400 mt-1">Advances: PKR {{ number_format($settlement->advances, 2) }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <span class="text-xs text-indigo-500 font-semibold uppercase">Net Payable</span>
            <div class="text-lg font-black text-indigo-600 mt-1">PKR {{ number_format($settlement->net_payable, 2) }}</div>
            <div class="text-xs text-gray-400 mt-1">Settled on {{ \Carbon\Carbon::parse($settlement->settlement_date)->format('M d, Y') }}</div>
        </div>
        <div class="bg-white p-4 rounded-lg shadow border border-gray-200">
            <span class="text-xs text-green-600 font-semibold uppercase">Balance Due</span>
            <div class="text-lg font-black text-green-700 mt-1">PKR {{ number_format($settlement->balance_due, 2) }}</div>
            <div class="text-xs text-gray-400 mt-1">Paid: PKR {{ number_format($settlement->paid_amount, 2) }}</div>
        </div>
    </div>

    <!-- Settlement Details & Deductions -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Trip Details -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-5 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b pb-2">Operational Information</h2>
            <div class="text-xs space-y-2">
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Trip Reference:</span>
                    <span class="font-mono font-bold text-indigo-600">{{ $settlement->trip?->trip_number ?? 'N/A' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Vehicle:</span>
                    <span class="font-mono font-bold text-gray-800">{{ $settlement->vehicle?->registration_number ?? 'Attached' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Vehicle Make/Model:</span>
                    <span class="text-gray-800">{{ $settlement->vehicle?->make ?? '' }} {{ $settlement->vehicle?->model ?? '' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Trip Route:</span>
                    <span class="text-gray-800">{{ $settlement->trip?->origin_city ?? '-' }} &rarr; {{ $settlement->trip?->destination_city ?? '-' }}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-gray-100">
                    <span class="text-gray-500">Customer Freight Ref:</span>
                    <span class="text-gray-800">PKR {{ number_format($settlement->customer_freight_reference ?? 0, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Deductions Breakdown -->
        <div class="bg-white rounded-lg shadow border border-gray-200 p-5 space-y-4">
            <h2 class="text-sm font-bold text-gray-900 uppercase tracking-wider border-b pb-2">Deductions & Adjustments</h2>
            @if($settlement->deductions && $settlement->deductions->count() > 0)
                <table class="min-w-full text-xs">
                    <thead>
                        <tr class="text-gray-500 border-b">
                            <th class="text-left py-1">Type</th>
                            <th class="text-left py-1">Description</th>
                            <th class="text-right py-1">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($settlement->deductions as $ded)
                            <tr>
                                <td class="py-1.5 font-medium text-gray-700 capitalize">{{ str_replace('_', ' ', $ded->deduction_type) }}</td>
                                <td class="py-1.5 text-gray-500">{{ $ded->description ?? '-' }}</td>
                                <td class="py-1.5 text-right font-bold text-red-600">-PKR {{ number_format($ded->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="py-6 text-center text-xs text-gray-400">
                    No itemized deductions recorded on this settlement.
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

