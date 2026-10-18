<?php

namespace App\Support\Compras;

use App\Models\Caja\Tipocuentacaja;
use App\Models\Ventas\Formapago;

/**
 * Mapeo Anita `propago` ↔ ERP (hardcodes Crown propago.def / a-promae.c).
 *
 * Forma de pago: PROP_CHEQUE='C', PROP_TRANSFERENCIA='T'
 * Tipo cuenta:   PROP_CC='1', PROP_CA='2' → tipocuentacaja CC / CA
 */
final class ProveedorPropagoAnitaSupport
{
    public const FORMA_CHEQUE = 'C';

    public const FORMA_TRANSFERENCIA = 'T';

    /** @var array<string, string> Anita prop_tipo_cta → abreviatura tipocuentacaja ERP */
    public const TIPO_CTA_ANITA_A_ABREV = [
        '1' => 'CC',
        '2' => 'CA',
        '0' => 'CC', // índice help_9 antes de cambia_variables
    ];

    /** @var array<string, string> abreviatura ERP → Anita prop_tipo_cta */
    public const ABREV_A_TIPO_CTA_ANITA = [
        'CC' => '1',
        'CA' => '2',
    ];

    public static function formapagoIdDesdeAnita(string $propFormaPago): ?int
    {
        $abrev = strtoupper(trim($propFormaPago));
        if ($abrev === '') {
            return null;
        }

        // help_8 índice 0/1 grabado en pantalla antes de normalizar a C/T
        $abrev = match ($abrev) {
            '0' => self::FORMA_CHEQUE,
            '1' => self::FORMA_TRANSFERENCIA,
            default => $abrev,
        };

        $id = Formapago::query()->where('abreviatura', $abrev)->value('id');

        return $id !== null ? (int) $id : null;
    }

    public static function tipocuentacajaIdDesdeAnita(string $propTipoCta): ?int
    {
        $raw = trim($propTipoCta);
        if ($raw === '') {
            return null;
        }

        $abrev = self::TIPO_CTA_ANITA_A_ABREV[$raw] ?? strtoupper($raw);

        $id = Tipocuentacaja::query()->where('abreviatura', $abrev)->value('id');
        if ($id !== null) {
            return (int) $id;
        }

        // Compat: a veces el bridge deja el dígito como id ERP
        if (ctype_digit($raw)) {
            $porId = Tipocuentacaja::query()->whereKey((int) $raw)->value('id');

            return $porId !== null ? (int) $porId : null;
        }

        return null;
    }

    /**
     * Valor Anita prop_tipo_cta (char '1'/'2') desde tipocuentacaja ERP.
     */
    public static function propTipoCtaAnitaDesdeTipocuentacajaId(?int $tipocuentacajaId): string
    {
        if ($tipocuentacajaId === null || $tipocuentacajaId <= 0) {
            return '';
        }

        $abrev = strtoupper((string) (Tipocuentacaja::query()->whereKey($tipocuentacajaId)->value('abreviatura') ?? ''));
        if (isset(self::ABREV_A_TIPO_CTA_ANITA[$abrev])) {
            return self::ABREV_A_TIPO_CTA_ANITA[$abrev];
        }

        // AGG u otros: a veces se mandaba el id numérico
        return (string) $tipocuentacajaId;
    }
}
