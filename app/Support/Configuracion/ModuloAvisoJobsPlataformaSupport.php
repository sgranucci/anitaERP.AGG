<?php

namespace App\Support\Configuracion;

/**
 * Catálogo read-only de jobs de Kernel para el cockpit del Programador.
 */
final class ModuloAvisoJobsPlataformaSupport
{
    /**
     * @return list<array{comando: string, etiqueta: string, horario: string, grupo: string}>
     */
    public static function listar(): array
    {
        $jobs = config('modulo_aviso_jobs_plataforma.jobs', []);
        if (! is_array($jobs)) {
            return [];
        }
        $out = [];
        foreach ($jobs as $job) {
            if (! is_array($job)) {
                continue;
            }
            $comando = trim((string) ($job['comando'] ?? ''));
            if ($comando === '') {
                continue;
            }
            $out[] = [
                'comando' => $comando,
                'etiqueta' => (string) ($job['etiqueta'] ?? $comando),
                'horario' => (string) ($job['horario'] ?? '—'),
                'grupo' => (string) ($job['grupo'] ?? 'otros'),
            ];
        }

        return $out;
    }

    /**
     * @return array{total: int, por_grupo: array<string, int>, filas: list<array<string, mixed>>}
     */
    public static function resumenCockpit(): array
    {
        $filas = self::listar();
        $porGrupo = [];
        foreach ($filas as $f) {
            $g = $f['grupo'];
            $porGrupo[$g] = ($porGrupo[$g] ?? 0) + 1;
        }

        return [
            'total' => count($filas),
            'por_grupo' => $porGrupo,
            'filas' => $filas,
        ];
    }
}
