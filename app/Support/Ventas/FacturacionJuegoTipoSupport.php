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

    /**
     * p-resultado checksigno == SUMA → asiento H en cuenta de ventas.
     *
     * Tipotransaccion expone signo vía accessor como S/R (BD: 1/-1).
     * Cast a int de "R"/"S" da 0 y trataba créditos (CME/CSL/CRE) como ganancia.
     */
    public static function esGanancia(Tipotransaccion $tipo): bool
    {
        $signo = $tipo->signo;
        if (is_string($signo) && ! is_numeric($signo)) {
            return strtoupper(trim($signo)) === 'S';
        }

        return (int) ($signo ?? 0) > 0;
    }

    /**
     * Descripción de movimiento al estilo p-resultado (subd_desc_mov, 30 chars):
     * "CME B-00015-00017046 Mesas de j".
     */
    public static function conceptoMayor(string $codigoVenta, string $nombreCliente = ''): string
    {
        $codigo = trim($codigoVenta);
        $nombre = trim($nombreCliente);
        $txt = $nombre !== '' ? trim($codigo.' '.$nombre) : $codigo;

        return mb_substr($txt !== '' ? $txt : 'Facturacion juego', 0, 30);
    }
}
