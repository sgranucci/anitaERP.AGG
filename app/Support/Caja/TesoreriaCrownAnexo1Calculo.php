<?php

namespace App\Support\Caja;

use App\ApiAnita;
use RuntimeException;

/**
 * Anexo I como l-anexo1.c: columnas día, mes y anual.
 *
 * Si Anita grabó el detalle en acumanexo1, esas filas son el listado
 * (el C las escribe al cerrar el día). El poder público sale de ppublico,
 * que es la misma cifra que l-stockfichm.c. No se escribe nada en Anita.
 *
 * Si el día no tiene detalle, se calculan las líneas que salen de efectivo,
 * de la bóveda y del poder público. Las recaudaciones de juego, gastronomía
 * y hotel las arma l-resmesa y Anita no las dejó grabadas: no se inventan.
 */
final class TesoreriaCrownAnexo1Calculo
{
    /**
     * @return array{
     *     filas: list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}>,
     *     cerrado: bool,
     *     avisos: list<string>
     * }
     */
    public static function delDia(string $fechaYmd): array
    {
        $fecha = (int) str_replace('-', '', $fechaYmd);
        $items = self::items($fecha);
        $poder = self::ppublico($fecha);
        $ayer = (int) date('Ymd', strtotime($fechaYmd.' -1 day'));
        $cajaAyer = self::item($ayer, 101);
        $avisos = [];

        if (isset($items[10])) {
            return [
                'filas' => self::desdeAcumulado($fechaYmd, $items, $poder, $cajaAyer, $avisos),
                'cerrado' => true,
                'avisos' => $avisos,
            ];
        }

        $ultimo = self::ultimoDetalle();
        if ($ultimo !== null) {
            $avisos[] = 'Anita no grabó el detalle del anexo para este día. El último detalle es del '
                .substr((string) $ultimo, 6, 2).'/'.substr((string) $ultimo, 4, 2).'/'.substr((string) $ultimo, 0, 4)
                .'. Las recaudaciones de juego, gastronomía, hotel, propina, torneos, bingo y los fallos salen de otros programas y no se completan con cero.';
        }
        $avisos[] = 'La cuenta corriente y la transferencia de hotel no están en la base SQL de Anita.';

        return [
            'filas' => self::desdeMovimientos($fecha, $fechaYmd, $poder, $cajaAyer, $items[101] ?? null),
            'cerrado' => false,
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  array<int, array{dia: float, mes: float, anio: float}>  $items
     * @param  array{dia: float, mes: float, anio: float}|null  $poder
     * @param  array{dia: float, mes: float, anio: float}|null  $cajaAyer
     * @param  list<string>  $avisos
     * @return list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}>
     */
    private static function desdeAcumulado(string $fechaYmd, array $items, ?array $poder, ?array $cajaAyer, array &$avisos): array
    {
        $v = fn (int $item): array => $items[$item] ?? ['dia' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        $poderFila = $poder ?? ['dia' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        if ($poder === null) {
            $avisos[] = 'Anita no tiene poder público grabado para este día.';
        }
        if (($v(126)['dia'] ?? 0) != 0.0) {
            $avisos[] = 'Anita tiene poder público de voucher grabado y el listado lo imprime en cero.';
        }

        $mesa = $v(10);
        $ruleta = $v(20);
        $slot = $v(70);
        $gastro = $v(40);
        $hotel = $v(60);
        $juego = self::suma($mesa, $ruleta, $slot);
        $recaudacion = self::suma($juego, $gastro, $hotel);
        $propina = $v(30);
        $torneo = $v(80);
        $bingo = $v(90);
        $online = $v(125);
        $bancarios = $v(200);
        $excedente = $v(100);
        $recargo = $v(330);
        $voucher = ['dia' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        $slotPoder = ['dia' => 0.0, 'mes' => 0.0, 'anio' => 0.0];
        $otros = self::suma($online, $bingo, $torneo, $poderFila, $propina, $bancarios, $slotPoder, $voucher, $excedente, $recargo);
        $ingresos = self::suma($recaudacion, $otros);

        $egresos = self::suma($v(220), $v(110), $v(120), $v(230), $v(240), $v(250), $v(251), $v(252), $v(260), $v(270), $v(290), $v(300), $v(310));
        $variacion = self::resta($ingresos, $egresos);
        $inicial = self::cajaInicial($fechaYmd, $cajaAyer);
        $finalCalculada = self::suma($variacion, $inicial);
        $finalAnita = $v(101);
        if (abs($finalCalculada['dia'] - $finalAnita['dia']) > 1) {
            $avisos[] = 'La caja final por variación da '.number_format($finalCalculada['dia'], 2, ',', '.')
                .' y Anita tiene grabados '.number_format($finalAnita['dia'], 2, ',', '.').'.';
        }
        $anexo8 = $v(130);
        $diferencia = self::resta($anexo8, $finalAnita);

        $filas = [];
        $filas[] = self::sec('INGRESOS');
        $filas[] = self::lin('Clientes', $v(50));
        $filas[] = self::lin('Recaudación sala convencional (anexo II)', $mesa);
        $filas[] = self::lin('Recaudación ruleta electrónica (anexo II)', $ruleta);
        $filas[] = self::lin('Recaudación máquinas tragamonedas (anexo II)', $slot);
        $filas[] = self::tot('Total de recaudaciones por juego', $juego);
        $filas[] = self::lin('Total ventas de gastronomía al contado (anexo III)', $gastro);
        $filas[] = self::lin('Total ventas de hotelería al contado (anexo III)', $hotel);
        $filas[] = self::tot('Total recaudación', $recaudacion);
        $filas[] = self::lin('Fichas poder público (anexo II)', $poderFila);
        $filas[] = self::lin('Recaudación propina empleados (anexo II)', $propina);
        $filas[] = self::lin('Torneos (anexo II)', $torneo);
        $filas[] = self::lin('Bingo (anexo II)', $bingo);
        $filas[] = self::lin('On-line', $online);
        $filas[] = self::lin('Poder público voucher', $voucher);
        $filas[] = self::lin('Movimientos bancarios y financieros', $bancarios);
        $filas[] = self::lin('Excedente de caja (anexo II)', $excedente);
        $filas[] = self::lin('Recargo financiero', $recargo);
        $filas[] = self::tot('Total otros ingresos', $otros);
        $filas[] = self::tot('Total ingresos', $ingresos);
        $filas[] = self::sec('EGRESOS');
        $filas[] = self::lin('Compras y gastos en efectivo y pagos de cuenta corriente (anexo VI)', $v(220));
        $filas[] = self::lin('Anticipos a cuenta de sueldo y fallos de caja (anexo VII)', self::suma($v(110), $v(120)));
        $filas[] = self::lin('Depósito efectivo', $v(230));
        $filas[] = self::lin('Depósito propina', $v(240));
        $filas[] = self::lin('Tarjetas de crédito', $v(250));
        $filas[] = self::lin('Tarjetas de débito', $v(251));
        $filas[] = self::lin('Total QR', $v(252));
        $filas[] = self::lin('Depósito dólares', $v(260));
        $filas[] = self::lin('Depósito euros', $v(270));
        $filas[] = self::lin('Cuenta corriente hotel', $v(290));
        $filas[] = self::lin('Transferencia a cuenta hotel', $v(300));
        $filas[] = self::lin('Otros egresos', $v(310));
        $filas[] = self::tot('Total egresos', $egresos);
        $filas[] = self::tot('Variación de la caja', $variacion);
        $filas[] = self::sec('CAJA');
        $filas[] = self::lin('Caja inicial', $inicial);
        $filas[] = self::lin('Caja final (por variación)', $finalAnita);
        $filas[] = self::lin('Caja final (anexo VIII)', $anexo8);
        $filas[] = self::tot('Diferencia', $diferencia);

        return $filas;
    }

    /**
     * @param  array{dia: float, mes: float, anio: float}|null  $poder
     * @param  array{dia: float, mes: float, anio: float}|null  $cajaAyer
     * @param  array{dia: float, mes: float, anio: float}|null  $cajaHoy
     * @return list<array{concepto: string, dia: ?float, mes: ?float, anio: ?float, total: bool, seccion: bool}>
     */
    private static function desdeMovimientos(int $fecha, string $fechaYmd, ?array $poder, ?array $cajaAyer, ?array $cajaHoy): array
    {
        $efe = self::efectivo($fecha);
        $boveda = self::boveda($fecha);
        $dolares = self::monedaExtranjera($fecha, 8, '2');
        $euros = self::monedaExtranjera($fecha, 9, '3');
        $sumaCod = function (int ...$codigos) use ($boveda): array {
            $total = 0.0;
            foreach ($codigos as $codigo) {
                $total += $boveda[$codigo] ?? 0.0;
            }

            return ['dia' => $total, 'mes' => null, 'anio' => null];
        };
        $dia = fn (float $importe): array => ['dia' => $importe, 'mes' => null, 'anio' => null];

        $filas = [];
        $filas[] = self::sec('INGRESOS');
        $filas[] = self::lin('Fichas poder público (anexo II)', $poder ?? ['dia' => null, 'mes' => null, 'anio' => null]);
        $filas[] = self::lin('Movimientos bancarios y financieros', $sumaCod(25, 1, 41, 37, 29));
        $filas[] = self::lin('Excedente de bóveda', $sumaCod(17, 13));
        $filas[] = self::lin('Recargo financiero', $dia($efe['recargo']));
        $filas[] = self::sec('EGRESOS');
        $filas[] = self::lin('Compras y gastos en efectivo y pagos de cuenta corriente (anexo VI)', $sumaCod(2));
        $filas[] = self::lin('Depósito efectivo', $sumaCod(3, 22));
        $filas[] = self::lin('Depósito propina', $sumaCod(7, 24));
        $filas[] = self::lin('Tarjetas de crédito', $dia($efe['tarjetas']));
        $filas[] = self::lin('Tarjetas de débito', $dia($efe['debito']));
        $filas[] = self::lin('Total QR', $dia($efe['qr']));
        $filas[] = self::lin('Depósito dólares', $dia($dolares));
        $filas[] = self::lin('Depósito euros', $dia($euros));
        $filas[] = self::lin('Otros egresos', $sumaCod(14, 30));
        $filas[] = self::sec('CAJA');
        $filas[] = self::lin('Caja inicial', self::cajaInicial($fechaYmd, $cajaAyer));
        if ($cajaHoy !== null) {
            $filas[] = self::lin('Caja final grabada en Anita', $cajaHoy);
        }

        return $filas;
    }

    /**
     * @param  array{dia: float, mes: float, anio: float}|null  $cajaAyer
     * @return array{dia: ?float, mes: ?float, anio: ?float}
     */
    private static function cajaInicial(string $fechaYmd, ?array $cajaAyer): array
    {
        if ($cajaAyer === null) {
            return ['dia' => null, 'mes' => null, 'anio' => null];
        }
        $mes = $cajaAyer['mes'];
        $anio = $cajaAyer['anio'];
        if (substr($fechaYmd, 8, 2) === '01') {
            $mes = $cajaAyer['dia'];
        }
        if (substr($fechaYmd, 5, 5) === '01-01') {
            $mes = $cajaAyer['dia'];
            $anio = $cajaAyer['dia'];
        }

        return ['dia' => $cajaAyer['dia'], 'mes' => $mes, 'anio' => $anio];
    }

    /**
     * @return array<int, array{dia: float, mes: float, anio: float}>
     */
    private static function items(int $fecha): array
    {
        $out = [];
        foreach (self::consultar(
            'item,diario,mensual,anual',
            'SELECT acan1_item, acan1_diario, acan1_mensual, acan1_anual FROM acumanexo1 WHERE acan1_fecha = '.$fecha
        ) as $fila) {
            $out[(int) $fila->item] = [
                'dia' => (float) $fila->diario,
                'mes' => (float) $fila->mensual,
                'anio' => (float) $fila->anual,
            ];
        }

        return $out;
    }

    /**
     * @return array{dia: float, mes: float, anio: float}|null
     */
    private static function item(int $fecha, int $codigo): ?array
    {
        return self::items($fecha)[$codigo] ?? null;
    }

    private static function ultimoDetalle(): ?int
    {
        $filas = self::consultar(
            'fecha',
            'SELECT MAX(acan1_fecha) FROM acumanexo1 WHERE acan1_item = 10'
        );
        if ($filas === [] || $filas[0]->fecha === null || $filas[0]->fecha === '') {
            return null;
        }

        return (int) $filas[0]->fecha;
    }

    /**
     * @return array{dia: float, mes: float, anio: float}|null
     */
    private static function ppublico(int $fecha): ?array
    {
        $filas = self::consultar(
            'dia,mes,acum',
            'SELECT ppub_ppublico, ppub_ppublico_mes, ppub_ppublico_acum FROM ppublico WHERE ppub_fecha = '.$fecha
        );
        if ($filas === []) {
            return null;
        }

        return [
            'dia' => (float) $filas[0]->dia,
            'mes' => (float) $filas[0]->mes,
            'anio' => (float) $filas[0]->acum,
        ];
    }

    /**
     * @return array{tarjetas: float, debito: float, qr: float, recargo: float}
     */
    private static function efectivo(int $fecha): array
    {
        $filas = self::consultar(
            'tarj,deb,qr,rec',
            'SELECT SUM(efe_tot_tarj), SUM(efe_tarjeta_debito), SUM(efe_total_qr), SUM(efe_recargo_fin) FROM efectivo WHERE efe_fecha = '.$fecha
        );
        $fila = $filas[0] ?? null;

        return [
            'tarjetas' => (float) ($fila->tarj ?? 0),
            'debito' => (float) ($fila->deb ?? 0),
            'qr' => (float) ($fila->qr ?? 0),
            'recargo' => (float) ($fila->rec ?? 0),
        ];
    }

    /**
     * Pesos de bóveda por código de transacción. l-anexo1.c lee_efeboveda
     * suma cantidad por valor de rendmbill con origen bóveda.
     *
     * @return array<int, float>
     */
    private static function boveda(int $fecha): array
    {
        $out = [];
        foreach (self::consultar(
            'cod,monto',
            'SELECT e.efeb_cod_trans, SUM(r.renmb_cantidad * r.renmb_valor) FROM efeboveda e, rendmbill r WHERE e.efeb_fecha = '.$fecha.' AND r.renmb_nro_oper = e.efeb_nro_oper AND r.renmb_origen = "B" AND r.renmb_cod_mon = "1" GROUP BY e.efeb_cod_trans'
        ) as $fila) {
            $out[(int) $fila->cod] = (float) $fila->monto;
        }

        return $out;
    }

    /**
     * Depósito de dólares (código 8, moneda 2) o euros (código 9, moneda 3),
     * como lee_monextr: monto por cotización, con el signo de la transacción.
     */
    private static function monedaExtranjera(int $fecha, int $codigo, string $moneda): float
    {
        $filas = self::consultar(
            'monto',
            'SELECT SUM(m.monev_monto * m.monev_cotizacion) FROM efeboveda e, monemov m WHERE e.efeb_fecha = '.$fecha.' AND e.efeb_cod_trans = '.$codigo.' AND m.monev_id = e.efeb_nro_oper AND m.monev_origen = "B" AND m.monev_cod_mon = "'.$moneda.'"'
        );
        $monto = (float) ($filas[0]->monto ?? 0);
        $signo = self::consultar(
            'signo',
            'SELECT transm_signo FROM transmae WHERE transm_codigo = '.$codigo
        );
        $marca = trim((string) ($signo[0]->signo ?? '0'));

        return $marca === '0' ? $monto : -$monto;
    }

    /**
     * @param  array{dia: float, mes: float, anio: float}  ...$partes
     * @return array{dia: float, mes: float, anio: float}
     */
    private static function suma(array ...$partes): array
    {
        $dia = $mes = $anio = 0.0;
        foreach ($partes as $parte) {
            $dia += $parte['dia'];
            $mes += $parte['mes'];
            $anio += $parte['anio'];
        }

        return ['dia' => $dia, 'mes' => $mes, 'anio' => $anio];
    }

    /**
     * @param  array{dia: float, mes: float, anio: float}  $a
     * @param  array{dia: float, mes: float, anio: float}  $b
     * @return array{dia: float, mes: float, anio: float}
     */
    private static function resta(array $a, array $b): array
    {
        return [
            'dia' => $a['dia'] - $b['dia'],
            'mes' => $a['mes'] - $b['mes'],
            'anio' => $a['anio'] - $b['anio'],
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
     * @param  array{dia: float, mes: float, anio: float}  $valores
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
