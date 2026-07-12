<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('articulo_movimiento') && ! Schema::hasColumn('articulo_movimiento', 'numeroparte')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->string('numeroparte', 50)->nullable()->after('articulo_id');
                $table->index(['numeroparte', 'deposito_id'], 'idx_artmov_numeroparte_deposito');
            });
        }

        if (Schema::hasTable('transferencia_mercaderia_articulo') && ! Schema::hasColumn('transferencia_mercaderia_articulo', 'numeroparte')) {
            Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
                $table->string('numeroparte', 50)->nullable()->after('articulo_destino_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('articulo_movimiento') && Schema::hasColumn('articulo_movimiento', 'numeroparte')) {
            Schema::table('articulo_movimiento', function (Blueprint $table) {
                $table->dropIndex('idx_artmov_numeroparte_deposito');
                $table->dropColumn('numeroparte');
            });
        }

        if (Schema::hasTable('transferencia_mercaderia_articulo') && Schema::hasColumn('transferencia_mercaderia_articulo', 'numeroparte')) {
            Schema::table('transferencia_mercaderia_articulo', function (Blueprint $table) {
                $table->dropColumn('numeroparte');
            });
        }
    }
};
