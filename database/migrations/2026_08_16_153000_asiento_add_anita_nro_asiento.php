<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asiento', 'anita_nro_asiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('anita_nro_asiento')
                    ->nullable()
                    ->after('anita_origen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('asiento', 'anita_nro_asiento')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->dropColumn('anita_nro_asiento');
            });
        }
    }
};
