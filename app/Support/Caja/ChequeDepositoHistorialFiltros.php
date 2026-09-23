<?php

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del historial de boletas de depósito CHT (agrupado).
 */
final class ChequeDepositoHistorialFiltros
{
    /**
     * @return array{
     *   empresa_id:?int,
     *   cuentacaja_id:?int,
     *   estado:string,
     *   boleta:string,
     *   texto:string,
     *   desde:string,
     *   hasta:string
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $empresaId = (int) $request->input('empresa_id', 0);
        $cuentaId = (int) $request->input('cuentacaja_id', 0);
        $estado = trim((string) $request->input('estado', ''));
        if ($estado !== '' && ! in_array($estado, ChequeDepositoHistorialSupport::ESTADOS, true)) {
            $estado = '';
        }

        $desde = trim((string) $request->input('desde', ''));
        if ($desde !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = '';
        }
        $hasta = trim((string) $request->input('hasta', ''));
        if ($hasta !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = '';
        }

        // Primera visita (sin query): mes en curso. Si el form envía desde/hasta vacíos, sin límite de fechas.
        $queryParams = $request->query();
        unset($queryParams['page']);
        if ($queryParams === []) {
            $desde = date('Y-m-01');
            $hasta = date('Y-m-d');
        }

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'cuentacaja_id' => $cuentaId > 0 ? $cuentaId : null,
            'estado' => $estado,
            'boleta' => trim((string) $request->input('boleta', '')),
            'texto' => trim((string) $request->input('texto', '')),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [];
        foreach (['empresa_id', 'cuentacaja_id'] as $k) {
            if ((int) ($filtros[$k] ?? 0) > 0) {
                $out[$k] = (int) $filtros[$k];
            }
        }
        foreach (['estado', 'boleta', 'texto', 'desde', 'hasta'] as $k) {
            if (($filtros[$k] ?? '') !== '') {
                $out[$k] = (string) $filtros[$k];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function tieneCriteriosAplicados(array $filtros): bool
    {
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            return true;
        }
        if ((int) ($filtros['cuentacaja_id'] ?? 0) > 0) {
            return true;
        }
        foreach (['estado', 'boleta', 'texto', 'desde', 'hasta'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * Subtítulo legible para PDF/Excel.
     *
     * @param  array<string, mixed>  $filtros
     */
    public static function subtitulo(array $filtros): string
    {
        $partes = [];
        if (! empty($filtros['desde']) || ! empty($filtros['hasta'])) {
            $partes[] = 'Depósito '
                .(! empty($filtros['desde']) ? $filtros['desde'] : '…')
                .' → '
                .(! empty($filtros['hasta']) ? $filtros['hasta'] : '…');
        }
        if (! empty($filtros['boleta'])) {
            $partes[] = 'Boleta '.$filtros['boleta'];
        }
        if (! empty($filtros['estado'])) {
            $labels = [
                'transito' => 'En tránsito',
                'acreditado' => 'Acreditados',
                'rechazado' => 'Con rechazos',
                'mixto' => 'Mixtos',
            ];
            $partes[] = 'Estado '.($labels[$filtros['estado']] ?? $filtros['estado']);
        }
        if (! empty($filtros['texto'])) {
            $partes[] = 'Texto «'.$filtros['texto'].'»';
        }

        return implode(' · ', $partes);
    }
}
