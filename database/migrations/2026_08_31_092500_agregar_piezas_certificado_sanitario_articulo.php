<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SENASA <se:cantidad> son unidades (piezas), no cajas.
 * Se persisten para regenerar el XML sin recalcular por unidadesxenvase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('certificado_sanitario_articulo', 'piezas')) {
            Schema::table('certificado_sanitario_articulo', function (Blueprint $table) {
                $table->decimal('piezas', 14, 3)->default(0)->after('cajas');
            });
        }

        $unidades = DB::table('articulo')->pluck('unidadesxenvase', 'id');
        DB::table('certificado_sanitario_articulo')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($unidades): void {
                foreach ($rows as $row) {
                    $ux = (float) ($unidades[$row->articulo_id] ?? 0);
                    $piezas = $ux > 0 ? (float) $row->cajas * $ux : 0.0;
                    DB::table('certificado_sanitario_articulo')
                        ->where('id', $row->id)
                        ->update(['piezas' => $piezas]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('certificado_sanitario_articulo', 'piezas')) {
            Schema::table('certificado_sanitario_articulo', function (Blueprint $table) {
                $table->dropColumn('piezas');
            });
        }
    }
};
