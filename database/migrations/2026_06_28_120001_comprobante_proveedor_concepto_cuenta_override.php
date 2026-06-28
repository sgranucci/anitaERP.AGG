<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_proveedor_concepto', 'cuentacontabledebe_id')) {
                $table->unsignedBigInteger('cuentacontabledebe_id')->nullable()->after('monto');
                $table->foreign('cuentacontabledebe_id', 'fk_cp_concepto_cuentadebe')
                    ->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    public function down(): void
    {
        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_proveedor_concepto', 'cuentacontabledebe_id')) {
                $table->dropForeign('fk_cp_concepto_cuentadebe');
                $table->dropColumn('cuentacontabledebe_id');
            }
        });
    }
};
