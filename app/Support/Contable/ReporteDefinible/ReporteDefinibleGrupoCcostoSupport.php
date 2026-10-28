<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\Centrocosto;
use App\Support\Contable\SumasSaldosListadoFiltros;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Filtro de ejecución por grupo de centros de costo (Anita l-infomae, opción «Filtra por grupo»).
 * El grupo vive en centrocosto.grupo_codigo / grupo_nombre (ccosgrupo de Anita).
 */
class ReporteDefinibleGrupoCcostoSupport
{
    /**
     * @return list<array{codigo: string, nombre: string, centros: int}>
     */
    public static function listar(): array
    {
        $armado = self::indice();
        $out = [];
        foreach ($armado as $grupo) {
            $out[] = [
                'codigo' => $grupo['codigo'],
                'nombre' => $grupo['nombre'],
                'centros' => count($grupo['centros']),
            ];
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigos
     * @return list<array{codigo: string, nombre: string}>
     */
    public static function describirSeleccion(array $codigos): array
    {
        $resuelto = self::resolver($codigos);

        return array_map(static fn (array $g): array => [
            'codigo' => $g['codigo'],
            'nombre' => $g['nombre'],
        ], $resuelto['grupos']);
    }

    public static function buscar(string $codigo): ?array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }
        $resuelto = self::resolver([$codigo]);
        $grupo = $resuelto['grupos'][0] ?? null;
        if ($grupo === null) {
            return null;
        }

        return [
            'codigo' => $grupo['codigo'],
            'nombre' => $grupo['nombre'],
            'centros' => count($grupo['centros']),
        ];
    }

    /**
     * Rango de códigos y/o grupos. Null = sin filtro de ejecución.
     *
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $advertencias
     * @return array{desde: int, hasta: int, codigo_set?: array<int, true>, grupos?: list<array{codigo: string, nombre: string}>}|null
     */
    public static function filtroRuntime(array $filtros, array &$advertencias): ?array
    {
        $desde = (int) ($filtros['ccosto_desde'] ?? 0);
        $hasta = (int) ($filtros['ccosto_hasta'] ?? 0);
        if ($hasta <= 0 && $desde > 0) {
            $hasta = $desde;
        }
        if ($desde <= 0 && $hasta > 0) {
            $desde = $hasta;
        }
        if ($desde > $hasta && $hasta > 0) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $pedidos = self::normalizarCodigos($filtros['grupo_ccosto'] ?? []);
        $hayRango = $desde > 0 || $hasta > 0;
        if ($pedidos === [] && ! $hayRango) {
            return null;
        }

        $filtro = [
            'desde' => $hayRango ? $desde : 0,
            'hasta' => $hayRango ? $hasta : 0,
        ];

        if ($pedidos !== []) {
            $resuelto = self::resolver($pedidos);
            if ($resuelto['faltan'] !== []) {
                $advertencias[] = 'Grupo de centros de costo inexistente: '.implode(', ', $resuelto['faltan']).'.';
            }
            $set = [];
            foreach ($resuelto['codigos'] as $codigo) {
                $set[(int) $codigo] = true;
            }
            $filtro['codigo_set'] = $set;
            $filtro['grupos'] = array_map(static fn (array $g): array => [
                'codigo' => $g['codigo'],
                'nombre' => $g['nombre'],
            ], $resuelto['grupos']);
            if ($filtro['grupos'] === []) {
                $advertencias[] = 'No quedó ningún grupo de centros de costo para filtrar. El informe sale vacío.';
            } else {
                $nombres = array_map(
                    static fn (array $g): string => trim($g['nombre']) !== ''
                        ? $g['nombre'].' ('.$g['codigo'].')'
                        : $g['codigo'],
                    $filtro['grupos']
                );
                $advertencias[] = 'Filtro por grupo de centros de costo: '.implode(', ', $nombres).'.';
            }
        }

        return $filtro;
    }

