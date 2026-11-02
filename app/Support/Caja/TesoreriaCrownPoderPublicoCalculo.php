<?php

namespace App\Support\Caja;

use App\ApiAnita;
use RuntimeException;

/**
 * Poder del público de fichas, como l-stockfichm.c.
 *
 * Por ficha, si hay conversión del día anterior:
 *   conversión inicial = conversión final de ayer + asignación inicial de hoy + otros movimientos de ficha de hoy
 *   conversión final = conversión inicial + variación de mesas de hoy + variación de cajeros de hoy
 *   variación de mesas = estado final − (habilitación + reposición − crédito) + canje
 *   variación de cajeros = rendición de pago + rendición de venta − habilitación
 *
 * El importe del día que entra al anexo I es la suma de
 * (conversión inicial − conversión final) × valor. No se trunca a cero:
 * si la conversión final sube, el día da negativo.
 * El mes y el acumulado arrancan en el ppublico del día anterior y suman ese día.
 * El 1 del mes, el mes arranca en cero.
 */
final class TesoreriaCrownPoderPublicoCalculo
{
    /**
     * @return array{
     *     filas: list<array<string, mixed>>,
     *     diario: int,
     *     mes: int,
     *     acumulado: int,
     *     anita_dia: int|null,
     *     anita_mes: int|null,
     *     anita_acumulado: int|null,
     *     conversion_valorizada: float,
     *     poder_valorizado: float,
     *     diario_valorizado: float,
     *     diario_canje: float,
     *     avisos: list<string>
     * }
     */
    public static function delDia(string $fechaYmd): array
    {
        $fecha = (int) str_replace('-', '', $fechaYmd);
        $ayer = (int) date('Ymd', strtotime($fechaYmd.' -1 day'));
        $avisos = [];

        $fichas = self::fichas();
        $ayerFinal = self::mapa('codigo', 'final', self::consultar(
            'codigo,final',
            'SELECT conv_codigo_ficha, conv_conv_final FROM conversion WHERE conv_fecha = '.$ayer
        ));
        $mesa = self::mapaDoble(self::consultar(
            'codigo,tipo,cantidad',
            'SELECT fichmv_codigo, fichmv_tipo_oper, SUM(fichmv_cantidad) FROM fichammov WHERE fichmv_fecha = '.$fecha.' GROUP BY fichmv_codigo, fichmv_tipo_oper'
        ));
        $cajero = self::mapaDoble(self::consultar(
            'codigo,tipo,cantidad',
            'SELECT fichcv_codigo, fichcv_tipo_oper, SUM(fichcv_cantidad) FROM fichacmov WHERE fichcv_fecha = '.$fecha.' GROUP BY fichcv_codigo, fichcv_tipo_oper'
        ));
        $canje = self::mapa('codigo', 'cantidad', self::consultar(
            'codigo,cantidad',
            'SELECT fcanjm_codigo, SUM(fcanjm_cantidad) FROM fcanjemesa WHERE fcanjm_fecha = '.$fecha.' GROUP BY fcanjm_codigo'
        ));
        $otrosMov = self::mapa('codigo', 'cantidad', self::consultar(
            'codigo,cantidad',
            'SELECT fichv_codigo_ficha, SUM(fichv_cantidad) FROM fichamov WHERE fichv_fecha = '.$fecha.' GROUP BY fichv_codigo_ficha'
        ));
        $reservaDia = self::mapa('codigo', 'cantidad', self::consultar(
            'codigo,cantidad',
            'SELECT fichmv_codigo, SUM(fichmv_cantidad) FROM fichammov WHERE fichmv_tipo_oper = "I" AND fichmv_fecha = '.$fecha.' GROUP BY fichmv_codigo'
        ));
        $cierre = self::cierres($fecha, array_column($fichas, 'codigo'));
        $asignacion = self::asignacionInicial($fecha, $cierre);
        $banca = self::mapa('codigo', 'cantidad', self::consultar(
            'codigo,cantidad',
            'SELECT b.bant_codigo, SUM(b.bant_cantidad) FROM bancatipo b, mesajuego m WHERE m.mesaj_estado = "A" AND b.bant_tipojuego = m.mesaj_tipoj_banca GROUP BY b.bant_codigo'
        ));

        $filas = [];
        $diarioValor = 0.0;
        $poderValor = 0.0;
        $conversionValor = 0.0;
        $diarioCanje = 0.0;
        foreach ($fichas as $ficha) {
            $codigo = $ficha['codigo'];
            $valor = $ficha['valor'];
            $a = (int) ($mesa[$codigo]['A'] ?? 0);
            $r = (int) ($mesa[$codigo]['R'] ?? 0);
            $c = (int) ($mesa[$codigo]['C'] ?? 0);
            $f = (int) ($mesa[$codigo]['F'] ?? 0);
            $variacionMesas = $f - ($a + $r - $c) + (int) ($canje[$codigo] ?? 0);
            $habilitacion = (int) ($cajero[$codigo]['H'] ?? 0);
            $variacionCajero = (int) ($cajero[$codigo]['P'] ?? 0) + (int) ($cajero[$codigo]['V'] ?? 0) - $habilitacion;
            $reserva = (int) ($cierre[$codigo]['reserva'] ?? 0) + (int) ($asignacion[$codigo] ?? 0);
            $mesas = (int) ($banca[$codigo] ?? 0);
            if (array_key_exists($codigo, $ayerFinal)) {
                $inicial = (int) $ayerFinal[$codigo] + (int) ($reservaDia[$codigo] ?? 0) + (int) ($otrosMov[$codigo] ?? 0);
            } else {
                $inicial = $reserva - $mesas;
                $avisos[] = 'La ficha '.$ficha['serie'].' no tiene conversión del '.$ayer.'. La inicial salió de reserva menos mesas.';
            }
            $final = $inicial + $variacionMesas + $variacionCajero;
            $otros = $final > $inicial ? -($inicial - $final) : 0;
            $ppDiario = $final > $inicial ? 0 : $inicial - $final;
            $poder = $reserva - $final - $mesas - $habilitacion;
            $total = $final + $ppDiario + $mesas + $habilitacion;
            $diferenciaValor = ($inicial - $final) * $valor;
            $diarioValor += $diferenciaValor;
            $poderValor += $poder * $valor;
            $conversionValor += $final * $valor;
            if ($ficha['canje']) {
                $diarioCanje += $diferenciaValor;
            }
            $filas[] = [
                'serie' => $ficha['serie'],
                'descripcion' => $ficha['descripcion'],
                'valor' => $valor,
                'reserva' => $reserva,
                'otros' => $otros,
                'inicial' => $inicial,
                'final' => $final,
                'habilitacion' => $habilitacion,
                'pp_diario' => $ppDiario,
                'poder' => $poder,
                'mesas' => $mesas,
                'total' => $total,
                'canje' => $ficha['canje'],
            ];
        }

        $dia = self::entero($diarioValor);
        $anterior = self::ppublico($ayer);
        $esPrimero = substr((string) $fecha, 6, 2) === '01';
        $mes = ($esPrimero ? 0 : (int) ($anterior['mes'] ?? 0)) + $dia;
        $acumulado = (int) ($anterior['acumulado'] ?? 0) + $dia;
        $grabado = self::ppublico($fecha);

        return [
            'filas' => $filas,
            'diario' => $dia,
            'mes' => $mes,
            'acumulado' => $acumulado,
            'anita_dia' => $grabado['dia'] ?? null,
            'anita_mes' => $grabado['mes'] ?? null,
            'anita_acumulado' => $grabado['acumulado'] ?? null,
            'conversion_valorizada' => round($conversionValor, 2),
            'poder_valorizado' => round($poderValor, 2),
            'diario_valorizado' => round($diarioValor, 2),
            'diario_canje' => round($diarioCanje, 2),
            'avisos' => $avisos,
        ];
    }

