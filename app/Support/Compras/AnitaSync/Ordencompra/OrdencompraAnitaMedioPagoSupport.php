<?php

namespace App\Support\Compras\AnitaSync\Ordencompra;

/**
 * occ_medio_pago en Anita (char): C/T/E/R/V — ver occuota.def (OCC_graba_occuota).
 */
final class OrdencompraAnitaMedioPagoSupport
{
    public const CHEQUE = 'C';

    public const TRANSFERENCIA = 'T';

    public const EFECTIVO = 'E';

    public const SOLO_REGISTRAR = 'R';

    public const VARIOS_CHEQUES = 'V';

    /** Formapago ERP (abreviatura) → occ_medio_pago Anita. */
    public static function desdeFormapagoAbreviatura(?string $abreviaturaErp): string
    {
        return match (strtoupper(trim((string) $abreviaturaErp))) {
            'C' => self::CHEQUE,
            'T' => self::TRANSFERENCIA,
            'E' => self::EFECTIVO,
            'V' => self::VARIOS_CHEQUES,
            default => self::SOLO_REGISTRAR,
        };
    }

    /** occ_medio_pago Anita (letra o dígito legacy UI) → abreviatura formapago ERP. */
    public static function haciaFormapagoAbreviatura(mixed $medioAnita): ?string
    {
        return match (strtoupper(trim((string) $medioAnita))) {
            self::CHEQUE, '0' => 'C',
            self::TRANSFERENCIA, '1' => 'T',
            self::EFECTIVO, '2' => 'E',
            self::SOLO_REGISTRAR, '3' => 'R',
            self::VARIOS_CHEQUES, '4' => 'V',
            default => null,
        };
    }
}
