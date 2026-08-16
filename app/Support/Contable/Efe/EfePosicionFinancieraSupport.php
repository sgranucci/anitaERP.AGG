<?php

namespace App\Support\Contable\Efe;

use App\Models\Ventas\Puntoventa;
use Carbon\Carbon;

/**
 * Posición financiera mensual (solapa «pos fin …») — port de l-posfinanc.c.
 *
 * Impresión Anita: una columna por día del mes + «Total mensual», cortada por
 * unidad (bingo, gastronomía, estacionamiento, EGA, SHOW, máquinas) y luego
 * apertura de medios / egresos / saldos. Kiosco está comentado en el .c.
 * Vending no existe en l-posfinanc.c: se agrega como bloque ERP (PV Maquina N
 * / sucursal ≥ 1000 / nombre vending) que antes se omitía.
 *
 * Fuentes Anita (vía bridge):
 * saldoposf, rendbingo/concbingo/rendpremio, rendmaquina, rendgastro, rendvalor,
 * valormae, remesas/rememae, apgasto/rendmapgasto.
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

    private const BLOQUE_VENDING = 'vending';

    private const BLOQUE_MAQUINAS = 'maquinas';

    private const BLOQUE_BINGO = 'bingo';

    private const BLOQUE_EGA = 'ega';

    private const BLOQUE_SHOW = 'show';

    private const BLOQUE_MEDIOS = 'medios';

    private const BLOQUE_EGRESOS = 'egresos';

    private const BLOQUE_SALDOS = 'saldos';

    private const BLOQUE_OMITIR = 'omitir';

    private const TIPO_CONCEPTO = 'concepto';

    private const TIPO_TITULO = 'titulo';

    private const TIPO_TOTAL = 'total';

    private const TIPO_RELLENO_EFE = 'relleno_efe';

    /** @var array<int, string> */
    private array $clasificacionSucursalCache = [];

    /** @var list<int> */
    private array $dias = [];

    public function __construct(
        private readonly EfeAnitaBridgeReader $bridgeReader = new EfeAnitaBridgeReader(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{
     *   totales_por_etiqueta: array<string, float>,
     *   filas_ordenadas: list<array{etiqueta: string, valor: float, por_dia: array<int, float>, bloque: string, tipo_fila: string}>,
     *   dias: list<int>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   gastronomia: array<string, float>,
     *   estacionamiento: array<string, float>,
     *   vending: array<string, float>,
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
        $this->dias = range(1, (int) $finMes->day);
        $fechaSaldoInicial = (int) $inicioMes->copy()->subDay()->format('Ymd');
        $fechaDesde = (int) $inicioMes->format('Ymd');
        $fechaHasta = (int) $finMes->format('Ymd');

        $errores = [];
        /** @var list<array{etiqueta: string, valor: float, por_dia: array<int, float>, bloque: string, tipo_fila: string}> $filasOrdenadas */
        $filasOrdenadas = [];

        $saldos = $this->bridgeReader->listarSaldoposf($empresaId, $fechaSaldoInicial, $fechaHasta);
        $saldoInicial = $this->saldoEnFecha($saldos, $fechaSaldoInicial);
        $saldoFinalBridge = $this->saldoEnFecha($saldos, $fechaHasta);

        $rendbingo = $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta);
        $bingo = $this->agregarRendbingo($rendbingo);
        $premios = $this->agregarPremiosBingo(
            $bingo,
            $this->bridgeReader->listarConcbingo(),
            $rendbingo,
            $this->bridgeReader->listarRendpremio($fechaDesde, $fechaHasta),
        );

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
        $vending = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $empresaId,
            self::BLOQUE_VENDING,
            'VENDING Z',
            'Total Vending',
        );

        $filasMaquina = $this->bridgeReader->listarRendmaquina($empresaId, $fechaDesde, $fechaHasta);
        $opsMaquina = [];
        $fechaPorOper = $this->fechasPorOperacion($cabGastro, $filasMaquina);
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
            $fechaPorOper,
        );
        $maquinasGastos = $this->agregarGastosMaquina(
            array_keys($opsMaquina),
            $gastosPorOper,
            $apgastoDesc,
            $fechaPorOper,
        );
        $descsMedio = [];
        foreach ($valormae as $meta) {
            $descsMedio[$meta['desc']] = true;
        }
        $maquinas = array_merge($maquinasBase, $maquinasMedios, $maquinasGastos);
        $maquinas['Total maquinas'] = $this->totalMaquinasPorDia($maquinas, $descsMedio);

        $apertura = $this->agregarAperturaMedios(
            $valoresPorOper,
            $valormae,
            $cabGastro,
            $opsMaquina,
            $empresaId,
            $rendbingo,
            $gastronomia,
            $fechaPorOper,
        );

        $egresos = $this->agregarEgresos(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $valormae,
            $this->acumularAbiertosNoEfectivo(
                $valoresPorOper,
                $valormae,
                $cabGastro,
                $opsMaquina,
                $empresaId,
                $fechaPorOper,
            ),
        );

        $saldoInicialPorDia = $this->vectorDias();
        $saldoFinalPorDia = $this->vectorDias();
        $saldoCorrido = (float) ($saldoInicial ?? 0);
        $ingresosPorDia = $apertura['Total de Ingresos'] ?? $this->vectorDias();
        $egresosPorDia = $egresos['Total de Egresos'] ?? $this->vectorDias();
        foreach ($this->dias as $dia) {
            $saldoInicialPorDia[$dia] = round($saldoCorrido, 2);
            $saldoCorrido += (float) ($ingresosPorDia[$dia] ?? 0) - (float) ($egresosPorDia[$dia] ?? 0);
            $saldoFinalPorDia[$dia] = round($saldoCorrido, 2);
        }
        $saldoFinal = $saldoFinalPorDia[$finMes->day] ?? $saldoFinalBridge;

        $this->pushFila($filasOrdenadas, 'Saldo inicial', $saldoInicialPorDia, self::BLOQUE_SALDOS, self::TIPO_TOTAL);

        $this->pushTitulo($filasOrdenadas, 'Bingo', self::BLOQUE_BINGO);
        $this->pushFila($filasOrdenadas, 'VENTA BINGO', $bingo['VENTA BINGO'] ?? $this->vectorDias(), self::BLOQUE_BINGO);
        $this->pushMapa($filasOrdenadas, $premios, self::BLOQUE_BINGO);
        foreach (['SOBRANTES', 'VALES', 'REDONDEO'] as $etiqBingo) {
            $this->pushFila($filasOrdenadas, $etiqBingo, $bingo[$etiqBingo] ?? $this->vectorDias(), self::BLOQUE_BINGO);
        }
        $this->pushFila(
            $filasOrdenadas,
            'Total bingo',
            $this->sumarMapas(array_merge(['VENTA BINGO' => $bingo['VENTA BINGO'] ?? $this->vectorDias()], $premios, [
                'SOBRANTES' => $bingo['SOBRANTES'] ?? $this->vectorDias(),
                'VALES' => $bingo['VALES'] ?? $this->vectorDias(),
                'REDONDEO' => $bingo['REDONDEO'] ?? $this->vectorDias(),
            ])),
            self::BLOQUE_BINGO,
            self::TIPO_TOTAL,
        );

        $this->pushTitulo($filasOrdenadas, 'Gastronomía', self::BLOQUE_GASTRO);
        $this->pushMapa($filasOrdenadas, $gastronomia, self::BLOQUE_GASTRO, 'Total Gastronomia');

        $this->pushTitulo($filasOrdenadas, 'Estacionamiento', self::BLOQUE_ESTAC);
        $this->pushMapa($filasOrdenadas, $estacionamiento, self::BLOQUE_ESTAC, 'Total Estacionamiento');

        $this->pushTitulo($filasOrdenadas, 'Vending', self::BLOQUE_VENDING);
        $this->pushMapa($filasOrdenadas, $vending, self::BLOQUE_VENDING, 'Total Vending');

        foreach (['EGA' => self::BLOQUE_EGA, 'SHOW' => self::BLOQUE_SHOW] as $bloqueOmitido => $claveBloque) {
            $this->pushTitulo($filasOrdenadas, $bloqueOmitido, $claveBloque);
            $this->pushFila($filasOrdenadas, $bloqueOmitido.' Z', $this->vectorDias(), $claveBloque);
            foreach (['EFECTIVO', 'TK.CANJE SHOW', 'MERCADO PAGO', 'PASSLINE'] as $medioOmitido) {
                $this->pushFila($filasOrdenadas, $medioOmitido, $this->vectorDias(), $claveBloque);
                for ($i = 0; $i < 2; $i++) {
                    $this->pushFila($filasOrdenadas, $medioOmitido, $this->vectorDias(), $claveBloque, self::TIPO_RELLENO_EFE);
                }
            }
            $this->pushFila($filasOrdenadas, 'Notas de credito', $this->vectorDias(), $claveBloque);
            $this->pushFila($filasOrdenadas, 'Diferencia abandono de pago', $this->vectorDias(), $claveBloque);
            $this->pushFila($filasOrdenadas, 'Redondeo', $this->vectorDias(), $claveBloque);
            $this->pushFila($filasOrdenadas, 'Diferencia de caja', $this->vectorDias(), $claveBloque);
            $this->pushFila($filasOrdenadas, 'Total '.$bloqueOmitido, $this->vectorDias(), $claveBloque, self::TIPO_TOTAL);
        }

        $this->pushTitulo($filasOrdenadas, 'Máquinas', self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'MAQUINAS VENTAS', $maquinasBase['MAQUINAS VENTAS'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'MAQUINAS CAJA', $maquinasBase['MAQUINAS CAJA'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushMapa($filasOrdenadas, $maquinasMedios, self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Vales fondo fijo', $maquinasBase['Vales fondo fijo'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Vales administracion', $maquinasBase['Vales administracion'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushMapa($filasOrdenadas, $maquinasGastos, self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Variacion de FF', $maquinasBase['Variacion de FF'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Diferencia de caja', $maquinasBase['Diferencia de caja'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Caja en transito', $maquinasBase['Caja en transito'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Total maquinas', $maquinas['Total maquinas'], self::BLOQUE_MAQUINAS, self::TIPO_TOTAL);

        $this->pushTitulo($filasOrdenadas, 'Apertura de medios de cobro', self::BLOQUE_MEDIOS);
        $this->pushMapa($filasOrdenadas, $apertura, self::BLOQUE_MEDIOS, 'Total de Ingresos');

        $this->pushTitulo($filasOrdenadas, 'Egresos', self::BLOQUE_EGRESOS);
        $this->pushMapa($filasOrdenadas, $egresos, self::BLOQUE_EGRESOS, 'Total de Egresos');

        $this->pushFila($filasOrdenadas, 'Saldo final', $saldoFinalPorDia, self::BLOQUE_SALDOS, self::TIPO_TOTAL);

        $totales = $this->mapaUltimaEtiqueta($filasOrdenadas);

        return [
            'totales_por_etiqueta' => $totales,
            'filas_ordenadas' => $filasOrdenadas,
            'dias' => $this->dias,
            'saldo_inicial' => $saldoInicial,
            'saldo_final' => $saldoFinal,
            'bingo' => $this->mapaTotales($bingo),
            'premios_bingo' => $this->mapaTotales($premios),
            'gastronomia' => $this->mapaTotales($gastronomia),
            'estacionamiento' => $this->mapaTotales($estacionamiento),
            'vending' => $this->mapaTotales($vending),
            'maquinas' => $this->mapaTotales($maquinas),
            'apertura_medios' => $this->mapaTotales($apertura),
            'egresos' => $this->mapaTotales($egresos),
            'errores_bridge' => $errores,
        ];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float, por_dia?: array<int, float>, bloque?: string, tipo_fila?: string}>  $filas
     */
    private function pushFila(
        array &$filas,
        string $etiqueta,
        array $porDia,
        string $bloque,
        string $tipoFila = self::TIPO_CONCEPTO,
    ): void {
        $porDia = $this->redondearVector($porDia);
        $filas[] = [
            'etiqueta' => $etiqueta,
            'valor' => $this->totalVector($porDia),
            'por_dia' => $porDia,
            'bloque' => $bloque,
            'tipo_fila' => $tipoFila,
        ];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float, por_dia?: array<int, float>, bloque?: string, tipo_fila?: string}>  $filas
     */
    private function pushTitulo(array &$filas, string $etiqueta, string $bloque): void
    {
        $filas[] = [
            'etiqueta' => $etiqueta,
            'valor' => 0.0,
            'por_dia' => $this->vectorDias(),
            'bloque' => $bloque,
            'tipo_fila' => self::TIPO_TITULO,
        ];
    }

    /**
     * @param  list<array{etiqueta: string, valor: float, por_dia?: array<int, float>, bloque?: string, tipo_fila?: string}>  $filas
     * @param  array<string, array<int, float>>  $mapa
     */
    private function pushMapa(array &$filas, array $mapa, string $bloque, ?string $etiquetaTotal = null): void
    {
        foreach ($mapa as $etiqueta => $porDia) {
            $tipo = ($etiquetaTotal !== null && $etiqueta === $etiquetaTotal)
                ? self::TIPO_TOTAL
                : self::TIPO_CONCEPTO;
            $this->pushFila($filas, $etiqueta, $porDia, $bloque, $tipo);
        }
    }

    /**
     * @param  list<array{etiqueta: string, valor: float, tipo_fila?: string}>  $filas
     * @return array<string, float>
     */
    private function mapaUltimaEtiqueta(array $filas): array
    {
        $totales = [];
        foreach ($filas as $fila) {
            if (in_array($fila['tipo_fila'] ?? self::TIPO_CONCEPTO, [self::TIPO_TITULO, self::TIPO_RELLENO_EFE], true)) {
                continue;
            }
            $totales[$fila['etiqueta']] = $fila['valor'];
        }

        return $totales;
    }

    /**
     * @param  array<string, array<int, float>>  $mapa
     * @return array<string, float>
     */
    private function mapaTotales(array $mapa): array
    {
        $totales = [];
        foreach ($mapa as $etiqueta => $porDia) {
            $totales[$etiqueta] = $this->totalVector($porDia);
        }

        return $totales;
    }

    /**
     * @return array<int, float>
     */
    private function vectorDias(): array
    {
        $vector = [];
        foreach ($this->dias as $dia) {
            $vector[$dia] = 0.0;
        }

        return $vector;
    }

    /**
     * @param  array<int, float>  $porDia
     * @return array<int, float>
     */
    private function redondearVector(array $porDia): array
    {
        $out = $this->vectorDias();
        foreach ($porDia as $dia => $valor) {
            $out[(int) $dia] = round((float) $valor, 2);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $porDia
     */
    private function totalVector(array $porDia): float
    {
        return round(array_sum($porDia), 2);
    }

    /**
     * @param  array<string, array<int, float>>  $mapas
     * @return array<int, float>
     */
    private function sumarMapas(array $mapas): array
    {
        $out = $this->vectorDias();
        foreach ($mapas as $porDia) {
            foreach ($this->dias as $dia) {
                $out[$dia] = round($out[$dia] + (float) ($porDia[$dia] ?? 0), 2);
            }
        }

        return $out;
    }

    private function diaDeYmd(int $ymd): int
    {
        $dia = (int) ($ymd % 100);
        if ($dia < 1 || $dia > 31) {
            return 1;
        }

        return $dia;
    }

    /**
     * @param  array<string, array<int, float>>  $mapa
     */
    private function sumarEn(array &$mapa, string $etiqueta, int $dia, float $importe): void
    {
        if (! isset($mapa[$etiqueta])) {
            $mapa[$etiqueta] = $this->vectorDias();
        }
        if (! isset($mapa[$etiqueta][$dia])) {
            $mapa[$etiqueta][$dia] = 0.0;
        }
        $mapa[$etiqueta][$dia] = round($mapa[$etiqueta][$dia] + $importe, 2);
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
     * @return array<string, array<int, float>>
     */
    private function agregarRendbingo(array $filas): array
    {
        $totales = [
            'VENTA BINGO' => $this->vectorDias(),
            'SOBRANTES' => $this->vectorDias(),
            'VALES' => $this->vectorDias(),
            'REDONDEO' => $this->vectorDias(),
        ];

        foreach ($filas as $fila) {
            $dia = $this->diaDeYmd((int) ($fila->rendb_fecha ?? 0));
            $this->sumarEn($totales, 'VENTA BINGO', $dia, (float) ($fila->rendb_total_carton ?? 0));
            $this->sumarEn($totales, 'SOBRANTES', $dia, (float) ($fila->rendb_sobrante ?? 0));
            $this->sumarEn($totales, 'VALES', $dia, (float) ($fila->rendb_vales ?? 0));
            $this->sumarEn($totales, 'REDONDEO', $dia, (float) ($fila->rendb_redondeo ?? 0));
        }

        return $totales;
    }

    /**
     * @param  array<string, array<int, float>>  $bingoBase
     * @param  list<object>  $concbingo
     * @param  list<object>  $rendbingo
     * @param  list<object>  $rendpremio
     * @return array<string, array<int, float>>
     */
    private function agregarPremiosBingo(
        array $bingoBase,
        array $concbingo,
        array $rendbingo,
        array $rendpremio,
    ): array {
        $totales = [];
        $ventaPorDia = $bingoBase['VENTA BINGO'] ?? $this->vectorDias();

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
            if ($pct <= 0) {
                continue;
            }
            foreach ($this->dias as $dia) {
                $ventaDia = (float) ($ventaPorDia[$dia] ?? 0);
                if ($ventaDia > 0) {
                    $this->sumarEn($totales, $desc, $dia, -1 * $ventaDia * ($pct / 100));
                }
            }
        }

        $opsRendb = [];
        foreach ($rendbingo as $row) {
            $clave = (int) ($row->rendb_nro_oper ?? 0).'|'.trim((string) ($row->rendb_tipo_oper ?? ''));
            $opsRendb[$clave] = (int) ($row->rendb_fecha ?? 0);
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

            $fecha = (int) ($opsRendb[$claveOp] ?? 0);
            if ($fecha <= 0) {
                $fecha = (int) ($row->rendp_fecha ?? 0);
            }
            $this->sumarEn($totales, $desc, $this->diaDeYmd($fecha), -1 * $importe);
        }

        return $totales;
    }

    /**
     * @param  list<object>  $cabeceras
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, array<int, float>>
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
        $totales = [
            $etiquetaZ => $this->vectorDias(),
        ];
        foreach ($valormae as $meta) {
            $totales[$meta['desc']] = $this->vectorDias();
        }
        $totales['Notas de credito'] = $this->vectorDias();
        $totales['Diferencia abandono de pago'] = $this->vectorDias();
        $totales['Redondeo'] = $this->vectorDias();
        $totales['Diferencia de caja'] = $this->vectorDias();
        $totales[$etiquetaTotal] = $this->vectorDias();

        foreach ($cabeceras as $fila) {
            $sucursal = (int) ($fila->rendg_sucursal ?? 0);
            if ($this->clasificarSucursal($empresaId, $sucursal) !== $bloque) {
                continue;
            }

            $dia = $this->diaDeYmd((int) ($fila->rendg_fecha ?? 0));
            $nroOper = (int) ($fila->rendg_nro_oper ?? 0);
            $this->sumarEn($totales, $etiquetaZ, $dia, (float) ($fila->rendg_total_z ?? 0));
            $this->sumarEn($totales, 'Notas de credito', $dia, (float) ($fila->rendg_tot_nc ?? 0));
            $this->sumarEn($totales, 'Diferencia abandono de pago', $dia, (float) ($fila->rendg_ab_pago ?? 0));
            $red = (float) ($fila->rendg_tot_redondeo ?? 0);
            $this->sumarEn($totales, 'Redondeo', $dia, $redondeoNegado ? -$red : $red);
            $this->sumarEn($totales, 'Diferencia de caja', $dia, -1 * (float) ($fila->rendg_dif_caja ?? 0));

            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $this->sumarEn(
                    $totales,
                    $valormae[$codigo]['desc'],
                    $dia,
                    $this->importeValorPesos($valor, $valormae[$codigo]['tipo']),
                );
            }
        }

        foreach ($this->dias as $dia) {
            $totalSinZ = 0.0;
            foreach ($totales as $etiqueta => $porDia) {
                if ($etiqueta === $etiquetaZ || $etiqueta === $etiquetaTotal) {
                    continue;
                }
                $totalSinZ += (float) ($porDia[$dia] ?? 0);
            }
            $totales[$etiquetaTotal][$dia] = round($totalSinZ, 2);
        }

        return $totales;
    }

    /**
     * @param  list<object>  $filas
     * @return array<string, array<int, float>>
     */
    private function agregarRendmaquina(array $filas): array
    {
        $totales = [
            'MAQUINAS VENTAS' => $this->vectorDias(),
            'MAQUINAS CAJA' => $this->vectorDias(),
            'Vales fondo fijo' => $this->vectorDias(),
            'Vales administracion' => $this->vectorDias(),
            'Variacion de FF' => $this->vectorDias(),
            'Diferencia de caja' => $this->vectorDias(),
            'Caja en transito' => $this->vectorDias(),
        ];

        foreach ($filas as $fila) {
            if (! $this->incluirRendmaquina($fila)) {
                continue;
            }

            $dia = $this->diaDeYmd((int) ($fila->rendm_fecha ?? 0));
            $venta = $this->ventaMaquinasDesdeFila($fila);
            $deposito = (float) ($fila->rendm_deposito ?? 0);
            $difCajaRaw = (float) ($fila->rendm_dif_caja ?? 0);
            $variacionFf = (float) ($fila->rendm_variacion_ff ?? 0);

            $this->sumarEn($totales, 'MAQUINAS VENTAS', $dia, $venta);
            $this->sumarEn($totales, 'MAQUINAS CAJA', $dia, $deposito);
            $this->sumarEn($totales, 'Vales administracion', $dia, (float) ($fila->rendm_vales ?? 0));
            $this->sumarEn($totales, 'Vales fondo fijo', $dia, (float) ($fila->rendm_reintegros ?? 0));
            $this->sumarEn($totales, 'Variacion de FF', $dia, $variacionFf);
            $this->sumarEn($totales, 'Diferencia de caja', $dia, $difCajaRaw + $variacionFf);

            if ($venta > $deposito) {
                $cajaTransito = ($venta + $difCajaRaw) - $deposito;
            } else {
                $cajaTransito = $deposito - ($venta + $difCajaRaw);
                $cajaTransito *= -1;
            }
            $this->sumarEn($totales, 'Caja en transito', $dia, $cajaTransito);
        }

        return $totales;
    }

    /**
     * @param  list<int>  $nrosOper
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, int>  $fechaPorOper
     * @return array<string, array<int, float>>
     */
    private function agregarMediosPorOperaciones(
        array $nrosOper,
        array $valoresPorOper,
        array $valormae,
        array $fechaPorOper,
    ): array {
        $totales = [];
        foreach ($nrosOper as $nroOper) {
            $diaOper = $this->diaDeYmd((int) ($fechaPorOper[$nroOper] ?? 0));
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                if ($codigo === 15) {
                    continue;
                }
                $dia = (int) ($valor->rendv_fecha ?? 0) > 0
                    ? $this->diaDeYmd((int) $valor->rendv_fecha)
                    : $diaOper;
                $this->sumarEn(
                    $totales,
                    $valormae[$codigo]['desc'],
                    $dia,
                    $this->importeValorPesos($valor, $valormae[$codigo]['tipo']),
                );
            }
        }

        return $totales;
    }

    /**
     * @param  list<int>  $nrosOper
     * @param  array<int, array<int, float>>  $gastosPorOper
     * @param  array<int, string>  $apgastoDesc
     * @param  array<int, int>  $fechaPorOper
     * @return array<string, array<int, float>>
     */
    private function agregarGastosMaquina(
        array $nrosOper,
        array $gastosPorOper,
        array $apgastoDesc,
        array $fechaPorOper,
    ): array {
        $totales = [];
        foreach ($nrosOper as $nroOper) {
            $dia = $this->diaDeYmd((int) ($fechaPorOper[$nroOper] ?? 0));
            foreach ($gastosPorOper[$nroOper] ?? [] as $concepto => $importe) {
                if (abs($importe) <= 0.0001) {
                    continue;
                }
                $desc = $apgastoDesc[$concepto] ?? ('Apertura gasto '.$concepto);
                $this->sumarEn($totales, $desc, $dia, $importe);
            }
        }

        return $totales;
    }

    /**
     * @param  array<string, array<int, float>>  $maquinas
     * @param  array<string, true>  $descsMedio
     * @return array<int, float>
     */
    private function totalMaquinasPorDia(array $maquinas, array $descsMedio): array
    {
        $out = $this->vectorDias();
        foreach ($this->dias as $dia) {
            $caja = (float) (($maquinas['MAQUINAS CAJA'][$dia] ?? 0));
            $restar = (float) (($maquinas['Vales fondo fijo'][$dia] ?? 0))
                + (float) (($maquinas['Vales administracion'][$dia] ?? 0));

            foreach ($maquinas as $etiqueta => $porDia) {
                if (in_array($etiqueta, [
                    'MAQUINAS VENTAS', 'MAQUINAS CAJA', 'Vales fondo fijo', 'Vales administracion',
                    'Diferencia de caja', 'Caja en transito', 'Variacion de FF', 'Total maquinas',
                ], true)) {
                    continue;
                }
                if (isset($descsMedio[$etiqueta])) {
                    continue;
                }
                $restar += (float) ($porDia[$dia] ?? 0);
            }
            $out[$dia] = round($caja - $restar, 2);
        }

        return $out;
    }

    /**
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  list<object>  $cabGastro
     * @param  array<int, true>  $opsMaquina
     * @param  list<object>  $rendbingo
     * @param  array<string, array<int, float>>  $gastronomia
     * @param  array<int, int>  $fechaPorOper
     * @return array<string, array<int, float>>
     */
    private function agregarAperturaMedios(
        array $valoresPorOper,
        array $valormae,
        array $cabGastro,
        array $opsMaquina,
        int $empresaId,
        array $rendbingo,
        array $gastronomia,
        array $fechaPorOper,
    ): array {
        $porTipo = [];
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            if (in_array($bloque, [self::BLOQUE_GASTRO, self::BLOQUE_ESTAC, self::BLOQUE_VENDING], true)) {
                $ops[(int) ($fila->rendg_nro_oper ?? 0)] = true;
            }
        }

        foreach ($ops as $nroOper => $_) {
            $diaOper = $this->diaDeYmd((int) ($fechaPorOper[$nroOper] ?? 0));
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $tipo = $valormae[$codigo]['tipo'];
                $dia = (int) ($valor->rendv_fecha ?? 0) > 0
                    ? $this->diaDeYmd((int) $valor->rendv_fecha)
                    : $diaOper;
                $this->sumarEn($porTipo, $tipo, $dia, $this->importeValorPesos($valor, $tipo));
            }
        }

        foreach ($rendbingo as $fila) {
            $importe = (float) ($fila->rendb_total_carton ?? 0)
                + (float) ($fila->rendb_sobrante ?? 0)
                + (float) ($fila->rendb_vales ?? 0);
            $this->sumarEn($porTipo, self::VALM_EFE_PESOS, $this->diaDeYmd((int) ($fila->rendb_fecha ?? 0)), $importe);
        }

        $canjeGastro = $gastronomia['Tk.Canje Gastronomia'] ?? $this->vectorDias();
        if (isset($porTipo['4'])) {
            foreach ($this->dias as $dia) {
                $porTipo['4'][$dia] = round(($porTipo['4'][$dia] ?? 0) - (float) ($canjeGastro[$dia] ?? 0), 2);
            }
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
        $totalIngresos = $this->vectorDias();
        foreach ($porTipo as $tipo => $porDia) {
            $etiqueta = $etiquetasTipo[$tipo] ?? 'Varios';
            foreach ($this->dias as $dia) {
                $importe = (float) ($porDia[$dia] ?? 0);
                $this->sumarEn($totales, $etiqueta, $dia, $importe);
                $totalIngresos[$dia] = round(($totalIngresos[$dia] ?? 0) + $importe, 2);
            }
        }

        $totales['Canje Gastronomia'] = $this->redondearVector($canjeGastro);
        foreach ($this->dias as $dia) {
            $totalIngresos[$dia] = round(($totalIngresos[$dia] ?? 0) + (float) ($canjeGastro[$dia] ?? 0), 2);
        }
        $totales['Total de Ingresos'] = $this->redondearVector($totalIngresos);

        return $totales;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<string, array<int, float>>  $abiertosNoEfectivo
     * @return array<string, array<int, float>>
     */
    private function agregarEgresos(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        array $valormae,
        array $abiertosNoEfectivo,
    ): array {
        $totales = [
            'Pesos Maco' => $this->vectorDias(),
            'Pesos Banco Macro' => $this->vectorDias(),
            'Pesos Banco Frances' => $this->vectorDias(),
            'Pesos Banco Provincia' => $this->vectorDias(),
            'Pesos Caja de seguridad' => $this->vectorDias(),
            'Dolares Maco' => $this->vectorDias(),
            'Dolares Banco Macro' => $this->vectorDias(),
            'Dolares Caja de seguridad' => $this->vectorDias(),
            'Euros Maco' => $this->vectorDias(),
            'Euros Banco Frances' => $this->vectorDias(),
            'Euros Caja de seguridad' => $this->vectorDias(),
            'Caudales en u$s' => $this->vectorDias(),
            'Caudales en Euros' => $this->vectorDias(),
            'Caudales en cripto' => $this->vectorDias(),
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

            $dia = $this->diaDeYmd((int) ($fila->reme_fecha ?? 0));
            $codigoValor = (int) ($fila->reme_cod_valor ?? 0);
            $tipo = $valormae[$codigoValor]['tipo'] ?? trim((string) ($fila->reme_tipo_valor ?? self::VALM_EFE_PESOS));
            $importe = (float) ($fila->reme_importe ?? 0);
            $cotizacion = (float) ($fila->reme_cotizacion ?? 0);
            $importePesos = $this->esTipoMe($tipo) && $cotizacion > 0
                ? $importe * $cotizacion
                : $importe;

            if ($tipo === self::VALM_EFE_DOLAR) {
                $this->sumarEn($totales, 'Caudales en u$s', $dia, $importe);
                $this->sumarEn($totales, $this->etiquetaEgresoDolar($destino), $dia, $importe);
                $this->sumarEn(
                    $totales,
                    $valormae[$codigoValor]['desc'] ?? 'Efectivo dolares',
                    $dia,
                    $importePesos,
                );
            } elseif ($tipo === self::VALM_EFE_EURO) {
                $this->sumarEn($totales, 'Caudales en Euros', $dia, $importe);
                $this->sumarEn($totales, $this->etiquetaEgresoEuro($destino), $dia, $importe);
            } elseif ($tipo === self::VALM_EFE_CRIPTO) {
                $this->sumarEn($totales, 'Caudales en cripto', $dia, $importe);
            } else {
                $this->sumarEn($totales, $this->etiquetaEgresoPesos($destino), $dia, $importePesos);
            }
        }

        $totalEgresos = $this->vectorDias();
        foreach ($abiertosNoEfectivo as $porDia) {
            foreach ($this->dias as $dia) {
                $totalEgresos[$dia] = round(($totalEgresos[$dia] ?? 0) + (float) ($porDia[$dia] ?? 0), 2);
            }
        }

        foreach ($totales as $etiqueta => $porDia) {
            if (str_starts_with($etiqueta, 'Caudales') || $etiqueta === 'Total de Egresos') {
                continue;
            }
            foreach ($this->dias as $dia) {
                $totalEgresos[$dia] = round(($totalEgresos[$dia] ?? 0) + (float) ($porDia[$dia] ?? 0), 2);
            }
        }
        $totales['Total de Egresos'] = $this->redondearVector($totalEgresos);

        return $totales;
    }

    /**
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  list<object>  $cabGastro
     * @param  array<int, true>  $opsMaquina
     * @param  array<int, int>  $fechaPorOper
     * @return array<string, array<int, float>>
     */
    private function acumularAbiertosNoEfectivo(
        array $valoresPorOper,
        array $valormae,
        array $cabGastro,
        array $opsMaquina,
        int $empresaId,
        array $fechaPorOper,
    ): array {
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            if (in_array($bloque, [self::BLOQUE_GASTRO, self::BLOQUE_ESTAC, self::BLOQUE_VENDING], true)) {
                $ops[(int) ($fila->rendg_nro_oper ?? 0)] = true;
            }
        }

        $totales = [];
        foreach ($ops as $nroOper => $_) {
            $diaOper = $this->diaDeYmd((int) ($fechaPorOper[$nroOper] ?? 0));
            foreach ($valoresPorOper[$nroOper] ?? [] as $valor) {
                $codigo = (int) ($valor->rendv_codigo ?? 0);
                if (! isset($valormae[$codigo])) {
                    continue;
                }
                $tipo = $valormae[$codigo]['tipo'];
                if ($this->esTipoEfectivo($tipo)) {
                    continue;
                }
                if ($codigo === 8) {
                    continue;
                }
                $dia = (int) ($valor->rendv_fecha ?? 0) > 0
                    ? $this->diaDeYmd((int) $valor->rendv_fecha)
                    : $diaOper;
                $this->sumarEn($totales, $valormae[$codigo]['desc'], $dia, $this->importeValorPesos($valor, $tipo));
            }
        }

        return $totales;
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

        if ($nombre !== '') {
            $esShow = str_contains($nombre, 'show');
            $esEgaPuro = str_contains($nombre, 'ega') && ! str_contains($nombre, 'gastro');
            if ($esShow || $esEgaPuro) {
                return $this->clasificacionSucursalCache[$key] = self::BLOQUE_OMITIR;
            }
            if (str_contains($nombre, 'vending')
                || (bool) preg_match('/maquina\s*\d+/', $nombre)
                || $sucursal >= 1000) {
                return $this->clasificacionSucursalCache[$key] = self::BLOQUE_VENDING;
            }
        } elseif ($sucursal >= 1000) {
            return $this->clasificacionSucursalCache[$key] = self::BLOQUE_VENDING;
        }

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
     * @param  list<object>  $cabGastro
     * @param  list<object>  $filasMaquina
     * @return array<int, int>
     */
    private function fechasPorOperacion(array $cabGastro, array $filasMaquina): array
    {
        $map = [];
        foreach ($cabGastro as $fila) {
            $nro = (int) ($fila->rendg_nro_oper ?? 0);
            if ($nro > 0) {
                $map[$nro] = (int) ($fila->rendg_fecha ?? 0);
            }
        }
        foreach ($filasMaquina as $fila) {
            $nro = (int) ($fila->rendm_nro_oper ?? 0);
            if ($nro > 0) {
                $map[$nro] = (int) ($fila->rendm_fecha ?? 0);
            }
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
     *   filas_ordenadas: list<array{etiqueta: string, valor: float, por_dia: array<int, float>, bloque: string, tipo_fila: string}>,
     *   dias: list<int>,
     *   saldo_inicial: ?float,
     *   saldo_final: ?float,
     *   bingo: array<string, float>,
     *   premios_bingo: array<string, float>,
     *   gastronomia: array<string, float>,
     *   estacionamiento: array<string, float>,
     *   vending: array<string, float>,
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
            'dias' => [],
            'saldo_inicial' => null,
            'saldo_final' => null,
            'bingo' => [],
            'premios_bingo' => [],
            'gastronomia' => [],
            'estacionamiento' => [],
            'vending' => [],
            'maquinas' => [],
            'apertura_medios' => [],
            'egresos' => [],
            'errores_bridge' => $errores,
        ];
    }
}
