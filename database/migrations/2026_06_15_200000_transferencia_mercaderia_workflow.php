<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tipotransaccion_stock', 'requiere_aprobacion')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->boolean('requiere_aprobacion')->default(false)->after('estado');
                $table->boolean('maneja_contabilidad')->default(false)->after('requiere_aprobacion');
            });
        }

        if (! Schema::hasColumn('deposito_administrador', 'aprueba_transferencia')) {
            Schema::table('deposito_administrador', function (Blueprint $table) {
                $table->boolean('aprueba_transferencia')->default(true)->after('aprueba_recepcion');
            });
        }

        if (! Schema::hasTable('transferencia_mercaderia')) {
            Schema::create('transferencia_mercaderia', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('codigo', 40);
                $table->unsignedBigInteger('lote');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('deposito_origen_id');
                $table->unsignedBigInteger('deposito_destino_id');
                $table->unsignedBigInteger('tipotransaccion_stock_id');
                $table->string('estado', 32);
                $table->boolean('requiere_aprobacion')->default(false);
                $table->unsignedBigInteger('usuario_origen_id')->nullable();
                $table->unsignedBigInteger('usuario_destino_id')->nullable();
                $table->unsignedBigInteger('usuario_aprobador_id')->nullable();
                $table->unsignedBigInteger('movimientostock_salida_id')->nullable();
                $table->unsignedBigInteger('movimientostock_entrada_id')->nullable();
                $table->unsignedBigInteger('asiento_id')->nullable();
                $table->date('fecha');
                $table->date('fecha_aprobacion')->nullable();
                $table->text('observacion')->nullable();
                $table->text('motivo_rechazo')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('empresa_id', 'fk_tm_empresa')->references('id')->on('empresa')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('deposito_origen_id', 'fk_tm_dep_origen')->references('id')->on('depmae')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('deposito_destino_id', 'fk_tm_dep_destino')->references('id')->on('depmae')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('tipotransaccion_stock_id', 'fk_tm_tipo_stock')->references('id')->on('tipotransaccion_stock')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('usuario_origen_id', 'fk_tm_usuario_origen')->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('usuario_destino_id', 'fk_tm_usuario_destino')->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('usuario_aprobador_id', 'fk_tm_usuario_aprobador')->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('movimientostock_salida_id', 'fk_tm_mov_salida')->references('id')->on('movimientostock')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('movimientostock_entrada_id', 'fk_tm_mov_entrada')->references('id')->on('movimientostock')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('asiento_id', 'fk_tm_asiento')->references('id')->on('asiento')->nullOnDelete()->cascadeOnUpdate();

                $table->unique('codigo', 'uk_transferencia_mercaderia_codigo');
                $table->index('estado', 'ix_tm_estado');
                $table->index(['deposito_destino_id', 'estado'], 'ix_tm_destino_estado');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('transferencia_mercaderia_articulo')) {
            Schema::create('transferencia_mercaderia_articulo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('transferencia_mercaderia_id');
                $table->unsignedSmallInteger('item');
                $table->unsignedBigInteger('articulo_origen_id');
                $table->unsignedBigInteger('articulo_destino_id');
                $table->decimal('cantidad_origen', 16, 6);
                $table->decimal('cantidad_destino', 16, 6);
                $table->decimal('precio_costo_origen', 16, 6)->default(0);
                $table->decimal('precio_costo_destino', 16, 6)->default(0);
                $table->decimal('coeficienteconversion', 16, 6)->default(1);
                $table->boolean('fl_conversion_formula')->default(false);
                $table->timestamps();

                $table->foreign('transferencia_mercaderia_id', 'fk_tma_transferencia')
                    ->references('id')->on('transferencia_mercaderia')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('articulo_origen_id', 'fk_tma_articulo_origen')
                    ->references('id')->on('articulo')->restrictOnDelete()->cascadeOnUpdate();
                $table->foreign('articulo_destino_id', 'fk_tma_articulo_destino')
                    ->references('id')->on('articulo')->restrictOnDelete()->cascadeOnUpdate();

                $table->unique(['transferencia_mercaderia_id', 'item'], 'uk_tma_item');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('transferencia_mercaderia_token')) {
            Schema::create('transferencia_mercaderia_token', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('transferencia_mercaderia_id');
                $table->string('token', 64);
                $table->string('accion', 16);
                $table->unsignedBigInteger('usuario_destino_id')->nullable();
                $table->timestamp('usado_el')->nullable();
                $table->timestamp('expira_el')->nullable();
                $table->timestamps();

                $table->foreign('transferencia_mercaderia_id', 'fk_tmt_transferencia')
                    ->references('id')->on('transferencia_mercaderia')->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('usuario_destino_id', 'fk_tmt_usuario')
                    ->references('id')->on('usuario')->nullOnDelete()->cascadeOnUpdate();

                $table->unique('token', 'uk_tmt_token');
                $table->index(['transferencia_mercaderia_id', 'accion'], 'ix_tmt_transferencia_accion');

                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('transferencia_mercaderia_token');
        Schema::dropIfExists('transferencia_mercaderia_articulo');
        Schema::dropIfExists('transferencia_mercaderia');

        if (Schema::hasColumn('deposito_administrador', 'aprueba_transferencia')) {
            Schema::table('deposito_administrador', function (Blueprint $table) {
                $table->dropColumn('aprueba_transferencia');
            });
        }

        if (Schema::hasColumn('tipotransaccion_stock', 'maneja_contabilidad')) {
            Schema::table('tipotransaccion_stock', function (Blueprint $table) {
                $table->dropColumn(['requiere_aprobacion', 'maneja_contabilidad']);
            });
        }
    }
};
