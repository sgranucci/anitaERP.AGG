<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OrdencompraArticuloEstadoLineaOc extends Migration
{
    public function up(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('ordencompra_articulo', 'estado_linea_oc')) {
                $table->string('estado_linea_oc', 20)->default('ACTIVA')->after('penvp_nro_interno');
                $table->index('estado_linea_oc', 'idx_ordencompra_articulo_estado_linea_oc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ordencompra_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('ordencompra_articulo', 'estado_linea_oc')) {
                $table->dropIndex('idx_ordencompra_articulo_estado_linea_oc');
                $table->dropColumn('estado_linea_oc');
            }
        });
    }
}
