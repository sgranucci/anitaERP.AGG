<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remitos internos para locales (Facturación Local) — solo Calzados Ferli.
 * Generan un único movimiento de stock (salida del depósito del local).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        if (! Schema::hasTable('remito_interno')) {
            Schema::create('remito_interno', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('numero')->unique();
                $table->date('fecha');
                $table->unsignedBigInteger('local_venta_id');
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->unsignedBigInteger('deposito_id');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedBigInteger('movimientostock_id')->nullable();
                $table->string('estado', 20)->default('borrador');
                $table->string('destinatario', 160)->nullable();
                $table->string('leyenda', 255)->nullable();
                $table->text('observacion')->nullable();
                $table->timestamps();

                $table->foreign('local_venta_id', 'fk_ri_local')
                    ->references('id')->on('local_venta')->onDelete('restrict');
                $table->foreign('empresa_id', 'fk_ri_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('deposito_id', 'fk_ri_deposito')
                    ->references('id')->on('depmae')->onDelete('restrict');
                $table->foreign('usuario_id', 'fk_ri_usuario')
                    ->references('id')->on('usuario')->onDelete('restrict');
                $table->foreign('movimientostock_id', 'fk_ri_movstock')
                    ->references('id')->on('movimientostock')->onDelete('restrict');

                $table->index(['estado', 'local_venta_id'], 'idx_ri_estado_local');
                $table->index(['fecha'], 'idx_ri_fecha');
            });
        }

        if (! Schema::hasTable('remito_interno_linea')) {
            Schema::create('remito_interno_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('remito_interno_id');
                $table->unsignedSmallInteger('orden')->default(1);
                $table->unsignedBigInteger('articulo_id');
                $table->unsignedBigInteger('combinacion_id')->nullable();
                $table->unsignedBigInteger('talle_id')->nullable();
                $table->unsignedBigInteger('color_id')->nullable();
                $table->unsignedBigInteger('modulo_id')->nullable();
                $table->decimal('cantidad', 14, 4)->default(1);
                $table->string('descripcion', 255)->nullable();
                $table->timestamps();

                $table->foreign('remito_interno_id', 'fk_ril_remito')
                    ->references('id')->on('remito_interno')->onDelete('cascade');
                $table->foreign('articulo_id', 'fk_ril_articulo')
                    ->references('id')->on('articulo')->onDelete('restrict');
                $table->index(['remito_interno_id', 'orden'], 'idx_ril_remito_orden');
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }

        Schema::dropIfExists('remito_interno_linea');
        Schema::dropIfExists('remito_interno');
    }
};
