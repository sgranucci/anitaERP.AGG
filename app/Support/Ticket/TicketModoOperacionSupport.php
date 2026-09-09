<?php

namespace App\Support\Ticket;

use App\Models\Ticket\Ticket_Configuracion_Areadestino;
use App\Models\Ticket\Ticket_Estado;

/**
 * Modo de operación del módulo de tickets por área destino.
 *
 * Sin fila en config = dispatch (comportamiento actual de Sistemas):
 * un administrador asigna técnicos.
 *
 * claim (p. ej. Mantenimiento): la cola queda sin técnico y el técnico toma el ticket.
 */
class TicketModoOperacionSupport
{
    public const MODO_DISPATCH = Ticket_Configuracion_Areadestino::MODO_DISPATCH;

    public const MODO_CLAIM = Ticket_Configuracion_Areadestino::MODO_CLAIM;

    /** @var array<int, string>|null */
    private static ?array $cacheModos = null;

    public static function forgetCache(): void
    {
        self::$cacheModos = null;
    }

    /**
     * @return array<string, string> valor => etiqueta
     */
    public static function opcionesModo(): array
    {
        return [
            self::MODO_DISPATCH => 'Asignación por administrador',
            self::MODO_CLAIM => 'Cola / el técnico toma el ticket',
        ];
    }

    public static function normalizarModo(?string $modo): string
    {
        $modo = strtolower(trim((string) $modo));

        return $modo === self::MODO_CLAIM
            ? self::MODO_CLAIM
            : self::MODO_DISPATCH;
    }

    public static function modo(?int $areadestinoId): string
    {
        if (! $areadestinoId || $areadestinoId <= 0) {
            return self::MODO_DISPATCH;
        }

        $map = self::mapaModos();

        return $map[$areadestinoId] ?? self::MODO_DISPATCH;
    }

    public static function esClaim(?int $areadestinoId): bool
    {
        return self::modo($areadestinoId) === self::MODO_CLAIM;
    }

    public static function esDispatch(?int $areadestinoId): bool
    {
        return ! self::esClaim($areadestinoId);
    }

    /**
     * Estado inicial al crear un ticket según el modo del área.
     * Claim y dispatch sin asignación arrancan en Sin Asignar.
     */
    public static function estadoInicialAlta(?int $areadestinoId): string
    {
        // Ambos modos parten en Sin Asignar; el parámetro queda para futuras reglas por área.
        unset($areadestinoId);

        return Ticket_Estado::$enumEstado[0]['nombre']; // Sin Asignar
    }

    /**
     * Etiqueta corta para UI.
     */
    public static function etiquetaModo(?string $modo): string
    {
        $modo = self::normalizarModo($modo);
        $opciones = self::opcionesModo();

        return $opciones[$modo] ?? $opciones[self::MODO_DISPATCH];
    }

    /**
     * @return array<int, string> areadestino_id => modo
     */
    private static function mapaModos(): array
    {
        if (self::$cacheModos !== null) {
            return self::$cacheModos;
        }

        self::$cacheModos = Ticket_Configuracion_Areadestino::query()
            ->pluck('modo_operacion', 'areadestino_id')
            ->map(static fn ($modo) => self::normalizarModo((string) $modo))
            ->all();

        return self::$cacheModos;
    }
}
