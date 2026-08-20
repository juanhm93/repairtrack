<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the technician summary dashboard.
     */
    public function index(Request $request, DashboardService $dashboard): Response
    {
        $user = $request->user();

        assert($user instanceof User);

        return Inertia::render('Dashboard', $dashboard->snapshotForUser($user));
    }
}
