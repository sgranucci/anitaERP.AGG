<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\Tipoasiento;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Services\Stock\RecepcionProveedorAsientoService;
use App\Support\Compras\ComprobanteProveedorAsientoDescripcionSupport;
use App\Support\Compras\ComprobanteProveedorConceptoIvaTipos;
use App\Support\Compras\ComprobanteProveedorAsientoPreviewSupport;
use App\Support\Compras\ComprobanteProveedorComContabilidadSupport;
use App\Support\Compras\ComprobanteProveedorFacturaAnticipadaSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ProveedorCuentaContableMonedaSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use RuntimeException;
use Illuminate\Http\Request;

class ComprobanteProveedorAsientoService
{
    public function __construct(
        private AsientoRepositoryInterface $asientoRepository,
        private Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private TipoasientoRepositoryInterface $tipoasientoRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
        private RecepcionProveedorAsientoService $recepcionAsientoService,
        private ComprobanteProveedorAsientoPreviewSupport $previewSupport,
    ) {}

    /**
     * Graba asiento ERP + ctamov Anita (compat). Preferir generarAsientoErp + sincronizarCtamovAnita
     * desde ContabilizarService para poder compensar ctamov si falla el sync de compra.
     */
    public function generarAsiento(Comprobante_Proveedor $comprobante): int
    {
        $resultado = $this->generarAsientoErp($comprobante);
        $this->sincronizarCtamovAnita($comprobante, $resultado['payload_anita']);

        return $resultado['asiento_id'];
    }

