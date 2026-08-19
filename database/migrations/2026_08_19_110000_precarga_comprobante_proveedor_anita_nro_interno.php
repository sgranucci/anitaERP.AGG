<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PrecargaComprobanteProveedorAnitaNroInterno extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('precarga_comprobante_proveedor', 'anita_nro_interno')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->unsignedInteger('anita_nro_interno')->nullable()->after('estado');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('precarga_comprobante_proveedor', 'anita_nro_interno')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('anita_nro_interno');
            });
        }
    }
}
