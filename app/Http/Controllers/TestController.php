<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function test(Request $request)
    {
        $keyword = "Mr. Nicola Christiansen";
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
}
