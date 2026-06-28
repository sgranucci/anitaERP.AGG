<?php

declare(strict_types=1);

namespace App\Support\Ventas\Gastronomia;

/**
 * Estados de conciliación ERP ↔ cabecera venta Anita ↔ rendgastro (por PC y totales).
 */
final class GastronomiaConciliacionEstadoSupport
{
    /**
     * @return array{estado: string, estado_anita: string, estado_rendg: string}
     */
    public static function resolverDetallado(
        float $diffErpAnita,
        ?float $diffErpRendg,
        float $tolerancia,
        bool $jornadaAbierta = false,
        float $ventasErp = 0.0,
    ): array {
        if ($ventasErp <= $tolerancia && abs($diffErpAnita) <= $tolerancia) {
            return [
                'estado' => '—',
                'estado_anita' => '—',
                'estado_rendg' => '—',
            ];
        }

        $okAnita = abs($diffErpAnita) <= $tolerancia;
        $estadoAnita = $okAnita ? 'OK' : 'DIF';

        if ($jornadaAbierta) {
            return [
                'estado' => $okAnita ? 'OK' : 'DIF venta',
                'estado_anita' => $estadoAnita,
                'estado_rendg' => '—',
            ];
        }

        if ($diffErpRendg === null) {
            if ($ventasErp <= $tolerancia) {
                return [
                    'estado' => $okAnita ? '—' : 'DIF venta',
                    'estado_anita' => $estadoAnita,
                    'estado_rendg' => '—',
                ];
            }

            return [
                'estado' => $okAnita ? 'SIN RENDG' : 'DIF venta',
                'estado_anita' => $estadoAnita,
                'estado_rendg' => 'SIN RENDG',
            ];
        }

        $okRendg = abs($diffErpRendg) <= $tolerancia;
        $estadoRendg = $okRendg ? 'OK' : 'DIF';

        if ($okAnita && $okRendg) {
            return [
                'estado' => 'OK',
                'estado_anita' => 'OK',
                'estado_rendg' => 'OK',
            ];
        }

        if (! $okAnita && ! $okRendg) {
            return [
                'estado' => 'DIF ambos',
                'estado_anita' => 'DIF',
                'estado_rendg' => 'DIF',
            ];
        }

        if (! $okAnita) {
            return [
                'estado' => 'DIF venta',
                'estado_anita' => 'DIF',
                'estado_rendg' => 'OK',
            ];
        }

        return [
            'estado' => 'DIF rendg',
            'estado_anita' => 'OK',
            'estado_rendg' => 'DIF',
        ];
    }

    public static function resolver(
        float $diffErpAnita,
        ?float $diffErpRendg,
        float $tolerancia,
        bool $jornadaAbierta = false,
        float $ventasErp = 0.0,
    ): string {
        return self::resolverDetallado(
            $diffErpAnita,
            $diffErpRendg,
            $tolerancia,
            $jornadaAbierta,
            $ventasErp,
        )['estado'];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array<string, mixed>
     */
    public static function aplicarEstadosEnFila(array $fila, float $tolerancia): array
    {
        $estados = self::resolverDetallado(
            (float) ($fila['diff_erp_anita'] ?? 0),
            isset($fila['diff_erp_rendg']) ? (float) $fila['diff_erp_rendg'] : null,
            $tolerancia,
            (bool) ($fila['jornada_abierta'] ?? false),
            (float) ($fila['ventas_erp'] ?? 0),
        );

        $fila['estado'] = $estados['estado'];
        $fila['estado_anita'] = $estados['estado_anita'];
        $fila['estado_rendg'] = $estados['estado_rendg'];

        return $fila;
    }

    public static function requiereAlerta(string $estadoAnita, string $estadoRendg): bool
    {
        if ($estadoAnita === 'DIF') {
            return true;
        }

        return in_array($estadoRendg, ['DIF', 'SIN RENDG'], true);
    }
}
