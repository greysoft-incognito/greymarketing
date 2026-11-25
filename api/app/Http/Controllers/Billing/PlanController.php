<?php

namespace App\Http\Controllers\Billing;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanCollection;
use App\Models\Plan;
use Illuminate\Http\Request;

/**
 * Public list of plans for pricing pages.
 */
class PlanController extends Controller
{
    public function __invoke(Request $request)
    {
        $plans = Plan::query()->active()->orderBy('sort')->orderBy('price')->paginate(50);

        return (new PlanCollection($plans))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }
}
