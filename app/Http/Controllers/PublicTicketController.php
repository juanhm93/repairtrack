<?php

namespace App\Http\Controllers;

use App\Services\PublicTicketService;
use Inertia\Inertia;
use Inertia\Response;

class PublicTicketController extends Controller
{
    /**
     * Public ticket status page (spec 03).
     */
    public function show(string $token, PublicTicketService $tickets): Response
    {
        return Inertia::render('public/TicketStatus', $tickets->pageProps($token));
    }
}
