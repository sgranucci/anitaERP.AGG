<?php

namespace App\Services\Compras;

use App\Models\Compras\CumplimientoRequisicionCompra;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Repositories\Compras\CumplimientoRequisicionCompraRepositoryInterface;
use Carbon\Carbon;

class CumplimientoRequisicionCompraImpresionService
{
    public function __construct(
        private CumplimientoRequisicionCompraRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeId(int $cumplimientoId): array
    {
        /** @var CumplimientoRequisicionCompra|null $cumplimiento */
        $cumplimiento = $this->repository->findConDetalle($cumplimientoId);
        if (! $cumplimiento) {
            throw new \RuntimeException('Cumplimiento no encontrado.');
        }

        $cabeceras = [];
        $filas = [];
        foreach ($cumplimiento->articulos as $linea) {
            $req = $linea->requisicion;
            $reqId = (int) $linea->requisicion_id;
            if ($req && ! isset($cabeceras[$reqId])) {
                $cabeceras[$reqId] = [
                    'id' => $req->id,
                    'numerorequisicion' => $req->numerorequisicion,
                    'fecha' => $req->fecha ? Carbon::parse($req->fecha)->format('d/m/Y') : null,
                    'empresa' => $req->empresas?->nombre,
                    'deposito_origen' => $linea->depositoOrigen?->nombre,
                    'deposito_origen_codigo' => $linea->depositoOrigen?->codigo,
                    'deposito_destino' => $linea->depositoDestino?->nombre,
                    'deposito_destino_codigo' => $linea->depositoDestino?->codigo,
                    'centrocosto' => trim(($req->centrocostos?->codigo ?? '').' '.($req->centrocostos?->nombre)),
                ];
            }

            $filas[] = [
                'requisicion_nro' => $req?->numerorequisicion ?? $reqId,
                'sku' => $linea->articulo?->sku,
                'descripcion' => $linea->articulo?->descripcion,
                'entrega' => (float) $linea->cantidad_entrega,
                'pendiente_restante' => max(0, (float) $linea->cantidad_pendiente_antes - (float) $linea->cantidad_entrega),
                'precio' => (float) ($linea->precio ?? 0),
                'deposito_origen_codigo' => $linea->depositoOrigen?->codigo,
                'deposito_origen' => $linea->depositoOrigen?->nombre,
                'deposito_destino_codigo' => $linea->depositoDestino?->codigo,
                'deposito_destino' => $linea->depositoDestino?->nombre,
            ];
        }

        $transferencias = $this->armarTransferencias($cumplimiento);

        return [
            'cumplimiento_id' => (int) $cumplimiento->id,
            'cumplimiento_numero' => (int) $cumplimiento->numero,
            'referencia' => CumplirRequisicionCompraPdfService::armarReferenciaImpresion(array_values($cabeceras)),
            'cabeceras' => array_values($cabeceras),
            'filas' => $filas,
            'transferencias' => $transferencias,
            'leyenda' => $cumplimiento->leyenda,
            'usuario' => $cumplimiento->usuario?->nombre ?? '',
            'generado_en' => optional($cumplimiento->fecha)->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function armarTransferencias(CumplimientoRequisicionCompra $cumplimiento): array
    {
        $salida = [];
        foreach ($cumplimiento->transferencias as $pivot) {
            /** @var Transferencia_Mercaderia|null $tm */
            $tm = $pivot->transferenciaMercaderia;
            if (! $tm) {
                continue;
            }
            $salida[] = [
                'id' => (int) $tm->id,
                'codigo' => (string) ($tm->codigo ?? ''),
                'origen_codigo' => $tm->depositoOrigen?->codigo,
                'origen' => $tm->depositoOrigen?->nombre,
                'destino_codigo' => $tm->depositoDestino?->codigo,
                'destino' => $tm->depositoDestino?->nombre,
            ];
        }

        return $salida;
    }
}
