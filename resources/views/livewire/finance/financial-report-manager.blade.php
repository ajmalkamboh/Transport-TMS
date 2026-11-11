<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0 text-primary fw-bold"><i class="fas fa-chart-line me-2"></i>Financial Statements & Voyage Intelligence</h4>
            <p class="text-muted small mb-0">Multi-tenant accounting statements, double-entry verification, customer statements, and trip profitability</p>
        </div>
    </div>

    <!-- Navigation Pills -->
    <ul class="nav nav-pills mb-3" role="tablist">
        <li class="nav-item">
            <button wire:click="$set('activeReport', 'trial_balance')" class="nav-link py-1 px-3 {{ $activeReport === 'trial_balance' ? 'active' : '' }}">
                <i class="fas fa-balance-scale me-1"></i> Trial Balance
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeReport', 'pnl')" class="nav-link py-1 px-3 {{ $activeReport === 'pnl' ? 'active' : '' }}">
                <i class="fas fa-file-invoice-dollar me-1"></i> Profit & Loss (P&L)
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeReport', 'balance_sheet')" class="nav-link py-1 px-3 {{ $activeReport === 'balance_sheet' ? 'active' : '' }}">
                <i class="fas fa-landmark me-1"></i> Balance Sheet
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeReport', 'customer_statement')" class="nav-link py-1 px-3 {{ $activeReport === 'customer_statement' ? 'active' : '' }}">
                <i class="fas fa-user-tag me-1"></i> Customer Statement
            </button>
        </li>
        <li class="nav-item">
            <button wire:click="$set('activeReport', 'trip_profitability')" class="nav-link py-1 px-3 {{ $activeReport === 'trip_profitability' ? 'active' : '' }}">
                <i class="fas fa-truck-moving me-1"></i> Trip Profitability
            </button>
        </li>
    </ul>

    <!-- Controls Bar -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                @if(in_array($activeReport, ['trial_balance', 'balance_sheet']))
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">As of Date</label>
                        <input type="date" wire:model.live="asOfDate" class="form-control form-control-sm">
                    </div>
                @else
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">From Date</label>
                        <input type="date" wire:model.live="fromDate" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">To Date</label>
                        <input type="date" wire:model.live="toDate" class="form-control form-control-sm">
                    </div>
                @endif

                @if($activeReport === 'customer_statement')
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Customer</label>
                        <select wire:model.live="selectedCustomerId" class="form-select form-select-sm">
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif($activeReport === 'trip_profitability')
                    <div class="col-md-4">
                        <label class="form-label small fw-bold mb-1">Select Voyage / Trip</label>
                        <select wire:model.live="selectedTripId" class="form-select form-select-sm">
                            @foreach($trips as $t)
                                <option value="{{ $t->id }}">{{ $t->trip_number }} ({{ $t->origin_city }} -> {{ $t->destination_city }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mt-4">
                        @if($selectedTripId)
                            <a href="{{ route('print.trip-profitability', $selectedTripId) }}" target="_blank" class="btn btn-outline-secondary btn-sm w-100">
                                <i class="fas fa-print me-1"></i> Print Voyage P&L
                            </a>
                        @endif
                    </div>
                @else
                    <div class="col-md-3">
                        <label class="form-label small fw-bold mb-1">Branch</label>
                        <select wire:model.live="selectedBranchId" class="form-select form-select-sm">
                            <option value="">All Branches / Consolidated</option>
                            @foreach($branches as $b)
                                <option value="{{ $b->id }}">{{ $b->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Active Report Body -->
    @if($activeReport === 'trial_balance')
        <!-- TRIAL BALANCE -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h5 class="mb-0 fw-bold">Trial Balance</h5>
                    <span class="text-muted small">As of {{ \Carbon\Carbon::parse($data['as_of_date'])->format('d M Y') }}</span>
                </div>
                <div>
                    @if($data['is_balanced'])
                        <span class="badge bg-success-subtle text-success fs--1 px-3 py-2"><i class="fas fa-check-circle me-1"></i> Trial Balance Agrees (Diff: 0.00)</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger fs--1 px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i> Discrepancy: {{ number_format($data['difference'], 2) }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light text-uppercase fs--1">
                            <tr>
                                <th>Account Code</th>
                                <th>Account Title</th>
                                <th>Category</th>
                                <th class="text-end">Debit Balance (PKR)</th>
                                <th class="text-end">Credit Balance (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($data['accounts'] as $row)
                                <tr>
                                    <td><span class="font-monospace fw-bold text-dark">{{ $row['account_code'] }}</span></td>
                                    <td><span class="fw-semibold text-dark">{{ $row['account_name'] }}</span></td>
                                    <td><span class="badge bg-light text-muted">{{ ucfirst($row['account_type']) }}</span></td>
                                    <td class="text-end font-monospace">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                                    <td class="text-end font-monospace">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No balances recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot class="table-light fw-bold fs--1 border-top border-2">
                            <tr>
                                <td colspan="3" class="text-end text-uppercase">Totals:</td>
                                <td class="text-end font-monospace text-primary fs-6">{{ number_format($data['total_debits'], 2) }}</td>
                                <td class="text-end font-monospace text-primary fs-6">{{ number_format($data['total_credits'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

    @elseif($activeReport === 'pnl')
        <!-- PROFIT & LOSS -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="mb-0 fw-bold">Statement of Profit and Loss (Income Statement)</h5>
                <span class="text-muted small">For the period {{ \Carbon\Carbon::parse($data['from_date'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($data['to_date'])->format('d M Y') }}</span>
            </div>
            <div class="card-body p-4">
                <!-- Operating Revenues -->
                <h6 class="text-uppercase fw-bold text-success border-bottom pb-2 mb-3"><i class="fas fa-coins me-1"></i> Operating Revenue</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @forelse($data['revenues'] as $rev)
                                <tr>
                                    <td class="font-monospace" style="width: 15%;">{{ $rev['account_code'] }}</td>
                                    <td>{{ $rev['account_name'] }}</td>
                                    <td class="text-end font-monospace fw-semibold" style="width: 25%;">{{ number_format($rev['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted small">No revenue booked in period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top fw-bold">
                            <tr>
                                <td colspan="2" class="text-uppercase">Total Operating Revenue:</td>
                                <td class="text-end font-monospace text-success fs-6">{{ number_format($data['total_revenue'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Direct Operational (Voyage) Costs -->
                <h6 class="text-uppercase fw-bold text-warning border-bottom pb-2 mb-3"><i class="fas fa-truck me-1"></i> Direct Operating / Voyage Costs</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @forelse($data['direct_costs'] as $dc)
                                <tr>
                                    <td class="font-monospace" style="width: 15%;">{{ $dc['account_code'] }}</td>
                                    <td>{{ $dc['account_name'] }}</td>
                                    <td class="text-end font-monospace fw-semibold" style="width: 25%;">{{ number_format($dc['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted small">No direct costs booked in period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top fw-bold">
                            <tr>
                                <td colspan="2" class="text-uppercase">Total Direct Operating Costs:</td>
                                <td class="text-end font-monospace text-warning fs-6">({{ number_format($data['total_direct_costs'], 2) }})</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Gross Margin Summary -->
                <div class="card bg-light border-0 mb-4">
                    <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-uppercase fs--1">Gross Operating Margin ({{ $data['gross_margin_percentage'] }}%)</span>
                        <span class="fw-bold font-monospace fs-5 {{ $data['gross_margin'] >= 0 ? 'text-primary' : 'text-danger' }}">
                            PKR {{ number_format($data['gross_margin'], 2) }}
                        </span>
                    </div>
                </div>

                <!-- Administrative Overheads -->
                <h6 class="text-uppercase fw-bold text-danger border-bottom pb-2 mb-3"><i class="fas fa-building me-1"></i> Terminal & Administrative Overheads</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @forelse($data['admin_expenses'] as $ae)
                                <tr>
                                    <td class="font-monospace" style="width: 15%;">{{ $ae['account_code'] }}</td>
                                    <td>{{ $ae['account_name'] }}</td>
                                    <td class="text-end font-monospace fw-semibold" style="width: 25%;">{{ number_format($ae['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted small">No administrative expenses booked in period.</td></tr>
                            @endforelse
                        </tbody>
                        <tfoot class="border-top fw-bold">
                            <tr>
                                <td colspan="2" class="text-uppercase">Total Administrative Expenses:</td>
                                <td class="text-end font-monospace text-danger fs-6">({{ number_format($data['total_admin_expenses'], 2) }})</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Net Operating Profit -->
                <div class="card {{ $data['net_profit'] >= 0 ? 'bg-success text-white' : 'bg-danger text-white' }} border-0 shadow-sm">
                    <div class="card-body py-3 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="mb-0 fw-bold text-uppercase">Net Operating Profit / (Loss)</h5>
                            <span class="small opacity-75">Net Profit Margin: {{ $data['net_profit_percentage'] }}%</span>
                        </div>
                        <h4 class="mb-0 font-monospace fw-bold">
                            PKR {{ number_format($data['net_profit'], 2) }}
                        </h4>
                    </div>
                </div>
            </div>
        </div>

    @elseif($activeReport === 'balance_sheet')
        <!-- BALANCE SHEET -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                <div>
                    <h5 class="mb-0 fw-bold">Statement of Financial Position (Balance Sheet)</h5>
                    <span class="text-muted small">As of {{ \Carbon\Carbon::parse($data['as_of_date'])->format('d M Y') }}</span>
                </div>
                <div>
                    @if($data['is_balanced'])
                        <span class="badge bg-success-subtle text-success fs--1 px-3 py-2"><i class="fas fa-check-circle me-1"></i> Balance Sheet Agrees ($Assets = Liabilities + Equity$)</span>
                    @else
                        <span class="badge bg-danger-subtle text-danger fs--1 px-3 py-2"><i class="fas fa-exclamation-triangle me-1"></i> Out of Balance: {{ number_format($data['difference'], 2) }}</span>
                    @endif
                </div>
            </div>
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Left Column: ASSETS -->
                    <div class="col-md-6 border-end">
                        <h5 class="fw-bold text-primary text-uppercase border-bottom pb-2 mb-3">1. Assets</h5>
                        
                        <h6 class="small fw-bold text-uppercase text-muted">Current Assets</h6>
                        <table class="table table-sm align-middle mb-3">
                            <tbody>
                                @foreach($data['current_assets'] as $ca)
                                    <tr>
                                        <td class="font-monospace text-muted" style="width: 20%;">{{ $ca['account_code'] }}</td>
                                        <td>{{ $ca['account_name'] }}</td>
                                        <td class="text-end font-monospace fw-semibold">{{ number_format($ca['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h6 class="small fw-bold text-uppercase text-muted">Non-Current Assets</h6>
                        <table class="table table-sm align-middle mb-4">
                            <tbody>
                                @foreach($data['non_current_assets'] as $nca)
                                    <tr>
                                        <td class="font-monospace text-muted" style="width: 20%;">{{ $nca['account_code'] }}</td>
                                        <td>{{ $nca['account_name'] }}</td>
                                        <td class="text-end font-monospace fw-semibold">{{ number_format($nca['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="card bg-primary-subtle border-0">
                            <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-uppercase">Total Assets</span>
                                <span class="fw-bold font-monospace text-primary fs-5">PKR {{ number_format($data['total_assets'], 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: LIABILITIES & EQUITY -->
                    <div class="col-md-6">
                        <h5 class="fw-bold text-danger text-uppercase border-bottom pb-2 mb-3">2. Liabilities & Equity</h5>
                        
                        <h6 class="small fw-bold text-uppercase text-muted">Current Liabilities</h6>
                        <table class="table table-sm align-middle mb-3">
                            <tbody>
                                @foreach($data['current_liabilities'] as $cl)
                                    <tr>
                                        <td class="font-monospace text-muted" style="width: 20%;">{{ $cl['account_code'] }}</td>
                                        <td>{{ $cl['account_name'] }}</td>
                                        <td class="text-end font-monospace fw-semibold">{{ number_format($cl['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <h6 class="small fw-bold text-uppercase text-muted">Total Liabilities: <span class="font-monospace">{{ number_format($data['total_liabilities'], 2) }}</span></h6>

                        <h6 class="small fw-bold text-uppercase text-muted mt-3">Equity</h6>
                        <table class="table table-sm align-middle mb-4">
                            <tbody>
                                @foreach($data['equity'] as $eq)
                                    <tr>
                                        <td class="font-monospace text-muted" style="width: 20%;">{{ $eq['account_code'] }}</td>
                                        <td>{{ $eq['account_name'] }}</td>
                                        <td class="text-end font-monospace fw-semibold">{{ number_format($eq['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="card bg-success-subtle border-0">
                            <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-uppercase">Total Liabilities & Equity</span>
                                <span class="fw-bold font-monospace text-success fs-5">PKR {{ number_format($data['total_liabilities_and_equity'], 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    @elseif($activeReport === 'customer_statement')
        <!-- CUSTOMER STATEMENT -->
        @if(!empty($data['customer']))
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">{{ $data['customer']->name }} — Statement of Account</h5>
                        <span class="text-muted small">Period: {{ \Carbon\Carbon::parse($data['from_date'])->format('d M Y') }} to {{ \Carbon\Carbon::parse($data['to_date'])->format('d M Y') }}</span>
                    </div>
                    <div>
                        <span class="text-muted small me-2">Outstanding Balance:</span>
                        <span class="fw-bold font-monospace fs-5 {{ $data['outstanding_balance'] > 0 ? 'text-danger' : 'text-success' }}">
                            PKR {{ number_format($data['outstanding_balance'], 2) }}
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-uppercase fs--1">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Reference #</th>
                                    <th>Transaction Details</th>
                                    <th class="text-end">Invoiced (Debit)</th>
                                    <th class="text-end">Paid (Credit)</th>
                                    <th class="text-end">Running Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($data['timeline'] as $item)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($item['date'])->format('d M Y') }}</td>
                                        <td>
                                            <span class="badge {{ $item['type'] === 'INVOICE' ? 'bg-primary-subtle text-primary' : 'bg-success-subtle text-success' }}">
                                                {{ $item['type'] }}
                                            </span>
                                        </td>
                                        <td class="font-monospace fw-bold">{{ $item['reference'] }}</td>
                                        <td>{{ $item['description'] }}</td>
                                        <td class="text-end font-monospace">{{ $item['debit'] > 0 ? number_format($item['debit'], 2) : '—' }}</td>
                                        <td class="text-end font-monospace">{{ $item['credit'] > 0 ? number_format($item['credit'], 2) : '—' }}</td>
                                        <td class="text-end font-monospace fw-bold {{ $item['balance'] > 0 ? 'text-danger' : 'text-dark' }}">
                                            {{ number_format($item['balance'], 2) }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No transactions found for this customer.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

    @elseif($activeReport === 'trip_profitability')
        <!-- TRIP PROFITABILITY -->
        @if(!empty($data['trip']))
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold">Voyage Profitability: {{ $data['trip']->trip_number }}</h5>
                        <span class="text-muted small">Route: {{ $data['trip']->originBranch?->name }} to {{ $data['trip']->destinationBranch?->name }} | Vehicle: {{ $data['trip']->vehicle?->registration_number }}</span>
                    </div>
                    <div>
                        <span class="badge {{ $data['is_profitable'] ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} fs--1 px-3 py-2">
                            <i class="fas {{ $data['is_profitable'] ? 'fa-arrow-up' : 'fa-arrow-down' }} me-1"></i>
                            Net Margin: PKR {{ number_format($data['net_voyage_margin'], 2) }} ({{ $data['margin_percentage'] }}%)
                        </span>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6 border-end">
                            <h6 class="text-uppercase fw-bold text-success border-bottom pb-2 mb-3">Freight Revenue</h6>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Total Invoiced / Consignment Freight:</span>
                                <span class="fw-bold font-monospace fs-5 text-success">PKR {{ number_format($data['freight_revenue'], 2) }}</span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-uppercase fw-bold text-warning border-bottom pb-2 mb-3">Direct Voyage Operational Costs</h6>
                            <table class="table table-sm align-middle mb-3">
                                <tbody>
                                    <tr>
                                        <td>Direct Fuel ({{ number_format($data['fuel_liters'], 1) }} Liters)</td>
                                        <td class="text-end font-monospace">{{ number_format($data['fuel_cost'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Motorway Tolls & Route Expenses</td>
                                        <td class="text-end font-monospace">{{ number_format($data['tolls_and_expenses'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Driver Duty Allowances</td>
                                        <td class="text-end font-monospace">{{ number_format($data['driver_allowances'], 2) }}</td>
                                    </tr>
                                    @if((float)$data['owner_freight_cost'] > 0)
                                        <tr>
                                            <td>Attached Third-Party Vehicle Freight Cost</td>
                                            <td class="text-end font-monospace">{{ number_format($data['owner_freight_cost'], 2) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                                <tfoot class="border-top fw-bold">
                                    <tr>
                                        <td>Total Voyage Direct Costs:</td>
                                        <td class="text-end font-monospace text-danger fs-6">{{ number_format($data['total_direct_costs'], 2) }}</td>
                                    </tr>
                                </tfoot>
                            </table>

                            <div class="card {{ $data['is_profitable'] ? 'bg-success text-white' : 'bg-danger text-white' }} border-0">
                                <div class="card-body py-2 px-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-uppercase">Net Voyage Profit</span>
                                    <span class="fw-bold font-monospace fs-5">PKR {{ number_format($data['net_voyage_margin'], 2) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>
