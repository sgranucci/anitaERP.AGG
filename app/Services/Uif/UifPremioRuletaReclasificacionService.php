<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Premio_Uif;
use App\Models\Uif\Juego_Uif;
use App\Support\Uif\UifMaquinaRuletaBienUsoSupport;

/**
 * Backfill opcional: reclasifica premios UIF a RULETA según padrón bien_uso.
 * No se ejecuta solo; usar comando artisan con --apply.
 */
final class UifPremioRuletaReclasificacionService
{
    /**
     * @return array{
     *   dry_run: bool,
     *   padron: list<array{empresa_id:int,cantidad:int}>,
     *   candidatos: int,
     *   actualizados: int,
     *   por_juego_origen: array<string,int>,
     *   por_sala: array<int,int>,
     *   ejemplos: list<array<string,mixed>>
     * }
     */
    public function ejecutar(bool $aplicar = false, int $limiteEjemplos = 20): array
    {
        UifMaquinaRuletaBienUsoSupport::resetCacheForTesting();
        JuegoUifDesdeAnitaResolver::resetCacheForTesting();

        $ruletaId = (int) Juego_Uif::query()->where('nombre', 'RULETA')->value('id');
        if ($ruletaId <= 0) {
            throw new \RuntimeException('No existe juego_uif RULETA.');
        }

        $nombres = Juego_Uif::query()->pluck('nombre', 'id')->all();
        $preservarIds = Juego_Uif::query()
            ->whereIn('nombre', ['BINGO', 'COMPRA TARJETA', 'RULETA'])
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $candidatos = 0;
        $actualizados = 0;
        $porJuego = [];
        $porSala = [];
        $ejemplos = [];
        $idsParaUpdate = [];

        Cliente_Premio_Uif::query()
            ->select(['id', 'sala_id', 'juego_uif_id', 'posicion', 'monto', 'fechaentrega'])
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (
                $ruletaId,
                $preservarIds,
                $nombres,
                &$candidatos,
                &$porJuego,
                &$porSala,
                &$ejemplos,
                &$idsParaUpdate,
                $limiteEjemplos,
            ): void {
                foreach ($filas as $premio) {
                    $juegoId = (int) $premio->juego_uif_id;
                    if (in_array($juegoId, $preservarIds, true)) {
                        continue;
                    }

                    $empresaId = UifMaquinaRuletaBienUsoSupport::empresaIdDesdeSalaUif((int) $premio->sala_id);
                    if (! UifMaquinaRuletaBienUsoSupport::esRuletaElectronica(
                        $premio->posicion !== null ? (string) $premio->posicion : null,
                        $empresaId,
                    )) {
                        continue;
                    }

                    $candidatos++;
                    $nombreOrigen = (string) ($nombres[$juegoId] ?? '#'.$juegoId);
                    $porJuego[$nombreOrigen] = ($porJuego[$nombreOrigen] ?? 0) + 1;
                    $sala = (int) $premio->sala_id;
                    $porSala[$sala] = ($porSala[$sala] ?? 0) + 1;
                    $idsParaUpdate[] = (int) $premio->id;

                    if (count($ejemplos) < $limiteEjemplos) {
                        $ejemplos[] = [
                            'id' => (int) $premio->id,
                            'sala_id' => $sala,
                            'juego_actual' => $nombreOrigen,
                            'posicion' => $premio->posicion,
                            'monto' => (float) $premio->monto,
                            'fechaentrega' => (string) $premio->fechaentrega,
                        ];
                    }
                }
            });

        if ($aplicar && $idsParaUpdate !== []) {
            foreach (array_chunk($idsParaUpdate, 500) as $chunk) {
                $n = Cliente_Premio_Uif::query()
                    ->whereIn('id', $chunk)
                    ->update(['juego_uif_id' => $ruletaId, 'updated_at' => now()]);
                $actualizados += $n;
            }
        }

        return [
            'dry_run' => ! $aplicar,
            'padron' => UifMaquinaRuletaBienUsoSupport::resumenPadron(),
            'candidatos' => $candidatos,
            'actualizados' => $actualizados,
            'por_juego_origen' => $porJuego,
            'por_sala' => $porSala,
            'ejemplos' => $ejemplos,
        ];
    }
}
