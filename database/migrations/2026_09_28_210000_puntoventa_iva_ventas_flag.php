<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Marca por punto de venta: si sus comprobantes entran al listado IVA ventas.
 * Alta inicial: tildado cuando el punto de venta tiene web service; destildado el resto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('puntoventa')
            && ! Schema::hasColumn('puntoventa', 'iva_ventas')) {
            Schema::table('puntoventa', function (Blueprint $table) {
                $table->boolean('iva_ventas')->default(false)->after('webservice');
            });
        }

        if (! Schema::hasColumn('puntoventa', 'iva_ventas')) {
            return;
        }

        DB::table('puntoventa')
            ->whereNotNull('webservice')
            ->where('webservice', '!=', '')
            ->whereRaw('UPPER(webservice) <> ?', ['NULL'])
            ->update([
                'iva_ventas' => true,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('puntoventa')
            && Schema::hasColumn('puntoventa', 'iva_ventas')) {
            Schema::table('puntoventa', function (Blueprint $table) {
                $table->dropColumn('iva_ventas');
            });
        }
    }
};
