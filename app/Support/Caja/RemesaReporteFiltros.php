<?php

declare(strict_types=1);

namespace App\Support\Caja;

use App\Support\Caja\Remesa\RemesaSupport;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de remesas por cuenta de caja.
 */
final class RemesaReporteFiltros
{
    public const FUENTE_TODAS = 'todas';

    public const FUENTE_ERP = 'erp';

    public const FUENTE_ANITA = 'anita';

    public const TIPO_TODAS = '';

    /**
     * @return array<string, mixed>
     */
    public static function filtrosVacios(): array
    {
        return [
            'empresa_ids' => [],
            'consolidar_empresas' => true,
            'fecha_desde' => date('Y-m-01'),
            'fecha_hasta' => date('Y-m-d'),
            'cuentacaja_id' => 0,
            'tipo' => RemesaSupport::TIPO_EXTERNA,
            'fuente' => self::FUENTE_TODAS,
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

        $tipo = (string) $request->input('tipo', $base['tipo']);
        if (! in_array($tipo, [RemesaSupport::TIPO_EXTERNA, RemesaSupport::TIPO_INTERNA, self::TIPO_TODAS], true)) {
            $tipo = RemesaSupport::TIPO_EXTERNA;
        }

        $fuente = (string) $request->input('fuente', $base['fuente']);
        if (! in_array($fuente, [self::FUENTE_TODAS, self::FUENTE_ERP, self::FUENTE_ANITA], true)) {
            $fuente = self::FUENTE_TODAS;
        }

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
            'tipo' => $tipo,
            'fuente' => $fuente,
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
            'tipo' => (string) ($filtros['tipo'] ?? RemesaSupport::TIPO_EXTERNA),
            'fuente' => (string) ($filtros['fuente'] ?? self::FUENTE_TODAS),
            'consolidar_empresas' => ! empty($filtros['consolidar_empresas']) ? '1' : '0',
        ];
        if ((int) $out['cuentacaja_id'] <= 0) {
            unset($out['cuentacaja_id']);
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
        $partes = ['Por cuenta de caja'];
        $desde = self::fechaDmy((string) ($filtros['fecha_desde'] ?? ''));
        $hasta = self::fechaDmy((string) ($filtros['fecha_hasta'] ?? ''));
        if ($desde !== '' && $hasta !== '') {
            $partes[] = 'Desde '.$desde.' hasta '.$hasta;
        }
        if ($nombresEmpresa !== []) {
            $partes[] = count($nombresEmpresa) === 1
                ? 'Empresa: '.$nombresEmpresa[0]
                : 'Empresas: '.implode(', ', $nombresEmpresa);
        }
        $tipo = (string) ($filtros['tipo'] ?? '');
        $partes[] = match ($tipo) {
            RemesaSupport::TIPO_INTERNA => 'Internas',
            RemesaSupport::TIPO_EXTERNA => 'Externas',
            default => 'Todos los tipos',
        };
        $fuente = (string) ($filtros['fuente'] ?? self::FUENTE_TODAS);
        if ($fuente === self::FUENTE_ERP) {
            $partes[] = 'Solo ERP';
        } elseif ($fuente === self::FUENTE_ANITA) {
            $partes[] = 'Solo Anita';
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
