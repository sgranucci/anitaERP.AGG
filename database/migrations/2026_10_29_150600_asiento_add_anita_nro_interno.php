<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asiento', 'anita_nro_interno')) {
            Schema::table('asiento', function (Blueprint $table) {
                $table->unsignedBigInteger('anita_nro_interno')
                    ->nullable()
                    ->after('anita_nro_asiento');
                $table->index(
                    ['empresa_id', 'anita_nro_interno'],
                    'asiento_empresa_anita_nro_interno_idx',
                );
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('asiento', 'anita_nro_interno')) {
            return;
        }

        Schema::table('asiento', function (Blueprint $table) {
            $table->dropIndex('asiento_empresa_anita_nro_interno_idx');
            $table->dropColumn('anita_nro_interno');
        });
    }
};
