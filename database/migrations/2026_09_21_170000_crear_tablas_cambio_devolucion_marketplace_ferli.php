<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legajo RMA cambio/devolución marketplace — solo Calzados Ferli.
 * No afecta Facturación POS gastronomía (AGG).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('cambio_devolucion_marketplace')) {
            Schema::create('cambio_devolucion_marketplace', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('numero')->unique();
                $table->string('canal', 20)->default('tiendanube');
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('usuario_alta_id');
                $table->unsignedBigInteger('tiendanube_pedido_id')->nullable();
                $table->unsignedBigInteger('venta_original_id');
                $table->unsignedBigInteger('venta_reemplazo_id')->nullable();
                $table->unsignedBigInteger('venta_nc_id')->nullable();
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->string('receptor_nombre', 120)->nullable();
                $table->string('receptor_documento', 30)->nullable();
                $table->string('estado', 40)->default('borrador');
                $table->string('motivo_codigo', 40)->nullable();
                $table->string('motivo', 255)->nullable();
                $table->string('disposicion', 30)->nullable();
                $table->decimal('diferencia_importe', 14, 2)->default(0);
                $table->string('diferencia_sentido', 30)->nullable();
                $table->timestamp('compensacion_registrada_at')->nullable();
                $table->text('compensacion_observacion')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('local_venta_id', 'fk_cdm_local')
                    ->references('id')->on('local_venta')->onDelete('restrict');
                $table->foreign('empresa_id', 'fk_cdm_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('usuario_alta_id', 'fk_cdm_usuario')
                    ->references('id')->on('usuario')->onDelete('restrict');
                $table->foreign('venta_original_id', 'fk_cdm_venta_orig')
                    ->references('id')->on('venta')->onDelete('restrict');
                $table->foreign('venta_reemplazo_id', 'fk_cdm_venta_reemp')
                    ->references('id')->on('venta')->onDelete('restrict');
                $table->foreign('venta_nc_id', 'fk_cdm_venta_nc')
                    ->references('id')->on('venta')->onDelete('restrict');
                $table->foreign('cliente_id', 'fk_cdm_cliente')
                    ->references('id')->on('cliente')->onDelete('restrict');

                $table->index(['estado', 'local_venta_id'], 'idx_cdm_estado_local');
                $table->index(['canal', 'estado'], 'idx_cdm_canal_estado');
            });
        }

        if (! Schema::hasTable('cambio_devolucion_marketplace_linea')) {
            Schema::create('cambio_devolucion_marketplace_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cambio_id');
                $table->string('tipo', 20);
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('talle_id')->nullable();
                $table->unsignedBigInteger('color_id')->nullable();
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->decimal('cantidad', 14, 4)->default(1);
                $table->decimal('precio_unitario', 14, 4)->default(0);
                $table->unsignedBigInteger('venta_emision_id')->nullable();
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();

                $table->foreign('cambio_id', 'fk_cdml_cambio')
                    ->references('id')->on('cambio_devolucion_marketplace')->onDelete('cascade');
                $table->foreign('articulo_id', 'fk_cdml_articulo')
                    ->references('id')->on('articulo')->onDelete('restrict');
                $table->index(['cambio_id', 'tipo'], 'idx_cdml_cambio_tipo');
            });
        }

        if (! Schema::hasTable('cambio_devolucion_marketplace_estado')) {
            Schema::create('cambio_devolucion_marketplace_estado', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cambio_id');
                $table->timestamp('fecha');
                $table->string('estado', 40);
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('cambio_id', 'fk_cdme_cambio')
                    ->references('id')->on('cambio_devolucion_marketplace')->onDelete('cascade');
                $table->index(['cambio_id', 'fecha'], 'idx_cdme_cambio_fecha');
            });
        }

        if (! Schema::hasTable('cambio_devolucion_marketplace_archivo')) {
            Schema::create('cambio_devolucion_marketplace_archivo', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cambio_id');
                $table->string('nombrearchivo', 255);
                $table->timestamps();

                $table->foreign('cambio_id', 'fk_cdma_cambio')
                    ->references('id')->on('cambio_devolucion_marketplace')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('cambio_devolucion_marketplace_archivo');
        Schema::dropIfExists('cambio_devolucion_marketplace_estado');
        Schema::dropIfExists('cambio_devolucion_marketplace_linea');
        Schema::dropIfExists('cambio_devolucion_marketplace');
    }
};
