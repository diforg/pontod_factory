<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FilamentController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Filaments/Index', [
            'user' => $request->user()?->only(['id', 'name', 'email']),
        ]);
    }
}
