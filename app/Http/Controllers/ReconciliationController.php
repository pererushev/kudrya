<?php

namespace App\Http\Controllers;

use App\Domain\ReconciliationService;
use Illuminate\Http\JsonResponse;

class ReconciliationController extends Controller
{
    public function __invoke(ReconciliationService $reconciliation): JsonResponse
    {
        return response()->json($reconciliation->report());
    }
}
