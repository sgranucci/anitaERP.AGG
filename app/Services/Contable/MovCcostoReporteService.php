<?php

namespace App\Services\Contable;

use App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaCentrocostoFiltroSupport;
use App\Support\Contable\MovCcosto\MovCcostoProcesador;
use App\Support\Contable\MovCcosto\MovCcostoRuntimeSupport;
use App\Support\Contable\MovCcostoListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MovCcostoReporteService
{
    public function __construct(
        private readonly MovCcostoProcesador $procesador,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        MovCcostoRuntimeSupport::elevarLimites();

        $empresaIds = array_values(array_filter(
            array_map('intval', $filtros['empresa_ids'] ?? []),
            static fn (int $id): bool => $id > 0,
        ));

        // El procesador ya separa por empresa si no se consolida (cada sección trae su empresa_id).
        return $this->procesador->generar($empresaIds, $filtros);
    }

    /**
     * Lista plana para pantalla / export: encabezado de empresa (si hay varias), filas,
     * total de empresa y total general.
     *
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(array $resultado, array $filtros, bool $paraExport = false): array
    {
        $secciones = $resultado['secciones'] ?? [];
        if (! is_array($secciones) || $secciones === []) {
            return [];
        }

        $varias = count($secciones) > 1;
        $out = [];
        $nombresEmpresa = $this->nombresEmpresaSeleccionadas($filtros);
        $nombreUnico = count($nombresEmpresa) === 1 ? reset($nombresEmpresa) : '';

        foreach ($secciones as $seccion) {
            $nombre = (string) ($seccion['empresa_nombre'] ?? '');
            $esEmpresaReal = (int) ($seccion['empresa_id'] ?? 0) > 0;

            if ($varias) {
                $out[] = [
                    'tipo_fila' => 'header_empresa',
                    'empresa_id' => (int) ($seccion['empresa_id'] ?? 0),
                    'nombreempresa' => $esEmpresaReal ? $nombre : '',
                    'codigo_fmt' => '',
                    'nombre' => $nombre,
                    'debe' => [],
                    'haber' => [],
                    'tot_debe' => 0.0,
                    'tot_haber' => 0.0,
                ];
            }

            foreach ($seccion['filas'] as $fila) {
                if (($fila['tipo_fila'] ?? '') === 'cuenta') {
                    $fila['nombreempresa'] = $esEmpresaReal ? $nombre : (string) $nombreUnico;
                }
                $out[] = $fila;
            }

            if ($varias) {
                $total = $seccion['total'];
                $total['tipo_fila'] = 'total_empresa';
                $total['nombre'] = 'Total '.$nombre;
                $out[] = $total;
            }
        }

        if ($varias || ! empty($resultado['total_general'])) {
            $general = $resultado['total_general'];
            if (is_array($general)) {
                $out[] = $general;
            }
        }

        return $out;
    }

    /**
     * Filas que corresponden a un bloque de columnas del PDF: se omiten las cuentas, totales y
     * títulos sin importes en esas columnas (un bloque no debe arrastrar renglones vacíos).
     *
     * @param  list<array<string, mixed>>  $filas
     * @param  list<int>  $indices
     * @param  bool  $conTotal  el bloque lleva la columna Total (último bloque)
     * @return list<array<string, mixed>>
     */
    public function filasParaBloque(array $filas, array $indices, bool $conTotal = false): array
    {
        $tieneValor = static function (array $fila) use ($indices, $conTotal): bool {
            if ($conTotal && (abs((float) ($fila['tot_debe'] ?? 0)) > 0.004 || abs((float) ($fila['tot_haber'] ?? 0)) > 0.004)) {
                return true;
            }
            foreach ($indices as $i) {
                if (abs((float) ($fila['debe'][$i] ?? 0)) > 0.004 || abs((float) ($fila['haber'][$i] ?? 0)) > 0.004) {
                    return true;
                }
            }

            return false;
        };

        $out = [];
        /** @var list<array<string, mixed>> $pendientes títulos / encabezados de empresa aún sin hijas con datos */
        $pendientes = [];

        foreach ($filas as $fila) {
            $tipo = (string) ($fila['tipo_fila'] ?? 'cuenta');
            $nivel = (int) ($fila['nivel'] ?? 0);

            switch ($tipo) {
                case 'header_empresa':
                    $pendientes = [$fila];
                    break;

                case 'titulo':
                    $pendientes = array_values(array_filter(
                        $pendientes,
                        static fn (array $p): bool => ($p['tipo_fila'] ?? '') === 'header_empresa'
                            || (int) ($p['nivel'] ?? 0) < $nivel,
                    ));
                    $pendientes[] = $fila;
                    break;

                case 'cuenta':
                    if ($tieneValor($fila)) {
                        foreach ($pendientes as $p) {
                            $out[] = $p;
                        }
                        $pendientes = [];
                        $out[] = $fila;
                    }
                    break;

                case 'total_nivel':
                    if ($tieneValor($fila)) {
                        $out[] = $fila;
                    }
                    break;

                default: // total_empresa, total_general
                    if ($tieneValor($fila)) {
                        $out[] = $fila;
                    }
                    break;
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>|Collection  $filas
     */
    public function paginarFilas($filas, int $perPage): LengthAwarePaginator
    {
        $items = $filas instanceof Collection ? $filas->values() : collect($filas)->values();
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = max(10, min(200, $perPage));

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearPeriodoTexto(array $filtros): string
    {
        [$d, $h] = MovCcostoListadoFiltros::normalizarRangoFechas(
            (string) ($filtros['fecha_desde'] ?? ''),
            (string) ($filtros['fecha_hasta'] ?? ''),
        );
        if ($d === '' || $h === '') {
            return '';
        }

        return $d === $h
            ? 'Día '.$this->fmtFecha($d)
            : 'Del '.$this->fmtFecha($d).' al '.$this->fmtFecha($h);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearEmpresasTexto(array $filtros): string
    {
        $ids = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? [])));
        if ($ids === []) {
            return '';
        }

        $nombres = DB::table('empresa')->whereIn('id', $ids)->pluck('nombre', 'id');
        $partes = [];
        foreach ($ids as $id) {
            $partes[] = (string) ($nombres[$id] ?? ('#'.$id));
        }

        $txt = implode(', ', $partes);
        if (count($ids) > 1 && ! empty($filtros['consolidar_empresas'])) {
            $txt .= ' (consolidado)';
        }

        return $txt;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearInclusionAsientosTexto(array $filtros): string
    {
        return match ((string) ($filtros['modo_inclusion_asientos'] ?? '')) {
            'todos' => 'Incluye cierre e inflación',
            'sin_cierre' => 'Excluye asientos de cierre y la apertura que los reabre',
            'sin_inflacion' => 'Excluye asientos de inflación',
            default => 'Excluye cierre, la apertura que lo reabre, e inflación',
        };
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function formatearAgrupacionTexto(array $filtros): string
    {
        $col = ($filtros['agrupar'] ?? '') === MovCcostoListadoFiltros::AGRUPAR_CCOSTO
            ? 'Columnas por centro de costo'
            : 'Columnas por grupo de centros de costo';
        $fmt = ($filtros['formato'] ?? '') === MovCcostoListadoFiltros::FORMATO_DEBE_HABER
            ? 'Debe / Haber'
            : 'Solo saldo';

        return $col.' · '.$fmt;
    }

    /**
     * Texto con los filtros de centro de costo / grupo activos ('' si no hay).
     *
     * @param  array<string, mixed>  $filtros
     */
    public function formatearCentrosTexto(array $filtros): string
    {
        $partes = [];

        $soporte = new MayorPlanoCuentaCentrocostoFiltroSupport(
            $filtros['centrocostos_codigo'] ?? '',
            $filtros['cc_desde'] ?? '',
            $filtros['cc_hasta'] ?? '',
            false,
        );
        if ($soporte->tieneFiltro()) {
            $partes[] = str_replace(' · excluye sin CC', '', $soporte->metaTexto());
        }

        $grupos = MovCcostoListadoFiltros::parsearGrupos($filtros['grupos_codigo'] ?? '');
        if ($grupos !== []) {
            $partes[] = 'Grupos: '.implode(', ', $grupos);
        }

        if (! empty($filtros['incluir_sin_cc'])) {
            $partes[] = 'incluye movimientos sin centro de costo';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<int, string> id → nombre
     */
    public function nombresEmpresaSeleccionadas(array $filtros): array
    {
        $ids = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? [])));
        if ($ids === []) {
            return [];
        }

        return DB::table('empresa')->whereIn('id', $ids)->pluck('nombre', 'id')->map(fn ($n) => (string) $n)->all();
    }

    private function fmtFecha(string $ymd): string
    {
        if (strlen($ymd) < 10) {
            return $ymd;
        }

        return substr($ymd, 8, 2).'/'.substr($ymd, 5, 2).'/'.substr($ymd, 0, 4);
    }
}
