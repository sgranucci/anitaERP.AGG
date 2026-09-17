<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Programa de pagos (cashflow Ferli): plan mensual editable por proveedor.
 * Tablas genéricas; menú/permisos solo en migración Ferli hermana.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('programa_pago')) {
            Schema::create('programa_pago', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('empresa_id');
                $table->string('titulo', 120)->nullable();
                $table->date('fecha_base');
                $table->string('anio_mes_inicio', 7);
                $table->unsignedTinyInteger('cantidad_meses')->default(4);
                $table->boolean('incluye_transf')->default(true);
                $table->string('estado', 20)->default('BORRADOR');
                $table->text('detalle')->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamps();

                $table->foreign('empresa_id', 'fk_programa_pago_empresa')
                    ->references('id')->on('empresa')->onDelete('restrict');
                $table->foreign('usuario_id', 'fk_programa_pago_usuario')
                    ->references('id')->on('usuario')->onDelete('set null');
                $table->index(['empresa_id', 'estado'], 'ix_programa_pago_empresa_estado');
            });
        }

        if (! Schema::hasTable('programa_pago_linea')) {
            Schema::create('programa_pago_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('programa_pago_id');
                $table->unsignedBigInteger('proveedor_id');
                $table->decimal('saldo_adeudado', 18, 2)->default(0);
                $table->text('observacion')->nullable();
                $table->unsignedInteger('orden')->default(0);
                $table->timestamps();

                $table->foreign('programa_pago_id', 'fk_programa_pago_linea_cab')
                    ->references('id')->on('programa_pago')->onDelete('cascade');
                $table->foreign('proveedor_id', 'fk_programa_pago_linea_prov')
                    ->references('id')->on('proveedor')->onDelete('restrict');
                $table->unique(['programa_pago_id', 'proveedor_id'], 'uq_programa_pago_linea_prov');
                $table->index(['programa_pago_id', 'orden'], 'ix_programa_pago_linea_orden');
            });
        }

        if (! Schema::hasTable('programa_pago_asignacion')) {
            Schema::create('programa_pago_asignacion', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('programa_pago_linea_id');
                $table->string('clave', 16);
                $table->decimal('monto', 18, 2)->default(0);
                $table->timestamps();

                $table->foreign('programa_pago_linea_id', 'fk_programa_pago_asig_linea')
                    ->references('id')->on('programa_pago_linea')->onDelete('cascade');
                $table->unique(['programa_pago_linea_id', 'clave'], 'uq_programa_pago_asig_clave');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('programa_pago_asignacion');
        Schema::dropIfExists('programa_pago_linea');
        Schema::dropIfExists('programa_pago');
    }
};
