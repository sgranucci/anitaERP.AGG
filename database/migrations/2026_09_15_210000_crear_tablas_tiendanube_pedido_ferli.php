<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging de pedidos Tiendanube para facturación en anitaERP (Ferli).
 * Sin SoftDeletes. Idempotencia por (store_id, tiendanube_order_id).
 * Gate: solo Corre en Calzados Ferli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('tiendanube_pedido')) {
            Schema::create('tiendanube_pedido', function (Blueprint $table) {
                $table->id();
                $table->string('store_id', 32);
                $table->unsignedBigInteger('tiendanube_order_id');
                $table->string('order_number', 32)->nullable();
                $table->string('payment_status', 32)->nullable();
                $table->string('status', 32)->nullable();
                // pendiente | listo | bloqueado_fiscal | facturado | error | omitido
                $table->string('estado_erp', 32)->default('pendiente');
                $table->decimal('total', 18, 4)->default(0);
                $table->string('currency', 8)->nullable();
                $table->string('customer_name', 191)->nullable();
                $table->string('customer_email', 191)->nullable();
                $table->string('customer_doc', 32)->nullable();
                $table->string('customer_doc_type', 16)->nullable();
                $table->string('gateway', 64)->nullable();
                $table->string('gateway_name', 128)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('created_at_tn')->nullable();
                $table->json('customer_json')->nullable();
                $table->json('shipping_json')->nullable();
                $table->json('payment_json')->nullable();
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('puntoventa_id_sugerido')->nullable();
                $table->unsignedBigInteger('deposito_id_sugerido')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('facturado_at')->nullable();
                $table->unsignedBigInteger('facturado_por_usuario_id')->nullable();
                $table->timestamps();

                $table->unique(['store_id', 'tiendanube_order_id'], 'uq_tn_pedido_store_order');
                $table->index(['estado_erp', 'paid_at'], 'ix_tn_pedido_estado_paid');
                $table->index('venta_id', 'ix_tn_pedido_venta');
            });
        }

        if (! Schema::hasTable('tiendanube_pedido_linea')) {
            Schema::create('tiendanube_pedido_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tiendanube_pedido_id');
                $table->string('tipo', 16)->default('producto'); // producto|envio|descuento|otro
                $table->string('sku', 64)->nullable();
                $table->string('nombre', 255)->nullable();
                $table->decimal('quantity', 18, 4)->default(0);
                $table->decimal('price', 18, 4)->default(0);
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->unsignedBigInteger('product_id')->nullable();
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->unsignedBigInteger('talle_id')->nullable();
                $table->unsignedBigInteger('color_id')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('tiendanube_pedido_id', 'fk_tn_linea_pedido')
                    ->references('id')->on('tiendanube_pedido')->onDelete('cascade');
                $table->index(['tiendanube_pedido_id', 'orden'], 'ix_tn_linea_pedido_orden');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('tiendanube_pedido_linea');
        Schema::dropIfExists('tiendanube_pedido');
    }
};
