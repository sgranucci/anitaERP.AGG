<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Facturación parcial de pedidos Tiendanube: cantidad ya facturada por línea
 * y una fila por cada venta emitida. Sin SoftDeletes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tiendanube_pedido_linea')
            && ! Schema::hasColumn('tiendanube_pedido_linea', 'cantidad_facturada')) {
            Schema::table('tiendanube_pedido_linea', function (Blueprint $table) {
                $table->decimal('cantidad_facturada', 18, 4)->default(0)->after('quantity');
            });
        }

        if (! Schema::hasTable('tiendanube_pedido') || Schema::hasTable('tiendanube_pedido_venta')) {
            return;
        }

        Schema::create('tiendanube_pedido_venta', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tiendanube_pedido_id');
            $table->unsignedBigInteger('venta_id');
            $table->decimal('total', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['tiendanube_pedido_id', 'venta_id'], 'uq_tn_pedido_venta');
            $table->index('venta_id', 'ix_tn_pedido_venta_venta');
            $table->foreign('tiendanube_pedido_id', 'fk_tn_pedventa_pedido')
                ->references('id')->on('tiendanube_pedido')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiendanube_pedido_venta');

        if (Schema::hasTable('tiendanube_pedido_linea')
            && Schema::hasColumn('tiendanube_pedido_linea', 'cantidad_facturada')) {
            Schema::table('tiendanube_pedido_linea', function (Blueprint $table) {
                $table->dropColumn('cantidad_facturada');
            });
        }
    }
};
