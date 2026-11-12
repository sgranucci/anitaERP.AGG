<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

/**
 * Tipos de comprobante de facturación juego (p-resultado.c dft_tipo_* + FOL/COL online).
 */
final class FacturacionJuegoTipoSupport
{
    public const RUBRO_MESA = 'mesa';

    public const RUBRO_SLOT = 'slot';

    public const RUBRO_RULETA_EL = 'ruleta_el';

    public const RUBRO_DEL_RIO = 'del_rio';

    public const RUBRO_BETSUR = 'betsur';

    public const RUBRO_APUESTAS = 'apuestas';

    /**
     * @return list<string>
     */
    public static function rubros(): array
    {
        return [
            self::RUBRO_MESA,
            self::RUBRO_SLOT,
            self::RUBRO_RULETA_EL,
            self::RUBRO_DEL_RIO,
            self::RUBRO_BETSUR,
            self::RUBRO_APUESTAS,
        ];
    }

    /**
     * Rubros de sala física (una factura por sala/día).
     *
     * @return list<string>
     */
    public static function rubrosSala(): array
    {
        return [
            self::RUBRO_MESA,
            self::RUBRO_SLOT,
            self::RUBRO_RULETA_EL,
        ];
    }

    /**
     * Rubros globales online (una sola factura por día, no por sala).
     *
     * @return list<string>
     */
    public static function rubrosOnline(): array
    {
        return [
            self::RUBRO_DEL_RIO,
            self::RUBRO_BETSUR,
            self::RUBRO_APUESTAS,
        ];
    }

    public static function esOnline(string $rubro): bool
    {
        return in_array($rubro, self::rubrosOnline(), true);
    }

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
            self::RUBRO_DEL_RIO => 'Del Río online',
            self::RUBRO_BETSUR => 'Betsur',
            self::RUBRO_APUESTAS => 'Apuestas deportivas',
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
            self::RUBRO_DEL_RIO, self::RUBRO_BETSUR, self::RUBRO_APUESTAS => $ganancia
                ? (string) ($p->tipo_gan_online ?: 'FOL')
                : (string) ($p->tipo_per_online ?: 'COL'),
            default => throw new InvalidArgumentException('Rubro de juego inválido: '.$rubro),
        };
    }

    /**
     * PV Anita del comprobante: sala física o sucursal Del Río / Betsur / Apuestas.
     */
    public static function sucursalAnitaParaRubro(string $rubro, int $sucursalSala): int
    {
        $p = FacturacionJuegoConfigSupport::parametro();

        return match ($rubro) {
            self::RUBRO_DEL_RIO => (int) ($p->sucursal_del_rio
                ?: config('caja_anita.sucursal_del_rio_online', 67)),
            self::RUBRO_BETSUR => (int) ($p->sucursal_betsur
                ?: config('caja_anita.sucursal_betsur_online', 68)),
            self::RUBRO_APUESTAS => (int) ($p->sucursal_apuestas ?? 0),
            default => $sucursalSala,
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
     * Cast a int de "R"/"S" da 0 y trataba créditos (CME/CSL/CRE/COL) como ganancia.
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
