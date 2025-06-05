<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('music', function (Blueprint $table) {
            $table->id();
            $table->string('filepath');
            $table->string('artist')->nullable();
            $table->string('title')->nullable();
            $table->string('album')->nullable();
            $table->integer('release_year')->nullable();
            $table->string('genre')->nullable();
            $table->json('metadata')->nullable();
            $table->json('musicbrainz_data')->nullable();
            $table->boolean('musicbrainz_no_result')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('music');
    }
};
