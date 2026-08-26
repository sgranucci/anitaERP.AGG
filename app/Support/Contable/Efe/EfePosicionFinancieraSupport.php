<?php

namespace App\Support\Contable\Efe;

use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Remesa;
use App\Models\Caja\RendicionEstacionamientoCaja;
use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionMaquina;
use App\Models\Ventas\Puntoventa;
use App\Support\Caja\AnitaSync\RendicionEstacionamientoRendvalorCodigoSupport;
use App\Support\Caja\AnitaSync\RendicionGastronomiaRendvalorCodigoSupport;
use App\Support\Caja\PosicionFinancieraOrdenConceptoSupport;
use App\Support\Caja\CotizacionTesoreriaConsultaSupport;
use App\Support\Caja\PosicionFinancieraSaldoSupport;
use App\Support\Caja\Remesa\RemesaSupport;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Contable\CierreRendicionOrigenConsultaSupport;
use Carbon\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * Posición financiera mensual (solapa «pos fin …») — port de l-posfinanc.c.
 *
 * Impresión Anita: una columna por día del mes + «Total mensual», cortada por
 * unidad (bingo, gastronomía, estacionamiento, vending, máquinas) y luego
 * apertura de medios / egresos / saldos. Kiosco está comentado en el .c.
 * EGA y SHOW no se imprimen (sucursales con esos nombres se omiten).
 * Vending no existe en l-posfinanc.c: se agrega como bloque ERP (PV Maquina N
 * / sucursal ≥ 1000 / nombre vending) que antes se omitía.
 *
 * Conceptos de medios: solo cuentas de caja con uso (Gastronomía,
 * Estacionamiento, Rendición de máquinas) de la empresa. No se listan
 * valormae huérfanos (Pedido Ya, PlayUzu, etc. sin uso asignado).
 * Máquinas suma esos medios a los renglones fijos (VENTAS, CAJA, vales…).
 *
 * Fuentes:
 * - Hasta jul/2026: híbrido (Anita completa huecos; bingo Anita-first).
 * - Desde ago/2026: todo anitaERP (bingo, gastro, estac, vending, apertura).
 *   Anita solo si ese día no hay cierre C de máquinas o si falta la remesa.
 * - Saldos: posicion_financiera_saldo ERP → saldoposf Anita.
 * Sobrantes y canje de gastronomía de máquinas son solo informativos.
 */
class EfePosicionFinancieraSupport
{
    private const TURNOS_MAQUINA_EXCLUIDOS = ['M', 'T', 'N'];

    private const FECHA_CORTE_TURNO_MAQUINA = 20100300;

    /** Desde este YYYYMM la posición se arma con anitaERP (Anita = máquinas/remesas faltantes). */
    private const CORTE_FUENTE_ERP_YM = 202608;

    private bool $fuenteErpPura = false;

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

    private const BLOQUE_MEDIOS = 'medios';

    private const BLOQUE_EGRESOS = 'egresos';

    private const BLOQUE_SALDOS = 'saldos';

    private const BLOQUE_OMITIR = 'omitir';

    private const TIPO_CONCEPTO = 'concepto';

    private const TIPO_TITULO = 'titulo';

    private const TIPO_TOTAL = 'total';

    private const TIPO_INFORMATIVO = 'informativo';

    /** Abre el ABM origen en modo consulta, sin menú, desde la auditoría. */
    private const QUERY_CONSULTA = ['origen' => 'modal_consulta', 'vista' => 'consulta'];

    /** @var array<int, string> */
    private array $clasificacionSucursalCache = [];

    /** @var list<int> */
    private array $dias = [];

