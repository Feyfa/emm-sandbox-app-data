<?php

namespace App\Http\Controllers;

use App\Models\PaymentMethod;
use App\Services\WhopService;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    public function __construct(private WhopService $whop) {}

    /**
     * GET /api/payment-methods
     * List all active payment methods for the authenticated user.
     */
    public function index(Request $request)
    {
        $paymentMethods = PaymentMethod::where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $paymentMethods]);
    }

    /**
     * POST /api/payment-methods/setup-intent
     * Create a Whop checkout configuration in "setup" mode.
     * Returns a `purchase_url` the frontend embeds in an iframe.
     * Whop will create a member automatically when the user completes the form.
     * We pass the internal user_id in metadata so the webhook can identify the user.
     */
    public function setupIntent(Request $request)
    {
        $user = $request->user();

        $checkout = $this->whop->createSetupCheckout([
            'user_id' => (string) $user->id,
            'flow'    => 'save_payment_method',
        ]);

        return response()->json([
            'purchase_url'        => $checkout['purchase_url'],
            'checkout_config_id'  => $checkout['id'],
        ]);
    }

    /**
     * POST /api/payment-methods/subscription-checkout
     * Create a Whop checkout configuration in "payment" mode for subscription plans.
     * Includes user metadata so webhook can map the resulting payment method to our user.
     */
    public function subscriptionCheckout(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => 'required|string',
        ]);

        $user = $request->user();

        $checkout = $this->whop->createPaymentCheckout(
            $validated['plan_id'],
            null,
            [
                'user_id' => (string) $user->id,
                'email'   => (string) $user->email,
                'flow'    => 'subscription_plan',
                'plan_id' => (string) $validated['plan_id'],
            ]
        );

        return response()->json([
            'purchase_url'       => $checkout['purchase_url'] ?? null,
            'checkout_config_id' => $checkout['id'] ?? null,
        ]);
    }

    /**
     * POST /api/payment-methods
     * Called after Whop embed confirms the card save.
     * Stores the payment method in our database.
     */
    public function store(Request $request)
    {
        $request->validate([
            'provider_customer_id'       => 'required|string',
            'provider_payment_method_id' => 'required|string',
            'payment_type'               => 'required|string',
            'last_four_digits'           => 'nullable|string|max:4',
            'brand'                      => 'nullable|string',
            'expires_at'                 => 'nullable|date_format:Y-m',
        ]);

        $user = $request->user();

        // If this is the first card, mark as default
        $hasExisting = PaymentMethod::where('user_id', $user->id)->where('is_active', true)->exists();

        $paymentMethod = PaymentMethod::create([
            'user_id'                    => $user->id,
            'provider_customer_id'       => $request->provider_customer_id,
            'provider_payment_method_id' => $request->provider_payment_method_id,
            'payment_type'               => $request->payment_type,
            'last_four_digits'           => $request->last_four_digits,
            'brand'                      => $request->brand,
            'expires_at'                 => $request->expires_at ? $request->expires_at . '-01' : null,
            'is_default'                 => !$hasExisting,
            'is_active'                  => true,
            'metadata'                   => json_encode($request->metadata ?? []),
        ]);

        return response()->json(['data' => $paymentMethod], 201);
    }

    /**
     * PUT /api/payment-methods/{id}/default
     * Set a payment method as default.
     */
    public function setDefault(Request $request, int $id)
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('user_id', $user->id)
            ->where('id', $id)
            ->where('is_active', true)
            ->firstOrFail();

        // Remove default from all others
        PaymentMethod::where('user_id', $user->id)->update(['is_default' => false]);

        $paymentMethod->update(['is_default' => true]);

        return response()->json(['data' => $paymentMethod]);
    }

    /**
     * DELETE /api/payment-methods/{id}
     * Soft-delete a payment method locally.
     * Whop does not expose a delete endpoint for payment methods; we deactivate locally only.
     */
    public function destroy(Request $request, int $id)
    {
        $user = $request->user();

        $paymentMethod = PaymentMethod::where('user_id', $user->id)
            ->where('id', $id)
            ->firstOrFail();

        $paymentMethod->update(['is_active' => false, 'is_default' => false]);
        $paymentMethod->delete();

        // If deleted was the default, promote the next active card
        $next = PaymentMethod::where('user_id', $user->id)->where('is_active', true)->latest()->first();
        if ($next) {
            $next->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Payment method deleted.']);
    }
}
