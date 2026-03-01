<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Services\WhopService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class InvoiceController extends Controller
{
    public function __construct(private WhopService $whop) {}

    /**
     * GET /api/invoices
     * List invoices for the authenticated user.
     */
    public function index(Request $request)
    {
        $invoices = Invoice::where('user_id', $request->user()->id)
            ->with('paymentMethod')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($invoices);
    }

    /**
     * GET /api/invoices/{id}
     * Show a specific invoice.
     */
    public function show(Request $request, int $id)
    {
        $invoice = Invoice::where('user_id', $request->user()->id)
            ->with('paymentMethod')
            ->findOrFail($id);

        return response()->json(['data' => $invoice]);
    }

    /**
     * POST /api/invoices/charge-credits
     * Charge the user's default payment method for a credit purchase.
     * Requires the user to have a `provider_customer_id` (Whop member_id) stored.
     * `amount` is in dollars (e.g. 10.00 for $10).
     */
    public function chargeCredits(Request $request)
    {
        $request->validate([
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'nullable|string',
        ]);

        $user          = $request->user();
        $paymentMethod = PaymentMethod::where('user_id', $user->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$paymentMethod->provider_customer_id) {
            return response()->json(['message' => 'No Whop member ID found for this payment method.'], 422);
        }

        // Create a pending invoice first
        $invoice = Invoice::create([
            'user_id'           => $user->id,
            'payment_method_id' => $paymentMethod->id,
            'invoice_number'    => 'INV-' . strtoupper(Str::random(10)),
            'type'              => 'credit_purchase',
            'amount'            => $request->amount,
            'currency'          => 'usd',
            'status'            => 'pending',
            'description'       => $request->description ?? 'Credit purchase',
            'metadata'          => json_encode([]),
        ]);

        try {
            $payment = $this->whop->createPayment(
                $paymentMethod->provider_customer_id,        // member_id (mber_xxx)
                $paymentMethod->provider_payment_method_id,  // payment_method_id (payt_xxx)
                (float) $request->amount,
                'usd',
                ['invoice_number' => $invoice->invoice_number]
            );

            $invoice->update([
                'status'                  => 'paid',
                'provider_transaction_id' => $payment['id'] ?? null,
                'paid_at'                 => now(),
            ]);
        } catch (\Exception $e) {
            $invoice->update(['status' => 'failed']);
            return response()->json(['message' => 'Payment failed: ' . $e->getMessage()], 422);
        }

        return response()->json(['data' => $invoice->fresh()], 201);
    }

    /**
     * POST /api/invoices/subscribe
     * Subscribe the user to a Whop plan using their default payment method.
     * `plan_id` is the Whop plan ID (e.g. plan_xxx).
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'plan_id'     => 'required|string',
            'description' => 'nullable|string',
        ]);

        $user          = $request->user();
        $paymentMethod = PaymentMethod::where('user_id', $user->id)
            ->where('is_default', true)
            ->where('is_active', true)
            ->firstOrFail();

        if (!$paymentMethod->provider_customer_id) {
            return response()->json(['message' => 'No Whop member ID found for this payment method.'], 422);
        }

        $invoice = Invoice::create([
            'user_id'           => $user->id,
            'payment_method_id' => $paymentMethod->id,
            'invoice_number'    => 'INV-' . strtoupper(Str::random(10)),
            'type'              => 'subscription',
            'amount'            => 0, // updated from webhook or plan lookup
            'currency'          => 'usd',
            'status'            => 'pending',
            'description'       => $request->description ?? 'Plan subscription',
            'metadata'          => json_encode(['plan_id' => $request->plan_id]),
        ]);

        try {
            $payment = $this->whop->createSubscriptionPayment(
                $paymentMethod->provider_customer_id,        // member_id (mber_xxx)
                $paymentMethod->provider_payment_method_id,  // payment_method_id (payt_xxx)
                $request->plan_id
            );

            $invoice->update([
                'status'                  => 'paid',
                'provider_transaction_id' => $payment['id'] ?? null,
                'amount'                  => $payment['amount'] ?? 0,
                'paid_at'                 => now(),
            ]);
        } catch (\Exception $e) {
            $invoice->update(['status' => 'failed']);
            return response()->json(['message' => 'Subscription failed: ' . $e->getMessage()], 422);
        }

        return response()->json(['data' => $invoice->fresh()], 201);
    }
}
