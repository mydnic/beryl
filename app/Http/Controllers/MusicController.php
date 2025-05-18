<?php

namespace App\Http\Controllers;

class MusicController extends Controller
{
    public function index()
    {
        return inertia('Music/Index');
    }

    public function scan()
    {
        return inertia('Music/Index');
    }
}