    /**
     * @return list<array{codigo: int, serie: string, descripcion: string, valor: float, canje: bool}>
     */
    private static function fichas(): array
    {
        $filas = [];
        foreach (self::consultar(
            'fichm_codigo,fichm_serie,fichm_valor,fichm_orden_carga,fichm_ficha_canje,fichm_desc_color',
            'SELECT fichm_codigo, fichm_serie, fichm_valor, fichm_orden_carga, fichm_ficha_canje, fichm_desc_color FROM fichamae ORDER BY fichm_orden_carga'
        ) as $fila) {
            if ((int) $fila->fichm_orden_carga < 1) {
                continue;
            }
            $filas[] = [
                'codigo' => (int) $fila->fichm_codigo,
                'serie' => trim((string) $fila->fichm_serie),
                'descripcion' => trim((string) $fila->fichm_desc_color),
                'valor' => (float) $fila->fichm_valor,
                'canje' => strtoupper(trim((string) $fila->fichm_ficha_canje)) === 'S',
            ];
        }

        return $filas;
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, array{reserva: int, cierre: int}>
     */
    private static function cierres(int $fecha, array $codigos): array
    {
        $out = [];
        if ($codigos === []) {
            return $out;
        }
        foreach (self::consultar(
            'codigo,reserva,ultimo',
            'SELECT cief_codigo_ficha, SUM(cief_reserva), MAX(cief_fecha) FROM cierreficha WHERE cief_fecha <= '.$fecha.' AND cief_codigo_ficha IN ('.implode(',', $codigos).') GROUP BY cief_codigo_ficha'
        ) as $fila) {
            $out[(int) $fila->codigo] = [
                'reserva' => (int) $fila->reserva,
                'cierre' => (int) $fila->ultimo,
            ];
        }

        return $out;
    }

    /**
     * Asignación inicial (tipo I) posterior al último cierre de esa ficha.
     *
     * @param  array<int, array{reserva: int, cierre: int}>  $cierres
     * @return array<int, int>
     */
    private static function asignacionInicial(int $fecha, array $cierres): array
    {
        $cortes = [0];
        foreach ($cierres as $cierre) {
            $cortes[$cierre['cierre']] = $cierre['cierre'];
        }
        $porCorte = [];
        foreach ($cortes as $corte) {
            $porCorte[$corte] = self::mapa('codigo', 'cantidad', self::consultar(
                'codigo,cantidad',
                'SELECT fichmv_codigo, SUM(fichmv_cantidad) FROM fichammov WHERE fichmv_tipo_oper = "I" AND fichmv_fecha > '.$corte.' AND fichmv_fecha <= '.$fecha.' GROUP BY fichmv_codigo'
            ));
        }
        $out = [];
        $codigos = array_unique(array_merge(array_keys($cierres), ...array_map('array_keys', $porCorte)));
        foreach ($codigos as $codigo) {
            $corte = $cierres[$codigo]['cierre'] ?? 0;
            $out[$codigo] = (int) ($porCorte[$corte][$codigo] ?? 0);
        }

        return $out;
    }

    /**
     * @return array{dia: int, mes: int, acumulado: int}|null
     */
    private static function ppublico(int $fecha): ?array
    {
        $filas = self::consultar(
            'ppub_fecha,ppub_ppublico,ppub_ppublico_mes,ppub_ppublico_acum',
            'SELECT ppub_fecha, ppub_ppublico, ppub_ppublico_mes, ppub_ppublico_acum FROM ppublico WHERE ppub_fecha = '.$fecha
        );
        if ($filas === []) {
            return null;
        }
        $fila = $filas[0];

        return [
            'dia' => (int) $fila->ppub_ppublico,
            'mes' => (int) $fila->ppub_ppublico_mes,
            'acumulado' => (int) $fila->ppub_ppublico_acum,
        ];
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, int>
     */
    private static function mapa(string $codigo, string $cantidad, array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->{$codigo}] = (int) $fila->{$cantidad};
        }

        return $out;
    }

    /**
     * @param  list<object>  $filas
     * @return array<int, array<string, int>>
     */
    private static function mapaDoble(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[(int) $fila->codigo][strtoupper(trim((string) $fila->tipo))] = (int) $fila->cantidad;
        }

        return $out;
    }

    private static function entero(float $importe): int
    {
        return (int) $importe;
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
