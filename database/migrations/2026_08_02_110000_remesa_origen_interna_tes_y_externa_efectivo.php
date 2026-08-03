<?php

use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Separa origen de remesa:
 * - Interna → uso TES (caja principal tesorería).
 * - Externa → cajas efectivo por moneda/empresa (pesos/dólar/euro) + cripto compartida.
 */
return new class extends Migration
{
    public function up(): void
    {
        $usoDestinoId = $this->asegurarUso(RemesaSupport::USO_DESTINO);
        $usoOrigenExternaId = $this->asegurarUso(RemesaSupport::USO_ORIGEN_EXTERNA);
        $usoOrigenInternaId = $this->asegurarUso(RemesaSupport::USO_ORIGEN_INTERNA);

        // TES pasa al uso interna (y deja de ser origen de externa).
        $this->asignarUsoACodigos($usoOrigenInternaId, RemesaSupport::CODIGOS_ORIGEN_INTERNA);
        $this->quitarUsoDeCodigos($usoOrigenExternaId, RemesaSupport::CODIGOS_ORIGEN_INTERNA);

        // Cajas efectivo por empresa/moneda + cripto compartida → origen externa.
        $this->asignarUsoACodigos($usoOrigenExternaId, RemesaSupport::CODIGOS_ORIGEN_EXTERNA);

        // TES compartida entre empresas (tesorería).
        DB::table('cuentacaja')
            ->where('codigo', 'TES')
            ->update([
                'empresa_id' => null,
                'updated_at' => now(),
            ]);

        // Mantener destino cableado (idempotente).
        $this->asignarUsoACodigos($usoDestinoId, RemesaSupport::CODIGOS_DESTINO);
    }

    public function down(): void
    {
        // No revierte asignaciones de uso ni empresa_id de TES.
    }

    private function asegurarUso(string $nombre): int
    {
        $id = (int) (DB::table('usocuentacaja')->where('nombre', $nombre)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        return (int) DB::table('usocuentacaja')->insertGetId([
            'nombre' => $nombre,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    /**
     * @param  list<string>  $codigosAnita
     */
    private function quitarUsoDeCodigos(int $usoId, array $codigosAnita): void
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

        if ($ids === []) {
            return;
        }

        DB::table('cuentacaja_usocuentacaja')
            ->where('usocuentacaja_id', $usoId)
            ->whereIn('cuentacaja_id', $ids)
            ->delete();
    }
};
