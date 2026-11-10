<div>
    <!-- Metric Cards -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-start border-danger border-4">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs--2 text-uppercase fw-bold text-danger">Expired</div>
                            <h4 class="fw-bolder mb-0 text-danger">{{ count($alerts['expired']) }}</h4>
                            <div class="fs--2 text-muted">Immediate Grounding</div>
                        </div>
                        <div class="avatar avatar-m bg-soft-danger rounded-circle text-danger">
                            <span class="fas fa-times-circle"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-start border-warning border-4">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs--2 text-uppercase fw-bold text-warning">Critical (<= 7 Days)</div>
                            <h4 class="fw-bolder mb-0 text-warning">{{ count($alerts['critical']) }}</h4>
                            <div class="fs--2 text-muted">Urgent Renewal</div>
                        </div>
                        <div class="avatar avatar-m bg-soft-warning rounded-circle text-warning">
                            <span class="fas fa-exclamation-triangle"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-start border-info border-4">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs--2 text-uppercase fw-bold text-info">Upcoming (<= {{ $withinDays }} Days)</div>
                            <h4 class="fw-bolder mb-0 text-info">{{ count($alerts['warning']) }}</h4>
                            <div class="fs--2 text-muted">Scheduled Renewal</div>
                        </div>
                        <div class="avatar avatar-m bg-soft-info rounded-circle text-info">
                            <span class="fas fa-calendar-alt"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-md-3">
            <div class="card shadow-sm border-start border-secondary border-4">
                <div class="card-body py-2">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fs--2 text-uppercase fw-bold text-secondary">Total Alerts</div>
                            <h4 class="fw-bolder mb-0 text-900">{{ $alerts['total_alerts'] }}</h4>
                            <div class="fs--2 text-muted">Compliance Pipeline</div>
                        </div>
                        <div class="avatar avatar-m bg-soft-secondary rounded-circle text-secondary">
                            <span class="fas fa-clipboard-check"></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center justify-content-between">
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <label class="form-label fs--1 fw-semi-bold mb-0">Expiry Horizon:</label>
                    <select class="form-select form-select-sm" wire:model.live="withinDays" style="width: 140px;">
                        <option value="15">Next 15 Days</option>
                        <option value="30">Next 30 Days</option>
                        <option value="60">Next 60 Days</option>
                        <option value="90">Next 90 Days</option>
                    </select>
                </div>
                <div class="col-md-auto d-flex gap-2 align-items-center">
                    <label class="form-label fs--1 fw-semi-bold mb-0">Filter Severity:</label>
                    <select class="form-select form-select-sm" wire:model.live="filterType" style="width: 160px;">
                        <option value="all">All Severities</option>
                        <option value="expired">Expired Only</option>
                        <option value="critical">Critical (<= 7d) Only</option>
                        <option value="warning">Warning (<= {{ $withinDays }}d) Only</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- EXPIRED SECTION -->
    @if(($filterType === 'all' || $filterType === 'expired') && count($alerts['expired']) > 0)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-soft-danger py-2 border-bottom border-danger">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-danger fw-bold">
                        <i class="fas fa-times-circle me-1"></i> EXPIRED REGULATORY DOCUMENTS & LICENSES ({{ count($alerts['expired']) }})
                    </h6>
                    <span class="badge bg-danger">Grounding Action Required</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                        <thead class="bg-200">
                            <tr>
                                <th>Entity Type</th>
                                <th>Vehicle / Driver Identifier</th>
                                <th>Document / License Category</th>
                                <th>Expiry Date</th>
                                <th>Status / Elapsed</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alerts['expired'] as $item)
                                <tr>
                                    <td>
                                        @if($item['entity_type'] === 'Vehicle')
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-truck me-1"></i> Vehicle</span>
                                        @else
                                            <span class="badge bg-soft-info text-info"><i class="fas fa-id-card me-1"></i> Driver</span>
                                        @endif
                                    </td>
                                    <td class="fw-bold text-900">{{ $item['entity_name'] }}</td>
                                    <td><span class="fw-semi-bold text-danger">{{ $item['document_name'] }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($item['expiry_date'])->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-danger">
                                            Expired {{ abs($item['days_remaining']) }} {{ abs($item['days_remaining']) === 1 ? 'day' : 'days' }} ago
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($item['entity_type'] === 'Vehicle')
                                            <a href="{{ route('fleet.vehicles') }}" class="btn btn-falcon-danger btn-xs">Renew Vehicle</a>
                                        @else
                                            <a href="{{ route('fleet.drivers') }}" class="btn btn-falcon-danger btn-xs">Renew License</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- CRITICAL SECTION -->
    @if(($filterType === 'all' || $filterType === 'critical') && count($alerts['critical']) > 0)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-soft-warning py-2 border-bottom border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-warning fw-bold">
                        <i class="fas fa-exclamation-triangle me-1"></i> CRITICAL - EXPIRING WITHIN 7 DAYS ({{ count($alerts['critical']) }})
                    </h6>
                    <span class="badge bg-warning text-dark">High Priority Renewal</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                        <thead class="bg-200">
                            <tr>
                                <th>Entity Type</th>
                                <th>Vehicle / Driver Identifier</th>
                                <th>Document / License Category</th>
                                <th>Expiry Date</th>
                                <th>Remaining Days</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alerts['critical'] as $item)
                                <tr>
                                    <td>
                                        @if($item['entity_type'] === 'Vehicle')
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-truck me-1"></i> Vehicle</span>
                                        @else
                                            <span class="badge bg-soft-info text-info"><i class="fas fa-id-card me-1"></i> Driver</span>
                                        @endif
                                    </td>
                                    <td class="fw-bold text-900">{{ $item['entity_name'] }}</td>
                                    <td><span class="fw-semi-bold text-warning">{{ $item['document_name'] }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($item['expiry_date'])->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-warning text-dark fw-bold">
                                            {{ $item['days_remaining'] }} {{ $item['days_remaining'] === 1 ? 'day' : 'days' }} left
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($item['entity_type'] === 'Vehicle')
                                            <a href="{{ route('fleet.vehicles') }}" class="btn btn-falcon-warning btn-xs">Renew Vehicle</a>
                                        @else
                                            <a href="{{ route('fleet.drivers') }}" class="btn btn-falcon-warning btn-xs">Renew License</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- WARNING SECTION -->
    @if(($filterType === 'all' || $filterType === 'warning') && count($alerts['warning']) > 0)
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-soft-info py-2 border-bottom border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 text-info fw-bold">
                        <i class="fas fa-calendar-alt me-1"></i> SCHEDULED WARNINGS - EXPIRING WITHIN {{ $withinDays }} DAYS ({{ count($alerts['warning']) }})
                    </h6>
                    <span class="badge bg-info">Routine Renewal</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-striped fs--1 mb-0 align-middle">
                        <thead class="bg-200">
                            <tr>
                                <th>Entity Type</th>
                                <th>Vehicle / Driver Identifier</th>
                                <th>Document / License Category</th>
                                <th>Expiry Date</th>
                                <th>Remaining Days</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($alerts['warning'] as $item)
                                <tr>
                                    <td>
                                        @if($item['entity_type'] === 'Vehicle')
                                            <span class="badge bg-soft-primary text-primary"><i class="fas fa-truck me-1"></i> Vehicle</span>
                                        @else
                                            <span class="badge bg-soft-info text-info"><i class="fas fa-id-card me-1"></i> Driver</span>
                                        @endif
                                    </td>
                                    <td class="fw-bold text-900">{{ $item['entity_name'] }}</td>
                                    <td><span class="fw-semi-bold text-secondary">{{ $item['document_name'] }}</span></td>
                                    <td>{{ \Carbon\Carbon::parse($item['expiry_date'])->format('d M Y') }}</td>
                                    <td>
                                        <span class="badge bg-soft-info text-info">
                                            {{ $item['days_remaining'] }} days left
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        @if($item['entity_type'] === 'Vehicle')
                                            <a href="{{ route('fleet.vehicles') }}" class="btn btn-falcon-default btn-xs">View Vehicle</a>
                                        @else
                                            <a href="{{ route('fleet.drivers') }}" class="btn btn-falcon-default btn-xs">View Driver</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    @if($alerts['total_alerts'] === 0)
        <div class="card shadow-sm border-0">
            <div class="card-body text-center py-5">
                <div class="avatar avatar-4xl bg-soft-success rounded-circle text-success mx-auto mb-3">
                    <span class="fas fa-shield-alt fa-2x"></span>
                </div>
                <h5 class="fw-bold text-success">All Fleet Documents & Driver Licenses Compliant!</h5>
                <p class="text-muted fs--1 mb-0">No vehicle fitness, commercial insurance, route permits, registration, or driver licenses are expiring within the next {{ $withinDays }} days.</p>
            </div>
        </div>
    @endif
</div>
