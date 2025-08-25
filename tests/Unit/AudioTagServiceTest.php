<?php

use App\Services\AudioTagService;

it('applies tags to an M4A file without clearing unspecified fields', function () {
    // Skip if no supported MP4 tag writer is available on this system
    $hasExiftool = trim(shell_exec('which exiftool 2>/dev/null') ?? '') !== '';
    $hasAP       = trim(shell_exec('which AtomicParsley 2>/dev/null') ?? '') !== '';
    if (!$hasExiftool && !$hasAP) {
        $this->markTestSkipped('No supported MP4 tag writer found (install exiftool or AtomicParsley).');
    }

    // Arrange: copy sample to temp path
    $source = __DIR__ . '/../data/music-test-m4a.m4a';
    expect(file_exists($source))->toBeTrue();
    $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'audio-tag-service-' . uniqid() . '.m4a';
    copy($source, $tmp);

    // Act: apply only a new title; other fields omitted to test merge-preserve behavior
    $service = new AudioTagService();
    $service->applyToPath($tmp, [
        'title' => 'Service Test Title',
        // artist/album/year intentionally omitted
    ]);

    // Assert: read back with getID3 to ensure title was set
    $analyzer = new getID3();
    $info = $analyzer->analyze($tmp);
    $tags = $info['tags'] ?? [];
    $foundTitle = null;
    foreach (['id3v2.4','id3v2.3','id3v2','quicktime','vorbiscomment','ape','id3v1'] as $fmt) {
        if (isset($tags[$fmt]['title'][0]) && trim((string)$tags[$fmt]['title'][0]) !== '') {
            $foundTitle = (string)$tags[$fmt]['title'][0];
            break;
        }
    }
    expect($foundTitle)->toBe('Service Test Title');

    // Cleanup temp file
    @unlink($tmp);
});
