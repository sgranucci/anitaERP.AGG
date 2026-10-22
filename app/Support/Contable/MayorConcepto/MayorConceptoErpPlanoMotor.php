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
 * reparte entre las cuentas de gasto del comprobante. Si no, queda en la
 * contrapartida con el concepto de esa cuenta.
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

        $gastos = $this->cargarGastosPorAsiento($empresaId, array_keys($conProveedor));
        $clasificado = $this->clasificar($asientos, $gastos, $limite);
        $lineas = $clasificado['lineas'];
        $analitico = $clasificado['analitico'];
        $auditoria = $this->auditar($analitico, $lineas);

        $debe = 0.0;
        $haber = 0.0;
        foreach ($lineas as $ln) {
            $debe += (float) $ln['debe'];
            $haber += (float) $ln['haber'];
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
     * @return array{lineas: list<array<string, mixed>>, analitico: array<int, array<string, mixed>>}
     */
    public function clasificar(array $asientos, array $gastosPorAsiento, int $limite): array
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
                        $partesGasto = $gastos !== []
                            ? $this->repartir($monto, $gastos, 'peso')
                            : [['peso_linea' => $monto, 'item' => $contra]];
                        foreach ($partesGasto as $parte) {
                            $lineas[] = $this->linea($asiento, $pata, $parte['item'], (float) $parte['peso_linea'], $esDebe, $gastos !== []);
                        }
                        continue;
                    }

                    $lineas[] = $this->linea($asiento, $pata, $contra, $monto, $esDebe, false);
                }
            }

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
            'nota' => 'Solo entran las cuentas menores o iguales al límite de caja/banco. El importe de esa pata no se cambia: se reclasifica al concepto de la contrapartida, o al gasto del comprobante si la contrapartida es proveedores.',
        ];
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
            'disp_debe' => $debe,
            'disp_haber' => $haber,
            'origen' => $desdeGasto ? 'Gasto del comprobante de compra' : 'Contrapartida de la pata hasta el límite',
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
     * Pesos de gasto por asiento: lo aplicado a cada comprobante, abierto por la
     * cuenta de debe_gasto (o, si no hay, por el concepto del comprobante).
     *
     * @param  list<int>  $asientoIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function cargarGastosPorAsiento(int $empresaId, array $asientoIds): array
    {
        if ($empresaId <= 0 || $asientoIds === []) {
            return [];
        }

        $aplicaciones = [];
        foreach (array_chunk($asientoIds, 800) as $lote) {
            $filas = DB::table('pagoproveedor as p')
                ->join('pagoproveedor_comprobante as pc', 'pc.pagoproveedor_id', '=', 'p.id')
                ->join('proveedor_cuentacorriente as pcc', 'pcc.id', '=', 'pc.proveedor_cuentacorriente_id')
                ->leftJoin('proveedor as pr', 'pr.id', '=', 'p.proveedor_id')
                ->where('p.empresa_id', $empresaId)
                ->whereIn('p.asiento_id', $lote)
                ->where('pc.montoaplicado', '>', 0)
                ->whereNotNull('pcc.comprobante_proveedor_id')
                ->get([
                    'p.asiento_id',
                    'pcc.comprobante_proveedor_id',
                    'pc.montoaplicado',
                    'pr.nombre as proveedor_nombre',
                ]);
            foreach ($filas as $f) {
                $aplicaciones[] = $f;
            }
        }

        if ($aplicaciones === []) {
            return [];
        }

        $comprobantes = [];
        foreach ($aplicaciones as $ap) {
            $comprobantes[(int) $ap->comprobante_proveedor_id] = true;
        }
        $idsComprobante = array_keys($comprobantes);

        $gastosPorComprobante = [];
        $conDebeGasto = [];
        foreach (array_chunk($idsComprobante, 800) as $lote) {
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
                $conDebeGasto[$cp] = true;
                $gastosPorComprobante[$cp][] = $f;
            }
        }

        $sinDebe = array_values(array_filter($idsComprobante, fn (int $id) => ! isset($conDebeGasto[$id])));
        foreach (array_chunk($sinDebe, 800) as $lote) {
            if ($lote === []) {
                continue;
            }
            $filas = DB::table('comprobante_proveedor_concepto as cpc')
                ->join('cuentacontable as cc', 'cc.id', '=', 'cpc.cuentacontabledebe_id')
                ->leftJoin('conceptogasto as cg', 'cg.id', '=', 'cc.conceptogasto_id')
                ->whereIn('cpc.comprobante_proveedor_id', $lote)
                ->where('cpc.monto', '>', 0)
                ->get([
                    'cpc.comprobante_proveedor_id',
                    'cpc.monto as importe',
                    'cc.codigo',
                    'cc.nombre',
                    'cc.conceptogasto_id',
                    'cg.nombre as concepto_nombre',
                ]);
            foreach ($filas as $f) {
                $gastosPorComprobante[(int) $f->comprobante_proveedor_id][] = $f;
            }
        }

        $porAsiento = [];
        foreach ($aplicaciones as $ap) {
            $cp = (int) $ap->comprobante_proveedor_id;
            $lineasGasto = $gastosPorComprobante[$cp] ?? [];
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
            $aplicado = (float) $ap->montoaplicado;
            $asientoId = (int) $ap->asiento_id;
            foreach ($lineasGasto as $g) {
                $codigo = $this->soloDigitos($g->codigo);
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
                    ];
                }
                $porAsiento[$asientoId][$clave]['peso'] += $peso;
            }
        }

        $salida = [];
        foreach ($porAsiento as $asientoId => $filas) {
            $salida[$asientoId] = array_values($filas);
        }

        return $salida;
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
