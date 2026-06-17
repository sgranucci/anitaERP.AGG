<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'penvp_nro_interno')) {
                $table->unsignedBigInteger('penvp_nro_interno')->nullable()->after('penvp_orden');
                $table->index('penvp_nro_interno', 'idx_ordencompra_articulo_penvp_nro_interno');
            }
        });

        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'penvp_nro_interno')) {
                $table->unsignedBigInteger('penvp_nro_interno')->nullable()->after('penvp_orden');
                $table->index('penvp_nro_interno', 'idx_recep_prov_art_penvp_nro_interno');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'penvp_nro_interno')) {
                $table->dropIndex('idx_recep_prov_art_penvp_nro_interno');
                $table->dropColumn('penvp_nro_interno');
            }
        });

        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'penvp_nro_interno')) {
                $table->dropIndex('idx_ordencompra_articulo_penvp_nro_interno');
                $table->dropColumn('penvp_nro_interno');
            }
        });
    }
};
