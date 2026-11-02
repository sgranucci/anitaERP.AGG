<?php

declare(strict_types=1);

namespace App\Support\Ventas\TransferenciaCrown;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tipos con los que entra cada código AFIP del archivo de Mis Comprobantes.
 * La letra sigue saliendo del código. Lo que se elige es el comprobante del ERP, y queda por usuario.
 */
final class TransferenciaCrownArcaTipos
{
    /**
     * Enum cerrado. No sale de un listado de la tabla.
     *
     * @var array<string, string>
     */
    public const OPCIONES = [
        'FAC' => 'Factura',
        'FAH' => 'Factura hotel',
        'TIC' => 'Ticket factura',
        'NDV' => 'Nota de débito',
        'NDH' => 'Nota de débito hotel',
        'NCD' => 'Nota de crédito',
        'NCH' => 'Nota de crédito hotel',
    ];

    /**
     * @var array<int, array{titulo: string, letra: string, defecto: string}>
     */
    public const CODIGOS = [
        1 => ['titulo' => 'Factura A', 'letra' => 'A', 'defecto' => 'FAC'],
        2 => ['titulo' => 'Nota de débito A', 'letra' => 'A', 'defecto' => 'NDV'],
        3 => ['titulo' => 'Nota de crédito A', 'letra' => 'A', 'defecto' => 'NCD'],
        6 => ['titulo' => 'Factura B', 'letra' => 'B', 'defecto' => 'FAC'],
        7 => ['titulo' => 'Nota de débito B', 'letra' => 'B', 'defecto' => 'NDV'],
        8 => ['titulo' => 'Nota de crédito B', 'letra' => 'B', 'defecto' => 'NCD'],
        195 => ['titulo' => 'Factura T', 'letra' => 'T', 'defecto' => 'FAC'],
        196 => ['titulo' => 'Nota de débito T', 'letra' => 'T', 'defecto' => 'NDV'],
        197 => ['titulo' => 'Nota de crédito T', 'letra' => 'T', 'defecto' => 'NCD'],
    ];

    /**
     * @param  array<int|string, mixed>  $pedido
     * @return array<int, string>
     */
    public static function resolver(int $usuarioId, array $pedido = []): array
    {
        $guardado = self::leer($usuarioId);
        $out = [];
        foreach (self::CODIGOS as $codigo => $meta) {
            $elegido = strtoupper(trim((string) ($pedido[$codigo] ?? $pedido[(string) $codigo] ?? '')));
            if (! isset(self::OPCIONES[$elegido])) {
                $elegido = $guardado[$codigo] ?? $meta['defecto'];
            }
            $out[$codigo] = $elegido;
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $mapeo
     */
    public static function recordar(int $usuarioId, array $mapeo): void
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('transferencia_crown_arca_tipo')) {
            return;
        }

        foreach (self::CODIGOS as $codigo => $meta) {
            $abreviatura = $mapeo[$codigo] ?? $meta['defecto'];
            if (! isset(self::OPCIONES[$abreviatura])) {
                $abreviatura = $meta['defecto'];
            }
            $ya = DB::table('transferencia_crown_arca_tipo')
                ->where('usuario_id', $usuarioId)
                ->where('codigo_afip', $codigo)
                ->exists();
            if ($ya) {
                DB::table('transferencia_crown_arca_tipo')
                    ->where('usuario_id', $usuarioId)
                    ->where('codigo_afip', $codigo)
                    ->update(['abreviatura' => $abreviatura, 'updated_at' => now()]);
            } else {
                DB::table('transferencia_crown_arca_tipo')->insert([
                    'usuario_id' => $usuarioId,
                    'codigo_afip' => $codigo,
                    'abreviatura' => $abreviatura,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public static function defecto(int $codigoAfip): string
    {
        return self::CODIGOS[$codigoAfip]['defecto'] ?? 'FAC';
    }

    /**
     * @return array<int, string>
     */
    private static function leer(int $usuarioId): array
    {
        if ($usuarioId <= 0 || ! Schema::hasTable('transferencia_crown_arca_tipo')) {
            return [];
        }

        $out = [];
        $filas = DB::table('transferencia_crown_arca_tipo')
            ->where('usuario_id', $usuarioId)
            ->get(['codigo_afip', 'abreviatura']);
        foreach ($filas as $fila) {
            $codigo = (int) $fila->codigo_afip;
            $abreviatura = strtoupper(trim((string) $fila->abreviatura));
            if (isset(self::CODIGOS[$codigo], self::OPCIONES[$abreviatura])) {
                $out[$codigo] = $abreviatura;
            }
        }

        return $out;
    }
}
