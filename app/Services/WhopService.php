<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhopService
{
    private string $apiKey;
    private string $companyId;
    private string $baseUrl = 'https://sandbox-api.whop.com/api/v1';

    public function __construct()
    {
        $this->apiKey    = config('services.whop.api_key');
        $this->companyId = config('services.whop.company_id');
    }

    // ─── HTTP Helper ──────────────────────────────────────────────────────────

    private function http()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    // ─── Checkout Configuration ───────────────────────────────────────────────

    /**
     * Create a checkout configuration in "setup" mode.
     * Used to let the user save a payment method without charging them.
     * Response includes a `purchase_url` to be embedded in an iframe.
     */
    public function createSetupCheckout(array $metadata = []): array
    {
        $payload = [
            'mode'     => 'setup',
            'currency' => 'usd',
        ];

        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->http()->post('/checkout_configurations', $payload);

        if ($response->failed()) {
            Log::error('[WhopService] createSetupCheckout failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to create setup checkout: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Create a checkout configuration in "payment" mode using an existing plan.
     * Used for subscription flow via embed.
     */
    public function createPaymentCheckout(string $planId, ?string $redirectUrl = null, array $metadata = []): array
    {
        $payload = [
            'mode'    => 'payment',
            'plan_id' => $planId,
        ];

        if ($redirectUrl) {
            $payload['redirect_url'] = $redirectUrl;
        }

        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->http()->post('/checkout_configurations', $payload);

        if ($response->failed()) {
            Log::error('[WhopService] createPaymentCheckout failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to create payment checkout: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Plans ────────────────────────────────────────────────────────────────

    /**
     * List all plans for the company.
     */
    public function getPlans(): array
    {
        $response = $this->http()->get('/plans', [
            'company_id' => $this->companyId,
        ]);

        if ($response->failed()) {
            Log::error('[WhopService] getPlans failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to get plans: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Retrieve a single plan by ID.
     */
    public function getPlan(string $planId): array
    {
        $response = $this->http()->get("/plans/{$planId}");

        if ($response->failed()) {
            Log::error('[WhopService] getPlan failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to get plan: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Payments ─────────────────────────────────────────────────────────────

    /**
     * Create a one-time payment for a member using their saved payment method.
     * Amount is in dollars (e.g. 10.00 for $10).
     * `member_id` is the Whop `mber_xxx` ID stored in `provider_customer_id`.
     */
    public function createPayment(string $memberId, string $paymentMethodId, float $amount, string $currency = 'usd', array $metadata = []): array
    {
        $payload = [
            'company_id'        => $this->companyId,
            'member_id'         => $memberId,
            'payment_method_id' => $paymentMethodId,
            'plan'              => [
                'currency'      => $currency,
                'initial_price' => $amount,
                'plan_type'     => 'one_time',
            ],
        ];

        if (!empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        $response = $this->http()->post('/payments', $payload);

        if ($response->failed()) {
            Log::error('[WhopService] createPayment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to create payment: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Create a subscription payment for a member using an existing Whop plan ID.
     */
    public function createSubscriptionPayment(string $memberId, string $paymentMethodId, string $planId): array
    {
        $payload = [
            'company_id'        => $this->companyId,
            'member_id'         => $memberId,
            'payment_method_id' => $paymentMethodId,
            'plan_id'           => $planId,
        ];

        $response = $this->http()->post('/payments', $payload);

        if ($response->failed()) {
            Log::error('[WhopService] createSubscriptionPayment failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to create subscription payment: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Payment Methods ──────────────────────────────────────────────────────

    /**
     * List payment methods for a member.
     */
    public function listPaymentMethods(string $memberId): array
    {
        $response = $this->http()->get('/payment_methods', [
            'member_id' => $memberId,
        ]);

        if ($response->failed()) {
            Log::error('[WhopService] listPaymentMethods failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to list payment methods: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Setup Intents ────────────────────────────────────────────────────────

    /**
     * List setup intents for the company.
     * Useful for verifying completion after embed flow.
     */
    public function listSetupIntents(array $filters = []): array
    {
        $params = array_merge(['company_id' => $this->companyId], $filters);

        $response = $this->http()->get('/setup_intents', $params);

        if ($response->failed()) {
            Log::error('[WhopService] listSetupIntents failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            throw new \Exception('Failed to list setup intents: ' . $response->body());
        }

        return $response->json();
    }

    // ─── Webhook ──────────────────────────────────────────────────────────────

    /**
     * Verify that a webhook request is genuinely from Whop.
     * Whop uses Svix webhook signature format:
     *   - Signature format: "v1,<base64_signature>"
     *   - Signed message: "{webhook-id}.{timestamp}.{payload}"
     *   - Algorithm: HMAC-SHA256
     */
    public function verifyWebhookSignature(string $payload, string $signature, string $timestamp, string $webhookId): bool 
    {
        $secret = config('services.whop.webhook_secret');
        
        // Extract signature(s) from "v1,sig1 v1,sig2" format
        $signatures = [];
        foreach (explode(' ', $signature) as $sig) {
            if (str_starts_with($sig, 'v1,')) {
                $signatures[] = substr($sig, 3); // Remove "v1," prefix
            }
        }
        
        if (empty($signatures)) {
            return false;
        }
        
        // Construct signed message: {webhook-id}.{timestamp}.{payload}
        $signedMessage = "{$webhookId}.{$timestamp}.{$payload}";
        
        // Compute expected signature
        $expectedSignature = base64_encode(
            hash_hmac('sha256', $signedMessage, $secret, true)
        );
        
        // Check if any provided signature matches
        foreach ($signatures as $sig) {
            if (hash_equals($expectedSignature, $sig)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the configured company ID.
     */
    public function getCompanyId(): string
    {
        return $this->companyId;
    }
}
