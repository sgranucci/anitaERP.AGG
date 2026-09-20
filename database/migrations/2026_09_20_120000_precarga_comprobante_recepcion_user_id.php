<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quién asignó la COM a la factura del legajo.
 *
 * La tabla pivote se reescribe completa en cada guardado (delete + create), así que sin
 * esta columna no quedaba rastro del responsable de una asignación equivocada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return;
        }

        Schema::table('precarga_comprobante_proveedor_recepcion', function (Blueprint $table) {
            if (! Schema::hasColumn('precarga_comprobante_proveedor_recepcion', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('orden')
                    ->comment('Usuario que asignó la COM a la factura');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return;
        }

        Schema::table('precarga_comprobante_proveedor_recepcion', function (Blueprint $table) {
            if (Schema::hasColumn('precarga_comprobante_proveedor_recepcion', 'user_id')) {
                $table->dropColumn('user_id');
            }
        });
    }
};
