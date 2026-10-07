<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\Canal;
use Illuminate\Http\Request;

/**
 * Filtros del reporte de costos del local (catálogo de SKU).
 */
final class FacturacionLocalCostosLocalReporteFiltros
{
    public const CANAL_TODOS = 'TODOS';

    public const ESTADO_ACTIVOS = 'ACTIVOS';

    public const ESTADO_INACTIVOS = 'INACTIVOS';

    public const ESTADO_TODOS = 'TODOS';

    /**
     * @return array{
     *   mventa_id:int,
     *   mventa_codigo:string,
     *   mventa_nombre:string,
     *   canal:string,
     *   estado:string,
     *   solo_precio_cero:bool
     * }
     */
    public static function resolverDesdeRequest(Request $request): array
    {
        $canalInput = $request->input('filtro_canal', null);
        if ($canalInput === null || trim((string) $canalInput) === '') {
            $canal = Canal::CODIGO_LOCAL;
        } else {
            $canal = strtoupper(trim((string) $canalInput));
            if (! in_array($canal, [self::CANAL_TODOS, Canal::CODIGO_FABRICA, Canal::CODIGO_LOCAL], true)) {
                $canal = Canal::CODIGO_LOCAL;
            }
        }

        $estado = strtoupper(trim((string) $request->input('filtro_estado', self::ESTADO_ACTIVOS)));
        if (! in_array($estado, [self::ESTADO_ACTIVOS, self::ESTADO_INACTIVOS, self::ESTADO_TODOS], true)) {
            $estado = self::ESTADO_ACTIVOS;
        }

        return [
            'mventa_id' => max(0, (int) $request->input('mventa_id', 0)),
            'mventa_codigo' => trim((string) $request->input('mventa_codigo', '')),
            'mventa_nombre' => trim((string) $request->input('mventa_nombre', '')),
            'canal' => $canal,
            'estado' => $estado,
            'solo_precio_cero' => $request->boolean('solo_precio_cero'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public static function paraQueryString(array $filtros): array
    {
        $out = [
            'filtro_canal' => (string) ($filtros['canal'] ?? self::CANAL_TODOS),
            'filtro_estado' => (string) ($filtros['estado'] ?? self::ESTADO_ACTIVOS),
        ];
        $mventaId = (int) ($filtros['mventa_id'] ?? 0);
        if ($mventaId > 0) {
            $out['mventa_id'] = $mventaId;
        }
        if (! empty($filtros['solo_precio_cero'])) {
            $out['solo_precio_cero'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function textoFiltros(array $filtros): string
    {
        $partes = [
            'Marca: '.self::etiquetaMarca($filtros),
            'Canal: '.self::etiquetaCanal((string) ($filtros['canal'] ?? self::CANAL_TODOS)),
            'SKU: '.self::etiquetaEstado((string) ($filtros['estado'] ?? self::ESTADO_ACTIVOS)),
        ];
        if (! empty($filtros['solo_precio_cero'])) {
            $partes[] = 'Solo precio en 0';
        }

        return implode(' · ', $partes);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public static function etiquetaMarca(array $filtros): string
    {
        $nombre = trim((string) ($filtros['mventa_nombre'] ?? ''));
        $codigo = trim((string) ($filtros['mventa_codigo'] ?? ''));
        if ((int) ($filtros['mventa_id'] ?? 0) <= 0) {
            return 'Todas';
        }
        $texto = trim($codigo.' '.$nombre);

        return $texto !== '' ? $texto : 'Marca #'.(int) $filtros['mventa_id'];
    }

    public static function etiquetaCanal(string $canal): string
    {
        return match (strtoupper($canal)) {
            Canal::CODIGO_FABRICA => 'Fábrica',
            Canal::CODIGO_LOCAL => 'Local',
            default => 'Fábrica y local',
        };
    }

    public static function etiquetaEstado(string $estado): string
    {
        return match (strtoupper($estado)) {
            self::ESTADO_INACTIVOS => 'Inactivos',
            self::ESTADO_TODOS => 'Activos e inactivos',
            default => 'Activos',
        };
    }
}
