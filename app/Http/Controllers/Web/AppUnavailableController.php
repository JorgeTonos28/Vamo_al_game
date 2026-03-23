<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class AppUnavailableController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('errors/AppUnavailable');
    }
}
