<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorCambioCotizacionService;
use App\Support\Compras\ComprobanteProveedorControlesConfigSupport;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorLineasFacturaSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;

/**
 * Controles de negocio al cargar factura de proveedor vinculada a legajo (OC + COM).
 *
 * @phpstan-type ResultadoControles array{
 *     ok: bool,
 *     avisos: list<string>,
 *     errores: list<string>,
 *     cotizaciones_actualizadas: list<int>,
 *     devolvio_compras: bool,
 *     recepcion_ids_efectivos: list<int>
 * }
 */
class ComprobanteProveedorControlesLegajoService
{
    private const EPS_COTIZACION = 0.0000005;

    public function __construct(
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private RecepcionProveedorCambioCotizacionService $cambioCotizacionService,
        private OrdencompraDevolverAComprasNotificacionService $devolverCompras,
        private ComprobanteProveedorComLegajoResolucionService $comLegajoResolucion,
        private ComprobanteProveedorMatchLineasService $matchLineas,
    ) {}

    /**
     * @param  list<int|string>  $recepcionIds
     * @param  iterable<object>  $conceptos
     * @param  iterable<int, mixed>|null  $lineasFactura
     * @return ResultadoControles
     */
    public function validarYAplicar(
        ?Ordencompra $ordencompra,
        string $modoCarga,
        array $recepcionIds,
        float $cotizacionFactura,
        int $monedaId,
        string $fechaComprobanteYmd,
        string $letra,
        ?int $condicionivaProveedorId,
        float $total,
        float $subtotal,
        iterable $conceptos,
        ?int $excluirComprobanteId = null,
        bool $estricto = true,
        ?iterable $lineasFactura = null,
    ): array {
        $resultado = [
            'ok' => true,
            'avisos' => [],
            'errores' => [],
            'cotizaciones_actualizadas' => [],
            'devolvio_compras' => false,
            'recepcion_ids_efectivos' => [],
        ];

        if (! $ordencompra) {
            return $resultado;
        }

        $cfgControles = ComprobanteProveedorControlesConfigSupport::paraEmpresa((int) $ordencompra->empresa_id);
        if (! $cfgControles['activo']) {
            // Controles de legajo desactivados en configuración de la empresa.
            return $resultado;
        }

        $tieneComDisponibles = $this->tieneComDisponiblesEnLegajo($ordencompra, $excluirComprobanteId);
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica($ordencompra, $tieneComDisponibles);

        if ($politica['bloquea_sin_com']) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'Esta empresa exige el flujo OC/COM/factura: no hay recepción COM disponible '
                .'y la orden no es anticipada. Debe confirmar una COM con provisión o marcar la OC como anticipada.';

            return $resultado;
        }

        if ($politica['debe_asignar_com'] && $modoCarga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'El legajo tiene recepciones COM disponibles: el modo de carga debe ser «Factura contra recepción (COM)».';

            return $resultado;
        }

        if ($politica['permite_factura_anticipada']
            && $modoCarga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'OC anticipada sin COM: use el modo «Factura contra orden de compra» (factura anticipada). '
                .'Puede cargar más de una factura anticipada en el mismo legajo.';

            return $resultado;
        }

        if ($modoCarga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            return $resultado;
        }

        $ids = collect($recepcionIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty() && ($politica['debe_asignar_com'] || $tieneComDisponibles)) {
            $auto = $this->comLegajoResolucion->autoAsignarPrimeraPorImporteNeto(
                $ordencompra,
                $letra,
                $condicionivaProveedorId,
                $total,
                $subtotal,
                $conceptos,
                $excluirComprobanteId,
                $monedaId,
                $cotizacionFactura,
                $fechaComprobanteYmd,
            );
            if ($auto['ids'] !== []) {
                $ids = collect($auto['ids']);
                if (! empty($auto['aviso'])) {
                    $resultado['avisos'][] = (string) $auto['aviso'];
                }
                if (! empty($auto['ordencompra_id']) && (int) $ordencompra->id !== (int) $auto['ordencompra_id']) {
                    // Mantener OC del form; solo aviso.
                    $resultado['avisos'][] = 'COM auto-asignada pertenece a OC #'.$auto['ordencompra_id'].'.';
                }
            } elseif (! empty($auto['aviso'])) {
                $resultado['ok'] = false;
                $resultado['errores'][] = (string) $auto['aviso'];

                return $resultado;
            }
        }

