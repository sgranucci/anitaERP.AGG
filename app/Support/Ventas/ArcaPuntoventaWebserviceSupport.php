<?php

declare(strict_types=1);

namespace App\Support\Ventas;

/**
 * Normaliza alias legacy de webservice en puntoventa (ej. mtxsca → wsmtxca).
 */
final class ArcaPuntoventaWebserviceSupport
{
    public const WSFE = 'wsfev1';

    public const WSMTXCA = 'wsmtxca';

    /** @var list<string> */
    public const ALIASES_MTXCA = ['wsmtxca', 'mtxsca', 'mtxca'];

    /** @var list<string> */
    public const ALIASES_WSFE = ['wsfev1', 'wsfe'];

    public static function normalizar(?string $webservice): string
    {
        $ws = strtolower(trim((string) $webservice));

        if (in_array($ws, self::ALIASES_MTXCA, true)) {
            return self::WSMTXCA;
        }

        if (in_array($ws, self::ALIASES_WSFE, true)) {
            return self::WSFE;
        }

        return $ws;
    }

    public static function esMtxca(?string $webservice): bool
    {
        return self::normalizar($webservice) === self::WSMTXCA;
    }

    public static function esWsfe(?string $webservice): bool
    {
        return self::normalizar($webservice) === self::WSFE;
    }

    public static function esSoapCaea(?string $webservice): bool
    {
        $ws = self::normalizar($webservice);

        return $ws === self::WSMTXCA || $ws === self::WSFE;
    }

    /**
     * Valores a usar en whereIn de puntoventa.webservice (incluye alias legacy).
     *
     * @return list<string>
     */
    public static function valoresWhereInSoapCaea(): array
    {
        return array_values(array_unique(array_merge(self::ALIASES_MTXCA, self::ALIASES_WSFE)));
    }

    /**
     * Clona/ajusta el objeto PV para servicios SOAP (webservice canónico).
     */
    public static function puntoventaParaSoap(object $puntoventa): object
    {
        $clone = clone $puntoventa;
        $clone->webservice = self::normalizar((string) ($puntoventa->webservice ?? ''));

        return $clone;
    }
}
