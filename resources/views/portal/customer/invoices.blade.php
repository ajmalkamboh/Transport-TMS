@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Invoices & Statements</h1>
            <p class="mt-1 text-sm text-gray-500">View billed transport invoices, balances due, and payment terms.</p>
        </div>
        <div class="text-right">
            <span class="text-xs text-gray-500 uppercase font-semibold">Total Outstanding</span>
            <div class="text-2xl font-black text-red-600">PKR {{ number_format($totalBalance, 2) }}</div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm flex flex-wrap gap-3 items-center">
        <a href="{{ route('portal.customer.invoices') }}" class="px-3 py-1.5 rounded-md text-xs font-semibold {{ !request('status') ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">All</a>
        <a href="{{ route('portal.customer.invoices', ['status' => 'unpaid']) }}" class="px-3 py-1.5 rounded-md text-xs font-semibold {{ request('status') === 'unpaid' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Unpaid / Overdue</a>
        <a href="{{ route('portal.customer.invoices', ['status' => 'paid']) }}" class="px-3 py-1.5 rounded-md text-xs font-semibold {{ request('status') === 'paid' ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">Paid</a>
    </div>

    <!-- Invoices Table -->
    <div class="bg-white shadow rounded-lg border border-gray-200 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-xs">
            <thead class="bg-gray-50 font-semibold text-gray-600 uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Invoice #</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Due Date</th>
                    <th class="px-4 py-3 text-right">Total Amount</th>
                    <th class="px-4 py-3 text-right">Paid Amount</th>
                    <th class="px-4 py-3 text-right">Balance Due</th>
                    <th class="px-4 py-3 text-center">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($invoices as $inv)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                            {{ $inv->invoice_number }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ \Carbon\Carbon::parse($inv->invoice_date)->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ \Carbon\Carbon::parse($inv->due_date)->format('M d, Y') }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium text-gray-900">
                            PKR {{ number_format($inv->total_amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right text-green-600 font-medium">
                            PKR {{ number_format($inv->paid_amount, 2) }}
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-red-600">
                            PKR {{ number_format($inv->balance_due, 2) }}
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold
                                {{ $inv->balance_due <= 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $inv->balance_due <= 0 ? 'PAID' : 'UNPAID' }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">No invoices found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        @if($invoices->hasPages())
            <div class="p-3 border-t border-gray-200">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

