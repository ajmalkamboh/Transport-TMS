<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Create a new subscription for a company.
     */
    public function createSubscription(
        Company $company,
        SubscriptionPlan $plan,
        string $billingCycle = 'monthly',
        bool $isTrial = false
    ): Subscription {
        return DB::transaction(function () use ($company, $plan, $billingCycle, $isTrial) {
            $now = now();
            $durationMonths = match ($billingCycle) {
                'quarterly' => 3,
                'annual' => 12,
                default => 1,
            };

            $endsAt = $isTrial 
                ? $now->copy()->addDays($plan->trial_duration_days) 
                : $now->copy()->addMonths($durationMonths);

            $price = $isTrial ? 0.00 : $plan->getPriceForCycle($billingCycle);

            $subscription = Subscription::create([
                'company_id' => $company->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle,
                'status' => $isTrial ? 'trial' : 'active',
                'starts_at' => $now,
                'ends_at' => $endsAt,
                'trial_ends_at' => $isTrial ? $endsAt : null,
                'grace_period_ends_at' => $endsAt->copy()->addDays(7), // 7-day grace period
                'price' => $price,
                'currency' => 'PKR',
                'auto_renew' => true,
            ]);

            // Update company pointers
            $company->plan_id = $plan->id;
            $company->status = $isTrial ? 'trial' : 'active';
            $company->subscription_expires_at = $endsAt;
            $company->trial_ends_at = $isTrial ? $endsAt : null;
            $company->save();

            // Create initial platform invoice if not trial
            if (!$isTrial && $price > 0) {
                $this->createInvoice($subscription);
            }

            return $subscription;
        });
    }

    /**
     * Activate a trial or pending subscription.
     */
    public function activateSubscription(
        Subscription $subscription,
        ?float $amountPaid = null,
        ?string $paymentRef = null
    ): Subscription {
        return DB::transaction(function () use ($subscription, $amountPaid, $paymentRef) {
            $durationMonths = match ($subscription->billing_cycle) {
                'quarterly' => 3,
                'annual' => 12,
                default => 1,
            };

            $now = now();
            $endsAt = $now->copy()->addMonths($durationMonths);

            $subscription->status = 'active';
            $subscription->starts_at = $now;
            $subscription->ends_at = $endsAt;
            $subscription->grace_period_ends_at = $endsAt->copy()->addDays(7);
            $subscription->save();

            $company = $subscription->company;
            $company->status = 'active';
            $company->subscription_expires_at = $endsAt;
            $company->save();

            // Create invoice & record payment
            $invoice = $this->createInvoice($subscription);

            if ($amountPaid !== null && $amountPaid > 0) {
                $this->recordPayment(
                    $invoice,
                    $amountPaid,
                    'bank_transfer',
                    $paymentRef,
                    null,
                    true
                );
            }

            return $subscription;
        });
    }

    /**
     * Renew an existing subscription.
     */
    public function renewSubscription(Subscription $subscription, ?string $cycle = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $cycle) {
            $billingCycle = $cycle ?? $subscription->billing_cycle;
            $durationMonths = match ($billingCycle) {
                'quarterly' => 3,
                'annual' => 12,
                default => 1,
            };

            // If subscription is still active, extend from current ends_at; otherwise extend from now
            $baseDate = $subscription->ends_at->isFuture() ? $subscription->ends_at : now();
            $newEndsAt = $baseDate->copy()->addMonths($durationMonths);

            $price = $subscription->plan->getPriceForCycle($billingCycle);

            $subscription->status = 'active';
            $subscription->billing_cycle = $billingCycle;
            $subscription->ends_at = $newEndsAt;
            $subscription->grace_period_ends_at = $newEndsAt->copy()->addDays(7);
            $subscription->price = $price;
            $subscription->save();

            $company = $subscription->company;
            $company->status = 'active';
            $company->subscription_expires_at = $newEndsAt;
            $company->save();

            // Generate renewal invoice
            $this->createInvoice($subscription);

            return $subscription;
        });
    }

    /**
     * Suspend a subscription and lock the tenant company.
     */
    public function suspendSubscription(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            $subscription->status = 'suspended';
            $subscription->save();

            $company = $subscription->company;
            $company->status = 'suspended';
            $company->save();

            return $subscription;
        });
    }

    /**
     * Cancel a subscription.
     */
    public function cancelSubscription(Subscription $subscription, ?string $reason = null): Subscription
    {
        return DB::transaction(function () use ($subscription, $reason) {
            $subscription->status = 'cancelled';
            $subscription->cancelled_at = now();
            $subscription->auto_renew = false;
            $subscription->save();

            return $subscription;
        });
    }

    /**
     * Create a SaaS subscription invoice.
     */
    public function createInvoice(Subscription $subscription): SubscriptionInvoice
    {
        $year = date('Y');
        $randomSeq = rand(1000, 9999);
        $invoiceNumber = sprintf('SAAS-INV-%s-%04d', $year, $subscription->id * 100 + $randomSeq);

        $price = (float) $subscription->price;
        $tax = round($price * 0.05, 2); // 5% SaaS sales tax
        $total = $price + $tax;

        return SubscriptionInvoice::create([
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'invoice_number' => $invoiceNumber,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'billing_cycle' => $subscription->billing_cycle,
            'subtotal' => $price,
            'tax_amount' => $tax,
            'total_amount' => $total,
            'paid_amount' => 0.00,
            'balance_amount' => $total,
            'status' => 'unpaid',
            'notes' => sprintf('Subscription charge for plan: %s (%s)', $subscription->plan->name, $subscription->billing_cycle),
        ]);
    }

    /**
     * Record a payment against a subscription invoice.
     */
    public function recordPayment(
        SubscriptionInvoice $invoice,
        float $amount,
        string $paymentMethod = 'bank_transfer',
        ?string $reference = null,
        ?string $receiptPath = null,
        bool $autoVerify = true
    ): SubscriptionPayment {
        return DB::transaction(function () use ($invoice, $amount, $paymentMethod, $reference, $receiptPath, $autoVerify) {
            $year = date('Y');
            $paymentNumber = sprintf('SAAS-PAY-%s-%05d', $year, rand(10000, 99999));

            $payment = SubscriptionPayment::create([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'payment_number' => $paymentNumber,
                'payment_date' => now()->toDateString(),
                'amount' => $amount,
                'payment_method' => $paymentMethod,
                'payment_reference' => $reference,
                'status' => $autoVerify ? 'verified' : 'pending_verification',
                'verified_by' => $autoVerify && auth()->check() ? auth()->id() : null,
                'verified_at' => $autoVerify ? now() : null,
                'receipt_file_path' => $receiptPath,
            ]);

            if ($autoVerify) {
                $this->applyPaymentToInvoice($invoice, $amount);
            }

            return $payment;
        });
    }

    /**
     * Verify a pending payment manually.
     */
    public function verifyPayment(SubscriptionPayment $payment, int $verifierUserId): void
    {
        if ($payment->status === 'verified') {
            return;
        }

        DB::transaction(function () use ($payment, $verifierUserId) {
            $payment->status = 'verified';
            $payment->verified_by = $verifierUserId;
            $payment->verified_at = now();
            $payment->save();

            $this->applyPaymentToInvoice($payment->invoice, (float) $payment->amount);
        });
    }

    /**
     * Apply verified payment to reduce invoice balance.
     */
    protected function applyPaymentToInvoice(SubscriptionInvoice $invoice, float $amount): void
    {
        $newPaid = (float) $invoice->paid_amount + $amount;
        $total = (float) $invoice->total_amount;
        $newBalance = max(0.00, $total - $newPaid);

        $status = $newBalance <= 0.00 ? 'paid' : ($newPaid > 0 ? 'partially_paid' : 'unpaid');

        $invoice->paid_amount = $newPaid;
        $invoice->balance_amount = $newBalance;
        $invoice->status = $status;
        $invoice->save();
    }

    /**
     * Calculate Platform Monthly Recurring Revenue (MRR).
     */
    public function calculateMRR(): float
    {
        $activeSubs = Subscription::where('status', 'active')
            ->where('ends_at', '>', now())
            ->get();

        $mrr = 0.00;

        foreach ($activeSubs as $sub) {
            $price = (float) $sub->price;
            $monthlyContribution = match ($sub->billing_cycle) {
                'quarterly' => $price / 3,
                'annual' => $price / 12,
                default => $price,
            };
            $mrr += $monthlyContribution;
        }

        return round($mrr, 2);
    }

    /**
     * Calculate Total Collected Subscription Revenue.
     */
    public function calculateTotalRevenue(): float
    {
        return (float) SubscriptionPayment::where('status', 'verified')->sum('amount');
    }

    /**
     * Count pending payments requiring Super Admin verification.
     */
    public function getPendingPaymentsCount(): int
    {
        return SubscriptionPayment::where('status', 'pending_verification')->count();
    }
}
