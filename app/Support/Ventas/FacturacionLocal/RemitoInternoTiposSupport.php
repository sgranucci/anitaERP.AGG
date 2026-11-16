<?php

namespace App\Support\Ventas\FacturacionLocal;

/**
 * Tipos de remito de Facturación Local (enum cerrado: select, no modal).
 */
final class RemitoInternoTiposSupport
{
    public const RIN = 'RIN';

    public const REX = 'REX';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::RIN => 'RIN — Remito interno',
        self::REX => 'REX — Remito externo',
    ];

    /** @var array<string, string> */
    public const TITULOS_DOCUMENTO = [
        self::RIN => 'REMITO INTERNO',
        self::REX => 'REMITO EXTERNO',
    ];

    public static function esValido(string $tipo): bool
    {
        return isset(self::ETIQUETAS[$tipo]);
    }

    public static function normalizar(?string $tipo): string
    {
        $tipo = strtoupper(trim((string) $tipo));

        return self::esValido($tipo) ? $tipo : self::RIN;
    }

    public static function etiqueta(string $tipo): string
    {
        $tipo = self::normalizar($tipo);

        return self::ETIQUETAS[$tipo];
    }

    public static function tituloDocumento(string $tipo): string
    {
        $tipo = self::normalizar($tipo);

        return self::TITULOS_DOCUMENTO[$tipo];
    }

    /**
     * @return array<string, string>
     */
    public static function opcionesSelect(): array
    {
        return self::ETIQUETAS;
    }
}
