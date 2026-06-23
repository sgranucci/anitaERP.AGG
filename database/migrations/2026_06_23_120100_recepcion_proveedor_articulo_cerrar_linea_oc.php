<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RecepcionProveedorArticuloCerrarLineaOc extends Migration
{
    public function up(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('recepcion_proveedor_articulo', 'fl_cerrar_linea_oc')) {
                $table->boolean('fl_cerrar_linea_oc')->default(false)->after('fl_articulo_distinto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('recepcion_proveedor_articulo', function (Blueprint $table) {
            if (Schema::hasColumn('recepcion_proveedor_articulo', 'fl_cerrar_linea_oc')) {
                $table->dropColumn('fl_cerrar_linea_oc');
            }
        });
    }
}
