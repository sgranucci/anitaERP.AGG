<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBingo;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Anexo I como l-anexo1.c: columnas día, mes y anual.
 *
 * El día sale de la jornada ERP (cajeros, mesas, bóveda). No se reconstruye
 * con el join de efeboveda y rendmbill: ese cruce recorre todo el detalle
 * de billetes y es lo que demoraba la pantalla.
 *
 * Drop, clientes y caja inicial: solo ERP. Si falta el dato, queda
 * en 0 / vacío con aviso para cargarlo o importarlo; no hay lectura live a Anita.
 * Propina empleados y depósito propina no entran (reporte propinas por casino).
 * Caja inicial: `caja_final_viii` de la jornada anterior (total l-estboveda);
 * si falta, se calcula y se persiste.
 * `caja_final_dia` / `caja_final_mes` son la caja final por variación del Anexo I
 * (no confundir con el VIII).
 * Tragamonedas / on-line: resultado online de la sala.
 * Poder público (fichas y slot): TesoreriaCrownPoderPublicoGenerador
 * (persiste en tesoreria_crown_poder_publico).
 */
final class TesoreriaCrownAnexo1Calculo
{
    /** @var array<string, array<int|string, mixed>> */
    private static array $precarga = [];

    /** @var array<string, bool> */
    private static array $columnas = [];

    private static function tieneColumna(string $columna): bool
    {
        return self::$columnas[$columna] ??= Schema::hasColumn('tesoreria_crown_jornada', $columna);
    }

