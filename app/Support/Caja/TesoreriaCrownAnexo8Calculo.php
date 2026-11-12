<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Caja\TesoreriaCrownMonedaExtranjera;
use App\Models\Caja\TesoreriaCrownResumenFinalDia;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Caja\TesoreriaCrownValor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Anexo VIII / l-estboveda.c: estado de bóveda al cierre del día.
 *
 * Total (arch_tot_boveda, ítem 130 del anexo I) =
 *   stock pesos (arqueo A del día + slots recuento sin vínculo a bóveda + drops mesa)
 *   + ítems resumen final (pre-depósito, fondos, cajas chicas, diferencias, …)
 *   + moneda extranjera convertida (l-monextr: partidas × cotización de ingreso)
 *
 * La propina acumulada ya no suma al VIII: se registra en bóveda PROP
 * (Mesas/Pública con billetaje; Híbrida solo informativo RRHH) y en
 * tesoreria_crown_propina_dia para liquidación de sueldos.
 *
 * Fuentes ERP: stock_dia / arqueo conforme, bóveda resumen, monextr ERP.
 * Sin Anita en el cálculo (importar con tesoreria-crown:importar-anexo8-anita
 * si falta snapshot).
 *
 * Las fichas / poder público se listan (como l-estboveda proc_fichas) y no entran
 * en arch_tot_boveda: salen de TesoreriaCrownPoderPublicoCalculo (l-stockfichm).
 * Fichas de color y no negociable quedan unificadas como fichas de valor.
 */
final class TesoreriaCrownAnexo8Calculo
{
    private static ?bool $tieneCacheViii = null;

    private static function tieneCacheViii(): bool
    {
        return self::$tieneCacheViii ??= Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_viii');
    }

    /**
     * Signo del ítem en el resumen final (transm_signo_resumen).
     * '0' = suma al bucket, '1' = resta. Informix no expone la columna
     * (nombre > 18 chars); se fija acá según el par entrada/salida de cada bucket.
     *
     * @var array<int, string>
     */
    private const SIGNO_RESUMEN = [
        21 => '0', 22 => '1', // Pre depósito
        25 => '0', 30 => '1', // Fondo
        31 => '0', 32 => '1', // Caja chica hotel
        35 => '0', 36 => '1', // Diferencia tesorería
        6 => '0', 40 => '1',  // Caja chica adm.
        99 => '0', 98 => '1', // Cancela dif. final bóveda
    ];

    /** Grupos del snapshot que ya no entran al VIII (legado Anita). */
    private const GRUPOS_RESUMEN_EXCLUIDOS = [5]; // Diferencia a verificar (tipos 33/34)

    /**
     * @return array{
     *     lugares: list<array{lugar: string, grupos: list<array{titulo: string, filas: list<array{denominacion: string, cantidad: float, monto: float}>, monto: float}>, total: float}>,
     *     total: float,
     *     fichas: array{
     *         filas: list<array{serie: string, descripcion: string, valor: float, cantidad: int, monto: float, canje: bool}>,
     *         cant_fichas: int,
     *         monto_fichas: float,
     *         cant_placas: int,
     *         monto_placas: float,
     *         conversion_valorizada: float,
     *         poder_valorizado: float,
     *         diario_valorizado: float,
     *         origen: string
     *     }|null,
     *     componentes: array{
     *         pesos: float,
     *         slots: float,
     *         drops_mesa: float,
     *         resumen: float,
     *         resumen_detalle: list<array{codigo: int, descripcion: string, monto: float}>,
     *         propina: float,
     *         moneda_extranjera: float,
     *         usd_face: float,
     *         eur_face: float,
     *         cotiz_usd: float,
     *         cotiz_eur: float
     *     },
     *     avisos: list<string>
     * }
     */
    /**
     * Total del VIII: usa `caja_final_viii` si está grabado; si no, calcula y persiste.
     * Forzar = true recalcula siempre (p. ej. al abrir el panel VIII).
     */
    public static function totalCacheadoOCalcular(TesoreriaCrownJornada $jornada, bool $forzar = false): float
    {
        TesoreriaCrownRecalculoAdelanteSupport::asegurar($jornada);
        if (! $forzar
            && self::tieneCacheViii()
            && $jornada->caja_final_viii !== null) {
            return round((float) $jornada->caja_final_viii, 2);
        }

        return (float) self::deJornada($jornada, false)['total'];
    }

