<?php

namespace App\Http\Controllers;

use App\Actions\Music\MusicScanner;

class MusicController extends Controller
{
    public function index()
    {
        return inertia('Music/Index');
    }

    public function scan()
    {
        (new MusicScanner())->handle();
        return back();
    }
}
