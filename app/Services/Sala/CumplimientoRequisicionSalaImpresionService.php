<?php

namespace App\Services\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Repositories\Sala\CumplimientoRequisicionSalaRepositoryInterface;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;

class CumplimientoRequisicionSalaImpresionService
{
    use RequisicionSalaArticuloEstadoParcialTrait;

    public function __construct(
        private CumplimientoRequisicionSalaRepositoryInterface $repository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeId(int $cumplimientoId): array
    {
        $cumplimiento = $this->repository->findConDetalle($cumplimientoId);
        if (! $cumplimiento) {
            throw new \RuntimeException('Cumplimiento no encontrado.');
        }

        return $this->armarDesdeModelo($cumplimiento);
    }

    /**
     * @return array<string, mixed>
     */
    public function armarDesdeModelo(CumplimientoRequisicionSala $cumplimiento): array
    {
        $cabeceras = [];
        $filas = [];

        foreach ($cumplimiento->articulos as $linea) {
            $req = $linea->requisicionSala;
            $reqId = (int) $linea->requisicion_sala_id;
            if ($req && ! isset($cabeceras[$reqId])) {
                $depOrigen = $linea->depositoOrigen;
                $cabeceras[$reqId] = CumplirRequisicionSalaPdfService::armarCabeceraDesdeRequisicion($req, $depOrigen);
            }

            $pendienteRestante = max(0, (float) $linea->cantidad_pendiente_antes - (float) $linea->cantidad_entrega);
            $motivo = self::estadoParcialNombrePorValor((string) ($linea->estadoparcial ?? ''));

            $filas[] = [
                'requisicion_nro' => $req?->numerorequisicion ?? '',
                'sku' => $linea->articulo?->sku,
                'descripcion' => $linea->requisicionSalaArticulo?->descripcionArticulo()
                    ?? trim(($linea->articulo?->sku ?? '').' '.($linea->articulo?->descripcion ?? '')),
                'entrega' => (float) $linea->cantidad_entrega,
                'pendiente_restante' => $pendienteRestante,
                'precio' => (float) ($linea->requisicionSalaArticulo?->precio ?? 0),
                'deposito_origen_codigo' => $linea->depositoOrigen?->codigo,
                'deposito_origen' => $linea->depositoOrigen?->nombre,
                'uid' => $linea->uid,
                'npu' => $linea->numeroparte,
                'tecnico' => $linea->tecnicoLaboratorio?->nombre,
                'motivo_parcial' => $motivo,
            ];
        }

        $transferencias = [];
        foreach ($cumplimiento->transferencias as $pivot) {
            $tm = $pivot->transferenciaMercaderia;
            if (! $tm) {
                continue;
            }
            $transferencias[] = [
                'id' => (int) $tm->id,
                'codigo' => (string) ($tm->codigo ?? ''),
                'origen_codigo' => $tm->depositoOrigen?->codigo,
                'origen' => $tm->depositoOrigen?->nombre,
                'destino_codigo' => $tm->depositoDestino?->codigo,
                'destino' => $tm->depositoDestino?->nombre,
            ];
        }

        $cabecerasList = array_values($cabeceras);

        return [
            'cumplimiento_id' => (int) $cumplimiento->id,
            'cumplimiento_numero' => (int) $cumplimiento->numero,
            'cumplimiento_estado' => (string) $cumplimiento->estado,
            'referencia' => 'cumple_'.$cumplimiento->numero,
            'cabeceras' => $cabecerasList,
            'filas' => $filas,
            'transferencias' => $transferencias,
            'leyenda' => $cumplimiento->leyenda,
            'usuario' => $cumplimiento->usuario?->nombre ?? $cumplimiento->usuario?->email ?? '',
            'generado_en' => optional($cumplimiento->fecha)->format('d/m/Y H:i'),
        ];
    }
}
