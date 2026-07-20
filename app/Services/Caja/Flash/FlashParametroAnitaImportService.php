<?php

declare(strict_types=1);

namespace App\Services\Caja\Flash;

use App\ApiAnita;
use App\Models\Caja\Flash\FlashParametro;
use App\Models\Configuracion\Empresa;
use App\Repositories\Caja\Flash\FlashParametroRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Importa los parámetros del Flash (budgets mensuales + índices estacionales diarios)
 * desde Informix (tablas paramflash / indexflash del legacy l-flash.c) vía bridge Anita
 * hacia las tablas ERP flash_parametro / flash_parametro_indice.
 */
final class FlashParametroAnitaImportService
{
    public function __construct(
        private readonly FlashParametroRepositoryInterface $repository,
    ) {}

    /**
     * @param  callable(string):void|null  $log  callback opcional para reportar progreso
     * @return array{
     *   parametros_creados: int,
     *   parametros_actualizados: int,
     *   indices: int,
     *   periodos_sin_empresa: array<int, string>,
     *   periodos_procesados: int
     * }
     */
    public function importarRango(string $periodoDesde, string $periodoHasta, ?callable $log = null): array
    {
        $periodoDesde = $this->normalizarPeriodo($periodoDesde);
        $periodoHasta = $this->normalizarPeriodo($periodoHasta);
        if ($periodoDesde === null || $periodoHasta === null) {
            throw new \InvalidArgumentException('Período inválido: use formato YYYYMM (ej. 202401).');
        }
        if ($periodoDesde > $periodoHasta) {
            [$periodoDesde, $periodoHasta] = [$periodoHasta, $periodoDesde];
        }

        $reportar = static function (string $mensaje) use ($log): void {
            if ($log !== null) {
                $log($mensaje);
            }
        };

        $mapaEmpresas = $this->mapaEmpresasPorCodigoAnita();

        $sistema = (string) config('flash_parametro_anita.sistema', 'caja');
        $tablaParam = (string) config('flash_parametro_anita.tabla_parametro', 'paramflash');
        $tablaIndice = (string) config('flash_parametro_anita.tabla_indice', 'indexflash');

        $reportar("Leyendo {$tablaParam} desde Anita ({$periodoDesde} a {$periodoHasta})...");
        $parametros = $this->leerParametros($sistema, $tablaParam, $periodoDesde, $periodoHasta);
        $reportar('Parámetros (cabecera) leídos: '.count($parametros));

        $reportar("Leyendo {$tablaIndice} desde Anita...");
        $indicesPorEmpresaPeriodo = $this->leerIndices($sistema, $tablaIndice, $periodoDesde, $periodoHasta);
        $totalIndicesLeidos = 0;
        foreach ($indicesPorEmpresaPeriodo as $porPeriodo) {
            foreach ($porPeriodo as $filas) {
                $totalIndicesLeidos += count($filas);
            }
        }
        $reportar('Índices diarios leídos: '.$totalIndicesLeidos);

        // Universo de (empresaAnita, periodo) presentes en cualquiera de las dos tablas.
        $claves = [];
        foreach ($parametros as $clave => $_) {
            $claves[$clave] = true;
        }
        foreach ($indicesPorEmpresaPeriodo as $empAnita => $porPeriodo) {
            foreach ($porPeriodo as $periodo => $_) {
                $claves[$empAnita.'|'.$periodo] = true;
            }
        }

        $creados = 0;
        $actualizados = 0;
        $indicesGrabados = 0;
        $periodosSinEmpresa = [];
        $procesados = 0;

        foreach (array_keys($claves) as $clave) {
            [$empAnita, $periodo] = explode('|', $clave, 2);
            $empAnita = (int) $empAnita;

            if (! isset($mapaEmpresas[$empAnita])) {
                $periodosSinEmpresa[] = $empAnita.'/'.$periodo;

                continue;
            }
            $empresaId = $mapaEmpresas[$empAnita];

            $budgets = $parametros[$clave] ?? null;
            $indices = $indicesPorEmpresaPeriodo[$empAnita][$periodo] ?? [];

            $resultado = $this->upsertParametro($empresaId, $periodo, $budgets, $indices);
            if ($resultado['creado']) {
                $creados++;
            } else {
                $actualizados++;
            }
            $indicesGrabados += $resultado['indices'];
            $procesados++;

            if ($procesados % 25 === 0) {
                $reportar("Procesados {$procesados} períodos...");
            }
        }

        return [
            'parametros_creados' => $creados,
            'parametros_actualizados' => $actualizados,
            'indices' => $indicesGrabados,
            'periodos_sin_empresa' => $periodosSinEmpresa,
            'periodos_procesados' => $procesados,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $budgets
     * @param  list<array<string, mixed>>  $indices
     * @return array{creado: bool, indices: int}
     */
    private function upsertParametro(int $empresaId, string $periodo, ?array $budgets, array $indices): array
    {
        return DB::transaction(function () use ($empresaId, $periodo, $budgets, $indices) {
            $payload = array_merge(
                ['empresa_id' => $empresaId, 'periodo' => $periodo],
                $this->payloadBudgets($budgets, $indices),
            );

            $existente = $this->repository->findPorEmpresaPeriodo($empresaId, $periodo);
            if ($existente === null) {
                $parametro = $this->repository->create($payload);
                $creado = true;
            } else {
                $this->repository->update($payload, $existente->id);
                $parametro = $existente->fresh();
                $creado = false;
            }

            /** @var FlashParametro $parametro */
            $this->repository->sincronizarIndices($parametro, $indices);

            return ['creado' => $creado, 'indices' => count($indices)];
        });
    }

    /**
     * Budgets/totales tal como vienen de Anita. Si no hay fila de paramflash pero sí índices,
     * recalcula los totales season desde los índices para no romper el reporte.
     *
     * @param  array<string, mixed>|null  $budgets
     * @param  list<array<string, mixed>>  $indices
     * @return array<string, float|int>
     */
    private function payloadBudgets(?array $budgets, array $indices): array
    {
        if ($budgets !== null) {
            return $budgets;
        }

        $totales = [
            'total_season' => 0.0,
            'total_sbingo' => 0.0,
            'total_sslot' => 0.0,
            'total_srul' => 0.0,
            'total_spoker' => 0.0,
            'total_s_estac' => 0.0,
        ];
        foreach ($indices as $fila) {
            $totales['total_season'] += (float) ($fila['season_index'] ?? 0);
            $totales['total_sbingo'] += (float) ($fila['sindex_bingo'] ?? 0);
            $totales['total_sslot'] += (float) ($fila['sindex_slot'] ?? 0);
            $totales['total_srul'] += (float) ($fila['sindex_rul'] ?? 0);
            $totales['total_spoker'] += (float) ($fila['sindex_poker'] ?? 0);
            $totales['total_s_estac'] += (float) ($fila['sindex_estac'] ?? 0);
        }

        return array_merge([
            'budget_total' => 0.0,
            'budget_slot' => 0.0,
            'budget_rul' => 0.0,
            'budget_poker' => 0.0,
            'budget_bingo' => 0.0,
            'budget_f_b' => 0.0,
            'budget_pos' => 0,
            'budget_estac' => 0.0,
        ], array_map(static fn ($v) => round((float) $v, 6), $totales));
    }

    /**
     * Lee paramflash → map['<empAnita>|<periodo>'] = budgets.
     *
     * @return array<string, array<string, float|int>>
     */
    private function leerParametros(string $sistema, string $tabla, string $periodoDesde, string $periodoHasta): array
    {
        $campos = implode(', ', [
            'parm_empresa', 'parm_periodo',
            'parm_budget_total', 'parm_budget_slot', 'parm_budget_rul', 'parm_budget_poker',
            'parm_budget_bingo', 'parm_budget_f_b', 'parm_budget_pos', 'parm_budget_estac',
            'parm_total_season', 'parm_total_sbingo', 'parm_total_sslot', 'parm_total_srul',
            'parm_total_spoker', 'parm_total_s_estac',
        ]);

        $where = " WHERE parm_periodo >= '".$periodoDesde."' AND parm_periodo <= '".$periodoHasta."'";

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => 'parm_empresa, parm_periodo',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo leer '.$tabla.' en Anita: '.$parsed['error_lectura']);
        }

        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $empAnita = (int) preg_replace('/\D+/', '', (string) ($fila->parm_empresa ?? ''));
            $periodo = $this->normalizarPeriodo((string) ($fila->parm_periodo ?? ''));
            if ($empAnita <= 0 || $periodo === null) {
                continue;
            }

            $map[$empAnita.'|'.$periodo] = [
                'budget_total' => (float) ($fila->parm_budget_total ?? 0),
                'budget_slot' => (float) ($fila->parm_budget_slot ?? 0),
                'budget_rul' => (float) ($fila->parm_budget_rul ?? 0),
                'budget_poker' => (float) ($fila->parm_budget_poker ?? 0),
                'budget_bingo' => (float) ($fila->parm_budget_bingo ?? 0),
                'budget_f_b' => (float) ($fila->parm_budget_f_b ?? 0),
                'budget_pos' => (int) round((float) ($fila->parm_budget_pos ?? 0)),
                'budget_estac' => (float) ($fila->parm_budget_estac ?? 0),
                'total_season' => (float) ($fila->parm_total_season ?? 0),
                'total_sbingo' => (float) ($fila->parm_total_sbingo ?? 0),
                'total_sslot' => (float) ($fila->parm_total_sslot ?? 0),
                'total_srul' => (float) ($fila->parm_total_srul ?? 0),
                'total_spoker' => (float) ($fila->parm_total_spoker ?? 0),
                'total_s_estac' => (float) ($fila->parm_total_s_estac ?? 0),
            ];
        }

        return $map;
    }

