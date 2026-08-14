<?php

namespace App\Support\Contable\Efe;

use App\Models\Ventas\Puntoventa;
use Carbon\Carbon;

/**
 * Posición financiera mensual (solapa «pos fin …») — port de l-posfinanc.c.
 *
 * Fuentes Anita (vía bridge, generadas desde el menú EFE de AnitaERP):
 * saldoposf, rendbingo/concbingo/rendpremio, rendmaquina, rendgastro, rendvalor,
 * valormae, remesas/rememae, apgasto/rendmapgasto.
 *
 * Clasificación gastro/estac por puntoventa ERP (nombre / códigos).
 * EGA, SHOW y Kiosco: omitidos (no viven en AnitaERP).
 */
class EfePosicionFinancieraSupport
{
    private const TURNOS_MAQUINA_EXCLUIDOS = ['M', 'T', 'N'];

    private const FECHA_CORTE_TURNO_MAQUINA = 20100300;

    /** Destinos rememae (rememae.def). */
    private const REMEM_MACRO = '1';

    private const REMEM_FRANCES = '2';

    private const REMEM_PROVINCIA = '3';

    private const REMEM_MACO = '4';

    private const REMEM_CAJASEG = '5';

    private const REMEM_PAGOFACIL = '6';

    /** Tipos valormae (efectivo ME se convierte con cotización). */
    private const VALM_EFE_PESOS = '0';

    private const VALM_EFE_DOLAR = '1';

    private const VALM_EFE_EURO = '2';

    private const VALM_EFE_CRIPTO = '8';

    private const BLOQUE_GASTRO = 'gastro';

    private const BLOQUE_ESTAC = 'estac';

    private const BLOQUE_MAQUINAS = 'maquinas';

    private const BLOQUE_OMITIR = 'omitir';

    /** @var array<int, string> */
    private array $clasificacionSucursalCache = [];

