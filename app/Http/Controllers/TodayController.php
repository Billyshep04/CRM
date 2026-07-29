<?php

namespace App\Http\Controllers;

use App\Services\Sales\TodayActionFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TodayController extends Controller
{
    public function __invoke(Request $request, TodayActionFeed $feed): JsonResponse
    {
        return response()->json(['data' => $feed->for($request->user())]);
    }
}
