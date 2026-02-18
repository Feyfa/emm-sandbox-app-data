<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index()
    {
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
        ]);
    }

    public function show($id)
    {
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }

    public function store(Request $request)
    {
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
        ]);
    }

    public function update(Request $request, $id)
    {
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }

    public function destroy($id)
    {
        return response()->json([
            'message' => 'ini adalah function ' . __FUNCTION__,
            'id' => $id,
        ]);
    }
}
