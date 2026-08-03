<?php

use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Reasigna usos Remesas destino / Remesas origen (efectivo) con códigos ERP
 * (Anita 00000120 → 120, 00000TES → TES). La migración inicial buscaba códigos
 * Anita literales y no encontró filas en cuentacaja.
 */
return new class extends Migration
{
    public function up(): void
    {
        $usoDestinoId = (int) (DB::table('usocuentacaja')
            ->where('nombre', RemesaSupport::USO_DESTINO)
            ->value('id') ?? 0);
        $usoOrigenId = (int) (DB::table('usocuentacaja')
            ->where('nombre', RemesaSupport::USO_ORIGEN)
            ->value('id') ?? 0);

        $this->asignarUsoACodigos($usoDestinoId, RemesaSupport::CODIGOS_DESTINO);
        $this->asignarUsoACodigos($usoOrigenId, RemesaSupport::CODIGOS_ORIGEN);
    }

    public function down(): void
    {
        // No revierte asignaciones de uso: pueden haberse editado a mano.
    }

    /**
     * @param  list<string>  $codigosAnita
     */
    private function asignarUsoACodigos(int $usoId, array $codigosAnita): void
    {
        if ($usoId <= 0) {
            return;
        }

        $codigosErp = RemesaSupport::codigosErpDesdeAnita($codigosAnita);
        if ($codigosErp === []) {
            return;
        }

        $ids = DB::table('cuentacaja')
            ->whereIn('codigo', $codigosErp)
            ->pluck('id')
            ->all();

        foreach ($ids as $cuentacajaId) {
            $exists = DB::table('cuentacaja_usocuentacaja')
                ->where('cuentacaja_id', $cuentacajaId)
                ->where('usocuentacaja_id', $usoId)
                ->exists();
            if (! $exists) {
                DB::table('cuentacaja_usocuentacaja')->insert([
                    'cuentacaja_id' => $cuentacajaId,
                    'usocuentacaja_id' => $usoId,
                ]);
            }
        }
    }
};