    /** Invalida el cache del VIII (p. ej. al confirmar bóveda / slots / drops / propina). */
    public static function invalidarCache(TesoreriaCrownJornada $jornada): void
    {
        if (! self::tieneCacheViii()) {
            return;
        }
        if ($jornada->caja_final_viii === null) {
            return;
        }
        $jornada->forceFill(['caja_final_viii' => null])->save();
    }

    private static function persistirTotal(TesoreriaCrownJornada $jornada, float $total): void
    {
        if (! self::tieneCacheViii()) {
            return;
        }
        $jornada->forceFill(['caja_final_viii' => round($total, 2)])->save();
    }

    /**
     * @param  bool  $compararAnita  Legacy (ignorado): el corte Anita ya no compara contra acumanexo1.
     */
    public static function deJornada(TesoreriaCrownJornada $jornada, bool $compararAnita = false): array
    {
        TesoreriaCrownRecalculoAdelanteSupport::asegurar($jornada);
        $fecha = $jornada->fecha->format('Y-m-d');
        $fechaInt = (int) $jornada->fecha->format('Ymd');
        $salaId = (int) $jornada->sala_id;
        $avisos = [];

        $lugares = TesoreriaCrownBovedaSupport::lugares($salaId)
            ->filter(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');

        $slots = self::cantidadesSlots($jornada);
        $drops = self::cantidadesDropsMesa($jornada);
        $slotsMonto = self::montoPorCodigo($salaId, $slots, 'ARS');
        $dropsMonto = self::montoPorCodigo($salaId, $drops, 'ARS');

        // Stock del día: ERP stock_dia → arqueo turno conforme (sin Anita).
        $arqueoDia = self::cantidadesArqueoDia($jornada, $salaId);
        $pesosArqueo = self::montoPorCodigo($salaId, $arqueoDia['ARS'], 'ARS');
        $usdFaceArqueo = self::montoPorCodigo($salaId, $arqueoDia['USD'], 'USD');
        $eurFaceArqueo = self::montoPorCodigo($salaId, $arqueoDia['EUR'], 'EUR');

        $cantidadesStock = self::sumarCantidades($arqueoDia['ARS'], $arqueoDia['USD'], $arqueoDia['EUR'], $slots, $drops);
        if ($pesosArqueo < 0.001 && $usdFaceArqueo < 0.001 && $eurFaceArqueo < 0.001) {
            $cantidadesStock = [];
            foreach ($lugares as $lugar) {
                $cantidadesStock = self::sumarCantidades(
                    $cantidadesStock,
                    TesoreriaCrownBovedaSupport::saldoCantidades((int) $lugar->id, $fecha)
                );
            }
            $cantidadesStock = self::sumarCantidades($cantidadesStock, $slots, $drops);
        }

        $pesosStock = self::montoPorCodigo($salaId, $cantidadesStock, 'ARS');
        $usdFace = self::montoPorCodigo($salaId, $cantidadesStock, 'USD');
        $eurFace = self::montoPorCodigo($salaId, $cantidadesStock, 'EUR');
        // Face FX del arqueo (en el CSV no hay USD/EUR en S/M).
        if ($usdFaceArqueo > 0.001 || $eurFaceArqueo > 0.001) {
            $usdFace = $usdFaceArqueo;
            $eurFace = $eurFaceArqueo;
        }

        $fx = self::monedaExtranjera($fecha, $fechaInt);
        $fxPesos = $fx['pesos'];
        $resumen = self::resumenFinal($fecha);

        // En l-estboveda slots/drops ya van dentro de Ptot_boveda1 (= pesosStock acá).
        // Propina acumulada fuera del VIII (bóveda PROP + propina_dia RRHH).
        $total = round($pesosStock + $resumen['total'] + $fxPesos, 2);

        $out = [];
        foreach ($lugares as $lugar) {
            $gruposRaw = TesoreriaCrownBovedaSupport::saldoAgrupado($salaId, $cantidadesStock);
            $grupos = [];
            $totalLugar = 0.0;
            foreach ($gruposRaw as $grupo) {
                $filas = [];
                foreach ($grupo['filas'] as $fila) {
                    // Anita muestra face en USD/EUR; la conversión va aparte (líneas convertidos).
                    $den = (float) ($fila['valor']->denominacion ?? 0);
                    $codigoMon = (string) ($fila['valor']->tipoValor->codigo ?? '');
                    $sufijo = match ($codigoMon) {
                        'USD' => ' u$s',
                        'EUR' => ' EUR',
                        default => ' $',
                    };
                    $filas[] = [
                        'denominacion' => rtrim(rtrim(number_format($den, 2, '.', ''), '0'), '.').$sufijo,
                        'cantidad' => (float) $fila['cantidad'],
                        'monto' => (float) $fila['monto'],
                    ];
                }
                $montoGrupo = round(array_sum(array_column($filas, 'monto')), 2);
                $grupos[] = [
                    'titulo' => (string) $grupo['titulo'],
                    'filas' => $filas,
                    'monto' => $montoGrupo,
                ];
                $totalLugar = round($totalLugar + $montoGrupo, 2);
            }
            if ($out === []) {
                if ($resumen['detalle'] !== []) {
                    $filasResumen = [];
                    foreach ($resumen['detalle'] as $item) {
                        if (abs($item['monto']) < 0.001) {
                            continue;
                        }
                        $filasResumen[] = [
                            'denominacion' => $item['descripcion'],
                            'cantidad' => 1.0,
                            'monto' => $item['monto'],
                        ];
                    }
                    if ($filasResumen !== []) {
                        $grupos[] = [
                            'titulo' => 'Items resumen final',
                            'filas' => $filasResumen,
                            'monto' => $resumen['total'],
                        ];
                        $totalLugar = round($totalLugar + $resumen['total'], 2);
                    }
                }
                if (abs($fxPesos) > 0.001) {
                    $filasFx = [];
                    if (abs($fx['usd_pesos']) > 0.001) {
                        $filasFx[] = [
                            'denominacion' => sprintf(
                                'Total dolares convertidos a pesos (equiv. %s u$s)',
                                number_format($fx['usd_face'], 2, ',', '.')
                            ),
                            'cantidad' => 1.0,
                            'monto' => round($fx['usd_pesos'], 2),
                        ];
                    }
                    if (abs($fx['eur_pesos']) > 0.001) {
                        $filasFx[] = [
                            'denominacion' => sprintf(
                                'Total euros convertidos a pesos (equiv. %s EUR)',
                                number_format($fx['eur_face'], 2, ',', '.')
                            ),
                            'cantidad' => 1.0,
                            'monto' => round($fx['eur_pesos'], 2),
                        ];
                    }
                    if ($filasFx === []) {
                        $filasFx[] = [
                            'denominacion' => 'Otras monedas segun cotiz.',
                            'cantidad' => 1.0,
                            'monto' => $fxPesos,
                        ];
                    }
                    $grupos[] = [
                        'titulo' => 'Otras monedas segun cotiz.',
                        'filas' => $filasFx,
                        'monto' => $fxPesos,
                    ];
                    $totalLugar = round($totalLugar + $fxPesos, 2);
                }
            }
            $out[] = [
                'lugar' => (string) $lugar->codigo.' — '.(string) $lugar->nombre,
                'grupos' => $grupos,
                'total' => $totalLugar,
            ];
            // Un solo bloque de stock (como el listado Anita); el resto de lugares no duplica.
            break;
        }

        unset($compararAnita);

        // pesos = arqueo del día (vault); slots/drops se muestran aparte como en el desglose.
        $pesosVault = $pesosArqueo > 0.001
            ? $pesosArqueo
            : round($pesosStock - $slotsMonto - $dropsMonto, 2);

        $fichas = self::stockFichas($fecha, $avisos);

        self::persistirTotal($jornada, $total);
        $difBoveda = self::difFinalBoveda($jornada, $total);
        if ($difBoveda['saldo_final_anexo1'] === null) {
            $avisos[] = 'Dif. final de bóveda: falta el saldo final del Anexo I (abrí el Anexo I para calcularlo).';
        }

        return [
            'lugares' => $out,
            'total' => $total,
            'fichas' => $fichas,
            'saldo_final_anexo1' => $difBoveda['saldo_final_anexo1'],
            'dif_final_boveda' => $difBoveda['dif_final_boveda'],
            'componentes' => [
                'pesos' => $pesosVault,
                'slots' => $slotsMonto,
                'drops_mesa' => $dropsMonto,
                'resumen' => $resumen['total'],
                'resumen_detalle' => $resumen['detalle'],
                'propina' => 0.0,
                'moneda_extranjera' => $fxPesos,
                'usd_face' => round($fx['usd_face'], 2),
                'eur_face' => round($fx['eur_face'], 2),
                'usd_pesos' => round($fx['usd_pesos'], 2),
                'eur_pesos' => round($fx['eur_pesos'], 2),
                'cotiz_usd' => $fx['usd_face'] > 0.001 ? round($fx['usd_pesos'] / $fx['usd_face'], 4) : 0.0,
                'cotiz_eur' => $fx['eur_face'] > 0.001 ? round($fx['eur_pesos'] / $fx['eur_face'], 4) : 0.0,
                'saldo_final_anexo1' => $difBoveda['saldo_final_anexo1'],
                'dif_final_boveda' => $difBoveda['dif_final_boveda'],
            ],
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * Stock de fichas en conversión (l-estboveda proc_fichas / l-stockfichm).
     * No suma al total del VIII.
     *
     * @param  list<string>  $avisos
     * @return array{
     *     filas: list<array{serie: string, descripcion: string, valor: float, cantidad: int, monto: float, canje: bool}>,
     *     cant_fichas: int,
     *     monto_fichas: float,
     *     cant_placas: int,
     *     monto_placas: float,
     *     conversion_valorizada: float,
     *     poder_valorizado: float,
     *     diario_valorizado: float,
     *     origen: string
     * }|null
     */
    private static function stockFichas(string $fechaYmd, array &$avisos): ?array
    {
        try {
            $armado = TesoreriaCrownPoderPublicoCalculo::delDia($fechaYmd);
        } catch (Throwable $e) {
            $avisos[] = 'No se pudo armar el stock de fichas: '.$e->getMessage();

            return null;
        }
        $filas = [];
        $cantFichas = 0;
        $montoFichas = 0.0;
        foreach ($armado['filas'] ?? [] as $fila) {
            $cantidad = (int) ($fila['final'] ?? 0);
            $valor = (float) ($fila['valor'] ?? 0);
            $monto = round($cantidad * $valor, 2);
            $filas[] = [
                'serie' => (string) ($fila['serie'] ?? ''),
                'descripcion' => (string) ($fila['descripcion'] ?? ''),
                'valor' => $valor,
                'cantidad' => $cantidad,
                'monto' => $monto,
                // Compat: ya no hay placas / no negociable; todo es ficha de valor.
                'canje' => false,
            ];
            $cantFichas += $cantidad;
            $montoFichas += $monto;
        }

        return [
            'filas' => $filas,
            'cant_fichas' => $cantFichas,
            'monto_fichas' => round($montoFichas, 2),
            'cant_placas' => 0,
            'monto_placas' => 0.0,
            'conversion_valorizada' => round((float) ($armado['conversion_valorizada'] ?? 0), 2),
            'poder_valorizado' => round((float) ($armado['poder_valorizado'] ?? 0), 2),
            'diario_valorizado' => round((float) ($armado['diario_valorizado'] ?? 0), 2),
            'origen' => (string) ($armado['origen'] ?? ''),
        ];
    }

    /**
     * Arqueo final del día por moneda → valor_id.
     * Orden: stock_dia ERP → arqueo turno conforme.
     *
     * @return array{ARS: array<int, float>, USD: array<int, float>, EUR: array<int, float>}
     */
    private static function cantidadesArqueoDia(
        TesoreriaCrownJornada $jornada,
        int $salaId
    ): array {
        $vacio = ['ARS' => [], 'USD' => [], 'EUR' => []];
        $erp = TesoreriaCrownBovedaStockDiaSupport::cantidadesDeJornada($jornada);
        if (is_array($erp) && $erp !== []) {
            return self::partirCantidadesPorMoneda($salaId, $erp);
        }
        $conforme = TesoreriaCrownBovedaStockDiaSupport::cantidadesDesdeArqueoConforme($jornada);
        if ($conforme !== []) {
            return self::partirCantidadesPorMoneda($salaId, $conforme);
        }

        return $vacio;
    }

    /**
     * @param  array<int, float>  $cantidades
     * @return array{ARS: array<int, float>, USD: array<int, float>, EUR: array<int, float>}
     */
    private static function partirCantidadesPorMoneda(int $salaId, array $cantidades): array
    {
        return [
            'ARS' => self::filtrarPorCodigo($salaId, $cantidades, 'ARS'),
            'USD' => self::filtrarPorCodigo($salaId, $cantidades, 'USD'),
            'EUR' => self::filtrarPorCodigo($salaId, $cantidades, 'EUR'),
        ];
    }

    /**
     * Conversión FX como l-monextr.c: saldo partida × cotización de ingreso (ledger ERP).
     *
     * @return array{pesos: float, usd_face: float, eur_face: float, usd_pesos: float, eur_pesos: float}
     */
    private static function monedaExtranjera(string $fechaYmd, int $fechaInt): array
    {
        unset($fechaInt);

        return self::monedaExtranjeraErp($fechaYmd);
    }

    /**
     * @return array{pesos: float, usd_face: float, eur_face: float, usd_pesos: float, eur_pesos: float}
     */
    private static function monedaExtranjeraErp(string $fechaYmd): array
    {
        $vacio = ['pesos' => 0.0, 'usd_face' => 0.0, 'eur_face' => 0.0, 'usd_pesos' => 0.0, 'eur_pesos' => 0.0];
        if (! Schema::hasTable('tesoreria_crown_moneda_extranjera')) {
            return $vacio;
        }
        $partidas = TesoreriaCrownMonedaExtranjera::query()
            ->with(['movimientos' => fn ($q) => $q->whereDate('fecha', '<=', $fechaYmd)])
            ->whereDate('fecha', '<=', $fechaYmd)
            ->where('monto_orig', '<>', 0)
            ->get();
        if ($partidas->isEmpty()) {
            return $vacio;
        }
        $usdFace = $eurFace = $usdPesos = $eurPesos = 0.0;
        foreach ($partidas as $partida) {
            $apps = 0.0;
            foreach ($partida->movimientos as $mov) {
                $monto = (float) $mov->monto;
                if ($monto < 0) {
                    $apps += $monto;
                }
            }
            $saldo = (float) $partida->monto_orig + $apps;
            if ($saldo <= 0.0) {
                continue;
            }
            $cot = (float) $partida->cotizacion;
            if ($partida->moneda === 'USD') {
                $usdFace += $saldo;
                $usdPesos += $saldo * $cot;
            } elseif ($partida->moneda === 'EUR') {
                $eurFace += $saldo;
                $eurPesos += $saldo * $cot;
            }
        }
        $pesos = round($usdPesos + $eurPesos, 2);

        return [
            'pesos' => $pesos,
            'usd_face' => round($usdFace, 2),
            'eur_face' => round($eurFace, 2),
            'usd_pesos' => round($usdPesos, 2),
            'eur_pesos' => round($eurPesos, 2),
        ];
    }

    /**
     * @return array<int, float>
     */
    private static function cantidadesSlots(TesoreriaCrownJornada $jornada): array
    {
        // Solo slots sin vínculo a bóveda: los CashBox ya asentados entran por el arqueo A.
        $filas = TesoreriaCrownSlot::query()
            ->with(['lineas'])
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownSlot::RECIBIDO)
            ->where('tipo', '!=', TesoreriaCrownSlot::DROP)
            ->where(function ($q) {
                $q->whereNull('boveda_id')->orWhere('boveda_id', 0);
            })
            ->get();
        $out = [];
        foreach ($filas as $slot) {
            foreach ($slot->lineas as $linea) {
                $valorId = (int) $linea->valor_id;
                $out[$valorId] = ($out[$valorId] ?? 0.0) + (float) $linea->cantidad;
            }
        }

        return $out;
    }

    /**
     * @return array<int, float>
     */
    private static function cantidadesDropsMesa(TesoreriaCrownJornada $jornada): array
    {
        $rows = DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_mesa_linea as l', 'l.mesa_id', '=', 'm.id')
            ->where('m.jornada_id', $jornada->id)
            ->where('m.tipo', 'D')
            ->where('m.estado', TesoreriaCrownMesa::CONFIRMADO)
            ->whereNull('m.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_mesa as r')
                    ->whereColumn('r.revierte_id', 'm.id');
            })
            ->groupBy('l.valor_id')
            ->get(['l.valor_id', DB::raw('SUM(l.cantidad) as cantidad')]);
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->valor_id] = (float) $row->cantidad;
        }

        return $out;
    }

    /**
     * Ítems de resumen final (l-estboveda procesa_resumen_final).
     * Orden: snapshot diario ERP → movimientos bóveda ERP.
     *
     * @return array{total: float, detalle: list<array{codigo: int, descripcion: string, monto: float}>}
     */
    private static function resumenFinal(string $fechaYmd): array
    {
        $grupoDeTipo = [
            21 => 1, 22 => 1,
            25 => 3, 30 => 3,
            31 => 4, 32 => 4,
            35 => 6, 36 => 6,
            6 => 7, 40 => 7,
            99 => 99, 98 => 99,
        ];
        $codigos = array_keys(self::SIGNO_RESUMEN);
        $porGrupo = self::resumenFinalSnapshot($fechaYmd);
        if ($porGrupo === []) {
            $porGrupo = self::resumenFinalErp($fechaYmd, $codigos, $grupoDeTipo);
        }
        foreach (self::GRUPOS_RESUMEN_EXCLUIDOS as $excluido) {
            unset($porGrupo[$excluido]);
        }

        $descripciones = self::descripcionesResumen();
        $detalle = [];
        $total = 0.0;
        ksort($porGrupo);
        foreach ($porGrupo as $codigo => $monto) {
            $monto = round($monto, 2);
            if (abs($monto) < 0.001) {
                continue;
            }
            $detalle[] = [
                'codigo' => (int) $codigo,
                'descripcion' => $descripciones[(int) $codigo] ?? ('Resumen '.$codigo),
                'monto' => $monto,
            ];
            $total = round($total + $monto, 2);
        }

        return ['total' => $total, 'detalle' => $detalle];
    }

    /**
     * @return array<int, float>
     */
    private static function resumenFinalSnapshot(string $fechaYmd): array
    {
        if (! Schema::hasTable('tesoreria_crown_resumen_final_dia')) {
            return [];
        }
        $filas = TesoreriaCrownResumenFinalDia::query()
            ->whereDate('fecha', $fechaYmd)
            ->get(['codigo', 'monto']);
        if ($filas->isEmpty()) {
            return [];
        }
        $porGrupo = [];
        foreach ($filas as $fila) {
            $porGrupo[(int) $fila->codigo] = (float) $fila->monto;
        }

        return $porGrupo;
    }

    /**
     * @param  list<int>  $codigos
     * @param  array<int, int>  $grupoDeTipo
     * @return array<int, float>
     */
    private static function resumenFinalErp(string $fechaYmd, array $codigos, array $grupoDeTipo): array
    {
        $movimientos = TesoreriaCrownBoveda::query()
            ->with(['lineas.valor.tipoValor', 'tipo'])
            ->where('estado', TesoreriaCrownBoveda::CONFIRMADO)
            ->whereNull('revierte_id')
            ->whereDoesntHave('reversa')
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '<=', $fechaYmd))
            ->whereHas('tipo', fn ($q) => $q->whereIn('codigo', $codigos))
            ->get();
        if ($movimientos->isEmpty()) {
            return [];
        }

        $porGrupo = [];
        foreach ($movimientos as $mov) {
            $cod = (int) ($mov->tipo->codigo ?? 0);
            $sr = self::SIGNO_RESUMEN[$cod] ?? null;
            if ($sr === null) {
                continue;
            }
            $signo = $sr === '1' ? -1.0 : 1.0;
            $grupo = $grupoDeTipo[$cod] ?? $cod;
            $bruto = 0.0;
            foreach ($mov->lineas as $linea) {
                $moneda = (string) ($linea->valor->tipoValor->codigo ?? '');
                if ($moneda !== '' && $moneda !== 'ARS') {
                    continue;
                }
                $bruto += (float) $linea->cantidad * (float) ($linea->valor->denominacion ?? 0);
            }
            $porGrupo[$grupo] = ($porGrupo[$grupo] ?? 0.0) + ($bruto * $signo);
        }

        return $porGrupo;
    }

    /**
     * @return array<int, string>
     */
    private static function descripcionesResumen(): array
    {
        return [
            1 => 'Pre deposito',
            3 => 'FONDO',
            4 => 'Caja chica hotel',
            6 => 'Diferencia tesoreria',
            7 => 'Caja chica adm.',
            99 => 'Cancela dif. final boveda',
        ];
    }

    /**
     * Dif. final de bóveda = total VIII − saldo final Anexo I (caja por variación).
     * Usa `caja_final_dia` ya persistido por el Anexo I; si falta, null.
     *
     * @return array{saldo_final_anexo1: ?float, dif_final_boveda: ?float}
     */
    private static function difFinalBoveda(TesoreriaCrownJornada $jornada, float $totalViii): array
    {
        if (! Schema::hasColumn('tesoreria_crown_jornada', 'caja_final_dia')
            || $jornada->caja_final_dia === null) {
            return ['saldo_final_anexo1' => null, 'dif_final_boveda' => null];
        }
        $saldo = round((float) $jornada->caja_final_dia, 2);

        return [
            'saldo_final_anexo1' => $saldo,
            'dif_final_boveda' => round($totalViii - $saldo, 2),
        ];
    }

    /**
     * @param  array<int, float>  ...$mapas
     * @return array<int, float>
     */
    private static function sumarCantidades(array ...$mapas): array
    {
        $out = [];
        foreach ($mapas as $mapa) {
            foreach ($mapa as $valorId => $cantidad) {
                $out[(int) $valorId] = ($out[(int) $valorId] ?? 0.0) + (float) $cantidad;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $cantidades
     * @return array<int, float>
     */
    private static function filtrarPorCodigo(int $salaId, array $cantidades, string $codigo): array
    {
        if ($cantidades === []) {
            return [];
        }
        $ids = TesoreriaCrownValor::query()
            ->where('sala_id', $salaId)
            ->whereIn('id', array_keys($cantidades))
            ->whereHas('tipoValor', fn ($q) => $q->where('codigo', $codigo))
            ->pluck('id')
            ->all();
        $out = [];
        foreach ($ids as $id) {
            $out[(int) $id] = (float) ($cantidades[(int) $id] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<int, float>  $cantidades
     */
    private static function montoPorCodigo(int $salaId, array $cantidades, string $codigo): float
    {
        $filtrado = self::filtrarPorCodigo($salaId, $cantidades, $codigo);
        if ($filtrado === []) {
            return 0.0;
        }
        $valores = TesoreriaCrownValor::query()
            ->whereIn('id', array_keys($filtrado))
            ->get(['id', 'denominacion'])
            ->keyBy('id');
        $total = 0.0;
        foreach ($filtrado as $valorId => $cantidad) {
            $valor = $valores->get((int) $valorId);
            if ($valor === null) {
                continue;
            }
            $total += (float) $cantidad * (float) $valor->denominacion;
        }

        return round($total, 2);
    }

}
