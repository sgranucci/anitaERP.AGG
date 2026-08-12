<?php

namespace App\Support\Compras;

use App\Models\Compras\PropuestaPago;
use App\Models\Compras\PropuestaPagoLinea;
use App\Models\Compras\Proveedor_Cuentacorriente;
use Carbon\Carbon;

/**
 * Presentación de líneas al estilo Anita l-proy.c (proyección de pagos).
 * Incluye M.Pago + Detalle pago desde cuota de factura / OC (occ_medio_pago / occ_detalle).
 */
class PropuestaPagoLineaPresentacionSupport
{
    /**
     * Resuelve forma de pago + detalle desde la deuda (cuota factura → cuota OC).
     *
     * @return array{formapago_id:?int, medio_pago:string, detalle_pago:string, ordencompra_id:?int, nro_refer:string}
     */
    public static function resolverMedioDesdeCuentacorriente(Proveedor_Cuentacorriente $cc): array
    {
        $cuota = $cc->comprobante_proveedor_cuotas
            ?? ($cc->comprobante_proveedor_cuota_id
                ? $cc->comprobante_proveedor_cuotas()->with([
                    'formapagos',
                    'ordencompra_comprobante_cuotas.formapagos',
                    'ordencompra_comprobante_cuotas.ordencompra_comprobantes',
                ])->first()
                : null);

        if ($cuota && ! $cuota->relationLoaded('formapagos')) {
            $cuota->load([
                'formapagos',
                'ordencompra_comprobante_cuotas.formapagos',
                'ordencompra_comprobante_cuotas.ordencompra_comprobantes',
            ]);
        }

        $formapagoId = null;
        $medio = '';
        $detalle = '';
        $ordencompraId = null;
        $nroRefer = '';

        if ($cuota) {
            $formapagoId = (int) ($cuota->formapago_id ?: 0) ?: null;
            $fp = $cuota->formapagos;
            $medio = (string) ($fp->abreviatura ?? $fp->nombre ?? '');
            $detalle = trim((string) ($cuota->detalle ?? ''));

            $ocCuota = $cuota->ordencompra_comprobante_cuotas;
            if ($ocCuota) {
                if ($formapagoId === null && $ocCuota->formapago_id) {
                    $formapagoId = (int) $ocCuota->formapago_id;
                    $fpOc = $ocCuota->formapagos;
                    $medio = (string) ($fpOc->abreviatura ?? $fpOc->nombre ?? $medio);
                }
                if ($detalle === '') {
                    $detalle = trim((string) ($ocCuota->detalle ?? ''));
                }
                $ocComp = $ocCuota->ordencompra_comprobantes;
                if ($ocComp && $ocComp->ordencompra_id) {
                    $ordencompraId = (int) $ocComp->ordencompra_id;
                    $nroRefer = (string) ($ocComp->ordencompra_id);
                }
            }
        }

        // Fallback: OC del comprobante proveedor
        if ($ordencompraId === null) {
            $comp = $cc->comprobante_proveedores;
            if ($comp && $comp->ordencompra_id) {
                $ordencompraId = (int) $comp->ordencompra_id;
                $nroRefer = (string) $ordencompraId;
            }
        }

        if ($medio === '' && $formapagoId) {
            $fp = \App\Models\Ventas\Formapago::query()->find($formapagoId);
            $medio = (string) ($fp->abreviatura ?? $fp->nombre ?? '');
        }

        // Anita muestra abreviaturas cortas (Cheque, Transf, Efect.)
        $medioCorto = self::abreviaturaAnita($medio);

        return [
            'formapago_id' => $formapagoId,
            'medio_pago' => $medioCorto !== '' ? $medioCorto : $medio,
            'detalle_pago' => $detalle,
            'ordencompra_id' => $ordencompraId,
            'nro_refer' => $nroRefer,
        ];
    }

    public static function abreviaturaAnita(string $nombreOAbrev): string
    {
        $u = mb_strtoupper(trim($nombreOAbrev));
        if ($u === '') {
            return '';
        }
        if (str_contains($u, 'TRANSF') || $u === 'T') {
            return 'Transf';
        }
        if (str_contains($u, 'VARIOS') || str_contains($u, 'V.CHEQ')) {
            return 'V.Cheq';
        }
        if (str_contains($u, 'CHEQUE') || $u === 'C' || $u === 'CH') {
            return 'Cheque';
        }
        if (str_contains($u, 'EFECT') || $u === 'E') {
            return 'Efect.';
        }
        if (str_contains($u, 'REGIST')) {
            return 'Regist';
        }

        return mb_substr($nombreOAbrev, 0, 6);
    }

