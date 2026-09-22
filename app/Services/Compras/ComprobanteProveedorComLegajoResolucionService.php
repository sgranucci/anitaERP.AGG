<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorImporteYaFacturadoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
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
        $conceptosLineas = $precarga->precarga_comprobante_proveedor_conceptos->map(function ($linea) use ($conceptosIvacompra) {
            $linea->setRelation('concepto_ivacompras', $conceptosIvacompra->get((int) $linea->concepto_ivacompra_id));

            return $linea;
        });
        $incluirIi = ComprobanteProveedorImporteComparacionComSupport::provisionIncluyeImpuestoInterno($recepciones);
        $importeMeta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            (string) ($precarga->letra ?? ''),
            $precarga->proveedores?->condicioniva_id ?? Proveedor::query()->whereKey($precarga->proveedor_id)->value('condicioniva_id'),
            (float) $precarga->total,
            (float) $precarga->subtotal,
            $conceptosLineas,
            $incluirIi,
        );
        // Manda la moneda de la factura: comparar en esa moneda (no forzar a pesos).
        $importeFactura = (float) $importeMeta['importe'];

        $forzada = $this->resolverSeleccionAsignadaBandeja($precarga, $recepciones, $importeFactura, $importeMeta['etiqueta']);
        if ($forzada !== null) {
            return $forzada;
        }

        $yaPorCom = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarAnticipadasSinComAPorRecepcion(
            ComprobanteProveedorImporteYaFacturadoLegajoSupport::importePorRecepcion(
                $recepciones->pluck('id')->all(),
                null,
                (int) ($precarga->moneda_id ?? 1),
                (float) ($precarga->cotizacion ?? 0),
                $precarga->fechafactura ?? null,
            ),
            (int) ($ordencompra->id ?? 0),
            ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($ordencompra),
            null,
            (int) ($precarga->moneda_id ?? 1),
            (float) ($precarga->cotizacion ?? 0),
            $precarga->fechafactura ?? null,
        );
        $seleccion = $this->resolverSeleccion(
            $recepciones,
            $importeFactura,
            $yaPorCom,
        );

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
     * Aplica modo y OC vinculada cuando hay COM en el legajo.
     *
     * OC anticipada: no fuerza COM. Default = factura anticipada (ASIGNA_OC); solo aplica
     * recepción si Compras la asignó en la bandeja. El operador puede cambiar a COM.
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
        if (OrdencompraContratoRutaFacturaSupport::aplicaSinRecepcion($ordencompra, $fechaFactura)
            || ! OrdencompraLegajoDocumentoTipoSupport::exigeCom(
                OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($precarga)
            )) {
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

        $idsBandeja = $this->idsComAsignadasBandeja($precarga, $resolucion['recepciones_disponibles']);
        $esAnticipada = ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($ordencompra);
        $permiteComAnticipada = ComprobanteProveedorFlujoOcComFacSupport::permiteAsignarComEnLegajoAnticipado(
            $ordencompra,
            (int) ($data->id ?? 0) ?: null
        );

        // Anticipada 50/50: 1ª factura siempre sin COM (aunque bandeja tenga asignación errónea).
        if ($esAnticipada && ! $permiteComAnticipada) {
            $data->modo_carga = ComprobanteProveedorModoCarga::ASIGNA_OC;

            return array_merge($prefill, [
                'recepciones_disponibles' => $resolucion['recepciones_disponibles'],
                'recepciones_seleccionadas' => [],
                'com_resolucion' => [
                    'ambigua' => false,
                    'mensaje' => ComprobanteProveedorFlujoOcComFacSupport::mensajeBloqueaComPrimeraAnticipada(),
                    'importe_comparacion' => (float) ($resolucion['com_resolucion']['importe_comparacion'] ?? 0),
                    'importe_comparacion_etiqueta' => (string) ($resolucion['com_resolucion']['importe_comparacion_etiqueta'] ?? ''),
                    'ordencompra_id' => $ordencompraId > 0 ? $ordencompraId : null,
                ],
            ]);
        }

        // Anticipada con COM ya habilitada: no pre-marcar recepción salvo asignación en bandeja.
        if ($esAnticipada && $idsBandeja === []) {
            $data->modo_carga = ComprobanteProveedorModoCarga::ASIGNA_OC;

            return array_merge($prefill, [
                'recepciones_disponibles' => $resolucion['recepciones_disponibles'],
                'recepciones_seleccionadas' => [],
                'com_resolucion' => [
                    'ambigua' => false,
                    'mensaje' => 'OC anticipada con COM disponible: se carga como factura anticipada. '
                        .'Si corresponde, cambie el modo y asigne la recepción.',
                    'importe_comparacion' => (float) ($resolucion['com_resolucion']['importe_comparacion'] ?? 0),
                    'importe_comparacion_etiqueta' => (string) ($resolucion['com_resolucion']['importe_comparacion_etiqueta'] ?? ''),
                    'ordencompra_id' => $ordencompraId > 0 ? $ordencompraId : null,
                ],
            ]);
        }

        $data->modo_carga = ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
        $seleccionadas = $idsBandeja !== []
            ? $idsBandeja
            : $resolucion['recepciones_seleccionadas'];

        return array_merge($prefill, [
            'recepciones_disponibles' => $resolucion['recepciones_disponibles'],
            'recepciones_seleccionadas' => $seleccionadas,
            'com_resolucion' => $resolucion['com_resolucion'],
        ]);
    }

    /**
     * @param  Collection<int, Recepcion_Proveedor>  $recepciones
     * @return list<int>
     */
    private function idsComAsignadasBandeja(Precarga_Comprobante_Proveedor $precarga, Collection $recepciones): array
    {
        if ($recepciones->isEmpty()) {
            return [];
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
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
            return [];
        }

        $disponibles = $recepciones->pluck('id')->map(static fn ($id) => (int) $id)->all();

        return array_values(array_intersect($asignadas, $disponibles));
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

        $incluirIi = ComprobanteProveedorImporteComparacionComSupport::provisionIncluyeImpuestoInterno($recepciones);
        $importeMeta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            $letra,
            $condicionivaProveedorId,
            $total,
            $subtotal,
            $conceptos,
            $incluirIi,
        );
        // Manda la moneda de la factura.
        $importe = (float) $importeMeta['importe'];

        $yaPorCom = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarAnticipadasSinComAPorRecepcion(
            ComprobanteProveedorImporteYaFacturadoLegajoSupport::importePorRecepcion(
                $recepciones->pluck('id')->all(),
                $excluirComprobanteId,
                $monedaId,
                $cotizacion,
                $fechaFacturaYmd,
            ),
            (int) $ordencompra->id,
            ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($ordencompra),
            $excluirComprobanteId,
            $monedaId,
            $cotizacion,
            $fechaFacturaYmd,
        );

        $provisionFactura = static function (Recepcion_Proveedor $rec) use ($yaPorCom): float {
            $bruta = (float) (
                $rec->importe_provision_com_factura
                ?? $rec->importe_provision_com
                ?? 0
            );
            $ya = (float) ($yaPorCom[(int) $rec->id] ?? 0);

            return ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible($bruta, $ya);
        };

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

        // Solo se asigna sola cuando no hay duda posible: una única candidata por importe.
        // Antes elegía la primera entre varias, o la más cercana cuando ninguna coincidía,
        // y avisaba; ese automatismo silencioso es el que ataba COM a la factura equivocada.
        $etiquetarComs = static function (Collection $coms): string {
            return $coms
                ->map(static function (Recepcion_Proveedor $rec): string {
                    $nro = trim((string) ($rec->numerorecepcion ?? ''));

                    return $nro !== '' ? 'Nº '.$nro : '#'.$rec->id;
                })
                ->implode(', ');
        };

        if ($candidatas->count() > 1) {
            return [
                'ids' => [],
                'auto' => false,
                'aviso' => sprintf(
                    'Hay %d COM pendientes cuyo neto coincide con el %s de la factura (%s): %s. '
                    .'Elija la COM correcta: el sistema no la asigna por usted cuando hay más de una opción.',
                    $candidatas->count(),
                    $importeMeta['etiqueta'],
                    number_format($importe, 2, ',', '.'),
                    $etiquetarComs($candidatas),
                ),
                'ordencompra_id' => null,
                'importe_comparacion' => $importe,
                'etiqueta' => $importeMeta['etiqueta'],
            ];
        }

        if ($candidatas->isEmpty()) {
            return [
                'ids' => [],
                'auto' => false,
                'aviso' => sprintf(
                    'Ninguna COM pendiente coincide con el %s de la factura (%s). COM disponibles: %s. '
                    .'Elija la COM manualmente o revise el importe de la factura.',
                    $importeMeta['etiqueta'],
                    number_format($importe, 2, ',', '.'),
                    $recepciones->isNotEmpty() ? $etiquetarComs($recepciones) : 'ninguna',
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
                'COM #%s asignada automáticamente (única pendiente con %s ≈ %s).',
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
        $ids = $this->idsComAsignadasBandeja($precarga, $recepciones);
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
     * @param  array<int, float>  $yaFacturadoPorRecepcion
     *
     * @return array{ids: list<int>, ambigua: bool, mensaje: string|null, ordencompra_id: int|null}
     */
    private function resolverSeleccion(
        Collection $recepciones,
        float $importeComprobante,
        array $yaFacturadoPorRecepcion = [],
    ): array {
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

        $coincidencias = $recepciones->filter(function (Recepcion_Proveedor $rec) use ($importeComprobante, $yaFacturadoPorRecepcion) {
            $importeComBruto = (float) (
                $rec->importe_provision_com_factura
                ?? $rec->importe_provision_com
                ?? 0
            );
            $importeCom = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
                $importeComBruto,
                (float) ($yaFacturadoPorRecepcion[(int) $rec->id] ?? 0),
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
