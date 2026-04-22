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
        if (config('app.empresa') !== 'EL BIERZO') {
            return;
        }

        Schema::table('articulo', function (Blueprint $table) {
            if (!Schema::hasColumn('articulo', 'divide')) {
                $table->string('divide', 50)->nullable()->after('estado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (config('app.empresa') !== 'EL BIERZO') {
            return;
        }

        Schema::table('articulo', function (Blueprint $table) {
            if (Schema::hasColumn('articulo', 'divide')) {
                $table->dropColumn('divide');
            }
        });
    }
};