    public function __construct(
        private readonly EfeAnitaBridgeReader $bridgeReader = new EfeAnitaBridgeReader(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   totales_por_etiqueta: array<string, float>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   gastronomia: array<string, float>,
     *   estacionamiento: array<string, float>,
     *   maquinas: array<string, float>,
     *   apertura_medios: array<string, float>,
     *   egresos: array<string, float>,
     *   errores_bridge: list<string>
     * }
     */
    public function generar(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);

        if ($empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return $this->vacio(['Parámetros de período incompletos']);
        }

        $inicioMes = Carbon::createFromDate($anio, $mes, 1);
        $finMes = $inicioMes->copy()->endOfMonth();
        $fechaSaldoInicial = (int) $inicioMes->copy()->subDay()->format('Ymd');
        $fechaDesde = (int) $inicioMes->format('Ymd');
        $fechaHasta = (int) $finMes->format('Ymd');

        $errores = [];
        /** @var list<array{etiqueta: string, valor: float}> $filasOrdenadas */
        $filasOrdenadas = [];

        $saldos = $this->bridgeReader->listarSaldoposf($empresaId, $fechaSaldoInicial, $fechaHasta);
        $saldoInicial = $this->saldoEnFecha($saldos, $fechaSaldoInicial);
        $saldoFinal = $this->saldoEnFecha($saldos, $fechaHasta);
        if ($saldoInicial !== null) {
            $this->pushFila($filasOrdenadas, 'Saldo inicial', $saldoInicial);
        }

        $bingo = $this->agregarRendbingo(
            $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta),
        );
        $premios = $this->agregarPremiosBingo(
            $bingo,
            $this->bridgeReader->listarConcbingo(),
            $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta),
            $this->bridgeReader->listarRendpremio($fechaDesde, $fechaHasta),
        );
        $this->pushMapa($filasOrdenadas, $bingo);
        $this->pushMapa($filasOrdenadas, $premios);

        $valormae = $this->indexarValormae($this->bridgeReader->listarValormae($empresaId));
        $rendvalor = $this->bridgeReader->listarRendvalor($fechaDesde, $fechaHasta);
        $valoresPorOper = $this->indexarRendvalorPorOper($rendvalor);

        $cabGastro = $this->bridgeReader->listarRendgastro($empresaId, $fechaDesde, $fechaHasta);
        $gastronomia = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $empresaId,
            self::BLOQUE_GASTRO,
            'GASTRONOMIA Z',
            'Total Gastronomia',
        );
        $estacionamiento = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $empresaId,
            self::BLOQUE_ESTAC,
            'ESTACIONAMIENTO Z',
            'Total Estacionamiento',
            redondeoNegado: true,
        );
        $this->pushMapa($filasOrdenadas, $gastronomia);
        $this->pushMapa($filasOrdenadas, $estacionamiento);
        // EGA / SHOW: no viven en AnitaERP — se publican en 0 (incl. sublíneas de la plantilla).
        foreach (['EGA', 'SHOW'] as $bloqueOmitido) {
            $this->pushFila($filasOrdenadas, $bloqueOmitido.' Z', 0.0);
            foreach (['EFECTIVO', 'TK.CANJE SHOW', 'MERCADO PAGO', 'PASSLINE'] as $medioOmitido) {
                // La plantilla Anita repite 3 veces cada medio.
                for ($i = 0; $i < 3; $i++) {
                    $this->pushFila($filasOrdenadas, $medioOmitido, 0.0);
                }
            }
            $this->pushFila($filasOrdenadas, 'Notas de credito', 0.0);
            $this->pushFila($filasOrdenadas, 'Diferencia abandono de pago', 0.0);
            $this->pushFila($filasOrdenadas, 'Redondeo', 0.0);
            $this->pushFila($filasOrdenadas, 'Diferencia de caja', 0.0);
            $this->pushFila($filasOrdenadas, 'Total '.$bloqueOmitido, 0.0);
        }

        $filasMaquina = $this->bridgeReader->listarRendmaquina($empresaId, $fechaDesde, $fechaHasta);
        $opsMaquina = [];
        foreach ($filasMaquina as $fila) {
            if ($this->incluirRendmaquina($fila)) {
                $opsMaquina[(int) ($fila->rendm_nro_oper ?? 0)] = true;
            }
        }
        $apgastoDesc = $this->indexarApgasto($this->bridgeReader->listarApgasto());
        $gastosPorOper = $this->cargarGastosMaquina(array_keys($opsMaquina));

        $maquinasBase = $this->agregarRendmaquina($filasMaquina);
        $maquinasMedios = $this->agregarMediosPorOperaciones(
            array_keys($opsMaquina),
            $valoresPorOper,
            $valormae,
        );
        $maquinasGastos = $this->agregarGastosMaquina(array_keys($opsMaquina), $gastosPorOper, $apgastoDesc);
        // Orden Anita: ventas, caja, medios valormae, vales, apgasto, variación/dif/tránsito, total.
        $this->pushFila($filasOrdenadas, 'MAQUINAS VENTAS', (float) ($maquinasBase['MAQUINAS VENTAS'] ?? 0));
        $this->pushFila($filasOrdenadas, 'MAQUINAS CAJA', (float) ($maquinasBase['MAQUINAS CAJA'] ?? 0));
        $this->pushMapa($filasOrdenadas, $maquinasMedios);
        $this->pushFila($filasOrdenadas, 'Vales fondo fijo', (float) ($maquinasBase['Vales fondo fijo'] ?? 0));
        $this->pushFila($filasOrdenadas, 'Vales administracion', (float) ($maquinasBase['Vales administracion'] ?? 0));
        $this->pushMapa($filasOrdenadas, $maquinasGastos);
        $this->pushFila($filasOrdenadas, 'Variacion de FF', (float) ($maquinasBase['Variacion de FF'] ?? 0));
        $this->pushFila($filasOrdenadas, 'Diferencia de caja', (float) ($maquinasBase['Diferencia de caja'] ?? 0));
        $this->pushFila($filasOrdenadas, 'Caja en transito', (float) ($maquinasBase['Caja en transito'] ?? 0));
        // Pago 24 omitido: columna Anita no disponible vía bridge.
        $maquinas = array_merge($maquinasBase, $maquinasMedios, $maquinasGastos);
        $descsMedio = [];
        foreach ($valormae as $meta) {
            $descsMedio[$meta['desc']] = true;
        }
        $maquinas['Total maquinas'] = $this->totalMaquinas($maquinas, $descsMedio);
        $this->pushFila($filasOrdenadas, 'Total maquinas', $maquinas['Total maquinas']);

        $apertura = $this->agregarAperturaMedios(
            $valoresPorOper,
            $valormae,
            $cabGastro,
            $opsMaquina,
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $gastronomia,
        );
        $this->pushMapa($filasOrdenadas, $apertura);

        $egresos = $this->agregarEgresos(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $valormae,
            $this->acumularAbiertosNoEfectivo($valoresPorOper, $valormae, $cabGastro, $opsMaquina, $empresaId),
        );
        $this->pushMapa($filasOrdenadas, $egresos);

        if ($saldoFinal !== null) {
            $this->pushFila($filasOrdenadas, 'Saldo final', $saldoFinal);
        }

        $totales = $this->mapaUltimaEtiqueta($filasOrdenadas);

        return [
            'totales_por_etiqueta' => $totales,
            'filas_ordenadas' => $filasOrdenadas,
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $saldoFinal,
            'bingo' => $bingo,
            'premios_bingo' => $premios,
            'gastronomia' => $gastronomia,
            'estacionamiento' => $estacionamiento,
            'maquinas' => $maquinas,
            'apertura_medios' => $apertura,
            'egresos' => $egresos,
            'errores_bridge' => $errores,
        ];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float}>  $filas
     */
    private function pushFila(array &$filas, string $etiqueta, float $valor): void
    {
        $filas[] = ['etiqueta' => $etiqueta, 'valor' => round($valor, 2)];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float}>  $filas
     * @param  array<string, float>  $mapa
     */
    private function pushMapa(array &$filas, array $mapa): void
    {
        foreach ($mapa as $etiqueta => $valor) {
            $this->pushFila($filas, $etiqueta, (float) $valor);
        }
    }

    /**
     * @param  list<array{etiqueta: string, valor: float}>  $filas
     * @return array<string, float>
     */
    private function mapaUltimaEtiqueta(array $filas): array
    {
        $totales = [];
        foreach ($filas as $fila) {
            $totales[$fila['etiqueta']] = $fila['valor'];
        }

        return $totales;
    }

    /**
     * @param  list<object>  $filas
     */
    private function saldoEnFecha(array $filas, int $fecha): ?float
    {
        foreach ($filas as $fila) {
            if ((int) ($fila->salpf_fecha ?? 0) === $fecha) {
                return round((float) ($fila->salpf_saldo ?? 0), 2);
            }
        }

        return null;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, float>
     */
    private function agregarRendbingo(array $filas): array
    {
        $totales = [
            'VENTA BINGO' => 0.0,
            'SOBRANTES' => 0.0,
            'VALES' => 0.0,
            'REDONDEO' => 0.0,
        ];

        foreach ($filas as $fila) {
            $totales['VENTA BINGO'] += (float) ($fila->rendb_total_carton ?? 0);
            $totales['SOBRANTES'] += (float) ($fila->rendb_sobrante ?? 0);
            $totales['VALES'] += (float) ($fila->rendb_vales ?? 0);
            $totales['REDONDEO'] += (float) ($fila->rendb_redondeo ?? 0);
        }

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    /**
     * @param  array<string, float>  $bingoBase
     * @param  list<object>  $concbingo
     * @param  list<object>  $rendbingo
     * @param  list<object>  $rendpremio
     * @return array<string, float>
     */
    private function agregarPremiosBingo(
        array $bingoBase,
        array $concbingo,
        array $rendbingo,
        array $rendpremio,
    ): array {
        $totales = [];
        $ventaBingo = (float) ($bingoBase['VENTA BINGO'] ?? 0);

        $mapConcb = [];
        foreach ($concbingo as $row) {
            $mapConcb[(int) ($row->concb_concepto ?? 0)] = $row;
        }

        foreach ($concbingo as $row) {
            $tipo = trim((string) ($row->concb_tipo_conc ?? ''));
            $desc = trim((string) ($row->concb_desc ?? ''));
            if ($desc === '' || ! in_array($tipo, ['0', '1'], true)) {
                continue;
            }

            $pct = (float) ($row->concb_porcentaje ?? 0);
            if ($ventaBingo > 0 && $pct > 0) {
                $totales[$desc] = round(-$ventaBingo * ($pct / 100), 2);
            }
        }

        $opsRendb = [];
        foreach ($rendbingo as $row) {
            $opsRendb[(int) ($row->rendb_nro_oper ?? 0).'|'.trim((string) ($row->rendb_tipo_oper ?? ''))] = true;
        }

        foreach ($rendpremio as $row) {
            $claveOp = (int) ($row->rendp_nro_oper ?? 0).'|'.trim((string) ($row->rendp_tipo_oper ?? ''));
            if (! isset($opsRendb[$claveOp])) {
                continue;
            }

            $conceptoId = (int) ($row->rendp_concepto ?? 0);
            if (! isset($mapConcb[$conceptoId])) {
                continue;
            }

            $concb = $mapConcb[$conceptoId];
            $tipo = trim((string) ($concb->concb_tipo_conc ?? ''));
            if ($tipo === '0') {
                continue;
            }

            $desc = trim((string) ($concb->concb_desc ?? ''));
            if ($desc === '') {
                continue;
            }

            $usaReal = in_array($tipo, ['3', '4', '5'], true);
            $importe = $usaReal
                ? (float) ($row->rendp_real ?? 0)
                : (float) ($row->rendp_pagado ?? 0);

            if ($importe <= 0) {
                continue;
            }

            $totales[$desc] = round(($totales[$desc] ?? 0) - $importe, 2);
        }

        return $totales;
    }

    /**
     * @param  list<object>  $cabeceras
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, float>
     */
    private function agregarBloqueGastroEstac(
        array $cabeceras,
        array $valoresPorOper,
        array $valormae,
        int $empresaId,
        string $bloque,
        string $etiquetaZ,
        string $etiquetaTotal,
        bool $redondeoNegado = false,
    ): array {
        $z = 0.0;
        $nc = 0.0;
        $abPago = 0.0;
        $redondeo = 0.0;
        $difCaja = 0.0;
        /** @var array<int, float> $mediosPorCodigo */
        $mediosPorCodigo = [];

        foreach ($cabeceras as $fila) {
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($this->clasificarSucursal($empresaId, $sucursal) !== $bloque) {
                continue;
            }

            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            $z += (float) ($fila->rendg_total_z ?? 0);
            $nc += (float) ($fila->rendg_tot_nc ?? 0);
            $abPago += (float) ($fila->rendg_ab_pago ?? 0);
            $red = (float) ($fila->rendg_tot_redondeo ?? 0);
            $redondeo += $redondeoNegado ? -$red : $red;
            $difCaja += -1 * (float) ($fila->rendg_dif_caja ?? 0);

            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $mediosPorCodigo[$codigo] = ($mediosPorCodigo[$codigo] ?? 0)
                    + $this->importeValorPesos($valor, $valormae[$codigo]['tipo']);
            }
        }

        // Orden Anita: Z → valormae → NC / abandono / redondeo / dif caja → Total.
        $totales = [$etiquetaZ => round($z, 2)];
        $totalSinZ = 0.0;
        foreach ($valormae as $codigo => $meta) {
            $importe = round($mediosPorCodigo[$codigo] ?? 0, 2);
            $totales[$meta['desc']] = $importe;
            $totalSinZ += $importe;
        }
        $totales['Notas de credito'] = round($nc, 2);
        $totales['Diferencia abandono de pago'] = round($abPago, 2);
        $totales['Redondeo'] = round($redondeo, 2);
        $totales['Diferencia de caja'] = round($difCaja, 2);
        $totalSinZ += $nc + $abPago + $redondeo + $difCaja;
        $totales[$etiquetaTotal] = round($totalSinZ, 2);

        return $totales;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, float>
     */
    private function agregarRendmaquina(array $filas): array
    {
        $totales = [
            'MAQUINAS VENTAS' => 0.0,
            'MAQUINAS CAJA' => 0.0,
            'Vales fondo fijo' => 0.0,
            'Vales administracion' => 0.0,
            'Variacion de FF' => 0.0,
            'Diferencia de caja' => 0.0,
            'Caja en transito' => 0.0,
        ];

        foreach ($filas as $fila) {
            if (! $this->incluirRendmaquina($fila)) {
                continue;
            }

            $venta = $this->ventaMaquinasDesdeFila($fila);
            $deposito = (float) ($fila->rendm_deposito ?? 0);
            $difCajaRaw = (float) ($fila->rendm_dif_caja ?? 0);
            $variacionFf = (float) ($fila->rendm_variacion_ff ?? 0);

            $totales['MAQUINAS VENTAS'] += $venta;
            $totales['MAQUINAS CAJA'] += $deposito;
            $totales['Vales administracion'] += (float) ($fila->rendm_vales ?? 0);
            $totales['Vales fondo fijo'] += (float) ($fila->rendm_reintegros ?? 0);
            $totales['Variacion de FF'] += $variacionFf;
            $totales['Diferencia de caja'] += $difCajaRaw + $variacionFf;
            // Pago 24 (rendm_vta_ant_gastro): columna no expuesta hoy en el bridge.

            if ($venta > $deposito) {
                $cajaTransito = ($venta + $difCajaRaw) - $deposito;
            } else {
                $cajaTransito = $deposito - ($venta + $difCajaRaw);
                $cajaTransito *= -1;
            }
            $totales['Caja en transito'] += $cajaTransito;
        }

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    /**
     * @param  list<int>  $nrosOper
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, float>
     */
    private function agregarMediosPorOperaciones(array $nrosOper, array $valoresPorOper, array $valormae): array
    {
        $totales = [];
        foreach ($nrosOper as $nroOper) {
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                // Canje gastro (15) no se imprime en bloque máquinas (l-posfinanc.c).
                if ($codigo === 15) {
                    continue;
                }
                $desc = $valormae[$codigo]['desc'];
                $totales[$desc] = round(($totales[$desc] ?? 0) + $this->importeValorPesos($valor, $valormae[$codigo]['tipo']), 2);
            }
        }

        return $totales;
    }

    /**
     * @param  list<int>  $nrosOper
     * @param  array<int, array<int, float>>  $gastosPorOper
     * @param  array<int, string>  $apgastoDesc
     * @return array<string, float>
     */
    private function agregarGastosMaquina(array $nrosOper, array $gastosPorOper, array $apgastoDesc): array
    {
        $totales = [];
        foreach ($nrosOper as $nroOper) {
            foreach ($gastosPorOper[$nroOper] ?? [] as $concepto => $importe) {
                if (abs($importe) <= 0.0001) {
                    continue;
                }
                $desc = $apgastoDesc[$concepto] ?? ('Apertura gasto '.$concepto);
                $totales[$desc] = round(($totales[$desc] ?? 0) + $importe, 2);
            }
        }

        return $totales;
    }

    /**
     * @param  array<string, float>  $maquinas
     * @param  array<string, true>  $descsMedio
     */
    private function totalMaquinas(array $maquinas, array $descsMedio): float
    {
        // l-posfinanc.c: depósito − vales − reintegros − apertura de gastos (no resta medios rendvalor).
        $caja = (float) ($maquinas['MAQUINAS CAJA'] ?? 0);
        $restar = (float) ($maquinas['Vales fondo fijo'] ?? 0)
            + (float) ($maquinas['Vales administracion'] ?? 0);

        foreach ($maquinas as $etiqueta => $importe) {
            if (in_array($etiqueta, [
                'MAQUINAS VENTAS', 'MAQUINAS CAJA', 'Vales fondo fijo', 'Vales administracion',
                'Diferencia de caja', 'Caja en transito', 'Variacion de FF', 'Total maquinas',
            ], true)) {
                continue;
            }
            if (isset($descsMedio[$etiqueta])) {
                continue;
            }
            $restar += (float) $importe;
        }

        return round($caja - $restar, 2);
    }

    /**
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  list<object>  $cabGastro
     * @param  array<int, true>  $opsMaquina
     * @param  array<string, float>  $gastronomia
     * @return array<string, float>
     */
    private function agregarAperturaMedios(
        array $valoresPorOper,
        array $valormae,
        array $cabGastro,
        array $opsMaquina,
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        array $gastronomia,
    ): array {
        $porTipo = [];
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            if ($bloque === self::BLOQUE_GASTRO || $bloque === self::BLOQUE_ESTAC) {
                $ops[(int) ($fila->rendg_nro_oper ?? 0)] = true;
            }
        }

        foreach ($ops as $nroOper => $_) {
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $tipo = $valormae[$codigo]['tipo'];
                $porTipo[$tipo] = ($porTipo[$tipo] ?? 0) + $this->importeValorPesos($valor, $tipo);
            }
        }

        // Bingo → efectivo pesos (carton + sobrante + vales).
        foreach ($this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta) as $fila) {
            $importe = (float) ($fila->rendb_total_carton ?? 0)
                + (float) ($fila->rendb_sobrante ?? 0)
                + (float) ($fila->rendb_vales ?? 0);
            $porTipo[self::VALM_EFE_PESOS] = ($porTipo[self::VALM_EFE_PESOS] ?? 0) + $importe;
        }

        // Ajuste Anita: tickets − canje gastronomía.
        $canjeGastro = (float) ($gastronomia['Tk.Canje Gastronomia'] ?? 0);
        if (isset($porTipo['4'])) {
            $porTipo['4'] -= $canjeGastro;
        }

        $etiquetasTipo = [
            self::VALM_EFE_PESOS => 'Efectivo pesos',
            self::VALM_EFE_DOLAR => 'Efectivo dolar',
            self::VALM_EFE_EURO => 'Efectivo euros',
            '3' => 'Tarjetas',
            '4' => 'Tickets',
            self::VALM_EFE_CRIPTO => 'Efectivo cripto USDT',
            '5' => 'QR',
            '6' => 'Varios',
            '7' => 'Varios',
            '9' => 'Varios',
            'A' => 'Varios',
            'B' => 'Varios',
        ];

        $totales = [];
        $totalIngresos = 0.0;
        foreach ($porTipo as $tipo => $importe) {
            $etiqueta = $etiquetasTipo[$tipo] ?? 'Varios';
            $totales[$etiqueta] = round(($totales[$etiqueta] ?? 0) + $importe, 2);
            $totalIngresos += $importe;
        }

        $totales['Canje Gastronomia'] = round($canjeGastro, 2);
        // Canje se informa aparte; el total Anita suma VAL_total (ya neto de canje en tickets) + canje línea.
        $totales['Total de Ingresos'] = round($totalIngresos + $canjeGastro, 2);

        return $totales;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<string, float>  $abiertosNoEfectivo
     * @return array<string, float>
     */
    private function agregarEgresos(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        array $valormae,
        array $abiertosNoEfectivo,
    ): array {
        $totales = [
            'Pesos Maco' => 0.0,
            'Pesos Banco Macro' => 0.0,
            'Pesos Banco Frances' => 0.0,
            'Pesos Banco Provincia' => 0.0,
            'Pesos Caja de seguridad' => 0.0,
            'Dolares Maco' => 0.0,
            'Dolares Banco Macro' => 0.0,
            'Dolares Caja de seguridad' => 0.0,
            'Euros Maco' => 0.0,
            'Euros Banco Frances' => 0.0,
            'Euros Caja de seguridad' => 0.0,
            'Caudales en u$s' => 0.0,
            'Caudales en Euros' => 0.0,
            'Caudales en cripto' => 0.0,
        ];

        $remesas = $this->bridgeReader->listarRemesas($empresaId, $fechaDesde, $fechaHasta);
        $rememae = [];
        foreach ($this->bridgeReader->listarRememae($empresaId, $fechaDesde, $fechaHasta) as $fila) {
            $rememae[(int) ($fila->remem_nro_remesa ?? 0)] = $fila;
        }

        foreach ($remesas as $fila) {
            $nro = (int) ($fila->reme_nro_remesa ?? 0);
            $mae = $rememae[$nro] ?? null;
            $destino = trim((string) ($mae->remem_destino ?? self::REMEM_MACO));
            if ($destino === self::REMEM_PAGOFACIL) {
                continue;
            }

            $codigoValor = (int) ($fila->reme_cod_valor ?? 0);
            $tipo = $valormae[$codigoValor]['tipo'] ?? trim((string) ($fila->reme_tipo_valor ?? self::VALM_EFE_PESOS));
            $importe = (float) ($fila->reme_importe ?? 0);
            $cotizacion = (float) ($fila->reme_cotizacion ?? 0);
            $importePesos = $this->esTipoMe($tipo) && $cotizacion > 0
                ? $importe * $cotizacion
                : $importe;

            if ($tipo === self::VALM_EFE_DOLAR) {
                $totales['Caudales en u$s'] += $importe;
                $totales[$this->etiquetaEgresoDolar($destino)] = ($totales[$this->etiquetaEgresoDolar($destino)] ?? 0) + $importe;
                $totales[$valormae[$codigoValor]['desc'] ?? 'Efectivo dolares'] =
                    ($totales[$valormae[$codigoValor]['desc'] ?? 'Efectivo dolares'] ?? 0) + $importePesos;
            } elseif ($tipo === self::VALM_EFE_EURO) {
                $totales['Caudales en Euros'] += $importe;
                $totales[$this->etiquetaEgresoEuro($destino)] = ($totales[$this->etiquetaEgresoEuro($destino)] ?? 0) + $importe;
            } elseif ($tipo === self::VALM_EFE_CRIPTO) {
                $totales['Caudales en cripto'] += $importe;
            } else {
                $totales[$this->etiquetaEgresoPesos($destino)] =
                    ($totales[$this->etiquetaEgresoPesos($destino)] ?? 0) + $importePesos;
            }
        }

        $totalEgresos = 0.0;
        foreach ($abiertosNoEfectivo as $desc => $importe) {
            // Entran al Total de Egresos pero no se reimprimen (ya salieron en gastro/máquinas).
            $totalEgresos += $importe;
        }

        foreach ($totales as $etiqueta => $importe) {
            if (str_starts_with($etiqueta, 'Caudales') || $etiqueta === 'Total de Egresos') {
                continue;
            }
            $totalEgresos += $importe;
        }
        $totales['Total de Egresos'] = round($totalEgresos, 2);

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    /**
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  list<object>  $cabGastro
     * @param  array<int, true>  $opsMaquina
     * @return array<string, float>
     */
    private function acumularAbiertosNoEfectivo(
        array $valoresPorOper,
        array $valormae,
        array $cabGastro,
        array $opsMaquina,
        int $empresaId,
    ): array {
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            if ($bloque === self::BLOQUE_GASTRO || $bloque === self::BLOQUE_ESTAC) {
                $ops[(int) ($fila->rendg_nro_oper ?? 0)] = true;
            }
        }

        $totales = [];
        foreach ($ops as $nroOper => $_) {
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $tipo = $valormae[$codigo]['tipo'];
                if ($this->esTipoEfectivo($tipo)) {
                    continue;
                }
                // Transf. Check MS (código 8): no suma en Total de Egresos del listado Contaduría BSA.
                if ($codigo === 8) {
                    continue;
                }
                $desc = $valormae[$codigo]['desc'];
                $totales[$desc] = ($totales[$desc] ?? 0) + $this->importeValorPesos($valor, $tipo);
            }
        }

        return array_map(fn (float $v) => round($v, 2), $totales);
    }

    private function etiquetaEgresoPesos(string $destino): string
    {
        return match ($destino) {
            self::REMEM_MACRO => 'Pesos Banco Macro',
            self::REMEM_FRANCES => 'Pesos Banco Frances',
            self::REMEM_PROVINCIA => 'Pesos Banco Provincia',
            self::REMEM_CAJASEG => 'Pesos Caja de seguridad',
            default => 'Pesos Maco',
        };
    }

    private function etiquetaEgresoDolar(string $destino): string
    {
        return match ($destino) {
            self::REMEM_MACRO => 'Dolares Banco Macro',
            self::REMEM_FRANCES, self::REMEM_CAJASEG => 'Dolares Caja de seguridad',
            default => 'Dolares Maco',
        };
    }

    private function etiquetaEgresoEuro(string $destino): string
    {
        return match ($destino) {
            self::REMEM_FRANCES => 'Euros Banco Frances',
            self::REMEM_CAJASEG => 'Euros Caja de seguridad',
            default => 'Euros Maco',
        };
    }

    private function incluirRendmaquina(object $fila): bool
    {
        $fecha = (int) ($fila->rendm_fecha ?? 0);
        if ($fecha >= self::FECHA_CORTE_TURNO_MAQUINA) {
            $turno = strtoupper(trim((string) ($fila->rendm_turno ?? '')));
            if (in_array($turno, self::TURNOS_MAQUINA_EXCLUIDOS, true)) {
                return false;
            }
        }

        return true;
    }

    private function ventaMaquinasDesdeFila(object $fila): float
    {
        $ingresoRodillos = (float) ($fila->rendm_venta_ficha ?? 0)
            + (float) ($fila->rendm_drop_billete ?? 0)
            + (float) ($fila->rendm_billem_rod ?? 0);
        $salidaRodillos = (float) ($fila->rendm_pago_manual ?? 0)
            + (float) ($fila->rendm_tito ?? 0)
            + (float) ($fila->rendm_hopper ?? 0);

        $ingresoRuleta = (float) ($fila->rendm_venta_ruleta ?? 0)
            + (float) ($fila->rendm_drop_ruleta ?? 0)
            + (float) ($fila->rendm_billem_rul ?? 0);
        $salidaRuleta = (float) ($fila->rendm_salida_rul ?? $fila->rendm_salida_ruleta ?? 0)
            + (float) ($fila->rendm_tito_ruleta ?? 0);

        return ($ingresoRodillos - $salidaRodillos) + ($ingresoRuleta - $salidaRuleta);
    }

    private function clasificarSucursal(int $empresaId, int $sucursal): string
    {
        if ($sucursal <= 0) {
            return self::BLOQUE_OMITIR;
        }

        $key = $empresaId.':'.$sucursal;
        if (isset($this->clasificacionSucursalCache[$key])) {
            return $this->clasificacionSucursalCache[$key];
        }

        $nombre = mb_strtolower($this->nombrePuntoventa($empresaId, $sucursal));

        if ($nombre !== '' && (str_contains($nombre, 'estacionamiento') || str_contains($nombre, 'estac.'))) {
            return $this->clasificacionSucursalCache[$key] = self::BLOQUE_ESTAC;
        }

        // EGA / SHOW / máquinas vending u otros no GASTRONOMIA.
        if ($nombre !== '') {
            $esShow = str_contains($nombre, 'show');
            $esEgaPuro = str_contains($nombre, 'ega') && ! str_contains($nombre, 'gastro');
            $esMaquinaNumerada = (bool) preg_match('/maquina\s*\d+/', $nombre) || $sucursal >= 1000;
            if ($esShow || $esEgaPuro || $esMaquinaNumerada) {
                return $this->clasificacionSucursalCache[$key] = self::BLOQUE_OMITIR;
            }
        } elseif ($sucursal >= 1000) {
            return $this->clasificacionSucursalCache[$key] = self::BLOQUE_OMITIR;
        }

        // Fallback Anita: PV 13/72/73/74 = estacionamiento BSA.
        if (in_array($sucursal, [13, 72, 73, 74], true)) {
            return $this->clasificacionSucursalCache[$key] = self::BLOQUE_ESTAC;
        }

        return $this->clasificacionSucursalCache[$key] = self::BLOQUE_GASTRO;
    }

    private function nombrePuntoventa(int $empresaId, int $sucursal): string
    {
        $codigo = str_pad((string) $sucursal, 5, '0', STR_PAD_LEFT);
            $pv = Puntoventa::query()
            ->where('empresa_id', $empresaId)
            ->where(function ($q) use ($sucursal, $codigo) {
                $q->where('codigo', $codigo)
                    ->orWhere('codigo', (string) $sucursal)
                    ->orWhere('codigo', ltrim($codigo, '0') ?: '0');
            })
            ->first(['nombre']);

        return trim((string) ($pv->nombre ?? ''));
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, array{desc: string, tipo: string}>
     */
    private function indexarValormae(array $filas): array
    {
        $map = [];
        foreach ($filas as $fila) {
            $codigo = (int) ($fila->valm_codigo ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $map[$codigo] = [
                'desc' => trim((string) ($fila->valm_desc ?? 'Valor '.$codigo)),
                'tipo' => trim((string) ($fila->valm_tipo_valor ?? self::VALM_EFE_PESOS)),
            ];
        }

        return $map;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, list<object>>
     */
    private function indexarRendvalorPorOper(array $filas): array
    {
        $map = [];
        foreach ($filas as $fila) {
            $nro = (int) ($fila->rendv_nro_oper ?? 0);
            if ($nro <= 0) {
                continue;
            }
            $map[$nro][] = $fila;
        }

        return $map;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, string>
     */
    private function indexarApgasto(array $filas): array
    {
        $map = [];
        foreach ($filas as $fila) {
            $concepto = (int) ($fila->apg_concepto ?? 0);
            if ($concepto <= 0) {
                continue;
            }
            $map[$concepto] = trim((string) ($fila->apg_desc ?? 'Gasto '.$concepto));
        }

        return $map;
    }

    /**
     * @param  list<int>  $nrosOper
     * @return array<int, array<int, float>>
     */
    private function cargarGastosMaquina(array $nrosOper): array
    {
        $nrosOper = array_values(array_filter(array_map('intval', $nrosOper), static fn (int $n) => $n > 0));
        if ($nrosOper === []) {
            return [];
        }

        $min = min($nrosOper);
        $max = max($nrosOper);
        $filas = $this->bridgeReader->listarRendmapgasto($min, $max);
        $colOper = (string) config('rendicion_maquina_anita.gasto_col_nro_oper', 'renmap_nro_oper');
        $colCodigo = (string) config('rendicion_maquina_anita.gasto_col_codigo', 'renmap_codigo');
        $colImporte = (string) config('rendicion_maquina_anita.gasto_col_importe', 'renmap_importe');

        $ops = array_flip($nrosOper);
        $map = [];
        foreach ($filas as $fila) {
            $arr = (array) $fila;
            $nro = (int) ($arr[$colOper] ?? 0);
            if (! isset($ops[$nro])) {
                continue;
            }
            $concepto = (int) ($arr[$colCodigo] ?? 0);
            $importe = (float) ($arr[$colImporte] ?? 0);
            if ($concepto <= 0 || abs($importe) <= 0.0001) {
                continue;
            }
            $map[$nro][$concepto] = round(($map[$nro][$concepto] ?? 0) + $importe, 2);
        }

        return $map;
    }

    private function importeValorPesos(object $valor, string $tipo): float
    {
        $total = (float) ($valor->rendv_total ?? 0);
        $cotizacion = (float) ($valor->rendv_cotizacion ?? 0);
        if ($this->esTipoMe($tipo) && $cotizacion > 0) {
            return $total * $cotizacion;
        }

        return $total;
    }

    private function esTipoMe(string $tipo): bool
    {
        return in_array($tipo, [self::VALM_EFE_DOLAR, self::VALM_EFE_EURO, self::VALM_EFE_CRIPTO], true);
    }

    private function esTipoEfectivo(string $tipo): bool
    {
        return in_array($tipo, [
            self::VALM_EFE_PESOS,
            self::VALM_EFE_DOLAR,
            self::VALM_EFE_EURO,
            self::VALM_EFE_CRIPTO,
        ], true);
    }

    /**
     * @param  list<string>  $errores
     * @return array{
     *   totales_por_etiqueta: array<string, float>,
     *   filas_ordenadas: list<array{etiqueta: string, valor: float}>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   gastronomia: array<string, float>,
     *   estacionamiento: array<string, float>,
     *   maquinas: array<string, float>,
     *   apertura_medios: array<string, float>,
     *   egresos: array<string, float>,
     *   errores_bridge: list<string>
     * }
     */
    private function vacio(array $errores): array
    {
        return [
            'totales_por_etiqueta' => [],
            'filas_ordenadas' => [],
            'saldo_inicial' => null,
            'saldo_final' => null,
            'bingo' => [],
            'premios_bingo' => [],
            'gastronomia' => [],
            'estacionamiento' => [],
            'maquinas' => [],
            'apertura_medios' => [],
            'egresos' => [],
            'errores_bridge' => $errores,
        ];
    }
}
