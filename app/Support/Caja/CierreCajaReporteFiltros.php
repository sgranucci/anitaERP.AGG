<?php

declare(strict_types=1);

namespace App\Support\Caja;

use Illuminate\Http\Request;

/**
 * Filtros del informe de cierre de caja diaria (Anita l-ciecaja.c).
 */
final class CierreCajaReporteFiltros
{
    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => date('Y-m-d'),
            'fecha_hasta' => date('Y-m-d'),
            'cuentacaja_id' => 0,
            'incluir_sin_movimiento' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $base = self::filtrosVacios();

        $empresaIds = collect($request->input('empresa_ids', []))
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $desde = (string) $request->input('fecha_desde', $base['fecha_desde']);
        $hasta = (string) $request->input('fecha_hasta', $base['fecha_hasta']);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = $base['fecha_desde'];
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $base['fecha_hasta'];
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'empresa_ids' => $empresaIds,
            'consolidar_empresas' => $request->input('consolidar_empresas', '1') === '1'
                || $request->boolean('consolidar_empresas'),
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'cuentacaja_id' => max(0, (int) $request->input('cuentacaja_id', 0)),
            'incluir_sin_movimiento' => $request->boolean('incluir_sin_movimiento'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros, bool $consultar = false): array
    {
        $out = [
            'fecha_desde' => (string) ($filtros['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($filtros['fecha_hasta'] ?? ''),
            'cuentacaja_id' => (int) ($filtros['cuentacaja_id'] ?? 0),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']) ? '1' : '0',
            'incluir_sin_movimiento' => ! empty($filtros['incluir_sin_movimiento']) ? '1' : '0',
        ];
        if ((int) $out['cuentacaja_id'] <= 0) {
            unset($out['cuentacaja_id']);
        }
        if ((string) $out['incluir_sin_movimiento'] !== '1') {
            unset($out['incluir_sin_movimiento']);
        }
        $empresaIds = array_values(array_map('intval', $filtros['empresa_ids'] ?? []));
        if ($empresaIds !== []) {
            $out['empresa_ids'] = $empresaIds;
        }
        if ($consultar) {
            $out['consultar'] = 1;
        }

        return array_filter($out, static fn ($v) => $v !== null && $v !== '');
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @param  list<string>  $nombresEmpresa
     */
    public static function subtitulo(array $filtros, array $nombresEmpresa): string
    {
        $partes = ['Cierre de caja'];
        $desde = self::fechaDmy((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = self::fechaDmy((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $partes[] = $desde === $hasta
                ? 'Fecha '.$desde
                : 'Desde '.$desde.' hasta '.$hasta;
        }
        if ($nombresEmpresa !== []) {
            $partes[] = count($nombresEmpresa) === 1
                ? 'Empresa: '.$nombresEmpresa[0]
                : 'Empresas: '.implode(', ', $nombresEmpresa);
        }

        return implode(' · ', $partes);
    }

    private static function fechaDmy(string $ymd): string
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
            return '';
        }

        return $m[3].'/'.$m[2].'/'.$m[1];
    }
}