    /**
     * @return array{
     *     filas: list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool, informativo?: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
        TesoreriaCrownRecalculoAdelanteSupport::asegurar($jornada);
        $fecha = $jornada->fecha->copy()->startOfDay();
        $desdeMes = $fecha->copy()->startOfMonth();
        $desdeAnio = $fecha->copy()->startOfYear();
        $jornadas = TesoreriaCrownJornada::query()
            ->where('sala_id', $jornada->sala_id)
            ->whereDate('fecha', '>=', $desdeAnio->toDateString())
            ->whereDate('fecha', '<=', $fecha->toDateString())
            ->orderBy('fecha')
            ->get();

        $avisos = [];
        // Un solo eager-load de cajeros/rendiciones del año (evita 1 rescajero × día).
        TesoreriaCrownResumenCajeroCalculo::precargarParaJornadas($jornadas);
        self::precargarDias($jornadas);
        $dias = [];
        try {
            foreach ($jornadas as $una) {
                $dias[] = [
                    'en_mes' => $una->fecha->greaterThanOrEqualTo($desdeMes),
                    'importes' => self::importesDia($una, $avisos),
                ];
            }
        } finally {
            TesoreriaCrownResumenCajeroCalculo::olvidarCache();
            TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::olvidarPrecarga();
            self::$precarga = [];
        }

        $sumar = function (string $clave, bool $mes) use ($dias): float {
            $total = 0.0;
            foreach ($dias as $dia) {
                if ($mes && ! $dia['en_mes']) {
                    continue;
                }
                $total += (float) ($dia['importes'][$clave] ?? 0);
            }

            return round($total, 2);
        };

        $ultimo = $dias === [] ? self::ceros() : $dias[array_key_last($dias)]['importes'];
        $valor = function (string $clave) use ($sumar, $ultimo): array {
            return [
                'dia' => round((float) ($ultimo[$clave] ?? 0), 2),
                'mes' => $sumar($clave, true),
                'anio' => $sumar($clave, false),
            ];
        };

        $poder = self::poderPublico($fecha->format('Ymd'), $avisos);
        $inicial = self::cajaInicial($fecha->copy()->subDay()->format('Ymd'), $fecha->format('Ymd'), $avisos);

        $sala = $valor('sala');
        $ruleta = $valor('ruleta');
        $slot = $valor('slot');
        $juego = self::suma($sala, $ruleta, $slot);
        $gastro = $valor('gastro');
        $hotel = $valor('hotel');
        $recaudacion = self::suma($juego, $gastro, $hotel);

        $poder = $poder ?? [
            'ficha_dia' => 0.0,
            'ficha_mes' => 0.0,
            'ficha_anio' => 0.0,
            'slot_dia' => 0.0,
            'slot_mes' => 0.0,
            'slot_anio' => 0.0,
        ];
        $poderFila = [
            'dia' => (float) $poder['ficha_dia'],
            'mes' => (float) $poder['ficha_mes'],
            'anio' => (float) $poder['ficha_anio'],
        ];
        $slotPoder = [
            'dia' => (float) $poder['slot_dia'],
            'mes' => (float) $poder['slot_mes'],
            'anio' => (float) $poder['slot_anio'],
        ];
        if (abs($slotPoder['dia']) < 0.001 && abs($slotPoder['mes']) < 0.001 && abs($slotPoder['anio']) < 0.001) {
            $avisos[] = 'Poder público de slot en cero: falta subtotal de soft count o resultado de slots del día.';
        }
        // Propina empleados / depósito propina: fuera del Anexo I (reporte propinas por casino).
        $torneo = $valor('torneo');
        $bingo = $valor('bingo');
        $online = $valor('online');
        $voucher = $valor('voucher');
        $apuestas = $valor('apuestas');
        $bancarios = $valor('bancarios');
        $excedente = $valor('excedente');
        $recargo = $valor('recargo');
        $otros = self::suma($poderFila, $slotPoder, $torneo, $bingo, $online, $voucher, $apuestas, $bancarios, $excedente, $recargo);
        $ingresos = self::suma($recaudacion, $otros);

        $compras = $valor('compras');
        $fallos = $valor('fallos');
        $deposito = $valor('deposito');
        $credito = $valor('credito');
        $debito = $valor('debito');
        $qr = $valor('qr');
        $dolares = $valor('dolares');
        $euros = $valor('euros');
        $ctacte = $valor('ctacte');
        $transferencia = $valor('transferencia');
        $otrosEgresos = $valor('otros_egresos');
        $egresos = self::suma($compras, $fallos, $deposito, $credito, $debito, $qr, $dolares, $euros, $ctacte, $transferencia, $otrosEgresos);
        $variacion = self::resta($ingresos, $egresos);

        $finalDia = $inicial['dia'] === null ? null : round($variacion['dia'] + $inicial['dia'], 2);
        $finalMes = $inicial['mes'] === null ? null : round($variacion['mes'] + $inicial['mes'], 2);
        $anexo8 = $valor('anexo8');

        $filas = [];
        $clientes = self::lin('Clientes', [
            'dia' => (float) ($ultimo['clientes'] ?? 0),
            'mes' => $sumar('clientes', true),
            'anio' => $sumar('clientes', false),
        ]);
        $clientes['informativo'] = true;
        $filas[] = $clientes;
        $filas[] = self::sec('INGRESOS');
        $filas[] = self::lin('Recaudación sala convencional (anexo II)', $sala);
        $filas[] = self::lin('Recaudación ruleta electrónica (anexo II)', $ruleta);
        $filas[] = self::lin('Recaudación máquinas tragamonedas (anexo II)', $slot);
        $filas[] = self::tot('Total de recaudaciones por juego', $juego);
        $filas[] = self::lin('Total ventas de gastronomía al contado (anexo III)', $gastro);
        $filas[] = self::lin('Total ventas de hotelería al contado (anexo III)', $hotel);
        $filas[] = self::tot('Total recaudación', $recaudacion);
        $filas[] = self::lin('Fichas poder público (anexo II)', $poderFila);
        $filas[] = self::lin('Fichas poder público slot (anexo II)', $slotPoder);
        $filas[] = self::lin('Torneos (anexo II)', $torneo);
        $filas[] = self::lin('Bingo (anexo II)', $bingo);
        $filas[] = self::lin('On-line', $online);
        $filas[] = self::lin('Poder público voucher', $voucher);
        $filas[] = self::lin('Apuesta deportiva', $apuestas);
        $filas[] = self::lin('Movimientos bancarios y financieros', $bancarios);
        $filas[] = self::lin('Excedente de caja (anexo II)', $excedente);
        $filas[] = self::lin('Recargo financiero', $recargo);
        $filas[] = self::tot('Total otros ingresos', $otros);
        $filas[] = self::tot('Total ingresos', $ingresos);
        $filas[] = self::sec('EGRESOS');
        $filas[] = self::lin('Compras y gastos en efectivo y pagos de cuenta corriente (anexo VI)', $compras);
        $filas[] = self::lin('Anticipos a cuenta de sueldo y fallos de caja (anexo VII)', $fallos);
        $filas[] = self::lin('Depósito efectivo', $deposito);
        $filas[] = self::lin('Tarjetas de crédito', $credito);
        $filas[] = self::lin('Tarjetas de débito', $debito);
        $filas[] = self::lin('Total QR', $qr);
        $filas[] = self::lin('Depósito dólares', $dolares);
        $filas[] = self::lin('Depósito euros', $euros);
        $filas[] = self::lin('Cuenta corriente hotel', $ctacte);
        $filas[] = self::lin('Transferencia a cuenta hotel', $transferencia);
        $filas[] = self::lin('Otros egresos', $otrosEgresos);
        $filas[] = self::tot('Total egresos', $egresos);
        $filas[] = self::tot('Variación de la caja', $variacion);
        $filas[] = self::sec('CAJA');
        $filas[] = self::lin('Caja inicial', $inicial);
        $filas[] = self::lin('Caja final (por variación)', ['dia' => $finalDia, 'mes' => $finalMes, 'anio' => null]);
        $filas[] = self::lin('Caja final (anexo VIII)', ['dia' => $anexo8['dia'], 'mes' => 0.0, 'anio' => null]);
        $diferenciaDia = $finalDia === null ? null : round($anexo8['dia'] - $finalDia, 2);
        $filas[] = self::tot('Diferencia', ['dia' => $diferenciaDia, 'mes' => 0.0, 'anio' => null]);

        $avisos[] = 'La caja final del anexo VIII es el total de l-estboveda (bóveda + slots + drops + resumen + propina + FX).';
        if (abs((float) ($ultimo['ruleta'] ?? 0)) < 0.001) {
            $avisos[] = 'Ruleta electrónica queda en cero: Crown no tiene carga nativa de ese juego en el ERP.';
        }
        if (abs((float) ($ultimo['bingo'] ?? 0)) < 0.001) {
            $avisos[] = 'Bingo en cero: cargá el movimiento del día (tesorería Crown → Bingo) o importá movbingo.';
        }

        self::persistirCajaFinal($jornada, $finalDia, $finalMes);

        return [
            'filas' => $filas,
            'cerrado' => false,
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * @return array{
     *     filas: list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>
     * }
     */
    public static function delDia(string $fechaYmd): array
    {
        $jornada = TesoreriaCrownJornada::query()
            ->whereDate('fecha', $fechaYmd)
            ->orderBy('id')
            ->first();
        if (! $jornada instanceof TesoreriaCrownJornada) {
            return [
                'filas' => [],
                'cerrado' => false,
                'avisos' => ['No hay jornada de tesorería para el '.$fechaYmd.'.'],
            ];
        }

        return self::deJornada($jornada);
    }

