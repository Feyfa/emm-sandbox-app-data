<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WhopService;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test(Request $request)
    {
        $keyword = "Dr. Norberto Eichmann IV";
        $key = "name";

        // search based on database
        $start_time = microtime(true);
        $users1 = User::where($key, 'like', "%$keyword%")
            ->get()
            ->toArray();
        $end_time = microtime(true);
        $execution_time_first = round($end_time - $start_time, 4);

        // call scout search
        $start_time = microtime(true);
        $users2 = User::search($keyword)
            ->where($key, $keyword) // Filter exact match
            ->get()
            ->toArray();
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 4);
        // call scout search

        // response
        $response = [
            'based_on_database' => [
                'users' => $users1,
                'execution_time' => $execution_time_first,
            ],
            'based_on_scout' => [
                'users' => $users2,
                'execution_time' => $execution_time,
            ],
        ];
        info($response);
        return response()->json($response);
        // response
    }

    public function whopOneTime(Request $request, WhopService $whop)
    {
        abort_unless(config('app.debug'), 404);

        $memberId = (string) $request->query('member_id', 'mber_uaCp2JaCxOlT9');
        $paymentMethodId = (string) $request->query('payment_method_id', 'payt_dakOkbQeaOTKR');
        $amount = (float) $request->query('amount', 5.72);

        try {
            $result = $whop->createPayment(
                $memberId,
                $paymentMethodId,
                $amount,
                'usd',
                [
                    'flow' => 'manual_web_test_one_time',
                    'source' => 'TestController::whopOneTime',
                ]
            );

            return response()->json([
                'ok' => true,
                'company_id' => $whop->getCompanyId(),
                'member_id' => $memberId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'company_id' => $whop->getCompanyId(),
                'member_id' => $memberId,
                'payment_method_id' => $paymentMethodId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function whopTransferSales(Request $request, WhopService $whop)
    {
        abort_unless(config('app.debug'), 404);

        $destinationId = (string) $request->query('destination_id', 'user_HXkCqKEhgoKb1');
        $amount = (float) $request->query('amount', 5.00);
        $notes = (string) $request->query('notes', 'Sales commission test transfer');

        try {
            $result = $whop->createTransfer(
                $destinationId,
                $amount,
                'usd',
                $notes
            );

            return response()->json([
                'ok' => true,
                'company_id' => $whop->getCompanyId(),
                'destination_id' => $destinationId,
                'amount' => $amount,
                'notes' => $notes,
                'result' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'company_id' => $whop->getCompanyId(),
                'destination_id' => $destinationId,
                'amount' => $amount,
                'notes' => $notes,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
