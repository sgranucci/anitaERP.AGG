<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tipo de autorización AFIP (CAE / CAEA / CAI) para unicidad de factura proveedor.
 * CAEA puede repetirse; CAE/CAI se controlan junto con el número.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('comprobante_proveedor')
            && ! Schema::hasColumn('comprobante_proveedor', 'tipo_autorizacion')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->string('tipo_autorizacion', 10)->nullable()->after('numerocae');
            });
        }

        if (Schema::hasTable('precarga_comprobante_proveedor')
            && ! Schema::hasColumn('precarga_comprobante_proveedor', 'tipo_autorizacion')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->string('tipo_autorizacion', 10)->nullable()->after('numerocae');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('comprobante_proveedor')
            && Schema::hasColumn('comprobante_proveedor', 'tipo_autorizacion')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('tipo_autorizacion');
            });
        }

        if (Schema::hasTable('precarga_comprobante_proveedor')
            && Schema::hasColumn('precarga_comprobante_proveedor', 'tipo_autorizacion')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('tipo_autorizacion');
            });
        }
    }
};
