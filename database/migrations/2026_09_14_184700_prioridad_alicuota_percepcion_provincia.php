<?php

use App\Support\Configuracion\PercepcionIibbPrioridadAlicuotaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provincia')) {
            return;
        }

        if (! Schema::hasColumn('provincia', 'prioridad_alicuota_percepcion')) {
            Schema::table('provincia', function (Blueprint $table) {
                $table->string('prioridad_alicuota_percepcion', 32)
                    ->default(PercepcionIibbPrioridadAlicuotaSupport::PADRON_DESCARTE)
                    ->after('tope_alicuota_percepcion');
            });
        }

        // Defaults por jurisdicción (Anita): con padrón → padron/descarte;
        // Misiones y sin padrón → descarte/padrón.
        $filas = DB::table('provincia')
            ->select(['id', 'jurisdiccion'])
            ->get();

        foreach ($filas as $fila) {
            $prioridad = PercepcionIibbPrioridadAlicuotaSupport::defaultParaJurisdiccion(
                $fila->jurisdiccion !== null && $fila->jurisdiccion !== ''
                    ? (int) $fila->jurisdiccion
                    : null
            );
            DB::table('provincia')
                ->where('id', $fila->id)
                ->update(['prioridad_alicuota_percepcion' => $prioridad]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('provincia')) {
            return;
        }
        if (! Schema::hasColumn('provincia', 'prioridad_alicuota_percepcion')) {
            return;
        }

        Schema::table('provincia', function (Blueprint $table) {
            $table->dropColumn('prioridad_alicuota_percepcion');
        });
    }
};
