<?php

namespace App\Services\Uif;

use App\Models\Uif\Cliente_Premio_Uif;
use App\Models\Uif\Juego_Uif;
use App\Support\Uif\UifMaquinaRuletaBienUsoSupport;

/**
 * Backfill: POSICION ELECTRONICA → SLOTS (o RULETA si la posición está en bien_uso).
 */
final class UifPremioPosicionElectronicaASlotsService
{
    /**
     * @return array{
     *   dry_run: bool,
     *   candidatos_slots: int,
     *   candidatos_ruleta: int,
     *   actualizados_slots: int,
     *   actualizados_ruleta: int,
     *   por_sala: array<int,int>,
     *   ejemplos: list<array<string,mixed>>
     * }
     */
    public function ejecutar(bool $aplicar = false, int $limiteEjemplos = 20): array
    {
        UifMaquinaRuletaBienUsoSupport::resetCacheForTesting();
        JuegoUifDesdeAnitaResolver::resetCacheForTesting();

        $peId = (int) Juego_Uif::query()->where('nombre', 'POSICION ELECTRONICA')->value('id');
        $slotsId = (int) Juego_Uif::query()->where('nombre', 'SLOTS')->value('id');
        $ruletaId = (int) Juego_Uif::query()->where('nombre', 'RULETA')->value('id');

        if ($peId <= 0) {
            throw new \RuntimeException('No existe juego_uif POSICION ELECTRONICA.');
        }
        if ($slotsId <= 0) {
            throw new \RuntimeException('No existe juego_uif SLOTS.');
        }
        if ($ruletaId <= 0) {
            throw new \RuntimeException('No existe juego_uif RULETA.');
        }

        $aSlots = [];
        $aRuleta = [];
        $porSala = [];
        $ejemplos = [];

        Cliente_Premio_Uif::query()
            ->select(['id', 'sala_id', 'juego_uif_id', 'posicion', 'monto', 'fechaentrega', 'numerotito'])
            ->where('juego_uif_id', $peId)
            ->orderBy('id')
            ->chunkById(500, function ($filas) use (
                $ruletaId,
                &$aSlots,
                &$aRuleta,
                &$porSala,
                &$ejemplos,
                $limiteEjemplos,
            ): void {
                foreach ($filas as $premio) {
                    $sala = (int) $premio->sala_id;
                    $empresaId = UifMaquinaRuletaBienUsoSupport::empresaIdDesdeSalaUif($sala);
                    $destino = UifMaquinaRuletaBienUsoSupport::esRuletaElectronica(
                        $premio->posicion !== null ? (string) $premio->posicion : null,
                        $empresaId,
                    ) ? 'RULETA' : 'SLOTS';

                    if ($destino === 'RULETA') {
                        $aRuleta[] = (int) $premio->id;
                    } else {
                        $aSlots[] = (int) $premio->id;
                    }

                    $porSala[$sala] = ($porSala[$sala] ?? 0) + 1;

                    if (count($ejemplos) < $limiteEjemplos) {
                        $ejemplos[] = [
                            'id' => (int) $premio->id,
                            'sala_id' => $sala,
                            'destino' => $destino,
                            'posicion' => $premio->posicion,
                            'numerotito' => $premio->numerotito,
                            'monto' => (float) $premio->monto,
                            'fechaentrega' => (string) $premio->fechaentrega,
                        ];
                    }
                }
            });

        $actualizadosSlots = 0;
        $actualizadosRuleta = 0;

        if ($aplicar) {
            foreach (array_chunk($aSlots, 500) as $chunk) {
                $actualizadosSlots += Cliente_Premio_Uif::query()
                    ->whereIn('id', $chunk)
                    ->update(['juego_uif_id' => $slotsId, 'updated_at' => now()]);
            }
            foreach (array_chunk($aRuleta, 500) as $chunk) {
                $actualizadosRuleta += Cliente_Premio_Uif::query()
                    ->whereIn('id', $chunk)
                    ->update(['juego_uif_id' => $ruletaId, 'updated_at' => now()]);
            }
        }

        return [
            'dry_run' => ! $aplicar,
            'candidatos_slots' => count($aSlots),
            'candidatos_ruleta' => count($aRuleta),
            'actualizados_slots' => $actualizadosSlots,
            'actualizados_ruleta' => $actualizadosRuleta,
            'por_sala' => $porSala,
            'ejemplos' => $ejemplos,
        ];
    }
}
