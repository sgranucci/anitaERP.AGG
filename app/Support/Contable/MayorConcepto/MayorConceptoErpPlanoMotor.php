<?php

declare(strict_types=1);

namespace App\Support\Contable\MayorConcepto;

use Illuminate\Support\Facades\DB;

/**
 * Mayor por concepto con origen ERP.
 *
 * Lee asientos, cuentas, conceptos y el gasto de la orden de pago desde MySQL.
 * Cada pata con cuenta menor o igual al límite de caja/banco sale con el mismo
 * importe y el mismo lado. Si la contrapartida es proveedores, ese importe se
 * abre en la cuenta de la COM de la orden de compra de la factura. Si la
 * factura no tiene la orden cargada, usa la COM que trae ese número de
 * factura. Si no hay COM, queda en la cuenta de la factura. Si no es proveedores,
 * queda en la contrapartida con el concepto de esa cuenta. Las OPP llevan
 * además el total de retenciones del pago, prorrateado con el mismo criterio.
 */
class MayorConceptoErpPlanoMotor
{
    private const PROVEEDOR_DESDE = 211010000;

    private const PROVEEDOR_HASTA = 212000000;

    private const TOLERANCIA = 0.02;

    /**
     * @return array<string, mixed>
     */
    public function generar(
        int $empresaId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $limite = (int) config('contable.mayor_concepto.limite_caja_banco', 112010008);
        $asientos = $empresaId > 0
            ? $this->cargarAsientos($empresaId, $fechaDesdeYmd, $fechaHastaYmd, $monedaReporteId, $soloMonedaOrigen, $monedaConverter)
            : [];

        $conProveedor = [];
        foreach ($asientos as $asiento) {
            foreach ($asiento['movimientos'] as $mov) {
                if ($this->esProveedor((int) $mov['cuenta'])) {
                    $conProveedor[(int) $asiento['id']] = true;
                    break;
                }
            }
        }

        $asientosProveedor = array_keys($conProveedor);
        $gastos = $this->cargarGastosPorAsiento($empresaId, $asientosProveedor);
        $retenciones = $this->cargarRetencionesPorAsiento($empresaId, $asientosProveedor);
        $clasificado = $this->clasificar($asientos, $gastos, $limite, $retenciones);
        $lineas = $clasificado['lineas'];
        $analitico = $clasificado['analitico'];
        $auditoria = $this->auditar($analitico, $lineas);

        $debe = 0.0;
        $haber = 0.0;
        $retencionesTotal = 0.0;
        foreach ($lineas as $ln) {
            $debe += (float) $ln['debe'];
            $haber += (float) $ln['haber'];
            $retencionesTotal += (float) ($ln['retenciones'] ?? 0);
        }

        return [
            'parametros' => [
                'empresa_id' => $empresaId,
                'fecha_desde' => $fechaDesdeYmd,
                'fecha_hasta' => $fechaHastaYmd,
                'moneda_reporte_id' => $monedaReporteId,
                'moneda_abreviatura' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                'solo_moneda_origen' => $soloMonedaOrigen,
                'motor' => 'erp_plano_limite',
                'fuente_etiqueta' => 'ERP — mayor por concepto sobre las patas hasta el límite',
                'limite_caja_banco' => $limite,
            ],
            'secciones' => $this->agruparPorConcepto($lineas),
            'totales' => [
                'lineas' => count($lineas),
                'debe' => round($debe, 2),
                'haber' => round($haber, 2),
                'retenciones' => round($retencionesTotal, 2),
            ],
            'errores_bridge' => [],
            'lectura_incompleta' => false,
            'stats' => [
                'asientos_erp' => count($asientos),
                'asientos_con_pata_limite' => count($analitico),
                'asientos_proveedor' => count($conProveedor),
                'lineas' => count($lineas),
                'motor' => 'erp_plano_limite',
            ],
            'analitico_por_asiento' => $analitico,
            'motivos_por_asiento' => [],
            '_auditoria' => $auditoria,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $asientos
     * @param  array<int, list<array<string, mixed>>>  $gastosPorAsiento
     * @param  array<int, float>  $retencionesPorAsiento  total de retenciones de la OPP, por asiento
     * @return array{lineas: list<array<string, mixed>>, analitico: array<int, array<string, mixed>>}
     */
    public function clasificar(array $asientos, array $gastosPorAsiento, int $limite, array $retencionesPorAsiento = []): array
    {
        $lineas = [];
        $analitico = [];

        foreach ($asientos as $asiento) {
            $movimientos = $asiento['movimientos'] ?? [];
            if ($movimientos === []) {
                continue;
            }

            $patas = [];
            foreach ($movimientos as $indice => $mov) {
                if ((int) $mov['cuenta'] > 0 && (int) $mov['cuenta'] <= $limite && (float) $mov['importe'] >= 0.005) {
                    $patas[$indice] = $mov;
                }
            }
            if ($patas === []) {
                continue;
            }

            $nro = (int) $asiento['nro'];
            $asientoId = (int) $asiento['id'];
            $inicioLineas = count($lineas);
            $debePlano = 0.0;
            $haberPlano = 0.0;
            $cuentasPlano = [];

            foreach ($patas as $indicePata => $pata) {
                $importe = round((float) $pata['importe'], 2);
                $esDebe = (bool) $pata['es_debe'];
                if ($esDebe) {
                    $debePlano += $importe;
                } else {
                    $haberPlano += $importe;
                }
                $cuentasPlano[$pata['cuenta_codigo']] = true;

                $opuestas = [];
                foreach ($movimientos as $indice => $otra) {
                    if ($indice === $indicePata || (bool) $otra['es_debe'] === $esDebe || (float) $otra['importe'] < 0.005) {
                        continue;
                    }
                    $opuestas[] = $otra;
                }

                $destino = $opuestas !== []
                    ? $this->repartir($importe, $opuestas, 'importe')
                    : [['peso_linea' => $importe, 'item' => $pata]];

                foreach ($destino as $trozo) {
                    $monto = round((float) $trozo['peso_linea'], 2);
                    if ($monto < 0.005) {
                        continue;
                    }
                    $contra = $trozo['item'];
                    if ($this->esProveedor((int) $contra['cuenta'])) {
                        $gastos = $gastosPorAsiento[$asientoId] ?? [];
                        $sumaPesos = 0.0;
                        foreach ($gastos as $gasto) {
                            $sumaPesos += (float) ($gasto['peso'] ?? 0);
                        }
                        $fraccion = $gastos === [] ? null : ($gastos[0]['fraccion'] ?? null);
                        if ($gastos === []) {
                            $aRepartir = 0.0;
                        } elseif ($fraccion !== null) {
                            $aRepartir = round($monto * (float) $fraccion, 2);
                        } else {
                            $aRepartir = round(min($monto, $sumaPesos), 2);
                        }
                        $partesGasto = $aRepartir >= 0.005
                            ? $this->repartir($aRepartir, $gastos, 'peso')
                            : [];
                        foreach ($partesGasto as $parte) {
                            $linea = $this->linea($asiento, $pata, $parte['item'], (float) $parte['peso_linea'], $esDebe, true);
                            $linea['lleva_retencion'] = true;
                            $lineas[] = $linea;
                        }
                        $resto = round($monto - $aRepartir, 2);
                        if ($resto >= 0.005) {
                            $linea = $this->linea($asiento, $pata, $contra, $resto, $esDebe, false);
                            $linea['lleva_retencion'] = true;
                            $lineas[] = $linea;
                        }
                        continue;
                    }

                    $lineas[] = $this->linea($asiento, $pata, $contra, $monto, $esDebe, false);
                }
            }

            $this->asignarRetenciones($lineas, $inicioLineas, (float) ($retencionesPorAsiento[$asientoId] ?? 0));

            $cuentas = array_keys($cuentasPlano);
            sort($cuentas);
            $analitico[$asientoId] = [
                'asiento_id' => $asientoId,
                'nro_asiento' => $nro,
                'debe' => round($debePlano, 2),
                'haber' => round($haberPlano, 2),
                'fecha_min' => (int) $asiento['fecha'],
                'fecha_fmt' => $this->fmtFecha((int) $asiento['fecha']),
                'cuentas' => $cuentas,
            ];
        }

        return ['lineas' => $lineas, 'analitico' => $analitico];
    }

    /**
     * Compara, asiento por asiento, el Debe y el Haber de las patas hasta el límite
     * contra el Debe y el Haber del mayor por concepto.
     *
     * @param  array<int, array<string, mixed>>  $analitico
     * @param  list<array<string, mixed>>  $lineas
     * @return array<string, mixed>
     */
    public function auditar(array $analitico, array $lineas): array
    {
        $concepto = [];
        foreach ($lineas as $ln) {
            $id = (int) ($ln['asiento_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (! isset($concepto[$id])) {
                $concepto[$id] = [
                    'debe' => 0.0,
                    'haber' => 0.0,
                    'cuentas' => [],
                    'nro_asiento' => (int) ($ln['nro_asiento'] ?? 0),
                    'fecha_fmt' => (string) ($ln['fecha_fmt'] ?? ''),
                ];
            }
            $concepto[$id]['debe'] += (float) ($ln['debe'] ?? 0);
            $concepto[$id]['haber'] += (float) ($ln['haber'] ?? 0);
            $codigo = (string) ($ln['cuenta_codigo'] ?? '');
            if ($codigo !== '') {
                $concepto[$id]['cuentas'][$codigo] = true;
            }
        }

        $ids = array_unique(array_merge(array_keys($analitico), array_keys($concepto)));
        sort($ids, SORT_NUMERIC);

        $filas = [];
        $descuadrados = 0;
        foreach ($ids as $id) {
            $a = $analitico[$id] ?? null;
            $c = $concepto[$id] ?? null;
            $debeA = round((float) ($a['debe'] ?? 0), 2);
            $haberA = round((float) ($a['haber'] ?? 0), 2);
            $debeC = round((float) ($c['debe'] ?? 0), 2);
            $haberC = round((float) ($c['haber'] ?? 0), 2);
            $dDebe = round($debeA - $debeC, 2);
            $dHaber = round($haberA - $haberC, 2);
            $cuadra = abs($dDebe) < self::TOLERANCIA && abs($dHaber) < self::TOLERANCIA;
            if (! $cuadra) {
                $descuadrados++;
            }

            $cuentasConcepto = array_keys($c['cuentas'] ?? []);
            sort($cuentasConcepto);
            $nro = (int) ($a['nro_asiento'] ?? $c['nro_asiento'] ?? 0);
            $fecha = (string) ($a['fecha_fmt'] ?? $c['fecha_fmt'] ?? '');

            $filas[] = [
                'asiento_id' => (int) $id,
                'nro_asiento' => $nro,
                'fecha' => (int) ($a['fecha_min'] ?? 0),
                'fecha_fmt' => $fecha,
                'cuentas_analitico' => implode(', ', $a['cuentas'] ?? []),
                'cuentas_concepto' => implode(', ', $cuentasConcepto),
                'debe_analitico' => $debeA,
                'haber_analitico' => $haberA,
                'debe_concepto' => $debeC,
                'haber_concepto' => $haberC,
                'neto_analitico' => round($debeA - $haberA, 2),
                'neto_concepto' => round($debeC - $haberC, 2),
                'diferencia' => round(abs($dDebe) + abs($dHaber), 2),
                'cuadra' => $cuadra,
                'excepcion' => false,
                'origen' => $a !== null && $c !== null
                    ? 'En ambos reportes'
                    : ($a !== null ? 'Solo en el mayor plano hasta el límite' : 'Solo en el mayor por concepto'),
                'motivo' => $cuadra
                    ? ''
                    : 'Debe plano '.$debeA.' / concepto '.$debeC.'; Haber plano '.$haberA.' / concepto '.$haberC.'.',
            ];
        }

        usort($filas, fn ($x, $y) => abs((float) $y['diferencia']) <=> abs((float) $x['diferencia']));
        $filasDescuadradas = array_values(array_filter($filas, fn ($f) => empty($f['cuadra'])));
        $filasCuadradas = array_values(array_filter($filas, fn ($f) => ! empty($f['cuadra'])));
        $analizados = count($filas);

        return [
            'cuadra' => $descuadrados === 0,
            'tolerancia' => self::TOLERANCIA,
            'regla' => 'El Debe y el Haber de cada asiento en el mayor por concepto igualan la pata hasta el límite',
            'asientos_analizados' => $analizados,
            'asientos_cuadrados' => $analizados - $descuadrados,
            'asientos_descuadrados' => $descuadrados,
            'asientos_excepcion' => 0,
            'porcentaje_cuadrado' => $analizados > 0 ? round(100 * ($analizados - $descuadrados) / $analizados, 1) : 100.0,
            'filas' => $filas,
            'filas_descuadradas' => $filasDescuadradas,
            'filas_cuadradas' => $filasCuadradas,
            'nota' => 'Solo entran las cuentas menores o iguales al límite de caja/banco. El importe de esa pata no se cambia: se abre en las cuentas de gasto de la COM si la contrapartida es proveedores, y si no queda en la contrapartida con el concepto de esa cuenta.',
        ];
    }

    /**
     * Reparte el total de retenciones de la OPP entre las líneas que salieron
     * de la cuenta de proveedores, con el mismo peso que el importe. La suma
     * de la columna cierra en el total del pago. Si el movimiento es un
     * reverso (neto al Debe), el importe de la columna sale en negativo.
     *
     * @param  list<array<string, mixed>>  $lineas
     */
    private function asignarRetenciones(array &$lineas, int $desde, float $total): void
    {
        $marcadas = [];
        for ($i = $desde, $n = count($lineas); $i < $n; $i++) {
            if (empty($lineas[$i]['lleva_retencion'])) {
                continue;
            }
            unset($lineas[$i]['lleva_retencion']);
            $marcadas[] = $i;
        }
        if ($marcadas === [] || abs($total) < 0.005) {
            return;
        }

        $pesos = [];
        $suma = 0.0;
        $neto = 0.0;
        foreach ($marcadas as $i) {
            $peso = abs((float) $lineas[$i]['debe']) + abs((float) $lineas[$i]['haber']);
            $pesos[$i] = $peso;
            $suma += $peso;
            $neto += (float) $lineas[$i]['haber'] - (float) $lineas[$i]['debe'];
        }
        if ($suma <= 0) {
            return;
        }

        $firmado = abs($total) * ($neto < 0 ? -1.0 : 1.0);
        if ($total < 0) {
            $firmado = -abs($firmado);
        }

        $acumulado = 0.0;
        $ultimo = $marcadas[count($marcadas) - 1];
        foreach ($marcadas as $i) {
            if ($i === $ultimo) {
                $monto = round($firmado - $acumulado, 2);
            } else {
                $monto = round($firmado * ($pesos[$i] / $suma), 2);
                $acumulado = round($acumulado + $monto, 2);
            }
            $lineas[$i]['retenciones'] = $monto;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{peso_linea: float, item: array<string, mixed>}>
     */
    private function repartir(float $importe, array $items, string $campoPeso): array
    {
        $vivos = [];
        $total = 0.0;
        foreach ($items as $item) {
            $peso = (float) ($item[$campoPeso] ?? 0);
            if ($peso <= 0) {
                continue;
            }
            $vivos[] = $item;
            $total += $peso;
        }
        if ($vivos === [] || $total <= 0 || $importe < 0.005) {
            return [];
        }

        $salida = [];
        $acumulado = 0.0;
        $ultimo = count($vivos) - 1;
        foreach ($vivos as $i => $item) {
            if ($i === $ultimo) {
                $monto = round($importe - $acumulado, 2);
            } else {
                $monto = round($importe * ((float) $item[$campoPeso] / $total), 2);
                $acumulado = round($acumulado + $monto, 2);
            }
            if (abs($monto) < 0.005) {
                continue;
            }
            $salida[] = ['peso_linea' => $monto, 'item' => $item];
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $asiento
     * @param  array<string, mixed>  $pata
     * @param  array<string, mixed>  $destino
     * @return array<string, mixed>
     */
    private function linea(array $asiento, array $pata, array $destino, float $importe, bool $esDebe, bool $desdeGasto): array
    {
        $importe = round($importe, 2);
        $debe = $esDebe ? $importe : 0.0;
        $haber = $esDebe ? 0.0 : $importe;
        $cuentaDisp = (int) $pata['cuenta'];

        return [
            'concepto_id' => (int) ($destino['concepto_id'] ?? 0),
            'concepto_nombre' => (string) ($destino['concepto_nombre'] ?? 'SIN CLASIFICAR'),
            'cuenta' => (int) $destino['cuenta'],
            'cuenta_codigo' => (string) $destino['cuenta_codigo'],
            'cuenta_nombre' => (string) ($destino['cuenta_nombre'] ?? ''),
            'cuenta_disponibilidad' => $cuentaDisp,
            'cuenta_disponibilidad_codigo' => (string) $pata['cuenta_codigo'],
            'fecha' => (int) $asiento['fecha'],
            'fecha_fmt' => $this->fmtFecha((int) $asiento['fecha']),
            'asiento_id' => (int) $asiento['id'],
            'nro_asiento' => (int) $asiento['nro'],
            'tipo_comp' => (string) ($asiento['tipo'] ?? ''),
            'comprobante' => $this->formatearComprobante(
                (string) ($asiento['tipo'] ?? ''),
                (string) ($asiento['letra'] ?? ''),
                (int) ($asiento['sucursal'] ?? 0),
                (int) ($asiento['nro_comp'] ?? 0),
            ),
            'cheque' => '',
            'nro_oc' => (int) ($pata['nro_oc'] ?? 0),
            'emisor' => (string) ($destino['emisor'] ?? $asiento['emisor'] ?? ''),
            'cuit' => (string) ($destino['cuit'] ?? ''),
            'descripcion' => (string) ($pata['descripcion'] ?? $asiento['observacion'] ?? ''),
            'moneda_abrev' => (string) ($asiento['moneda_abrev'] ?? ''),
            'cotizacion' => (float) ($pata['cotizacion'] ?? 0),
            'debe' => $debe,
            'haber' => $haber,
            'retenciones' => 0.0,
            'disp_debe' => $debe,
            'disp_haber' => $haber,
            'origen' => (string) ($destino['origen_linea']
                ?? ($desdeGasto ? 'Gasto del comprobante de compra' : 'Contrapartida de la pata hasta el límite')),
            'desde_operacion_disponibilidad' => true,
            'empresa_id' => (int) ($asiento['empresa_id'] ?? 0),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cargarAsientos(
        int $empresaId,
        int $fechaDesdeYmd,
        int $fechaHastaYmd,
        int $monedaReporteId,
        bool $soloMonedaOrigen,
        MayorConceptoMonedaConverter $monedaConverter,
    ): array {
        $filas = DB::table('asiento as a')
            ->join('asiento_movimiento as am', 'am.asiento_id', '=', 'a.id')
            ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
            ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
            ->where('a.empresa_id', $empresaId)
            ->whereBetween('a.fecha', [$this->ymdAFecha($fechaDesdeYmd), $this->ymdAFecha($fechaHastaYmd)])
            ->orderBy('a.id')
            ->orderBy('am.id')
            ->get([
                'a.id',
                'a.numeroasiento',
                'a.anita_nro_asiento',
                'a.fecha',
                'a.observacion',
                'a.anita_tipo',
                'a.anita_letra',
                'a.anita_sucursal',
                'a.anita_nro',
                'a.anita_emisor',
                'am.monto',
                'am.cotizacion',
                'am.moneda_id',
                'am.observacion as mov_observacion',
                'am.anita_tipo as mov_anita_tipo',
                'am.anita_letra as mov_anita_letra',
                'am.anita_sucursal as mov_anita_sucursal',
                'am.anita_nro as mov_anita_nro',
                'am.nro_ordencompra',
                'cc.codigo as cuenta_codigo',
                'cc.nombre as cuenta_nombre',
                'cc.conceptogasto_id',
                'cg.nombre as concepto_nombre',
            ]);

        $porId = [];
        foreach ($filas as $f) {
            $codMon = $monedaConverter->codigoAnitaDesdeMonedaId((int) ($f->moneda_id ?? 1));
            $cotizacion = (float) ($f->cotizacion ?? 0);
            if (! $monedaConverter->movimientoVisible($codMon, $cotizacion, $monedaReporteId, $soloMonedaOrigen)) {
                continue;
            }

            $nominal = (float) $f->monto;
            if (abs($nominal) < 0.00005) {
                continue;
            }

            $fecha = $this->fechaAYmd($f->fecha);
            $importe = abs($monedaConverter->convertirImporte($nominal, $codMon, $cotizacion, $fecha, $monedaReporteId));
            if ($importe < 0.005) {
                continue;
            }

            $id = (int) $f->id;
            if (! isset($porId[$id])) {
                $nro = MayorConceptoErpMetadatosSupport::numeroAsientoOperativo($f->anita_nro_asiento, $f->numeroasiento);
                if ($nro <= 0) {
                    $nro = $id;
                }
                $porId[$id] = [
                    'id' => $id,
                    'empresa_id' => $empresaId,
                    'nro' => $nro,
                    'fecha' => $fecha,
                    'tipo' => strtoupper(trim((string) ($f->anita_tipo ?? ''))),
                    'letra' => trim((string) ($f->anita_letra ?? '')),
                    'sucursal' => (int) ($f->anita_sucursal ?? 0),
                    'nro_comp' => (int) ($f->anita_nro ?? 0),
                    'emisor' => trim((string) ($f->anita_emisor ?? '')),
                    'observacion' => trim((string) ($f->observacion ?? '')),
                    'moneda_abrev' => $monedaConverter->abreviaturaMoneda($monedaReporteId),
                    'movimientos' => [],
                ];
            }

            if ($porId[$id]['tipo'] === '' && trim((string) ($f->mov_anita_tipo ?? '')) !== '') {
                $porId[$id]['tipo'] = strtoupper(trim((string) $f->mov_anita_tipo));
                $porId[$id]['letra'] = trim((string) ($f->mov_anita_letra ?? ''));
                $porId[$id]['sucursal'] = (int) ($f->mov_anita_sucursal ?? 0);
                $porId[$id]['nro_comp'] = (int) ($f->mov_anita_nro ?? 0);
            }

            $codigo = $this->soloDigitos($f->cuenta_codigo);
            $conceptoId = (int) ($f->conceptogasto_id ?? 0);
            $porId[$id]['movimientos'][] = [
                'cuenta' => $codigo,
                'cuenta_codigo' => $this->formatearCodigoCuenta($codigo),
                'cuenta_nombre' => trim((string) ($f->cuenta_nombre ?? '')),
                'concepto_id' => $conceptoId,
                'concepto_nombre' => $conceptoId > 0
                    ? trim((string) ($f->concepto_nombre ?? ''))
                    : 'SIN CLASIFICAR',
                'importe' => round($importe, 2),
                'es_debe' => $nominal >= 0,
                'descripcion' => trim((string) ($f->mov_observacion ?? '')),
                'nro_oc' => (int) ($f->nro_ordencompra ?? 0),
                'cotizacion' => $cotizacion,
                'peso' => round($importe, 2),
            ];
        }

        return array_values($porId);
    }

    /**
     * Pesos de gasto por asiento. De cada factura del pago se toma la orden de
     * compra y, de esa orden, la COM. Si la factura no tiene COM, se usa la
     * cuenta de la factura.
     *
     * @param  list<int>  $asientoIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function cargarGastosPorAsiento(int $empresaId, array $asientoIds): array
    {
        if ($empresaId <= 0 || $asientoIds === []) {
            return [];
        }

        $pagoPorAsiento = $this->pagoIdPorAsiento($empresaId, $asientoIds);
        $aplicaciones = $this->aplicacionesDePagos($pagoPorAsiento);

        if ($aplicaciones === []) {
            return [];
        }

        $comprobantes = [];
        foreach ($aplicaciones as $ap) {
            $comprobantes[(int) $ap->comprobante_proveedor_id] = true;
        }
        $signos = $this->signoPorComprobante(array_keys($comprobantes));
        $notasCredito = array_keys(array_filter($signos, fn (int $s) => $s < 0));
        $cuentasNotaCredito = $this->cargarCuentasNotaCredito($notasCredito);
        $idsComprobante = array_values(array_filter(
            array_keys($comprobantes),
            fn (int $id) => ($signos[$id] ?? 1) > 0,
        ));

        $gastosCom = $this->cargarGastosComPorComprobante($idsComprobante);
        $sinCom = array_values(array_filter(
            $idsComprobante,
            fn (int $id) => ! isset($gastosCom[$id]),
        ));
        foreach ($this->cargarGastosComPorOrdencompra($sinCom) as $cp => $lineas) {
            $gastosCom[$cp] = $lineas;
        }
        $sinCom = array_values(array_filter(
            $idsComprobante,
            fn (int $id) => ! isset($gastosCom[$id]),
        ));
        foreach ($this->cargarGastosComPorNumeroFactura($sinCom) as $cp => $lineas) {
            $gastosCom[$cp] = $lineas;
        }
        $sinCom = array_values(array_filter(
            $idsComprobante,
            fn (int $id) => ! isset($gastosCom[$id]),
        ));
        $cuentasFactura = $this->cargarCuentasFactura($sinCom);

        $porAsiento = [];
        $aplicadoPorAsiento = [];
        $cubiertoPorAsiento = [];
        foreach ($aplicaciones as $ap) {
            $cp = (int) $ap->comprobante_proveedor_id;
            $signo = $signos[$cp] ?? 1;
            $aplicadoPorAsiento[(int) $ap->asiento_id] = ($aplicadoPorAsiento[(int) $ap->asiento_id] ?? 0.0)
                + $signo * (float) $ap->montoaplicado;
            if ($signo < 0) {
                $lineasGasto = $cuentasNotaCredito[$cp] ?? [];
                $origenLinea = 'Gasto del comprobante de compra';
            } elseif (isset($gastosCom[$cp])) {
                $lineasGasto = $gastosCom[$cp];
                $origenLinea = 'Gasto de la COM';
            } else {
                $lineasGasto = $cuentasFactura[$cp] ?? [];
                $origenLinea = 'Gasto del comprobante de compra';
            }
            if ($lineasGasto === []) {
                continue;
            }
            $suma = 0.0;
            foreach ($lineasGasto as $g) {
                $suma += (float) $g->importe;
            }
            if ($suma <= 0) {
                continue;
            }
            $aplicado = $signo * (float) $ap->montoaplicado;
            $asientoId = (int) $ap->asiento_id;
            foreach ($lineasGasto as $g) {
                $codigo = $this->soloDigitos($g->codigo);
                if ($codigo <= 0) {
                    continue;
                }
                $conceptoId = (int) ($g->conceptogasto_id ?? 0);
                $peso = $aplicado * ((float) $g->importe / $suma);
                $clave = $asientoId.'|'.$codigo.'|'.$conceptoId;
                if (! isset($porAsiento[$asientoId][$clave])) {
                    $porAsiento[$asientoId][$clave] = [
                        'cuenta' => $codigo,
                        'cuenta_codigo' => $this->formatearCodigoCuenta($codigo),
                        'cuenta_nombre' => trim((string) ($g->nombre ?? '')),
                        'concepto_id' => $conceptoId,
                        'concepto_nombre' => $conceptoId > 0
                            ? trim((string) ($g->concepto_nombre ?? ''))
                            : 'SIN CLASIFICAR',
                        'peso' => 0.0,
                        'emisor' => trim((string) ($ap->proveedor_nombre ?? '')),
                        'cuit' => '',
                        'origen_linea' => $origenLinea,
                    ];
                }
                if ($origenLinea === 'Gasto de la COM') {
                    $porAsiento[$asientoId][$clave]['origen_linea'] = 'Gasto de la COM';
                }
                $porAsiento[$asientoId][$clave]['peso'] += $peso;
                $cubiertoPorAsiento[$asientoId] = ($cubiertoPorAsiento[$asientoId] ?? 0.0) + $peso;
            }
        }

        $salida = [];
        foreach ($porAsiento as $asientoId => $filas) {
            $aplicado = $aplicadoPorAsiento[$asientoId] ?? 0.0;
            $filas = array_filter($filas, fn (array $fila) => (float) $fila['peso'] >= 0.005);
            if ($filas === []) {
                continue;
            }
            $fraccion = $aplicado > 0
                ? max(0.0, min(1.0, ($cubiertoPorAsiento[$asientoId] ?? 0.0) / $aplicado))
                : 1.0;
            foreach ($filas as $clave => $fila) {
                $filas[$clave]['fraccion'] = $fraccion;
            }
            $salida[$asientoId] = array_values($filas);
        }

        return $salida;
    }

    /**
     * COM de la orden de compra de la factura. El Debe de esas COM, sin proveedores.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, list<object>>
     */
    private function cargarGastosComPorOrdencompra(array $idsComprobante): array
    {
        $idsComprobante = array_values(array_filter(array_map('intval', $idsComprobante), fn (int $id) => $id > 0));
        if ($idsComprobante === []) {
            return [];
        }

        $ocPorComprobante = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor')
                ->whereIn('id', $lote)
                ->where('ordencompra_id', '>', 0)
                ->get(['id', 'ordencompra_id']);
            foreach ($filas as $f) {
                $ocPorComprobante[(int) $f->id] = (int) $f->ordencompra_id;
            }
        }
        if ($ocPorComprobante === []) {
            return [];
        }

        $asientosPorOc = [];
        foreach (array_chunk(array_values(array_unique($ocPorComprobante)), 800) as $lote) {
            $filas = DB::table('recepcion_proveedor')
                ->where('anita_tipo', 'COM')
                ->whereNotNull('asiento_id')
                ->whereIn('ordencompra_id', $lote)
                ->get(['ordencompra_id', 'asiento_id']);
            foreach ($filas as $f) {
                $asientosPorOc[(int) $f->ordencompra_id][(int) $f->asiento_id] = true;
            }
        }
        if ($asientosPorOc === []) {
            return [];
        }

        $asientoIds = [];
        foreach ($asientosPorOc as $asientos) {
            foreach (array_keys($asientos) as $asientoId) {
                $asientoIds[$asientoId] = true;
            }
        }
        $movs = $this->cargarDebeNoProveedorPorAsiento(array_keys($asientoIds));

        $porComprobante = [];
        foreach ($ocPorComprobante as $cp => $ocId) {
            $acum = [];
            foreach (array_keys($asientosPorOc[$ocId] ?? []) as $asientoId) {
                foreach ($movs[$asientoId] ?? [] as $g) {
                    $codigo = $this->soloDigitos($g->codigo);
                    $conceptoId = (int) ($g->conceptogasto_id ?? 0);
                    $clave = $codigo.'|'.$conceptoId;
                    if (! isset($acum[$clave])) {
                        $acum[$clave] = (object) [
                            'codigo' => $g->codigo,
                            'nombre' => $g->nombre,
                            'conceptogasto_id' => $conceptoId,
                            'concepto_nombre' => $g->concepto_nombre,
                            'importe' => 0.0,
                        ];
                    }
                    $acum[$clave]->importe += (float) $g->importe;
                }
            }
            if ($acum !== []) {
                $porComprobante[$cp] = array_values($acum);
            }
        }

        return $porComprobante;
    }

    /**
     * COM que anota el número de esta factura (sucursal y número), misma
     * empresa y mismo proveedor. La factura no tiene la orden de compra cargada.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, list<object>>
     */
    private function cargarGastosComPorNumeroFactura(array $idsComprobante): array
    {
        $idsComprobante = array_values(array_filter(array_map('intval', $idsComprobante), fn (int $id) => $id > 0));
        if ($idsComprobante === []) {
            return [];
        }

        $facturas = [];
        $empresas = [];
        $proveedores = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor')
                ->whereIn('id', $lote)
                ->where('proveedor_id', '>', 0)
                ->where('numerocomprobante', '>', 0)
                ->get(['id', 'empresa_id', 'proveedor_id', 'sucursal', 'numerocomprobante']);
            foreach ($filas as $f) {
                $facturas[(int) $f->id] = $f;
                $empresas[(int) $f->empresa_id] = true;
                $proveedores[(int) $f->proveedor_id] = true;
            }
        }
        if ($facturas === []) {
            return [];
        }

        $asientosPorClave = [];
        foreach (array_chunk(array_keys($proveedores), 400) as $lote) {
            $filas = DB::table('recepcion_proveedor')
                ->where('anita_tipo', 'COM')
                ->whereNotNull('asiento_id')
                ->where('numerofactura', '!=', '')
                ->whereIn('empresa_id', array_keys($empresas))
                ->whereIn('proveedor_id', $lote)
                ->get(['empresa_id', 'proveedor_id', 'numerofactura', 'asiento_id']);
            foreach ($filas as $f) {
                $parsed = $this->parsearNumeroFacturaCom((string) $f->numerofactura);
                if ($parsed === null) {
                    continue;
                }
                $clave = (int) $f->empresa_id.'|'.(int) $f->proveedor_id.'|'.$parsed['sucursal'].'|'.$parsed['numero'];
                $asientosPorClave[$clave][(int) $f->asiento_id] = true;
            }
        }
        if ($asientosPorClave === []) {
            return [];
        }

        $asientoIds = [];
        $asientosPorComprobante = [];
        foreach ($facturas as $cp => $f) {
            $clave = (int) $f->empresa_id.'|'.(int) $f->proveedor_id.'|'.(int) $f->sucursal.'|'.(int) $f->numerocomprobante;
            $asientos = array_keys($asientosPorClave[$clave] ?? []);
            if ($asientos === []) {
                continue;
            }
            $asientosPorComprobante[$cp] = $asientos;
            foreach ($asientos as $asientoId) {
                $asientoIds[$asientoId] = true;
            }
        }
        if ($asientosPorComprobante === []) {
            return [];
        }

        $movs = $this->cargarDebeNoProveedorPorAsiento(array_keys($asientoIds));
        $porComprobante = [];
        foreach ($asientosPorComprobante as $cp => $asientos) {
            $acum = [];
            foreach ($asientos as $asientoId) {
                foreach ($movs[$asientoId] ?? [] as $g) {
                    $codigo = $this->soloDigitos($g->codigo);
                    $conceptoId = (int) ($g->conceptogasto_id ?? 0);
                    $clave = $codigo.'|'.$conceptoId;
                    if (! isset($acum[$clave])) {
                        $acum[$clave] = (object) [
                            'codigo' => $g->codigo,
                            'nombre' => $g->nombre,
                            'conceptogasto_id' => $conceptoId,
                            'concepto_nombre' => $g->concepto_nombre,
                            'importe' => 0.0,
                        ];
                    }
                    $acum[$clave]->importe += (float) $g->importe;
                }
            }
            if ($acum !== []) {
                $porComprobante[$cp] = array_values($acum);
            }
        }

        return $porComprobante;
    }

    /**
     * Número de factura anotado en la COM: sucursal y número.
     *
     * @return array{sucursal: int, numero: int}|null
     */
    private function parsearNumeroFacturaCom(string $numerofactura): ?array
    {
        $raw = trim($numerofactura);
        if ($raw === '' || ! preg_match('/(\d+)\D+(\d+)\s*$/', $raw, $m)) {
            return null;
        }
        $sucursal = (int) $m[1];
        $numero = (int) $m[2];
        if ($numero <= 0) {
            return null;
        }

        return ['sucursal' => $sucursal, 'numero' => $numero];
    }

    /**
     * Asiento de la factura. Si la ficha no lo tiene cargado, el subdiario
     * con el mismo tipo, letra, sucursal y número. Si hay más de uno, el de
     * la fecha de contabilización (fecha de IVA).
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, int>
     */
    private function asientoIdPorFactura(array $idsComprobante): array
    {
        $asientoPorCp = [];
        $sinEnlace = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor as cp')
                ->leftJoin('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
                ->whereIn('cp.id', $lote)
                ->get([
                    'cp.id',
                    'cp.empresa_id',
                    'cp.asiento_id',
                    'cp.letra',
                    'cp.sucursal',
                    'cp.numerocomprobante',
                    'cp.fechaiva',
                    'tc.abreviatura',
                ]);
            foreach ($filas as $f) {
                $cp = (int) $f->id;
                $asientoId = (int) ($f->asiento_id ?? 0);
                if ($asientoId > 0) {
                    $asientoPorCp[$cp] = $asientoId;
                    continue;
                }
                $tipo = strtoupper(trim((string) ($f->abreviatura ?? '')));
                $numero = (int) ($f->numerocomprobante ?? 0);
                if ($tipo === '' || $numero <= 0) {
                    continue;
                }
                $sinEnlace[$cp] = [
                    'empresa_id' => (int) $f->empresa_id,
                    'tipo' => $tipo,
                    'letra' => strtoupper(trim((string) ($f->letra ?? ''))),
                    'sucursal' => (int) ($f->sucursal ?? 0),
                    'numero' => $numero,
                    'fechaiva' => substr((string) ($f->fechaiva ?? ''), 0, 10),
                ];
            }
        }
        if ($sinEnlace === []) {
            return $asientoPorCp;
        }

        $numeros = [];
        $empresas = [];
        $tipos = [];
        foreach ($sinEnlace as $dato) {
            $numeros[$dato['numero']] = true;
            $empresas[$dato['empresa_id']] = true;
            $tipos[$dato['tipo']] = true;
        }

        $porClave = [];
        foreach (array_chunk(array_keys($numeros), 800) as $lote) {
            $filas = DB::table('asiento')
                ->whereIn('empresa_id', array_keys($empresas))
                ->whereIn('anita_nro', $lote)
                ->whereIn('anita_tipo', array_keys($tipos))
                ->get(['id', 'empresa_id', 'fecha', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);
            foreach ($filas as $f) {
                $clave = (int) $f->empresa_id.'|'
                    .strtoupper(trim((string) $f->anita_tipo)).'|'
                    .strtoupper(trim((string) ($f->anita_letra ?? ''))).'|'
                    .(int) $f->anita_sucursal.'|'
                    .(int) $f->anita_nro;
                $porClave[$clave][] = $f;
            }
        }

        foreach ($sinEnlace as $cp => $dato) {
            $clave = $dato['empresa_id'].'|'.$dato['tipo'].'|'.$dato['letra'].'|'.$dato['sucursal'].'|'.$dato['numero'];
            $candidatos = $porClave[$clave] ?? [];
            if ($candidatos === []) {
                continue;
            }
            if (count($candidatos) > 1 && $dato['fechaiva'] !== '') {
                $enFecha = array_values(array_filter(
                    $candidatos,
                    fn ($a) => substr((string) ($a->fecha ?? ''), 0, 10) === $dato['fechaiva'],
                ));
                if ($enFecha !== []) {
                    $candidatos = $enFecha;
                }
            }
            if (count($candidatos) === 1) {
                $asientoPorCp[$cp] = (int) $candidatos[0]->id;
            }
        }

        return $asientoPorCp;
    }

    /**
     * Cuenta de la factura cuando no tiene COM: el Debe de su asiento que no es
     * proveedores (el de la ficha o, si no está enganchado, el del mismo
     * tipo, letra, sucursal y número), si no el debe del gasto cargado, y si
     * no la cuenta del concepto.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, list<object>>
     */
    private function cargarCuentasFactura(array $idsComprobante): array
    {
        $idsComprobante = array_values(array_filter(array_map('intval', $idsComprobante), fn (int $id) => $id > 0));
        if ($idsComprobante === []) {
            return [];
        }

        $asientoPorCp = $this->asientoIdPorFactura($idsComprobante);
        $movs = $this->cargarDebeNoProveedorPorAsiento(array_values(array_filter($asientoPorCp)));

        $salida = [];
        $sinAsiento = [];
        foreach ($idsComprobante as $cp) {
            $gastos = $movs[$asientoPorCp[$cp] ?? 0] ?? [];
            if ($gastos !== []) {
                $salida[$cp] = $gastos;
                continue;
            }
            $sinAsiento[] = $cp;
        }
        if ($sinAsiento === []) {
            return $salida;
        }

        $conDebe = [];
        foreach (array_chunk($sinAsiento, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor_debe_gasto as dg')
                ->join('cuentacontable as cc', 'cc.id', '=', 'dg.cuentacontable_id')
                ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
                ->whereIn('dg.comprobante_proveedor_id', $lote)
                ->where('dg.importe', '>', 0)
                ->get([
                    'dg.comprobante_proveedor_id',
                    'dg.importe',
                    'cc.codigo',
                    'cc.nombre',
                    'cc.conceptogasto_id',
                    'cg.nombre as concepto_nombre',
                ]);
            foreach ($filas as $f) {
                $cp = (int) $f->comprobante_proveedor_id;
                $conDebe[$cp] = true;
                $salida[$cp][] = (object) [
                    'codigo' => $f->codigo,
                    'nombre' => $f->nombre,
                    'conceptogasto_id' => (int) ($f->conceptogasto_id ?? 0),
                    'concepto_nombre' => $f->concepto_nombre,
                    'importe' => (float) $f->importe,
                ];
            }
        }

        $sinDebe = array_values(array_filter($sinAsiento, fn (int $id) => ! isset($conDebe[$id])));
        $pendientes = [];
        $cuentaIds = [];
        foreach (array_chunk($sinDebe, 800) as $lote) {
            if ($lote === []) {
                continue;
            }
            $filas = DB::table('comprobante_proveedor_concepto as cpc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpc.comprobante_proveedor_id')
                ->leftJoin('concepto_ivacompra as ci', 'ci.id', '=', 'cpc.concepto_ivacompra_id')
                ->leftJoin('concepto_ivacompra_empresa as cie', function ($join) {
                    $join->on('cie.concepto_ivacompra_id', '=', 'ci.id')
                        ->on('cie.empresa_id', '=', 'cp.empresa_id');
                })
                ->leftJoin('proveedor as pr', 'pr.id', '=', 'cp.proveedor_id')
                ->whereIn('cpc.comprobante_proveedor_id', $lote)
                ->where('cpc.monto', '>', 0)
                ->get([
                    'cpc.comprobante_proveedor_id',
                    'cpc.monto',
                    'cpc.cuentacontabledebe_id',
                    'cie.cuentacontabledebe_id as cuenta_empresa',
                    'ci.cuentacontabledebe_id as cuenta_maestro',
                    'ci.tipoconcepto',
                    'pr.cuentacontablecompra_id',
                ]);
            foreach ($filas as $f) {
                $cuentaId = (int) ($f->cuentacontabledebe_id ?: 0);
                if ($cuentaId <= 0) {
                    $cuentaId = (int) ($f->cuenta_empresa ?: 0);
                }
                if ($cuentaId <= 0) {
                    $cuentaId = (int) ($f->cuenta_maestro ?: 0);
                }
                if ($cuentaId <= 0 && in_array(strtoupper((string) ($f->tipoconcepto ?? '')), ['N', 'G', 'E'], true)) {
                    $cuentaId = (int) ($f->cuentacontablecompra_id ?: 0);
                }
                if ($cuentaId <= 0) {
                    $salida[(int) $f->comprobante_proveedor_id][] = (object) [
                        'codigo' => '',
                        'nombre' => '',
                        'conceptogasto_id' => 0,
                        'concepto_nombre' => '',
                        'importe' => (float) $f->monto,
                    ];
                    continue;
                }
                $pendientes[] = [(int) $f->comprobante_proveedor_id, $cuentaId, (float) $f->monto];
                $cuentaIds[$cuentaId] = true;
            }
        }

        $cuentas = [];
        foreach (array_chunk(array_keys($cuentaIds), 800) as $lote) {
            if ($lote === []) {
                continue;
            }
            $filas = DB::table('cuentacontable as cc')
                ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
                ->whereIn('cc.id', $lote)
                ->get(['cc.id', 'cc.codigo', 'cc.nombre', 'cc.conceptogasto_id', 'cg.nombre as concepto_nombre']);
            foreach ($filas as $f) {
                $cuentas[(int) $f->id] = $f;
            }
        }
        foreach ($pendientes as [$cp, $cuentaId, $monto]) {
            $cuenta = $cuentas[$cuentaId] ?? null;
            if ($cuenta === null) {
                continue;
            }
            $codigo = $this->soloDigitos($cuenta->codigo);
            if ($codigo <= 0 || $this->esProveedor($codigo)) {
                continue;
            }
            $salida[$cp][] = (object) [
                'codigo' => $cuenta->codigo,
                'nombre' => $cuenta->nombre,
                'conceptogasto_id' => (int) ($cuenta->conceptogasto_id ?? 0),
                'concepto_nombre' => $cuenta->concepto_nombre,
                'importe' => $monto,
            ];
        }

        return $salida;
    }

    /**
     * Signo del tipo de comprobante: las notas de crédito restan del pago.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, int>
     */
    private function signoPorComprobante(array $idsComprobante): array
    {
        $signos = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor as cp')
                ->leftJoin('tipotransaccion_compra as tc', 'tc.id', '=', 'cp.tipotransaccion_compra_id')
                ->whereIn('cp.id', $lote)
                ->get(['cp.id', 'tc.signo']);
            foreach ($filas as $f) {
                $signos[(int) $f->id] = (int) ($f->signo ?? 1) < 0 ? -1 : 1;
            }
        }

        return $signos;
    }

    /**
     * Cuentas de la nota de crédito: el Haber de su asiento que no es proveedores.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, list<object>>
     */
    private function cargarCuentasNotaCredito(array $idsComprobante): array
    {
        $idsComprobante = array_values(array_filter(array_map('intval', $idsComprobante), fn (int $id) => $id > 0));
        if ($idsComprobante === []) {
            return [];
        }

        $asientoPorCp = $this->asientoIdPorFactura($idsComprobante);
        $movs = $this->cargarDebeNoProveedorPorAsiento(array_values(array_filter($asientoPorCp)), true);

        $salida = [];
        foreach ($idsComprobante as $cp) {
            $lineas = $movs[$asientoPorCp[$cp] ?? 0] ?? [];
            if ($lineas !== []) {
                $salida[$cp] = $lineas;
            }
        }

        return $salida;
    }

    private function cargarDebeNoProveedorPorAsiento(array $asientoIds, bool $haber = false): array
    {
        $asientoIds = array_values(array_filter(array_map('intval', $asientoIds), fn (int $id) => $id > 0));
        if ($asientoIds === []) {
            return [];
        }

        $porAsiento = [];
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('asiento_movimiento as am')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
                ->whereIn('am.asiento_id', $lote)
                ->where('am.monto', $haber ? '<' : '>', 0)
                ->get([
                    'am.asiento_id',
                    'am.monto',
                    'cc.codigo',
                    'cc.nombre',
                    'cc.conceptogasto_id',
                    'cg.nombre as concepto_nombre',
                ]);
            foreach ($filas as $f) {
                $codigo = $this->soloDigitos($f->codigo);
                if ($codigo <= 0 || $this->esProveedor($codigo)) {
                    continue;
                }
                $asientoId = (int) $f->asiento_id;
                $conceptoId = (int) ($f->conceptogasto_id ?? 0);
                $clave = $codigo.'|'.$conceptoId;
                if (! isset($porAsiento[$asientoId][$clave])) {
                    $porAsiento[$asientoId][$clave] = (object) [
                        'codigo' => $f->codigo,
                        'nombre' => $f->nombre,
                        'conceptogasto_id' => $conceptoId,
                        'concepto_nombre' => $f->concepto_nombre,
                        'importe' => 0.0,
                    ];
                }
                $porAsiento[$asientoId][$clave]->importe += abs((float) $f->monto);
            }
        }

        $salida = [];
        foreach ($porAsiento as $asientoId => $filas) {
            $salida[$asientoId] = array_values($filas);
        }

        return $salida;
    }

    /**
     * Cuentas de gasto de la COM vinculada a cada factura: el Debe del asiento
     * de la recepción, sin la cuenta de proveedores.
     *
     * @param  list<int>  $idsComprobante
     * @return array<int, list<object>>
     */
    private function cargarGastosComPorComprobante(array $idsComprobante): array
    {
        if ($idsComprobante === []) {
            return [];
        }

        $asientosPorComprobante = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor_recepcion as cpr')
                ->join('recepcion_proveedor as rp', 'rp.id', '=', 'cpr.recepcion_proveedor_id')
                ->whereIn('cpr.comprobante_proveedor_id', $lote)
                ->where('rp.anita_tipo', 'COM')
                ->whereNotNull('rp.asiento_id')
                ->get(['cpr.comprobante_proveedor_id', 'rp.asiento_id']);
            foreach ($filas as $f) {
                $asientosPorComprobante[(int) $f->comprobante_proveedor_id][(int) $f->asiento_id] = true;
            }
        }
        if ($asientosPorComprobante === []) {
            return [];
        }

        $asientoIds = [];
        foreach ($asientosPorComprobante as $asientos) {
            foreach (array_keys($asientos) as $asientoId) {
                $asientoIds[$asientoId] = true;
            }
        }

        $movsPorAsiento = [];
        foreach (array_chunk(array_keys($asientoIds), 800) as $lote) {
            $filas = DB::table('asiento_movimiento as am')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
                ->whereIn('am.asiento_id', $lote)
                ->where('am.monto', '>', 0)
                ->get([
                    'am.asiento_id',
                    'am.monto',
                    'cc.codigo',
                    'cc.nombre',
                    'cc.conceptogasto_id',
                    'cg.nombre as concepto_nombre',
                ]);
            foreach ($filas as $f) {
                $codigo = $this->soloDigitos($f->codigo);
                if ($codigo <= 0 || $this->esProveedor($codigo)) {
                    continue;
                }
                $movsPorAsiento[(int) $f->asiento_id][] = $f;
            }
        }

        $porComprobante = [];
        foreach ($asientosPorComprobante as $cp => $asientos) {
            $acum = [];
            foreach (array_keys($asientos) as $asientoId) {
                foreach ($movsPorAsiento[$asientoId] ?? [] as $g) {
                    $codigo = $this->soloDigitos($g->codigo);
                    $conceptoId = (int) ($g->conceptogasto_id ?? 0);
                    $clave = $codigo.'|'.$conceptoId;
                    if (! isset($acum[$clave])) {
                        $acum[$clave] = (object) [
                            'codigo' => $g->codigo,
                            'nombre' => $g->nombre,
                            'conceptogasto_id' => $conceptoId,
                            'concepto_nombre' => $g->concepto_nombre,
                            'importe' => 0.0,
                        ];
                    }
                    $acum[$clave]->importe += (float) $g->monto;
                }
            }
            if ($acum !== []) {
                $porComprobante[(int) $cp] = array_values($acum);
            }
        }

        return $porComprobante;
    }

    /**
     * @param  list<int>  $asientoIds
     * @return array<int, float>
     */
    private function cargarRetencionesPorAsiento(int $empresaId, array $asientoIds): array
    {
        if ($empresaId <= 0 || $asientoIds === []) {
            return [];
        }

        $pagoPorAsiento = $this->pagoIdPorAsiento($empresaId, $asientoIds);
        if ($pagoPorAsiento === []) {
            return [];
        }

        $totalPorPago = [];
        foreach (array_chunk(array_values(array_unique($pagoPorAsiento)), 800) as $lote) {
            $filas = DB::table('pagoproveedor_retencion')
                ->whereIn('pagoproveedor_id', $lote)
                ->groupBy('pagoproveedor_id')
                ->selectRaw('pagoproveedor_id, SUM(importe) as total')
                ->get();
            foreach ($filas as $f) {
                $totalPorPago[(int) $f->pagoproveedor_id] = round((float) $f->total, 2);
            }
        }

        $asientosPorPago = [];
        foreach ($pagoPorAsiento as $asientoId => $pagoId) {
            $asientosPorPago[$pagoId][] = $asientoId;
        }
        $pesoCaja = $this->pesoCajaPorAsiento($asientoIds);

        $porAsiento = [];
        foreach ($asientosPorPago as $pagoId => $ids) {
            $total = $totalPorPago[$pagoId] ?? 0.0;
            if (abs($total) < 0.005) {
                continue;
            }
            $vivos = [];
            $suma = 0.0;
            foreach ($ids as $id) {
                $peso = $pesoCaja[$id] ?? 0.0;
                if ($peso <= 0) {
                    continue;
                }
                $vivos[] = $id;
                $suma += $peso;
            }
            if ($vivos === [] || $suma <= 0) {
                continue;
            }
            $acumulado = 0.0;
            $ultimo = $vivos[count($vivos) - 1];
            foreach ($vivos as $id) {
                if ($id === $ultimo) {
                    $monto = round($total - $acumulado, 2);
                } else {
                    $monto = round($total * (($pesoCaja[$id] ?? 0.0) / $suma), 2);
                    $acumulado = round($acumulado + $monto, 2);
                }
                $porAsiento[$id] = $monto;
            }
        }

        return $porAsiento;
    }

    /**
     * La orden de pago puede estar partida en dos asientos (banco y
     * retenciones) y el pago queda colgado de uno solo. Cada asiento se
     * engancha por su id o, si no, por tipo, letra, sucursal y número.
     *
     * @param  list<int>  $asientoIds
     * @return array<int, int> asiento_id => pagoproveedor_id
     */
    private function pagoIdPorAsiento(int $empresaId, array $asientoIds): array
    {
        $asientoIds = array_values(array_filter(array_map('intval', $asientoIds), fn (int $id) => $id > 0));
        if ($empresaId <= 0 || $asientoIds === []) {
            return [];
        }

        $identidades = [];
        $numeros = [];
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('asiento')
                ->whereIn('id', $lote)
                ->get(['id', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);
            foreach ($filas as $f) {
                $identidades[(int) $f->id] = $f;
                $nro = (int) ($f->anita_nro ?? 0);
                if ($nro > 0) {
                    $numeros[(string) $nro] = true;
                }
            }
        }

        $pagos = [];
        $camposPago = [
            'p.id',
            'p.asiento_id',
            'p.tipocomprobante',
            'p.letra',
            'p.sucursal',
            'p.numerotransaccion',
        ];
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('pagoproveedor as p')
                ->where('p.empresa_id', $empresaId)
                ->whereIn('p.asiento_id', $lote)
                ->get($camposPago);
            foreach ($filas as $f) {
                $pagos[(int) $f->id] = $f;
            }
        }
        foreach (array_chunk(array_keys($numeros), 800) as $lote) {
            $filas = DB::table('pagoproveedor as p')
                ->where('p.empresa_id', $empresaId)
                ->whereIn('p.numerotransaccion', $lote)
                ->get($camposPago);
            foreach ($filas as $f) {
                $pagos[(int) $f->id] = $f;
            }
        }

        $porAsientoPago = [];
        $porClave = [];
        $porClaveSinLetra = [];
        foreach ($pagos as $pago) {
            if ((int) ($pago->asiento_id ?? 0) > 0) {
                $porAsientoPago[(int) $pago->asiento_id] = $pago;
            }
            $clave = $this->claveOrden($pago->tipocomprobante, $pago->letra, $pago->sucursal, $pago->numerotransaccion);
            if ($clave === '') {
                continue;
            }
            $porClave[$clave] = $pago;
            $sinLetra = $this->claveOrden($pago->tipocomprobante, '', $pago->sucursal, $pago->numerotransaccion);
            $porClaveSinLetra[$sinLetra][] = $pago;
        }

        $salida = [];
        foreach ($identidades as $asientoId => $f) {
            if (isset($porAsientoPago[$asientoId])) {
                $salida[$asientoId] = (int) $porAsientoPago[$asientoId]->id;
                continue;
            }
            $clave = $this->claveOrden($f->anita_tipo, $f->anita_letra, $f->anita_sucursal, $f->anita_nro);
            if ($clave !== '' && isset($porClave[$clave])) {
                $salida[$asientoId] = (int) $porClave[$clave]->id;
                continue;
            }
            if (strtoupper(trim((string) ($f->anita_tipo ?? ''))) === 'AOP') {
                $claveOpp = $this->claveOrden('OPP', $f->anita_letra, $f->anita_sucursal, $f->anita_nro);
                if ($claveOpp !== '' && isset($porClave[$claveOpp])) {
                    $salida[$asientoId] = (int) $porClave[$claveOpp]->id;
                    continue;
                }
            }
            if (trim((string) ($f->anita_letra ?? '')) === '' && $clave !== '') {
                $candidatos = $porClaveSinLetra[$clave] ?? [];
                if (count($candidatos) === 1) {
                    $salida[$asientoId] = (int) $candidatos[0]->id;
                }
            }
        }

        return $salida;
    }

    /**
     * @param  array<int, int>  $pagoPorAsiento
     * @return list<object>
     */
    private function aplicacionesDePagos(array $pagoPorAsiento): array
    {
        if ($pagoPorAsiento === []) {
            return [];
        }

        $porPago = [];
        foreach (array_chunk(array_values(array_unique($pagoPorAsiento)), 800) as $lote) {
            $filas = DB::table('pagoproveedor as p')
                ->join('pagoproveedor_comprobante as pc', 'pc.pagoproveedor_id', '=', 'p.id')
                ->join('proveedor_cuentacorriente as pcc', 'pcc.id', '=', 'pc.proveedor_cuentacorriente_id')
                ->leftJoin('proveedor as pr', 'pr.id', '=', 'p.proveedor_id')
                ->whereIn('p.id', $lote)
                ->where('pc.montoaplicado', '<>', 0)
                ->whereNotNull('pcc.comprobante_proveedor_id')
                ->get([
                    'p.id as pago_id',
                    'pcc.comprobante_proveedor_id',
                    'pc.montoaplicado',
                    'pr.nombre as proveedor_nombre',
                ]);
            foreach ($filas as $f) {
                $f->montoaplicado = abs((float) $f->montoaplicado);
                $porPago[(int) $f->pago_id][] = $f;
            }
        }

        $pagoIds = array_values(array_unique(array_map('intval', $pagoPorAsiento)));
        foreach ($this->aplicacionesDesdeImpresion($pagoIds) as $pagoId => $filas) {
            $ya = [];
            foreach ($porPago[$pagoId] ?? [] as $ap) {
                $ya[(int) $ap->comprobante_proveedor_id] = true;
            }
            foreach ($filas as $ap) {
                if (isset($ya[(int) $ap->comprobante_proveedor_id])) {
                    continue;
                }
                $porPago[$pagoId][] = $ap;
                $ya[(int) $ap->comprobante_proveedor_id] = true;
            }
        }

        $aplicaciones = [];
        foreach ($pagoPorAsiento as $asientoId => $pagoId) {
            foreach ($porPago[$pagoId] ?? [] as $ap) {
                $copia = clone $ap;
                $copia->asiento_id = $asientoId;
                $aplicaciones[] = $copia;
            }
        }

        return $aplicaciones;
    }

    /**
     * Pagos importados sin cuenta corriente: la factura está en la impresión.
     *
     * @param  list<int>  $pagoIds
     * @return array<int, list<object>>
     */
    private function aplicacionesDesdeImpresion(array $pagoIds): array
    {
        $pagoIds = array_values(array_filter(array_map('intval', $pagoIds), fn (int $id) => $id > 0));
        if ($pagoIds === []) {
            return [];
        }

        $busqueda = [];
        foreach (array_chunk($pagoIds, 400) as $lote) {
            $filas = DB::table('pagoproveedor as p')
                ->leftJoin('proveedor as pr', 'pr.id', '=', 'p.proveedor_id')
                ->whereIn('p.id', $lote)
                ->whereNotNull('p.anita_impresion_json')
                ->get([
                    'p.id',
                    'p.empresa_id',
                    'p.proveedor_id',
                    'p.anita_impresion_json',
                    'pr.nombre as proveedor_nombre',
                ]);
            foreach ($filas as $pago) {
                $json = json_decode((string) $pago->anita_impresion_json, true);
                if (! is_array($json)) {
                    continue;
                }
                foreach ($json['aplicaciones'] ?? [] as $ap) {
                    if (! is_array($ap)) {
                        continue;
                    }
                    $monto = (float) ($ap['monto_aplicado'] ?? $ap['monto'] ?? 0);
                    if ($monto <= 0) {
                        continue;
                    }
                    $parsed = $this->parsearNumeroAplicacion((string) ($ap['numero'] ?? ''));
                    $busqueda[] = [
                        'pago_id' => (int) $pago->id,
                        'empresa_id' => (int) $pago->empresa_id,
                        'proveedor_id' => (int) $pago->proveedor_id,
                        'proveedor_nombre' => trim((string) ($pago->proveedor_nombre ?? '')),
                        'nro_int' => (int) ($ap['nro_int'] ?? 0),
                        'letra' => $parsed['letra'] ?? '',
                        'sucursal' => $parsed['sucursal'] ?? 0,
                        'numero' => $parsed['numero'] ?? 0,
                        'monto' => $monto,
                    ];
                }
            }
        }
        if ($busqueda === []) {
            return [];
        }

        $porInterno = [];
        $internos = array_values(array_unique(array_filter(array_map(
            fn (array $b) => $b['nro_int'],
            $busqueda,
        ))));
        foreach (array_chunk($internos, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor')
                ->whereIn('anita_nro_interno', $lote)
                ->get(['id', 'empresa_id', 'proveedor_id', 'anita_nro_interno']);
            foreach ($filas as $f) {
                $clave = (int) $f->empresa_id.'|'.(int) $f->proveedor_id.'|'.(int) $f->anita_nro_interno;
                $porInterno[$clave] = (int) $f->id;
            }
        }

        $porNumero = [];
        $numeros = array_values(array_unique(array_filter(array_map(
            fn (array $b) => $b['numero'],
            $busqueda,
        ))));
        foreach (array_chunk($numeros, 800) as $lote) {
            $filas = DB::table('comprobante_proveedor')
                ->whereIn('numerocomprobante', $lote)
                ->get(['id', 'empresa_id', 'proveedor_id', 'letra', 'sucursal', 'numerocomprobante']);
            foreach ($filas as $f) {
                $clave = (int) $f->empresa_id.'|'.(int) $f->proveedor_id.'|'
                    .strtoupper(trim((string) $f->letra)).'|'.(int) $f->sucursal.'|'.(int) $f->numerocomprobante;
                $porNumero[$clave] = (int) $f->id;
            }
        }

        $salida = [];
        foreach ($busqueda as $b) {
            $cp = $porInterno[$b['empresa_id'].'|'.$b['proveedor_id'].'|'.$b['nro_int']] ?? 0;
            if ($cp <= 0 && $b['numero'] > 0) {
                $cp = $porNumero[$b['empresa_id'].'|'.$b['proveedor_id'].'|'.$b['letra'].'|'.$b['sucursal'].'|'.$b['numero']] ?? 0;
            }
            if ($cp <= 0) {
                continue;
            }
            $salida[$b['pago_id']][] = (object) [
                'comprobante_proveedor_id' => $cp,
                'montoaplicado' => $b['monto'],
                'proveedor_nombre' => $b['proveedor_nombre'],
            ];
        }

        return $salida;
    }

    /**
     * @return array{letra: string, sucursal: int, numero: int}|null
     */
    private function parsearNumeroAplicacion(string $numero): ?array
    {
        $numero = trim($numero);
        if (! preg_match('/^[A-Z0-9]+\s+([A-Z])(\d+)-(\d+)$/i', $numero, $m)) {
            return null;
        }

        return [
            'letra' => strtoupper($m[1]),
            'sucursal' => (int) $m[2],
            'numero' => (int) $m[3],
        ];
    }

    /**
     * @param  list<int>  $asientoIds
     * @return array<int, float>
     */
    private function pesoCajaPorAsiento(array $asientoIds): array
    {
        $limite = (int) config('contable.mayor_concepto.limite_caja_banco', 112010008);
        $pesos = [];
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('asiento_movimiento as am')
                ->join('cuentacontable as cc', 'cc.id', '=', 'am.cuentacontable_id')
                ->whereIn('am.asiento_id', $lote)
                ->get(['am.asiento_id', 'am.monto', 'cc.codigo']);
            foreach ($filas as $f) {
                $codigo = $this->soloDigitos($f->codigo);
                if ($codigo <= 0 || $codigo > $limite) {
                    continue;
                }
                $asientoId = (int) $f->asiento_id;
                $pesos[$asientoId] = ($pesos[$asientoId] ?? 0.0) + abs((float) $f->monto);
            }
        }

        return $pesos;
    }

    private function claveOrden(mixed $tipo, mixed $letra, mixed $sucursal, mixed $nro): string
    {
        $numero = (int) preg_replace('/\D/', '', (string) ($nro ?? ''));
        $tipo = strtoupper(trim((string) ($tipo ?? '')));
        if ($numero <= 0 || $tipo === '') {
            return '';
        }
        $letra = strtoupper(trim((string) ($letra ?? '')));

        return $tipo.'|'.$letra.'|'.(int) $sucursal.'|'.$numero;
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array<string, mixed>>
     */
    private function agruparPorConcepto(array $lineas): array
    {
        $porConcepto = [];
        foreach ($lineas as $linea) {
            $cpto = (int) $linea['concepto_id'];
            $cuenta = (int) $linea['cuenta'];
            if (! isset($porConcepto[$cpto])) {
                $porConcepto[$cpto] = [
                    'concepto_id' => $cpto,
                    'concepto_nombre' => (string) $linea['concepto_nombre'],
                    'cuentas' => [],
                ];
            }
            if (! isset($porConcepto[$cpto]['cuentas'][$cuenta])) {
                $porConcepto[$cpto]['cuentas'][$cuenta] = [
                    'cuenta' => $cuenta,
                    'cuenta_codigo' => (string) $linea['cuenta_codigo'],
                    'cuenta_nombre' => (string) $linea['cuenta_nombre'],
                    'lineas' => [],
                    'total_debe' => 0.0,
                    'total_haber' => 0.0,
                    'total_retenciones' => 0.0,
                ];
            }
            $porConcepto[$cpto]['cuentas'][$cuenta]['lineas'][] = $linea;
            $porConcepto[$cpto]['cuentas'][$cuenta]['total_debe'] = round(
                $porConcepto[$cpto]['cuentas'][$cuenta]['total_debe'] + (float) $linea['debe'],
                2,
            );
            $porConcepto[$cpto]['cuentas'][$cuenta]['total_haber'] = round(
                $porConcepto[$cpto]['cuentas'][$cuenta]['total_haber'] + (float) $linea['haber'],
                2,
            );
            $porConcepto[$cpto]['cuentas'][$cuenta]['total_retenciones'] = round(
                $porConcepto[$cpto]['cuentas'][$cuenta]['total_retenciones'] + (float) ($linea['retenciones'] ?? 0),
                2,
            );
        }

        $secciones = [];
        ksort($porConcepto, SORT_NUMERIC);
        foreach ($porConcepto as $bloque) {
            $cuentas = array_values($bloque['cuentas']);
            usort($cuentas, fn ($a, $b) => $a['cuenta'] <=> $b['cuenta']);
            $bloque['cuentas'] = $cuentas;
            $secciones[] = $bloque;
        }

        return $secciones;
    }

    private function esProveedor(int $cuenta): bool
    {
        return $cuenta >= self::PROVEEDOR_DESDE && $cuenta < self::PROVEEDOR_HASTA;
    }

    private function formatearCodigoCuenta(int $codigo): string
    {
        $s = str_pad((string) $codigo, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6, 3);
    }

    private function formatearComprobante(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        if ($nro <= 0 && $tipo === '') {
            return '';
        }
        $letra = $letra !== '' ? $letra : ' ';

        return trim($tipo.' '.$letra.str_pad((string) $sucursal, 4, '0', STR_PAD_LEFT).'-'.str_pad((string) $nro, 8, '0', STR_PAD_LEFT));
    }

    private function fmtFecha(int $ymd): string
    {
        if ($ymd <= 0) {
            return '';
        }
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 6, 2).'/'.substr($txt, 4, 2).'/'.substr($txt, 0, 4);
    }

    private function fechaAYmd(mixed $fecha): int
    {
        $txt = trim((string) ($fecha ?? ''));
        if ($txt === '') {
            return 0;
        }

        return (int) preg_replace('/\D/', '', substr($txt, 0, 10));
    }

    private function ymdAFecha(int $ymd): string
    {
        $txt = str_pad((string) $ymd, 8, '0', STR_PAD_LEFT);

        return substr($txt, 0, 4).'-'.substr($txt, 4, 2).'-'.substr($txt, 6, 2);
    }

    private function soloDigitos(mixed $valor): int
    {
        return (int) preg_replace('/\D/', '', (string) ($valor ?? ''));
    }
}
