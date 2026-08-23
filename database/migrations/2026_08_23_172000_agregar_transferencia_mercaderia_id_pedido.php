<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link pedido → TM de reposición DESPACHO (El Bierzo). Nullable en todos los clientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pedido') || ! Schema::hasTable('transferencia_mercaderia')) {
            return;
        }
        if (Schema::hasColumn('pedido', 'transferencia_mercaderia_id')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            $table->unsignedBigInteger('transferencia_mercaderia_id')->nullable()->after('zonavta_id');
            $table->foreign('transferencia_mercaderia_id', 'fk_pedido_transferencia_mercaderia')
                ->references('id')
                ->on('transferencia_mercaderia')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pedido') || ! Schema::hasColumn('pedido', 'transferencia_mercaderia_id')) {
            return;
        }

        Schema::table('pedido', function (Blueprint $table) {
            $table->dropForeign('fk_pedido_transferencia_mercaderia');
            $table->dropColumn('transferencia_mercaderia_id');
        });
    }
};
