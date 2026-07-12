<?php

namespace App\Support\Ventas\Vianda;

use App\Models\Ventas\ViandaConsumo;
use App\Support\Ventas\ViandaUsuarioTipoSupport;
use InvalidArgumentException;

/**
 * Un pedido activo por empleado de vianda y fecha de jornada.
 * Si el consumo del día fue anulado (estado N), el empleado puede volver a pedir.
 * Usuarios con tipo Administrador (A) quedan exentos del límite diario.
 */
final class ViandaConsumoLimiteDiarioSupport
{
    public static function habilitado(): bool
    {
        return filter_var(config('vianda.un_pedido_por_dia', true), FILTER_VALIDATE_BOOLEAN);
    }

    public static function consumoActivoDelDia(int $viandaUsuarioId, int $empresaId, string $fechaJornada): ?ViandaConsumo
    {
        if ($viandaUsuarioId <= 0 || $empresaId <= 0 || trim($fechaJornada) === '') {
            return null;
        }

        return ViandaConsumo::query()
            ->where('vianda_usuario_id', $viandaUsuarioId)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->where('estado', 'A')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{
     *   puede_pedir:bool,
     *   mensaje:?string,
     *   consumo_existente:?array{id:int,codigo_retiro:string,hora:?string}
     * }
     */
    public static function estadoParaUsuario(
        int $viandaUsuarioId,
        int $empresaId,
        string $fechaJornada,
        ?string $tipoUsuario = null,
    ): array {
        if (! self::habilitado() || ViandaUsuarioTipoSupport::esAdministrador($tipoUsuario)) {
            return [
                'puede_pedir' => true,
                'mensaje' => null,
                'consumo_existente' => null,
            ];
        }

        $existente = self::consumoActivoDelDia($viandaUsuarioId, $empresaId, $fechaJornada);
        if ($existente === null) {
            return [
                'puede_pedir' => true,
                'mensaje' => null,
                'consumo_existente' => null,
            ];
        }

        return [
            'puede_pedir' => false,
            'mensaje' => self::mensajeConsumoExistente($existente),
            'consumo_existente' => [
                'id' => (int) $existente->id,
                'codigo_retiro' => (string) $existente->codigo_retiro,
                'hora' => $existente->hora !== null ? (string) $existente->hora : null,
            ],
        ];
    }

    /**
     * Dentro de una transacción: bloquea filas concurrentes del mismo empleado/jornada.
     */
    public static function exigirPuedePedir(
        int $viandaUsuarioId,
        int $empresaId,
        string $fechaJornada,
        ?string $tipoUsuario = null,
    ): void {
        if (! self::habilitado() || ViandaUsuarioTipoSupport::esAdministrador($tipoUsuario)) {
            return;
        }

        $existente = ViandaConsumo::query()
            ->where('vianda_usuario_id', $viandaUsuarioId)
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', $fechaJornada)
            ->where('estado', 'A')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->first();

        if ($existente !== null) {
            throw new InvalidArgumentException(self::mensajeConsumoExistente($existente));
        }
    }

    private static function mensajeConsumoExistente(ViandaConsumo $consumo): string
    {
        $codigo = trim((string) $consumo->codigo_retiro);
        $hora = trim((string) ($consumo->hora ?? ''));
        $detalle = $codigo !== '' ? ' (código '.$codigo.($hora !== '' ? ' · '.$hora : '').')' : '';

        return 'Ya retiró vianda en la jornada de hoy'.$detalle.'. Solo se permite un pedido por día.';
    }
}
