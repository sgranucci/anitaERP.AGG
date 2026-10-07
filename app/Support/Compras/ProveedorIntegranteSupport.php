<?php

namespace App\Support\Compras;

use App\Models\Compras\Proveedor_Integrante;
use App\Support\Database\EloquentAuditDeleteSupport;

/**
 * Integrantes del condominio en el padrón del proveedor (un solo proveedor).
 */
final class ProveedorIntegranteSupport
{
    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $filas
     */
    public static function sincronizar(int $proveedorId, array $filas): void
    {
        EloquentAuditDeleteSupport::each(
            Proveedor_Integrante::query()->where('proveedor_id', $proveedorId)
        );

        $orden = 0;
        foreach (self::filasValidas($filas) as $fila) {
            $orden++;
            Proveedor_Integrante::query()->create([
                'proveedor_id' => $proveedorId,
                'orden' => $orden,
                'nombre' => mb_substr(trim((string) $fila['nombre']), 0, 60),
                'cuit' => Proveedor_Integrante::formatearCuit((string) $fila['cuit']),
                'porcentaje' => round((float) $fila['porcentaje'], 4),
                'inscripto' => strtoupper(trim((string) ($fila['inscripto'] ?? 'S'))) === 'N' ? 'N' : 'S',
            ]);
        }
    }

    /**
     * @param  list<array<string, mixed>>|array<int, mixed>  $filas
     * @return list<array{nombre: string, cuit: string, porcentaje: float, inscripto: string}>
     */
    public static function filasValidas(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $nombre = trim((string) ($fila['nombre'] ?? ''));
            $cuit = trim((string) ($fila['cuit'] ?? ''));
            $porcentajeRaw = str_replace(',', '.', trim((string) ($fila['porcentaje'] ?? '')));
            if ($nombre === '' && $cuit === '' && $porcentajeRaw === '') {
                continue;
            }
            $out[] = [
                'nombre' => $nombre,
                'cuit' => $cuit,
                'porcentaje' => is_numeric($porcentajeRaw) ? (float) $porcentajeRaw : 0.0,
                'inscripto' => strtoupper(trim((string) ($fila['inscripto'] ?? 'S'))) === 'N' ? 'N' : 'S',
            ];
        }

        return $out;
    }
}
