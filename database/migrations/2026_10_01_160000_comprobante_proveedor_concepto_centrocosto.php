<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_proveedor_concepto', 'centrocosto_id')) {
                $table->unsignedBigInteger('centrocosto_id')->nullable()->after('cuentacontabledebe_id');
                $table->foreign('centrocosto_id', 'fk_cp_concepto_centrocosto')
                    ->references('id')->on('centrocosto')->onDelete('restrict')->onUpdate('cascade');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_proveedor_concepto', 'centrocosto_id')) {
                $table->dropForeign('fk_cp_concepto_centrocosto');
                $table->dropColumn('centrocosto_id');
            }
        });
    }
};
