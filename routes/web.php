<?php

use App\Services\WhopService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
Route::get('/jidan', function () {
    return response()->json([
        'first_name' => 'Muhammad',
        'last_name' => 'Jidann',
    ]);
});
Route::get('/agies', function () {
    return response()->json([
        'first_name' => 'Agies',
        'last_name' => 'Wahyudi',
    ]);
});

Route::get('/test/whop-one-time', function (Request $request, WhopService $whop) {
    abort_unless(config('app.debug'), 404);

    $memberId = 'mber_uaCp2JaCxOlT9';
    $paymentMethodId = 'payt_dakOkbQeaOTKR';
    $amount = (float) ($request->query('amount', 7));

    try {
        $result = $whop->createPayment(
            $memberId,
            $paymentMethodId,
            $amount,
            'usd',
            [
                'flow' => 'manual_web_test_one_time',
                'source' => 'routes/web.php',
            ]
        );

        return response()->json([
            'ok' => true,
            'company_id' => 'biz_fwPEdL9u81G2vf',
            'member_id' => $memberId,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'result' => $result,
        ]);
    } catch (\Throwable $e) {
        return response()->json([
            'ok' => false,
            'company_id' => 'biz_fwPEdL9u81G2vf',
            'member_id' => $memberId,
            'payment_method_id' => $paymentMethodId,
            'amount' => $amount,
            'error' => $e->getMessage(),
        ], 500);
    }
});
