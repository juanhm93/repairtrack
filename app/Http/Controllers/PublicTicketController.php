<?php

namespace App\Http\Controllers;

class PublicTicketController extends Controller
{
    /**
     * Public ticket status page (spec 03).
     */
    public function show(string $token): never
    {
        abort(404);
    }
}
