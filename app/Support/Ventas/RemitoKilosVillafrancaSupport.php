<?php

namespace App\Support\Ventas;

use App\ApiAnita;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * F5 remito Z: kilos del día desde Anita Villafranca (comprob + compaux).
 *
 * FAC y ND suman; NC restan. Remitos, cobranzas y otros tipos no entran.
 */
final class RemitoKilosVillafrancaSupport
{
    /** SKU de texto / no mercadería (mismo criterio que COT). */
    private const SKU_EXCLUIDOS = ['0000000000903'];

    /**
     * Signo de kilos según tipo Anita Villafranca (t_comp).
     * NC → -1, FAC/ND → +1, resto (REM, cobranzas, etc.) → 0.
     */
    public static function signoTipo(string $tipoAnita): int
    {
        $tipo = strtoupper(preg_replace('/[^A-Z0-9]/', '', trim($tipoAnita)) ?? '');
        if ($tipo === '') {
            return 0;
        }

        if (str_starts_with($tipo, 'NC')) {
            return -1;
        }
        if (str_starts_with($tipo, 'ND')) {
            return 1;
        }
        if (str_starts_with($tipo, 'FA') || $tipo === 'FCE') {
            return 1;
        }

        return 0;
    }

    public static function fechaHoy(): Carbon
    {
        return Carbon::today();
    }

    public static function fechaAnitaHoy(): int
    {
        return (int) self::fechaHoy()->format('Ymd');
    }

    public static function esLineaExcluida(string $sku): bool
    {
        $sku = trim($sku);
        if ($sku === '') {
            return true;
        }
        $skuNorm = strtolower($sku);
        if (str_starts_with($skuNorm, 'texto')) {
            return true;
        }

        return in_array($sku, self::SKU_EXCLUIDOS, true);
    }

    public static function normalizarSku(string $sku): string
    {
        $sku = trim($sku);
        $sinCeros = ltrim($sku, '0');

        return $sinCeros !== '' ? $sinCeros : $sku;
    }

    public static function claveComprob(string $tipo, string $letra, int $sucursal, int $nro): string
    {
        return strtoupper(trim($tipo)).'|'.strtoupper(trim($letra)).'|'.$sucursal.'|'.$nro;
    }

    /**
     * Lee comprob+compaux de Villafranca del día y agrega kilos por SKU (ya firmados).
     *
     * @return array{ok: bool, error?: string, fecha: string, fecha_anita: int, items: list<array<string, mixed>>, comprobantes: int}
     */
    public static function agregarKilosDelDia(int $codigoReparto): array
    {
        $fecha = self::fechaHoy();
        $fechaAnita = self::fechaAnitaHoy();
        $base = [
            'ok' => false,
            'fecha' => $fecha->toDateString(),
            'fecha_anita' => $fechaAnita,
            'items' => [],
            'comprobantes' => 0,
        ];

        if ($codigoReparto <= 0) {
            $base['error'] = 'Reparto inválido';

            return $base;
        }

        $comprobs = self::listarComprobDelDia($codigoReparto, $fechaAnita);
        if (isset($comprobs['error'])) {
            $base['error'] = $comprobs['error'];

            return $base;
        }

        /** @var array<string, int> $signoPorClave */
        $signoPorClave = [];
        foreach ($comprobs['filas'] as $fila) {
            $row = (array) $fila;
            $tipo = trim((string) ($row['comp_tipo'] ?? ''));
            $signo = self::signoTipo($tipo);
            if ($signo === 0) {
                continue;
            }
            $clave = self::claveComprob(
                $tipo,
                (string) ($row['comp_letra'] ?? ''),
                (int) ($row['comp_sucursal'] ?? 0),
                (int) ($row['comp_nro_fact'] ?? 0),
            );
            $signoPorClave[$clave] = $signo;
        }

        if ($signoPorClave === []) {
            $base['error'] = 'No hay comprobantes del día en Villafranca para ese reparto';

            return $base;
        }

        $lineas = self::listarCompauxDelDia($fechaAnita);
        if (isset($lineas['error'])) {
            $base['error'] = $lineas['error'];

            return $base;
        }

        $agg = [];
        foreach ($lineas['filas'] as $fila) {
            $row = (array) $fila;
            $clave = self::claveComprob(
                (string) ($row['compa_tipo'] ?? ''),
                (string) ($row['compa_letra'] ?? ''),
                (int) ($row['compa_sucursal'] ?? 0),
                (int) ($row['compa_nro_fact'] ?? 0),
            );
            if (! isset($signoPorClave[$clave])) {
                continue;
            }
            self::acumularLinea($agg, $row, $signoPorClave[$clave]);
        }

        if ($agg === []) {
            $base['error'] = 'No hay ítems en compaux de Villafranca para ese reparto';

            return $base;
        }

        $base['ok'] = true;
        $base['items'] = array_values($agg);
        $base['comprobantes'] = count($signoPorClave);

        return $base;
    }

