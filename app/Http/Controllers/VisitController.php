<?php

namespace App\Http\Controllers;

use App\Services\Analytics\VisitorTracker;
use Illuminate\Http\Request;
use Throwable;

class VisitController extends Controller
{
    public function __invoke(Request $request, VisitorTracker $tracker)
    {
        try {
            $tracker->confirm($request);
        } catch (Throwable $exception) {
            VisitorTracker::failure($exception);
        }

        return response()->noContent()->header('Cache-Control', 'no-store');
    }
}
