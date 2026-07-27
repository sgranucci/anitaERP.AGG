<?php

namespace App\Support\Sala;

use App\Models\Sala\CumplimientoRequisicionSala;
use App\Models\Sala\RequisicionSala;
use App\Models\Sala\RequisicionSalaEstado;

/**
 * Modos de edición post-aprobación de requisición de sala.
 *
 * - Edición completa: PENDIENTE / EN LABORATORIO / RECHAZADA.
 * - Edición menor: APROBADA / PARCIAL (campos no estructurales).
 * - Reabrir/desaprobar: vuelve a PENDIENTE para cambio de negocio.
 */
final class RequisicionSalaEdicionSupport
{
    /** @return list<string> */
    public static function estadosEdicionCompleta(): array
    {
        return [
            self::nombreEstado('0'),
            self::nombreEstado('5'),
            self::nombreEstado('Z'),
        ];
    }

    /** @return list<string> */
    public static function estadosEdicionMenor(): array
    {
        return [
            self::nombreEstado('A'),
            self::nombreEstado('2'),
        ];
    }

    /** @return list<string> */
    public static function estadosReabrir(): array
    {
        return [
            self::nombreEstado('A'),
            self::nombreEstado('2'),
        ];
    }

    public static function permiteEdicionCompleta(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosEdicionCompleta(), true);
    }

    public static function permiteEdicionMenor(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosEdicionMenor(), true);
    }

    public static function permiteReabrir(?string $estado): bool
    {
        return in_array((string) $estado, self::estadosReabrir(), true);
    }

    /**
     * @return list<string>
     */
    public static function camposCabeceraMenores(): array
    {
        return [
            'comentario',
            'detalle',
            'zona_sala_id',
            'prioridad_sala_id',
            'fecha_entrega',
        ];
    }

    public static function cantidadCumplimientosActivos(RequisicionSala|int $req): int
    {
        $id = $req instanceof RequisicionSala ? (int) $req->id : (int) $req;
        if ($id <= 0) {
            return 0;
        }

        return (int) CumplimientoRequisicionSala::query()
            ->where('estado', CumplimientoRequisicionSala::ESTADO_ACTIVO)
            ->whereHas('articulos', fn ($q) => $q->where('requisicion_sala_id', $id))
            ->count();
    }

    public static function mensajeBloqueoReabrirPorCumplimientos(): string
    {
        return 'No se puede reabrir: hay cumplimientos activos. Revertí los cumplimientos primero y luego reabrí la requisición.';
    }

    public static function mensajeNoEditable(): string
    {
        return 'Solo se puede editar en estado PENDIENTE, EN LABORATORIO o RECHAZADA. '
            .'En APROBADA/PARCIAL solo se permiten cambios menores, o bien reabrir para un cambio de negocio.';
    }

    private static function nombreEstado(string $valor): string
    {
        $idx = array_search($valor, array_column(RequisicionSalaEstado::$enumEstado, 'valor'), true);

        return RequisicionSalaEstado::$enumEstado[$idx]['nombre'];
    }
}
