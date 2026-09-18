<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorCambioCotizacionService;
use App\Services\Stock\TransferenciaMercaderiaTitoRecalculoService;
use Illuminate\Support\Facades\Log;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorControlesConfigSupport;
use App\Support\Compras\ComprobanteProveedorCotizacionSupport;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorLineasFacturaSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorImporteYaFacturadoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorReservaComLegajoSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use Illuminate\Support\Facades\Schema;

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
        private TransferenciaMercaderiaTitoRecalculoService $titoRecalculoService,
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
        ?string $tipoDocumento = null,
        ?int $precargaIdActual = null,
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
        $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
            $ordencompra,
            $tieneComDisponibles,
            $fechaComprobanteYmd,
            $tipoDocumento
        );

        if ($politica['sin_com_por_tipo'] ?? false) {
            $this->validarImputacionContratoSinRecepcion($resultado, $politica, $ordencompra, $conceptos);

            return $resultado;
        }

        if ($politica['bloquea_sin_com']) {
            $resultado['ok'] = false;
            $resultado['errores'][] = ComprobanteProveedorFlujoOcComFacSupport::mensajeBloqueaSinCom($politica);

            return $resultado;
        }

        if ($politica['debe_asignar_com'] && $modoCarga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            $resultado['ok'] = false;
            $resultado['errores'][] = ComprobanteProveedorFlujoOcComFacSupport::mensajeDebeAsignarCom($politica);

            return $resultado;
        }

        if ($politica['contrato_vigente']
            && ! ($politica['contrato_requiere_recepcion'] ?? true)
            && $modoCarga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'El contrato vigente de esta OC no requiere recepción: use el modo «Gasto sin recepción».';

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
            $this->validarImputacionContratoSinRecepcion($resultado, $politica, $ordencompra, $conceptos);

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

        $toleranciaPct = ComprobanteProveedorToleranciaImporteSupport::porcentajeDesdeOc($ordencompra);
        $ocAnticipada = ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($ordencompra);
        $yaPorComSeleccion = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarAnticipadasSinComAPorRecepcion(
            ComprobanteProveedorImporteYaFacturadoLegajoSupport::importePorRecepcion(
                $ids->all(),
                $excluirComprobanteId,
                $monedaId,
                $cotizacionFactura,
                $fechaComprobanteYmd,
            ),
            (int) $ordencompra->id,
            $ocAnticipada,
            $excluirComprobanteId,
            $monedaId,
            $cotizacionFactura,
            $fechaComprobanteYmd,
        );
        $this->validarReservaComOtrasFacturas(
            $resultado,
            $ordencompra,
            $ids->all(),
            $recepciones,
            $importeFactura,
            $toleranciaPct,
            $yaPorComSeleccion,
            $excluirComprobanteId,
            $precargaIdActual,
            $estricto,
        );
        if (! $resultado['ok']) {
            return $resultado;
        }

        $importeComFactura = round((float) $recepciones->sum(
            fn ($r) => (float) ($r->importe_provision_com_factura ?? $r->importe_provision_com ?? 0)
        ), 2);
        $importeComMe = round((float) $recepciones->sum(
            fn ($r) => (float) ($r->importe_provision_com ?? 0)
        ), 2);

        // Provisión COM − FC en esas COM; si OC anticipada, también − anticipadas sin COM.
        // No restar FC del legajo vinculadas a otras COM (Telefónica).
        $yaFacturado = ComprobanteProveedorImporteYaFacturadoLegajoSupport::sumarComparableParaProvisionCom(
            $ids->all(),
            (int) $ordencompra->id,
            $ocAnticipada,
            $excluirComprobanteId,
            $monedaId,
            $cotizacionFactura,
            $fechaComprobanteYmd,
        );
        $importeComDisponible = ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
            $importeComFactura,
            (float) $yaFacturado['importe'],
        );

        if (ComprobanteProveedorToleranciaImporteSupport::excedeTolerancia(
            $importeFactura,
            $importeComDisponible,
            $toleranciaPct
        )) {
            $detalleYa = ((int) $yaFacturado['cantidad'] > 0)
                ? sprintf(
                    ' (COM %s − ya facturado %s)',
                    number_format($importeComFactura, 2, ',', '.'),
                    number_format((float) $yaFacturado['importe'], 2, ',', '.')
                )
                : '';
            $detalle = sprintf(
                'Importe factura (%s) %s vs provisión COM disponible %s%s%s. Tolerancia permitida: %s%% (centro de costo de la OC).',
                $importeMeta['etiqueta'],
                number_format($importeFactura, 2, ',', '.'),
                number_format($importeComDisponible, 2, ',', '.'),
                $detalleYa,
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
            $this->recalcularTraTitoSilencioso($resultado, $recepcion);
        }

        return $resultado;
    }

    /**
     * @param  array{ok: bool, avisos: list<string>, errores: list<string>}  $resultado
     */
    private function recalcularTraTitoSilencioso(array &$resultado, Recepcion_Proveedor $recepcion): void
    {
        try {
            $recalc = $this->titoRecalculoService->aplicarAutomaticoMesEnCurso((int) $recepcion->id);
        } catch (\Throwable $e) {
            Log::warning('TITO TRA: fallo recálculo automático al alinear cotización COM', [
                'recepcion_id' => (int) $recepcion->id,
                'error' => $e->getMessage(),
            ]);
            $resultado['avisos'][] = sprintf(
                'No se pudieron recalcular TRA TITO de la recepción #%s: %s',
                $recepcion->id,
                $e->getMessage()
            );

            return;
        }

        if (! ($recalc['aplicado'] ?? false) || (int) ($recalc['lineas_actualizadas'] ?? 0) <= 0) {
            return;
        }

        $resultado['avisos'][] = sprintf(
            'Se recalcularon %d TRA TITO del mes en curso de la recepción #%s.',
            (int) $recalc['lineas_actualizadas'],
            $recepcion->id
        );
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
            'tipotransaccion_compras',
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
            OrdencompraLegajoDocumentoTipoSupport::desdeComprobante($comprobante),
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

    /**
     * @param  array{ok: bool, avisos: list<string>, errores: list<string>}  $resultado
     * @param  array<string, mixed>  $politica
     * @param  iterable<object>  $conceptos
     */
    private function validarImputacionContratoSinRecepcion(
        array &$resultado,
        array $politica,
        Ordencompra $ordencompra,
        iterable $conceptos,
    ): void {
        if (! ($politica['contrato_vigente'] ?? false) || ($politica['contrato_requiere_recepcion'] ?? true)) {
            return;
        }

        $imputacion = (string) ($politica['contrato_imputacion'] ?? '');
        if ($imputacion === OrdencompraContratoRutaFacturaSupport::IMPUTACION_ARTICULOS) {
            $ordencompra->loadMissing(['ordencompra_articulos.articulos.articulo_cuentacontables']);
            if ($ordencompra->ordencompra_articulos->isEmpty()) {
                $resultado['ok'] = false;
                $resultado['errores'][] = 'El contrato imputa el neto con las cuentas de los artículos de la OC, '
                    .'pero la orden no tiene renglones. Cargue artículos o indique una cuenta en el contrato.';
            }

            return;
        }

        if ($imputacion !== OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL) {
            return;
        }

        $cuentaContratoId = OrdencompraContratoRutaFacturaSupport::cuentaManualId($ordencompra);
        if ($cuentaContratoId <= 0) {
            $resultado['ok'] = false;
            $resultado['errores'][] = 'El contrato imputa el neto con una cuenta del contrato, '
                .'pero la OC no tiene cuenta contable cargada. Edite el contrato e indique la cuenta a imputar.';

            return;
        }

        foreach ($conceptos as $linea) {
            $concepto = $linea->concepto_ivacompras ?? null;
            $tipo = (string) ($concepto->tipoconcepto ?? '');
            if (! ComprobanteProveedorConceptoIvaTipos::esNeto($tipo)) {
                continue;
            }
            $monto = round(abs((float) ($linea->monto ?? 0)), 2);
            if ($monto <= 0) {
                continue;
            }
            $cuentaId = OrdencompraContratoRutaFacturaSupport::cuentaDebeNetoManual(
                $ordencompra,
                (int) ($linea->cuentacontabledebe_id ?? 0)
            );
            if ($cuentaId <= 0) {
                $resultado['ok'] = false;
                $resultado['errores'][] = 'Falta la cuenta DEBE del neto en el concepto «'.($concepto->nombre ?? 'neto').'».';

                return;
            }
        }
    }

    /**
     * @param  array{ok: bool, avisos: list<string>, errores: list<string>}  $resultado
     * @param  list<int>  $recepcionIds
     * @param  \Illuminate\Support\Collection<int, Recepcion_Proveedor>  $recepciones
     * @param  array<int, float>  $yaPorComSeleccion
     */
    private function validarReservaComOtrasFacturas(
        array &$resultado,
        Ordencompra $ordencompra,
        array $recepcionIds,
        $recepciones,
        float $importeFactura,
        float $toleranciaPct,
        array $yaPorComSeleccion,
        ?int $excluirComprobanteId,
        ?int $precargaIdActual,
        bool $estricto,
    ): void {
        $comsParaMensaje = [];
        foreach ($recepciones as $rec) {
            $bruta = (float) (
                $rec->importe_provision_com_factura
                ?? $rec->importe_provision_com
                ?? 0
            );
            $comsParaMensaje[] = [
                'id' => (int) $rec->id,
                'numerorecepcion' => $rec->numerorecepcion ?? $rec->id,
                'provision' => ComprobanteProveedorImporteYaFacturadoLegajoSupport::provisionDisponible(
                    $bruta,
                    (float) ($yaPorComSeleccion[(int) $rec->id] ?? 0),
                ),
            ];
        }

        $mensajes = array_values(array_filter([
            ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoComQueCubrenSolas(
                $importeFactura,
                $comsParaMensaje,
                $toleranciaPct,
            ),
        ]));

        $disponibles = $this->recepcionesSupport
            ->listarDisponibles((int) $ordencompra->id, $excluirComprobanteId, false)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $pendientes = array_values(array_filter(
            OrdencompraEnvioCuentasAPagarGateSupport::documentosPendientesCarga($ordencompra),
            static fn (array $doc) => OrdencompraLegajoDocumentoTipoSupport::exigeCom((string) ($doc['tipo'] ?? 'FC'))
        ));
        $pendientesCount = count($pendientes);
        $actualEntrePendientes = $excluirComprobanteId === null || $excluirComprobanteId <= 0;
        if ($precargaIdActual && $precargaIdActual > 0) {
            $actualEntrePendientes = collect($pendientes)->contains(
                static fn (array $doc) => (int) ($doc['precarga_id'] ?? 0) === (int) $precargaIdActual
            );
        }

        $mensajes[] = ComprobanteProveedorReservaComLegajoSupport::mensajeInsuficienteParaOtrasPendientes(
            $pendientesCount,
            $actualEntrePendientes,
            $disponibles,
            $recepcionIds,
        );

        if (Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            $reservas = [];
            foreach ($pendientes as $doc) {
                $preId = (int) ($doc['precarga_id'] ?? 0);
                if ($preId <= 0) {
                    continue;
                }
                $reservas[$preId] = Precarga_Comprobante_Proveedor_Recepcion::query()
                    ->where('precarga_comprobante_proveedor_id', $preId)
                    ->pluck('recepcion_proveedor_id')
                    ->map(static fn ($id) => (int) $id)
                    ->filter(static fn (int $id) => $id > 0)
                    ->values()
                    ->all();
            }
            $mensajes[] = ComprobanteProveedorReservaComLegajoSupport::mensajeConflictoReservaBandeja(
                $recepcionIds,
                $reservas,
                $precargaIdActual,
            );
        }

        foreach (array_filter($mensajes) as $mensaje) {
            if ($estricto) {
                $resultado['ok'] = false;
                $resultado['errores'][] = $mensaje;
            } else {
                $resultado['avisos'][] = $mensaje;
            }
        }
    }

    private function tieneComDisponiblesEnLegajo(Ordencompra $ordencompra, ?int $excluirComprobanteId): bool
    {
        return $this->recepcionesSupport->listarDisponibles((int) $ordencompra->id, $excluirComprobanteId)->isNotEmpty();
    }
}