    /**
     * @param  list<string>  $avisos
     * @return array<string, float>
     */
    private static function importesDia(TesoreriaCrownJornada $jornada, array &$avisos): array
    {
        $out = self::ceros();
        $resumen = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $porTipo = [];
        foreach ($resumen['grupos'] as $grupo) {
            $porTipo[(string) $grupo['tipo']] = $grupo['totales'];
        }
        $ventaContado = function (string $tipo) use ($porTipo): float {
            $tot = $porTipo[$tipo] ?? null;
            if (! is_array($tot)) {
                return 0.0;
            }

            return round((float) ($tot['resultado'] ?? 0) - (float) ($tot['diferencia'] ?? 0), 2);
        };
        $out['gastro'] = $ventaContado('G');
        $out['hotel'] = $ventaContado('H');

        // Propina empleados / depósito propina ya no entran al Anexo I (reporte propinas por casino).
        $out['propina'] = 0.0;

        foreach ($resumen['filas'] as $fila) {
            if (! empty($fila['propina'])) {
                continue;
            }
            $dif = (float) ($fila['diferencia'] ?? 0);
            if ($dif > 0.001) {
                $out['excedente'] += $dif;
            } elseif ($dif < -0.001) {
                $out['fallos'] += abs($dif);
            }
            $out['online'] -= (float) ($fila['online'] ?? 0);
            $out['credito'] += (float) ($fila['tarjeta_credito'] ?? 0);
            $out['debito'] += (float) ($fila['tarjeta_debito'] ?? 0);
            $out['qr'] += (float) ($fila['total_qr'] ?? 0);
            $out['recargo'] += (float) ($fila['recargo_fin'] ?? 0);
            // l-anexo1.c: efe_ctacte_hotel y efe_transferencia_hotel en renglones distintos.
            $transferenciaHotel = (float) ($fila['transferencia_hotel'] ?? 0);
            $out['ctacte'] += (float) ($fila['ctacte'] ?? 0) - $transferenciaHotel;
            $out['transferencia'] += $transferenciaHotel;
            $out['apuestas'] += (float) ($fila['vta_apuesta'] ?? 0) - (float) ($fila['pago_apuesta'] ?? 0);
        }

        $boveda = self::bovedaPesos($jornada);
        $sumarCod = function (int ...$codigos) use ($boveda): float {
            $total = 0.0;
            foreach ($codigos as $codigo) {
                $total += (float) ($boveda[$codigo] ?? 0);
            }

            return round($total, 2);
        };
        $out['torneo'] = $sumarCod(37);
        $out['bancarios'] = $sumarCod(25, 1, 41, 37, 29);
        $out['excedente'] = round($out['excedente'] + $sumarCod(17, 13), 2);
        // lee_fallo_boveda (l-anexo7.c): adelanto, fallo de caja y faltante de bóveda a cuenta de terceros.
        $out['fallos'] = round($out['fallos'] + $sumarCod(...TesoreriaCrownAnexo7Calculo::CODIGOS_BOVEDA_FALLO), 2);
        $out['compras'] = $sumarCod(2);
        $out['deposito'] = $sumarCod(3, 22);
        $out['deposito_propina'] = 0.0;
        $out['credito'] = round($out['credito'] + $sumarCod(10), 2);
        $out['otros_egresos'] = $sumarCod(14, 30);
        $out['dolares'] = TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::totalJornada(
            $jornada,
            TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::CODIGO_DOLARES,
            $avisos
        );
        $out['euros'] = TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::totalJornada(
            $jornada,
            TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::CODIGO_EUROS,
            $avisos
        );
        $out['sala'] = self::salaConvencional($jornada, $avisos);
        $out['clientes'] = self::clientesDeJornada($jornada, $avisos);
        // l-anexo1.c imprime PP Voucher en 0: el voucher de mesa ya se resta del poder público slot.
        $out['voucher'] = 0.0;
        // Cache propio del VIII (`caja_final_viii`), distinto de caja_final_dia (variación).
        $out['anexo8'] = TesoreriaCrownAnexo8Calculo::totalCacheadoOCalcular($jornada);
        $central = self::porSalaFecha('online', $jornada, fn () => TesoreriaCrownResultadoOnline::deSalaFecha(
            (int) $jornada->sala_id,
            (string) $jornada->fecha?->format('Y-m-d')
        ));
        if ($central) {
            // Del Río (67) y Betsur (68) son marcas online de AC: no pisan la línea
            // On-line del anexo de la sala (sigue saliendo de cajeros / movimientos).
            if (abs((float) $central->slot_resultado) > 0.001) {
                $out['slot'] = (float) $central->slot_resultado;
            }
        }

        foreach ($out as $clave => $importe) {
            $out[$clave] = round((float) $importe, 2);
        }
        if (abs($out['slot']) < 0.001) {
            $avisos[] = 'La recaudación de tragamonedas queda en cero: cargá el resultado de slots online del día.';
        }

        $bingo = self::porSalaFecha('bingo', $jornada, fn () => TesoreriaCrownBingo::deSalaFecha(
            (int) $jornada->sala_id,
            (string) $jornada->fecha?->format('Y-m-d')
        ));
        if ($bingo) {
            $out['bingo'] = (float) $bingo->resultado;
        }

        return $out;
    }

