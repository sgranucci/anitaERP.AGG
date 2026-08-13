<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PrecargaComprobanteProveedorFechavencimiento extends Migration
{
    public function up()
    {
        if (! Schema::hasColumn('precarga_comprobante_proveedor', 'fechavencimiento')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->date('fechavencimiento')->nullable()->after('fechavencimientocaicae');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('precarga_comprobante_proveedor', 'fechavencimiento')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('fechavencimiento');
            });
        }
    }
}
