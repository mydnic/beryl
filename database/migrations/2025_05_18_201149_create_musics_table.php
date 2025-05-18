<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('musics', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->string('artist');
            $table->string('album');
            $table->string('genre');
            $table->string('path');
            $table->date('release_date');
            $table->json('api_results')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('musics');
    }
};
