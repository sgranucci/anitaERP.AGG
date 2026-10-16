<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Retencionganancia;

/**
 * Propone código de retención de ganancias según actividad principal ARCA.
 */
final class ProveedorActividadRetencionGananciasSupport
{
    /** @var array<string, array{id: int, codigo: string, nombre: string}>|null */
    private static ?array $catalogoPorCodigo = null;

    /**
     * @return array{
     *     codigo: string|null,
     *     nombre: string|null,
     *     retencionganancia_id: int|null,
     *     origen: string,
     *     mensaje: string|null
     * }
     */
    public static function proponerDesdeActividad(?int $idActividad): array
    {
        if ($idActividad === null || $idActividad <= 0) {
            return [
                'codigo' => null,
                'nombre' => null,
                'retencionganancia_id' => null,
                'origen' => 'sin_actividad',
                'mensaje' => 'Sin actividad principal en constancia ARCA.',
            ];
        }

        $mapa = config('compras_control_ganancias.actividad_retencion_codigo', []);
        if (! is_array($mapa) || $mapa === []) {
            return [
                'codigo' => null,
                'nombre' => null,
                'retencionganancia_id' => null,
                'origen' => 'sin_mapeo',
                'mensaje' => 'No hay mapeo actividad→retención (config/compras_control_ganancias.php).',
            ];
        }

        $codigoMapa = null;
        if (array_key_exists($idActividad, $mapa)) {
            $codigoMapa = $mapa[$idActividad];
        } elseif (array_key_exists((string) $idActividad, $mapa)) {
            $codigoMapa = $mapa[(string) $idActividad];
        }

        if ($codigoMapa === null || trim((string) $codigoMapa) === '') {
            return [
                'codigo' => null,
                'nombre' => null,
                'retencionganancia_id' => null,
                'origen' => 'actividad_sin_mapeo',
                'mensaje' => 'Actividad '.$idActividad.' sin código de retención mapeado.',
            ];
        }

        $codigo = trim((string) $codigoMapa);
        $fila = self::catalogoPorCodigo()[$codigo] ?? null;
        if ($fila === null) {
            return [
                'codigo' => $codigo,
                'nombre' => null,
                'retencionganancia_id' => null,
                'origen' => 'codigo_inexistente',
                'mensaje' => 'Código propuesto '.$codigo.' no existe en maestro retencionganancia.',
            ];
        }

        return [
            'codigo' => $fila['codigo'],
            'nombre' => $fila['nombre'],
            'retencionganancia_id' => $fila['id'],
            'origen' => 'mapeo',
            'mensaje' => null,
        ];
    }

    /**
     * Propuesta fija para monotributistas: no aplica retención (código 0).
     *
     * @return array{
     *     codigo: string|null,
     *     nombre: string|null,
     *     retencionganancia_id: int|null,
     *     origen: string,
     *     mensaje: string|null
     * }
     */
    public static function proponerSinRetencion(): array
    {
        $ids = ProveedorImpuestosRetencionRules::idsSinCodigo();
        $id = $ids['retencionganancia_id'] ?? null;
        if ($id === null) {
            return [
                'codigo' => ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION,
                'nombre' => ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA,
                'retencionganancia_id' => null,
                'origen' => 'sin_retencion',
                'mensaje' => 'No se encontró maestro "sin retención" (código 0).',
            ];
        }

        $row = Retencionganancia::query()->whereKey($id)->first(['id', 'codigo', 'nombre']);

        return [
            'codigo' => $row ? (string) $row->codigo : ProveedorImpuestosRetencionRules::CODIGO_SIN_RETENCION,
            'nombre' => $row ? (string) $row->nombre : ProveedorImpuestosRetencionRules::NOMBRE_SIN_CODIGO_GANANCIA,
            'retencionganancia_id' => (int) $id,
            'origen' => 'sin_retencion',
            'mensaje' => null,
        ];
    }

    /**
     * @return array<string, array{id: int, codigo: string, nombre: string}>
     */
    private static function catalogoPorCodigo(): array
    {
        if (self::$catalogoPorCodigo !== null) {
            return self::$catalogoPorCodigo;
        }

        $out = [];
        foreach (Retencionganancia::query()->get(['id', 'codigo', 'nombre']) as $row) {
            $codigo = trim((string) $row->codigo);
            if ($codigo === '') {
                continue;
            }
            $out[$codigo] = [
                'id' => (int) $row->id,
                'codigo' => $codigo,
                'nombre' => (string) $row->nombre,
            ];
        }

        return self::$catalogoPorCodigo = $out;
    }

    /** @internal tests */
    public static function resetCatalogoCache(): void
    {
        self::$catalogoPorCodigo = null;
    }
}