    /**
     * Solo MySQL: no escribe ctamov (omitir_anita). El caller sincroniza Anita al final.
     *
     * @return array{
     *     asiento_id: int,
     *     numeroasiento: string,
     *     payload_anita: array<string, mixed>
     * }
     */
    public function generarAsientoErp(Comprobante_Proveedor $comprobante): array
    {
        $preview = $this->armarPreview($comprobante);
        $payload = array_merge($preview['payload_asiento'], ['omitir_anita' => true]);

        $asiento = $this->asientoRepository->create($payload);
        if ($asiento === 'Error' || ! $asiento) {
            throw new RuntimeException('No se pudo grabar el asiento contable del comprobante de proveedor.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payload, $asientoId);

        $asientoModel = $asiento->fresh() ?? $asiento;
        $numeroAsiento = trim((string) ($asientoModel->numeroasiento ?? ''));
        if ($numeroAsiento === '') {
            throw new RuntimeException('El asiento ERP no obtuvo número para sincronizar Anita.');
        }

        $fechaAsiento = $asientoModel->fecha instanceof \DateTimeInterface
            ? $asientoModel->fecha->format('Y-m-d')
            : (string) $asientoModel->fecha;

        $payloadAnita = array_merge($preview['payload_asiento'], [
            'numeroasiento' => $numeroAsiento,
            'empresa_id' => (int) $comprobante->empresa_id,
            'tipoasiento_id' => (int) $preview['payload_asiento']['tipoasiento_id'],
            'fecha' => $fechaAsiento,
        ]);

        return [
            'asiento_id' => $asientoId,
            'numeroasiento' => $numeroAsiento,
            'payload_anita' => $payloadAnita,
        ];
    }

    /**
     * Empuja ctamov (delete+insert por numeroasiento). Antes limpia por clave del comprobante.
     *
     * @param  array<string, mixed>  $payloadAnita
     */
    public function sincronizarCtamovAnita(Comprobante_Proveedor $comprobante, array $payloadAnita): void
    {
        $this->eliminarCtamovAnitaDeComprobante($comprobante, null);

        // ERP guarda la descripción completa; ctamov Informix solo admite 30 (a-compprov.c).
        if (isset($payloadAnita['observaciones']) && is_array($payloadAnita['observaciones'])) {
            $payloadAnita['observaciones'] = array_map(
                static fn ($obs) => ComprobanteProveedorAsientoDescripcionSupport::aCtamovDesdeErp((string) $obs),
                $payloadAnita['observaciones']
            );
        }

        $this->asientoRepository->sincronizarCtamovAnita($payloadAnita);
    }

    /**
     * Compensación / preparación: borra ctamov del comprobante y, si hay número, del asiento.
     */
    public function eliminarCtamovAnitaDeComprobante(
        Comprobante_Proveedor $comprobante,
        ?string $numeroAsiento = null,
    ): void {
        $comprobante->loadMissing('tipotransaccion_compras');

        $tipo = substr((string) ($comprobante->tipotransaccion_compras?->abreviatura ?? ''), 0, 3);
        $letra = strtoupper(substr((string) ($comprobante->letra ?? ''), 0, 1));
        $sucursal = (int) ($comprobante->sucursal ?? 0);
        $nro = (int) ($comprobante->numerocomprobante ?? 0);
        $empresaId = (int) ($comprobante->empresa_id ?? 0);

        if ($empresaId > 0 && $tipo !== '' && $letra !== '' && $nro > 0) {
            $this->asientoRepository->eliminarCtamovAnitaPorComprobante(
                $empresaId,
                $tipo,
                $letra,
                $sucursal,
                $nro,
            );
        }

        $numero = trim((string) ($numeroAsiento ?? ''));
        if ($empresaId > 0 && $numero !== '') {
            $this->asientoRepository->eliminarCtamovAnitaPorNumero($empresaId, $numero);
        }
    }

    /**
     * @return array{
     *     total_debe: float,
     *     total_haber: float,
     *     payload_asiento: array<string, mixed>
     * }
     */
    public function armarPreview(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'comprobante_proveedor_conceptos.concepto_ivacompras',
            'proveedores',
            'tipotransaccion_compras',
            'ordencompras.ordencompra_articulos',
            'comprobante_proveedor_recepciones.recepcion_proveedores',
        ]);

        // La moneda de la factura manda: todo el asiento se arma en ella y con su cotización.
        $monedaFactura = $this->monedaFactura($comprobante);

        $esNotaCredito = (string) ($comprobante->tipotransaccion_compras?->signo ?? 'S') === 'R';
        $centrocostoId = (int) ($comprobante->proveedores?->centrocostocompra_id
            ?? $comprobante->ordencompras?->centrocosto_id
            ?? 1);

        $modoAsignaRecepcion = $comprobante->modo_carga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION;
        // Provisión FAR solo si la empresa genera asiento en la COM (GR valuado).
        $usaProvisionCom = $modoAsignaRecepcion
            && ComprobanteProveedorComContabilidadSupport::generaAsientoCom((int) ($comprobante->empresa_id ?? 0));
        $facturaAnticipada = ComprobanteProveedorFacturaAnticipadaSupport::aplica($comprobante);
        $tieneCapexAnticipo = $facturaAnticipada
            ? ComprobanteProveedorFacturaAnticipadaSupport::ocTieneCapex($comprobante->ordencompras)
            : false;
        $cuentaAnticipoId = $facturaAnticipada
            ? ComprobanteProveedorFacturaAnticipadaSupport::resolverCuentaAnticipoId(
                (int) $comprobante->empresa_id,
                $tieneCapexAnticipo
            )
            : 0;

        $lineasDebe = [];
        $totalNetoConceptos = 0.0;
        // Con recepción vinculada: "COM: nro codigo nombre"; si no, "codigo nombre".
        $varianteLinea = $modoAsignaRecepcion ? 'com' : 'normal';
        $descLineaErp = ComprobanteProveedorAsientoDescripcionSupport::descripcionLineaErp(
            $comprobante,
            $varianteLinea
        );
        $descDiferenciaErp = ComprobanteProveedorAsientoDescripcionSupport::descripcionLineaErp(
            $comprobante,
            'diferencia'
        );

        foreach ($comprobante->comprobante_proveedor_conceptos as $linea) {
            $concepto = $linea->concepto_ivacompras;
            $monto = round(abs((float) $linea->monto), 2);
            if ($monto <= 0) {
                continue;
            }

            $tipoConcepto = (string) ($concepto?->tipoconcepto ?? '');

            if ($usaProvisionCom && ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                $totalNetoConceptos += $monto;

                continue;
            }

            if ($modoAsignaRecepcion && ! ComprobanteProveedorConceptoIvaTipos::esImpuesto($tipoConcepto)
                && ! ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                throw new RuntimeException(
                    'Concepto IVA «'.($concepto?->nombre ?? $linea->concepto_ivacompra_id).'» con tipo «'.$tipoConcepto.'» no admite factura contra COM.'
                );
            }

            // OC anticipada sin COM: neto → anticipo (Capex / sin Capex); impuestos siguen por concepto.
            if ($facturaAnticipada && ComprobanteProveedorConceptoIvaTipos::esNeto($tipoConcepto)) {
                $lineasDebe[] = [
                    'cuentacontable_id' => $cuentaAnticipoId,
                    'importe' => $monto,
                    'centrocosto_id' => $centrocostoId,
                    'observacion' => $descLineaErp,
                ];

                continue;
            }

            $empresaId = (int) ($comprobante->empresa_id ?? 0);
            $cuentaId = (int) ($concepto?->cuentacontableDebeIdParaEmpresa($empresaId) ?? 0);
            if ($cuentaId <= 0) {
                throw new RuntimeException(
                    'Falta cuenta contable DEBE en concepto IVA «'.($concepto?->nombre ?? $linea->concepto_ivacompra_id).'»'
                    .($empresaId > 0 ? ' para la empresa del comprobante.' : '.')
                );
            }

            $lineasDebe[] = [
                'cuentacontable_id' => $cuentaId,
                'importe' => $monto,
                'centrocosto_id' => $centrocostoId,
                'observacion' => $descLineaErp,
            ];
        }

        if ($usaProvisionCom) {
            $totalProvision = $this->totalProvisionRecepcionesVinculadas($comprobante);

            // Ambos importes ya están en moneda de la factura: un cociente del orden de la
            // cotización solo puede venir de una moneda mal declarada, no de una diferencia
            // de precio. Cortar acá evita el asiento inflado por el factor de cambio.
            ComprobanteProveedorMonedaMotor::assertImportesCoherentes(
                $totalNetoConceptos,
                $totalProvision,
                $monedaFactura['moneda_id'],
                $this->cotizacionEscala($comprobante, $monedaFactura['cotizacion']),
                'la provisión de las recepciones COM',
                $monedaFactura['nombre'],
            );

            $diferenciaNeto = round($totalNetoConceptos - $totalProvision, 2);

            if ($diferenciaNeto < -0.05) {
                throw new RuntimeException(
                    'El neto de la factura ('.number_format($totalNetoConceptos, 2)
                    .') es menor que la provisión COM ('.number_format($totalProvision, 2).').'
                );
            }

            if ($totalProvision > 0) {
                $cuentaProvisionId = $this->resolverCuentaProvision((int) $comprobante->empresa_id);
                $lineasDebe[] = [
                    'cuentacontable_id' => $cuentaProvisionId,
                    'importe' => $totalProvision,
                    'centrocosto_id' => $centrocostoId,
                    'observacion' => $descLineaErp,
                ];
            }

            if ($diferenciaNeto > 0.05) {
                $lineasDiff = $this->lineasDebeDiferenciaArticulosProrrateada(
                    $comprobante,
                    $diferenciaNeto,
                    $centrocostoId
                );
                foreach ($lineasDiff as &$lineaDiff) {
                    $lineaDiff['observacion'] = $descDiferenciaErp;
                }
                unset($lineaDiff);
                $lineasDebe = array_merge($lineasDebe, $lineasDiff);
            }
        }

        if ($lineasDebe === []) {
            throw new RuntimeException('No hay conceptos con monto para contabilizar.');
        }

        // La moneda de la OC solo elige la cuenta (proveedores MN vs ME), nunca los importes:
        // esos ya están en moneda de la factura (Anita filtra_moneda_oc).
        $resMoneda = ProveedorCuentaContableMonedaSupport::resolverMonedaParaCuentaProveedor($comprobante);
        $monedaCuentaId = (int) $resMoneda['moneda_id'];
        $origenMoneda = ProveedorCuentaContableMonedaSupport::etiquetaOrigenMoneda($resMoneda['origen']);
        $cuentaProveedor = ProveedorCuentaContableMonedaSupport::cuentaProveedorId(
            $comprobante->proveedores,
            $monedaCuentaId
        );
        if ($cuentaProveedor <= 0) {
            throw new RuntimeException(
                'El proveedor no tiene cuenta contable de '
                .ProveedorCuentaContableMonedaSupport::etiquetaCuentaEsperada($monedaCuentaId)
                .' (según '.$origenMoneda.').'
            );
        }
        if (ProveedorCuentaContableMonedaSupport::esMonedaExtranjera($monedaCuentaId)
            && (int) ($comprobante->proveedores?->cuentacontableme_id ?? 0) <= 0) {
            throw new RuntimeException(
                'El proveedor no tiene cuenta contable de proveedores moneda extranjera (m/e) configurada ('.$origenMoneda.').'
            );
        }

        $totalDebe = round(array_sum(array_column($lineasDebe, 'importe')), 2);
        $totalComprobante = round(abs((float) $comprobante->total), 2);
        if ($totalComprobante <= 0) {
            throw new RuntimeException('El total del comprobante debe ser mayor a cero para contabilizar.');
        }

        $diferencia = round($totalDebe - $totalComprobante, 2);
        if (abs($diferencia) > 0.05) {
            throw new RuntimeException(
                'Los conceptos ('.number_format($totalDebe, 2).') no coinciden con el total del comprobante ('.number_format($totalComprobante, 2).').'
            );
        }

        $lineasHaber = [[
            'cuentacontable_id' => $cuentaProveedor,
            'importe' => $totalComprobante,
            'centrocosto_id' => $centrocostoId,
            'observacion' => $descLineaErp,
        ]];

        if ($esNotaCredito) {
            [$lineasDebe, $lineasHaber] = [$lineasHaber, $lineasDebe];
        }

        $tipoAsiento = $this->resolverTipoAsiento();
        $tipoAbrev = (string) ($comprobante->tipotransaccion_compras?->abreviatura ?? 'FAC');
        $numeroOc = (int) ($comprobante->ordencompras?->numeroordencompra ?? 0);

        $payloadAsiento = [
            'empresa_id' => $comprobante->empresa_id,
            'tipoasiento_id' => $tipoAsiento->id,
            'fecha' => ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante),
            'comprobante_proveedor_id' => $comprobante->id,
            'ordencompra_id' => $comprobante->ordencompra_id,
            'observacion' => ComprobanteProveedorAsientoDescripcionSupport::descripcionAsientoErp($comprobante),
            'tipo' => substr($tipoAbrev, 0, 3),
            'letra' => $comprobante->letra,
            'sucursal' => $comprobante->sucursal,
            'nro' => $comprobante->numerocomprobante,
            'ctav_o_compra' => $numeroOc,
            'sistema_ctav' => 'C',
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'cuentacontable_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        foreach ($lineasDebe as $linea) {
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $monedaFactura['moneda_id'];
            $payloadAsiento['centrocosto_ids'][] = $linea['centrocosto_id'] ?? $centrocostoId;
            $payloadAsiento['debes'][] = $linea['importe'];
            $payloadAsiento['haberes'][] = 0;
            $payloadAsiento['cotizaciones'][] = $monedaFactura['cotizacion'];
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        foreach ($lineasHaber as $linea) {
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $monedaFactura['moneda_id'];
            $payloadAsiento['centrocosto_ids'][] = $linea['centrocosto_id'] ?? $centrocostoId;
            $payloadAsiento['debes'][] = 0;
            $payloadAsiento['haberes'][] = $linea['importe'];
            $payloadAsiento['cotizaciones'][] = $monedaFactura['cotizacion'];
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        $totalHaber = round(array_sum(array_column($lineasHaber, 'importe')), 2);

        return [
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'payload_asiento' => $payloadAsiento,
        ];
    }

    /**
     * Vista previa para solapa del formulario (patrón recepción proveedor).
     *
     * @return array{
     *   activo: bool,
     *   error?: string|null,
     *   es_preview?: bool,
     *   total_comprobante?: float,
     *   total_debe?: float,
     *   total_haber?: float,
     *   asiento_id?: int,
     *   numeroasiento?: string,
     *   fecha?: string,
     *   tipo_asiento?: string,
     *   lineas?: list<array<string, mixed>>
     * }
     */
    public function previewParaVista(Comprobante_Proveedor $comprobante): array
    {
        if ((int) ($comprobante->asiento_id ?? 0) > 0) {
            return $this->previewAsientoGrabado($comprobante);
        }

        return $this->previewBorrador($comprobante);
    }

    /**
     * Vista previa desde el formulario (sin guardar).
     *
     * @return array{
     *   activo: bool,
     *   error?: string|null,
     *   es_preview?: bool,
     *   avisos?: list<array<string, mixed>>,
     *   total_comprobante?: float,
     *   total_debe?: float,
     *   total_haber?: float,
     *   lineas?: list<array<string, mixed>>
     * }
     */
    public function previewDesdeFormulario(Request $request, ?Comprobante_Proveedor $existente = null): array
    {
        if ($existente && $existente->estado === ComprobanteProveedorEstados::CONTABILIZADO) {
            return array_merge(
                $this->previewAsientoGrabado($existente),
                ['avisos' => []],
            );
        }

        try {
            $comprobante = $this->previewSupport->construirDesdeRequest($request, $existente);
        } catch (\RuntimeException $e) {
            return [
                'activo' => true,
                'error' => $e->getMessage(),
                'es_preview' => true,
                'avisos' => [],
                'lineas' => [],
            ];
        }

        $preview = $this->previewBorrador($comprobante);
        $preview['avisos'] = $this->previewSupport->avisosFaltantes($comprobante);

        return $preview;
    }

    /**
     * @return array<string, mixed>
     */
    private function previewAsientoGrabado(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'asientos.tipoasientos',
            'asientos.asiento_movimientos.cuentacontables',
            'asientos.asiento_movimientos.centrocostos',
        ]);