    public function __construct(
        private readonly EfeAnitaBridgeReader $bridgeReader = new EfeAnitaBridgeReader(),
        private readonly EfePosicionFinancieraFuenteErpSupport $fuenteErp = new EfePosicionFinancieraFuenteErpSupport(),
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
        $fechaDesde = (int) $inicioMes->format('Ymd');
        $fechaHasta = (int) $finMes->format('Ymd');
        $this->fuenteErpPura = (($anio * 100) + $mes) >= self::CORTE_FUENTE_ERP_YM;

        $errores = [];
        /** @var list<array{etiqueta: string, valor: float, por_dia: array<int, float>, bloque: string, tipo_fila: string}> $filasOrdenadas */
        $filasOrdenadas = [];

        $saldoCierreErp = PosicionFinancieraSaldoSupport::ultimoConfirmadoAnterior(
            $empresaId,
            $inicioMes->toDateString(),
        );
        $saldoInicial = $saldoCierreErp !== null
            ? round((float) $saldoCierreErp->saldo_final, 2)
            : $this->ultimoSaldoAnterior(
                $this->bridgeReader->listarSaldoposfAnteriores($empresaId, $fechaDesde),
                $fechaDesde,
            );
        $saldoInicialOrigen = $saldoCierreErp !== null ? 'erp' : 'anita';

        $rendbingo = $this->bridgeReader->listarRendbingo($empresaId, $fechaDesde, $fechaHasta);
        $concbingo = $this->bridgeReader->listarConcbingo();
        $rendpremio = $this->bridgeReader->listarRendpremio($fechaDesde, $fechaHasta);
        $bingoAnita = $this->agregarRendbingo($rendbingo);
        $premiosAnita = $this->agregarPremiosBingo(
            $bingoAnita,
            $concbingo,
            $rendbingo,
            $rendpremio,
        );
        $bingoErp = $this->fuenteErp->bingo($empresaId, $inicioMes, $finMes, $this->dias);
        $diasBingoAnita = [];
        foreach ($rendbingo as $filaBingoAnita) {
            $diaAnita = $this->diaDeYmd((int) ($filaBingoAnita->rendb_fecha ?? 0));
            if ($diaAnita > 0) {
                $diasBingoAnita[$diaAnita] = true;
            }
        }
        // Fechas ERP de bingo a veces no coinciden con Anita (misma venta en otro día):
        // no pisar ni duplicar montos ya presentes en Anita del mes.
        $diasBingoErp = $bingoErp['dias'];
        foreach ($diasBingoErp as $diaErp => $_) {
            $ventaErp = (float) ($bingoErp['base']['VENTA BINGO'][$diaErp] ?? 0);
            if (abs($ventaErp) < 0.01) {
                continue;
            }
            foreach ($bingoAnita['VENTA BINGO'] ?? [] as $diaAnita => $ventaAnita) {
                if (abs((float) $ventaAnita - $ventaErp) < 0.01) {
                    unset($diasBingoErp[$diaErp]);
                    break;
                }
            }
        }
        $premiosErp = $this->normalizarEtiquetasPremiosBingo($bingoErp['premios']);
        if ($this->fuenteErpPura) {
            $bingo = $bingoErp['base'];
            $premios = $premiosErp;
        } else {
            $bingo = $this->fuenteErp->mergePorDiaAnitaPrimero(
                $bingoAnita,
                $bingoErp['base'],
                $diasBingoAnita,
                $diasBingoErp,
                $this->dias,
            );
            $premios = $this->fuenteErp->mergePorDiaAnitaPrimero(
                $premiosAnita,
                $premiosErp,
                $diasBingoAnita,
                $diasBingoErp,
                $this->dias,
            );
        }

        $valormae = $this->indexarValormae($this->bridgeReader->listarValormae($empresaId));
        $rendvalor = $this->bridgeReader->listarRendvalor($fechaDesde, $fechaHasta);
        $valoresPorOper = $this->indexarRendvalorPorOper($rendvalor);

        $cabGastro = $this->bridgeReader->listarRendgastro($empresaId, $fechaDesde, $fechaHasta);
        $codigosUsoGastronomia = $this->codigosValormaePorUso(
            $empresaId,
            'Gastronomia',
            RendicionGastronomiaRendvalorCodigoSupport::class,
        );
        $codigosUsoEstacionamiento = $this->codigosValormaePorUso(
            $empresaId,
            'Estacionamiento',
            RendicionEstacionamientoRendvalorCodigoSupport::class,
        );
        $codigosUsoMaquinas = $this->codigosValormaePorEtiquetaDeUso(
            $empresaId,
            PosicionFinancieraOrdenConceptoSupport::USO_MAQUINAS,
            $valormae,
        );
        $codigosMediosOperativos = $codigosUsoGastronomia
            + $codigosUsoEstacionamiento
            + $codigosUsoMaquinas;
        $gastronomiaAnita = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $codigosUsoGastronomia,
            $empresaId,
            self::BLOQUE_GASTRO,
            'GASTRONOMIA Z',
            'Total Gastronomia',
        );
        $estacionamientoAnita = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $codigosUsoEstacionamiento,
            $empresaId,
            self::BLOQUE_ESTAC,
            'ESTACIONAMIENTO Z',
            'Total Estacionamiento',
            redondeoNegado: true,
        );
        $vendingAnita = $this->agregarBloqueGastroEstac(
            $cabGastro,
            $valoresPorOper,
            $valormae,
            $codigosUsoGastronomia,
            $empresaId,
            self::BLOQUE_VENDING,
            'VENDING Z',
            'Total Vending',
        );

        $clasificar = fn (int $emp, int $suc) => $this->clasificarSucursal($emp, $suc);
        $gastroErp = $this->fuenteErp->gastroEstac(
            $empresaId, $inicioMes, $finMes, $this->dias,
            self::BLOQUE_GASTRO, 'GASTRONOMIA Z', 'Total Gastronomia',
            $valormae, $codigosUsoGastronomia, $clasificar,
        );
        // Anita guarda rendg_tot_redondeo de estac ya negativo; ERP guarda el
        // centavo en positivo (igual que gastronomía). No negar la fuente ERP.
        $estacErp = $this->fuenteErp->gastroEstac(
            $empresaId, $inicioMes, $finMes, $this->dias,
            self::BLOQUE_ESTAC, 'ESTACIONAMIENTO Z', 'Total Estacionamiento',
            $valormae, $codigosUsoEstacionamiento, $clasificar,
        );
        $vendingErp = $this->fuenteErp->gastroEstac(
            $empresaId, $inicioMes, $finMes, $this->dias,
            self::BLOQUE_VENDING, 'VENDING Z', 'Total Vending',
            $valormae, $codigosUsoGastronomia, $clasificar,
        );
        if ($this->fuenteErpPura) {
            $gastronomia = $this->fuenteErp->recalcularTotalBloque(
                $gastroErp['filas'],
                'GASTRONOMIA Z',
                'Total Gastronomia',
                $this->dias,
            );
            $estacionamiento = $this->fuenteErp->recalcularTotalBloque(
                $estacErp['filas'],
                'ESTACIONAMIENTO Z',
                'Total Estacionamiento',
                $this->dias,
            );
            $vending = $this->fuenteErp->recalcularTotalBloque(
                $vendingErp['filas'],
                'VENDING Z',
                'Total Vending',
                $this->dias,
            );
        } else {
            $gastronomia = $this->fuenteErp->recalcularTotalBloque(
                $this->fuenteErp->mergePorDiaCompletarHuecos(
                    $gastronomiaAnita, $gastroErp['filas'], $gastroErp['dias'], $this->dias,
                ),
                'GASTRONOMIA Z',
                'Total Gastronomia',
                $this->dias,
                $gastronomiaAnita,
            );
            $estacionamiento = $this->fuenteErp->recalcularTotalBloque(
                $this->fuenteErp->mergePorDiaCompletarHuecos(
                    $estacionamientoAnita, $estacErp['filas'], $estacErp['dias'], $this->dias,
                ),
                'ESTACIONAMIENTO Z',
                'Total Estacionamiento',
                $this->dias,
                $estacionamientoAnita,
            );
            $vending = $this->fuenteErp->recalcularTotalBloque(
                $this->fuenteErp->mergePorDiaCompletarHuecos(
                    $vendingAnita, $vendingErp['filas'], $vendingErp['dias'], $this->dias,
                ),
                'VENDING Z',
                'Total Vending',
                $this->dias,
                $vendingAnita,
            );
        }

        $gastronomia = PosicionFinancieraOrdenConceptoSupport::reordenarBloqueGastro(
            $gastronomia,
            $empresaId,
            $valormae,
            $codigosUsoGastronomia,
            RendicionGastronomiaRendvalorCodigoSupport::class,
            'GASTRONOMIA Z',
            'Total Gastronomia',
        );
        $estacionamiento = PosicionFinancieraOrdenConceptoSupport::reordenarBloqueGastro(
            $estacionamiento,
            $empresaId,
            $valormae,
            $codigosUsoEstacionamiento,
            RendicionEstacionamientoRendvalorCodigoSupport::class,
            'ESTACIONAMIENTO Z',
            'Total Estacionamiento',
        );
        $vending = PosicionFinancieraOrdenConceptoSupport::reordenarBloqueGastro(
            $vending,
            $empresaId,
            $valormae,
            $codigosUsoGastronomia,
            RendicionGastronomiaRendvalorCodigoSupport::class,
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
        if ($apgastoDesc === []) {
            $apgastoDesc = $this->fuenteErp->apgastoDescDesdeErp();
        }
        $gastosPorOper = $this->cargarGastosMaquina(array_keys($opsMaquina));

        $maquinasBaseAnita = $this->agregarRendmaquina($filasMaquina);
        $maquinasMediosAnita = $this->agregarMediosPorOperaciones(
            array_keys($opsMaquina),
            $valoresPorOper,
            $valormae,
            $fechaPorOper,
            $codigosUsoMaquinas,
        );
        $maquinasGastosAnita = $this->agregarGastosMaquina(
            array_keys($opsMaquina),
            $gastosPorOper,
            $apgastoDesc,
            $fechaPorOper,
        );
        $maquinasErp = $this->fuenteErp->maquinasCompletas(
            $empresaId, $inicioMes, $finMes, $this->dias, $valormae, $apgastoDesc,
        );
        $etiquetasMediosMaquina = $this->etiquetasMediosDeUso(
            $empresaId,
            PosicionFinancieraOrdenConceptoSupport::USO_MAQUINAS,
            $valormae,
            $codigosUsoMaquinas,
        );
        $etiquetasMediosOperativos = $etiquetasMediosMaquina
            + $this->etiquetasMediosDeUso(
                $empresaId,
                PosicionFinancieraOrdenConceptoSupport::USO_GASTRONOMIA,
                $valormae,
                $codigosUsoGastronomia,
            )
            + $this->etiquetasMediosDeUso(
                $empresaId,
                PosicionFinancieraOrdenConceptoSupport::USO_ESTACIONAMIENTO,
                $valormae,
                $codigosUsoEstacionamiento,
            );
        $maquinasErp['medios'] = $this->filtrarMapaPorEtiquetas(
            $maquinasErp['medios'] ?? [],
            $etiquetasMediosMaquina,
        );
        $maquinasBase = $this->fuenteErp->mergePorDia(
            $maquinasBaseAnita, $maquinasErp['base'], $maquinasErp['dias'], $this->dias,
        );
        // Antes del corte ERP: dif_caja ERP a veces viene corrupto; si Anita tiene
        // el día C, preferir Anita en esas filas (no afectan Total maquinas).
        if (! $this->fuenteErpPura) {
            foreach ($maquinasErp['dias'] as $diaErp => $_) {
                $cajaAnita = (float) ($maquinasBaseAnita['MAQUINAS CAJA'][$diaErp] ?? 0);
                if (abs($cajaAnita) < 0.01) {
                    continue;
                }
                $maquinasBase['Diferencia de caja'][$diaErp] = round(
                    (float) ($maquinasBaseAnita['Diferencia de caja'][$diaErp] ?? 0),
                    2,
                );
                $maquinasBase['Caja en transito'][$diaErp] = round(
                    (float) ($maquinasBaseAnita['Caja en transito'][$diaErp] ?? 0),
                    2,
                );
                $maquinasBase['MAQUINAS VENTAS'][$diaErp] = round(
                    (float) ($maquinasBaseAnita['MAQUINAS VENTAS'][$diaErp] ?? 0),
                    2,
                );
            }
        }
        $maquinasMedios = PosicionFinancieraOrdenConceptoSupport::reordenarMapaMedios(
            $empresaId,
            $this->filtrarMapaPorEtiquetas(
                $this->fuenteErp->mergePorDia(
                    $maquinasMediosAnita, $maquinasErp['medios'], $maquinasErp['dias'], $this->dias,
                ),
                $etiquetasMediosMaquina,
            ),
            $valormae,
        );
        $maquinasGastos = $this->fuenteErp->mergePorDia(
            $maquinasGastosAnita, $maquinasErp['gastos'], $maquinasErp['dias'], $this->dias,
        );
        $descsMedio = [];
        foreach ($valormae as $meta) {
            $descsMedio[$meta['desc']] = true;
        }
        // Medios ERP (etiquetas Anita o fallback) no deben restarse del Total.
        foreach (array_keys($maquinasErp['medios']) as $descMedioErp) {
            $descsMedio[$descMedioErp] = true;
        }
        $maquinas = array_merge($maquinasBase, $maquinasMedios, $maquinasGastos);
        $maquinas['Total maquinas'] = $this->totalMaquinasPorDia($maquinas, $descsMedio);
        $maquinasInformativos = $this->informativosMaquinas($empresaId, $inicioMes, $finMes, $filasMaquina);

        $apertura = $this->fuenteErpPura
            ? $this->agregarAperturaMediosErpPuro(
                $bingoErp,
                $premios,
                $gastronomia,
                $estacionamiento,
                $maquinasErp,
                $maquinasMediosAnita,
                $valormae,
            )
            : $this->agregarAperturaMedios(
                $valoresPorOper,
                $valormae,
                $cabGastro,
                $opsMaquina,
                $empresaId,
                $rendbingo,
                $rendpremio,
                $concbingo,
                $gastronomia,
                $fechaPorOper,
            );

        $abiertosNoEfectivo = $this->fuenteErpPura
            ? $this->acumularAbiertosNoEfectivoErpPuro(
                $gastronomia,
                $estacionamiento,
                $maquinasErp,
                $maquinasMediosAnita,
                $valormae,
            )
            : $this->acumularAbiertosNoEfectivo(
                $valoresPorOper,
                $valormae,
                $cabGastro,
                $opsMaquina,
                $empresaId,
                $fechaPorOper,
                $codigosMediosOperativos,
            );
        $abiertosNoEfectivo = $this->filtrarMapaPorEtiquetas(
            $abiertosNoEfectivo,
            $etiquetasMediosOperativos,
        );
        $egresos = $this->agregarEgresos(
            $empresaId,
            $fechaDesde,
            $fechaHasta,
            $abiertosNoEfectivo,
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
        $saldoFinal = $saldoFinalPorDia[$finMes->day] ?? $saldoInicial;

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
            $this->sumarMapas(array_merge(
                [
                    $bingo['VENTA BINGO'] ?? $this->vectorDias(),
                ],
                array_values($premios),
                [
                    $bingo['SOBRANTES'] ?? $this->vectorDias(),
                    $bingo['VALES'] ?? $this->vectorDias(),
                    $bingo['REDONDEO'] ?? $this->vectorDias(),
                ],
            )),
            self::BLOQUE_BINGO,
            self::TIPO_TOTAL,
        );

        $this->pushTitulo($filasOrdenadas, 'Gastronomía', self::BLOQUE_GASTRO);
        $this->pushMapa($filasOrdenadas, $gastronomia, self::BLOQUE_GASTRO, 'Total Gastronomia');

        $this->pushTitulo($filasOrdenadas, 'Estacionamiento', self::BLOQUE_ESTAC);
        $this->pushMapa($filasOrdenadas, $estacionamiento, self::BLOQUE_ESTAC, 'Total Estacionamiento');

        $this->pushTitulo($filasOrdenadas, 'Vending', self::BLOQUE_VENDING);
        $this->pushMapa($filasOrdenadas, $vending, self::BLOQUE_VENDING, 'Total Vending');

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
        $this->pushFila($filasOrdenadas, 'Pago 24', $maquinasBase['Pago 24'] ?? $this->vectorDias(), self::BLOQUE_MAQUINAS);
        $this->pushFila($filasOrdenadas, 'Total maquinas', $maquinas['Total maquinas'], self::BLOQUE_MAQUINAS, self::TIPO_TOTAL);
        $this->pushFila($filasOrdenadas, 'Sobrantes', $maquinasInformativos['Sobrantes'], self::BLOQUE_MAQUINAS, self::TIPO_INFORMATIVO);
        $this->pushFila($filasOrdenadas, 'Canje de gastronomía', $maquinasInformativos['Canje de gastronomía'], self::BLOQUE_MAQUINAS, self::TIPO_INFORMATIVO);

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
            'saldo_inicial_origen' => $saldoInicialOrigen,
            'saldo_cierre_anterior_id' => $saldoCierreErp?->id,
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
     * Detalle de auditoría para una celda diaria de la posición financiera.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function auditarDato(array $filtros, int $dia, string $bloque, string $etiqueta): array
    {
        $resultado = $this->generar($filtros);
        $filaSeleccionada = null;
        foreach ($resultado['filas_ordenadas'] ?? [] as $fila) {
            if (($fila['bloque'] ?? '') === $bloque && ($fila['etiqueta'] ?? '') === $etiqueta) {
                $filaSeleccionada = $fila;
                break;
            }
        }

        if ($filaSeleccionada === null
            || ($dia !== 0 && ! in_array($dia, $resultado['dias'] ?? [], true))) {
            throw new InvalidArgumentException('No se encontró el dato solicitado para auditar.');
        }

        $componentes = $this->componentesAuditoria(
            $resultado['filas_ordenadas'] ?? [],
            $filaSeleccionada,
            $dia,
        );
        if ($componentes === [] && $bloque === self::BLOQUE_BINGO) {
            foreach ($this->bridgeReader->listarConcbingoExtendido() as $concepto) {
                $tipo = trim((string) ($concepto->concb_tipo_conc ?? ''));
                $porcentaje = (float) ($concepto->concb_porcentaje ?? 0);
                if (trim((string) ($concepto->concb_desc ?? '')) !== $etiqueta
                    || ! in_array($tipo, ['0', '1'], true)
                    || $porcentaje <= 0) {
                    continue;
                }
                foreach ($resultado['filas_ordenadas'] ?? [] as $fila) {
                    if (($fila['etiqueta'] ?? '') === 'VENTA BINGO') {
                        $componentes[] = [
                            'etiqueta' => 'VENTA BINGO',
                            'importe' => (float) ($fila['por_dia'][$dia] ?? 0),
                            'operacion' => '× -'.rtrim(rtrim(number_format($porcentaje, 4, '.', ''), '0'), '.').'%',
                        ];
                        break;
                    }
                }
                break;
            }
        }
        $fecha = $dia === 0
            ? null
            : Carbon::createFromDate(
                (int) ($filtros['anio'] ?? 0),
                (int) ($filtros['mes'] ?? 0),
                $dia,
            );

        return [
            'etiqueta' => $etiqueta,
            'bloque' => $bloque,
            'fecha' => $fecha?->format('d/m/Y')
                ?? str_pad((string) ($filtros['mes'] ?? 0), 2, '0', STR_PAD_LEFT).'/'.(int) ($filtros['anio'] ?? 0).' · total mensual',
            'fecha_ymd' => $fecha?->toDateString(),
            'importe' => $dia === 0
                ? (float) ($filaSeleccionada['valor'] ?? 0)
                : (float) (($filaSeleccionada['por_dia'][$dia] ?? 0)),
            'tipo_fila' => (string) ($filaSeleccionada['tipo_fila'] ?? self::TIPO_CONCEPTO),
            'componentes' => $componentes,
            'registros' => $fecha === null
                ? []
                : $this->registrosAuditoriaFuente(
                    (int) ($filtros['empresa_id'] ?? 0),
                    (int) $fecha->format('Ymd'),
                    $bloque,
                    $etiqueta,
                ),
            'fuentes' => $this->fuentesAuditoriaPorBloque($bloque, $etiqueta),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @param  array<string, mixed>  $seleccionada
     * @return list<array{etiqueta: string, importe: float, operacion: string}>
     */
    private function componentesAuditoria(array $filas, array $seleccionada, int $dia): array
    {
        $etiqueta = (string) ($seleccionada['etiqueta'] ?? '');
        $bloque = (string) ($seleccionada['bloque'] ?? '');

        if ($dia === 0) {
            $componentes = [];
            foreach ($seleccionada['por_dia'] ?? [] as $numeroDia => $importe) {
                if (abs((float) $importe) < 0.005) {
                    continue;
                }
                $componentes[] = [
                    'etiqueta' => 'Día '.str_pad((string) $numeroDia, 2, '0', STR_PAD_LEFT),
                    'importe' => (float) $importe,
                    'operacion' => '+',
                ];
            }

            return $componentes;
        }

        if ($etiqueta === 'Saldo final') {
            $componentes = [];
            foreach ([
                'Saldo inicial' => '+',
                'Total de Ingresos' => '+',
                'Total de Egresos' => '-',
            ] as $buscada => $operacion) {
                foreach ($filas as $fila) {
                    if (($fila['etiqueta'] ?? '') === $buscada) {
                        $componentes[] = [
                            'etiqueta' => $buscada,
                            'importe' => (float) ($fila['por_dia'][$dia] ?? 0),
                            'operacion' => $operacion,
                        ];
                        break;
                    }
                }
            }

            return $componentes;
        }

        if ($etiqueta === 'Total maquinas') {
            $descsGasto = array_flip($this->indexarApgasto($this->bridgeReader->listarApgasto()));
            $componentes = [];
            foreach ($filas as $fila) {
                if (($fila['bloque'] ?? '') !== self::BLOQUE_MAQUINAS) {
                    continue;
                }
                $etiquetaFila = (string) ($fila['etiqueta'] ?? '');
                $importe = (float) ($fila['por_dia'][$dia] ?? 0);
                if ($etiquetaFila === 'MAQUINAS CAJA') {
                    $componentes[] = [
                        'etiqueta' => $etiquetaFila,
                        'importe' => $importe,
                        'operacion' => '+',
                    ];

                    continue;
                }
                $seResta = in_array($etiquetaFila, ['Vales fondo fijo', 'Vales administracion'], true)
                    || isset($descsGasto[$etiquetaFila]);
                if (! $seResta || abs($importe) < 0.005) {
                    continue;
                }
                $componentes[] = [
                    'etiqueta' => $etiquetaFila,
                    'importe' => $importe,
                    'operacion' => '-',
                ];
            }

            if ($componentes !== []) {
                return $componentes;
            }
        }

        if (($seleccionada['tipo_fila'] ?? '') !== self::TIPO_TOTAL
            && preg_match('/^total(\s|$)/u', mb_strtolower(trim($etiqueta))) !== 1) {
            return [];
        }

        $componentes = [];
        foreach ($filas as $fila) {
            if (($fila['bloque'] ?? '') !== $bloque
                || ($fila['tipo_fila'] ?? '') !== self::TIPO_CONCEPTO
                || str_ends_with(mb_strtoupper(trim((string) ($fila['etiqueta'] ?? ''))), ' Z')) {
                continue;
            }
            $importe = (float) ($fila['por_dia'][$dia] ?? 0);
            if (abs($importe) < 0.005) {
                continue;
            }
            $componentes[] = [
                'etiqueta' => (string) $fila['etiqueta'],
                'importe' => $importe,
                'operacion' => '+',
            ];
        }

        return $componentes;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function registrosAuditoriaFuente(int $empresaId, int $fechaYmd, string $bloque, string $etiqueta): array
    {
        if ($bloque === self::BLOQUE_SALDOS) {
            $fecha = Carbon::createFromFormat('Ymd', (string) $fechaYmd);
            $saldo = PosicionFinancieraSaldoSupport::ultimoConfirmadoAnterior($empresaId, $fecha->toDateString());

            return $saldo === null ? [] : [[
                'fuente' => 'posicion_financiera_saldo',
                'referencia' => '#'.$saldo->id,
                'detalle' => 'Cierre '.$saldo->fecha_cierre?->format('d/m/Y').' · '.$saldo->origen,
                'importe' => (float) $saldo->saldo_final,
            ]];
        }

        if ($bloque === self::BLOQUE_MAQUINAS && in_array($etiqueta, ['Sobrantes', 'Canje de gastronomía'], true)) {
            $campo = $etiqueta === 'Sobrantes' ? 'sobrantes' : 'impuesto_pago';
            $rendiciones = RendicionMaquina::query()
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', Carbon::createFromFormat('Ymd', (string) $fechaYmd)->toDateString())
                ->where(function ($query) {
                    $query->whereNull('estado')->orWhere('estado', '!=', RendicionMaquina::ESTADO_ANULADA);
                })
                ->orderBy('id')
                ->get(['id', 'fecha', 'turno', 'inputs_json']);
            $completo = $rendiciones->filter(function (RendicionMaquina $rendicion) {
                try {
                    return RendicionMaquinaTurno::normalizar((string) $rendicion->turno)
                        === RendicionMaquinaTurno::COMPLETO;
                } catch (InvalidArgumentException) {
                    return false;
                }
            })->last();
            if ($completo !== null) {
                $rendiciones = collect([$completo]);
            } else {
                $rendiciones = $rendiciones->filter(function (RendicionMaquina $rendicion) {
                    try {
                        RendicionMaquinaTurno::normalizar((string) $rendicion->turno);

                        return true;
                    } catch (InvalidArgumentException) {
                        return false;
                    }
                });
            }

            // Sin rendición ERP del día se sigue con rendmaquina de Anita.
            if ($rendiciones->isNotEmpty()) {
                return $rendiciones
                    ->map(function (RendicionMaquina $rendicion) use ($campo) {
                        $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];

                        return [
                            'fuente' => 'rendicion_maquina ERP',
                            'referencia' => '#'.$rendicion->id,
                            'detalle' => 'Turno '.$rendicion->turno,
                            'importe' => $this->valorInputRendicionMaquina($inputs, $campo),
                            'url' => route('editar_rendicion_maquina', array_merge(
                                ['id' => $rendicion->id],
                                self::QUERY_CONSULTA,
                            )),
                        ];
                    })
                    ->all();
            }
        }

        if ($bloque === self::BLOQUE_EGRESOS
            && preg_match('/^(Pesos|Dolares|Euros) /', $etiqueta) === 1) {
            $registros = [];
            $firmasErp = [];
            $remesasErp = Remesa::query()
                ->with('lineasDestino.cuentacaja')
                ->where('empresa_id', $empresaId)
                ->whereDate('fecha', Carbon::createFromFormat('Ymd', (string) $fechaYmd)->toDateString())
                ->where('tipo', RemesaSupport::TIPO_EXTERNA)
                ->where('estado', RemesaSupport::ESTADO_CONFIRMADA)
                ->orderBy('numero')
                ->get();

            foreach ($remesasErp as $remesa) {
                foreach ($remesa->lineasDestino as $linea) {
                    $cuenta = $linea->cuentacaja;
                    $importe = round((float) ($linea->monto ?? 0), 2);
                    if ($cuenta === null || $importe <= 0) {
                        continue;
                    }
                    $codigoMoneda = CotizacionTesoreriaConsultaSupport::codigoAnitaDesdeMonedaId(
                        (int) ($cuenta->moneda_id ?: 1),
                    ) ?? 1;
                    $destino = $this->destinoRememaeDesdeCuenta($cuenta);
                    if ($this->etiquetaEgresoCuentaErp($cuenta, $destino, $codigoMoneda) !== $etiqueta) {
                        continue;
                    }
                    $firmasErp[$this->firmaRemesa($fechaYmd, $destino, $codigoMoneda, $importe)] =
                        ($firmasErp[$this->firmaRemesa($fechaYmd, $destino, $codigoMoneda, $importe)] ?? 0) + 1;
                    $registros[] = [
                        'fuente' => 'remesa ERP',
                        'referencia' => '#'.$remesa->numero,
                        'detalle' => (string) ($cuenta->nombre ?? 'Destino'),
                        'importe' => $importe,
                        'url' => route('editar_remesa', $remesa->id),
                    ];
                }
            }

            foreach ($this->bridgeReader->listarRememae($empresaId, $fechaYmd, $fechaYmd) as $fila) {
                if (strtoupper(trim((string) ($fila->remem_tipo_remesa ?? ''))) !== RemesaSupport::TIPO_EXTERNA) {
                    continue;
                }
                $destino = trim((string) ($fila->remem_destino ?? self::REMEM_MACO));
                $codigoMoneda = (int) ($fila->remem_cod_mon ?? 1);
                $importe = round((float) ($fila->remem_importe ?? 0), 2);
                $etiquetaFila = $codigoMoneda === 2
                    ? $this->etiquetaEgresoDolar($destino)
                    : ($codigoMoneda === 3
                        ? $this->etiquetaEgresoEuro($destino)
                        : $this->etiquetaEgresoPesos($destino));
                if ($destino === self::REMEM_PAGOFACIL || $etiquetaFila !== $etiqueta || $importe <= 0) {
                    continue;
                }
                $firma = $this->firmaRemesa($fechaYmd, $destino, $codigoMoneda, $importe);
                if (($firmasErp[$firma] ?? 0) > 0) {
                    $firmasErp[$firma]--;
                    continue;
                }
                $registros[] = [
                    'fuente' => 'rememae Anita',
                    'referencia' => '#'.(int) ($fila->remem_nro_remesa ?? 0),
                    'detalle' => 'Destino '.$destino.' · moneda '.$codigoMoneda,
                    'importe' => $importe,
                ];
            }

            return array_slice($registros, 0, 100);
        }

        $valormae = $this->indexarValormae($this->bridgeReader->listarValormae($empresaId));
        $operacionesPermitidas = $this->operacionesAuditoriaBloque($empresaId, $fechaYmd, $bloque);
        $registros = [];
        foreach ($this->bridgeReader->listarRendvalor($fechaYmd, $fechaYmd) as $valor) {
            $codigo = (int) ($valor->rendv_codigo ?? 0);
            $nroOper = (int) ($valor->rendv_nro_oper ?? 0);
            if (($valormae[$codigo]['desc'] ?? '') !== $etiqueta
                || ($operacionesPermitidas !== [] && ! isset($operacionesPermitidas[$nroOper]))) {
                continue;
            }
            $registros[] = [
                'fuente' => 'rendvalor Anita',
                'referencia' => 'Oper. '.$nroOper,
                'detalle' => 'Código '.$codigo.' · tipo '.($valormae[$codigo]['tipo'] ?? ''),
                'importe' => $this->importeValorPesos($valor, (string) ($valormae[$codigo]['tipo'] ?? '')),
                'url' => $this->urlRendicionErpPorOperacion($empresaId, $bloque, $nroOper),
            ];
        }

        if ($registros !== []) {
            return array_slice($registros, 0, 100);
        }

        if (in_array($bloque, [self::BLOQUE_GASTRO, self::BLOQUE_ESTAC, self::BLOQUE_VENDING], true)) {
            foreach ($this->bridgeReader->listarRendgastro($empresaId, $fechaYmd, $fechaYmd) as $fila) {
                if ($this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0)) !== $bloque) {
                    continue;
                }
                $registros[] = [
                    'fuente' => 'rendgastro Anita',
                    'referencia' => 'Oper. '.(int) ($fila->rendg_nro_oper ?? 0),
                    'detalle' => 'Sucursal '.(int) ($fila->rendg_sucursal ?? 0).' · turno '.($fila->rendg_turno ?? ''),
                    'importe' => (float) ($fila->rendg_total_z ?? 0),
                    'url' => $this->urlRendicionErpPorOperacion(
                        $empresaId,
                        $bloque,
                        (int) ($fila->rendg_nro_oper ?? 0),
                    ),
                ];
            }
        } elseif ($bloque === self::BLOQUE_MAQUINAS) {
            $filasMaquina = $this->bridgeReader->listarRendmaquina($empresaId, $fechaYmd, $fechaYmd);
            $conceptoApgasto = array_flip($this->indexarApgasto($this->bridgeReader->listarApgasto()));
            $gastosPorOper = [];
            if (isset($conceptoApgasto[$etiqueta])) {
                $operaciones = [];
                foreach ($filasMaquina as $fila) {
                    if ($this->incluirRendmaquina($fila)) {
                        $operaciones[] = (int) ($fila->rendm_nro_oper ?? 0);
                    }
                }
                $gastosPorOper = $this->cargarGastosMaquina($operaciones);
            }

            foreach ($filasMaquina as $fila) {
                if (! $this->incluirRendmaquina($fila)) {
                    continue;
                }
                $nroOper = (int) ($fila->rendm_nro_oper ?? 0);
                $importe = isset($conceptoApgasto[$etiqueta])
                    ? (float) ($gastosPorOper[$nroOper][$conceptoApgasto[$etiqueta]] ?? 0)
                    : $this->importeAuditoriaRendmaquina($fila, $etiqueta);
                $registros[] = [
                    'fuente' => 'rendmaquina Anita',
                    'referencia' => 'Oper. '.$nroOper,
                    'detalle' => 'Turno '.($fila->rendm_turno ?? ''),
                    'importe' => $importe,
                    'url' => $this->urlRendicionMaquinaErp(
                        $empresaId,
                        (int) ($fila->rendm_fecha ?? 0),
                        isset($fila->rendm_turno) ? (string) $fila->rendm_turno : null,
                    ),
                ];
            }
        } elseif ($bloque === self::BLOQUE_BINGO) {
            $rendbingo = $this->bridgeReader->listarRendbingo($empresaId, $fechaYmd, $fechaYmd);
            if (in_array($etiqueta, ['VENTA BINGO', 'SOBRANTES', 'VALES', 'REDONDEO'], true)) {
                foreach ($rendbingo as $fila) {
                    $importe = match ($etiqueta) {
                        'VENTA BINGO' => (float) ($fila->rendb_total_carton ?? 0),
                        'SOBRANTES' => (float) ($fila->rendb_sobrante ?? 0),
                        'VALES' => (float) ($fila->rendb_vales ?? 0),
                        'REDONDEO' => (float) ($fila->rendb_redondeo ?? 0),
                    };
                    $registros[] = [
                        'fuente' => 'rendbingo Anita',
                        'referencia' => 'Oper. '.(int) ($fila->rendb_nro_oper ?? 0),
                        'detalle' => 'Turno '.($fila->rendb_turno ?? ''),
                        'importe' => $importe,
                        'url' => $this->urlRendicionErpPorOperacion(
                            $empresaId,
                            $bloque,
                            (int) ($fila->rendb_nro_oper ?? 0),
                        ),
                    ];
                }
            } else {
                $operaciones = [];
                foreach ($rendbingo as $fila) {
                    $operaciones[(int) ($fila->rendb_nro_oper ?? 0).'|'.trim((string) ($fila->rendb_tipo_oper ?? ''))] = true;
                }
                $conceptos = [];
                foreach ($this->bridgeReader->listarConcbingoExtendido() as $concepto) {
                    $conceptos[(int) ($concepto->concb_concepto ?? 0)] = $concepto;
                }
                foreach ($this->bridgeReader->listarRendpremio($fechaYmd, $fechaYmd) as $premio) {
                    $clave = (int) ($premio->rendp_nro_oper ?? 0).'|'.trim((string) ($premio->rendp_tipo_oper ?? ''));
                    $concepto = $conceptos[(int) ($premio->rendp_concepto ?? 0)] ?? null;
                    if (! isset($operaciones[$clave])
                        || trim((string) ($concepto->concb_desc ?? '')) !== $etiqueta) {
                        continue;
                    }
                    $tipo = trim((string) ($concepto->concb_tipo_conc ?? ''));
                    $importe = in_array($tipo, ['3', '4', '5'], true)
                        ? (float) ($premio->rendp_real ?? 0)
                        : (float) ($premio->rendp_pagado ?? 0);
                    $registros[] = [
                        'fuente' => 'rendpremio Anita',
                        'referencia' => 'Oper. '.(int) ($premio->rendp_nro_oper ?? 0),
                        'detalle' => 'Concepto '.(int) ($premio->rendp_concepto ?? 0),
                        'importe' => -1 * $importe,
                        'url' => $this->urlRendicionErpPorOperacion(
                            $empresaId,
                            $bloque,
                            (int) ($premio->rendp_nro_oper ?? 0),
                        ),
                    ];
                }
            }
        }

        return array_slice($registros, 0, 100);
    }

    private function importeAuditoriaRendmaquina(object $fila, string $etiqueta): float
    {
        $venta = $this->ventaMaquinasDesdeFila($fila);
        $deposito = (float) ($fila->rendm_deposito ?? 0);
        $diferencia = (float) ($fila->rendm_dif_caja ?? 0);
        $variacion = (float) ($fila->rendm_variacion_ff ?? 0);

        return match ($etiqueta) {
            'MAQUINAS VENTAS' => $venta,
            'MAQUINAS CAJA', 'Total maquinas' => $deposito,
            'Vales fondo fijo' => (float) ($fila->rendm_reintegros ?? 0),
            'Vales administracion' => (float) ($fila->rendm_vales ?? 0),
            'Variacion de FF' => $variacion,
            'Diferencia de caja' => $diferencia + $variacion,
            'Caja en transito' => $venta > $deposito
                ? ($venta + $diferencia) - $deposito
                : ($deposito - ($venta + $diferencia)) * -1,
            'Pago 24' => (float) ($fila->rendm_vtaant_gast ?? 0),
            'Sobrantes' => (float) ($fila->rendm_sobrantes ?? 0),
            'Canje de gastronomía' => (float) ($fila->rendm_canje_gastro ?? 0),
            default => $deposito,
        };
    }

    /**
     * Resuelve la rendición ERP que originó una operación Anita para poder
     * abrirla desde la auditoría. Devuelve null si el usuario no tiene permiso
     * o si esa rendición todavía no vive en el ERP.
     */
    private function urlRendicionErpPorOperacion(int $empresaId, string $bloque, int $nroOper): ?string
    {
        if ($nroOper <= 0) {
            return null;
        }

        $bingo = fn (): ?string => CierreRendicionOrigenConsultaSupport::puedeVerPdfRendicionBingo()
            ? $this->urlPorNroOperAnita(RendicionBingoCaja::class, $empresaId, $nroOper, 'imprimir_rendicion_bingo', ['inline' => 1])
            : null;
        $gastro = fn (): ?string => CierreRendicionOrigenConsultaSupport::puedeConsultarRendicionGastronomia()
            ? $this->urlPorNroOperAnita(RendicionGastronomiaCaja::class, $empresaId, $nroOper, 'editar_rendiciongastronomia', self::QUERY_CONSULTA)
            : null;
        $estac = fn (): ?string => CierreRendicionOrigenConsultaSupport::puedeConsultarRendicionEstacionamiento()
            ? $this->urlPorNroOperAnita(RendicionEstacionamientoCaja::class, $empresaId, $nroOper, 'editar_rendicionestacionamiento', self::QUERY_CONSULTA)
            : null;
        // Máquinas no entra acá: rendicion_maquina.nro_oper_anita guarda una
        // secuencia propia del ERP, no el rendm_nro_oper de Anita.
        $candidatos = match ($bloque) {
            self::BLOQUE_BINGO => [$bingo],
            self::BLOQUE_GASTRO, self::BLOQUE_VENDING => [$gastro],
            self::BLOQUE_ESTAC => [$estac],
            self::BLOQUE_MEDIOS, self::BLOQUE_EGRESOS => [$gastro, $estac, $bingo],
            default => [],
        };

        foreach ($candidatos as $resolver) {
            $url = $resolver();
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }

    /**
     * La rendición de máquinas se ubica por empresa, fecha y turno porque su
     * numeración de operación no coincide con la de Anita.
     */
    private function urlRendicionMaquinaErp(int $empresaId, int $fechaYmd, ?string $turno): ?string
    {
        if ($fechaYmd <= 0 || ! CierreRendicionOrigenConsultaSupport::puedeConsultarRendicionMaquina()) {
            return null;
        }

        try {
            $turnoNormalizado = RendicionMaquinaTurno::normalizar((string) $turno);
        } catch (InvalidArgumentException) {
            return null;
        }

        $id = (int) (RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', Carbon::createFromFormat('Ymd', (string) $fechaYmd)->toDateString())
            ->where('turno', $turnoNormalizado)
            ->where(function ($query) {
                $query->whereNull('estado')->orWhere('estado', '!=', RendicionMaquina::ESTADO_ANULADA);
            })
            ->orderByDesc('id')
            ->value('id') ?? 0);

        return $id > 0
            ? route('editar_rendicion_maquina', array_merge(['id' => $id], self::QUERY_CONSULTA))
            : null;
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  array<string, mixed>  $extra
     */
    private function urlPorNroOperAnita(
        string $modelClass,
        int $empresaId,
        int $nroOper,
        string $ruta,
        array $extra,
    ): ?string {
        $id = (int) ($modelClass::query()
            ->where('empresa_id', $empresaId)
            ->where('nro_oper_anita', $nroOper)
            ->value('id') ?? 0);

        return $id > 0 ? route($ruta, array_merge(['id' => $id], $extra)) : null;
    }

    /**
     * @return array<int, true>
     */
    private function operacionesAuditoriaBloque(int $empresaId, int $fechaYmd, string $bloque): array
    {
        $operaciones = [];

        if (in_array($bloque, [
            self::BLOQUE_GASTRO,
            self::BLOQUE_ESTAC,
            self::BLOQUE_VENDING,
            self::BLOQUE_MEDIOS,
            self::BLOQUE_EGRESOS,
        ], true)) {
            foreach ($this->bridgeReader->listarRendgastro($empresaId, $fechaYmd, $fechaYmd) as $fila) {
                $bloqueFila = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
                if (in_array($bloque, [self::BLOQUE_MEDIOS, self::BLOQUE_EGRESOS], true)
                    || $bloqueFila === $bloque) {
                    $operaciones[(int) ($fila->rendg_nro_oper ?? 0)] = true;
                }
            }
        }

        if (in_array($bloque, [self::BLOQUE_MAQUINAS, self::BLOQUE_MEDIOS, self::BLOQUE_EGRESOS], true)) {
            foreach ($this->bridgeReader->listarRendmaquina($empresaId, $fechaYmd, $fechaYmd) as $fila) {
                if ($this->incluirRendmaquina($fila)) {
                    $operaciones[(int) ($fila->rendm_nro_oper ?? 0)] = true;
                }
            }
        }

        if (in_array($bloque, [self::BLOQUE_BINGO, self::BLOQUE_MEDIOS, self::BLOQUE_EGRESOS], true)) {
            foreach ($this->bridgeReader->listarRendbingo($empresaId, $fechaYmd, $fechaYmd) as $fila) {
                $operaciones[(int) ($fila->rendb_nro_oper ?? 0)] = true;
            }
        }

        unset($operaciones[0]);

        return $operaciones;
    }

    /**
     * @return list<string>
     */
    private function fuentesAuditoriaPorBloque(string $bloque, string $etiqueta): array
    {
        return match ($bloque) {
            self::BLOQUE_BINGO => $this->fuenteErpPura
                ? ['rendicion_bingo_caja ERP']
                : [
                    'rendbingo / concbingo / rendpremio Anita (prioridad por día)',
                    'rendicion_bingo_caja ERP (solo días sin Anita)',
                ],
            self::BLOQUE_GASTRO => $this->fuenteErpPura
                ? [
                    'rendicion_gastronomia_caja ERP',
                    'Cierre Waitry (factura CAEA + cobranza, por jornada)',
                ]
                : [
                    'rendicion_gastronomia_caja ERP (días con turno) + huecos Anita (MEP, etc.)',
                    'Cierre Waitry (factura CAEA + cobranza, por jornada)',
                    'rendgastro / rendvalor / valormae Anita (días sin ERP)',
                ],
            self::BLOQUE_ESTAC, self::BLOQUE_VENDING => $this->fuenteErpPura
                ? ['rendicion_*_caja ERP']
                : [
                    'rendicion_*_caja ERP (días con turno) + huecos Anita (MEP, etc.)',
                    'rendgastro / rendvalor / valormae Anita (días sin ERP)',
                ],
            self::BLOQUE_MAQUINAS => in_array($etiqueta, ['Sobrantes', 'Canje de gastronomía'], true)
                ? ['rendicion_maquina ERP', 'rendmaquina Anita (días sin rendición ERP)']
                : [
                    'rendicion_maquina ERP turno C (días con cierre)',
                    'rendmaquina / rendvalor / rendmapgasto Anita (días sin ERP)',
                ],
            self::BLOQUE_MEDIOS => $this->fuenteErpPura
                ? ['rendiciones ERP (bingo/gastro/estac)', 'rendicion_maquina ERP o rendvalor Anita si no hay C']
                : ['rendvalor', 'rendbingo'],
            self::BLOQUE_EGRESOS => ['remesa ERP', 'rememae Anita (si falta la remesa)', 'medios no efectivos'],
            self::BLOQUE_SALDOS => ['posicion_financiera_saldo ERP', 'saldoposf Anita (fallback)', 'fórmula diaria'],
            default => [],
        };
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
            if (in_array(
                $fila['tipo_fila'] ?? self::TIPO_CONCEPTO,
                [self::TIPO_TITULO, self::TIPO_INFORMATIVO],
                true
            )) {
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
    private function ultimoSaldoAnterior(array $filas, int $fechaExclusiva): ?float
    {
        $ultimo = null;
        $ultimaFecha = 0;
        foreach ($filas as $fila) {
            $fecha = (int) ($fila->salpf_fecha ?? 0);
            if ($fecha <= 0 || $fecha >= $fechaExclusiva || $fecha <= $ultimaFecha) {
                continue;
            }

            $ultimaFecha = $fecha;
            $ultimo = round((float) ($fila->salpf_saldo ?? 0), 2);
        }

        return $ultimo;
    }

    /**
     * Resuelve los códigos rendvalor habilitados por la configuración operativa
     * de cuentas de caja. Vending usa deliberadamente el uso Gastronomia.
     *
     * @param  class-string  $mapperClass
     * @return array<int, true>
     */
    private function codigosValormaePorUso(int $empresaId, string $usoNombre, string $mapperClass): array
    {
        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($query) => $query->where('usocuentacaja.nombre', $usoNombre))
            ->get();

        $codigos = [];
        foreach ($cuentas as $cuenta) {
            if ($mapperClass::omitirEnRendvalorAnita($cuenta)) {
                continue;
            }

            try {
                $codigo = $mapperClass::codigoDesdeCuentacaja($empresaId, $cuenta);
            } catch (RuntimeException) {
                continue;
            }

            if ($codigo > 0) {
                $codigos[$codigo] = true;
            }
        }

        return $codigos;
    }

    /**
     * Códigos valormae que corresponden a cuentas de caja con el uso indicado
     * (etiqueta / nombre). Así no entran medios del catálogo Anita sin cuenta operativa.
     *
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<int, true>
     */
    private function codigosValormaePorEtiquetaDeUso(int $empresaId, string $usoNombre, array $valormae): array
    {
        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($query) => $query->where('usocuentacaja.nombre', $usoNombre))
            ->get();

        $codigos = [];
        foreach ($cuentas as $cuenta) {
            if (RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)) {
                continue;
            }
            foreach ($valormae as $codigo => $meta) {
                if (! $this->valormaeCoincideConCuenta((string) ($meta['desc'] ?? ''), $cuenta)) {
                    continue;
                }
                $codigo = (int) $codigo;
                if ($codigo > 0) {
                    $codigos[$codigo] = true;
                }
            }
        }

        return $codigos;
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>  $codigos
     * @return array<string, true>
     */
    private function etiquetasMediosDeUso(
        int $empresaId,
        string $usoNombre,
        array $valormae,
        array $codigos,
    ): array {
        $etiquetas = [];
        foreach ($codigos as $codigo => $_) {
            $desc = trim((string) ($valormae[$codigo]['desc'] ?? ''));
            if ($desc !== '') {
                $etiquetas[$desc] = true;
            }
        }

        $cuentas = Cuentacaja::query()
            ->paraEmpresa($empresaId)
            ->whereHas('usocuentacajas', fn ($query) => $query->where('usocuentacaja.nombre', $usoNombre))
            ->get();
        foreach ($cuentas as $cuenta) {
            if (RendicionGastronomiaRendvalorCodigoSupport::omitirEnRendvalorAnita($cuenta)) {
                continue;
            }
            $etiqueta = trim($cuenta->etiquetaOperaciones());
            $nombre = trim((string) $cuenta->nombre);
            if ($etiqueta !== '') {
                $etiquetas[$etiqueta] = true;
            }
            if ($nombre !== '') {
                $etiquetas[$nombre] = true;
            }
        }

        return $etiquetas;
    }

    /**
     * @param  array<string, array<int, float>>  $mapa
     * @param  array<string, true>  $etiquetasPermitidas
     * @return array<string, array<int, float>>
     */
    private function filtrarMapaPorEtiquetas(array $mapa, array $etiquetasPermitidas): array
    {
        if ($etiquetasPermitidas === []) {
            return $mapa;
        }

        $out = [];
        foreach ($mapa as $etiqueta => $porDia) {
            if (isset($etiquetasPermitidas[$etiqueta])) {
                $out[$etiqueta] = $porDia;
            }
        }

        return $out;
    }

    private function valormaeCoincideConCuenta(string $desc, Cuentacaja $cuenta): bool
    {
        $descN = $this->normalizarEtiquetaMedio($desc);
        if ($descN === '') {
            return false;
        }

        $etiq = $this->normalizarEtiquetaMedio($cuenta->etiquetaOperaciones());
        $nom = $this->normalizarEtiquetaMedio((string) $cuenta->nombre);
        if ($descN === $etiq || $descN === $nom) {
            return true;
        }

        $descC = str_replace(' ', '', $descN);
        foreach ([$etiq, $nom] as $cuentaN) {
            $cuentaC = str_replace(' ', '', $cuentaN);
            if ($cuentaC === '' || $descC === '') {
                continue;
            }
            if ($descC === $cuentaC) {
                return true;
            }
            $corta = min(strlen($descC), strlen($cuentaC));
            if ($corta < 10) {
                continue;
            }
            if (str_contains($cuentaC, $descC) || str_contains($descC, $cuentaC)) {
                return true;
            }
        }

        return false;
    }

    private function normalizarEtiquetaMedio(string $texto): string
    {
        $texto = mb_strtoupper(trim($texto));
        $texto = str_replace(['Á', 'É', 'Í', 'Ó', 'Ú', '.'], ['A', 'E', 'I', 'O', 'U', ''], $texto);

        return preg_replace('/\s+/', ' ', $texto) ?? $texto;
    }

    /**
     * Alinea nombres de conceptos ERP bingo a las etiquetas Anita (l-posfinanc).
     *
     * @param  array<string, array<int, float>>  $premios
     * @return array<string, array<int, float>>
     */
    private function normalizarEtiquetasPremiosBingo(array $premios): array
    {
        $alias = [
            'Línea 6%' => 'Linea 6%',
            'Linea 6%' => 'Linea 6%',
            'B.U.B 0,50% apertura' => 'Pago 0.5% pozo ac. dia ant.',
            'B.U.B 0,50% cierre' => 'Pago 0.5% pozo ac. del dia',
            'Premio 5%' => 'Premio 5% Pozo Ac.',
        ];

        $out = [];
        foreach ($premios as $etiqueta => $porDia) {
            $destino = $alias[$etiqueta] ?? $etiqueta;
            if (! isset($out[$destino])) {
                $out[$destino] = $this->vectorDias();
            }
            foreach ($this->dias as $dia) {
                $out[$destino][$dia] = round(
                    ($out[$destino][$dia] ?? 0) + (float) ($porDia[$dia] ?? 0),
                    2,
                );
            }
        }

        return $out;
    }

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
            // Tipos 0/1 ya se calculan por % sobre cartones; no sumar de nuevo rendpremio.
            if (in_array($tipo, ['0', '1'], true)) {
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
     * @param  array<int, true>  $codigosValormaePermitidos
     * @return array<string, array<int, float>>
     */
    private function agregarBloqueGastroEstac(
        array $cabeceras,
        array $valoresPorOper,
        array $valormae,
        array $codigosValormaePermitidos,
        int $empresaId,
        string $bloque,
        string $etiquetaZ,
        string $etiquetaTotal,
        bool $redondeoNegado = false,
    ): array {
        $totales = [
            $etiquetaZ => $this->vectorDias(),
            'Notas de credito' => $this->vectorDias(),
            'Diferencia abandono de pago' => $this->vectorDias(),
            'Redondeo' => $this->vectorDias(),
            'Diferencia de caja' => $this->vectorDias(),
        ];
        $mapperClass = $bloque === 'estac'
            ? RendicionEstacionamientoRendvalorCodigoSupport::class
            : RendicionGastronomiaRendvalorCodigoSupport::class;
        foreach (PosicionFinancieraOrdenConceptoSupport::ordenarValormaePermitidos(
            $empresaId,
            $valormae,
            $codigosValormaePermitidos,
            $mapperClass,
        ) as $meta) {
            $desc = trim((string) ($meta['desc'] ?? ''));
            if ($desc !== '') {
                $totales[$desc] = $this->vectorDias();
            }
        }
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
                if (! isset($valormae[$codigo], $codigosValormaePermitidos[$codigo])) {
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
            'Pago 24' => $this->vectorDias(),
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
            $this->sumarEn($totales, 'Pago 24', $dia, (float) ($fila->rendm_vtaant_gast ?? 0));
        }

        return $totales;
    }

    /**
     * Renglones exclusivamente informativos: ERP cuando el día ya está cargado,
     * Anita para los días de agosto que todavía no se rindieron en el ERP.
     *
     * @param  list<object>  $filasMaquina
     * @return array{'Sobrantes': array<int, float>, 'Canje de gastronomía': array<int, float>}
     */
    private function informativosMaquinas(int $empresaId, Carbon $desde, Carbon $hasta, array $filasMaquina): array
    {
        $erp = $this->informativosMaquinasErp($empresaId, $desde, $hasta);
        $anita = $this->informativosMaquinasAnita($filasMaquina);

        $resultado = [
            'Sobrantes' => $this->vectorDias(),
            'Canje de gastronomía' => $this->vectorDias(),
        ];

        foreach ($this->dias as $dia) {
            $fuente = isset($erp['dias'][$dia]) ? $erp['filas'] : $anita;
            foreach (array_keys($resultado) as $etiqueta) {
                $resultado[$etiqueta][$dia] = round((float) ($fuente[$etiqueta][$dia] ?? 0), 2);
            }
        }

        return $resultado;
    }

    /**
     * Informativos tomados de rendmaquina (Anita): solo cierres del día,
     * mismo criterio de turnos que el resto del bloque de máquinas.
     *
     * @param  list<object>  $filasMaquina
     * @return array<string, array<int, float>>
     */
    private function informativosMaquinasAnita(array $filasMaquina): array
    {
        $totales = [
            'Sobrantes' => $this->vectorDias(),
            'Canje de gastronomía' => $this->vectorDias(),
        ];

        foreach ($filasMaquina as $fila) {
            if (! $this->incluirRendmaquina($fila)) {
                continue;
            }

            $dia = $this->diaDeYmd((int) ($fila->rendm_fecha ?? 0));
            $this->sumarEn($totales, 'Sobrantes', $dia, (float) ($fila->rendm_sobrantes ?? 0));
            $this->sumarEn($totales, 'Canje de gastronomía', $dia, (float) ($fila->rendm_canje_gastro ?? 0));
        }

        return $totales;
    }

    /**
     * Renglones informativos desde el ERP.
     *
     * Por día se toma el cierre C, que ya consolida M/T/N. Si todavía no
     * existe, se suman los turnos parciales disponibles para no duplicar.
     *
     * @return array{filas: array<string, array<int, float>>, dias: array<int, true>}
     */
    private function informativosMaquinasErp(int $empresaId, Carbon $desde, Carbon $hasta): array
    {
        $resultado = [
            'Sobrantes' => $this->vectorDias(),
            'Canje de gastronomía' => $this->vectorDias(),
        ];

        $rendiciones = RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->where(function ($query) {
                $query->whereNull('estado')
                    ->orWhere('estado', '!=', RendicionMaquina::ESTADO_ANULADA);
            })
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'fecha', 'turno', 'inputs_json']);

        /** @var array<int, array{parcial_sobrantes: float, parcial_canje: float, completo: ?array{sobrantes: float, canje: float}}> $porDia */
        $porDia = [];
        foreach ($rendiciones as $rendicion) {
            $dia = (int) $rendicion->fecha?->day;
            if (! in_array($dia, $this->dias, true)) {
                continue;
            }

            try {
                $turno = RendicionMaquinaTurno::normalizar((string) $rendicion->turno);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (! isset($porDia[$dia])) {
                $porDia[$dia] = [
                    'parcial_sobrantes' => 0.0,
                    'parcial_canje' => 0.0,
                    'completo' => null,
                ];
            }

            $inputs = is_array($rendicion->inputs_json) ? $rendicion->inputs_json : [];
            $sobrantes = $this->valorInputRendicionMaquina($inputs, 'sobrantes');
            $canje = $this->valorInputRendicionMaquina($inputs, 'impuesto_pago');

            if ($turno === RendicionMaquinaTurno::COMPLETO) {
                $porDia[$dia]['completo'] = [
                    'sobrantes' => $sobrantes,
                    'canje' => $canje,
                ];

                continue;
            }

            $porDia[$dia]['parcial_sobrantes'] += $sobrantes;
            $porDia[$dia]['parcial_canje'] += $canje;
        }

        $diasConErp = [];
        foreach ($porDia as $dia => $datos) {
            $resultado['Sobrantes'][$dia] = round(
                (float) ($datos['completo']['sobrantes'] ?? $datos['parcial_sobrantes']),
                2
            );
            $resultado['Canje de gastronomía'][$dia] = round(
                (float) ($datos['completo']['canje'] ?? $datos['parcial_canje']),
                2
            );
            $diasConErp[$dia] = true;
        }

        return ['filas' => $resultado, 'dias' => $diasConErp];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function valorInputRendicionMaquina(array $inputs, string $campo): float
    {
        return (float) ($inputs[$campo] ?? $inputs['inputs.'.$campo] ?? 0);
    }

    /**
     * @param  list<int>  $nrosOper
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, int>  $fechaPorOper
     * @param  array<int, true>  $codigosPermitidos
     * @return array<string, array<int, float>>
     */
    private function agregarMediosPorOperaciones(
        array $nrosOper,
        array $valoresPorOper,
        array $valormae,
        array $fechaPorOper,
        array $codigosPermitidos = [],
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
                if ($codigosPermitidos !== [] && ! isset($codigosPermitidos[$codigo])) {
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
        // l-posfinanc.c lista todos los conceptos de apgasto, con o sin movimiento.
        $totales = [];
        foreach ($apgastoDesc as $desc) {
            $totales[$desc] = $this->vectorDias();
        }

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
                    'Diferencia de caja', 'Caja en transito', 'Variacion de FF', 'Pago 24',
                    'Total maquinas',
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
     * @param  list<object>  $rendpremio
     * @param  list<object>  $concbingo
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
        array $rendpremio,
        array $concbingo,
        array $gastronomia,
        array $fechaPorOper,
    ): array {
        $porTipo = [];
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            // l-posfinanc.c: lee_rendvalor solo en gastro/estac/máquinas (no vending).
            if (in_array($bloque, [self::BLOQUE_GASTRO, self::BLOQUE_ESTAC], true)) {
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

        $opsBingo = [];
        $ventaBingoPorDia = $this->vectorDias();
        foreach ($rendbingo as $fila) {
            $importe = (float) ($fila->rendb_total_carton ?? 0)
                + (float) ($fila->rendb_sobrante ?? 0)
                + (float) ($fila->rendb_vales ?? 0);
            $dia = $this->diaDeYmd((int) ($fila->rendb_fecha ?? 0));
            $this->sumarEn($porTipo, self::VALM_EFE_PESOS, $dia, $importe);
            $ventaBingoPorDia[$dia] = round(
                ($ventaBingoPorDia[$dia] ?? 0) + (float) ($fila->rendb_total_carton ?? 0),
                2,
            );
            $clave = (int) ($fila->rendb_nro_oper ?? 0).'|'.trim((string) ($fila->rendb_tipo_oper ?? ''));
            $opsBingo[$clave] = (int) ($fila->rendb_fecha ?? 0);
        }

        // Efectivo apertura: resta premios bingo (l-posfinanc.c lee_premios).
        // Los % sobre cartones (tipos 0/1) también bajan el efectivo aunque a veces
        // no vengan en rendpremio del día.
        $mapConcb = [];
        foreach ($concbingo as $row) {
            $mapConcb[(int) ($row->concb_concepto ?? 0)] = $row;
            $tipoConc = trim((string) ($row->concb_tipo_conc ?? ''));
            $pct = (float) ($row->concb_porcentaje ?? 0);
            if (! in_array($tipoConc, ['0', '1'], true) || $pct <= 0) {
                continue;
            }
            foreach ($this->dias as $dia) {
                $venta = (float) ($ventaBingoPorDia[$dia] ?? 0);
                if ($venta > 0) {
                    $this->sumarEn(
                        $porTipo,
                        self::VALM_EFE_PESOS,
                        $dia,
                        -1 * $venta * ($pct / 100),
                    );
                }
            }
        }
        foreach ($rendpremio as $row) {
            $claveOp = (int) ($row->rendp_nro_oper ?? 0).'|'.trim((string) ($row->rendp_tipo_oper ?? ''));
            if (! isset($opsBingo[$claveOp])) {
                continue;
            }
            $conceptoId = (int) ($row->rendp_concepto ?? 0);
            $concb = $mapConcb[$conceptoId] ?? null;
            if ($concb === null) {
                continue;
            }
            $tipo = trim((string) ($concb->concb_tipo_conc ?? ''));
            // 0/1 ya restados por % sobre cartones. tipo 2 = CONCB_PORC_RECAUD (lee_premios lo salta).
            if (in_array($tipo, ['0', '1', '2'], true)) {
                continue;
            }
            $usaReal = in_array($tipo, ['3', '4', '5'], true);
            $importe = $usaReal
                ? (float) ($row->rendp_real ?? 0)
                : (float) ($row->rendp_pagado ?? 0);
            if ($importe <= 0) {
                continue;
            }
            $fecha = (int) ($opsBingo[$claveOp] ?? 0);
            if ($fecha <= 0) {
                $fecha = (int) ($row->rendp_fecha ?? 0);
            }
            $this->sumarEn($porTipo, self::VALM_EFE_PESOS, $this->diaDeYmd($fecha), -1 * $importe);
        }

        $canjeGastro = $gastronomia['Tk.Canje Gastronomia'] ?? $this->vectorDias();

        return $this->cerrarAperturaDesdePorTipo($porTipo, $canjeGastro, $valormae);
    }

    /**
     * Apertura desde anitaERP: bingo/gastro/estac siempre ERP; máquinas ERP si hay
     * cierre C ese día, si no rendvalor Anita.
     *
     * @param  array{base: array<string, array<int, float>>, premios?: array<string, array<int, float>>}  $bingoErp
     * @param  array<string, array<int, float>>  $premios
     * @param  array<string, array<int, float>>  $gastronomia
     * @param  array<string, array<int, float>>  $estacionamiento
     * @param  array{medios: array<string, array<int, float>>, dias: array<int, true>}  $maquinasErp
     * @param  array<string, array<int, float>>  $maquinasMediosAnita
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, array<int, float>>
     */
    private function agregarAperturaMediosErpPuro(
        array $bingoErp,
        array $premios,
        array $gastronomia,
        array $estacionamiento,
        array $maquinasErp,
        array $maquinasMediosAnita,
        array $valormae,
    ): array {
        $porTipo = [];
        $this->sumarMediosFilasEnPorTipo($porTipo, $gastronomia, $valormae);
        $this->sumarMediosFilasEnPorTipo($porTipo, $estacionamiento, $valormae);

        $diasMaquinaErp = $maquinasErp['dias'] ?? [];
        $this->sumarMediosFilasEnPorTipo($porTipo, $maquinasErp['medios'] ?? [], $valormae, $diasMaquinaErp);
        $this->sumarMediosFilasEnPorTipo(
            $porTipo,
            $maquinasMediosAnita,
            $valormae,
            null,
            $diasMaquinaErp,
        );

        foreach (['VENTA BINGO', 'SOBRANTES', 'VALES'] as $etiqBingo) {
            foreach ($this->dias as $dia) {
                $this->sumarEn(
                    $porTipo,
                    self::VALM_EFE_PESOS,
                    $dia,
                    (float) (($bingoErp['base'][$etiqBingo] ?? [])[$dia] ?? 0),
                );
            }
        }
        foreach ($premios as $porDiaPremio) {
            foreach ($this->dias as $dia) {
                $this->sumarEn($porTipo, self::VALM_EFE_PESOS, $dia, (float) ($porDiaPremio[$dia] ?? 0));
            }
        }

        $canjeGastro = $gastronomia['Tk.Canje Gastronomia'] ?? $this->vectorDias();

        return $this->cerrarAperturaDesdePorTipo($porTipo, $canjeGastro, $valormae);
    }

    /**
     * @param  array<string, array<int, float>>  $gastronomia
     * @param  array<string, array<int, float>>  $estacionamiento
     * @param  array{medios: array<string, array<int, float>>, dias: array<int, true>}  $maquinasErp
     * @param  array<string, array<int, float>>  $maquinasMediosAnita
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, array<int, float>>
     */
    private function acumularAbiertosNoEfectivoErpPuro(
        array $gastronomia,
        array $estacionamiento,
        array $maquinasErp,
        array $maquinasMediosAnita,
        array $valormae,
    ): array {
        $totales = [];
        $diasMaquinaErp = $maquinasErp['dias'] ?? [];
        $this->sumarMediosNoEfectivoEnAbiertos($totales, $gastronomia, $valormae);
        $this->sumarMediosNoEfectivoEnAbiertos($totales, $estacionamiento, $valormae);
        $this->sumarMediosNoEfectivoEnAbiertos($totales, $maquinasErp['medios'] ?? [], $valormae, $diasMaquinaErp);
        $this->sumarMediosNoEfectivoEnAbiertos($totales, $maquinasMediosAnita, $valormae, null, $diasMaquinaErp);

        return $totales;
    }

    /**
     * Replica l-posfinanc.c procesa_ingresos_por_medio_de_cobro.
     *
     * @param  array<string, array<int, float>>  $porTipo
     * @param  array<int, float>  $canjeGastro
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, array<int, float>>
     */
    private function cerrarAperturaDesdePorTipo(
        array $porTipo,
        array $canjeGastro,
        array $valormae,
    ): array {
        $tiposOrden = [];
        $vistos = [];
        $codigosValm = array_keys($valormae);
        sort($codigosValm, SORT_NUMERIC);
        foreach ($codigosValm as $codValm) {
            $tipoValm = (string) ($valormae[$codValm]['tipo'] ?? '');
            if ($tipoValm === '' || isset($vistos[$tipoValm])) {
                continue;
            }
            $vistos[$tipoValm] = true;
            $tiposOrden[] = $tipoValm;
        }
        $switchCodigo = [
            self::VALM_EFE_PESOS => 0,
            self::VALM_EFE_DOLAR => 1,
            self::VALM_EFE_EURO => 2,
            '3' => 3,
            '4' => 4,
            self::VALM_EFE_CRIPTO => 9,
        ];

        $totales = [
            'Efectivo pesos' => $this->vectorDias(),
            'Efectivo dolar' => $this->vectorDias(),
            'Efectivo euros' => $this->vectorDias(),
            'Efectivo cripto USDT' => $this->vectorDias(),
            'Tickets' => $this->vectorDias(),
            'Varios' => $this->vectorDias(),
            'Tarjetas' => $this->vectorDias(),
            'QR' => $this->vectorDias(),
        ];
        $totalIngresos = $this->vectorDias();
        foreach ($this->dias as $dia) {
            $canje = (float) ($canjeGastro[$dia] ?? 0);
            $codigoLst = null;
            $ajustado = [];
            $ticketsCodigo4 = 0.0;
            $sumaTotal = 0.0;
            foreach ($tiposOrden as $tipoValm) {
                if (isset($switchCodigo[$tipoValm])) {
                    $codigoLst = $switchCodigo[$tipoValm];
                }
                $monto = (float) ($porTipo[$tipoValm][$dia] ?? 0);
                if ($codigoLst === 4) {
                    $monto -= $canje;
                    $ticketsCodigo4 += $monto;
                }
                $ajustado[$tipoValm] = $monto;
                $sumaTotal += $monto;
            }

            $totales['Efectivo pesos'][$dia] = round((float) ($ajustado[self::VALM_EFE_PESOS] ?? 0), 2);
            $totales['Efectivo dolar'][$dia] = round((float) ($ajustado[self::VALM_EFE_DOLAR] ?? 0), 2);
            $totales['Efectivo euros'][$dia] = round((float) ($ajustado[self::VALM_EFE_EURO] ?? 0), 2);
            $totales['Efectivo cripto USDT'][$dia] = round((float) ($ajustado[self::VALM_EFE_CRIPTO] ?? 0), 2);
            $totales['Tarjetas'][$dia] = round((float) ($ajustado['3'] ?? 0), 2);
            $totales['QR'][$dia] = round((float) ($ajustado['5'] ?? 0), 2);
            $totales['Tickets'][$dia] = round($ticketsCodigo4, 2);
            $totales['Varios'][$dia] = round(
                (float) ($ajustado['7'] ?? 0)
                + (float) ($ajustado['9'] ?? 0)
                + (float) ($ajustado['A'] ?? 0)
                + (float) ($ajustado['B'] ?? 0),
                2,
            );
            $totalIngresos[$dia] = round($sumaTotal, 2);
        }

        $totales['Canje Gastronomia'] = $this->redondearVector($canjeGastro);
        $totales['Total de Ingresos'] = $this->redondearVector($totalIngresos);

        return $totales;
    }

    /**
     * @param  array<string, array<int, float>>  $porTipo
     * @param  array<string, array<int, float>>  $filas
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>|null  $soloDias
     * @param  array<int, true>|null  $exceptoDias
     */
    private function sumarMediosFilasEnPorTipo(
        array &$porTipo,
        array $filas,
        array $valormae,
        ?array $soloDias = null,
        ?array $exceptoDias = null,
    ): void {
        $tipoPorDesc = $this->tipoValormaePorDescripcion($valormae);
        foreach ($filas as $desc => $porDia) {
            if ($this->esEtiquetaCabeceraBloqueGastro($desc)) {
                continue;
            }
            $tipo = $tipoPorDesc[$desc] ?? null;
            if ($tipo === null) {
                continue;
            }
            foreach ($this->dias as $dia) {
                if ($soloDias !== null && ! isset($soloDias[$dia])) {
                    continue;
                }
                if ($exceptoDias !== null && isset($exceptoDias[$dia])) {
                    continue;
                }
                $this->sumarEn($porTipo, $tipo, $dia, (float) ($porDia[$dia] ?? 0));
            }
        }
    }

    /**
     * @param  array<string, array<int, float>>  $totales
     * @param  array<string, array<int, float>>  $filas
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  array<int, true>|null  $soloDias
     * @param  array<int, true>|null  $exceptoDias
     */
    private function sumarMediosNoEfectivoEnAbiertos(
        array &$totales,
        array $filas,
        array $valormae,
        ?array $soloDias = null,
        ?array $exceptoDias = null,
    ): void {
        $metaPorDesc = [];
        foreach ($valormae as $codigo => $meta) {
            $desc = (string) ($meta['desc'] ?? '');
            if ($desc === '' || isset($metaPorDesc[$desc])) {
                continue;
            }
            $metaPorDesc[$desc] = ['codigo' => (int) $codigo, 'tipo' => (string) ($meta['tipo'] ?? '')];
        }
        foreach ($filas as $desc => $porDia) {
            if ($this->esEtiquetaCabeceraBloqueGastro($desc)) {
                continue;
            }
            $meta = $metaPorDesc[$desc] ?? null;
            if ($meta === null) {
                continue;
            }
            if ($this->esTipoEfectivo($meta['tipo']) || $meta['codigo'] === 8) {
                continue;
            }
            foreach ($this->dias as $dia) {
                if ($soloDias !== null && ! isset($soloDias[$dia])) {
                    continue;
                }
                if ($exceptoDias !== null && isset($exceptoDias[$dia])) {
                    continue;
                }
                $this->sumarEn($totales, $desc, $dia, (float) ($porDia[$dia] ?? 0));
            }
        }
    }

    /**
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @return array<string, string>
     */
    private function tipoValormaePorDescripcion(array $valormae): array
    {
        $map = [];
        foreach ($valormae as $meta) {
            $desc = (string) ($meta['desc'] ?? '');
            if ($desc === '' || isset($map[$desc])) {
                continue;
            }
            $map[$desc] = (string) ($meta['tipo'] ?? '');
        }

        return $map;
    }

    private function esEtiquetaCabeceraBloqueGastro(string $etiqueta): bool
    {
        return in_array($etiqueta, [
            'GASTRONOMIA Z',
            'Total Gastronomia',
            'ESTACIONAMIENTO Z',
            'Total Estacionamiento',
            'VENDING Z',
            'Total Vending',
            'Notas de credito',
            'Diferencia abandono de pago',
            'Redondeo',
            'Diferencia de caja',
        ], true);
    }

    /**
     * Para el componente remesas, ERP tiene prioridad y rememae completa el
     * período sin duplicar las remesas que todavía viven en ambos.
     *
     * @param  array<string, array<int, float>>  $abiertosNoEfectivo
     * @return array<string, array<int, float>>
     */
    private function agregarEgresos(
        int $empresaId,
        int $fechaDesde,
        int $fechaHasta,
        array $abiertosNoEfectivo,
    ): array {
        $totales = $abiertosNoEfectivo;
        $totalEgresos = $this->vectorDias();
        foreach ($abiertosNoEfectivo as $porDia) {
            foreach ($this->dias as $dia) {
                $totalEgresos[$dia] = round($totalEgresos[$dia] + (float) ($porDia[$dia] ?? 0), 2);
            }
        }
        $firmasErp = [];
        $desde = Carbon::createFromFormat('Ymd', (string) $fechaDesde)->toDateString();
        $hasta = Carbon::createFromFormat('Ymd', (string) $fechaHasta)->toDateString();

        $remesasErp = Remesa::query()
            ->with(['lineasDestino.cuentacaja'])
            ->where('empresa_id', $empresaId)
            ->whereBetween('fecha', [$desde, $hasta])
            ->where('tipo', RemesaSupport::TIPO_EXTERNA)
            ->where('estado', RemesaSupport::ESTADO_CONFIRMADA)
            ->orderBy('fecha')
            ->orderBy('numero')
            ->get(['id', 'fecha', 'numero']);

        foreach ($remesasErp as $remesa) {
            $fecha = $remesa->fecha?->toDateString() ?? '';
            $fechaYmd = (int) str_replace('-', '', $fecha);
            $dia = $this->diaDeYmd($fechaYmd);
            foreach ($remesa->lineasDestino as $linea) {
                $cuenta = $linea->cuentacaja;
                $importe = round((float) ($linea->monto ?? 0), 2);
                if ($cuenta === null || $importe <= 0) {
                    continue;
                }

                $monedaId = (int) ($cuenta->moneda_id ?: 1);
                $codigoMonedaAnita = CotizacionTesoreriaConsultaSupport::codigoAnitaDesdeMonedaId($monedaId) ?? 1;
                $cotizacion = CotizacionTesoreriaConsultaSupport::ventaPorMonedaId($fecha, $monedaId, $empresaId);
                if ($cotizacion === null || $cotizacion <= 0) {
                    throw new RuntimeException('No hay cotización vigente para la remesa ERP '.$remesa->numero.'.');
                }

                $destino = $this->destinoRememaeDesdeCuenta($cuenta);
                $etiqueta = $this->etiquetaEgresoCuentaErp($cuenta, $destino, $codigoMonedaAnita);
                $this->sumarEn($totales, $etiqueta, $dia, $importe);
                $totalEgresos[$dia] = round($totalEgresos[$dia] + ($importe * $cotizacion), 2);

                $firma = $this->firmaRemesa($fechaYmd, $destino, $codigoMonedaAnita, $importe);
                $firmasErp[$firma] = ($firmasErp[$firma] ?? 0) + 1;
            }
        }

        foreach ($this->bridgeReader->listarRememae($empresaId, $fechaDesde, $fechaHasta) as $fila) {
            if (strtoupper(trim((string) ($fila->remem_tipo_remesa ?? ''))) !== RemesaSupport::TIPO_EXTERNA) {
                continue;
            }

            $destino = trim((string) ($fila->remem_destino ?? self::REMEM_MACO));
            $importe = round((float) ($fila->remem_importe ?? 0), 2);
            $fechaYmd = (int) ($fila->remem_fecha ?? 0);
            $codigoMoneda = (int) ($fila->remem_cod_mon ?? 1);
            if ($destino === self::REMEM_PAGOFACIL || $importe <= 0 || $fechaYmd <= 0) {
                continue;
            }

            $firma = $this->firmaRemesa($fechaYmd, $destino, $codigoMoneda, $importe);
            if (($firmasErp[$firma] ?? 0) > 0) {
                $firmasErp[$firma]--;

                continue;
            }

            $cotizacion = $codigoMoneda === 1 ? 1.0 : (float) ($fila->remem_cotizacion ?? 0);
            if ($cotizacion <= 0) {
                throw new RuntimeException('No hay cotización válida para la remesa Anita '.($fila->remem_nro_remesa ?? '').'.');
            }

            $dia = $this->diaDeYmd($fechaYmd);
            $etiqueta = $codigoMoneda === 2
                ? $this->etiquetaEgresoDolar($destino)
                : ($codigoMoneda === 3
                    ? $this->etiquetaEgresoEuro($destino)
                    : $this->etiquetaEgresoPesos($destino));
            $this->sumarEn($totales, $etiqueta, $dia, $importe);
            $totalEgresos[$dia] = round($totalEgresos[$dia] + ($importe * $cotizacion), 2);
        }

        $totales['Total de Egresos'] = $this->redondearVector($totalEgresos);

        return $totales;
    }

    private function destinoRememaeDesdeCuenta(Cuentacaja $cuenta): string
    {
        $texto = mb_strtoupper(trim((string) $cuenta->codigo).' '.trim((string) $cuenta->nombre));

        return match (true) {
            str_contains($texto, 'PAGO FACIL') => self::REMEM_PAGOFACIL,
            str_contains($texto, 'SEGURIDAD') => self::REMEM_CAJASEG,
            str_contains($texto, 'PROVINCIA') => self::REMEM_PROVINCIA,
            str_contains($texto, 'FRANCES'), str_contains($texto, 'BBVA') => self::REMEM_FRANCES,
            str_contains($texto, 'MACRO') => self::REMEM_MACRO,
            str_contains($texto, 'MACO'), str_contains($texto, 'GREEN ARMOR') => self::REMEM_MACO,
            default => 'erp:'.$cuenta->id,
        };
    }

    private function etiquetaEgresoCuentaErp(Cuentacaja $cuenta, string $destino, int $codigoMonedaAnita): string
    {
        if (! str_starts_with($destino, 'erp:')) {
            return $codigoMonedaAnita === 2
                ? $this->etiquetaEgresoDolar($destino)
                : ($codigoMonedaAnita === 3
                    ? $this->etiquetaEgresoEuro($destino)
                    : $this->etiquetaEgresoPesos($destino));
        }

        return 'Remesa a '.$cuenta->etiquetaOperaciones();
    }

    private function firmaRemesa(int $fechaYmd, string $destino, int $codigoMoneda, float $importe): string
    {
        return implode('|', [
            $fechaYmd,
            $destino,
            $codigoMoneda,
            number_format($importe, 2, '.', ''),
        ]);
    }

    /**
     * Medios no efectivos ya abiertos como ingreso. l-posfinanc.c los vuelve a
     * exponer como egreso para que no incrementen la posición de efectivo.
     *
     * @param  array<int, list<object>>  $valoresPorOper
     * @param  array<int, array{desc: string, tipo: string}>  $valormae
     * @param  list<object>  $cabGastro
     * @param  array<int, true>  $opsMaquina
     * @param  array<int, int>  $fechaPorOper
     * @param  array<int, true>  $codigosPermitidos
     * @return array<string, array<int, float>>
     */
    private function acumularAbiertosNoEfectivo(
        array $valoresPorOper,
        array $valormae,
        array $cabGastro,
        array $opsMaquina,
        int $empresaId,
        array $fechaPorOper,
        array $codigosPermitidos = [],
    ): array {
        $ops = $opsMaquina;
        foreach ($cabGastro as $fila) {
            $bloque = $this->clasificarSucursal($empresaId, (int) ($fila->rendg_sucursal ?? 0));
            if (in_array($bloque, [self::BLOQUE_GASTRO, self::BLOQUE_ESTAC], true)) {
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
                if ($codigosPermitidos !== [] && ! isset($codigosPermitidos[$codigo])) {
                    continue;
                }
                $tipo = $valormae[$codigo]['tipo'];
                if ($this->esTipoEfectivo($tipo) || $codigo === 8) {
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
            'saldo_inicial_origen' => null,
            'saldo_cierre_anterior_id' => null,
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
