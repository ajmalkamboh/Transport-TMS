<?php

namespace App\Livewire\Admin;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class UserManagement extends Component
{
    public ?int $selectedUserId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $phone = '';
    public ?int $branch_id = null;
    public ?int $role_id = null;
    public string $status = 'active';
    public array $authorized_branch_ids = [];

    public bool $isEditing = false;
    public string $search = '';

    protected function checkAuthorization(): void
    {
        $user = auth()->user();
        if (!$user->isSuperAdmin() && !$user->isTenantAdmin() && !$user->hasPermission('manage_users')) {
            throw new AuthorizationException('Unauthorized: You do not possess permission to manage company users.');
        }
    }

    public function render()
    {
        $this->checkAuthorization();

        $activeCompanyId = app(TenantContextService::class)->getCompanyId();

        $users = User::with(['role', 'branch', 'branches'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->get();

        $branches = Branch::where('is_active', true)->get();
        $roles = Role::whereNull('company_id')
            ->orWhere('company_id', $activeCompanyId)
            ->get();

        return view('livewire.admin.user-management', [
            'users' => $users,
            'branches' => $branches,
            'roles' => $roles,
        ]);
    }

    public function resetForm(): void
    {
        $this->selectedUserId = null;
        $this->name = '';
        $this->email = '';
        $this->password = '';
        $this->phone = '';
        $this->branch_id = null;
        $this->role_id = null;
        $this->status = 'active';
        $this->authorized_branch_ids = [];
        $this->isEditing = false;
        $this->resetErrorBag();
    }

    public function editUser(int $userId): void
    {
        $this->checkAuthorization();

        /** @var User $targetUser */
        $targetUser = User::withoutGlobalScopes()->findOrFail($userId);

        // Security: Validate tenant ownership (prevent IDOR)
        app(TenantAuthorizationService::class)->validateTenantOwnership($targetUser);

        $this->selectedUserId = $targetUser->id;
        $this->name = $targetUser->name;
        $this->email = $targetUser->email;
        $this->phone = $targetUser->phone ?? '';
        $this->branch_id = $targetUser->branch_id;
        $this->role_id = $targetUser->role_id;
        $this->status = $targetUser->status;
        $this->authorized_branch_ids = $targetUser->branches->pluck('id')->toArray();
        $this->password = '';
        $this->isEditing = true;
    }

    public function save(): void
    {
        $this->checkAuthorization();

        $tenantAuth = app(TenantAuthorizationService::class);
        $companyId = app(TenantContextService::class)->getCompanyId();

        $rules = [
            'name' => ['required', 'string', 'max:191'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($this->selectedUserId),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'branch_id' => ['required', 'exists:branches,id'],
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'authorized_branch_ids' => ['array'],
            'authorized_branch_ids.*' => ['exists:branches,id'],
        ];

        if (!$this->isEditing) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        }

        $validated = $this->validate($rules);

        // Ensure branches belong to the tenant
        if ($validated['branch_id']) {
            $branch = Branch::withoutGlobalScopes()->findOrFail($validated['branch_id']);
            $tenantAuth->validateTenantOwnership($branch);
        }

        if ($this->isEditing) {
            /** @var User $user */
            $user = User::withoutGlobalScopes()->findOrFail($this->selectedUserId);
            $tenantAuth->validateTenantOwnership($user);

            $oldValues = $user->only(['name', 'email', 'phone', 'branch_id', 'role_id', 'status']);

            $updateData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'branch_id' => $validated['branch_id'],
                'role_id' => $validated['role_id'],
                'status' => $validated['status'],
            ];

            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
            }

            $user->update($updateData);
            $user->branches()->sync($this->authorized_branch_ids);

            app(AuditLogService::class)->log('user_updated', $user, $oldValues, $updateData, 'User account updated.');
            session()->flash('success', 'User updated successfully.');
        } else {
            $userData = [
                'company_id' => $companyId,
                'branch_id' => $validated['branch_id'],
                'role_id' => $validated['role_id'],
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'],
                'status' => $validated['status'],
                'is_super_admin' => false,
            ];

            $user = User::create($userData);
            $user->branches()->sync($this->authorized_branch_ids);

            app(AuditLogService::class)->log('user_created', $user, null, $userData, 'New user account created.');
            session()->flash('success', 'New user registered successfully.');
        }

        $this->resetForm();
    }

    public function deleteUser(int $userId): void
    {
        $this->checkAuthorization();

        if (auth()->id() === $userId) {
            session()->flash('error', 'You cannot delete your own account.');
            return;
        }

        /** @var User $targetUser */
        $targetUser = User::withoutGlobalScopes()->findOrFail($userId);
        app(TenantAuthorizationService::class)->validateTenantOwnership($targetUser);

        app(AuditLogService::class)->log('user_deleted', $targetUser, $targetUser->toArray(), null, 'User deleted.');
        $targetUser->delete();

        session()->flash('success', 'User deleted successfully.');
    }
}

