<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Stock\RecepcionProveedorDiferenciaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Illuminate\Support\Collection;

/**
 * Listado de recepciones / devoluciones vinculadas a una OC (solapa Recepciones en edición OC).
 */
class OrdencompraRecepcionesListadoService
{
    /**
     * @return array{
     *     recepciones: list<array<string, mixed>>,
     *     resumen: array{cantidad: int, con_precio_diferencia: int, pendientes_aplicar_precio: int}
     * }
     */
    public function listar(int $ordencompraId): array
    {
        $oc = Ordencompra::query()
            ->with(['ordencompra_articulos.articulos'])
            ->findOrFail($ordencompraId);

        $preciosOcActuales = $oc->ordencompra_articulos->mapWithKeys(
            static fn ($art) => [(int) $art->id => (float) $art->precio]
        );

        $recepciones = Recepcion_Proveedor::query()
            ->with([
                'monedas',
                'creousuarios',
                'recepcion_proveedor_articulos.articulos',
            ])
            ->where('ordencompra_id', $ordencompraId)
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();

        $conPrecioDiff = 0;
        $pendientesAplicar = 0;
        $items = [];

        foreach ($recepciones as $rec) {
            $lineas = $this->mapearLineas($rec, $preciosOcActuales);
            $pendienteAplicar = collect($lineas)->contains(
                static fn (array $l) => ! empty($l['pendiente_aplicar_precio_oc'])
            );

            if ($rec->fl_precio_diferencia
                || RecepcionProveedorDiferenciaSupport::recepcionTieneDiferenciaPrecioEstricta($rec)) {
                $conPrecioDiff++;
            }
            if ($pendienteAplicar) {
                $pendientesAplicar++;
            }

            $items[] = [
                'id' => (int) $rec->id,
                'tipo' => (string) $rec->tipo,
                'documento' => $this->documentoRecepcion($rec),
                'fecha' => $rec->fecha?->format('Y-m-d'),
                'estado' => (string) $rec->estado,
                'numerorecepcion' => $rec->numerorecepcion,
                'flags' => [
                    'fl_precio_diferencia' => (bool) $rec->fl_precio_diferencia,
                    'fl_diferencia_cantidad' => (bool) $rec->fl_diferencia_cantidad,
                    'fl_articulo_extra' => (bool) $rec->fl_articulo_extra,
                    'fl_faltante_oc' => (bool) $rec->fl_faltante_oc,
                    'fl_laboratorio' => (bool) $rec->fl_laboratorio,
                    'fl_linea_rechazada' => (bool) $rec->fl_linea_rechazada,
                ],
                'resumen_diferencias' => $rec->resumen_diferencias,
                'comentario_precio' => $rec->comentario_precio,
                'total' => $this->totalRecepcion($rec),
                'moneda' => $rec->monedas?->abreviatura ?? $rec->monedas?->nombre,
                'usuario' => $rec->creousuarios?->nombre,
                'observacion' => $rec->observacion,
                'anita_ref' => $this->anitaRefRecepcion($rec),
                'lineas' => $lineas,
                'pendiente_aplicar_precio_oc' => $pendienteAplicar,
                'puede_editar' => $rec->estado === RecepcionProveedorEstados::BORRADOR,
                'es_devolucion' => $rec->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION,
                'urls' => [
                    'editar' => route('editar_recepcion_proveedor', ['id' => $rec->id]),
                    'pdf' => route('recepcion_proveedor_com_pdf', ['id' => $rec->id, 'inline' => 1]),
                ],
            ];
        }

        return [
            'recepciones' => $items,
            'resumen' => [
                'cantidad' => count($items),
                'con_precio_diferencia' => $conPrecioDiff,
                'pendientes_aplicar_precio' => $pendientesAplicar,
            ],
        ];
    }

    /**
     * @param  Collection<int, float>  $preciosOcActuales
     * @return list<array<string, mixed>>
     */
    private function mapearLineas(Recepcion_Proveedor $rec, Collection $preciosOcActuales): array
    {
        $lineas = [];

        foreach ($rec->recepcion_proveedor_articulos as $linea) {
            $ocArtId = (int) ($linea->ordencompra_articulo_id ?? 0);
            $precioOcSnap = (float) ($linea->precio_ordencompra ?? 0);
            $precioRec = (float) ($linea->precio ?? 0);
            $precioOcActual = $ocArtId > 0 ? ($preciosOcActuales->get($ocArtId) ?? null) : null;
            $tipoLinea = (string) ($linea->tipo_linea ?? RecepcionProveedorDiferenciaSupport::TIPO_OC);

            $tieneDiff = (bool) $linea->fl_precio_diferencia
                || ($precioOcSnap > 0 && abs($precioRec - $precioOcSnap) >= 0.0001);

            $pendienteAplicar = $ocArtId > 0
                && $tipoLinea !== RecepcionProveedorDiferenciaSupport::TIPO_EXTRA
                && $tieneDiff
                && $precioOcActual !== null
                && abs($precioOcActual - $precioRec) >= 0.0001
                && $rec->estado === RecepcionProveedorEstados::CONFIRMADA
                && $rec->tipo === Recepcion_Proveedor::TIPO_RECEPCION;

            $lineas[] = [
                'id' => (int) $linea->id,
                'ordencompra_articulo_id' => $ocArtId > 0 ? $ocArtId : null,
                'sku' => $linea->articulos?->sku,
                'descripcion' => $linea->articulos?->nombre ?? $linea->articulos?->descripcion,
                'tipo_linea' => $tipoLinea,
                'cantidad' => (float) $linea->cantidad,
                'cantidad_rechazada' => (float) ($linea->cantidad_rechazada ?? 0),
                'precio_ordencompra_snapshot' => $precioOcSnap > 0 ? $precioOcSnap : null,
                'precio_recepcion' => $precioRec,
                'precio_oc_actual' => $precioOcActual,
                'fl_precio_diferencia' => $tieneDiff,
                'fl_cantidad_diferencia' => (bool) $linea->fl_cantidad_diferencia,
                'comentario_precio' => $linea->comentario_precio,
                'pendiente_aplicar_precio_oc' => $pendienteAplicar,
            ];
        }

        return $lineas;
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
        $sum = 0.0;
        foreach ($rec->recepcion_proveedor_articulos as $art) {
            $sum += (float) $art->cantidad * (float) $art->precio;
        }

        return $sum > 0 ? $sum : null;
    }
}