    /**
     * Lee indexflash → map[empAnita][periodo] = list<fila índice ERP>.
     *
     * @return array<int, array<string, list<array<string, mixed>>>>
     */
    private function leerIndices(string $sistema, string $tabla, string $periodoDesde, string $periodoHasta): array
    {
        $fechaDesde = (int) ($periodoDesde.'01');
        $fechaHasta = (int) ($periodoHasta.'31');

        $campos = implode(', ', [
            'indf_empresa', 'indf_fecha', 'indf_customer', 'indf_season_index',
            'indf_sindex_bingo', 'indf_sindex_slot', 'indf_sindex_rul',
            'indf_sindex_poker', 'indf_sindex_estac', 'indf_vehiculos',
        ]);

        $where = ' WHERE indf_fecha >= '.$fechaDesde.' AND indf_fecha <= '.$fechaHasta;

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => 'indf_empresa, indf_fecha',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo leer '.$tabla.' en Anita: '.$parsed['error_lectura']);
        }

        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $empAnita = (int) preg_replace('/\D+/', '', (string) ($fila->indf_empresa ?? ''));
            $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->indf_fecha ?? ''));
            if ($empAnita <= 0 || $fechaEntera < 10000101 || $fechaEntera > 99991231) {
                continue;
            }

            $y = intdiv($fechaEntera, 10000);
            $m = intdiv($fechaEntera % 10000, 100);
            $d = $fechaEntera % 100;
            if ($m < 1 || $m > 12 || $d < 1 || $d > 31 || ! checkdate($m, $d, $y)) {
                continue;
            }

            $periodo = sprintf('%04d%02d', $y, $m);
            $map[$empAnita][$periodo][] = [
                'fecha' => sprintf('%04d-%02d-%02d', $y, $m, $d),
                'customer' => (int) round((float) ($fila->indf_customer ?? 0)),
                'season_index' => (float) ($fila->indf_season_index ?? 0),
                'sindex_bingo' => (float) ($fila->indf_sindex_bingo ?? 0),
                'sindex_slot' => (float) ($fila->indf_sindex_slot ?? 0),
                'sindex_rul' => (float) ($fila->indf_sindex_rul ?? 0),
                'sindex_poker' => (float) ($fila->indf_sindex_poker ?? 0),
                'sindex_estac' => (float) ($fila->indf_sindex_estac ?? 0),
                'vehiculos' => (int) round((float) ($fila->indf_vehiculos ?? 0)),
            ];
        }

        return $map;
    }

    /**
     * Mapa código Anita (empresa.codigo numérico) → empresa_id ERP.
     *
     * @return array<int, int>
     */
    private function mapaEmpresasPorCodigoAnita(): array
    {
        $mapa = [];
        foreach (Empresa::query()->get(['id', 'codigo']) as $empresa) {
            $codigo = trim((string) ($empresa->codigo ?? ''));
            if ($codigo !== '' && ctype_digit($codigo)) {
                $mapa[(int) $codigo] = (int) $empresa->id;
            }
        }

        return $mapa;
    }

    private function normalizarPeriodo(?string $valor): ?string
    {
        $valor = trim((string) $valor);
        if (preg_match('/^\d{6}$/', $valor)) {
            $mes = (int) substr($valor, 4, 2);

            return ($mes >= 1 && $mes <= 12) ? $valor : null;
        }
        if (preg_match('/^(\d{4})-(\d{2})$/', $valor, $m)) {
            $mes = (int) $m[2];

            return ($mes >= 1 && $mes <= 12) ? $m[1].$m[2] : null;
        }

        return null;
    }
}