    /**
     * @return array<string, mixed>
     */
    public static function enriquecer(PropuestaPagoLinea $linea, ?PropuestaPago $propuesta = null): array
    {
        $proveedor = $linea->proveedores;
        $comp = $linea->comprobante_proveedores;
        $fechaRef = $propuesta?->fecha ? Carbon::parse($propuesta->fecha)->startOfDay() : Carbon::today();
        $fechaVto = $linea->fechavencimiento ? Carbon::parse($linea->fechavencimiento)->startOfDay() : null;

        $dias = null;
        $bucket = 'sin_vto';
        if ($fechaVto) {
            $dias = (int) $fechaRef->diffInDays($fechaVto, false);
            $bucket = $fechaVto->lte($fechaRef) ? 'vencido' : 'a_vencer';
        }

        $comprobante = '';
        $tipo = '';
        if ($comp) {
            $tt = $comp->tipotransaccion_compras;
            $tipo = (string) ($tt->abreviatura ?? $tt->nombre ?? '');
            if (strlen($tipo) > 3) {
                $tipo = substr($tipo, 0, 3);
            }
            $comprobante = sprintf(
                '%s-%04d-%s',
                $comp->letra ?? 'A',
                (int) ($comp->sucursal ?? 0),
                $comp->numerocomprobante ?? ''
            );
        }

        $medio = '';
        $detallePago = (string) ($linea->detalle_pago ?? '');
        $nroRefer = $linea->ordencompra_id ? (string) $linea->ordencompra_id : '';

        if ($linea->formapagos) {
            $medio = self::abreviaturaAnita((string) ($linea->formapagos->abreviatura ?? $linea->formapagos->nombre ?? ''));
        } elseif ($linea->formapago_id) {
            $fp = \App\Models\Ventas\Formapago::query()->find((int) $linea->formapago_id);
            $medio = self::abreviaturaAnita((string) ($fp->abreviatura ?? $fp->nombre ?? ''));
        }

        // Si no hay snapshot, resolver en vivo desde CC
        if (($medio === '' || $detallePago === '') && $linea->proveedor_cuentacorrientes) {
            $vivo = self::resolverMedioDesdeCuentacorriente($linea->proveedor_cuentacorrientes);
            if ($medio === '') {
                $medio = $vivo['medio_pago'];
            }
            if ($detallePago === '') {
                $detallePago = $vivo['detalle_pago'];
            }
            if ($nroRefer === '') {
                $nroRefer = $vivo['nro_refer'];
            }
        }

        return [
            'codigo_proveedor' => (string) ($proveedor->codigo ?? ''),
            'nombre_proveedor' => (string) ($proveedor->nombre ?? ('#'.$linea->proveedor_id)),
            'tipo' => $tipo,
            'comprobante' => $comprobante !== '' ? $comprobante : ('CC#'.$linea->proveedor_cuentacorriente_id),
            'fecha_comprobante' => optional($comp?->fechacomprobante)->format('d/m/Y'),
            'fecha_iva' => optional($comp?->fechaiva)->format('d/m/Y'),
            'fecha_vto' => optional($linea->fechavencimiento)->format('d/m/Y'),
            'dias' => $dias,
            'condicion_pago' => (string) ($comp?->condicionpagos->nombre ?? ''),
            'nro_refer' => $nroRefer,
            'medio_pago' => $medio,
            'detalle_pago' => $detallePago,
            'moneda' => (string) ($linea->monedas->abreviatura ?? ''),
            'bucket' => $bucket,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PropuestaPagoLinea>  $lineas
     * @return array{vencidos: float, a_vencer: float, total: float, cant_vencidos: int, cant_a_vencer: int, por_medio: list<array{medio:string,monto:float,cant:int}>}
     */
    public static function resumenBuckets($lineas, ?PropuestaPago $propuesta = null): array
    {
        $fechaRef = $propuesta?->fecha ? Carbon::parse($propuesta->fecha)->startOfDay() : Carbon::today();
        $vencidos = 0.0;
        $aVencer = 0.0;
        $cantV = 0;
        $cantA = 0;
        $porMedio = [];

        foreach ($lineas as $linea) {
            if (! $linea->incluido) {
                continue;
            }
            $monto = (float) $linea->monto_propuesto;
            $fechaVto = $linea->fechavencimiento ? Carbon::parse($linea->fechavencimiento)->startOfDay() : null;
            if ($fechaVto && $fechaVto->lte($fechaRef)) {
                $vencidos += $monto;
                $cantV++;
            } else {
                $aVencer += $monto;
                $cantA++;
            }

            $p = self::enriquecer($linea, $propuesta);
            $clave = $p['medio_pago'] !== '' ? $p['medio_pago'] : '(sin medio)';
            if (! isset($porMedio[$clave])) {
                $porMedio[$clave] = ['medio' => $clave, 'monto' => 0.0, 'cant' => 0];
            }
            $porMedio[$clave]['monto'] += $monto;
            $porMedio[$clave]['cant']++;
        }

        usort($porMedio, static fn ($a, $b) => $b['monto'] <=> $a['monto']);

        return [
            'vencidos' => round($vencidos, 4),
            'a_vencer' => round($aVencer, 4),
            'total' => round($vencidos + $aVencer, 4),
            'cant_vencidos' => $cantV,
            'cant_a_vencer' => $cantA,
            'por_medio' => array_values(array_map(static function ($r) {
                $r['monto'] = round($r['monto'], 4);

                return $r;
            }, $porMedio)),
        ];
    }
}
