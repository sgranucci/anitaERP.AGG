<?php

namespace App\Services\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Models\Sala\CumplimientoRequisicionSalaArticulo;
use App\Models\Sala\CumplimientoRequisicionSalaTransferencia;
use App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface;
use Carbon\Carbon;

class CumplimientoRequisicionSalaPersistenciaService
{
    public function __construct(
        private CumplimientoRequisicionSalaRepositoryInterface $repository,
    ) {
    }

    /**
     * @param  list<array<string, mixed>>  $snapshots
     * @param  list<int>  $transferenciaIds
     */
    public function persistir(
        int $usuarioId,
        ?string $leyenda,
        array $snapshots,
        array $transferenciaIds,
        ?int $empresaId
    ): CumplimientoRequisicionSala {
        if ($snapshots === []) {
            throw new \RuntimeException('No hay líneas para persistir el cumplimiento.');
        }

        $cabecera = CumplimientoRequisicionSala::query()->create([
            'numero' => $this->repository->siguienteNumero(),
            'fecha' => Carbon::now(),
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'leyenda' => $leyenda !== '' && $leyenda !== null ? $leyenda : null,
            'estado' => CumplimientoRequisicionSala::ESTADO_ACTIVO,
        ]);

        foreach ($snapshots as $snap) {
            CumplimientoRequisicionSalaArticulo::query()->create([
                'cumplimiento_requisicion_sala_id' => $cabecera->id,
                'requisicion_sala_id' => (int) $snap['requisicion_sala_id'],
                'requisicion_sala_articulo_id' => (int) $snap['requisicion_sala_articulo_id'],
                'articulo_id' => (int) ($snap['articulo_id'] ?? 0) ?: null,
                'articulo_id_original' => ! empty($snap['articulo_id_original'])
                    ? (int) $snap['articulo_id_original']
                    : null,
                'cantidad_entrega' => (float) ($snap['cantidad_entrega'] ?? 0),
                'cantidad_pendiente_antes' => (float) ($snap['cantidad_pendiente_antes'] ?? 0),
                'cantidadentregada_antes' => (float) ($snap['cantidadentregada_antes'] ?? 0),
                'deposito_origen_id' => $snap['deposito_origen_id'] ?? null,
                'tecnico_laboratorio_id' => $snap['tecnico_laboratorio_id'] ?? null,
                'numeroparte' => $snap['numeroparte'] ?? null,
                'uid' => $snap['uid'] ?? null,
                'destino' => $snap['destino'] ?? null,
                'estado_linea' => $snap['estado_linea'] ?? null,
                'estadoparcial' => $snap['estadoparcial'] ?? null,
                'fecha_entrega' => $snap['fecha_entrega'] ?? null,
                'numeroremito' => $snap['numeroremito'] ?? null,
                'nombreresponsable' => $snap['nombreresponsable'] ?? null,
                'estado_linea_antes' => $snap['estado_linea_antes'] ?? null,
                'estadoparcial_antes' => $snap['estadoparcial_antes'] ?? null,
                'fecha_entrega_antes' => $snap['fecha_entrega_antes'] ?? null,
                'numeroremito_antes' => $snap['numeroremito_antes'] ?? null,
                'nombreresponsable_antes' => $snap['nombreresponsable_antes'] ?? null,
                'tecnico_laboratorio_id_antes' => $snap['tecnico_laboratorio_id_antes'] ?? null,
                'deposito_origen_id_antes' => $snap['deposito_origen_id_antes'] ?? null,
                'numeroparte_antes' => $snap['numeroparte_antes'] ?? null,
            ]);
        }

        foreach (array_values(array_unique(array_filter(array_map('intval', $transferenciaIds)))) as $tmId) {
            if ($tmId <= 0) {
                continue;
            }
            CumplimientoRequisicionSalaTransferencia::query()->create([
                'cumplimiento_requisicion_sala_id' => $cabecera->id,
                'transferencia_mercaderia_id' => $tmId,
            ]);
        }

        return $cabecera;
    }
}
