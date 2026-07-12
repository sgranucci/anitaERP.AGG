<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Contable\Tipoasiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Contable\Asiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\RecepcionProveedorAnitaClaveSupport;
use App\Support\Stock\RecepcionProveedorAsientoDescripcionSupport;
use App\Support\Stock\RecepcionProveedorImpuestoInternoSupport;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Stock\RecepcionProveedorCuadreContableSupport;
use Illuminate\Support\Facades\Log;

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
     * Recepción solo administrativa (cierre OC, etc.): Σ(cant × precio) ≈ 0 → sin asiento COM.
     */
    public function recepcionSinImporteContable(Recepcion_Proveedor $recepcion): bool
    {
        $recepcion->loadMissing(['recepcion_proveedor_articulos']);
        $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 1);

        return RecepcionProveedorCuadreContableSupport::importeContableEsCero(
            $this->totalRecepcionContable($recepcion, $cotizacionRecepcion)
        );
    }

    /**
     * Valida el cuadre recepción ↔ contabilidad sin grabar (falla rápido antes de stock/asiento).
     */
    public function assertCuadreRecepcion(Recepcion_Proveedor $recepcion): void
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return;
        }

        if ($this->recepcionSinImporteContable($recepcion)) {
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

        if ($this->recepcionSinImporteContable($recepcion)) {
            return null;
        }

        $this->prepararContabilidadAntesDeGenerar($recepcion);

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
        $this->revertirTodosAsientosDeRecepcion($recepcion);
    }

    /**
     * Antes de grabar: sin ctamov COM previo ni asientos ERP huérfanos de intentos fallidos.
     */
    public function prepararContabilidadAntesDeGenerar(Recepcion_Proveedor $recepcion): void
    {
        $this->eliminarCtamovAnitaPorComRecepcion($recepcion);
        $this->eliminarAsientosHuerfanosDeRecepcion((int) $recepcion->id, null);
    }

    /**
     * Rollback de confirmación: borra todos los asientos ERP (y ctamov por nro) de la recepción.
     */
    public function revertirTodosAsientosDeRecepcion(Recepcion_Proveedor $recepcion): void
    {
        $this->eliminarCtamovAnitaPorComRecepcion($recepcion);

        $asientos = Asiento::query()
            ->where('recepcionproveedor_id', (int) $recepcion->id)
            ->orderBy('id')
            ->get(['id', 'numeroasiento']);

        foreach ($asientos as $asiento) {
            try {
                $this->asientoRepository->delete((int) $asiento->id);
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAsiento: no se pudo eliminar asiento en rollback', [
                    'recepcion_id' => (int) $recepcion->id,
                    'asiento_id' => (int) $asiento->id,
                    'numeroasiento' => (string) ($asiento->numeroasiento ?? ''),
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Tras confirmación OK: conservar solo el asiento vigente.
     *
     * @return list<int> ids eliminados
     */
    public function eliminarAsientosHuerfanosDeRecepcion(int $recepcionId, ?int $conservarAsientoId = null): array
    {
        if ($recepcionId <= 0) {
            return [];
        }

        $query = Asiento::query()
            ->where('recepcionproveedor_id', $recepcionId)
            ->orderBy('id');

        if ($conservarAsientoId !== null && $conservarAsientoId > 0) {
            $query->where('id', '!=', $conservarAsientoId);
        }

        $eliminados = [];
        foreach ($query->get(['id', 'numeroasiento']) as $asiento) {
            try {
                $this->asientoRepository->delete((int) $asiento->id);
                $eliminados[] = (int) $asiento->id;
            } catch (\Throwable $e) {
                Log::warning('RecepcionProveedorAsiento: no se pudo eliminar asiento huérfano', [
                    'recepcion_id' => $recepcionId,
                    'asiento_id' => (int) $asiento->id,
                    'conservar_asiento_id' => $conservarAsientoId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($eliminados !== []) {
            Log::info('RecepcionProveedorAsiento: asientos huérfanos eliminados', [
                'recepcion_id' => $recepcionId,
                'conservar_asiento_id' => $conservarAsientoId,
                'asiento_ids' => $eliminados,
            ]);
        }

        return $eliminados;
    }

    private function eliminarCtamovAnitaPorComRecepcion(Recepcion_Proveedor $recepcion): void
    {
        $recepcion->loadMissing('empresas');
        $clave = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);

        $this->asientoRepository->eliminarCtamovAnitaPorComprobante(
            (int) $recepcion->empresa_id,
            (string) $clave['tipo'],
            (string) $clave['letra'],
            (int) $clave['sucursal'],
            (int) $clave['nro'],
        );
    }

    /**
     * Preview contable (Σ cantidad × precio de línea; precio ya neto de descuentos OC al precargar).
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
     * Líneas DEBE por cuenta de compra de artículos (agrupadas por cuenta + CC), coherente con el asiento COM.
     *
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int}>
     */
    public function lineasDebeArticulosAgrupadas(Recepcion_Proveedor $recepcion): array
    {
        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos.articulo_cuentacontables']);
        $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 1);

        return $this->armarLineasDebeArticulos($recepcion, $cotizacionRecepcion);
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

        if ($this->recepcionSinImporteContable($recepcion)) {
            throw new \RuntimeException(
                'La recepción no tiene importe contable; no corresponde recuadrar un asiento COM.'
            );
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

        if ($this->recepcionSinImporteContable($recepcion)) {
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

        $this->eliminarCtamovAnitaPorComRecepcion($recepcion);
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

        if ($this->recepcionSinImporteContable($recepcion)) {
            return [
                'activo' => true,
                'error' => null,
                'sin_asiento' => true,
                'mensaje' => 'Recepción sin importe contable (p. ej. solo cierre de líneas OC): no se generará asiento COM.',
                'es_preview' => true,
                'total_recepcion' => 0.0,
                'total_debe' => 0.0,
                'total_haber' => 0.0,
                'lineas' => [],
            ];
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
        $empresaId = (int) $recepcion->empresa_id;
        $provisionId = CuentaAutomaticaResolver::resolverId(
            $empresaId,
            CuentaAutomaticaClaves::RECEPCION_PROVISION_FACTURAS
        );
        if (! $provisionId) {
            throw new \RuntimeException('Falta configurar cuenta de provisión de facturas a recibir para la empresa.');
        }

        $recepcion->loadMissing([
            'proveedores',
            'recepcion_proveedor_articulos.articulos.articulo_cuentacontables',
            'ordencompras',
        ]);

        $descripcionAsiento = RecepcionProveedorAsientoDescripcionSupport::descripcionAsientoErp($recepcion);
        $descripcionCtamov = RecepcionProveedorAsientoDescripcionSupport::descripcionCtamovAnita($recepcion);
        $tipoAsiento = $this->resolverTipoAsientoCompras();

        $claveAnita = RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
        $oc = $recepcion->ordencompras;
        $numeroOrdenCompra = (int) ($oc->numeroordencompra ?? 0);
        $esDevolucion = $recepcion->tipo === Recepcion_Proveedor::TIPO_DEVOLUCION;
        $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 1);

        $lineasDebe = $this->armarLineasDebeArticulos($recepcion, $cotizacionRecepcion);
        $lineasDebe = $this->agregarLineaDebeImpuestoInterno($recepcion, $lineasDebe, $cotizacionRecepcion, $empresaId);
        $totalDebe = round(array_sum(array_column($lineasDebe, 'importe')), 2);
        $totalRecepcion = $this->totalRecepcionContable($recepcion, $cotizacionRecepcion);

        $lineasHaber = [];
        $esAnticipada = $oc && strtoupper((string) $oc->tratamiento) === 'ANTICIPADA';

        if ($esAnticipada) {
            $lineasHaber = $this->armarHaberAnticipada($oc, $empresaId, $totalDebe, $cotizacionRecepcion);
        } else {
            $lineasHaber[] = [
                'cuentacontable_id' => $provisionId,
                'importe' => $totalDebe,
            ];
        }

        $totalHaber = round(array_sum(array_column($lineasHaber, 'importe')), 2);
        $diferencia = round($totalDebe - $totalHaber, 2);

        if (abs($diferencia) >= 0.01 && $esAnticipada && $diferencia > 0) {
            $lineasHaber[] = [
                'cuentacontable_id' => $provisionId,
                'importe' => $diferencia,
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
            'observacion' => $descripcionAsiento,
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
            if ((float) ($linea['importe'] ?? 0) <= 0) {
                continue;
            }
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $linea['centrocosto_id'] ?? $ccDefault;
            $payloadAsiento['debes'][] = $linea['importe'];
            $payloadAsiento['haberes'][] = 0;
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = trim((string) ($linea['observacion'] ?? '')) !== ''
                ? (string) $linea['observacion']
                : $descripcionCtamov;
        }

        foreach ($lineasHaberAsiento as $linea) {
            if ((float) ($linea['importe'] ?? 0) <= 0) {
                continue;
            }
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $ccDefault;
            $payloadAsiento['debes'][] = 0;
            $payloadAsiento['haberes'][] = $linea['importe'];
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = trim((string) ($linea['observacion'] ?? '')) !== ''
                ? (string) $linea['observacion']
                : $descripcionCtamov;
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
        $monedaRecepcionId = (int) ($recepcion->moneda_id ?: 1);

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $total += RecepcionProveedorConversionSupport::importeLineaEnMonedaReferencia(
                $monedaRecepcionId,
                (int) ($linea->moneda_id ?: $monedaRecepcionId),
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
                0,
                (float) ($linea->cotizacion ?: 1),
            );
        }

        $total += RecepcionProveedorImpuestoInternoSupport::importeImpuestoInternoContable(
            $recepcion,
            $cotizacionRecepcion
        );

        return round($total, 2);
    }

    /**
     * @param  list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>  $lineasDebe
     * @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}>
     */
    private function agregarLineaDebeImpuestoInterno(
        Recepcion_Proveedor $recepcion,
        array $lineasDebe,
        float $cotizacionRecepcion,
        int $empresaId,
    ): array {
        $importe = RecepcionProveedorImpuestoInternoSupport::importeImpuestoInternoContable(
            $recepcion,
            $cotizacionRecepcion
        );
        if ($importe <= 0.000001) {
            return $lineasDebe;
        }

        $cuentaId = RecepcionProveedorImpuestoInternoSupport::resolverCuentaCompraImpuestoInterno($empresaId);
        $ccDefault = (int) ($recepcion->centrocosto_id ?? 0);
        if ($ccDefault <= 0) {
            $ccDefault = (int) ($recepcion->recepcion_proveedor_articulos->first()->centrocosto_id ?? 0);
        }

        $observacion = 'Impuesto interno cigarrillos ('.RecepcionProveedorImpuestoInternoSupport::skuArticuloImpuestoInterno().')';

        $encontrado = false;
        foreach ($lineasDebe as &$row) {
            $rowCc = (int) ($row['centrocosto_id'] ?? 0);
            if ((int) $row['cuentacontable_id'] === $cuentaId && $rowCc === $ccDefault) {
                $row['importe'] = round((float) $row['importe'] + $importe, 2);
                $row['observacion'] = $observacion;
                $encontrado = true;
                break;
            }
        }
        unset($row);

        if (! $encontrado) {
            $lineasDebe[] = [
                'cuentacontable_id' => $cuentaId,
                'centrocosto_id' => $ccDefault,
                'importe' => round($importe, 2),
                'observacion' => $observacion,
            ];
        }

        return $lineasDebe;
    }

    /** @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}> */
    private function armarLineasDebeArticulos(
        Recepcion_Proveedor $recepcion,
        float $cotizacionRecepcion,
    ): array {
        $agrupado = [];

        $empresaId = (int) $recepcion->empresa_id;
        $monedaRecepcionId = (int) ($recepcion->moneda_id ?: 1);

        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $articulo = $linea->articulos;
            $ctaId = $this->resolverCuentaCompraId($articulo, $empresaId);
            if ($ctaId <= 0) {
                throw new \RuntimeException('Artículo '.($articulo->sku ?? $linea->articulo_id).' sin cuenta contable de compra.');
            }

            $importe = RecepcionProveedorConversionSupport::importeLineaEnMonedaReferencia(
                $monedaRecepcionId,
                (int) ($linea->moneda_id ?: $monedaRecepcionId),
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0),
                0,
                (float) ($linea->cotizacion ?: 1),
            );

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

    private function resolverCuentaCompraId(?Articulo $articulo, int $empresaId): int
    {
        if (! $articulo) {
            return 0;
        }

        $cuentaGrid = $articulo->articulo_cuentacontables
            ?->first(fn ($row) => (int) $row->empresa_id === $empresaId
                && strtoupper((string) $row->tipoimputacion) === 'COMPRAS');

        if ($cuentaGrid && (int) $cuentaGrid->cuentacontable_id > 0) {
            return (int) $cuentaGrid->cuentacontable_id;
        }

        return (int) ($articulo->cuentacontablecompra_id ?? 0);
    }

    /**
     * @return list<array{cuentacontable_id:int, importe:float, observacion?:string}>
     */
    private function armarHaberAnticipada(
        Ordencompra $oc,
        int $empresaId,
        float $totalDebe,
        float $cotizacionRecepcion
    ): array {
        $cuentasAnticipo = array_filter([
            CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::RECEPCION_FACTURA_ANTICIPADA) ?? 0,
            CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::RECEPCION_ANTICIPO_BIENES_USO) ?? 0,
            CuentaAutomaticaResolver::resolverId($empresaId, CuentaAutomaticaClaves::RECEPCION_PROVEEDORES_INTANGIBLE) ?? 0,
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
