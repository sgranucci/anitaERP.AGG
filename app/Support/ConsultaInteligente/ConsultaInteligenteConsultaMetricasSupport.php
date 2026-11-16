<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Conteos / sumas / deltas / aging sobre el query del explorador (grounded, sin inventar).
 */
final class ConsultaInteligenteConsultaMetricasSupport
{
    /**
     * @param  array<string, mixed>  $query
     */
    public static function contar(string $clave, array $query): int
    {
        if (! EntidadSemanticaCatalogo::existe($clave)) {
            return 0;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        $query['consultar'] = 1;
        $request = Request::create('/', 'GET', $query);
        $filtros = $ent->resolverFiltros($request);
        $filas = $ent->consultar($filtros, true);

        return $filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
            ? (int) $filas->total()
            : (int) (is_countable($filas) ? count($filas) : 0);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function sumar(string $clave, array $query, ?string $campo = null): ?float
    {
        $campo = $campo ?? ConsultaInteligenteTableroSupport::campoMedidaSuma($clave);
        if ($campo === null || ! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        $query['consultar'] = 1;
        $request = Request::create('/', 'GET', $query);
        $filtros = $ent->resolverFiltros($request);
        $filas = $ent->consultar($filtros, false);
        $suma = 0.0;
        $n = 0;
        foreach ($filas as $row) {
            $suma += (float) ($row->{$campo} ?? 0);
            $n++;
            // Tope defensivo: narrativa no debe barrer universos enormes.
            if ($n >= 5000) {
                break;
            }
        }

        return $suma;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{texto: string, dir: string, pct: float}|null
     */
    public static function deltaVsMesAnterior(string $clave, array $query, ?int $totalActual = null): ?array
    {
        $campo = self::campoFechaQbe($clave);
        if ($campo === null || ! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $totalActual ??= self::contar($clave, $query);

        $queryAnt = $query;
        $queryAnt['filtro_modo'] = 'qbe';
        $qbe = is_array($queryAnt['qbe'] ?? null) ? $queryAnt['qbe'] : [
            'entre_grupos' => 'and',
            'grupos' => [],
        ];
        $grupos = is_array($qbe['grupos'] ?? null) ? $qbe['grupos'] : [];
        $reemplazado = false;
        foreach ($grupos as $gi => $grupo) {
            $criterios = is_array($grupo['criterios'] ?? null) ? $grupo['criterios'] : [];
            foreach ($criterios as $ci => $c) {
                if (! is_array($c)) {
                    continue;
                }
                if ((string) ($c['campo'] ?? '') === $campo
                    && in_array((string) ($c['op'] ?? ''), ['hoy', 'ayer', 'esta_semana', 'este_mes', 'mes_anterior', 'este_anio', 'entre', 'desde', 'hasta'], true)
                ) {
                    $grupos[$gi]['criterios'][$ci]['op'] = 'mes_anterior';
                    $grupos[$gi]['criterios'][$ci]['valor'] = '';
                    $grupos[$gi]['criterios'][$ci]['valor_hasta'] = '';
                    $reemplazado = true;
                }
            }
        }
        if (! $reemplazado) {
            $grupos[] = [
                'logic' => 'and',
                'not' => 0,
                'criterios' => [[
                    'campo' => $campo,
                    'op' => 'mes_anterior',
                    'valor' => '',
                    'valor_hasta' => '',
                ]],
            ];
        }
        $qbe['grupos'] = $grupos;
        $queryAnt['qbe'] = $qbe;

        try {
            $ant = self::contar($clave, $queryAnt);
        } catch (\Throwable) {
            return null;
        }
        if ($ant < 1) {
            return null;
        }
        $var = (($totalActual - $ant) / $ant) * 100;
        $dir = $var > 0.5 ? 'up' : ($var < -0.5 ? 'down' : 'flat');

        return [
            'texto' => sprintf('%s%.0f%% vs mes ant.', $var > 0 ? '+' : '', $var),
            'dir' => $dir,
            'pct' => round($var, 1),
        ];
    }

    /**
     * @return array{campo: string, modo: string}|null  modo: vencimiento|antiguedad
     */
    public static function agingConfig(string $clave): ?array
    {
        return match ($clave) {
            'cheque' => ['campo' => 'fechapago', 'modo' => 'vencimiento'],
            'comprobante_proveedor' => ['campo' => 'fechavencimiento', 'modo' => 'vencimiento'],
            'cobranza' => ['campo' => 'fecha', 'modo' => 'antiguedad'],
            default => null,
        };
    }

    public static function soportaAging(string $clave): bool
    {
        return self::agingConfig($clave) !== null;
    }

    /**
     * Aging por fecha de vencimiento (cheque / factura proveedor) o antigüedad (cobranza).
     *
     * @param  array<string, mixed>  $query
     * @return array{
     *   buckets: list<array{clave: string, label: string, n: int, url: string}>,
     *   total: int,
     *   modo: string,
     *   campo: string
     * }|null
     */
    public static function aging(string $clave, array $query): ?array
    {
        $cfg = self::agingConfig($clave);
        if ($cfg === null || ! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        $query['consultar'] = 1;
        $request = Request::create('/', 'GET', $query);
        $filtros = $ent->resolverFiltros($request);
        /** @var Collection|iterable $filas */
        $filas = $ent->consultar($filtros, false);

        $hoy = Carbon::today();
        $campo = $cfg['campo'];
        $modo = $cfg['modo'];
        $buckets = $modo === 'antiguedad'
            ? [
                '0_7' => ['label' => '0–7 días', 'n' => 0],
                '8_15' => ['label' => '8–15 días', 'n' => 0],
                '16_30' => ['label' => '16–30 días', 'n' => 0],
                '30_mas' => ['label' => '+30 días', 'n' => 0],
                'sin_fecha' => ['label' => 'Sin fecha', 'n' => 0],
            ]
            : [
                'vencido' => ['label' => 'Vencidos', 'n' => 0],
                '0_7' => ['label' => '0–7 días', 'n' => 0],
                '8_15' => ['label' => '8–15 días', 'n' => 0],
                '16_30' => ['label' => '16–30 días', 'n' => 0],
                '30_mas' => ['label' => '+30 días', 'n' => 0],
                'sin_fecha' => ['label' => 'Sin fecha', 'n' => 0],
            ];
        $total = 0;
        foreach ($filas as $row) {
            $total++;
            if ($total > 5000) {
                break;
            }
            $raw = $row->{$campo} ?? null;
            if ($raw === null || $raw === '' || $raw === '0000-00-00') {
                $buckets['sin_fecha']['n']++;
                continue;
            }
            try {
                $f = Carbon::parse((string) $raw)->startOfDay();
            } catch (\Throwable) {
                $buckets['sin_fecha']['n']++;
                continue;
            }
            if ($modo === 'antiguedad') {
                $dias = (int) $f->diffInDays($hoy, false); // edad desde la fecha
                if ($dias < 0) {
                    $dias = 0;
                }
                if ($dias <= 7) {
                    $buckets['0_7']['n']++;
                } elseif ($dias <= 15) {
                    $buckets['8_15']['n']++;
                } elseif ($dias <= 30) {
                    $buckets['16_30']['n']++;
                } else {
                    $buckets['30_mas']['n']++;
                }
            } else {
                $dias = (int) $hoy->diffInDays($f, false); // negativo = vencido
                if ($dias < 0) {
                    $buckets['vencido']['n']++;
                } elseif ($dias <= 7) {
                    $buckets['0_7']['n']++;
                } elseif ($dias <= 15) {
                    $buckets['8_15']['n']++;
                } elseif ($dias <= 30) {
                    $buckets['16_30']['n']++;
                } else {
                    $buckets['30_mas']['n']++;
                }
            }
        }

        $out = [];
        foreach ($buckets as $k => $b) {
            if ($b['n'] < 1 && $k === 'sin_fecha') {
                continue;
            }
            $out[] = [
                'clave' => $k,
                'label' => $b['label'],
                'n' => $b['n'],
                'url' => EntidadSemanticaCatalogo::urlExplorar($clave, $query),
            ];
        }

        return [
            'buckets' => $out,
            'total' => $total,
            'modo' => $modo,
            'campo' => $campo,
        ];
    }

    /**
     * Frase corta para KPI de tablero (grounded).
     *
     * @param  array<string, mixed>  $query
     */
    public static function narrativaCorta(string $clave, array $query, ?int $conteo = null, ?float $suma = null): string
    {
        if (! EntidadSemanticaCatalogo::existe($clave)) {
            return '';
        }
        $nombre = EntidadSemanticaCatalogo::porClave($clave)->nombre();
        try {
            $conteo ??= self::contar($clave, $query);
        } catch (\Throwable) {
            return '';
        }
        $partes = [];
        if ($conteo === 0) {
            return 'Sin registros de '.$nombre.' con el filtro actual.';
        }
        $partes[] = number_format($conteo, 0, ',', '.').' '.$nombre;
        $campoSuma = ConsultaInteligenteTableroSupport::campoMedidaSuma($clave);
        if ($suma === null && $campoSuma !== null && $conteo <= 3000) {
            try {
                $suma = self::sumar($clave, $query, $campoSuma);
            } catch (\Throwable) {
                $suma = null;
            }
        }
        if ($suma !== null) {
            $partes[] = self::fmtImporte($suma);
        }
        try {
            $delta = self::deltaVsMesAnterior($clave, $query, $conteo);
            if ($delta !== null) {
                $partes[] = $delta['texto'];
            }
        } catch (\Throwable) {
        }

        return implode(' · ', $partes).'.';
    }

    /**
     * Narrativa grounded a partir del resultado NL (conteo + suma + delta + aging opcional).
     *
     * @param  array<string, mixed>  $nl
     * @return array{
     *   texto: string,
     *   conteo: int,
     *   suma: ?float,
     *   suma_fmt: ?string,
     *   promedio: ?float,
     *   promedio_fmt: ?string,
     *   delta: ?array{texto: string, dir: string, pct: float},
     *   aging: ?array
     * }|null
     */
    public static function narrativaDesdeNl(array $nl): ?array
    {
        if (empty($nl['ok']) || empty($nl['entidad'])) {
            return null;
        }
        $clave = (string) $nl['entidad'];
        if (! EntidadSemanticaCatalogo::existe($clave)) {
            return null;
        }
        $ent = EntidadSemanticaCatalogo::porClave($clave);
        if ($ent instanceof EntidadSemanticaPuenteReporte) {
            return null;
        }
        $query = is_array($nl['query'] ?? null) ? $nl['query'] : [];
        $query['consultar'] = 1;

        try {
            $conteo = self::contar($clave, $query);
        } catch (\Throwable) {
            return null;
        }

        $campoSuma = ConsultaInteligenteTableroSupport::campoMedidaSuma($clave);
        $suma = null;
        $promedio = null;
        if ($campoSuma !== null && $conteo > 0 && $conteo <= 5000) {
            try {
                $suma = self::sumar($clave, $query, $campoSuma);
                if ($suma !== null && $conteo > 0) {
                    $promedio = $suma / $conteo;
                }
            } catch (\Throwable) {
                $suma = null;
            }
        }

        $delta = null;
        try {
            $delta = self::deltaVsMesAnterior($clave, $query, $conteo);
        } catch (\Throwable) {
            $delta = null;
        }

        $aging = null;
        $metricaHint = is_string($nl['metrica'] ?? null) ? (string) $nl['metrica'] : null;
        $pideAging = self::soportaAging($clave) && (
            $metricaHint === 'aging'
            || self::textoPideAging((string) ($nl['explicacion'] ?? ''), (string) ($nl['_pregunta'] ?? ''))
        );
        $autoAgingCheque = self::soportaAging($clave) && $clave === 'cheque' && $conteo > 0 && $conteo <= 2000;
        if (($pideAging || $autoAgingCheque) && $conteo > 0 && $conteo <= 3000) {
            try {
                $aging = self::aging($clave, $query);
            } catch (\Throwable) {
                $aging = null;
            }
        }

        $nombre = $ent->nombre();
        $partes = [];
        if ($conteo === 0) {
            $partes[] = 'No hay registros de '.$nombre.' con esos criterios.';
        } elseif ($conteo === 1) {
            $partes[] = 'Hay 1 registro de '.$nombre.'.';
        } else {
            $partes[] = 'Hay '.number_format($conteo, 0, ',', '.').' registros de '.$nombre.'.';
        }
        if ($suma !== null) {
            $partes[] = 'Suma '.$campoSuma.': '.self::fmtImporte($suma);
        }
        if ($promedio !== null && $conteo > 1) {
            $partes[] = 'Promedio: '.self::fmtImporte($promedio);
        }
        if ($delta !== null) {
            $partes[] = $delta['texto'];
        }
        if ($aging !== null && ($aging['total'] ?? 0) > 0) {
            $bits = [];
            foreach ($aging['buckets'] as $b) {
                if (($b['n'] ?? 0) < 1) {
                    continue;
                }
                $bits[] = $b['label'].' '.$b['n'];
            }
            if ($bits !== []) {
                $partes[] = 'Aging: '.implode(' · ', $bits);
            }
        }

        return [
            'texto' => implode(' ', $partes),
            'conteo' => $conteo,
            'suma' => $suma,
            'suma_fmt' => $suma !== null ? self::fmtImporte($suma) : null,
            'promedio' => $promedio,
            'promedio_fmt' => $promedio !== null ? self::fmtImporte($promedio) : null,
            'delta' => $delta,
            'aging' => $aging,
        ];
    }

    public static function fmtImporte(float $n): string
    {
        return '$ '.number_format($n, 2, ',', '.');
    }

    private static function textoPideAging(string $explicacion, string $pregunta): bool
    {
        $blob = mb_strtolower($explicacion.' '.$pregunta);

        return str_contains($blob, 'aging')
            || str_contains($blob, 'antiguedad')
            || str_contains($blob, 'antigüedad')
            || str_contains($blob, 'vencim')
            || str_contains($blob, 'por vencer')
            || str_contains($blob, 'métrica: aging')
            || str_contains($blob, 'metrica: aging');
    }

    public static function campoFechaQbe(string $clave): ?string
    {
        return match ($clave) {
            'cheque' => 'fechapago',
            'comprobante_proveedor' => 'fechacomprobante',
            'pagoproveedor', 'ingresoegreso', 'cobranza', 'ordencompra', 'requisicion', 'solicitudpago', 'ticket', 'recepcion_proveedor', 'asiento', 'remito', 'factura', 'pedido', 'movimientostock', 'transferencia_mercaderia', 'capex' => 'fecha',
            'importacion' => 'fecha_apertura',
            'empleado_sueldos' => 'fecha_ingreso',
            default => null,
        };
    }
}
