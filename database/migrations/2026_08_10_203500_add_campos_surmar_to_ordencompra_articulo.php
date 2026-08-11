<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campos exclusivos del esquema Surmar (pendmovp): penvp_lote_transf / penvp_peso_unit.
 * AGG no los usa; quedan nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'lote_transferencia')) {
                $table->unsignedInteger('lote_transferencia')->nullable()->after('penvp_nro_interno')
                    ->comment('Surmar penvp_lote_transf');
            }
            if (! Schema::hasColumn('ordencompra_articulo', 'peso_unitario')) {
                $table->decimal('peso_unitario', 18, 6)->nullable()->after('lote_transferencia')
                    ->comment('Surmar penvp_peso_unit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'peso_unitario')) {
                $table->dropColumn('peso_unitario');
            }
            if (Schema::hasColumn('ordencompra_articulo', 'lote_transferencia')) {
                $table->dropColumn('lote_transferencia');
            }
        });
    }
};
