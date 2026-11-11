@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Welcome, {{ $customer->name }}</h1>
            <p class="mt-1 text-sm text-gray-500">Customer Portal &bull; Track shipments, review invoices, and download Proof of Delivery (POD) slips.</p>
        </div>
        <div class="mt-4 sm:mt-0">
            <a href="{{ route('portal.customer.consignments') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                Track Shipments &rarr;
            </a>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Active In-Transit</span>
            <div class="mt-1 text-2xl font-extrabold text-indigo-600">{{ $activeConsignmentsCount }}</div>
            <span class="text-xs text-gray-400">Consignments on road</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Delivered</span>
            <div class="mt-1 text-2xl font-extrabold text-green-600">{{ $deliveredCount }}</div>
            <span class="text-xs text-gray-400">Successfully received</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Pending Invoices</span>
            <div class="mt-1 text-2xl font-extrabold text-amber-600">{{ $unpaidInvoicesCount }}</div>
            <span class="text-xs text-gray-400">Awaiting payment</span>
        </div>
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <span class="text-xs font-semibold text-gray-500 uppercase">Balance Due</span>
            <div class="mt-1 text-2xl font-extrabold text-red-600">PKR {{ number_format($totalBalanceDue, 2) }}</div>
            <span class="text-xs text-gray-400">Total outstanding</span>
        </div>
    </div>

    <!-- Recent Consignments & Invoices -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Consignments -->
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-900">Recent Consignments</h3>
                <a href="{{ route('portal.customer.consignments') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">View All &rarr;</a>
            </div>
            <div class="divide-y divide-gray-200 text-xs">
                @forelse($recentConsignments as $cons)
                    <div class="p-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <span class="font-mono font-bold text-gray-800">{{ $cons->consignment_number }}</span>
                            <div class="text-gray-500">{{ $cons->origin_city }} &rarr; {{ $cons->destination_city }}</div>
                        </div>
                        <div class="text-right">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-100 text-blue-800">
                                {{ strtoupper($cons->delivery_status ?? 'BOOKED') }}
                            </span>
                            <div class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($cons->created_at)->format('M d, Y') }}</div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No shipments found.</div>
                @endforelse
            </div>
        </div>

        <!-- Recent Invoices -->
        <div class="bg-white shadow rounded-lg border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-sm font-bold text-gray-900">Recent Invoices</h3>
                <a href="{{ route('portal.customer.invoices') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-500">View All &rarr;</a>
            </div>
            <div class="divide-y divide-gray-200 text-xs">
                @forelse($recentInvoices as $inv)
                    <div class="p-3 flex items-center justify-between hover:bg-gray-50">
                        <div>
                            <span class="font-mono font-bold text-gray-800">{{ $inv->invoice_number }}</span>
                            <div class="text-gray-500">Due: {{ \Carbon\Carbon::parse($inv->due_date)->format('M d, Y') }}</div>
                        </div>
                        <div class="text-right">
                            <span class="font-bold text-gray-900">PKR {{ number_format($inv->balance_due, 2) }}</span>
                            <div>
                                <span class="px-1.5 py-0.2 rounded text-[10px] font-medium {{ $inv->balance_due > 0 ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                    {{ $inv->balance_due > 0 ? 'UNPAID' : 'PAID' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-6 text-center text-gray-400">No invoices generated yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

