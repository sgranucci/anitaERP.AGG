<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Contable\Tipoasiento;
use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;

class RecepcionProveedorAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
        private readonly TipoasientoRepositoryInterface $tipoasientoRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function debeGenerarAsiento(int $empresaId): bool
    {
        if (! config('recepcion_proveedor.contabilidad_activa')) {
            return false;
        }

        $cfg = Configuracion_RecepcionProveedor::query()->where('empresa_id', $empresaId)->first();

        return $cfg ? (bool) $cfg->activa_contabilidad : config('recepcion_proveedor.contabilidad_activa');
    }

    /**
     * Valida el cuadre recepción ↔ contabilidad sin grabar (falla rápido antes de stock/asiento).
     */
    public function assertCuadreRecepcion(Recepcion_Proveedor $recepcion): void
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return;
        }

        $preview = $this->armarPreviewAsiento($recepcion);
        RecepcionProveedorCuadreContableSupport::assertPreview($preview);
    }

    /**
     * Genera asiento contable coherente con el valor de la recepción (sin IVA).
     */
    public function generarAsiento(Recepcion_Proveedor $recepcion): ?int
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return null;
        }

        $preview = $this->armarPreviewAsiento($recepcion);
        RecepcionProveedorCuadreContableSupport::assertPreview($preview);

        $payloadAsiento = $preview['payload_asiento'];
        $asiento = $this->asientoRepository->create($payloadAsiento);
        if ($asiento === 'Error' || ! $asiento) {
            throw new \RuntimeException('Error al grabar asiento contable en Anita.');
        }

        $asientoId = (int) $asiento->id;
        $this->asientoMovimientoRepository->create($payloadAsiento, $asientoId);

        RecepcionProveedorCuadreContableSupport::assertPersistido(
            $asientoId,
            $preview,
            $this->asientoMovimientoRepository
        );

        return $asientoId;
    }

    public function anularAsiento(Recepcion_Proveedor $recepcion): void
    {
        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return;
        }

        $this->asientoRepository->delete($asientoId);
    }

    /**
     * Preview contable (cantidad × precio de línea; sin descuento de pie de OC).
     *
     * @return array{
     *   total_recepcion: float,
     *   total_debe: float,
     *   total_haber: float,
     *   numerorecepcion: string,
     *   payload_asiento: array<string, mixed>
     * }
     */
    public function previewAsientoContable(Recepcion_Proveedor $recepcion): array
    {
        return $this->armarPreviewAsiento($recepcion);
    }

    /**
     * Regraba movimientos del asiento existente para cuadrar con Σ(cant × precio) de la recepción.
     */
    public function recuadrarAsientoExistente(Recepcion_Proveedor $recepcion): void
    {
        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('La recepción no tiene asiento contable asociado.');
        }

        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return;
        }

        $preview = $this->armarPreviewAsiento($recepcion);
        RecepcionProveedorCuadreContableSupport::assertPreview($preview);

        $this->asientoMovimientoRepository->update($preview['payload_asiento'], $asientoId);

        RecepcionProveedorCuadreContableSupport::assertPersistido(
            $asientoId,
            $preview,
            $this->asientoMovimientoRepository
        );

        $this->sincronizarCtamovAnitaRecepcion($recepcion, $preview);
    }

    /**
     * Empuja a Anita contab.ctamov el asiento de la recepción (delete + insert por numeroasiento).
     *
     * @param  array{payload_asiento: array<string, mixed>}|null  $preview
     */
    public function sincronizarCtamovAnitaRecepcion(Recepcion_Proveedor $recepcion, ?array $preview = null): void
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return;
        }

        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            throw new \RuntimeException('La recepción no tiene asiento contable asociado.');
        }

        $recepcion->loadMissing('asientos');
        $asiento = $recepcion->asientos;
        if (! $asiento) {
            throw new \RuntimeException('No se encontró el asiento id '.$asientoId.' de la recepción.');
        }

        $preview ??= $this->armarPreviewAsiento($recepcion);
        $payload = $preview['payload_asiento'];

        $fechaAsiento = $asiento->fecha;
        if ($fechaAsiento instanceof \DateTimeInterface) {
            $fechaAsiento = $fechaAsiento->format('Y-m-d');
        } else {
            $fechaAsiento = \Carbon\Carbon::parse((string) $fechaAsiento)->format('Y-m-d');
        }

        $dataAnita = array_merge($payload, [
            'numeroasiento' => $asiento->numeroasiento,
            'empresa_id' => (int) $asiento->empresa_id,
            'tipoasiento_id' => (int) $asiento->tipoasiento_id,
            'fecha' => $fechaAsiento,
        ]);

        $this->asientoRepository->sincronizarCtamovAnita($dataAnita);
    }

    /**
     * Vista previa del asiento (borrador) o datos del asiento ya grabado.
     *
     * @return array{
     *   activo: bool,
     *   error?: string|null,
     *   es_preview?: bool,
     *   total_recepcion?: float,
     *   total_debe?: float,
     *   total_haber?: float,
     *   lineas?: list<array<string, mixed>>
     * }
     */
    public function previewParaVista(Recepcion_Proveedor $recepcion): array
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return ['activo' => false];
        }

        if ((int) ($recepcion->asiento_id ?? 0) > 0) {
            $recepcion->loadMissing([
                'asientos.tipoasientos',
                'asientos.asiento_movimientos.cuentacontables',
                'asientos.asiento_movimientos.centrocostos',
                'asientos.asiento_movimientos.monedas',
            ]);

            $asiento = $recepcion->asientos;
            if (! $asiento) {
                return [
                    'activo' => true,
                    'error' => 'La recepción indica asiento id '.(int) $recepcion->asiento_id.' pero no se encontró en el ERP.',
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

        try {
            $preview = $this->armarPreviewAsiento($recepcion);

            return [
                'activo' => true,
                'error' => null,
                'es_preview' => true,
                'total_recepcion' => $preview['total_recepcion'],
                'total_debe' => $preview['total_debe'],
                'total_haber' => $preview['total_haber'],
                'lineas' => $this->formatearLineasPayload($preview['payload_asiento']),
            ];
        } catch (\Throwable $e) {
            return [
                'activo' => true,
                'error' => $e->getMessage(),
                'es_preview' => true,
                'lineas' => [],
            ];
        }
    }

    /**
     * @return array{
     *   total_recepcion: float,
     *   total_debe: float,
     *   total_haber: float,
     *   numerorecepcion: string,
     *   payload_asiento: array<string, mixed>
     * }
     */
    private function armarPreviewAsiento(Recepcion_Proveedor $recepcion): array
    {
        $cfg = Configuracion_RecepcionProveedor::query()->where('empresa_id', $recepcion->empresa_id)->first();
        if (! $cfg || ! $cfg->cuentacontable_provision_facturas_id) {
            throw new \RuntimeException('Falta configurar cuenta de provisión de facturas a recibir para la empresa.');
        }

        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos', 'ordencompras']);
        $tipoAsiento = $this->resolverTipoAsientoCompras();

        $claveAnita = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $oc = $recepcion->ordencompras;
        $numeroOrdenCompra = (int) ($oc->numeroordencompra ?? 0);
        $esDevolucion = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION;
        $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 1);

        $lineasDebe = $this->armarLineasDebeArticulos($recepcion, $cotizacionRecepcion);
        $totalDebe = round(array_sum(array_column($lineasDebe, 'importe')), 2);
        $totalRecepcion = $this->totalRecepcionContable($recepcion, $cotizacionRecepcion);

        $lineasHaber = [];
        $esAnticipada = $oc && strtoupper((string) $oc->tratamiento) === 'ANTICIPADA';

        if ($esAnticipada) {
            $lineasHaber = $this->armarHaberAnticipada($oc, $cfg, $totalDebe, $cotizacionRecepcion);
        } else {
            $lineasHaber[] = [
                'cuentacontable_id' => (int) $cfg->cuentacontable_provision_facturas_id,
                'importe' => $totalDebe,
            ];
        }

        $totalHaber = round(array_sum(array_column($lineasHaber, 'importe')), 2);
        $diferencia = round($totalDebe - $totalHaber, 2);

        if (abs($diferencia) >= 0.01 && $esAnticipada) {
            $lineasHaber[] = [
                'cuentacontable_id' => (int) $cfg->cuentacontable_provision_facturas_id,
                'importe' => max(0, $diferencia),
            ];
            $totalHaber = round(array_sum(array_column($lineasHaber, 'importe')), 2);
        }

        $ccDefault = (int) ($recepcion->recepcion_proveedor_articulos->first()->centrocosto_id ?? 0);
        $payloadAsiento = [
            'empresa_id' => $recepcion->empresa_id,
            'tipoasiento_id' => $tipoAsiento->id,
            'fecha' => $recepcion->fecha->format('Y-m-d'),
            'recepcionproveedor_id' => $recepcion->id,
            'ordencompra_id' => $recepcion->ordencompra_id,
            'observacion' => 'Recepción proveedor '.$recepcion->numerorecepcion,
            'tipo' => $claveAnita['tipo'],
            'letra' => $claveAnita['letra'],
            'sucursal' => $claveAnita['sucursal'],
            'nro' => $claveAnita['nro'],
            'ctav_o_compra' => $numeroOrdenCompra,
            'sistema_ctav' => 'C',
            'moneda_ids' => [],
            'centrocosto_ids' => [],
            'cuentacontable_ids' => [],
            'debes' => [],
            'haberes' => [],
            'cotizaciones' => [],
            'observaciones' => [],
        ];

        $lineasDebeAsiento = $lineasDebe;
        $lineasHaberAsiento = $lineasHaber;
        if ($esDevolucion) {
            [$lineasDebeAsiento, $lineasHaberAsiento] = [$lineasHaberAsiento, $lineasDebeAsiento];
        }

        foreach ($lineasDebeAsiento as $linea) {
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $linea['centrocosto_id'] ?? $ccDefault;
            $payloadAsiento['debes'][] = $linea['importe'];
            $payloadAsiento['haberes'][] = 0;
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        foreach ($lineasHaberAsiento as $linea) {
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $ccDefault;
            $payloadAsiento['debes'][] = 0;
            $payloadAsiento['haberes'][] = $linea['importe'];
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        return [
            'total_recepcion' => $totalRecepcion,
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'numerorecepcion' => (string) $recepcion->numerorecepcion,
            'payload_asiento' => $payloadAsiento,
        ];
    }

    private function resolverTipoAsientoCompras(): Tipoasiento
    {
        $tipo = $this->tipoasientoRepository->findPorAbreviatura('COM')
            ?? $this->tipoasientoRepository->findPorAbreviatura('STK');

        if ($tipo instanceof Tipoasiento) {
            return $tipo;
        }

        return $this->tipoasientoRepository->create([
            'nombre' => 'Compras',
            'abreviatura' => 'COM',
        ]);
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

    private function totalRecepcionContable(
        Recepcion_Proveedor $recepcion,
        float $cotizacionRecepcion,
    ): float {
        $total = 0.0;

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $cotLinea = (float) ($linea->cotizacion ?: 1);
            $importe = RecepcionProveedorConversionSupport::importeLinea(
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
            );
            $total += RecepcionProveedorConversionSupport::convertirMoneda($importe, $cotLinea, $cotizacionRecepcion);
        }

        return round($total, 2);
    }

    /** @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}> */
    private function armarLineasDebeArticulos(
        Recepcion_Proveedor $recepcion,
        float $cotizacionRecepcion,
    ): array {
        $agrupado = [];

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articulo = $linea->articulos;
            $ctaId = (int) ($articulo->cuentacontablecompra_id ?? 0);
            if ($ctaId <= 0) {
                throw new \RuntimeException('Artículo '.($articulo->sku ?? $linea->articulo_id).' sin cuenta contable de compra.');
            }

            $cotLinea = (float) ($linea->cotizacion ?: 1);
            $importe = RecepcionProveedorConversionSupport::importeLinea(
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
            );
            $importe = RecepcionProveedorConversionSupport::convertirMoneda($importe, $cotLinea, $cotizacionRecepcion);

            $clave = $ctaId.'|'.((int) ($linea->centrocosto_id ?? 0));
            if (! isset($agrupado[$clave])) {
                $agrupado[$clave] = [
                    'cuentacontable_id' => $ctaId,
                    'centrocosto_id' => (int) ($linea->centrocosto_id ?? 0),
                    'importe' => 0,
                ];
            }
            $agrupado[$clave]['importe'] += $importe;
        }

        foreach ($agrupado as &$row) {
            $row['importe'] = round($row['importe'], 2);
        }

        return array_values($agrupado);
    }

    /**
     * @return list<array{cuentacontable_id:int, importe:float, observacion?:string}>
     */
    private function armarHaberAnticipada(
        Ordencompra $oc,
        Configuracion_RecepcionProveedor $cfg,
        float $totalDebe,
        float $cotizacionRecepcion
    ): array {
        $cuentasAnticipo = array_filter([
            (int) ($cfg->cuentacontable_factura_anticipada_id ?? 0),
            (int) ($cfg->cuentacontable_anticipo_bienes_uso_id ?? 0),
            (int) ($cfg->cuentacontable_proveedores_intangible_id ?? 0),
        ]);

        if ($cuentasAnticipo === []) {
            throw new \RuntimeException('OC anticipada: configure cuentas de anticipo en setup de recepción.');
        }

        $codigosCuenta = [];
        foreach ($cuentasAnticipo as $ctaId) {
            $cuenta = $this->cuentacontableRepository->find($ctaId);
            if ($cuenta) {
                $codigosCuenta[$ctaId] = trim((string) $cuenta->codigo);
            }
        }

        $saldos = $this->mayorizarAnticiposDesdeAnita((int) $oc->numeroordencompra, array_values($codigosCuenta), $cotizacionRecepcion);
        $lineasHaber = [];
        $restante = $totalDebe;

        foreach ($codigosCuenta as $ctaId => $codigo) {
            $saldo = (float) ($saldos[$codigo] ?? 0);
            if ($saldo <= 0) {
                continue;
            }
            $aplicar = min($saldo, $restante);
            if ($aplicar <= 0) {
                continue;
            }
            $lineasHaber[] = [
                'cuentacontable_id' => $ctaId,
                'importe' => round($aplicar, 2),
                'observacion' => 'Cierre anticipo cuenta '.$codigo,
            ];
            $restante -= $aplicar;
        }

        return $lineasHaber;
    }

    /**
     * Mayoriza subdiario contable: facturas suman, recepciones restan.
     *
     * @param  list<string>  $codigosCuenta
     * @return array<string, float> codigo => saldo en moneda recepción
     */
    private function mayorizarAnticiposDesdeAnita(int $numeroOc, array $codigosCuenta, float $cotizacionRecepcion): array
    {
        $saldos = array_fill_keys($codigosCuenta, 0.0);
        $facturas = $this->buscarComprobantesOc($numeroOc);

        foreach ($facturas as $factura) {
            $lineasSubdiario = $this->leerSubdiarioFactura($factura);
            foreach ($lineasSubdiario as $linea) {
                $codigoCta = trim((string) ($linea->subd_cuenta ?? ''));
                if (! in_array($codigoCta, $codigosCuenta, true)) {
                    continue;
                }

                $importe = (float) ($linea->subd_importe ?? 0);
                $tipoMov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? 'D')));
                $cotSubd = (float) ($linea->subd_cotizacion ?? 1);
                $importeConv = RecepcionProveedorConversionSupport::convertirMoneda(abs($importe), $cotSubd, $cotizacionRecepcion);

                if ($tipoMov === 'D') {
                    $saldos[$codigoCta] += $importeConv;
                } else {
                    $saldos[$codigoCta] -= $importeConv;
                }
            }
        }

        foreach ($saldos as $cod => $val) {
            $saldos[$cod] = max(0, round($val, 2));
        }

        return $saldos;
    }

    /** @return list<object> */
    private function buscarComprobantesOc(int $numeroOc): array
    {
        $api = new ApiAnita;
        $cfg = config('recepcion_proveedor.anita');
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'sistema' => $cfg['sistema_compras'],
            'tabla' => $cfg['tablas']['aplicacion_oc'],
            'campos' => 'aplp_proveedor, aplp_tipo, aplp_letra, aplp_sucursal, aplp_nro, aplp_ref_nro',
            'whereArmado' => " WHERE
                aplp_ref_tipo='{$cfg['oc_tipo']}' and
                aplp_ref_letra='{$cfg['oc_letra']}' and
                aplp_ref_sucursal={$cfg['oc_sucursal']} and
                aplp_ref_nro={$numeroOc} and
                aplp_tipo<>'COM'",
        ]));

        return is_array($rows) ? $rows : [];
    }

    /** @return list<object> */
    private function leerSubdiarioFactura(object $factura): array
    {
        $api = new ApiAnita;
        $tipo = trim((string) ($factura->aplp_tipo ?? ''));
        $letra = trim((string) ($factura->aplp_letra ?? ''));
        $sucursal = (int) ($factura->aplp_sucursal ?? 0);
        $nro = (int) ($factura->aplp_nro ?? 0);

        if ($tipo === '' || $nro <= 0) {
            return [];
        }

        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'sistema' => config('recepcion_proveedor.anita.sistema_contab'),
            'tabla' => config('recepcion_proveedor.anita.tablas.subdiario').', '.config('recepcion_proveedor.anita.tablas.cuenta'),
            'campos' => 'subd_tipo, subd_letra, subd_sucursal, subd_nro, subd_cuenta, subd_importe, subd_tipo_mov, subd_cotizacion',
            'whereArmado' => " WHERE
                subd_tipo='{$tipo}' and
                subd_letra='{$letra}' and
                subd_sucursal={$sucursal} and
                subd_nro={$nro}",
        ]));

        return is_array($rows) ? $rows : [];
    }
}
