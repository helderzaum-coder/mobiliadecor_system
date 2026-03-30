<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class BlingWebhookController extends Controller
{
    public function handle(Request $request, string $account): \Illuminate\Http\JsonResponse
    {
        return response()->json(['status' => 'disabled']);
    }
}
