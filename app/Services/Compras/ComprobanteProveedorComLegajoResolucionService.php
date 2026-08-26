<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
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
        if ($ordencompra) {
            $recepciones = $this->recepcionesSupport->listarDisponibles((int) $ordencompra->id, null, false);
        } else {
            $recepciones = $this->recepcionesSupport->listarSinFacturarEnLegajo(
                (int) $precarga->proveedor_id,
                (int) $precarga->empresa_id,
                $sectorLegajoId,
            );
        }

        $recepciones = $this->recepcionesSupport->enriquecerConImporteEnMonedaFactura(
            $recepciones,
            (int) ($precarga->moneda_id ?? 1),
            (float) ($precarga->cotizacion ?? 0),
            $precarga->fechafactura ?? null,
        );

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
        // Manda la moneda de la factura: comparar en esa moneda (no forzar a pesos).
        $importeFactura = (float) $importeMeta['importe'];

        $forzada = $this->resolverSeleccionAsignadaBandeja($precarga, $recepciones, $importeFactura, $importeMeta['etiqueta']);
        if ($forzada !== null) {
            return $forzada;
        }

        $seleccion = $this->resolverSeleccion($recepciones, $importeFactura);

        return [
            'recepciones_disponibles' => $recepciones,
            'recepciones_seleccionadas' => $seleccion['ids'],
            'com_resolucion' => [
                'ambigua' => $seleccion['ambigua'],
                'mensaje' => $seleccion['mensaje'],
                'importe_comparacion' => $importeFactura,
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

        $fechaFactura = null;
        if ($precarga->fechafactura) {
            $fechaFactura = \Carbon\Carbon::parse($precarga->fechafactura)->format('Y-m-d');
        }
        if (OrdencompraContratoRutaFacturaSupport::aplicaSinRecepcion($ordencompra, $fechaFactura)) {
            /** @var \App\Models\Compras\Comprobante_Proveedor $data */
            $data = $prefill['data'];
            $data->modo_carga = ComprobanteProveedorModoCarga::SIN_RECEPCION;

            return $prefill;
        }

        if ($resolucion['recepciones_disponibles']->isEmpty()) {
            return $prefill;
        }

        /** @var \App\Models\Compras\Comprobante_Proveedor $data */
        $data = $prefill['data'];
        $data->modo_carga = ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;

        // La OC de la precarga manda: una COM de otra OC del mismo proveedor no la reemplaza.
        $ordencompraId = $ordencompra?->id
            ?? $resolucion['com_resolucion']['ordencompra_id']
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

    /**
     * Si el circuito exige COM y no hay IDs elegidos: toma la primera pendiente
     * cuyo neto de provisión coincide con el importe neto/total de la factura.
     *
     * @param  iterable<object>  $conceptos
     * @return array{
     *     ids: list<int>,
     *     auto: bool,
     *     aviso: string|null,
     *     ordencompra_id: int|null,
     *     importe_comparacion: float,
     *     etiqueta: string
     * }
     */
    public function autoAsignarPrimeraPorImporteNeto(
        Ordencompra $ordencompra,
        string $letra,
        ?int $condicionivaProveedorId,
        float $total,
        float $subtotal,
        iterable $conceptos,
        ?int $excluirComprobanteId = null,
        int $monedaId = 1,
        float $cotizacion = 1.0,
        ?string $fechaFacturaYmd = null,
    ): array {
        $recepciones = $this->recepcionesSupport->listarDisponibles((int) $ordencompra->id, $excluirComprobanteId);
        if ($recepciones->isEmpty()) {
            $recepciones = $this->recepcionesSupport->listarSinFacturarEnLegajo(
                (int) $ordencompra->proveedor_id,
                (int) $ordencompra->empresa_id,
                $ordencompra->sector_legajocompra_id ? (int) $ordencompra->sector_legajocompra_id : null,
                $excluirComprobanteId,
            );
        }

        $recepciones = $this->recepcionesSupport
            ->enriquecerConImporteEnMonedaFactura(
                $recepciones,
                $monedaId,
                $cotizacion,
                $fechaFacturaYmd,
            )
            ->sortBy([
                fn (Recepcion_Proveedor $r) => $r->fecha?->format('Y-m-d') ?? '9999-99-99',
                fn (Recepcion_Proveedor $r) => (int) $r->id,
            ])
            ->values();

        $importeMeta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            $letra,
            $condicionivaProveedorId,
            $total,
            $subtotal,
            $conceptos,
        );
        // Manda la moneda de la factura.
        $importe = (float) $importeMeta['importe'];

        $provisionFactura = static fn (Recepcion_Proveedor $rec): float => (float) (
            $rec->importe_provision_com_factura
            ?? $rec->importe_provision_com
            ?? 0
        );

        if ($recepciones->isEmpty()) {
            return [
                'ids' => [],
                'auto' => false,
                'aviso' => null,
                'ordencompra_id' => null,
                'importe_comparacion' => $importe,
                'etiqueta' => $importeMeta['etiqueta'],
            ];
        }

        $toleranciaPct = ComprobanteProveedorToleranciaImporteSupport::porcentajeDesdeOc($ordencompra);

        $exactas = $recepciones->filter(function (Recepcion_Proveedor $rec) use ($importe, $provisionFactura) {
            return ComprobanteProveedorImporteComparacionComSupport::coinciden($importe, $provisionFactura($rec));
        })->values();

        $candidatas = $exactas;
        if ($candidatas->isEmpty()) {
            $candidatas = $recepciones->filter(function (Recepcion_Proveedor $rec) use ($importe, $toleranciaPct, $provisionFactura) {
                return ! ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(
                    $importe,
                    $provisionFactura($rec),
                    $toleranciaPct
                );
            })->values();
        }

        if ($candidatas->isEmpty()) {
            // Flujo simple: si hay una sola COM, asignarla igual (aviso); si hay varias, la más cercana.
            if ($recepciones->count() === 1) {
                /** @var Recepcion_Proveedor $unica */
                $unica = $recepciones->first();
                $prov = $provisionFactura($unica);

                return [
                    'ids' => [(int) $unica->id],
                    'auto' => true,
                    'aviso' => sprintf(
                        'COM #%s asignada automáticamente aunque el neto no coincide (factura %s vs provisión COM %s). Revise antes de contabilizar.',
                        $unica->id,
                        number_format($importe, 2, ',', '.'),
                        number_format($prov, 2, ',', '.')
                    ),
                    'ordencompra_id' => (int) ($unica->ordencompra_id ?? 0) ?: (int) $ordencompra->id,
                    'importe_comparacion' => $importe,
                    'etiqueta' => $importeMeta['etiqueta'],
                ];
            }

            $cercana = $recepciones->sortBy(function (Recepcion_Proveedor $rec) use ($importe, $provisionFactura) {
                return abs($provisionFactura($rec) - $importe);
            })->first();

            if ($cercana) {
                $prov = $provisionFactura($cercana);

                return [
                    'ids' => [(int) $cercana->id],
                    'auto' => true,
                    'aviso' => sprintf(
                        'COM #%s asignada por aproximación (factura %s vs provisión COM %s). Hay más de una COM: confirme la correcta antes de contabilizar.',
                        $cercana->id,
                        number_format($importe, 2, ',', '.'),
                        number_format($prov, 2, ',', '.')
                    ),
                    'ordencompra_id' => (int) ($cercana->ordencompra_id ?? 0) ?: (int) $ordencompra->id,
                    'importe_comparacion' => $importe,
                    'etiqueta' => $importeMeta['etiqueta'],
                ];
            }

            return [
                'ids' => [],
                'auto' => false,
                'aviso' => sprintf(
                    'No hay COM pendiente cuyo neto coincida con el %s de la factura (%s).',
                    $importeMeta['etiqueta'],
                    number_format($importe, 2, ',', '.')
                ),
                'ordencompra_id' => null,
                'importe_comparacion' => $importe,
                'etiqueta' => $importeMeta['etiqueta'],
            ];
        }

        /** @var Recepcion_Proveedor $elegida */
        $elegida = $candidatas->first();

        return [
            'ids' => [(int) $elegida->id],
            'auto' => true,
            'aviso' => sprintf(
                'COM #%s asignada automáticamente (primera pendiente con %s ≈ %s).',
                $elegida->id,
                $importeMeta['etiqueta'],
                number_format($provisionFactura($elegida), 2, ',', '.')
            ),
            'ordencompra_id' => (int) ($elegida->ordencompra_id ?? 0) ?: (int) $ordencompra->id,
            'importe_comparacion' => $importe,
            'etiqueta' => $importeMeta['etiqueta'],
        ];
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
     * COM persistida desde la bandeja de legajos: CxP abre la precarga con esa recepción ya elegida.
     *
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
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
     * }|null
     */
    private function resolverSeleccionAsignadaBandeja(
        Precarga_Comprobante_Proveedor $precarga,
        Collection $recepciones,
        float $importeFactura,
        string $etiqueta,
    ): ?array {
        if ($recepciones->isEmpty()) {
            return null;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return null;
        }

        $asignadas = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->where('precarga_comprobante_proveedor_id', $precarga->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->pluck('recepcion_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($asignadas === []) {
            return null;
        }

        $disponibles = $recepciones->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $ids = array_values(array_intersect($asignadas, $disponibles));
        if ($ids === []) {
            return null;
        }

        $primera = $recepciones->first(static fn (Recepcion_Proveedor $r) => in_array((int) $r->id, $ids, true));

        return [
            'recepciones_disponibles' => $recepciones,
            'recepciones_seleccionadas' => $ids,
            'com_resolucion' => [
                'ambigua' => false,
                'mensaje' => 'COM asignada desde la bandeja de legajos.',
                'importe_comparacion' => $importeFactura,
                'importe_comparacion_etiqueta' => $etiqueta,
                'ordencompra_id' => $primera ? ((int) ($primera->ordencompra_id ?? 0) ?: null) : null,
            ],
        ];
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
            $importeCom = (float) (
                $rec->importe_provision_com_factura
                ?? $rec->importe_provision_com
                ?? 0
            );

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
