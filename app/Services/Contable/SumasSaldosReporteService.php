<?php

namespace App\Services\Contable;

use App\Support\Contable\SumasSaldos\SumasSaldosProcesador;
use App\Support\Contable\SumasSaldosListadoFiltros;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SumasSaldosReporteService
{
    public function __construct(
        private readonly SumasSaldosProcesador $procesador,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function generarDesdeFiltros(array $filtros): array
    {
        $empresaIds = array_values(array_filter(array_map('intval', $filtros['empresa_ids'] ?? []), fn (int $id) => $id > 0));
        $consolidar = ! empty($filtros['consolidar_empresas']);

        if ($consolidar || count($empresaIds) <= 1) {
            $resultado = $this->procesador->generar($empresaIds, $filtros);
            $resultado['secciones'] = [[
                'empresa_id' => count($empresaIds) === 1 ? $empresaIds[0] : 0,
                'empresa_nombre' => count($empresaIds) === 1
                    ? $this->nombreEmpresa($empresaIds[0])
                    : 'Consolidado',
                'filas' => $resultado['filas'],
                'totales' => $resultado['totales'],
            ]];

            return $resultado;
        }

        $secciones = [];
        $filasFusion = [];
        $advertencias = [];
        $tot = [
            'cuentas' => 0,
            'lineas' => 0,
            'debe' => 0.0,
            'haber' => 0.0,
            'saldo_periodo' => 0.0,
            'saldo_mes_anterior' => 0.0,
            'saldo_ejercicio' => 0.0,
        ];
        $fuente = 'saldos_mes';

        foreach ($empresaIds as $empresaId) {
            $parcial = $this->procesador->generar([$empresaId], $filtros);
            $fuente = (string) ($parcial['fuente'] ?? $fuente);
            $nombre = $this->nombreEmpresa($empresaId);
            $secciones[] = [
                'empresa_id' => $empresaId,
                'empresa_nombre' => $nombre,
                'filas' => $parcial['filas'],
                'totales' => $parcial['totales'],
            ];

            $filasFusion[] = [
                'tipo_fila' => 'header_empresa',
                'empresa_id' => $empresaId,
                'nombreempresa' => $nombre,
                'codigo_fmt' => '',
                'nombre' => $nombre,
                'debe' => null,
                'haber' => null,
                'saldo_periodo' => null,
                'saldo_mes_anterior' => null,
                'saldo_ejercicio' => null,
            ];
            foreach ($parcial['filas'] as $fila) {
                $filasFusion[] = $fila;
            }

            foreach (['debe', 'haber', 'saldo_periodo', 'saldo_mes_anterior', 'saldo_ejercicio'] as $k) {
                $tot[$k] += (float) ($parcial['totales'][$k] ?? 0);
            }
            $tot['cuentas'] += (int) ($parcial['totales']['cuentas'] ?? 0);
            $tot['lineas'] += (int) ($parcial['totales']['lineas'] ?? 0);
            foreach ($parcial['advertencias'] ?? [] as $adv) {
                $advertencias[$adv] = $adv;
            }
        }

        foreach (['debe', 'haber', 'saldo_periodo', 'saldo_mes_anterior', 'saldo_ejercicio'] as $k) {
            $tot[$k] = round((float) $tot[$k], 2);
        }

        return [
            'filas' => $filasFusion,
            'totales' => $tot,
            'fuente' => $fuente,
            'advertencias' => array_values($advertencias),
            'secciones' => $secciones,
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @param  array<string, mixed>  $filtros
     * @return list<array<string, mixed>>
     */
    public function aplanarFilas(array $resultado, array $filtros, bool $paraExport = false): array
    {
        $filas = $resultado['filas'] ?? [];
        if (! is_array($filas)) {
            return [];
        }

        // Enriquecer nombreempresa para logos en export.
        $nombres = [];
        foreach ($filas as &$fila) {
            if (($fila['tipo_fila'] ?? '') === 'header_empresa') {
                $nombres[(int) ($fila['empresa_id'] ?? 0)] = (string) ($fila['nombreempresa'] ?? $fila['nombre'] ?? '');
                $fila['nombreempresa'] = $fila['nombreempresa'] ?? $fila['nombre'] ?? '';
            }
        }
        unset($fila);

        if (count($filtros['empresa_ids'] ?? []) === 1) {
            $empresaId = (int) $filtros['empresa_ids'][0];
            $nombre = $this->nombreEmpresa($empresaId);
            foreach ($filas as &$fila) {
                if (($fila['tipo_fila'] ?? '') === 'cuenta') {
                    $fila['nombreempresa'] = $nombre;
                }
            }
            unset($fila);
        }

        return array_values($filas);
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
        if (($filtros['modo_periodo'] ?? '') === SumasSaldosListadoFiltros::MODO_RANGO) {
            $d = trim((string) ($filtros['fecha_desde'] ?? ''));
            $h = trim((string) ($filtros['fecha_hasta'] ?? ''));
            if ($d === '' || $h === '') {
                return '';
            }

            return 'Rango '.$this->fmtFecha($d).' a '.$this->fmtFecha($h).' (asientos)';
        }

        $pd = (int) ($filtros['periodo_desde'] ?? 0);
        $ph = (int) ($filtros['periodo_hasta'] ?? 0);
        if ($pd <= 0) {
            return '';
        }

        $txt = $this->fmtPeriodo($pd);
        if ($ph > 0 && $ph !== $pd) {
            $txt .= ' a '.$this->fmtPeriodo($ph);
        }

        return $txt.' (saldos mensuales)';
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
        $modo = (string) ($filtros['modo_inclusion_asientos'] ?? '');
        $base = match ($modo) {
            'todos' => 'Incluye cierre e inflación',
            'sin_cierre' => 'Excluye asientos de cierre',
            'sin_inflacion' => 'Excluye asientos de inflación',
            default => 'Excluye cierre e inflación',
        };

        if (($filtros['modo_periodo'] ?? '') === SumasSaldosListadoFiltros::MODO_PERIODOS) {
            if ($modo === 'todos') {
                return 'Saldos mensuales (incluye todos los asientos del agregado)';
            }

            return 'Saldos mensuales − '.$base.' (resta desde asientos)';
        }

        return $base;
    }

    private function nombreEmpresa(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '';
        }

        return (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? ('#'.$empresaId));
    }

    private function fmtFecha(string $ymd): string
    {
        if (strlen($ymd) < 10) {
            return $ymd;
        }

        return substr($ymd, 8, 2).'/'.substr($ymd, 5, 2).'/'.substr($ymd, 0, 4);
    }

    private function fmtPeriodo(int $yyyymm): string
    {
        $s = str_pad((string) $yyyymm, 6, '0', STR_PAD_LEFT);

        return substr($s, 4, 2).'/'.substr($s, 0, 4);
    }
}