    /**
     * Cabecera al estilo Anita (cartel / cartel2): moneda, período y si se filtró la inflación.
     *
     * @param  array<string, mixed>  $filtros
     * @param  array{desde: int, hasta: int, codigo_set?: array<int, true>, grupos?: list<array{codigo: string, nombre: string}>}|null  $filtroCc
     */
    public static function criterios(array $filtros, ?array $filtroCc): string
    {
        $lineas = [];

        $monedaId = (int) ($filtros['moneda_id'] ?? 1);
        $nombreMoneda = trim((string) (DB::table('moneda')->where('id', $monedaId)->value('nombre') ?? ''));
        if ($nombreMoneda === '') {
            $nombreMoneda = 'moneda '.$monedaId;
        }
        $expr = ! empty($filtros['solo_moneda_origen']) ? 'M/O' : 'T/M';
        $rango = self::textoPeriodoAnita($filtros);
        $lineas[] = 'Expr.: '.mb_strtoupper($nombreMoneda).' ('.$expr.')'.($rango !== '' ? ' '.$rango : '');

        $modoAsientos = (string) ($filtros['modo_inclusion_asientos'] ?? 'sin_cierre_ni_inflacion');
        $filtraInflacion = in_array($modoAsientos, ['sin_inflacion', 'sin_cierre_ni_inflacion'], true);
        $lineas[] = $filtraInflacion
            ? 'Filtra ajuste x inflacion'
            : 'Sin filtrar ajuste x inflacion';

        $base = (string) ($filtros['base_saldo'] ?? ReporteDefinibleSupport::BASE_SALDO_PERIODO);
        if ($base === ReporteDefinibleSupport::BASE_SALDO_EJERCICIO) {
            $lineas[] = 'Saldo del ejercicio';
        }

        $grupos = is_array($filtroCc['grupos'] ?? null) ? $filtroCc['grupos'] : [];
        if ($grupos !== []) {
            $nombres = array_map(
                static fn (array $g): string => trim((string) ($g['nombre'] ?? '')) !== ''
                    ? trim((string) $g['nombre']).' ('.$g['codigo'].')'
                    : (string) $g['codigo'],
                $grupos
            );
            $lineas[] = 'Grupo de centros de costo: '.implode(', ', $nombres);
        }

        $desde = (int) ($filtroCc['desde'] ?? 0);
        $hasta = (int) ($filtroCc['hasta'] ?? 0);
        if ($desde > 0 || $hasta > 0) {
            $lineas[] = 'Centro de costo '.$desde.' a '.$hasta;
        }

        return implode("\n", $lineas);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private static function textoPeriodoAnita(array $filtros): string
    {
        $fmt = static function (int $mes, int $anio): string {
            $nombres = [
                1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
                5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
                9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
            ];

            return ($nombres[$mes] ?? (string) $mes).'/'.sprintf('%02d', $anio % 100);
        };

        $modo = (string) ($filtros['modo_periodo'] ?? '');
        if ($modo === ReporteDefinibleSupport::MODO_MESES) {
            $periodos = ReporteDefinibleListadoFiltros::normalizarListaPeriodos($filtros['periodos'] ?? []);
            if ($periodos === []) {
                return '';
            }
            $a = $periodos[0];
            $b = $periodos[count($periodos) - 1];
            $izq = $fmt($a % 100, intdiv($a, 100));
            $der = $fmt($b % 100, intdiv($b, 100));

            return $izq === $der ? $izq : $izq.' - '.$der;
        }

        if ($modo === SumasSaldosListadoFiltros::MODO_RANGO) {
            $fd = trim((string) ($filtros['fecha_desde'] ?? ''));
            $fh = trim((string) ($filtros['fecha_hasta'] ?? ''));
            $d = \DateTime::createFromFormat('Y-m-d', $fd);
            $h = \DateTime::createFromFormat('Y-m-d', $fh);
            if ($d && $h) {
                return $d->format('d/m/Y').' - '.$h->format('d/m/Y');
            }

            return '';
        }

        $md = (int) ($filtros['mes_desde'] ?? 0);
        $ad = (int) ($filtros['anio_desde'] ?? 0);
        $mh = (int) ($filtros['mes_hasta'] ?? 0);
        $ah = (int) ($filtros['anio_hasta'] ?? 0);
        if ($md < 1 || $ad < 1) {
            $pd = (int) ($filtros['periodo_desde'] ?? 0);
            $ph = (int) ($filtros['periodo_hasta'] ?? 0);
            if ($pd <= 0) {
                return '';
            }
            $md = $pd % 100;
            $ad = intdiv($pd, 100);
            $mh = $ph > 0 ? $ph % 100 : $md;
            $ah = $ph > 0 ? intdiv($ph, 100) : $ad;
        }
        $izq = $fmt($md, $ad);
        $der = $fmt($mh > 0 ? $mh : $md, $ah > 0 ? $ah : $ad);

        return $izq === $der ? $izq : $izq.' - '.$der;
    }

    /**
     * @param  list<int|string>  $pedidos
     * @return array{grupos: list<array{codigo: string, nombre: string, centros: list<int>}>, codigos: list<int>, faltan: list<string>}
     */
    public static function resolver(array $pedidos): array
    {
        $indice = self::indice();
        $porClave = [];
        foreach ($indice as $grupo) {
            $porClave[$grupo['codigo']] = $grupo;
            $sinCeros = ltrim($grupo['codigo'], '0');
            if ($sinCeros !== '' && $sinCeros !== $grupo['codigo']) {
                $porClave[$sinCeros] = $grupo;
            }
        }

        $elegidos = [];
        $faltan = [];
        $centros = [];
        foreach (self::normalizarCodigos($pedidos) as $pedido) {
            $grupo = $porClave[$pedido] ?? $porClave[ltrim($pedido, '0')] ?? null;
            if ($grupo === null) {
                $faltan[] = $pedido;
                continue;
            }
            $elegidos[$grupo['codigo']] = $grupo;
            foreach ($grupo['centros'] as $codigo) {
                $centros[(int) $codigo] = (int) $codigo;
            }
        }

        return [
            'grupos' => array_values($elegidos),
            'codigos' => array_values($centros),
            'faltan' => $faltan,
        ];
    }

    /**
     * @return list<string>
     */
    public static function normalizarCodigos(mixed $raw): array
    {
        if (! is_array($raw)) {
            $raw = ($raw === null || $raw === '') ? [] : [$raw];
        }
        $out = [];
        foreach ($raw as $valor) {
            $codigo = trim((string) $valor);
            if ($codigo === '' || isset($out[$codigo])) {
                continue;
            }
            $out[$codigo] = $codigo;
        }

        return array_values($out);
    }

    /**
     * @return array<string, array{codigo: string, nombre: string, centros: list<int>}>
     */
    private static function indice(): array
    {
        if (! Schema::hasTable('centrocosto') || ! Schema::hasColumn('centrocosto', 'grupo_codigo')) {
            return [];
        }

        $filas = Centrocosto::query()
            ->whereNotNull('grupo_codigo')
            ->where('grupo_codigo', '!=', '')
            ->orderBy('grupo_codigo')
            ->get(['grupo_codigo', 'grupo_nombre', 'codigo']);

        $grupos = [];
        foreach ($filas as $fila) {
            $codigo = trim((string) $fila->grupo_codigo);
            if ($codigo === '') {
                continue;
            }
            if (! isset($grupos[$codigo])) {
                $grupos[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => trim((string) ($fila->grupo_nombre ?? '')),
                    'centros' => [],
                ];
            }
            $codigoCentro = (int) $fila->codigo;
            if ($codigoCentro > 0) {
                $grupos[$codigo]['centros'][$codigoCentro] = $codigoCentro;
            }
        }
        foreach ($grupos as $codigo => $grupo) {
            $grupos[$codigo]['centros'] = array_values($grupo['centros']);
        }

        return $grupos;
    }
}
