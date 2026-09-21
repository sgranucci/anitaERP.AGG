<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Estados canónicos del legajo cambio/devolución marketplace (Ferli).
 */
final class CambioDevolucionMarketplaceEstadosSupport
{
    public const BORRADOR = 'borrador';

    public const ABIERTO = 'abierto';

    public const FACTURA_REEMPLAZO = 'factura_reemplazo';

    public const AGUARDANDO_RECEPCION = 'aguardando_recepcion';

    public const RECIBIDO = 'recibido';

    public const NC_ORIGINAL = 'nc_original';

    public const PENDIENTE_COMPENSACION = 'pendiente_compensacion';

    public const CERRADO = 'cerrado';

    public const ANULADO = 'anulado';

    /** @var list<string> */
    public const TODOS = [
        self::BORRADOR,
        self::ABIERTO,
        self::FACTURA_REEMPLAZO,
        self::AGUARDANDO_RECEPCION,
        self::RECIBIDO,
        self::NC_ORIGINAL,
        self::PENDIENTE_COMPENSACION,
        self::CERRADO,
        self::ANULADO,
    ];

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::BORRADOR => 'Borrador',
        self::ABIERTO => 'Abierto',
        self::FACTURA_REEMPLAZO => 'Factura reemplazo emitida',
        self::AGUARDANDO_RECEPCION => 'Aguardando recepción',
        self::RECIBIDO => 'Recibido',
        self::NC_ORIGINAL => 'NC original emitida',
        self::PENDIENTE_COMPENSACION => 'Pendiente compensación',
        self::CERRADO => 'Cerrado',
        self::ANULADO => 'Anulado',
    ];

    /** @var array<string, list<string>> */
    private const TRANSICIONES = [
        self::BORRADOR => [self::ABIERTO, self::ANULADO],
        self::ABIERTO => [self::FACTURA_REEMPLAZO, self::ANULADO],
        self::FACTURA_REEMPLAZO => [self::AGUARDANDO_RECEPCION],
        self::AGUARDANDO_RECEPCION => [self::RECIBIDO, self::ANULADO],
        self::RECIBIDO => [self::NC_ORIGINAL],
        self::NC_ORIGINAL => [self::PENDIENTE_COMPENSACION, self::CERRADO],
        self::PENDIENTE_COMPENSACION => [self::CERRADO],
        self::CERRADO => [],
        self::ANULADO => [],
    ];

    public static function etiqueta(string $estado): string
    {
        return self::ETIQUETAS[$estado] ?? $estado;
    }

    public static function esValido(string $estado): bool
    {
        return in_array($estado, self::TODOS, true);
    }

    public static function puedeTransicionar(string $desde, string $hacia): bool
    {
        $permitidos = self::TRANSICIONES[$desde] ?? [];

        return in_array($hacia, $permitidos, true);
    }

    public static function esEditable(string $estado): bool
    {
        return in_array($estado, [self::BORRADOR, self::ABIERTO], true);
    }

    public static function esTerminal(string $estado): bool
    {
        return in_array($estado, [self::CERRADO, self::ANULADO], true);
    }

    /** @return array<string, string> */
    public static function opcionesSelect(): array
    {
        return self::ETIQUETAS;
    }
}
