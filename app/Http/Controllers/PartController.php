<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Parts/Index', [
            'user' => $request->user()?->only(['id', 'name', 'email']),
        ]);
    }
}
