<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Tipos de comprobante de facturación juego (p-resultado.c dft_tipo_*).
 */
final class FacturacionJuegoTipoSupport
{
    public const RUBRO_MESA = 'mesa';

    public const RUBRO_SLOT = 'slot';

    public const RUBRO_RULETA_EL = 'ruleta_el';

    public static function letra(): string
    {
        $letra = strtoupper(trim((string) FacturacionJuegoConfigSupport::parametro()->letra));

        return $letra !== '' ? $letra : 'B';
    }

    public static function nombreCliente(string $rubro): string
    {
        $map = (array) config('facturacion_juego.nombres_cliente', []);

        return (string) ($map[$rubro] ?? match ($rubro) {
            self::RUBRO_MESA => 'Mesas de juego',
            self::RUBRO_SLOT => 'Tragamonedas',
            self::RUBRO_RULETA_EL => 'Ruleta El.',
            default => 'Juego',
        });
    }

    public static function abreviaturaParaResultado(string $rubro, float $resultado): string
    {
        $p = FacturacionJuegoConfigSupport::parametro();
        $ganancia = $resultado >= 0.0;

        return match ($rubro) {
            self::RUBRO_MESA => $ganancia ? (string) $p->tipo_gan_mesa : (string) $p->tipo_per_mesa,
            self::RUBRO_SLOT => $ganancia ? (string) $p->tipo_gan_slot : (string) $p->tipo_per_slot,
            self::RUBRO_RULETA_EL => $ganancia
                ? (string) $p->tipo_gan_ruleta_el
                : (string) $p->tipo_per_ruleta_el,
            default => throw new InvalidArgumentException('Rubro de juego inválido: '.$rubro),
        };
    }

    public static function tipo(string $abreviatura): Tipotransaccion
    {
        $abrev = strtoupper(trim($abreviatura));
        $tipo = Tipotransaccion::query()
            ->where('abreviatura', $abrev)
            ->whereNull('deleted_at')
            ->first();

        if ($tipo === null) {
            throw new InvalidArgumentException(
                'Falta tipo de transacción '.$abrev.' para facturación juego.',
            );
        }

        return $tipo;
    }

    /** p-resultado checksigno == SUMA → asiento H en cuenta de ventas. */
    public static function esGanancia(Tipotransaccion $tipo): bool
    {
        return (int) ($tipo->signo ?? 0) >= 0;
    }
}
