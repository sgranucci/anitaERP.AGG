<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lote bancario (archivo de pagos) + override cuenta por línea de propuesta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('propuesta_pago_linea') && ! Schema::hasColumn('propuesta_pago_linea', 'cuentacaja_id')) {
            Schema::table('propuesta_pago_linea', function (Blueprint $table) {
                $table->unsignedBigInteger('cuentacaja_id')->nullable()->after('formapago_id');
                $table->index('cuentacaja_id');
            });
        }

        if (! Schema::hasTable('lote_bancario')) {
            Schema::create('lote_bancario', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('propuesta_pago_id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('cuentacaja_id')->nullable();
                $table->string('estado', 30)->default('BORRADOR');
                $table->unsignedInteger('cantidad_lineas')->default(0);
                $table->decimal('monto_total', 18, 4)->default(0);
                $table->string('archivo_nombre', 255)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('exportado_at')->nullable();
                $table->timestamps();

                $table->index('propuesta_pago_id');
                $table->index('empresa_id');
                $table->index('estado');
            });
        }

        if (! Schema::hasTable('lote_bancario_linea')) {
            Schema::create('lote_bancario_linea', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lote_bancario_id');
                $table->unsignedBigInteger('pagoproveedor_id')->nullable();
                $table->unsignedBigInteger('proveedor_id')->nullable();
                $table->string('proveedor_nombre', 200)->nullable();
                $table->string('cuit', 20)->nullable();
                $table->string('cbu', 30)->nullable();
                $table->decimal('monto_bruto', 18, 4)->default(0);
                $table->decimal('monto_retenciones', 18, 4)->default(0);
                $table->decimal('monto_neto', 18, 4)->default(0);
                $table->string('referencia_op', 80)->nullable();
                $table->string('medio', 30)->nullable();
                $table->string('observacion', 255)->nullable();
                $table->timestamps();

                $table->index('lote_bancario_id');
                $table->index('pagoproveedor_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_bancario_linea');
        Schema::dropIfExists('lote_bancario');
        if (Schema::hasTable('propuesta_pago_linea') && Schema::hasColumn('propuesta_pago_linea', 'cuentacaja_id')) {
            Schema::table('propuesta_pago_linea', function (Blueprint $table) {
                $table->dropColumn('cuentacaja_id');
            });
        }
    }
};
