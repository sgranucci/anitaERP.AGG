<?php

namespace App\Services\Stock;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Contable\AsientoRepositoryInterface;
use App\Repositories\Contable\Asiento_MovimientoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Repositories\Contable\TipoasientoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Stock\RecepcionProveedorConversionSupport;
use Auth;
use Illuminate\Support\Facades\Log;

class RecepcionProveedorAsientoService
{
    public function __construct(
        private readonly AsientoRepositoryInterface $asientoRepository,
        private readonly Asiento_MovimientoRepositoryInterface $asientoMovimientoRepository,
        private readonly CuentacontableRepositoryInterface $cuentacontableRepository,
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
     * Genera asiento contable coherente con el valor de la recepción (sin IVA).
     */
    public function generarAsiento(Recepcion_Proveedor $recepcion): ?int
    {
        if (! $this->debeGenerarAsiento((int) $recepcion->empresa_id)) {
            return null;
        }

        $cfg = Configuracion_RecepcionProveedor::query()->where('empresa_id', $recepcion->empresa_id)->first();
        if (! $cfg || ! $cfg->cuentacontable_provision_facturas_id) {
            throw new \RuntimeException('Falta configurar cuenta de provisión de facturas a recibir para la empresa.');
        }

        $recepcion->loadMissing(['recepcion_proveedor_articulos.articulos', 'ordencompras']);
        $tipoAsiento = $this->tipoasientoRepository->findPorAbreviatura('COM')
            ?? $this->tipoasientoRepository->findPorAbreviatura('STK');

        if (! $tipoAsiento) {
            throw new \RuntimeException('No existe tipo de asiento COM o STK para recepción de proveedores.');
        }

        $cotizacionRecepcion = (float) ($recepcion->cotizacion ?: 1);
        $totalRecepcion = $this->totalRecepcion($recepcion);
        $lineasDebe = $this->armarLineasDebeArticulos($recepcion, $cotizacionRecepcion);
        $totalDebe = round(array_sum(array_column($lineasDebe, 'importe')), 2);

        $lineasHaber = [];
        $oc = $recepcion->ordencompras;
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

        if (abs($totalDebe - $totalHaber) >= 0.02) {
            throw new \RuntimeException(
                "Asiento desbalanceado: debe {$totalDebe} vs haber {$totalHaber} (recepción {$totalRecepcion})."
            );
        }

        $ccDefault = (int) ($recepcion->recepcion_proveedor_articulos->first()->centrocosto_id ?? 0);
        $payloadAsiento = [
            'empresa_id' => $recepcion->empresa_id,
            'tipoasiento_id' => $tipoAsiento->id,
            'fecha' => $recepcion->fecha->format('Y-m-d'),
            'recepcionproveedor_id' => $recepcion->id,
            'ordencompra_id' => $recepcion->ordencompra_id,
            'observacion' => 'Recepción proveedor '.$recepcion->numerorecepcion,
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
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $linea['centrocosto_id'] ?? $ccDefault;
            $payloadAsiento['debes'][] = $linea['importe'];
            $payloadAsiento['haberes'][] = 0;
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        foreach ($lineasHaber as $linea) {
            $payloadAsiento['cuentacontable_ids'][] = $linea['cuentacontable_id'];
            $payloadAsiento['moneda_ids'][] = $recepcion->moneda_id;
            $payloadAsiento['centrocosto_ids'][] = $ccDefault;
            $payloadAsiento['debes'][] = 0;
            $payloadAsiento['haberes'][] = $linea['importe'];
            $payloadAsiento['cotizaciones'][] = $cotizacionRecepcion;
            $payloadAsiento['observaciones'][] = $linea['observacion'] ?? '';
        }

        $asiento = $this->asientoRepository->create($payloadAsiento);
        if ($asiento === 'Error' || ! $asiento) {
            throw new \RuntimeException('Error al grabar asiento contable en Anita.');
        }

        $this->asientoMovimientoRepository->create($payloadAsiento, $asiento->id);

        return (int) $asiento->id;
    }

    public function anularAsiento(Recepcion_Proveedor $recepcion): void
    {
        $asientoId = (int) ($recepcion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return;
        }

        $this->asientoRepository->delete($asientoId);
    }

    private function totalRecepcion(Recepcion_Proveedor $recepcion): float
    {
        $total = 0;
        foreach ($recepcion->recepcion_proveedor_articulos as $linea) {
            $total += RecepcionProveedorConversionSupport::importeLinea(
                (float) $linea->cantidad,
                (float) $linea->precio,
                (float) ($linea->descuento ?? 0)
            );
        }

        return round($total, 2);
    }

    /** @return list<array{cuentacontable_id:int, importe:float, centrocosto_id?:int, observacion?:string}> */
    private function armarLineasDebeArticulos(Recepcion_Proveedor $recepcion, float $cotizacionRecepcion): array
    {
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
                (float) ($linea->descuento ?? 0)
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
