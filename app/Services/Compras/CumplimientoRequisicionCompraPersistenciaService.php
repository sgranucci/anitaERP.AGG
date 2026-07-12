<?php

namespace App\Services\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use App\Models\Compras\CumplimientoRequisicionCompraArticulo;
use App\Models\Compras\CumplimientoRequisicionCompraTransferencia;
use App\Repositories\Compras\CumplimientoRequisicionCompraRepositoryInterface;
use Carbon\Carbon;

class CumplimientoRequisicionCompraPersistenciaService
{
    public function __construct(
        private CumplimientoRequisicionCompraRepositoryInterface $repository,
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
    ): CumplimientoRequisicionCompra {
        if ($snapshots === []) {
            throw new \RuntimeException('No hay líneas para persistir el cumplimiento.');
        }

        $cabecera = CumplimientoRequisicionCompra::query()->create([
            'numero' => $this->repository->siguienteNumero(),
            'fecha' => Carbon::now(),
            'usuario_id' => $usuarioId,
            'empresa_id' => $empresaId,
            'leyenda' => $leyenda !== '' && $leyenda !== null ? $leyenda : null,
            'estado' => CumplimientoRequisicionCompra::ESTADO_ACTIVO,
        ]);

        foreach ($snapshots as $snap) {
            CumplimientoRequisicionCompraArticulo::query()->create([
                'cumplimiento_requisicion_compra_id' => $cabecera->id,
                'requisicion_id' => (int) $snap['requisicion_id'],
                'requisicion_articulo_id' => (int) $snap['requisicion_articulo_id'],
                'articulo_id' => (int) ($snap['articulo_id'] ?? 0) ?: null,
                'articulo_id_original' => ! empty($snap['articulo_id_original']) ? (int) $snap['articulo_id_original'] : null,
                'cantidad_entrega' => (float) ($snap['cantidad_entrega'] ?? 0),
                'cantidad_pendiente_antes' => (float) ($snap['cantidad_pendiente_antes'] ?? 0),
                'cantidadentregada_antes' => (float) ($snap['cantidadentregada_antes'] ?? 0),
                'deposito_origen_id' => $snap['deposito_origen_id'] ?? null,
                'deposito_destino_id' => $snap['deposito_destino_id'] ?? null,
                'precio' => $snap['precio'] ?? null,
                'moneda_id' => $snap['moneda_id'] ?? null,
                'centrocostodestino_id' => $snap['centrocostodestino_id'] ?? null,
                'detalle' => $snap['detalle'] ?? null,
                'estado_requisicion_antes' => $snap['estado_requisicion_antes'] ?? null,
            ]);
        }

        foreach (array_values(array_unique(array_filter(array_map('intval', $transferenciaIds)))) as $tmId) {
            if ($tmId <= 0) {
                continue;
            }
            CumplimientoRequisicionCompraTransferencia::query()->create([
                'cumplimiento_requisicion_compra_id' => $cabecera->id,
                'transferencia_mercaderia_id' => $tmId,
            ]);
        }

        return $cabecera;
    }
}