        $asiento = $comprobante->asientos;
        if (! $asiento) {
            return [
                'activo' => true,
                'error' => 'El comprobante indica asiento id '.(int) $comprobante->asiento_id.' pero no se encontró en el ERP.',
                'es_preview' => false,
                'lineas' => [],
            ];
        }

        $lineas = [];
        $totales = ['debe' => 0.0, 'haber' => 0.0];
        foreach ($asiento->asiento_movimientos as $mov) {
            $monto = (float) ($mov->monto ?? 0);
            $debe = $monto > 0 ? $monto : null;
            $haber = $monto < 0 ? abs($monto) : null;
            if ($debe !== null) {
                $totales['debe'] += $debe;
            }
            if ($haber !== null) {
                $totales['haber'] += $haber;
            }
            $lineas[] = [
                'cuenta_codigo' => optional($mov->cuentacontables)->codigo ?? '—',
                'cuenta_nombre' => optional($mov->cuentacontables)->nombre ?? '',
                'centrocosto_codigo' => optional($mov->centrocostos)->codigo ?? '',
                'debe' => $debe,
                'haber' => $haber,
                'observacion' => (string) ($mov->observacion ?? ''),
            ];
        }

        return [
                'activo' => true,
                'error' => null,
                'es_preview' => false,
                'asiento_id' => (int) $asiento->id,
                'numeroasiento' => (string) $asiento->numeroasiento,
                'fecha' => optional($asiento->fecha)->format('d/m/Y'),
                'tipo_asiento' => optional($asiento->tipoasientos)->nombre ?? '',
                'total_debe' => round($totales['debe'], 2),
                'total_haber' => round($totales['haber'], 2),
                'lineas' => $lineas,
            ];
    }

    /**
     * @return array<string, mixed>
     */
    private function previewBorrador(Comprobante_Proveedor $comprobante): array
    {
        try {
            $preview = $this->armarPreview($comprobante);

            return [
                'activo' => true,
                'error' => null,
                'es_preview' => true,
                'total_comprobante' => round(abs((float) $comprobante->total), 2),
                'total_debe' => $preview['total_debe'],
                'total_haber' => $preview['total_haber'],
                'lineas' => $this->formatearLineasPayload($preview['payload_asiento']),
            ];
        } catch (\Throwable $e) {
            return [
                'activo' => true,
                'error' => $e->getMessage(),
                'es_preview' => true,
                'total_comprobante' => round(abs((float) $comprobante->total), 2),
                'lineas' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function formatearLineasPayload(array $payload): array
    {
        $lineas = [];
        $cuentas = $payload['cuentacontable_ids'] ?? [];
        $debes = $payload['debes'] ?? [];
        $haberes = $payload['haberes'] ?? [];
        $centros = $payload['centrocosto_ids'] ?? [];
        $observaciones = $payload['observaciones'] ?? [];

        foreach ($cuentas as $i => $cuentaId) {
            $cuenta = $this->cuentacontableRepository->find((int) $cuentaId);
            $ccId = (int) ($centros[$i] ?? 0);
            $ccCodigo = '';
            if ($ccId > 0) {
                $cc = $this->centrocostoRepository->find($ccId);
                $ccCodigo = (string) ($cc->codigo ?? '');
            }

            $debe = (float) ($debes[$i] ?? 0);
            $haber = (float) ($haberes[$i] ?? 0);

            $lineas[] = [
                'cuenta_codigo' => $cuenta->codigo ?? '—',
                'cuenta_nombre' => $cuenta->nombre ?? '',
                'centrocosto_codigo' => $ccCodigo,
                'debe' => $debe > 0 ? $debe : null,
                'haber' => $haber > 0 ? $haber : null,
                'observacion' => (string) ($observaciones[$i] ?? ''),
            ];
        }

        return $lineas;
    }

    private function resolverTipoAsiento(): Tipoasiento
    {
        $tipo = $this->tipoasientoRepository->findPorAbreviatura(
            config('comprobante_proveedor.tipoasiento_abreviatura', 'COM')
        );

        if ($tipo instanceof Tipoasiento) {
            return $tipo;
        }

        return $this->tipoasientoRepository->create([
            'nombre' => 'Compras',
            'abreviatura' => 'COM',
        ]);
    }

    private function resolverCuentaProvision(int $empresaId): int
    {
        return CuentaAutomaticaResolver::resolverIdObligatorio(
            $empresaId,
            CuentaAutomaticaClaves::RECEPCION_PROVISION_FACTURAS,
            'Falta configurar la cuenta de provisión de facturas a recibir para la empresa.',
        );
    }

    /**
     * Importe provisionado por cada COM vinculada, en moneda del comprobante (asiento factura).
     * Cada COM se convierte con su propia cotización: es el valor con el que se provisionó.
     */
    private function totalProvisionRecepcionesVinculadas(Comprobante_Proveedor $comprobante): float
    {
        $vinculos = $comprobante->comprobante_proveedor_recepciones;
        if ($vinculos->isEmpty()) {
            throw new RuntimeException(
                'Modo factura contra recepción: seleccione al menos una recepción COM vinculada.'
            );
        }

        $factura = $this->monedaFactura($comprobante);

        $total = 0.0;
        foreach ($vinculos as $vinculo) {
            $recepcion = $vinculo->recepcion_proveedores;
            if (! $recepcion) {
                throw new RuntimeException('Recepción vinculada inexistente.');
            }

            if (! ComprobanteProveedorComContabilidadSupport::recepcionTieneProvisionContable($recepcion)) {
                continue;
            }

            $preview = $this->recepcionAsientoService->previewAsientoContable($recepcion);
            $importeEnMonedaRecepcion = (float) ($preview['total_debe'] ?? 0);
            $total += ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFactura(
                $importeEnMonedaRecepcion,
                (int) ($recepcion->moneda_id ?: 1),
                (float) ($recepcion->cotizacion ?: 0),
                $factura['moneda_id'],
                $factura['cotizacion'],
                $this->fechaDocumento($recepcion->fecha ?? null),
                $factura['fecha'],
            );
        }

        return round($total, 2);
    }

    /**
     * Moneda de la factura: la que manda en todo el asiento.
     *
     * @return array{moneda_id: int, cotizacion: float, fecha: string, nombre: string}
     */
    private function monedaFactura(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing('monedas');

        $monedaId = ComprobanteProveedorMonedaMotor::normalizarMonedaId($comprobante->moneda_id);
        $fecha = $this->fechaDocumento($comprobante->fechacomprobante ?? null);

        return [
            'moneda_id' => $monedaId,
            'cotizacion' => ComprobanteProveedorMonedaMotor::cotizacionValida(
                $monedaId,
                $comprobante->cotizacion,
                $fecha,
                'la factura del proveedor',
            ),
            'fecha' => $fecha,
            'nombre' => (string) ($comprobante->monedas?->nombre ?? ''),
        ];
    }

    private function fechaDocumento(mixed $fecha): string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('Y-m-d');
        }

        $texto = trim((string) $fecha);

        return $texto !== '' ? substr($texto, 0, 10) : now()->format('Y-m-d');
    }

    /**
     * Escala de cotización en juego entre la factura y sus COM: sirve para detectar
     * importes cargados en otra moneda (ver ComprobanteProveedorMonedaMotor).
     */
    private function cotizacionEscala(Comprobante_Proveedor $comprobante, float $cotizacionFactura): float
    {
        $escala = $cotizacionFactura;

        foreach ($comprobante->comprobante_proveedor_recepciones as $vinculo) {
            $recepcion = $vinculo->recepcion_proveedores;
            if (! $recepcion) {
                continue;
            }

            $escala = max($escala, (float) ($recepcion->cotizacion ?: 0));
        }

        return $escala;
    }

    /**
     * Distribuye el excedente de neto (factura &gt; COM) en las cuentas de compra de artículos, prorrateado.
     *
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id:int, observacion:string}>
     */
    private function lineasDebeDiferenciaArticulosProrrateada(
        Comprobante_Proveedor $comprobante,
        float $importeADistribuir,
        int $centrocostoDefault,
    ): array {
        $importeADistribuir = round($importeADistribuir, 2);
        if ($importeADistribuir <= 0) {
            return [];
        }

        /** @var array<string, array{cuentacontable_id:int, centrocosto_id:int, importe:float}> $agrupado */
        $agrupado = [];
        $factura = $this->monedaFactura($comprobante);

        foreach ($comprobante->comprobante_proveedor_recepciones as $vinculo) {
            $recepcion = $vinculo->recepcion_proveedores;
            if (! $recepcion) {
                continue;
            }

            $monedaRecepcionId = (int) ($recepcion->moneda_id ?: 1);
            $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 0);
            $fechaRecepcion = $this->fechaDocumento($recepcion->fecha ?? null);

            foreach ($this->recepcionAsientoService->lineasDebeArticulosAgrupadas($recepcion) as $linea) {
                $ccId = (int) ($linea['centrocosto_id'] ?? 0);
                $clave = (int) $linea['cuentacontable_id'].'|'.$ccId;

                if (! isset($agrupado[$clave])) {
                    $agrupado[$clave] = [
                        'cuentacontable_id' => (int) $linea['cuentacontable_id'],
                        'centrocosto_id' => $ccId,
                        'importe' => 0.0,
                    ];
                }

                // Base de prorrateo en moneda factura (ME×cot si COM en dólares y factura en pesos).
                $agrupado[$clave]['importe'] += ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFactura(
                    (float) $linea['importe'],
                    $monedaRecepcionId,
                    $cotizacionRecepcion,
                    $factura['moneda_id'],
                    $factura['cotizacion'],
                    $fechaRecepcion,
                    $factura['fecha'],
                );
            }
        }

        if ($agrupado === []) {
            throw new RuntimeException(
                'No se pudieron obtener cuentas de artículos de las recepciones COM para prorratear la diferencia.'
            );
        }

        $totalBase = round(array_sum(array_column($agrupado, 'importe')), 2);
        if ($totalBase <= 0) {
            throw new RuntimeException(
                'Las recepciones COM vinculadas no tienen importe base para prorratear la diferencia de la factura.'
            );
        }

        $lineas = [];
        $asignado = 0.0;
        $items = array_values($agrupado);
        $ultimoIndice = count($items) - 1;

        foreach ($items as $i => $row) {
            if ($i === $ultimoIndice) {
                $importe = round($importeADistribuir - $asignado, 2);
            } else {
                $importe = round($importeADistribuir * ($row['importe'] / $totalBase), 2);
                $asignado += $importe;
            }

            if ($importe <= 0) {
                continue;
            }

            $lineas[] = [
                'cuentacontable_id' => $row['cuentacontable_id'],
                'importe' => $importe,
                'centrocosto_id' => $row['centrocosto_id'] > 0 ? $row['centrocosto_id'] : $centrocostoDefault,
                'observacion' => 'Diferencia factura vs COM (prorrateo artículos)',
            ];
        }

        return $lineas;
    }
}
