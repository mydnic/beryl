<?php

namespace App\Actions\Music;

use App\Models\Music;

class RenameMusicFileWithMetadata
{
    /**
     * Rename the music file on disk based on current metadata and setting.
     * Returns true on success (or no-op), false if rename attempted but failed.
     */
    public function handle(Music $music): bool
    {
        if (!(bool) setting('rename_on_apply', false)) {
            return true; // no-op
        }

        $dir = dirname($music->filepath);
        $ext = pathinfo($music->filepath, PATHINFO_EXTENSION);
        $artist = trim((string) ($music->artist ?? '')) ?: 'Unknown Artist';
        $title  = trim((string) ($music->title  ?? '')) ?: 'Unknown Title';

        // Build a readable, safe filename WITHOUT lowercasing and preserving the dash
        $base = $artist . ' - ' . $title;
        // Replace disallowed filename characters with a hyphen (no regex)
        $sanitized = str_replace(['\\', '/', ':', '*', '?', '"', '<', '>', '|'], '-', $base);
        // Replace underscores with spaces, collapse multiple spaces/hyphens, and trim
        $sanitized = str_replace('_', ' ', $sanitized);
        $sanitized = preg_replace('/\s+/', ' ', $sanitized);
        $sanitized = preg_replace('/-+/', '-', $sanitized);
        $readable = trim($sanitized, " .-\t");
        if ($readable === '') {
            $readable = 'Unknown Artist - Unknown Title';
        }
        $candidate = $readable . '.' . $ext;

        $target = $dir . DIRECTORY_SEPARATOR . $candidate;
        if (file_exists($target)) {
            // Prevent collisions by appending (n)
            $i = 1;
            do {
                $candidate = $readable . ' (' . $i . ').' . $ext;
                $target = $dir . DIRECTORY_SEPARATOR . $candidate;
                $i++;
            } while (file_exists($target) && $i < 1000);
        }

        if ($target !== $music->filepath) {
            if (!@rename($music->filepath, $target)) {
                return false;
            }
            $music->filepath = $target;
            $music->save();
        }

        return true;
    }
}
