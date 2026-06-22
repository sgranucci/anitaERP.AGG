<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('transferencia_mercaderia', 'centrocosto_destino_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->unsignedBigInteger('centrocosto_destino_id')
                    ->nullable()
                    ->after('deposito_destino_id');

                $table->foreign('centrocosto_destino_id', 'fk_tm_centrocosto_destino')
                    ->references('id')
                    ->on('centrocosto')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('transferencia_mercaderia', 'centrocosto_destino_id')) {
            Schema::table('transferencia_mercaderia', function (Blueprint $table) {
                $table->dropForeign('fk_tm_centrocosto_destino');
                $table->dropColumn('centrocosto_destino_id');
            });
        }
    }
};
