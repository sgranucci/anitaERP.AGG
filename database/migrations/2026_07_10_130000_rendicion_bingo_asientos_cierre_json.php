<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
            if (! Schema::hasColumn('rendicion_bingo_caja', 'asientos_cierre_ids_json')) {
                $table->json('asientos_cierre_ids_json')->nullable()->after('asiento_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rendicion_bingo_caja', function (Blueprint $table) {
            if (Schema::hasColumn('rendicion_bingo_caja', 'asientos_cierre_ids_json')) {
                $table->dropColumn('asientos_cierre_ids_json');
            }
        });
    }
};