    /**
     * @param  array<string, array<string, mixed>>  $agg
     * @param  array<string, mixed>  $linea
     */
    public static function acumularLinea(array &$agg, array $linea, int $signo): void
    {
        if ($signo === 0) {
            return;
        }

        $skuRaw = trim((string) ($linea['compa_articulo'] ?? ''));
        if (self::esLineaExcluida($skuRaw)) {
            return;
        }

        $sku = self::normalizarSku($skuRaw);
        $kilo = (float) ($linea['compa_cantidad'] ?? 0);
        $pieza = (float) ($linea['compa_pieza'] ?? 0);
        if ($kilo == 0.0 && $pieza == 0.0) {
            return;
        }

        if (! isset($agg[$sku])) {
            $agg[$sku] = [
                'sku' => $sku,
                'sku_anita' => $skuRaw,
                'descripcion' => trim((string) ($linea['compa_desc'] ?? '')),
                'kilo' => 0.0,
                'pieza' => 0.0,
                'caja' => 0.0,
                'precio' => 0.0,
                'incluyeimpuesto' => self::incluyeImpuestoAnita($linea['compa_incl_imp'] ?? 'N'),
            ];
        }

        $agg[$sku]['kilo'] += $kilo * $signo;
        $agg[$sku]['pieza'] += $pieza * $signo;

        $precio = (float) ($linea['compa_precio'] ?? 0);
        if ($precio > 0 && $agg[$sku]['precio'] <= 0) {
            $agg[$sku]['precio'] = $precio;
        }
        if (trim((string) ($agg[$sku]['descripcion'] ?? '')) === '') {
            $agg[$sku]['descripcion'] = trim((string) ($linea['compa_desc'] ?? ''));
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public static function aplicarPorcentaje(array $items, float $porcentaje): array
    {
        $factor = 1.0 - ($porcentaje / 100.0);
        $out = [];
        foreach ($items as $row) {
            $row['kilo'] = round((float) $row['kilo'] * $factor, 1);
            $row['pieza'] = round((float) ($row['pieza'] ?? 0) * $factor, 1);
            $row['caja'] = round((float) ($row['caja'] ?? 0) * $factor, 1);
            if ($row['kilo'] == 0.0 && $row['pieza'] == 0.0) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }

    /**
     * @return array{filas: list<object>, error?: string}
     */
    private static function listarComprobDelDia(int $codigoReparto, int $fechaAnita): array
    {
        $codigoSql = (int) $codigoReparto;

        return self::listarAnita('comprob', '
                comp_tipo, comp_letra, comp_sucursal, comp_nro_fact,
                comp_fecha, comp_transporte
            ', ' WHERE comp_fecha = '.$fechaAnita
                .' AND comp_transporte = '.$codigoSql.' ');
    }

    /**
     * @return array{filas: list<object>, error?: string}
     */
    private static function listarCompauxDelDia(int $fechaAnita): array
    {
        return self::listarAnita('compaux', '
                compa_tipo, compa_letra, compa_sucursal, compa_nro_fact,
                compa_articulo, compa_cantidad, compa_pieza, compa_precio,
                compa_desc, compa_incl_imp, compa_fecha
            ', ' WHERE compa_fecha = '.$fechaAnita.' ');
    }

    /**
     * @return array{filas: list<object>, error?: string}
     */
    private static function listarAnita(string $tabla, string $campos, string $where): array
    {
        $api = new ApiAnita();
        $parseado = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'ventas',
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'path_sistema' => VillafrancaFacturacionSupport::pathSistema(),
        ]));

        if ($parseado['error_lectura'] !== null) {
            Log::warning('remito.f5.villafranca.'.$tabla, [
                'mensaje' => $parseado['error_lectura'],
                'where' => $where,
            ]);

            return ['filas' => [], 'error' => 'No se pudo leer '.$tabla.' de Villafranca: '.$parseado['error_lectura']];
        }

        return ['filas' => $parseado['filas']];
    }

    private static function incluyeImpuestoAnita(mixed $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return $v === 'S' ? 'S' : 'N';
    }
}
