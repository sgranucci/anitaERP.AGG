<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Crown: cuenta abierta + comanda (sin SoftDeletes; auditoría OwenIt en modelos).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gastro_crown_cuenta')) {
            Schema::create('gastro_crown_cuenta', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('configuracion_puntoventa_gastronomia_id')->nullable();
                $table->string('identificador_pc', 100)->nullable();
                $table->string('modo_pos', 20)->default('resto');
                $table->unsignedBigInteger('sala_id')->nullable();
                $table->unsignedBigInteger('mesa_gastronomia_id')->nullable();
                $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
                $table->unsignedInteger('cubiertos')->default(0);
                $table->string('estado', 20)->default('abierta'); // abierta|cobrando|cerrada|anulada
                $table->unsignedBigInteger('cliente_id')->nullable();
                $table->unsignedBigInteger('listaprecio_id')->nullable();
                $table->unsignedBigInteger('descuento_gastronomia_id')->nullable();
                $table->decimal('porcentaje_descuento', 8, 2)->default(0);
                $table->string('detalle_descuento', 80)->nullable();
                $table->string('habitacion', 20)->nullable();
                $table->string('reserva_hotel', 30)->nullable();
                $table->string('tarjeta_gst', 30)->nullable();
                $table->string('observacion', 255)->nullable();
                $table->unsignedBigInteger('venta_id')->nullable();
                $table->unsignedBigInteger('usuario_apertura_id')->nullable();
                $table->timestamp('cerrada_at')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_gc_cuenta_empresa')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('configuracion_puntoventa_gastronomia_id', 'fk_gc_cuenta_config_pv')
                    ->references('id')->on('configuracion_puntoventa_gastronomia')->nullOnDelete();
                $table->foreign('sala_id', 'fk_gc_cuenta_sala')->references('id')->on('sala')->nullOnDelete();
                $table->foreign('mesa_gastronomia_id', 'fk_gc_cuenta_mesa')->references('id')->on('mesa_gastronomia')->nullOnDelete();
                $table->foreign('mozo_gastronomia_id', 'fk_gc_cuenta_mozo')->references('id')->on('mozo_gastronomia')->nullOnDelete();
                $table->index(['empresa_id', 'estado'], 'idx_gc_cuenta_empresa_estado');
                $table->index(['mesa_gastronomia_id', 'estado'], 'idx_gc_cuenta_mesa_estado');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('gastro_crown_cuenta_linea')) {
            Schema::create('gastro_crown_cuenta_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gastro_crown_cuenta_id');
                $table->unsignedInteger('orden')->default(1);
                $table->unsignedBigInteger('articulo_id')->nullable();
                $table->string('sku', 30)->nullable();
                $table->string('descripcion', 120);
                $table->decimal('cantidad', 12, 3)->default(1);
                $table->decimal('precio', 14, 4)->default(0);
                $table->decimal('descuento_importe', 14, 4)->default(0);
                $table->unsignedBigInteger('descuento_gastronomia_id')->nullable();
                $table->string('observacion', 255)->nullable();
                $table->boolean('marcado')->default(false);
                $table->boolean('es_separador')->default(false);
                $table->unsignedBigInteger('area_comanda_gastronomia_id')->nullable();
                $table->unsignedBigInteger('gastro_crown_comanda_id')->nullable();
                $table->timestamp('fired_at')->nullable();
                $table->timestamp('fired_postre_at')->nullable();
                $table->json('modifiers')->nullable();
                $table->timestamps();

                $table->foreign('gastro_crown_cuenta_id', 'fk_gc_linea_cuenta')
                    ->references('id')->on('gastro_crown_cuenta')->cascadeOnDelete();
                $table->foreign('articulo_id', 'fk_gc_linea_articulo')
                    ->references('id')->on('articulo')->nullOnDelete();
                $table->foreign('area_comanda_gastronomia_id', 'fk_gc_linea_area')
                    ->references('id')->on('area_comanda_gastronomia')->nullOnDelete();
                $table->index(['gastro_crown_cuenta_id', 'orden'], 'idx_gc_linea_cuenta_orden');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('gastro_crown_comanda')) {
            Schema::create('gastro_crown_comanda', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('gastro_crown_cuenta_id');
                $table->string('numero', 20);
                $table->string('tipo', 20)->default('normal'); // normal|postres
                $table->unsignedBigInteger('area_comanda_gastronomia_id')->nullable();
                $table->unsignedBigInteger('salida_id')->nullable();
                $table->unsignedBigInteger('mesa_gastronomia_id')->nullable();
                $table->unsignedBigInteger('mozo_gastronomia_id')->nullable();
                $table->string('estado', 20)->default('fired'); // fired|preparing|ready|delivered|void (KDS)
                $table->string('canal_impresion', 20)->default('termica'); // termica|pdf|ambos
                $table->string('ruta_pdf', 255)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->text('observacion')->nullable();
                $table->timestamp('fired_at')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_gc_comanda_empresa')->references('id')->on('empresa')->restrictOnDelete();
                $table->foreign('gastro_crown_cuenta_id', 'fk_gc_comanda_cuenta')
                    ->references('id')->on('gastro_crown_cuenta')->cascadeOnDelete();
                $table->foreign('area_comanda_gastronomia_id', 'fk_gc_comanda_area')
                    ->references('id')->on('area_comanda_gastronomia')->nullOnDelete();
                $table->foreign('salida_id', 'fk_gc_comanda_salida')->references('id')->on('salida')->nullOnDelete();
                $table->unique(['empresa_id', 'numero'], 'uk_gc_comanda_empresa_numero');
                $table->index(['estado', 'fired_at'], 'idx_gc_comanda_estado_fired');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('gastro_crown_comanda_linea')) {
            Schema::create('gastro_crown_comanda_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gastro_crown_comanda_id');
                $table->unsignedBigInteger('gastro_crown_cuenta_linea_id')->nullable();
                $table->unsignedInteger('orden')->default(1);
                $table->string('sku', 30)->nullable();
                $table->string('descripcion', 120);
                $table->decimal('cantidad', 12, 3)->default(1);
                $table->string('observacion', 255)->nullable();
                $table->timestamps();

                $table->foreign('gastro_crown_comanda_id', 'fk_gc_comline_comanda')
                    ->references('id')->on('gastro_crown_comanda')->cascadeOnDelete();
                $table->foreign('gastro_crown_cuenta_linea_id', 'fk_gc_comline_cuenta_linea')
                    ->references('id')->on('gastro_crown_cuenta_linea')->nullOnDelete();
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('gastro_crown_comanda_evento')) {
            Schema::create('gastro_crown_comanda_evento', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gastro_crown_comanda_id');
                $table->string('evento', 30); // fired|ack|preparing|ready|bump|void|refire|print_ok|print_fail
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('detalle', 255)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('gastro_crown_comanda_id', 'fk_gc_comevt_comanda')
                    ->references('id')->on('gastro_crown_comanda')->cascadeOnDelete();
                $table->index(['gastro_crown_comanda_id', 'evento'], 'idx_gc_comevt_comanda_evento');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('gastro_crown_cuenta_evento')) {
            Schema::create('gastro_crown_cuenta_evento', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('gastro_crown_cuenta_id');
                $table->string('evento', 30); // open|add_item|void_item|fire|pay|hotel|gst|close|anular
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->string('detalle', 255)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('gastro_crown_cuenta_id', 'fk_gc_ctevt_cuenta')
                    ->references('id')->on('gastro_crown_cuenta')->cascadeOnDelete();
                $table->index(['gastro_crown_cuenta_id', 'evento'], 'idx_gc_ctevt_cuenta_evento');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        // FK diferida linea → comanda (tabla comanda creada después)
        if (Schema::hasTable('gastro_crown_cuenta_linea')
            && Schema::hasColumn('gastro_crown_cuenta_linea', 'gastro_crown_comanda_id')
            && Schema::hasTable('gastro_crown_comanda')) {
            Schema::table('gastro_crown_cuenta_linea', function (Blueprint $table) {
                try {
                    $table->foreign('gastro_crown_comanda_id', 'fk_gc_linea_comanda')
                        ->references('id')->on('gastro_crown_comanda')->nullOnDelete();
                } catch (\Throwable) {
                    // ya existe
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gastro_crown_cuenta_evento');
        Schema::dropIfExists('gastro_crown_comanda_evento');
        Schema::dropIfExists('gastro_crown_comanda_linea');
        if (Schema::hasTable('gastro_crown_cuenta_linea')) {
            Schema::table('gastro_crown_cuenta_linea', function (Blueprint $table) {
                try {
                    $table->dropForeign('fk_gc_linea_comanda');
                } catch (\Throwable) {
                }
            });
        }
        Schema::dropIfExists('gastro_crown_comanda');
        Schema::dropIfExists('gastro_crown_cuenta_linea');
        Schema::dropIfExists('gastro_crown_cuenta');
    }
};
