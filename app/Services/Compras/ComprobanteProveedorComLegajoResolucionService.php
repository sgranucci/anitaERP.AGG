<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve COM sin facturar del legajo al generar comprobante desde precarga.
 */
class ComprobanteProveedorComLegajoResolucionService
{
    public function __construct(
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
    ) {}

    /**
     * @return array{
     *     recepciones_disponibles: Collection<int, Recepcion_Proveedor>,
     *     recepciones_seleccionadas: list<int>,
     *     com_resolucion: array{
     *         ambigua: bool,
     *         mensaje: string|null,
     *         importe_comparacion: float,
     *         importe_comparacion_etiqueta: string,
     *         ordencompra_id: int|null
     *     }
     * }
     */
    public function resolverDesdePrecarga(Precarga_Comprobante_Proveedor $precarga, ?Ordencompra $ordencompra): array
    {
        $sectorLegajoId = $this->resolverSectorLegajo($precarga, $ordencompra);
        $recepciones = $this->recepcionesSupport->listarSinFacturarEnLegajo(
            (int) $precarga->proveedor_id,
            (int) $precarga->empresa_id,
            $sectorLegajoId,
        );

        $recepciones = $this->recepcionesSupport->enriquecerConImporteProvision($recepciones);

        $conceptosIvacompra = $this->cargarConceptosPrecarga($precarga);
        $importeMeta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            (string) ($precarga->letra ?? ''),
            $precarga->proveedores?->condicioniva_id ?? Proveedor::query()->whereKey($precarga->proveedor_id)->value('condicioniva_id'),
            (float) $precarga->total,
            (float) $precarga->subtotal,
            $precarga->precarga_comprobante_proveedor_conceptos->map(function ($linea) use ($conceptosIvacompra) {
                $linea->setRelation('concepto_ivacompras', $conceptosIvacompra->get((int) $linea->concepto_ivacompra_id));

                return $linea;
            }),
        );

        $seleccion = $this->resolverSeleccion($recepciones, $importeMeta['importe']);

        return [
            'recepciones_disponibles' => $recepciones,
            'recepciones_seleccionadas' => $seleccion['ids'],
            'com_resolucion' => [
                'ambigua' => $seleccion['ambigua'],
                'mensaje' => $seleccion['mensaje'],
                'importe_comparacion' => $importeMeta['importe'],
                'importe_comparacion_etiqueta' => $importeMeta['etiqueta'],
                'ordencompra_id' => $seleccion['ordencompra_id'],
            ],
        ];
    }

    /**
     * Aplica modo ASIGNA_RECEPCION y OC vinculada cuando hay COM en el legajo.
     *
     * @param  array<string, mixed>  $prefill
     *
     * @return array<string, mixed>
     */
    public function aplicarAlPrefill(array $prefill, Precarga_Comprobante_Proveedor $precarga, ?Ordencompra $ordencompra): array
    {
        $resolucion = $this->resolverDesdePrecarga($precarga, $ordencompra);

        if ($resolucion['recepciones_disponibles']->isEmpty()) {
            return $prefill;
        }

        /** @var \App\Models\Compras\Comprobante_Proveedor $data */
        $data = $prefill['data'];
        $data->modo_carga = ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;

        $ordencompraId = $resolucion['com_resolucion']['ordencompra_id']
            ?? $ordencompra?->id
            ?? (int) ($data->ordencompra_id ?? 0);

        if ($ordencompraId > 0) {
            $data->ordencompra_id = $ordencompraId;
            if (! $ordencompra || (int) $ordencompra->id !== $ordencompraId) {
                $ordencompra = Ordencompra::query()->find($ordencompraId);
            }
            $data->setRelation('ordencompras', $ordencompra);
        }

        return array_merge($prefill, [
            'recepciones_disponibles' => $resolucion['recepciones_disponibles'],
            'recepciones_seleccionadas' => $resolucion['recepciones_seleccionadas'],
            'com_resolucion' => $resolucion['com_resolucion'],
        ]);
    }

    private function resolverSectorLegajo(Precarga_Comprobante_Proveedor $precarga, ?Ordencompra $ordencompra): ?int
    {
        if ($ordencompra?->sector_legajocompra_id) {
            return (int) $ordencompra->sector_legajocompra_id;
        }

        $sectorUsuario = Auth::user()?->sector_legajocompra_id;

        return $sectorUsuario ? (int) $sectorUsuario : null;
    }

    /** @return Collection<int, Concepto_Ivacompra> */
    private function cargarConceptosPrecarga(Precarga_Comprobante_Proveedor $precarga): Collection
    {
        $ids = $precarga->precarga_comprobante_proveedor_conceptos
            ->pluck('concepto_ivacompra_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            return collect();
        }

        return Concepto_Ivacompra::query()->whereIn('id', $ids)->get()->keyBy('id');
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     *
     * @return array{ids: list<int>, ambigua: bool, mensaje: string|null, ordencompra_id: int|null}
     */
    private function resolverSeleccion(Collection $recepciones, float $importeComprobante): array
    {
        if ($recepciones->isEmpty()) {
            return [
                'ids' => [],
                'ambigua' => false,
                'mensaje' => null,
                'ordencompra_id' => null,
            ];
        }

        if ($recepciones->count() === 1) {
            $unica = $recepciones->first();

            return [
                'ids' => [(int) $unica->id],
                'ambigua' => false,
                'mensaje' => null,
                'ordencompra_id' => (int) ($unica->ordencompra_id ?? 0) ?: null,
            ];
        }

        $coincidencias = $recepciones->filter(function (Recepcion_Proveedor $rec) use ($importeComprobante) {
            $importeCom = (float) ($rec->importe_provision_com ?? 0);

            return ComprobanteProveedorImporteComparacionComSupport::coinciden($importeComprobante, $importeCom);
        })->values();

        if ($coincidencias->count() === 1) {
            $elegida = $coincidencias->first();

            return [
                'ids' => [(int) $elegida->id],
                'ambigua' => false,
                'mensaje' => null,
                'ordencompra_id' => (int) ($elegida->ordencompra_id ?? 0) ?: null,
            ];
        }

        $importeFmt = number_format($importeComprobante, 2, ',', '.');
        $mensaje = $coincidencias->isEmpty()
            ? 'Hay '.$recepciones->count().' COM sin facturar en el legajo y ninguna coincide con el importe del comprobante ('.$importeFmt.'). Seleccione la recepción correcta.'
            : 'Hay '.$coincidencias->count().' COM sin facturar con el mismo importe ('.$importeFmt.'). Seleccione cuál corresponde a esta factura.';

        return [
            'ids' => [],
            'ambigua' => true,
            'mensaje' => $mensaje,
            'ordencompra_id' => null,
        ];
    }
}
