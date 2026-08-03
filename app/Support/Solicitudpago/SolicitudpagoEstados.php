<?php

namespace App\Support\Solicitudpago;

final class SolicitudpagoEstados
{
    public const EMITIDA = 'EMITIDA';

    public const CONTROLADA = 'CONTROLADA';

    public const AUTORIZADA = 'AUTORIZADA';

    public const PAGADA = 'PAGADA';

    public const SUSPENDIDA = 'SUSPENDIDA';

    public const RECHAZADA = 'RECHAZADA';

    public const TERMINADA = 'TERMINADA';

    /** Estados en los que se puede reenviar al árbol (todo menos PAGADA). */
    public static function puedeReenviarAlArbol(?string $estado): bool
    {
        $estado = strtoupper(trim((string) $estado));

        return $estado !== '' && $estado !== self::PAGADA;
    }

    /** Cabecera bloqueada para edición de datos (CONTROLADA: solo consulta / suspensión). */
    public static function bloqueaEdicion(?string $estado): bool
    {
        return strtoupper(trim((string) $estado)) === self::CONTROLADA;
    }

    public static function esAdministradorSesion(): bool
    {
        return session()->get('rol_nombre') === 'administrador';
    }

    /** @var array<string, string> */
    private const ANITA_A_ERP = [
        'E' => self::EMITIDA,
        'C' => self::CONTROLADA,
        'A' => self::AUTORIZADA,
        'P' => self::PAGADA,
        'S' => self::SUSPENDIDA,
        'R' => self::RECHAZADA,
        'T' => self::TERMINADA,
    ];

    /** @var array<string, string> */
    private const ERP_A_ANITA = [
        self::EMITIDA => 'E',
        self::CONTROLADA => 'C',
        self::AUTORIZADA => 'A',
        self::PAGADA => 'P',
        self::SUSPENDIDA => 'S',
        self::RECHAZADA => 'R',
        self::TERMINADA => 'T',
    ];

    public static function desdeAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));
        if ($v === '') {
            return self::EMITIDA;
        }

        return self::ANITA_A_ERP[$v] ?? self::EMITIDA;
    }

    public static function haciaAnita(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return self::ERP_A_ANITA[$v] ?? 'E';
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::EMITIDA, 'nombre' => 'Emitida'],
            ['valor' => self::CONTROLADA, 'nombre' => 'Controlada'],
            ['valor' => self::AUTORIZADA, 'nombre' => 'Autorizada'],
            ['valor' => self::PAGADA, 'nombre' => 'Pagada'],
            ['valor' => self::SUSPENDIDA, 'nombre' => 'Suspendida'],
            ['valor' => self::RECHAZADA, 'nombre' => 'Rechazada'],
            ['valor' => self::TERMINADA, 'nombre' => 'Terminada'],
        ];
    }

    /**
     * Estados configurables en el árbol del concepto (nivel → documento).
     *
     * @return list<array{valor: string, nombre: string}>
     */
    public static function opcionesArbolAprobacion(): array
    {
        return [
            ['valor' => self::EMITIDA, 'nombre' => 'Emitida (auto)'],
            ['valor' => self::CONTROLADA, 'nombre' => 'Controlada'],
            ['valor' => self::AUTORIZADA, 'nombre' => 'Autorizada'],
            // PAGADA en el árbol = aviso a pagadores (mail IE); el estado real lo pone el IE/OP.
            ['valor' => self::PAGADA, 'nombre' => 'Aviso pago (IE)'],
        ];
    }

    /** Nivel del concepto marcado como aviso a pagadores (no fija PAGADA en cabecera). */
    public static function esAvisoPagoArbol(?string $documentoEstadoAlAprobar): bool
    {
        return strtoupper(trim((string) $documentoEstadoAlAprobar)) === self::PAGADA;
    }

    /** Convención por defecto: nivel 1..4 → EMITIDA..PAGADA. */
    public static function estadoArbolPorNivel(int $nivel): ?string
    {
        return match ($nivel) {
            1 => self::EMITIDA,
            2 => self::CONTROLADA,
            3 => self::AUTORIZADA,
            4 => self::PAGADA,
            default => null,
        };
    }

    /** @return list<string> */
    public static function valoresArbolAprobacion(): array
    {
        return array_column(self::opcionesArbolAprobacion(), 'valor');
    }

    public static function label(?string $estado): string
    {
        $estado = strtoupper(trim((string) $estado));
        foreach (self::opciones() as $opcion) {
            if ($opcion['valor'] === $estado) {
                return $opcion['nombre'];
            }
        }

        return $estado !== '' ? $estado : '—';
    }

    /** Clases Bootstrap badge para listados. */
    public static function badgeClass(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            self::EMITIDA => 'badge badge-info',
            self::CONTROLADA => 'badge badge-primary',
            self::AUTORIZADA => 'badge badge-success',
            self::PAGADA => 'badge badge-dark',
            self::SUSPENDIDA => 'badge badge-warning text-dark',
            self::RECHAZADA => 'badge badge-danger',
            self::TERMINADA => 'badge badge-secondary',
            default => 'badge badge-light',
        };
    }
}