        if ($ids->isEmpty()) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'Debe seleccionar al menos una recepción COM para asociar a la factura del legajo.';

            return $resultado;
        }

        $resultado['recepcion_ids_efectivos'] = $ids->all();

        $recepciones = Recepcion_Proveedor::query()
            ->whereIn('id', $ids->all())
            ->get();

        if ($recepciones->count() !== $ids->count()) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'Una o más recepciones seleccionadas no existen.';

            return $resultado;
        }

        $importeMeta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            $letra,
            $condicionivaProveedorId,
            $total,
            $subtotal,
            $conceptos,
        );
        $importeFactura = (float) $importeMeta['importe'];

        $recepciones = $this->recepcionesSupport->enriquecerConImporteEnMonedaFactura(
            $recepciones,
            $monedaId,
            $cotizacionFactura,
            $fechaComprobanteYmd,
        );
        $importeComFactura = round((float) $recepciones->sum(
            fn ($r) => (float) ($r->importe_provision_com_factura ?? $r->importe_provision_com ?? 0)
        ), 2);
        $importeComMe = round((float) $recepciones->sum(
            fn ($r) => (float) ($r->importe_provision_com ?? 0)
        ), 2);

        $toleranciaPct = ComprobanteProveedorToleranciaImporteSupport::porcentajeParaOc(
            (int) $ordencompra->empresa_id,
            (int) ($ordencompra->centrocosto_id ?? 0) ?: null,
        );

        if (ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($importeFactura, $importeComFactura, $toleranciaPct)) {
            $detalle = sprintf(
                'Importe factura (%s) %s vs provisión COM %s%s. Tolerancia permitida: %s%% (centro de costo de la OC).',
                $importeMeta['etiqueta'],
                number_format($importeFactura, 2, ',', '.'),
                number_format($importeComFactura, 2, ',', '.'),
                abs($importeComMe - $importeComFactura) > 0.05
                    ? ' (ME origen '.number_format($importeComMe, 2, ',', '.').')'
                    : '',
                number_format($toleranciaPct, 2, ',', '.'),
            );
            if (! $estricto) {
                // Generación de borrador / PDF+IA: no bloquear ni devolver a Compras.
                $resultado['avisos'][] = $detalle.' Revise la COM asignada antes de contabilizar.';

                return $resultado;
            }
            $this->devolverCompras->devolver(
                (int) $ordencompra->id,
                'Diferencia de importe factura vs recepción fuera de tolerancia',
                $detalle,
            );
            $resultado['ok'] = false;
            $resultado['devolvio_compras'] = true;
            $resultado['errores'][] = $detalle.' El legajo fue devuelto a COMPRAS y se notificó por correo.';

            return $resultado;
        }

        $match = $this->matchLineas->validar(
            $ordencompra,
            $ids->all(),
            $this->resolverLineasFacturaParaMatch($lineasFactura, $excluirComprobanteId),
            $estricto,
            (int) ($ordencompra->proveedor_id ?? 0),
        );
        foreach ($match['avisos'] as $aviso) {
            $resultado['avisos'][] = $aviso;
        }
        foreach ($match['errores'] as $error) {
            $resultado['ok'] = false;
            $resultado['errores'][] = $error;
        }
        if (! $resultado['ok']) {
            return $resultado;
        }

        if (! ComprobanteProveedorCotizacionSupport::esMonedaExtranjera($monedaId)) {
            return $resultado;
        }

        $cotFactura = round($cotizacionFactura, 6);
        if ($cotFactura <= 0) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'La cotización de la factura debe ser mayor a cero.';

            return $resultado;
        }

        $mesFactura = substr($fechaComprobanteYmd, 0, 7);
        $distintas = $recepciones->filter(function (Recepcion_Proveedor $r) use ($cotFactura) {
            return abs((float) ($r->cotizacion ?: 1) - $cotFactura) > self::EPS_COTIZACION;
        });

        if ($distintas->isEmpty()) {
            return $resultado;
        }

        $otroMes = $distintas->first(function (Recepcion_Proveedor $r) use ($mesFactura) {
            $mesRec = $r->fecha ? $r->fecha->format('Y-m') : '';

            return $mesRec !== '' && $mesRec !== $mesFactura;
        });

        if ($otroMes) {
            $detalle = sprintf(
                'Cotización factura %s distinta de recepción #%s (cotización %s, fecha %s) en otro mes contable/calendario.',
                number_format($cotFactura, 4, ',', '.'),
                $otroMes->id,
                number_format((float) ($otroMes->cotizacion ?: 1), 4, ',', '.'),
                $otroMes->fecha ? $otroMes->fecha->format('d/m/Y') : '',
            );
            $this->devolverCompras->devolver(
                (int) $ordencompra->id,
                'Cotización de factura distinta a recepción de otro mes',
                $detalle,
            );
            $resultado['ok'] = false;
            $resultado['devolvio_compras'] = true;
            $resultado['errores'][] = $detalle.' El legajo fue devuelto a COMPRAS y se notificó por correo.';

            return $resultado;
        }

        foreach ($distintas as $recepcion) {
            $this->cambioCotizacionService->cambiar((int) $recepcion->id, $cotFactura);
            $resultado['cotizaciones_actualizadas'][] = (int) $recepcion->id;
            $resultado['avisos'][] = sprintf(
                'Se actualizó la cotización de la recepción #%s a %s (misma del mes de la factura).',
                $recepcion->id,
                number_format($cotFactura, 4, ',', '.'),
            );
        }

        return $resultado;
    }

    /**
     * @param  list<int|string>  $recepcionIds
     * @return ResultadoControles
     */
    public function validarComprobante(Comprobante_Proveedor $comprobante, array $recepcionIds): array
    {
        $comprobante->loadMissing([
            'ordencompras',
            'proveedores',
            'comprobante_proveedor_conceptos.concepto_ivacompras',
        ]);

        return $this->validarYAplicar(
            $comprobante->ordencompras,
            (string) ($comprobante->modo_carga ?? ''),
            $recepcionIds,
            (float) ($comprobante->cotizacion ?? 1),
            (int) ($comprobante->moneda_id ?? 1),
            $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d'),
            (string) ($comprobante->letra ?? ''),
            $comprobante->proveedores?->condicioniva_id !== null
                ? (int) $comprobante->proveedores->condicioniva_id
                : null,
            (float) ($comprobante->total ?? 0),
            (float) ($comprobante->subtotal ?? 0),
            $comprobante->comprobante_proveedor_conceptos,
            (int) $comprobante->id,
            true,
            ComprobanteProveedorLineasFacturaSupport::desdeComprobante($comprobante),
        );
    }

    /**
     * @param  iterable<int, mixed>|null  $lineasFactura
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function resolverLineasFacturaParaMatch(?iterable $lineasFactura, ?int $comprobanteId)
    {
        if ($lineasFactura !== null) {
            return ComprobanteProveedorLineasFacturaSupport::coleccionDesdeIterable($lineasFactura);
        }
        if ($comprobanteId && $comprobanteId > 0) {
            $cp = Comprobante_Proveedor::query()->find($comprobanteId);

            return ComprobanteProveedorLineasFacturaSupport::desdeComprobante($cp);
        }

        return collect();
    }

    private function tieneComDisponiblesEnLegajo(Ordencompra $ordencompra, ?int $excluirComprobanteId): bool
    {
        if ($this->recepcionesSupport->listarDisponibles((int) $ordencompra->id, $excluirComprobanteId)->isNotEmpty()) {
            return true;
        }

        return $this->recepcionesSupport->listarSinFacturarEnLegajo(
            (int) $ordencompra->proveedor_id,
            (int) $ordencompra->empresa_id,
            $ordencompra->sector_legajocompra_id ? (int) $ordencompra->sector_legajocompra_id : null,
            $excluirComprobanteId,
        )->isNotEmpty();
    }
}
