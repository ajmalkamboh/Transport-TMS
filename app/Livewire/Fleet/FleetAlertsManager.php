<?php

namespace App\Livewire\Fleet;

use App\Services\FleetManagementService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Component;

class FleetAlertsManager extends Component
{
    public int $withinDays = 30;
    public string $filterType = 'all'; // 'all', 'expired', 'critical', 'warning'

    protected function checkAuthorization(): void
    {
        if (!auth()->check()) {
            throw new AuthorizationException('Authentication required.');
        }

        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('view_fleet')) {
            throw new AuthorizationException('Access denied: You do not have permission to view fleet alerts.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $companyId = app(TenantContextService::class)->getCompanyId() ?? auth()->user()->company_id;
        $alertsData = app(FleetManagementService::class)->getExpiringDocuments($companyId, $this->withinDays);

        return view('livewire.fleet.fleet-alerts-manager', [
            'alerts' => $alertsData,
        ]);
    }
}
