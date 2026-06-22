<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('movimientostock', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientostock', 'asiento_id')) {
                $table->unsignedBigInteger('asiento_id')->nullable()->after('usuario_id');
                $table->foreign('asiento_id', 'fk_movimientostock_asiento')
                    ->references('id')->on('asiento')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
            if (! Schema::hasColumn('movimientostock', 'centrocosto_destino_id')) {
                $table->unsignedBigInteger('centrocosto_destino_id')->nullable()->after('asiento_id');
                $table->foreign('centrocosto_destino_id', 'fk_movimientostock_centrocosto_destino')
                    ->references('id')->on('centrocosto')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });
    }

    public function down(): void
    {
        Schema::table('movimientostock', function (Blueprint $table) {
            if (Schema::hasColumn('movimientostock', 'centrocosto_destino_id')) {
                $table->dropForeign('fk_movimientostock_centrocosto_destino');
                $table->dropColumn('centrocosto_destino_id');
            }
            if (Schema::hasColumn('movimientostock', 'asiento_id')) {
                $table->dropForeign('fk_movimientostock_asiento');
                $table->dropColumn('asiento_id');
            }
        });
    }
};
