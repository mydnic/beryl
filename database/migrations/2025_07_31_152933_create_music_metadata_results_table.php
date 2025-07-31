<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('music_metadata_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('music_id')->constrained()->onDelete('cascade');
            $table->string('service'); // musicbrainz, deezer, etc.
            $table->string('search_type'); // metadata, filename
            $table->string('title')->nullable();
            $table->string('artist')->nullable();
            $table->string('album')->nullable();
            $table->integer('release_year')->nullable();
            $table->decimal('score', 5, 2)->nullable(); // confidence score
            $table->string('external_id')->nullable(); // ID from the service
            $table->json('raw_data')->nullable(); // original API response
            $table->timestamps();

            $table->index(['music_id', 'service', 'search_type']);
            $table->index(['score']);
        });

        Schema::table('music', function (Blueprint $table) {
            $table->dropColumn('api_results');
            $table->dropColumn('results');
            $table->dropColumn('musicbrainz_no_result');
            $table->dropColumn('deezer_no_result');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('music_metadata_results');
    }
};
