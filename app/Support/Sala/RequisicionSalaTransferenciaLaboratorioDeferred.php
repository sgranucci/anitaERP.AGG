<?php

namespace App\Support\Sala;

use App\Services\Sala\RequisicionSalaTransferenciaLaboratorioService;
use Illuminate\Support\Facades\Log;

/**
 * Encola transferencias TM→laboratorio para ejecutarlas tras commit de la transacción del árbol.
 */
final class RequisicionSalaTransferenciaLaboratorioDeferred
{
    /** @var list<array{id: int, usuario_id: int}> */
    private static array $pendientes = [];

    public static function encolar(int $requisicionSalaId, int $usuarioId): void
    {
        if ($requisicionSalaId <= 0) {
            return;
        }
        foreach (self::$pendientes as $item) {
            if ($item['id'] === $requisicionSalaId) {
                return;
            }
        }
        self::$pendientes[] = [
            'id' => $requisicionSalaId,
            'usuario_id' => max(0, $usuarioId),
        ];
    }

    /**
     * @return list<array{ok: bool, requisicion_sala_id: int, mensaje?: string}>
     */
    public static function procesarPendientes(): array
    {
        $resultados = [];
        $pendientes = self::$pendientes;
        self::$pendientes = [];

        foreach ($pendientes as $item) {
            try {
                app(RequisicionSalaTransferenciaLaboratorioService::class)
                    ->ejecutarSiCorresponde($item['id'], $item['usuario_id']);
                $resultados[] = ['ok' => true, 'requisicion_sala_id' => $item['id']];
            } catch (\Throwable $e) {
                Log::error('RequisicionSala: transferencia diferida falló', [
                    'requisicion_sala_id' => $item['id'],
                    'error' => $e->getMessage(),
                ]);
                $resultados[] = [
                    'ok' => false,
                    'requisicion_sala_id' => $item['id'],
                    'mensaje' => $e->getMessage(),
                ];
            }
        }

        return $resultados;
    }

    public static function tienePendientes(): bool
    {
        return self::$pendientes !== [];
    }

    public static function descartarPendientes(): void
    {
        self::$pendientes = [];
    }
}
