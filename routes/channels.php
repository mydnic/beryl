<?php

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('music-added', function (User $user) {
    return true;
});
