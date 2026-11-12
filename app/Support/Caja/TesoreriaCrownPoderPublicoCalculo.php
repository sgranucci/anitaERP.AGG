<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownConversionFicha;
use App\Models\Caja\TesoreriaCrownFicha;
use App\Models\Caja\TesoreriaCrownFichaBanca;
use App\Models\Caja\TesoreriaCrownFichaCierre;
use App\Models\Caja\TesoreriaCrownFichaMovimiento;
use App\Models\Caja\TesoreriaCrownPoderPublico;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Poder del público de fichas, como l-stockfichm.c.
 *
 * Solo tablas ERP (`tesoreria_crown_ficha*`). Si falta catálogo o conversión
 * del día anterior, devuelve estructura en cero con aviso para importar fichas
 * (sin lectura live a Anita).
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
 * El mes y el acumulado arrancan en tesoreria_crown_poder_publico del día anterior y suman ese día.
 * El 1 del mes, el mes arranca en cero. Sin lectura Anita.
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
     *     origen: string,
     *     avisos: list<string>
     * }
     */
    public static function delDia(string $fechaYmd, ?int $salaId = null): array
    {
        $avisos = [];
        if (self::erpDisponible($fechaYmd, $avisos)) {
            $armado = self::delDiaErp($fechaYmd, $avisos);
            $armado['origen'] = 'erp';
            $armado['filas'] = self::unificarFilasFval((int) ($salaId ?? 0), $armado['filas'] ?? []);

            return $armado;
        }

        $avisos[] = 'Poder público de fichas sin datos ERP: importá con tesoreria-crown:importar-fichas-anita.';

        return [
            'filas' => [],
            'diario' => 0,
            'mes' => 0,
            'acumulado' => 0,
            'anita_dia' => null,
            'anita_mes' => null,
            'anita_acumulado' => null,
            'conversion_valorizada' => 0.0,
            'poder_valorizado' => 0.0,
            'diario_valorizado' => 0.0,
            'diario_canje' => 0.0,
            'origen' => 'erp',
            'avisos' => array_values(array_unique($avisos)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private static function unificarFilasFval(int $salaId, array $filas): array
    {
        if ($salaId <= 0 || $filas === []) {
            return $filas;
        }

        return TesoreriaCrownFichaValorUnificacionSupport::unificarFilas(
            $salaId,
            $filas,
            [
                'reserva',
                'reserva_inicial',
                'otros',
                'inicial',
                'final',
                'habilitacion',
                'pagos_caja',
                'pp_diario',
                'poder',
                'mesas',
                'total',
            ]
        );
    }

    /**
     * @param  list<string>  $avisos
     */
    private static function erpDisponible(string $fechaYmd, array &$avisos): bool
    {
        if (! Schema::hasTable('tesoreria_crown_ficha')
            || ! Schema::hasTable('tesoreria_crown_conversion_ficha')) {
            return false;
        }
        if (TesoreriaCrownFicha::query()->where('orden_carga', '>=', 1)->count() === 0) {
            $avisos[] = 'Sin catálogo de fichas ERP.';

            return false;
        }
        $ayer = date('Y-m-d', strtotime($fechaYmd.' -1 day'));
        $tieneAyer = TesoreriaCrownConversionFicha::query()->whereDate('fecha', $ayer)->exists();
        if (! $tieneAyer) {
            $avisos[] = 'Sin conversión ERP del '.$ayer.'.';

            return false;
        }

        return true;
    }

    /**
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function delDiaErp(string $fechaYmd, array $avisos): array
    {
        $ayer = date('Y-m-d', strtotime($fechaYmd.' -1 day'));
        $fichas = self::fichasErp();
        $ayerFinal = TesoreriaCrownConversionFicha::query()
            ->whereDate('fecha', $ayer)
            ->pluck('conv_final', 'codigo_ficha')
            ->map(fn ($v) => (int) $v)
            ->all();
        $mesa = self::mapaDobleErp($fechaYmd, TesoreriaCrownFichaMovimiento::ORIGEN_MESA);
        $cajero = self::mapaDobleErp($fechaYmd, TesoreriaCrownFichaMovimiento::ORIGEN_CAJERO);
        $canje = self::mapaSimpleErp($fechaYmd, TesoreriaCrownFichaMovimiento::ORIGEN_CANJE);
        $otrosMov = self::mapaSimpleErp($fechaYmd, TesoreriaCrownFichaMovimiento::ORIGEN_OTROS);
        $reservaDia = [];
        foreach ($mesa as $codigo => $tipos) {
            if (isset($tipos['I'])) {
                $reservaDia[$codigo] = (int) $tipos['I'];
            }
        }
        $codigos = array_column($fichas, 'codigo');
        $cierre = self::cierresErp($fechaYmd, $codigos);
        $asignacion = self::asignacionInicialErp($fechaYmd, $codigos, $cierre, $avisos);
        $banca = TesoreriaCrownFichaBanca::query()
            ->pluck('cantidad', 'codigo_ficha')
            ->map(fn ($v) => (int) $v)
            ->all();

        return self::armarFilas(
            $fechaYmd,
            $ayer,
            $fichas,
            $ayerFinal,
            $mesa,
            $cajero,
            $canje,
            $otrosMov,
            $reservaDia,
            $cierre,
            $asignacion,
            $banca,
            $avisos
        );
    }

    /**
     * @param  list<array{codigo: int, serie: string, descripcion: string, valor: float, canje: bool}>  $fichas
     * @param  array<int, int>  $ayerFinal
     * @param  array<int, array<string, int>>  $mesa
     * @param  array<int, array<string, int>>  $cajero
     * @param  array<int, int>  $canje
     * @param  array<int, int>  $otrosMov
     * @param  array<int, int>  $reservaDia
     * @param  array<int, array{reserva: int, cierre: int}>  $cierre
     * @param  array<int, int>  $asignacion
     * @param  array<int, int>  $banca
     * @param  list<string>  $avisos
     * @return array<string, mixed>
     */
    private static function armarFilas(
        string $fechaYmd,
        string $ayerYmd,
        array $fichas,
        array $ayerFinal,
        array $mesa,
        array $cajero,
        array $canje,
        array $otrosMov,
        array $reservaDia,
        array $cierre,
        array $asignacion,
        array $banca,
        array $avisos
    ): array {
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
            $pagosCaja = (int) ($cajero[$codigo]['P'] ?? 0);
            $ventasCaja = (int) ($cajero[$codigo]['V'] ?? 0);
            $variacionCajero = $pagosCaja + $ventasCaja - $habilitacion;
            $reservaBase = (int) ($cierre[$codigo]['reserva'] ?? 0);
            $reservaInicialDia = (int) ($asignacion[$codigo] ?? 0) + (int) ($reservaDia[$codigo] ?? 0);
            $reserva = $reservaBase + (int) ($asignacion[$codigo] ?? 0);
            $mesas = (int) ($banca[$codigo] ?? 0);
            if (array_key_exists($codigo, $ayerFinal)) {
                $inicial = (int) $ayerFinal[$codigo] + (int) ($reservaDia[$codigo] ?? 0) + (int) ($otrosMov[$codigo] ?? 0);
            } else {
                $inicial = $reserva - $mesas;
                $avisos[] = 'La ficha '.$ficha['serie'].' no tiene conversión del '.$ayerYmd.'. La inicial salió de reserva menos mesas.';
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
                'reserva_inicial' => $reservaInicialDia,
                'otros' => $otros,
                'inicial' => $inicial,
                'final' => $final,
                'habilitacion' => $habilitacion,
                'pagos_caja' => $pagosCaja,
                'pp_diario' => $ppDiario,
                'poder' => $poder,
                'mesas' => $mesas,
                'total' => $total,
                'canje' => $ficha['canje'],
            ];
        }

        $dia = self::entero($diarioValor);
        $fechaInt = (int) str_replace('-', '', $fechaYmd);
        $anterior = self::poderPublicoErpCadena($ayerYmd);
        $esPrimero = substr((string) $fechaInt, 6, 2) === '01';
        $mes = ($esPrimero ? 0 : (int) ($anterior['mes'] ?? 0)) + $dia;
        $acumulado = (int) ($anterior['acumulado'] ?? 0) + $dia;

        return [
            'filas' => $filas,
            'diario' => $dia,
            'mes' => $mes,
            'acumulado' => $acumulado,
            'anita_dia' => null,
            'anita_mes' => null,
            'anita_acumulado' => null,
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
    private static function fichasErp(): array
    {
        $filas = [];
        foreach (TesoreriaCrownFicha::query()->where('orden_carga', '>=', 1)->orderBy('orden_carga')->get() as $ficha) {
            $filas[] = [
                'codigo' => (int) $ficha->codigo,
                'serie' => (string) $ficha->serie,
                'descripcion' => (string) $ficha->desc_color,
                'valor' => (float) $ficha->valor,
                'canje' => (bool) $ficha->ficha_canje,
            ];
        }

        return $filas;
    }

    /**
     * @return array<int, array<string, int>>
     */
    private static function mapaDobleErp(string $fechaYmd, string $origen): array
    {
        $out = [];
        $rows = TesoreriaCrownFichaMovimiento::query()
            ->whereDate('fecha', $fechaYmd)
            ->where('origen', $origen)
            ->get(['codigo_ficha', 'tipo_oper', 'cantidad']);
        foreach ($rows as $row) {
            $tipo = strtoupper(trim((string) ($row->tipo_oper ?? '')));
            if ($tipo === '') {
                continue;
            }
            $out[(int) $row->codigo_ficha][$tipo] = (int) $row->cantidad;
        }

        return $out;
    }

    /**
     * @return array<int, int>
     */
    private static function mapaSimpleErp(string $fechaYmd, string $origen): array
    {
        $out = [];
        $rows = TesoreriaCrownFichaMovimiento::query()
            ->whereDate('fecha', $fechaYmd)
            ->where('origen', $origen)
            ->get(['codigo_ficha', 'cantidad']);
        foreach ($rows as $row) {
            $out[(int) $row->codigo_ficha] = (int) $row->cantidad;
        }

        return $out;
    }

    /**
     * @param  list<int>  $codigos
     * @return array<int, array{reserva: int, cierre: int}>
     */
    private static function cierresErp(string $fechaYmd, array $codigos): array
    {
        $out = [];
        if ($codigos === [] || ! Schema::hasTable('tesoreria_crown_ficha_cierre')) {
            return $out;
        }
        $rows = TesoreriaCrownFichaCierre::query()
            ->whereDate('fecha', '<=', $fechaYmd)
            ->whereIn('codigo_ficha', $codigos)
            ->orderByDesc('fecha')
            ->get(['codigo_ficha', 'fecha', 'reserva']);
        foreach ($rows as $row) {
            $codigo = (int) $row->codigo_ficha;
            if (isset($out[$codigo])) {
                continue;
            }
            $out[$codigo] = [
                'reserva' => (int) $row->reserva,
                'cierre' => (int) $row->fecha->format('Ymd'),
            ];
        }

        return $out;
    }

    /**
     * Asignaciones iniciales (tipo I) desde el último cierre + 1 (o desde siempre si no hay cierre).
     *
     * @param  list<int>  $codigos
     * @param  array<int, array{reserva: int, cierre: int}>  $cierres
     * @param  list<string>  $avisos
     * @return array<int, int>
     */
    private static function asignacionInicialErp(string $fechaYmd, array $codigos, array $cierres, array &$avisos = []): array
    {
        $out = [];
        $hayI = TesoreriaCrownFichaMovimiento::query()
            ->where('origen', TesoreriaCrownFichaMovimiento::ORIGEN_MESA)
            ->where('tipo_oper', 'I')
            ->exists();
        if (! $hayI) {
            $avisos[] = 'Sin asignaciones iniciales ERP (tipo I): importá fichas/movimientos; se usan ceros.';

            return [];
        }
        foreach ($codigos as $codigo) {
            $codigo = (int) $codigo;
            $corte = (int) ($cierres[$codigo]['cierre'] ?? 0);
            $reservaCierre = (int) ($cierres[$codigo]['reserva'] ?? 0);
            if ($reservaCierre <= 0) {
                $corte = 0;
            }
            $desde = $corte > 0
                ? date('Y-m-d', strtotime(sprintf('%04d-%02d-%02d', intdiv($corte, 10000), intdiv($corte % 10000, 100), $corte % 100).' +1 day'))
                : '1900-01-01';
            $suma = (int) TesoreriaCrownFichaMovimiento::query()
                ->where('origen', TesoreriaCrownFichaMovimiento::ORIGEN_MESA)
                ->where('tipo_oper', 'I')
                ->where('codigo_ficha', $codigo)
                ->whereDate('fecha', '>=', $desde)
                ->whereDate('fecha', '<=', $fechaYmd)
                ->sum('cantidad');
            $out[$codigo] = $suma;
        }

        return $out;
    }

    /**
     * Cadena mes/acumulado desde fila ERP del día (cualquier sala; CIP tiene una).
     *
     * @return array{mes: int, acumulado: int}|null
     */
    private static function poderPublicoErpCadena(string $fechaYmd): ?array
    {
        if (! Schema::hasTable('tesoreria_crown_poder_publico')) {
            return null;
        }
        $fila = TesoreriaCrownPoderPublico::query()
            ->whereDate('fecha', $fechaYmd)
            ->orderByDesc('id')
            ->first();
        if ($fila === null) {
            return null;
        }

        return [
            'mes' => (int) round((float) $fila->ficha_mes),
            'acumulado' => (int) round((float) $fila->ficha_acum),
        ];
    }

    private static function entero(float $importe): int
    {
        return (int) $importe;
    }
}
