<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class Main extends Controller
{
    public function home(Request $request) {
        $test = "esto es una variable desde el controlador";
        return Inertia::render('Home', ['test' => $test]);
    }
}
