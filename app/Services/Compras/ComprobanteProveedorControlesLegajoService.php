<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorCambioCotizacionService;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
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
 *     devolvio_compras: bool
 * }
 */
class ComprobanteProveedorControlesLegajoService
{
    private const EPS_COTIZACION = 0.0000005;

    public function __construct(
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private RecepcionProveedorCambioCotizacionService $cambioCotizacionService,
        private OrdencompraDevolverAComprasNotificacionService $devolverCompras,
    ) {}

    /**
     * @param  list<int|string>  $recepcionIds
     * @param  iterable<object>  $conceptos
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
    ): array {
        $resultado = [
            'ok' => true,
            'avisos' => [],
            'errores' => [],
            'cotizaciones_actualizadas' => [],
            'devolvio_compras' => false,
        ];

        if (! $ordencompra) {
            return $resultado;
        }

        $disponibles = $this->recepcionesSupport->listarDisponibles((int) $ordencompra->id, $excluirComprobanteId);
        $tieneComDisponibles = $disponibles->isNotEmpty();

        if ($tieneComDisponibles && $modoCarga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'El legajo tiene recepciones COM disponibles: el modo de carga debe ser «Factura contra recepción (COM)».';

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

        if ($ids->isEmpty()) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'Debe seleccionar al menos una recepción COM para asociar a la factura del legajo.';

            return $resultado;
        }

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

        $recepciones = $this->recepcionesSupport->enriquecerConImporteProvision($recepciones);
        $importeCom = round((float) $recepciones->sum(fn ($r) => (float) ($r->importe_provision_com ?? 0)), 2);

        $toleranciaPct = ComprobanteProveedorToleranciaImporteSupport::porcentajeParaOc(
            (int) $ordencompra->empresa_id,
            (int) ($ordencompra->centrocosto_id ?? 0) ?: null,
        );

        if (ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia($importeFactura, $importeCom, $toleranciaPct)) {
            $detalle = sprintf(
                'Importe factura (%s) %s vs provisión COM %s. Tolerancia permitida: %s%% (centro de costo de la OC).',
                $importeMeta['etiqueta'],
                number_format($importeFactura, 2, ',', '.'),
                number_format($importeCom, 2, ',', '.'),
                number_format($toleranciaPct, 2, ',', '.'),
            );
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
     * Variante post-persistencia usando el modelo ya guardado + IDs de recepción.
     *
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
        );
    }
}
