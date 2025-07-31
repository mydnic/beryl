<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Music Metadata Service
    |--------------------------------------------------------------------------
    |
    | This option controls which service will be used for searching music metadata.
    | Supported: "musicbrainz", "deezer", "spotify"
    |
    */
    'metadata_service' => env('MUSIC_METADATA_SERVICE', 'spotify'),

    /*
    |--------------------------------------------------------------------------
    | API Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Some APIs require throttling to avoid rate limits
    |
    */
    'musicbrainz' => [
        'throttle_time' => env('MUSICBRAINZ_THROTTLE_TIME', 1), // seconds
        'user_agent' => env('MUSICBRAINZ_USER_AGENT', 'Beryl/1.0 ( rigoclement@mydnic.be )'),
    ],
];
