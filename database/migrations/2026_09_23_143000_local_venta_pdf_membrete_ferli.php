<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membrete PDF propio de cada local (Facturación Local Ferli).
 * No pisa factura_pdf_parametro de fábrica; se usa solo en ventas del POS local.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta')) {
            return;
        }

        $cols = [
            'pdf_web' => fn (Blueprint $t) => $t->string('pdf_web', 500)->nullable()->after('observacion'),
            'pdf_imp_internos' => fn (Blueprint $t) => $t->string('pdf_imp_internos', 200)->nullable()->after('pdf_web'),
            'pdf_seguridad_higiene' => fn (Blueprint $t) => $t->string('pdf_seguridad_higiene', 200)->nullable()->after('pdf_imp_internos'),
            'pdf_habilitacion' => fn (Blueprint $t) => $t->string('pdf_habilitacion', 200)->nullable()->after('pdf_seguridad_higiene'),
            'pdf_lugar' => fn (Blueprint $t) => $t->string('pdf_lugar', 80)->nullable()->after('pdf_habilitacion'),
            'pdf_inicio_actividad' => fn (Blueprint $t) => $t->string('pdf_inicio_actividad', 40)->nullable()->after('pdf_lugar'),
        ];

        foreach ($cols as $nombre => $def) {
            if (Schema::hasColumn('local_venta', $nombre)) {
                continue;
            }
            Schema::table('local_venta', function (Blueprint $table) use ($def) {
                $def($table);
            });
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esFerli()) {
            return;
        }
        if (! Schema::hasTable('local_venta')) {
            return;
        }
        $drop = [];
        foreach ([
            'pdf_web',
            'pdf_imp_internos',
            'pdf_seguridad_higiene',
            'pdf_habilitacion',
            'pdf_lugar',
            'pdf_inicio_actividad',
        ] as $col) {
            if (Schema::hasColumn('local_venta', $col)) {
                $drop[] = $col;
            }
        }
        if ($drop === []) {
            return;
        }
        Schema::table('local_venta', function (Blueprint $table) use ($drop) {
            $table->dropColumn($drop);
        });
    }
};
