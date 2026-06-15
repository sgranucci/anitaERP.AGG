<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use Illuminate\Support\Collection;

/**
 * Grilla unificada de historia de compras vinculadas a una orden de compra (OC, COM, devoluciones, facturas).
 */
class OrdencompraFlujoComprasHistoriaService
{
    public const TIPO_OC = 'OC';

    public const TIPO_OC_ESTADO = 'OC_ESTADO';

    public const TIPO_OC_LEGAJO = 'OC_LEGAJO';

    public const TIPO_COM = 'COM';

    public const TIPO_DEVOLUCION = 'DEVOLUCION';

    public const TIPO_COMPROBANTE = 'COMPROBANTE';

    /**
     * @return Collection<int, array{
     *     tipo: string,
     *     subtipo: string|null,
     *     fecha: string,
     *     documento: string,
     *     estado: string|null,
     *     total: float|null,
     *     moneda: string|null,
     *     usuario: string|null,
     *     observacion: string|null,
     *     entity_id: int,
     *     url_consulta: string|null,
     *     anita_ref: string|null
     * }>
     */
    public function listarPorOrdencompra(int $ordencompraId): Collection
    {
        $ordencompra = Ordencompra::query()
            ->with([
                'ordencompra_estados.usuarios',
                'ordencompra_historias.usuarios',
                'ordencompra_historias.sector_legajocompras',
            ])
            ->findOrFail($ordencompraId);

        $filas = collect();

        $filas->push([
            'tipo' => self::TIPO_OC,
            'subtipo' => 'ALTA',
            'fecha' => $ordencompra->fecha,
            'documento' => 'OC '.$ordencompra->numeroordencompra,
            'estado' => $ordencompra->estadoordencompra,
            'total' => null,
            'moneda' => null,
            'usuario' => $ordencompra->usuarios?->nombre ?? null,
            'observacion' => $ordencompra->comentario,
            'entity_id' => $ordencompra->id,
            'url_consulta' => route('editar_ordencompra', ['id' => $ordencompra->id]),
            'anita_ref' => null,
        ]);

        foreach ($ordencompra->ordencompra_estados->sortBy('fecha') as $est) {
            $filas->push([
                'tipo' => self::TIPO_OC_ESTADO,
                'subtipo' => 'CAMBIO_ESTADO',
                'fecha' => $est->fecha,
                'documento' => 'OC '.$ordencompra->numeroordencompra,
                'estado' => $est->estado,
                'total' => null,
                'moneda' => null,
                'usuario' => $est->usuarios?->nombre ?? null,
                'observacion' => $est->observacion,
                'entity_id' => $est->id,
                'url_consulta' => route('editar_ordencompra', ['id' => $ordencompra->id]),
                'anita_ref' => null,
            ]);
        }

        foreach ($ordencompra->ordencompra_historias->sortBy('fecha') as $hist) {
            $filas->push([
                'tipo' => self::TIPO_OC_LEGAJO,
                'subtipo' => $hist->sector_legajocompras?->nombre,
                'fecha' => $hist->fecha,
                'documento' => 'OC '.$ordencompra->numeroordencompra,
                'estado' => null,
                'total' => null,
                'moneda' => null,
                'usuario' => $hist->usuarios?->nombre ?? null,
                'observacion' => trim(($hist->observacion ?? '').' '.($hist->leyenda ?? '')),
                'entity_id' => $hist->id,
                'url_consulta' => route('editar_ordencompra', ['id' => $ordencompra->id]),
                'anita_ref' => null,
            ]);
        }

        $recepciones = Recepcion_Proveedor::query()
            ->with(['monedas', 'creousuarios', 'recepcion_proveedor_articulos'])
            ->where('ordencompra_id', $ordencompraId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        foreach ($recepciones as $rec) {
            $tipo = $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION
                ? self::TIPO_DEVOLUCION
                : self::TIPO_COM;

            $filas->push([
                'tipo' => $tipo,
                'subtipo' => $rec->tipo,
                'fecha' => $rec->fecha?->format('Y-m-d'),
                'documento' => $this->documentoRecepcion($rec),
                'estado' => $rec->estado,
                'total' => $this->totalRecepcion($rec),
                'moneda' => $rec->monedas?->abreviatura ?? $rec->monedas?->nombre,
                'usuario' => $rec->creousuarios?->nombre ?? null,
                'observacion' => $rec->observacion,
                'entity_id' => $rec->id,
                'url_consulta' => route('editar_recepcion_proveedor', ['id' => $rec->id]),
                'anita_ref' => $this->anitaRefRecepcion($rec),
            ]);
        }

        $comprobantes = Comprobante_Proveedor::query()
            ->with(['monedas', 'tipotransaccion_compras', 'creousuarios'])
            ->where('ordencompra_id', $ordencompraId)
            ->orderBy('fechacomprobante')
            ->orderBy('id')
            ->get();

        foreach ($comprobantes as $cp) {
            $filas->push([
                'tipo' => self::TIPO_COMPROBANTE,
                'subtipo' => $cp->modo_carga,
                'fecha' => $cp->fechacomprobante?->format('Y-m-d'),
                'documento' => $this->documentoComprobante($cp),
                'estado' => $cp->estado,
                'total' => (float) $cp->total,
                'moneda' => $cp->monedas?->abreviatura ?? $cp->monedas?->nombre,
                'usuario' => $cp->creousuarios?->nombre ?? null,
                'observacion' => $cp->leyenda,
                'entity_id' => $cp->id,
                'url_consulta' => null,
                'anita_ref' => $cp->anita_nro_interno ? (string) $cp->anita_nro_interno : null,
            ]);
        }

        return $filas->sortBy([
            fn ($row) => $row['fecha'] ?? '',
            fn ($row) => $row['tipo'],
            fn ($row) => $row['entity_id'],
        ])->values();
    }

    private function documentoRecepcion(Recepcion_Proveedor $rec): string
    {
        $prefijo = $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION ? 'DEV' : 'COM';
        $nro = $rec->numerorecepcion ?: $rec->id;

        if ($rec->anita_tipo && $rec->anita_sucursal && $rec->anita_nro) {
            return sprintf(
                '%s %s %c %d-%d',
                $prefijo,
                $rec->anita_tipo,
                $rec->anita_letra ?? ' ',
                $rec->anita_sucursal,
                $rec->anita_nro
            );
        }

        return $prefijo.' #'.$nro;
    }

    private function anitaRefRecepcion(Recepcion_Proveedor $rec): ?string
    {
        if (! $rec->anita_tipo) {
            return null;
        }

        return sprintf(
            '%s %c %d-%d',
            $rec->anita_tipo,
            $rec->anita_letra ?? ' ',
            $rec->anita_sucursal ?? 0,
            $rec->anita_nro ?? 0
        );
    }

    private function totalRecepcion(Recepcion_Proveedor $rec): ?float
    {
        if (! $rec->relationLoaded('recepcion_proveedor_articulos')) {
            $rec->load('recepcion_proveedor_articulos');
        }

        $sum = 0.0;
        foreach ($rec->recepcion_proveedor_articulos as $art) {
            $sum += (float) $art->cantidad * (float) $art->precio;
        }

        return $sum > 0 ? $sum : null;
    }

    private function documentoComprobante(Comprobante_Proveedor $cp): string
    {
        $tipo = $cp->tipotransaccion_compras?->abreviatura ?? 'FAC';

        return sprintf(
            '%s %c %d-%d',
            $tipo,
            $cp->letra,
            $cp->sucursal,
            $cp->numerocomprobante
        );
    }
}
