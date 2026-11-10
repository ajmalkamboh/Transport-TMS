<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService
    ) {}

    /**
     * Show the login form.
     */
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        if (!Auth::attempt($credentials, $remember)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = Auth::user();

        // Account status check
        if ($user->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => sprintf('Your account is %s. Please contact administration.', $user->status),
            ]);
        }

        // Company status check (if tenant user)
        if ($user->company && $user->company->status !== 'active') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' => sprintf('Your company is %s. Please contact platform support.', $user->company->status),
            ]);
        }

        $request->session()->regenerate();

        // Update login audit telemetry
        $user->last_login_at = now();
        $user->last_login_ip = $request->ip();
        $user->saveQuietly();

        $this->auditLogService->log('login', $user, null, null, 'User authenticated successfully.');

        // Initialize session active branch
        if ($user->branch_id) {
            session(['active_branch_id' => $user->branch_id]);
        }

        if ($user->isSuperAdmin()) {
            return redirect()->intended(route('saas.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            $this->auditLogService->log('logout', $user, null, null, 'User logged out.');
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}

