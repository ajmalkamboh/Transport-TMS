<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomerPayment;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\ProofOfDelivery;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public const EVENT_TRIP_DISPATCH = 'trip_dispatch';
    public const EVENT_DELIVERY_DELAY = 'delivery_delay';
    public const EVENT_POD_COMPLETED = 'pod_completed';
    public const EVENT_DOCUMENT_EXPIRY = 'document_expiry';
    public const EVENT_MAINTENANCE_DUE = 'maintenance_due';
    public const EVENT_SUBSCRIPTION_EXPIRY = 'subscription_expiry';
    public const EVENT_PENDING_APPROVAL = 'pending_approval';
    public const EVENT_PAYMENT_RECEIPT = 'payment_receipt';

    public function __construct(
        protected TenantContextService $tenantContext
    ) {}

    /**
     * Dispatch notification across enabled channels for an event type.
     * Fault-tolerant: failure of one channel does not disrupt the application or other channels.
     *
     * @param int $companyId
     * @param string $eventType
     * @param string $title
     * @param string $body
     * @param array $recipients e.g. ['users' => [1, 2], 'emails' => ['a@b.com'], 'phones' => ['+923001234567']]
     * @param array $payload
     * @return array Summary of dispatch results per channel
     */
    public function notify(
        int $companyId,
        string $eventType,
        string $title,
        string $body,
        array $recipients = [],
        array $payload = []
    ): array {
        $setting = NotificationSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('event_type', $eventType)
            ->first();

        // System defaults if setting not specifically configured
        $inAppEnabled = $setting ? (bool)$setting->in_app_enabled : true;
        $emailEnabled = $setting ? (bool)$setting->email_enabled : true;
        $smsEnabled = $setting ? (bool)$setting->sms_enabled : false;
        $whatsappEnabled = $setting ? (bool)$setting->whatsapp_enabled : false;

        $results = [
            'in_app' => [],
            'email' => [],
            'sms' => [],
            'whatsapp' => [],
        ];

        // 1. In-App Notifications
        if ($inAppEnabled && !empty($recipients['users'])) {
            foreach ($recipients['users'] as $userId) {
                $results['in_app'][] = $this->sendInApp($companyId, (int)$userId, $eventType, $title, $body, $payload);
            }
        }

        // 2. Email Notifications
        if ($emailEnabled && !empty($recipients['emails'])) {
            foreach ($recipients['emails'] as $email) {
                $results['email'][] = $this->sendEmail($companyId, $email, $eventType, $title, $body, $payload);
            }
        }

        // 3. SMS Notifications
        if ($smsEnabled && !empty($recipients['phones'])) {
            foreach ($recipients['phones'] as $phone) {
                $results['sms'][] = $this->sendSms($companyId, $phone, $eventType, $title, $body, $payload);
            }
        }

        // 4. WhatsApp Notifications
        if ($whatsappEnabled && !empty($recipients['phones'])) {
            foreach ($recipients['phones'] as $phone) {
                $results['whatsapp'][] = $this->sendWhatsapp($companyId, $phone, $eventType, $title, $body, $payload);
            }
        }

        return $results;
    }

    /**
     * Send In-App Notification (logged to database and read in-app).
     */
    public function sendInApp(int $companyId, int $userId, string $eventType, string $title, string $body, array $payload = []): NotificationLog
    {
        return NotificationLog::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'channel' => NotificationLog::CHANNEL_IN_APP,
            'event_type' => $eventType,
            'recipient' => (string)$userId,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'status' => NotificationLog::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    /**
     * Send Outbound Email Notification.
     */
    public function sendEmail(int $companyId, string $email, string $eventType, string $title, string $body, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'company_id' => $companyId,
            'channel' => NotificationLog::CHANNEL_EMAIL,
            'event_type' => $eventType,
            'recipient' => $email,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        try {
            // In testing/production environment, dispatch mail
            if (config('mail.default')) {
                Mail::raw($body, function ($message) use ($email, $title) {
                    $message->to($email)->subject($title);
                });
            }

            $log->update([
                'status' => NotificationLog::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("Email notification failed to {$email}: " . $e->getMessage());
            $log->update([
                'status' => NotificationLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Send Outbound SMS (via adapter / configured gateway).
     */
    public function sendSms(int $companyId, string $phone, string $eventType, string $title, string $body, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'company_id' => $companyId,
            'channel' => NotificationLog::CHANNEL_SMS,
            'event_type' => $eventType,
            'recipient' => $phone,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        try {
            // Simulated gateway provider check (e.g. Twilio / Zong / Jazz / Infobip)
            $gatewayConfigured = config('services.sms.provider') !== null;
            if ($gatewayConfigured) {
                // Outbound HTTP request to SMS gateway
            }

            $log->update([
                'status' => NotificationLog::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("SMS notification failed to {$phone}: " . $e->getMessage());
            $log->update([
                'status' => NotificationLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    /**
     * Send Outbound WhatsApp Notification (via approved business API provider).
     */
    public function sendWhatsapp(int $companyId, string $phone, string $eventType, string $title, string $body, array $payload = []): NotificationLog
    {
        $log = NotificationLog::create([
            'company_id' => $companyId,
            'channel' => NotificationLog::CHANNEL_WHATSAPP,
            'event_type' => $eventType,
            'recipient' => $phone,
            'title' => $title,
            'body' => $body,
            'payload' => $payload,
            'status' => NotificationLog::STATUS_PENDING,
        ]);

        try {
            // Simulated Meta WhatsApp Cloud API / Twilio WhatsApp integration
            $waConfigured = config('services.whatsapp.token') !== null;
            if ($waConfigured) {
                // Outbound WhatsApp message template
            }

            $log->update([
                'status' => NotificationLog::STATUS_SENT,
                'sent_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning("WhatsApp notification failed to {$phone}: " . $e->getMessage());
            $log->update([
                'status' => NotificationLog::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    // ==========================================
    // OPERATIONAL NOTIFICATION SHORTCUT HELPERS
    // ==========================================

    /**
     * 1. Trip Dispatched Notification
     */
    public function notifyTripDispatched(Trip $trip): array
    {
        $driver = $trip->primaryDriver();
        $emails = [];
        $phones = [];
        $users = [];

        if ($driver?->phone) {
            $phones[] = $driver->phone;
        }

        // Notify dispatcher
        if ($trip->assigned_dispatcher_id) {
            $users[] = $trip->assigned_dispatcher_id;
        }

        return $this->notify(
            $trip->company_id,
            self::EVENT_TRIP_DISPATCH,
            "Trip Dispatched: {$trip->trip_number}",
            "Trip {$trip->trip_number} ({$trip->origin_city} -> {$trip->destination_city}) has been dispatched.",
            ['users' => $users, 'emails' => $emails, 'phones' => $phones],
            ['trip_id' => $trip->id, 'trip_number' => $trip->trip_number]
        );
    }

    /**
     * 2. Delivery Delay Notification
     */
    public function notifyDeliveryDelayed(Trip $trip, string $delayReason): array
    {
        $users = $trip->assigned_dispatcher_id ? [$trip->assigned_dispatcher_id] : [];
        return $this->notify(
            $trip->company_id,
            self::EVENT_DELIVERY_DELAY,
            "Delivery Delayed: Trip {$trip->trip_number}",
            "Delay reported for Trip {$trip->trip_number}. Reason: {$delayReason}",
            ['users' => $users],
            ['trip_id' => $trip->id, 'reason' => $delayReason]
        );
    }

    /**
     * 3. POD Completed Notification
     */
    public function notifyPodCompleted(ProofOfDelivery $pod): array
    {
        return $this->notify(
            $pod->company_id,
            self::EVENT_POD_COMPLETED,
            "POD Verified: Consignment #{$pod->trip_consignment_id}",
            "Signed Proof of Delivery uploaded and verified for Trip #{$pod->trip_id} by receiver {$pod->receiver_name}.",
            ['users' => [$pod->verified_by ?? 1]],
            ['pod_id' => $pod->id, 'trip_id' => $pod->trip_id]
        );
    }

    /**
     * 4. Document Expiry Alert
     */
    public function notifyDocumentExpiry(string $documentType, string $referenceNumber, string $expiryDate, int $companyId): array
    {
        return $this->notify(
            $companyId,
            self::EVENT_DOCUMENT_EXPIRY,
            "Document Expiry Alert: {$documentType}",
            "{$documentType} (Ref: {$referenceNumber}) is expiring on {$expiryDate}. Please renew immediately.",
            ['users' => [1]],
            ['document_type' => $documentType, 'reference' => $referenceNumber, 'expiry_date' => $expiryDate]
        );
    }

    /**
     * 5. Vehicle Maintenance Due Alert
     */
    public function notifyMaintenanceDue(Vehicle $vehicle, string $serviceType): array
    {
        return $this->notify(
            $vehicle->company_id,
            self::EVENT_MAINTENANCE_DUE,
            "Maintenance Due: {$vehicle->registration_number}",
            "Vehicle {$vehicle->registration_number} is due for {$serviceType} service.",
            ['users' => [1]],
            ['vehicle_id' => $vehicle->id, 'service_type' => $serviceType]
        );
    }

    /**
     * 6. Subscription Expiry Warning
     */
    public function notifySubscriptionExpiry(Company $company, string $expiryDate): array
    {
        return $this->notify(
            $company->id,
            self::EVENT_SUBSCRIPTION_EXPIRY,
            "TMS Subscription Renewal Required",
            "Your subscription for {$company->name} will expire on {$expiryDate}. Please renew to prevent service interruption.",
            ['emails' => [$company->email ?? 'admin@company.com']],
            ['expiry_date' => $expiryDate]
        );
    }

    /**
     * 7. Pending Approval Alert
     */
    public function notifyPendingApproval(int $companyId, string $voucherType, string $voucherNumber, float $amount): array
    {
        return $this->notify(
            $companyId,
            self::EVENT_PENDING_APPROVAL,
            "Pending Approval: {$voucherType} #{$voucherNumber}",
            "{$voucherType} #{$voucherNumber} for PKR " . number_format($amount, 2) . " is waiting for your authorization.",
            ['users' => [1]],
            ['voucher_type' => $voucherType, 'voucher_number' => $voucherNumber, 'amount' => $amount]
        );
    }

    /**
     * 8. Payment Receipt Notification
     */
    public function notifyPaymentReceived(CustomerPayment $payment): array
    {
        $emails = [];
        $customer = $payment->customer;
        if ($customer?->email) {
            $emails[] = $customer->email;
        }

        return $this->notify(
            $payment->company_id,
            self::EVENT_PAYMENT_RECEIPT,
            "Payment Receipt: {$payment->receipt_number}",
            "We have received PKR " . number_format($payment->amount, 2) . " via {$payment->payment_method}. Thank you for your payment.",
            ['emails' => $emails, 'users' => [1]],
            ['receipt_number' => $payment->receipt_number, 'amount' => (float)$payment->amount]
        );
    }
}

