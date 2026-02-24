<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CampaignController extends Controller
{
    public function index()
    {
        Log::info('ini adalah function ' . __FUNCTION__);
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
        ]);
    }

    public function show($id)
    {
        Log::info('ini adalah function ' . __FUNCTION__);
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }

    public function store(Request $request)
    {
        Log::info('ini adalah function ' . __FUNCTION__);
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
        ]);
    }

    public function update(Request $request, $id)
    {
        Log::info('ini adalah function ' . __FUNCTION__);
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }

    public function destroy($id)
    {
        Log::info('ini adalah function ' . __FUNCTION__);
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }
}
