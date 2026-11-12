<?php

namespace App\Support\Sueldos;

/**
 * Tipos de documento emitibles desde la solapa Contratos (Anita: contrato / cert laboral / cert baja).
 */
final class ContratoDocumentoTipos
{
    public const CONTRATO = 'CONTRATO';

    public const CERT_LABORAL = 'CERT_LABORAL';

    public const CERT_BAJA = 'CERT_BAJA';

    /** @var array<string, string> */
    public const LABELS = [
        self::CONTRATO => 'Contrato de trabajo',
        self::CERT_LABORAL => 'Certificado laboral',
        self::CERT_BAJA => 'Certificado de baja',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::LABELS);
    }

    public static function label(string $tipo): string
    {
        return self::LABELS[$tipo] ?? $tipo;
    }

    public static function esValido(string $tipo): bool
    {
        return isset(self::LABELS[$tipo]);
    }
}