    /**
     * Precarga en memoria (solo mientras dura deJornada) de lo que importesDia consulta por día:
     * una consulta por tipo para todo el año en vez de una por jornada.
     *
     * @param  iterable<TesoreriaCrownJornada>  $jornadas
     */
    private static function precargarDias(iterable $jornadas): void
    {
        $ids = [];
        $salaFechas = [];
        foreach ($jornadas as $jornada) {
            $ids[] = (int) $jornada->id;
            $salaFechas[(int) $jornada->sala_id][] = $jornada->fecha->format('Y-m-d');
        }
        self::$precarga = [];
        if ($ids === []) {
            return;
        }

        foreach ([
            'mesa' => self::consultaMesasPorTipo($ids),
            'drop' => self::consultaDrops($ids),
            'propina' => self::consultaPropinas($ids),
            'boveda' => self::consultaBovedaPorCodigo($ids),
        ] as $clave => $filas) {
            self::$precarga[$clave] = array_fill_keys($ids, []);
            foreach ($filas as $fila) {
                self::$precarga[$clave][(int) $fila->jornada_id][] = $fila;
            }
        }

        foreach (['online' => TesoreriaCrownResultadoOnline::class, 'bingo' => TesoreriaCrownBingo::class] as $clave => $modelo) {
            self::$precarga[$clave] = [];
            foreach ($salaFechas as $salaId => $fechas) {
                foreach ($fechas as $fecha) {
                    self::$precarga[$clave][$salaId.'|'.$fecha] = null;
                }
                $filas = $modelo::query()
                    ->where('sala_id', $salaId)
                    ->whereDate('fecha', '>=', min($fechas))
                    ->whereDate('fecha', '<=', max($fechas))
                    ->orderBy('id')
                    ->get();
                foreach ($filas as $fila) {
                    $llave = $salaId.'|'.$fila->fecha->format('Y-m-d');
                    if (array_key_exists($llave, self::$precarga[$clave]) && self::$precarga[$clave][$llave] === null) {
                        self::$precarga[$clave][$llave] = $fila;
                    }
                }
            }
        }

        TesoreriaCrownBovedaMonedaExtranjeraPesosSupport::precargar($ids);
    }

