<?php

namespace App\Support\Caja;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownResultadoOnline;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Anexo I como l-anexo1.c: columnas día, mes y anual.
 *
 * El día sale de la jornada (cajeros, mesas, bóveda). No se reconstruye
 * con el join de efeboveda y rendmbill: ese cruce recorre todo el detalle
 * de billetes y es lo que demoraba la pantalla.
 *
 * El drop de mesa y la propina tipeada, cuando la jornada todavía no los
 * tiene, se leen de efemesa y fichammae (una suma por fecha). La caja
 * inicial y el poder público de fichas salen de la fila que Anita ya
 * grabó (acumanexo1 101 y ppublico): no están en el stock importado.
 * Tragamonedas sale del resultado online cargado en la sala (slot_r de
 * administración central). Si ese día no está cargado, la línea queda en
 * cero: resulol no está en la base SQL. El poder público de slot sigue
 * el subtotal del anexo II, que tampoco está en la jornada.
 */
final class TesoreriaCrownAnexo1Calculo
{
    /** @var array<int, array<int, float>> */
    private static array $propinaPorMesa = [];
    /**
     * @return array{
     *     filas: list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool, informativo?: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>
     * }
     */
    public static function deJornada(TesoreriaCrownJornada $jornada): array
    {
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
        $dias = [];
        foreach ($jornadas as $una) {
            $dias[] = [
                'en_mes' => $una->fecha->greaterThanOrEqualTo($desdeMes),
                'importes' => self::importesDia($una, $avisos),
            ];
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

        $poderFila = $poder ?? ['dia' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        $slotPoder = $valor('slot_poder');
        $propina = $valor('propina');
        $torneo = $valor('torneo');
        $bingo = $valor('bingo');
        $online = $valor('online');
        $voucher = $valor('voucher');
        $apuestas = $valor('apuestas');
        $bancarios = $valor('bancarios');
        $excedente = $valor('excedente');
        $recargo = $valor('recargo');
        $otros = self::suma($poderFila, $slotPoder, $propina, $torneo, $bingo, $online, $voucher, $apuestas, $bancarios, $excedente, $recargo);
        $ingresos = self::suma($recaudacion, $otros);

        $compras = $valor('compras');
        $fallos = $valor('fallos');
        $deposito = $valor('deposito');
        $depositoPropina = $valor('deposito_propina');
        $credito = $valor('credito');
        $debito = $valor('debito');
        $qr = $valor('qr');
        $dolares = $valor('dolares');
        $euros = $valor('euros');
        $ctacte = $valor('ctacte');
        $transferencia = $valor('transferencia');
        $otrosEgresos = $valor('otros_egresos');
        $egresos = self::suma($compras, $fallos, $deposito, $depositoPropina, $credito, $debito, $qr, $dolares, $euros, $ctacte, $transferencia, $otrosEgresos);
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
        $filas[] = self::lin('Recaudación propina empleados (anexo II)', $propina);
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
        $filas[] = self::lin('Depósito propina', $depositoPropina);
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

        $avisos[] = 'La caja final del anexo VIII es el saldo de bóveda cargado en la jornada. Anita imprime el stock físico completo; si falta la apertura anterior, no cierra con ese listado.';

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
        $fechaInt = (int) $jornada->fecha->format('Ymd');
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

        $propinaErp = round((float) DB::table('tesoreria_crown_mesa')
            ->where('jornada_id', $jornada->id)
            ->where('tipo', 'F')
            ->where('estado', 'C')
            ->whereNull('revierte_id')
            ->sum('propina'), 2);
        $propinaAnita = 0.0;
        if (abs($propinaErp) < 0.001) {
            $propinaAnita = self::propinaAnita($fechaInt, $avisos);
        }
        $out['propina'] = round((float) ($porTipo['E']['resultado'] ?? 0) + $propinaAnita, 2);

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
            $out['ctacte'] += (float) ($fila['ctacte'] ?? 0);
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
        $out['compras'] = $sumarCod(2);
        $out['deposito'] = $sumarCod(3, 22);
        $out['deposito_propina'] = $sumarCod(7, 24);
        $out['credito'] = round($out['credito'] + $sumarCod(10), 2);
        $out['otros_egresos'] = $sumarCod(14, 30);
        $out['dolares'] = self::monedaExtranjera($jornada, 8, ['USD', 'DOL']);
        $out['euros'] = self::monedaExtranjera($jornada, 9, ['EUR', 'EURO']);
        $out['sala'] = self::salaConvencional($jornada, $fechaInt, $propinaAnita > 0, $avisos);
        $out['clientes'] = self::clientes($fechaInt, $avisos);
        $out['anexo8'] = (float) TesoreriaCrownAnexo8Calculo::deJornada($jornada)['total'];
        $central = TesoreriaCrownResultadoOnline::deSalaFecha(
            (int) $jornada->sala_id,
            (string) $jornada->fecha?->format('Y-m-d')
        );
        if ($central) {
            $tieneCasino = abs((float) $central->apostado) > 0.001
                || abs((float) $central->pagado) > 0.001
                || abs((float) $central->resultado) > 0.001;
            if ($tieneCasino) {
                $out['online'] = (float) $central->resultado;
            }
            if (abs((float) $central->slot_resultado) > 0.001) {
                $out['slot'] = (float) $central->slot_resultado;
            }
        }

        foreach ($out as $clave => $importe) {
            $out[$clave] = round((float) $importe, 2);
        }
        if ($central && abs((float) $out['online'] - (float) $central->resultado) < 0.001 && (abs((float) $central->apostado) > 0.001 || abs((float) $central->pagado) > 0.001 || abs((float) $central->resultado) > 0.001)) {
            $avisos[] = 'On-line salió del resultado de casino online cargado para el día.';
        }
        if (abs($out['slot']) < 0.001 && abs($out['slot_poder']) < 0.001) {
            $avisos[] = 'La recaudación de tragamonedas queda en cero: cargá el resultado de slots online del día. El poder público de slot también queda en cero.';
        } elseif (abs($out['slot_poder']) < 0.001) {
            $avisos[] = 'El poder público de slot queda en cero. Anita lo arma con el subtotal de slots del anexo II, que no está en la jornada.';
        }

        return $out;
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
     * El drop es efem_total_peso. Las mesas 27 y 28 no entran.
     *
     * @param  list<string>  $avisos
     */
    private static function salaConvencional(TesoreriaCrownJornada $jornada, int $fechaInt, bool $propinaDeAnita, array &$avisos): float
    {
        $filas = DB::table('tesoreria_crown_mesa as m')
            ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'm.deposito_id')
            ->join('tesoreria_crown_mesa_linea as l', 'l.mesa_id', '=', 'm.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->where('m.jornada_id', $jornada->id)
            ->where('m.estado', 'C')
            ->whereNull('m.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_mesa as r')
                    ->whereColumn('r.revierte_id', 'm.id');
            })
            ->groupBy('d.codigo', 'm.tipo')
            ->get(['d.codigo as mesa', 'm.tipo', DB::raw('SUM(l.cantidad * v.denominacion) as monto')]);

        $porMesa = [];
        foreach ($filas as $fila) {
            $nro = (int) preg_replace('/\D/', '', (string) $fila->mesa);
            if ($nro === 27 || $nro === 28 || $nro === 0) {
                continue;
            }
            $porMesa[$nro][(string) $fila->tipo] = (float) $fila->monto;
        }
        $drops = self::dropsAnita($fechaInt, $avisos);
        $propinas = $propinaDeAnita ? self::propinasPorMesa($fechaInt, $avisos) : [];
        if (! $propinaDeAnita) {
            $locales = DB::table('tesoreria_crown_mesa as m')
                ->join('tesoreria_crown_deposito as d', 'd.id', '=', 'm.deposito_id')
                ->where('m.jornada_id', $jornada->id)
                ->where('m.tipo', 'F')
                ->where('m.estado', 'C')
                ->whereNull('m.revierte_id')
                ->groupBy('d.codigo')
                ->get(['d.codigo as mesa', DB::raw('SUM(m.propina) as propina')]);
            foreach ($locales as $fila) {
                $propinas[(int) preg_replace('/\D/', '', (string) $fila->mesa)] = (float) $fila->propina;
            }
        }

        $total = 0.0;
        $mesas = array_unique(array_merge(array_keys($porMesa), array_keys($drops), array_keys($propinas)));
        foreach ($mesas as $nro) {
            if ($nro === 27 || $nro === 28) {
                continue;
            }
            $mov = $porMesa[$nro] ?? [];
            $total += (float) ($mov['F'] ?? 0)
                + (float) ($drops[$nro] ?? 0)
                - (float) ($mov['A'] ?? 0)
                - (float) ($mov['R'] ?? 0)
                + (float) ($mov['C'] ?? 0)
                - (float) ($propinas[$nro] ?? 0);
        }

        return round($total, 2);
    }

    /**
     * Pesos de bóveda por código de transacción. l-anexo1 suma el importe,
     * sin aplicar el signo: ingresos y egresos se imprimen en positivo.
     *
     * @return array<int, float>
     */
    private static function bovedaPesos(TesoreriaCrownJornada $jornada): array
    {
        $filas = DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->leftJoin('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->where('b.jornada_id', $jornada->id)
            ->where('b.estado', 'C')
            ->whereNull('b.revierte_id')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('tesoreria_crown_boveda as r')
                    ->whereColumn('r.revierte_id', 'b.id');
            })
            ->groupBy('t.codigo', 'tv.codigo')
            ->get(['t.codigo as codigo', 'tv.codigo as moneda', DB::raw('SUM(l.cantidad * v.denominacion) as monto')]);

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
     * @param  list<string>  $monedas
     */
    private static function monedaExtranjera(TesoreriaCrownJornada $jornada, int $codigo, array $monedas): float
    {
        $filas = DB::table('tesoreria_crown_boveda as b')
            ->join('tesoreria_crown_boveda_tipo as t', 't.id', '=', 'b.tipo_id')
            ->join('tesoreria_crown_boveda_linea as l', 'l.boveda_id', '=', 'b.id')
            ->join('tesoreria_crown_valor as v', 'v.id', '=', 'l.valor_id')
            ->join('tesoreria_crown_tipo_valor as tv', 'tv.id', '=', 'v.tipo_valor_id')
            ->where('b.jornada_id', $jornada->id)
            ->where('b.estado', 'C')
            ->whereNull('b.revierte_id')
            ->where('t.codigo', $codigo)
            ->whereIn('tv.codigo', $monedas)
            ->groupBy('v.tipo_valor_id')
            ->get(['v.tipo_valor_id', DB::raw('SUM(l.cantidad * v.denominacion) as monto')]);

        $total = 0.0;
        $fecha = $jornada->fecha->format('Y-m-d');
        foreach ($filas as $fila) {
            $cot = TesoreriaCrownCierreCalculo::cotizacionVenta((int) $jornada->sala_id, $fecha, (int) $fila->tipo_valor_id);
            $tasa = (float) ($cot['tasa'] ?? 0);
            if ($tasa <= 0) {
                continue;
            }
            $total += (float) $fila->monto * $tasa;
        }

        return round($total, 2);
    }

    /**
     * @param  list<string>  $avisos
     * @return array<int, float>
     */
    private static function dropsAnita(int $fecha, array &$avisos): array
    {
        try {
            $filas = self::consultar(
                'mesa,tipo,monto',
                'SELECT efem_mesajuego, efem_tipo_oper, SUM(efem_total_peso) FROM efemesa WHERE efem_fecha = '.$fecha.' GROUP BY efem_mesajuego, efem_tipo_oper'
            );
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo leer el drop de las mesas: '.$e->getMessage();

            return [];
        }
        $out = [];
        foreach ($filas as $fila) {
            $nro = (int) $fila->mesa;
            $tipo = strtoupper(trim((string) $fila->tipo));
            $monto = (float) $fila->monto;
            if ($tipo === 'I') {
                $monto = -$monto;
            } elseif ($tipo !== 'F') {
                continue;
            }
            $out[$nro] = round(($out[$nro] ?? 0) + $monto, 2);
        }

        return $out;
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function propinaAnita(int $fecha, array &$avisos): float
    {
        $porMesa = self::propinasPorMesa($fecha, $avisos);
        if (abs(array_sum($porMesa)) > 0.001) {
            $avisos[] = 'La propina tipeada en el estado final no está en la jornada. Se tomó de Anita.';
        }

        return round(array_sum($porMesa), 2);
    }

    /**
     * @param  list<string>  $avisos
     * @return array<int, float>
     */
    private static function propinasPorMesa(int $fecha, array &$avisos): array
    {
        if (isset(self::$propinaPorMesa[$fecha])) {
            return self::$propinaPorMesa[$fecha];
        }
        try {
            $filas = self::consultar(
                'mesa,propina',
                'SELECT fichmm_mesajuego, SUM(fichmm_propina) FROM fichammae WHERE fichmm_fecha = '.$fecha.' AND fichmm_tipo_oper = "F" GROUP BY fichmm_mesajuego'
            );
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo leer la propina de las mesas: '.$e->getMessage();
            self::$propinaPorMesa[$fecha] = [];

            return [];
        }
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->mesa] = (float) $fila->propina;
        }
        self::$propinaPorMesa[$fecha] = $out;

        return $out;
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function clientes(int $fecha, array &$avisos): float
    {
        try {
            $filas = self::consultar(
                'cantidad',
                'SELECT SUM(ingr_cantidad) FROM ingreso WHERE ingr_fecha = '.$fecha
            );
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo leer la cantidad de clientes: '.$e->getMessage();

            return 0.0;
        }

        return round((float) ($filas[0]->cantidad ?? 0), 2);
    }

    /**
     * La fila del día ya trae día, mes y acumulado como los imprime el listado.
     *
     * @param  list<string>  $avisos
     * @return array{dia: float, mes: float, anio: float}|null
     */
    private static function poderPublico(string $fechaYmd, array &$avisos): ?array
    {
        try {
            $filas = self::consultar(
                'dia,mes,acum',
                'SELECT ppub_ppublico, ppub_ppublico_mes, ppub_ppublico_acum FROM ppublico WHERE ppub_fecha = '.(int) $fechaYmd
            );
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo leer el poder público: '.$e->getMessage();

            return null;
        }
        if ($filas === []) {
            $avisos[] = 'Anita no tiene poder público grabado para este día.';

            return null;
        }

        return [
            'dia' => (float) $filas[0]->dia,
            'mes' => (float) $filas[0]->mes,
            'anio' => (float) $filas[0]->acum,
        ];
    }

    /**
     * @param  list<string>  $avisos
     * @return array{dia: ?float, mes: ?float, anio: ?float}
     */
    private static function cajaInicial(string $ayerYmd, string $fechaYmd, array &$avisos): array
    {
        try {
            $filas = self::consultar(
                'dia,mes,anio',
                'SELECT acan1_diario, acan1_mensual, acan1_anual FROM acumanexo1 WHERE acan1_fecha = '.(int) $ayerYmd.' AND acan1_item = 101'
            );
        } catch (RuntimeException $e) {
            $avisos[] = 'No se pudo leer la caja inicial: '.$e->getMessage();

            return ['dia' => null, 'mes' => null, 'anio' => null];
        }
        if ($filas === []) {
            $avisos[] = 'Anita no tiene caja final del día anterior.';

            return ['dia' => null, 'mes' => null, 'anio' => null];
        }
        $dia = (float) $filas[0]->dia;
        $mes = (float) $filas[0]->mes;
        if (substr($fechaYmd, 8, 2) === '01') {
            $mes = $dia;
        }

        return ['dia' => $dia, 'mes' => $mes, 'anio' => null];
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

    /**
     * @return list<object>
     */
    private static function consultar(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }
}
