<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging Mercado Libre (Ferli): OAuth + pedidos + líneas + vínculo venta.
 * Sin SoftDeletes. Idempotencia por (seller_id, order_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('mercadolibre_oauth_token')) {
            Schema::create('mercadolibre_oauth_token', function (Blueprint $table) {
                $table->id();
                $table->string('seller_id', 32)->nullable()->index();
                $table->text('access_token');
                $table->text('refresh_token')->nullable();
                $table->string('token_type', 32)->nullable();
                $table->unsignedInteger('expires_in')->nullable();
                $table->timestamp('expires_at')->nullable()->index();
                $table->string('scope', 255)->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('mercadolibre_pedido')) {
            Schema::create('mercadolibre_pedido', function (Blueprint $table) {
                $table->id();
                $table->string('seller_id', 32)->nullable()->index();
                $table->unsignedBigInteger('order_id');
                $table->unsignedBigInteger('pack_id')->nullable()->index();
                $table->string('status', 48)->nullable();
                $table->string('status_detail', 64)->nullable();
                // pendiente | listo | bloqueado_fiscal | facturado | error | omitido | cancelado
                $table->string('estado_erp', 32)->default('pendiente')->index();
                $table->boolean('es_full')->default(false);
                $table->decimal('total', 18, 4)->default(0);
                $table->string('currency', 8)->nullable();
                $table->string('buyer_nickname', 128)->nullable();
                $table->string('buyer_doc', 32)->nullable();
                $table->string('buyer_doc_type', 16)->nullable();
                $table->string('buyer_name', 191)->nullable();
                $table->string('payment_id', 64)->nullable()->index();
                $table->string('billing_info_id', 64)->nullable();
                $table->string('letra_sugerida', 1)->nullable();
                $table->timestamp('paid_at')->nullable()->index();
                $table->timestamp('date_created_ml')->nullable();
                $table->json('buyer_json')->nullable();
                $table->json('billing_json')->nullable();
                $table->json('payments_json')->nullable();
                $table->json('shipping_json')->nullable();
                $table->json('payload_json')->nullable();
                $table->unsignedBigInteger('venta_id')->nullable()->index();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('puntoventa_id_sugerido')->nullable();
                $table->unsignedBigInteger('deposito_id_sugerido')->nullable();
                $table->text('error_mensaje')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamp('facturado_at')->nullable();
                $table->unsignedBigInteger('facturado_por_usuario_id')->nullable();
                $table->timestamp('fiscal_publicado_at')->nullable();
                $table->timestamps();

                $table->unique(['seller_id', 'order_id'], 'uq_ml_pedido_seller_order');
                $table->index(['estado_erp', 'paid_at'], 'ix_ml_pedido_estado_paid');
            });
        }

        if (! Schema::hasTable('mercadolibre_pedido_linea')) {
            Schema::create('mercadolibre_pedido_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mercadolibre_pedido_id');
                $table->string('tipo', 16)->default('producto');
                $table->string('sku', 64)->nullable();
                $table->string('seller_sku', 64)->nullable();
                $table->string('item_id', 32)->nullable();
                $table->string('nombre', 255)->nullable();
                $table->decimal('quantity', 18, 4)->default(0);
                $table->decimal('unit_price', 18, 4)->default(0);
                $table->decimal('full_unit_price', 18, 4)->nullable();
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->unsignedBigInteger('talle_id')->nullable();
                $table->unsignedBigInteger('color_id')->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('mercadolibre_pedido_id', 'fk_ml_linea_pedido')
                    ->references('id')->on('mercadolibre_pedido')->onDelete('cascade');
                $table->index(['mercadolibre_pedido_id', 'orden'], 'ix_ml_linea_pedido_orden');
            });
        }

        if (! Schema::hasTable('mercadolibre_pedido_venta')) {
            Schema::create('mercadolibre_pedido_venta', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mercadolibre_pedido_id');
                $table->unsignedBigInteger('venta_id');
                $table->decimal('total', 18, 4)->default(0);
                $table->timestamps();

                $table->foreign('mercadolibre_pedido_id', 'fk_ml_pedido_venta_pedido')
                    ->references('id')->on('mercadolibre_pedido')->onDelete('cascade');
                $table->unique(['mercadolibre_pedido_id', 'venta_id'], 'uq_ml_pedido_venta');
            });
        }

        if (! Schema::hasTable('mercadolibre_notificacion')) {
            Schema::create('mercadolibre_notificacion', function (Blueprint $table) {
                $table->id();
                $table->string('topic', 64)->nullable()->index();
                $table->string('resource', 255)->nullable();
                $table->string('user_id', 32)->nullable()->index();
                $table->string('application_id', 32)->nullable();
                $table->string('attempts', 16)->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->string('estado', 32)->default('recibida')->index();
                $table->text('error_mensaje')->nullable();
                $table->json('payload_json')->nullable();
                $table->timestamp('procesada_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('mercadolibre_notificacion');
        Schema::dropIfExists('mercadolibre_pedido_venta');
        Schema::dropIfExists('mercadolibre_pedido_linea');
        Schema::dropIfExists('mercadolibre_pedido');
        Schema::dropIfExists('mercadolibre_oauth_token');
    }
};