    /**
     * @param  callable(list<int>): iterable<object>  $consulta
     * @return list<object>
     */
    private static function precargado(string $clave, TesoreriaCrownJornada $jornada, callable $consulta): array
    {
        $id = (int) $jornada->id;
        if (isset(self::$precarga[$clave]) && array_key_exists($id, self::$precarga[$clave])) {
            return self::$precarga[$clave][$id];
        }
        $filas = [];
        foreach ($consulta([$id]) as $fila) {
            $filas[] = $fila;
        }

        return $filas;
    }

    private static function porSalaFecha(string $clave, TesoreriaCrownJornada $jornada, callable $consulta): mixed
    {
        $llave = (int) $jornada->sala_id.'|'.$jornada->fecha?->format('Y-m-d');
        if (isset(self::$precarga[$clave]) && array_key_exists($llave, self::$precarga[$clave])) {
            return self::$precarga[$clave][$llave];
        }

        return $consulta();
    }

    /** @return list<object> */
    private static function propinasFilas(TesoreriaCrownJornada $jornada): array
    {
        return self::precargado('propina', $jornada, fn (array $ids) => self::consultaPropinas($ids));
    }

    /**
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultaMesasPorTipo(array $ids)
    {
        return DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'm.deposito_id')
            ->leftJoin('tesoreria_crown_mesa_linea as l', 'l.mesa_id', '=', 'm.id')
            ->leftJoin('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->whereIn('m.jornada_id', $ids)
            ->where('m.estado', 'C')
            ->whereNull('m.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_mesa as r')
                    ->whereColumn('r.revierte_id', 'm.id');
            })
            ->groupBy('m.jornada_id', 'd.codigo', 'm.tipo')
            ->get([
                'm.jornada_id',
                'd.codigo as mesa',
                'm.tipo',
                DB::raw('COALESCE(SUM(l.cantidad * v.denominacion), 0) as monto'),
            ]);
    }

    /**
     * Drops confirmados y vigentes (tipo D) con el total de sus líneas.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultaDrops(array $ids)
    {
        $lineas = DB::table('tesoreria_crown_mesa_linea as l')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->whereIn('l.mesa_id', function ($q) use ($ids) {
                $q->select('id')
                    ->from('tesoreria_crown_mesa')
                    ->whereIn('jornada_id', $ids)
                    ->where('tipo', 'D');
            })
            ->groupBy('l.mesa_id')
            ->selectRaw('l.mesa_id, SUM(l.cantidad * v.denominacion) as monto');

        return DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'm.deposito_id')
            ->leftJoinSub($lineas, 'ml', 'ml.mesa_id', '=', 'm.id')
            ->whereIn('m.jornada_id', $ids)
            ->where('m.tipo', 'D')
            ->where('m.estado', 'C')
            ->whereNull('m.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_mesa as r')
                    ->whereColumn('r.revierte_id', 'm.id');
            })
            ->orderBy('m.id')
            ->get([
                'm.jornada_id',
                'm.id',
                'd.codigo as mesa',
                'm.voucher',
                'm.comentario',
                DB::raw('COALESCE(ml.monto, 0) as monto_lineas'),
            ]);
    }

    /**
     * Propina de estados finales (tipo F) confirmados, por mesa.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultaPropinas(array $ids)
    {
        return DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'm.deposito_id')
            ->whereIn('m.jornada_id', $ids)
            ->where('m.tipo', 'F')
            ->where('m.estado', 'C')
            ->whereNull('m.revierte_id')
            ->groupBy('m.jornada_id', 'd.codigo')
            ->get(['m.jornada_id', 'd.codigo as mesa', DB::raw('SUM(m.propina) as propina')]);
    }

    /**
     * Bóveda confirmada y vigente por código de transacción y moneda.
     *
     * @param  list<int>  $ids
     * @return \Illuminate\Support\Collection<int, object>
     */
    private static function consultaBovedaPorCodigo(array $ids)
    {
        return DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->leftJoin('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->whereIn('b.jornada_id', $ids)
            ->where('b.estado', 'C')
            ->whereNull('b.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_boveda as r')
                    ->whereColumn('r.revierte_id', 'b.id');
            })
            ->groupBy('b.jornada_id', 't.codigo', 'tv.codigo')
            ->get(['b.jornada_id', 't.codigo as codigo', 'tv.codigo as moneda', DB::raw('SUM(l.cantidad * v.denominacion) as monto')]);
    }

    /**
     * @return array<string, float>
     */
    private static function ceros(): array
    {
        return array_fill_keys([
            'clientes', 'sala', 'ruleta', 'slot', 'gastro', 'hotel', 'slot_poder', 'propina',
            'torneo', 'bingo', 'online', 'voucher', 'apuestas', 'bancarios', 'excedente', 'recargo',
            'compras', 'fallos', 'deposito', 'deposito_propina', 'credito', 'debito', 'qr',
            'dolares', 'euros', 'ctacte', 'transferencia', 'otros_egresos', 'anexo8',
        ], 0.0);
    }

    /**
     * l-resmesa: resultado = estado final + drop − habilitación − reposición + crédito − propina.
     * Drop ERP = líneas tipo D + voucher del movimiento (mismo criterio que Anexo II).
     * Mesas 27 y 28 no entran.
     *
     * @param  list<string>  $avisos
     */
    private static function salaConvencional(TesoreriaCrownJornada $jornada, array &$avisos): float
    {
        $filas = self::precargado('mesa', $jornada, fn (array $ids) => self::consultaMesasPorTipo($ids));

        $porMesa = [];
        foreach ($filas as $fila) {
            $nro = (int) preg_replace('/\D/', '', (string) $fila->mesa);
            if ($nro === 27 || $nro === 28 || $nro === 0) {
                continue;
            }
            $porMesa[$nro][(string) $fila->tipo] = round(
                (float) ($porMesa[$nro][(string) $fila->tipo] ?? 0) + (float) $fila->monto,
                2
            );
        }

        // Drop ERP = líneas tipo D + voucher (mismo criterio que Anexo II).
        $drops = self::dropsErp($jornada);
        $hayDropErp = false;
        foreach ($drops as $monto) {
            if (abs((float) $monto) > 0.001) {
                $hayDropErp = true;
                break;
            }
        }
        if (! $hayDropErp) {
            $avisos[] = 'Drop de mesas en cero: confirmá drops tipo D en la jornada ERP (o importá mesas).';
        }

        $propinas = [];
        foreach (self::propinasFilas($jornada) as $fila) {
            $propinas[(int) preg_replace('/\D/', '', (string) $fila->mesa)] = (float) $fila->propina;
        }

        $total = 0.0;
        $mesas = array_unique(array_merge(array_keys($porMesa), array_keys($drops), array_keys($propinas)));
        foreach ($mesas as $nro) {
            if ($nro === 27 || $nro === 28) {
                continue;
            }
            $mov = $porMesa[$nro] ?? [];
            $drop = (float) ($drops[$nro] ?? ($mov['D'] ?? 0));
            $total += (float) ($mov['F'] ?? 0)
                + $drop
                - (float) ($mov['A'] ?? 0)
                - (float) ($mov['R'] ?? 0)
                + (float) ($mov['C'] ?? 0)
                - (float) ($propinas[$nro] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Drop por mesa ERP: líneas del movimiento D + voucher (Anexo II).
     *
     * @return array<int, float>
     */
    private static function dropsErp(TesoreriaCrownJornada $jornada): array
    {
        $movimientos = self::precargado('drop', $jornada, fn (array $ids) => self::consultaDrops($ids));

        $out = [];
        foreach ($movimientos as $mov) {
            $nro = (int) preg_replace('/\D/', '', (string) $mov->mesa);
            if ($nro === 27 || $nro === 28 || $nro === 0) {
                continue;
            }
            $lineas = (float) ($mov->monto_lineas ?? 0);
            $voucher = round((float) ($mov->voucher ?? 0), 2);
            if ($voucher < 0.0001) {
                $comentario = (string) ($mov->comentario ?? '');
                if (preg_match('/voucher\s+([\d.]+(?:,\d+)?)/iu', $comentario, $m)) {
                    $texto = str_replace('.', '', $m[1]);
                    $texto = str_replace(',', '.', $texto);
                    $voucher = round((float) $texto, 2);
                }
            }
            $out[$nro] = round(($out[$nro] ?? 0) + $lineas + $voucher, 2);
        }

        return $out;
    }

    /**
     * Pesos de bóveda por código de transacción. l-anexo1 suma el importe,
     * sin aplicar el signo: ingresos y egresos se imprimen en positivo.
     *
     * @return array<int, float>
     */
    private static function bovedaPesos(TesoreriaCrownJornada $jornada): array
    {
        $filas = self::precargado('boveda', $jornada, fn (array $ids) => self::consultaBovedaPorCodigo($ids));

        $out = [];
        foreach ($filas as $fila) {
            $moneda = strtoupper(trim((string) $fila->moneda));
            if (in_array($moneda, ['USD', 'DOL', 'EUR', 'EURO'], true)) {
                continue;
            }
            $codigo = (int) $fila->codigo;
            $out[$codigo] = round(($out[$codigo] ?? 0) + (float) $fila->monto, 2);
        }

        return $out;
    }

    /**
     * Valor para el input de clientes: el grabado en la jornada ERP (0 si falta).
     */
    public static function clientesParaFormulario(TesoreriaCrownJornada $jornada): int
    {
        if (! self::tieneColumna('clientes')) {
            return 0;
        }

        return (int) ($jornada->clientes ?? 0);
    }

    /**
     * Clientes informativos: solo campo de la jornada ERP.
     *
     * @param  list<string>  $avisos
     */
    private static function clientesDeJornada(TesoreriaCrownJornada $jornada, array &$avisos): float
    {
        if (self::tieneColumna('clientes')) {
            $local = (int) ($jornada->clientes ?? 0);
            if ($local > 0) {
                return (float) $local;
            }
        }
        $avisos[] = 'Clientes en cero: cargá la cantidad en la jornada ERP.';

        return 0.0;
    }

    /**
     * Persiste la caja final por variación del Anexo I (inicial + variación).
     * No escribe el VIII: eso va en `caja_final_viii` vía TesoreriaCrownAnexo8Calculo.
     */
    private static function persistirCajaFinal(TesoreriaCrownJornada $jornada, ?float $finalDia, ?float $finalMes): void
    {
        if (! self::tieneColumna('caja_final_dia')) {
            return;
        }
        if ($finalDia === null && $finalMes === null) {
            return;
        }
        $jornada->forceFill([
            'caja_final_dia' => $finalDia,
            'caja_final_mes' => $finalMes,
        ])->save();
    }

    /**
     * @param  list<string>  $avisos
     * @return array{
     *     ficha_dia: float,
     *     ficha_mes: float,
     *     ficha_anio: float,
     *     slot_dia: float,
     *     slot_mes: float,
     *     slot_anio: float
     * }|null
     */
    private static function poderPublico(string $fechaYmd, array &$avisos): ?array
    {
        $jornada = null;
        // $fechaYmd llega como Ymd (sin guiones) desde deJornada.
        $fechaIso = strlen($fechaYmd) === 8
            ? substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2)
            : $fechaYmd;
        try {
            $jornada = TesoreriaCrownJornada::query()
                ->whereDate('fecha', $fechaIso)
                ->orderBy('id')
                ->first();
            $salaId = $jornada ? (int) $jornada->sala_id : 0;
            if ($salaId <= 0) {
                $avisos[] = 'No hay jornada ERP para generar el poder público del día.';

                return null;
            }
            $armado = TesoreriaCrownPoderPublicoGenerador::obtenerOGenerar($salaId, $fechaIso);
            foreach ($armado['avisos'] as $aviso) {
                $avisos[] = $aviso;
            }

            return [
                'ficha_dia' => (float) $armado['ficha_dia'],
                'ficha_mes' => (float) $armado['ficha_mes'],
                'ficha_anio' => (float) $armado['ficha_anio'],
                'slot_dia' => (float) $armado['slot_dia'],
                'slot_mes' => (float) $armado['slot_mes'],
                'slot_anio' => (float) $armado['slot_anio'],
            ];
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo generar el poder público: '.$e->getMessage();

            return null;
        }
    }

    /**
     * Caja inicial = caja final (Anexo VIII) de la jornada anterior en la misma sala.
     * Si no hay jornada ERP ayer, queda null con aviso (sin lectura Anita).
     *
     * @param  list<string>  $avisos
     * @return array{dia: ?float, mes: ?float, anio: ?float}
     */
    private static function cajaInicial(string $ayerYmd, string $fechaYmd, array &$avisos): array
    {
        $ayerIso = strlen($ayerYmd) === 8
            ? substr($ayerYmd, 0, 4).'-'.substr($ayerYmd, 4, 2).'-'.substr($ayerYmd, 6, 2)
            : $ayerYmd;
        $hoyIso = strlen($fechaYmd) === 8
            ? substr($fechaYmd, 0, 4).'-'.substr($fechaYmd, 4, 2).'-'.substr($fechaYmd, 6, 2)
            : $fechaYmd;

        $jornadaHoy = TesoreriaCrownJornada::query()
            ->whereDate('fecha', $hoyIso)
            ->orderBy('id')
            ->first();
        $jornadaAyer = null;
        if ($jornadaHoy) {
            $jornadaAyer = TesoreriaCrownJornada::query()
                ->where('sala_id', $jornadaHoy->sala_id)
                ->whereDate('fecha', $ayerIso)
                ->orderByDesc('id')
                ->first();
        }
        if ($jornadaAyer instanceof TesoreriaCrownJornada) {
            try {
                // Caja inicial del día = VIII de ayer (cache caja_final_viii).
                $dia = TesoreriaCrownAnexo8Calculo::totalCacheadoOCalcular($jornadaAyer);
                $mes = self::cajaInicialMes($jornadaHoy, $dia, $avisos);

                return ['dia' => $dia, 'mes' => $mes, 'anio' => null];
            } catch (\Throwable $e) {
                $avisos[] = 'No se pudo armar la caja inicial desde el anexo VIII de ayer: '.$e->getMessage();
            }
        }

        $avisos[] = 'Sin jornada ERP del día anterior: abrí/importá esa jornada para la caja inicial.';

        return ['dia' => null, 'mes' => null, 'anio' => null];
    }

    /**
     * Caja inicial del mes = caja inicial del primer día del mes (VIII del día previo).
     * La columna mes ya acumula la variación desde el 1: no se encadena con la caja final de ayer.
     *
     * @param  list<string>  $avisos
     */
    private static function cajaInicialMes(TesoreriaCrownJornada $jornadaHoy, float $inicialDia, array &$avisos): ?float
    {
        $desdeMes = $jornadaHoy->fecha->copy()->startOfMonth();
        $primera = TesoreriaCrownJornada::query()
            ->where('sala_id', $jornadaHoy->sala_id)
            ->whereDate('fecha', '>=', $desdeMes->toDateString())
            ->orderBy('fecha')
            ->orderBy('id')
            ->first();
        if (! $primera instanceof TesoreriaCrownJornada || (int) $primera->id === (int) $jornadaHoy->id) {
            return $inicialDia;
        }
        $previaMes = TesoreriaCrownJornada::query()
            ->where('sala_id', $jornadaHoy->sala_id)
            ->whereDate('fecha', $primera->fecha->copy()->subDay()->toDateString())
            ->orderByDesc('id')
            ->first();
        if (! $previaMes instanceof TesoreriaCrownJornada) {
            $avisos[] = 'Caja inicial del mes vacía: no está la jornada ERP del '
                .$primera->fecha->copy()->subDay()->format('d/m/Y').' (anexo VIII previo al '.$primera->fecha->format('d/m/Y').').';

            return null;
        }

        return TesoreriaCrownAnexo8Calculo::totalCacheadoOCalcular($previaMes);
    }

    /**
     * @param  array{dia: float, mes: float, anio: ?float}  ...$partes
     * @return array{dia: float, mes: float, anio: float}
     */
    private static function suma(array ...$partes): array
    {
        $dia = $mes = $anio = 0.0;
        foreach ($partes as $parte) {
            $dia += (float) ($parte['dia'] ?? 0);
            $mes += (float) ($parte['mes'] ?? 0);
            $anio += (float) ($parte['anio'] ?? 0);
        }

        return ['dia' => round($dia, 2), 'mes' => round($mes, 2), 'anio' => round($anio, 2)];
    }

    /**
     * @param  array{dia: float, mes: float, anio: ?float}  $a
     * @param  array{dia: float, mes: float, anio: ?float}  $b
     * @return array{dia: float, mes: float, anio: float}
     */
    private static function resta(array $a, array $b): array
    {
        return [
            'dia' => round((float) $a['dia'] - (float) $b['dia'], 2),
            'mes' => round((float) $a['mes'] - (float) $b['mes'], 2),
            'anio' => round((float) ($a['anio'] ?? 0) - (float) ($b['anio'] ?? 0), 2),
        ];
    }

    /**
     * @param  array{dia: ?float, mes: ?float, anio: ?float}  $valores
     * @return array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}
     */
    private static function lin(string $concepto, array $valores): array
    {
        return [
            'concepto' => $concepto,
            'dia' => $valores['dia'],
            'mes' => $valores['mes'],
            'anio' => $valores['anio'],
            'total' => false,
            'seccion' => false,
        ];
    }

    /**
     * @param  array{dia: float, mes: float, anio: ?float}  $valores
     * @return array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}
     */
    private static function tot(string $concepto, array $valores): array
    {
        $fila = self::lin($concepto, $valores);
        $fila['total'] = true;

        return $fila;
    }

    /**
     * @return array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}
     */
    private static function sec(string $concepto): array
    {
        return [
            'concepto' => $concepto,
            'dia' => null,
            'mes' => null,
            'anio' => null,
            'total' => false,
            'seccion' => true,
        ];
    }

}
