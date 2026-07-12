<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

use App\ApiAnita;
use Carbon\Carbon;

/**
 * Totales diarios Informix flash (caja): Σ flash_ayb + flash_estac por empresa y jornada.
 */
final class GastronomiaControlFlashSupport
{
    /**
     * @param  list<int>  $empresaCodigos  Código Anita (flash_empresa), alineado con empresa.codigo ERP.
     * @return array<int, array<string, float>> codigo empresa => Y-m-d => total
     */
    public function totalesPorEmpresaJornada(string $fechaDesde, string $fechaHasta, array $empresaCodigos): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $desdeEntera = (int) str_replace('-', '', $desde);
        $hastaEntera = (int) str_replace('-', '', $hasta);

        $codigos = array_values(array_unique(array_filter(array_map(
            static fn ($c): int => (int) $c,
            $empresaCodigos,
        ), static fn (int $c): bool => $c > 0)));

        if ($codigos === []) {
            return [];
        }

        $where = ' WHERE flash_fecha >= \''.$desdeEntera.'\''
            .' AND flash_fecha <= \''.$hastaEntera.'\''
            .' AND flash_empresa IN ('.implode(',', $codigos).')';

        $sistema = trim((string) config('gastronomia.control_ctamov_rendg_dia_anita.flash_sistema', 'caja'));
        if ($sistema === '') {
            $sistema = 'caja';
        }

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'flash',
            'campos' => 'flash_empresa, flash_fecha, flash_sala, flash_ayb, flash_estac',
            'whereArmado' => $where,
            'orderBy' => 'flash_empresa, flash_fecha, flash_sala',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar flash Anita (caja): '.$parsed['error_lectura']);
        }

        /** @var array<int, array<string, float>> $map */
        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $empresa = (int) preg_replace('/\D+/', '', (string) ($fila->flash_empresa ?? ''));
            $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->flash_fecha ?? ''));
            if ($empresa <= 0 || $fechaEntera <= 0) {
                continue;
            }

            $fecha = substr((string) $fechaEntera, 0, 4).'-'
                .substr((string) $fechaEntera, 4, 2).'-'
                .substr((string) $fechaEntera, 6, 2);

            $neto = round((float) ($fila->flash_ayb ?? 0) + (float) ($fila->flash_estac ?? 0), 2);
            $map[$empresa][$fecha] = round(($map[$empresa][$fecha] ?? 0) + $neto, 2);
        }

        return $map;
    }

    /**
     * Desglose flash_ayb / flash_estac por empresa y jornada (suma salas).
     *
     * @param  list<int>  $empresaCodigos
     * @return array<int, array<string, array{flash_ayb: float, flash_estac: float}>>
     */
    public function desglosePorEmpresaJornada(string $fechaDesde, string $fechaHasta, array $empresaCodigos): array
    {
        $desde = Carbon::parse($fechaDesde)->toDateString();
        $hasta = Carbon::parse($fechaHasta)->toDateString();
        $desdeEntera = (int) str_replace('-', '', $desde);
        $hastaEntera = (int) str_replace('-', '', $hasta);

        $codigos = array_values(array_unique(array_filter(array_map(
            static fn ($c): int => (int) $c,
            $empresaCodigos,
        ), static fn (int $c): bool => $c > 0)));

        if ($codigos === []) {
            return [];
        }

        $where = ' WHERE flash_fecha >= \''.$desdeEntera.'\''
            .' AND flash_fecha <= \''.$hastaEntera.'\''
            .' AND flash_empresa IN ('.implode(',', $codigos).')';

        $sistema = trim((string) config('gastronomia.control_ctamov_rendg_dia_anita.flash_sistema', 'caja'));
        if ($sistema === '') {
            $sistema = 'caja';
        }

        $parsed = ApiAnita::parsearRespuestaLista((new ApiAnita)->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => 'flash',
            'campos' => 'flash_empresa, flash_fecha, flash_sala, flash_ayb, flash_estac',
            'whereArmado' => $where,
            'orderBy' => 'flash_empresa, flash_fecha, flash_sala',
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new \RuntimeException('No se pudo listar flash Anita (caja): '.$parsed['error_lectura']);
        }

        /** @var array<int, array<string, array{flash_ayb: float, flash_estac: float}>> $map */
        $map = [];
        foreach ($parsed['filas'] as $fila) {
            $empresa = (int) preg_replace('/\D+/', '', (string) ($fila->flash_empresa ?? ''));
            $fechaEntera = (int) preg_replace('/\D+/', '', (string) ($fila->flash_fecha ?? ''));
            if ($empresa <= 0 || $fechaEntera <= 0) {
                continue;
            }

            $fecha = substr((string) $fechaEntera, 0, 4).'-'
                .substr((string) $fechaEntera, 4, 2).'-'
                .substr((string) $fechaEntera, 6, 2);

            $ayb = round((float) ($fila->flash_ayb ?? 0), 2);
            $estac = round((float) ($fila->flash_estac ?? 0), 2);
            $map[$empresa][$fecha]['flash_ayb'] = round(($map[$empresa][$fecha]['flash_ayb'] ?? 0) + $ayb, 2);
            $map[$empresa][$fecha]['flash_estac'] = round(($map[$empresa][$fecha]['flash_estac'] ?? 0) + $estac, 2);
        }

        return $map;
    }
}
