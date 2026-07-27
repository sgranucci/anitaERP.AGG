<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flag para habilitar la herramienta de control contable de cigarrillos
 * (planilla Contaduría + conciliación vs mayor Anita 414020001) en el reporte
 * de insumos gastronomía por día, sin hardcodear por nombre.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipoarticulo')) {
            return;
        }

        Schema::table('tipoarticulo', function (Blueprint $table) {
            if (! Schema::hasColumn('tipoarticulo', 'usa_control_contable_cigarrillos')) {
                $table->boolean('usa_control_contable_cigarrillos')
                    ->default(false)
                    ->after('abreviatura');
            }
        });

        $nombre = strtoupper((string) config('facturacion.IMPUESTO_INTERNO_TIPOARTICULO_NOMBRE', 'CIGARRILLO'));
        if ($nombre !== '') {
            DB::table('tipoarticulo')
                ->whereRaw('UPPER(TRIM(nombre)) = ?', [$nombre])
                ->update(['usa_control_contable_cigarrillos' => true]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipoarticulo')) {
            return;
        }

        Schema::table('tipoarticulo', function (Blueprint $table) {
            if (Schema::hasColumn('tipoarticulo', 'usa_control_contable_cigarrillos')) {
                $table->dropColumn('usa_control_contable_cigarrillos');
            }
        });
    }
};
