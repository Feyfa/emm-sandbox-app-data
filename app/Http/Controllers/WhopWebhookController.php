<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Services\WhopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhopWebhookController extends Controller
{
    public function __construct(private WhopService $whop) {}

    /**
     * POST /webhook/whop
     * Handle all incoming Whop webhook events.
     */
    public function handle(Request $request)
    {
        Log::info('[WhopWebhook] Received webhook', [
            'headers' => $request->headers->all(), 
            'body' => $request->getContent(), 
            'all' => $request->all()
        ]);
        
        $signature  = $request->header('webhook-signature');
        $timestamp  = $request->header('webhook-timestamp');
        $webhookId  = $request->header('webhook-id');
        $payload    = $request->getContent();

        // Verify the webhook signature
        if (!$this->whop->verifyWebhookSignature($payload, $signature ?? '', $timestamp ?? '', $webhookId ?? '')) {
            Log::warning('[WhopWebhook] Invalid signature received');
            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $event = $request->json()->all();
        $type  = $event['type'] ?? null;
        
        Log::info('[WhopWebhook] Event type: ' . $type);
        Log::info('[WhopWebhook] Full event data:', $event);

        match ($type) {
            'membership.went_valid'   => $this->handleMembershipWentValid($event),
            'membership.activated'    => $this->handleMembershipActivated($event),
            'setup_intent.succeeded'  => $this->handleSetupIntentSucceeded($event),
            'payment.succeeded'       => $this->handlePaymentSucceeded($event),
            'payment.failed'          => $this->handlePaymentFailed($event),
            default => Log::info('[WhopWebhook] Unhandled event type', ['type' => $type]),
        };

        return response()->json(['message' => 'ok']);
    }

    // ─── Event Handlers ───────────────────────────────────────────────────────

    /**
     * Membership went valid — fired when user successfully subscribes to a plan.
     * This is the PRIMARY way we get payment_method_id after a subscription via embed.
     *
     * Whop payload includes:
     *   - data.id           : membership ID (mem_xxx)
     *   - data.user_id      : Whop user ID
     *   - data.plan_id      : which plan they subscribed to
     *   - data.metadata     : custom metadata we may have passed
     *
     * Note: payment_method_id may also come via payment.succeeded fired at the same time.
     * We handle both; idempotency check prevents duplicates.
     */
    private function handleMembershipWentValid(array $event): void
    {
        $data       = $event['data'] ?? [];
        $memberId   = $data['user_id'] ?? null;   // Whop member/user ID
        $membershipId = $data['id'] ?? null;       // mem_xxx
        $pmId       = $data['payment_method_id'] ?? null;
        $metadata   = $this->extractMetadata($data);

        Log::info('[WhopWebhook] membership.went_valid', [
            'membership_id' => $membershipId,
            'member_id'     => $memberId,
            'pm_id'         => $pmId,
            'plan_id'       => $data['plan_id'] ?? null,
            'metadata'      => $metadata,
        ]);
        
        if (empty($metadata)) {
            Log::warning('[WhopWebhook] ⚠️ NO METADATA FOUND in membership.went_valid');
        } else {
            Log::info('[WhopWebhook] ✅ METADATA PRESENT in membership.went_valid:', [
                'metadata_keys' => array_keys($metadata),
                'full_metadata' => $metadata,
            ]);
        }

        if (!$memberId) {
            Log::warning('[WhopWebhook] membership.went_valid: no member/user ID found');
            return;
        }

        if (!$this->metadataFlowMatches($metadata, 'subscription_plan')) {
            Log::info('[WhopWebhook] membership.went_valid skipped due to flow mismatch', [
                'flow' => $metadata['flow'] ?? null,
            ]);
            return;
        }

        // Identify our user via metadata (if we passed user_id when creating checkout)
        $userId = $this->extractUserIdFromMetadata($metadata);

        // If no metadata, try matching by existing member_id in our DB
        if (!$userId) {
            $existing = PaymentMethod::where('provider_customer_id', $memberId)->first();
            if ($existing) {
                $userId = $existing->user_id;
                Log::info('[WhopWebhook] Matched user by existing member_id', ['user_id' => $userId]);
            }
        }

        if (!$userId) {
            Log::warning('[WhopWebhook] membership.went_valid: cannot identify user', [
                'member_id' => $memberId,
                'metadata'  => $metadata,
            ]);
            return;
        }

        // Save payment method if we have it and it’s not already stored
        if ($pmId && !PaymentMethod::where('provider_payment_method_id', $pmId)->exists()) {
            $hasExisting = PaymentMethod::where('user_id', $userId)->where('is_active', true)->exists();

            PaymentMethod::create([
                'user_id'                    => $userId,
                'provider_customer_id'       => $memberId,
                'provider_payment_method_id' => $pmId,
                'payment_type'               => 'credit_card',
                'last_four_digits'           => $data['payment_method']['card']['last4'] ?? null,
                'brand'                      => $data['payment_method']['card']['brand'] ?? null,
                'expires_at'                 => isset($data['payment_method']['card']['exp_year'])
                    ? $data['payment_method']['card']['exp_year'] . '-'
                      . str_pad($data['payment_method']['card']['exp_month'], 2, '0', STR_PAD_LEFT) . '-01'
                    : null,
                'is_default'                 => !$hasExisting,
                'is_active'                  => true,
                'metadata'                   => json_encode($data['payment_method'] ?? []),
            ]);

            Log::info('[WhopWebhook] Payment method saved from subscription', [
                'user_id'   => $userId,
                'member_id' => $memberId,
                'pm_id'     => $pmId,
            ]);
        }
    }

    /**
     * Setup intent succeeded — user has saved a card via the embed.
     * The setup_intent object contains the member_id and payment_method_id.
     * We identify the user via metadata.user_id that was passed when creating the checkout configuration.
     */
    private function handleSetupIntentSucceeded(array $event): void
    {
        $data     = $event['data'] ?? [];
        $memberId = $data['member_id'] ?? null;
        $pmId     = $data['payment_method_id'] ?? null;
        $metadata = $this->extractMetadata($data);

        Log::info('[WhopWebhook] setup_intent.succeeded', [
            'member_id' => $memberId,
            'pm_id'     => $pmId,
            'metadata'  => $metadata,
        ]);
        
        if (empty($metadata)) {
            Log::warning('[WhopWebhook] ⚠️ NO METADATA FOUND in setup_intent.succeeded');
        } else {
            Log::info('[WhopWebhook] ✅ METADATA PRESENT in setup_intent.succeeded:', [
                'metadata_keys' => array_keys($metadata),
                'full_metadata' => $metadata,
            ]);
        }

        if (!$memberId || !$pmId) {
            Log::warning('[WhopWebhook] setup_intent.succeeded missing member_id or payment_method_id', $data);
            return;
        }

        if (!$this->metadataFlowMatches($metadata, 'save_payment_method')) {
            Log::info('[WhopWebhook] setup_intent.succeeded skipped due to flow mismatch', [
                'flow' => $metadata['flow'] ?? null,
            ]);
            return;
        }

        // Idempotency — skip if already saved
        if (PaymentMethod::where('provider_payment_method_id', $pmId)->exists()) {
            Log::info('[WhopWebhook] Payment method already stored, skipping.', ['pm_id' => $pmId]);
            return;
        }

        // Identify the user from metadata.user_id set when creating the checkout configuration
        $userId = $this->extractUserIdFromMetadata($metadata);

        if (!$userId) {
            Log::warning('[WhopWebhook] setup_intent.succeeded: no user_id in metadata, cannot store payment method.', [
                'member_id' => $memberId,
                'pm_id'     => $pmId,
            ]);
            return;
        }

        $user = User::find((int) $userId);

        if (!$user) {
            Log::warning('[WhopWebhook] setup_intent.succeeded: user not found', ['user_id' => $userId]);
            return;
        }

        // Parse card details from the payment_method sub-object if present
        $pm   = $data['payment_method'] ?? [];
        $card = $pm['card'] ?? [];

        $hasExisting = PaymentMethod::where('user_id', $user->id)->where('is_active', true)->exists();

        PaymentMethod::create([
            'user_id'                    => $user->id,
            'provider_customer_id'       => $memberId,          // Whop member_id (mber_xxx)
            'provider_payment_method_id' => $pmId,              // Whop payment_method_id (payt_xxx)
            'payment_type'               => $pm['type'] ?? 'credit_card',
            'last_four_digits'           => $card['last4'] ?? null,
            'brand'                      => $card['brand'] ?? null,
            'expires_at'                 => isset($card['exp_year'], $card['exp_month'])
                ? $card['exp_year'] . '-' . str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT) . '-01'
                : null,
            'is_default'                 => !$hasExisting,
            'is_active'                  => true,
            'metadata'                   => json_encode($pm),
        ]);

        Log::info('[WhopWebhook] Payment method saved via webhook', [
            'user_id'   => $user->id,
            'member_id' => $memberId,
            'pm_id'     => $pmId,
        ]);
    }

    /**
     * Membership activated — user subscription is now active.
     * Whop fires this instead of membership.went_valid in some cases.
     * Save payment method if present.
     */
    private function handleMembershipActivated(array $event): void
    {
        $data = $event['data'] ?? [];
        $memberId = $data['member']['id'] ?? null;
        $user = $data['user'] ?? [];
        $userEmail = $user['email'] ?? null;

        Log::info('[WhopWebhook] membership.activated', [
            'membership_id' => $data['id'] ?? null,
            'member_id'     => $memberId,
            'user_email'    => $userEmail,
            'plan_id'       => $data['plan']['id'] ?? null,
        ]);

        // Note: membership.activated does NOT include payment_method.
        // We rely on payment.succeeded (which fires at same time) to save it.
        // This handler is just for logging/tracking membership status.
    }

    /**
     * Payment succeeded — update invoice OR save payment method from subscription.
     *
     * Two scenarios:
     * 1. Invoice payment (buy contacts) → update invoice status
     * 2. Subscription payment (billing_reason = "subscription_create") → save payment method
     */
    private function handlePaymentSucceeded(array $event): void
    {
        $data = $event['data'] ?? [];
        $billingReason = $data['billing_reason'] ?? null;
        $paymentId = $data['id'] ?? null;

        // Scenario 1: Invoice payment (buy contacts)
        $invoice = Invoice::where('provider_transaction_id', $paymentId)->first();

        if ($invoice) {
            $invoice->update([
                'status'              => 'paid',
                'paid_at'             => now(),
                'webhook_received_at' => now(),
                'amount'              => $data['total'] ?? $invoice->amount,
            ]);

            Log::info('[WhopWebhook] Invoice marked paid', ['invoice_id' => $invoice->id]);
            return;
        }

        // Scenario 2: Subscription payment — save payment method
        if ($billingReason === 'subscription_create') {
            $this->savePaymentMethodFromSubscription($data);
        } else {
            Log::warning('[WhopWebhook] payment.succeeded: invoice not found and not a subscription', [
                'payment_id'     => $paymentId,
                'billing_reason' => $billingReason,
            ]);
        }
    }

    /**
     * Save payment method from subscription payment webhook.
     */
    private function savePaymentMethodFromSubscription(array $data): void
    {
        $pmData = $data['payment_method'] ?? null;
        $memberId = $data['member']['id'] ?? null;
        $userEmail = $data['user']['email'] ?? null;
        $metadata = $this->extractMetadata($data);

        Log::info('[WhopWebhook] savePaymentMethodFromSubscription', [
            'member_id'  => $memberId,
            'user_email' => $userEmail,
            'pm_id'      => $pmData['id'] ?? null,
            'metadata'   => $metadata,
        ]);
        
        if (empty($metadata)) {
            Log::warning('[WhopWebhook] ⚠️ NO METADATA FOUND in payment.succeeded');
        } else {
            Log::info('[WhopWebhook] ✅ METADATA PRESENT in payment.succeeded:', [
                'metadata_keys' => array_keys($metadata),
                'full_metadata' => $metadata,
            ]);
        }

        if (!$pmData || !$memberId) {
            Log::warning('[WhopWebhook] subscription payment missing payment_method or member_id', [
                'has_pm'        => !empty($pmData),
                'has_member_id' => !empty($memberId),
            ]);
            return;
        }

        if (!$this->metadataFlowMatches($metadata, 'subscription_plan')) {
            Log::info('[WhopWebhook] payment.succeeded subscription save skipped due to flow mismatch', [
                'flow' => $metadata['flow'] ?? null,
            ]);
            return;
        }

        $pmId = $pmData['id'] ?? null;

        // Idempotency — skip if already saved
        if (PaymentMethod::where('provider_payment_method_id', $pmId)->exists()) {
            Log::info('[WhopWebhook] Payment method already exists, skipping', ['pm_id' => $pmId]);
            return;
        }

        $userId = $this->extractUserIdFromMetadata($metadata);
        $user = $userId ? User::find($userId) : null;

        if (!$user && $userEmail) {
            $user = User::where('email', $userEmail)->first();
        }

        if (!$user) {
            Log::warning('[WhopWebhook] Cannot save payment method: user not found', [
                'email'     => $userEmail,
                'member_id' => $memberId,
                'metadata'  => $metadata,
            ]);
            return;
        }

        $card = $pmData['card'] ?? [];
        $hasExisting = PaymentMethod::where('user_id', $user->id)->where('is_active', true)->exists();

        PaymentMethod::create([
            'user_id'                    => $user->id,
            'provider_customer_id'       => $memberId,
            'provider_payment_method_id' => $pmId,
            'payment_type'               => $pmData['payment_method_type'] ?? 'card',
            'last_four_digits'           => $card['last4'] ?? null,
            'brand'                      => $card['brand'] ?? null,
            'expires_at'                 => isset($card['exp_year'], $card['exp_month'])
                ? $card['exp_year'] . '-' . str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT) . '-01'
                : null,
            'is_default'                 => !$hasExisting,
            'is_active'                  => true,
            'metadata'                   => json_encode($pmData),
        ]);

        Log::info('[WhopWebhook] Payment method saved from subscription', [
            'user_id'   => $user->id,
            'member_id' => $memberId,
            'pm_id'     => $pmId,
            'brand'     => $card['brand'] ?? null,
            'last4'     => $card['last4'] ?? null,
        ]);
    }

    private function extractMetadata(array $payload): array
    {
        $candidates = [
            $payload['metadata'] ?? null,
            $payload['checkout_session']['metadata'] ?? null,
            $payload['checkout']['metadata'] ?? null,
            $payload['payment']['metadata'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_array($candidate)) {
                return $candidate;
            }
        }

        return [];
    }

    private function extractUserIdFromMetadata(array $metadata): ?int
    {
        $rawUserId = $metadata['user_id'] ?? $metadata['app_user_id'] ?? null;

        if ($rawUserId === null || $rawUserId === '') {
            return null;
        }

        return is_numeric($rawUserId) ? (int) $rawUserId : null;
    }

    private function metadataFlowMatches(array $metadata, string $expectedFlow): bool
    {
        $flow = $metadata['flow'] ?? null;

        if (!$flow) {
            return true;
        }

        return $flow === $expectedFlow;
    }

    /**
     * Payment failed — update invoice status.
     */
    private function handlePaymentFailed(array $event): void
    {
        $data = $event['data'] ?? [];

        $invoice = Invoice::where('provider_transaction_id', $data['id'] ?? null)->first();

        if ($invoice) {
            $invoice->update([
                'status'              => 'failed',
                'webhook_received_at' => now(),
            ]);

            Log::info('[WhopWebhook] Invoice marked failed', ['invoice_id' => $invoice->id]);
        } else {
            Log::warning('[WhopWebhook] payment.failed: invoice not found', ['payment_id' => $data['id'] ?? null]);
        }
    }
}

