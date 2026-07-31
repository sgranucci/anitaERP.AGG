<?php

declare(strict_types=1);

namespace App\Support\Contable\CcVsMayorAnita;

use Illuminate\Http\Request;

final class CcVsMayorAnitaListadoFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $fecha = trim((string) $request->input('fecha', date('Y-m-d', strtotime('-1 day'))));
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            $fecha = date('Y-m-d', strtotime('-1 day'));
        }

        $cuenta = (int) preg_replace('/\D/', '', (string) $request->input(
            'cuenta_codigo',
            (string) config('cliente.DEUDORES_POR_VENTAS'),
        ));
        if ($cuenta <= 0) {
            $cuenta = (int) config('cliente.DEUDORES_POR_VENTAS');
        }

        $sistemaSubdiario = strtolower(trim((string) $request->input(
            'sistema_subdiario',
            (string) config('anita.subdiario_sistema', 'ventas'),
        )));
        if (! in_array($sistemaSubdiario, ['ventas', 'contab'], true)) {
            $sistemaSubdiario = (string) config('anita.subdiario_sistema', 'ventas');
        }

        $soloDiferencias = $request->boolean('solo_diferencias', true);
        $tolerancia = (float) str_replace(',', '.', (string) $request->input('tolerancia', '0.05'));
        if ($tolerancia < 0) {
            $tolerancia = 0.05;
        }

        return [
            'fecha' => $fecha,
            'cuenta_codigo' => $cuenta,
            'sistema_subdiario' => $sistemaSubdiario,
            'solo_diferencias' => $soloDiferencias,
            'tolerancia' => $tolerancia,
            'consultar' => $request->boolean('consultar') ? 1 : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        return trim((string) ($filtros['fecha'] ?? '')) !== ''
            && (int) ($filtros['cuenta_codigo'] ?? 0) > 0;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        return array_filter([
            'fecha' => (string) ($filtros['fecha'] ?? ''),
            'cuenta_codigo' => (int) ($filtros['cuenta_codigo'] ?? 0),
            'sistema_subdiario' => (string) ($filtros['sistema_subdiario'] ?? 'ventas'),
            'solo_diferencias' => ! empty($filtros['solo_diferencias']) ? 1 : 0,
            'tolerancia' => (string) ($filtros['tolerancia'] ?? '0.05'),
            'consultar' => 1,
        ], static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function fechaYmd(array $filtros): int
    {
        $fecha = (string) ($filtros['fecha'] ?? '');

        return (int) str_replace('-', '', $fecha);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function firma(array $filtros): string
    {
        return sha1(json_encode([
            'fecha' => (string) ($filtros['fecha'] ?? ''),
            'cuenta' => (int) ($filtros['cuenta_codigo'] ?? 0),
            'sistema' => (string) ($filtros['sistema_subdiario'] ?? ''),
            'tol' => (float) ($filtros['tolerancia'] ?? 0.05),
        ], JSON_THROW_ON_ERROR));
    }
}
