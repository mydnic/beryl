<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('music', function (Blueprint $table) {
            $table->boolean('need_fixing')->default(true)->index();
        });

        // Ensure existing records get the default true
        DB::table('music')->whereNull('need_fixing')->update(['need_fixing' => true]);
    }

    public function down(): void
    {
        Schema::table('music', function (Blueprint $table) {
            $table->dropIndex(['need_fixing']);
            $table->dropColumn('need_fixing');
        });
    }
};
