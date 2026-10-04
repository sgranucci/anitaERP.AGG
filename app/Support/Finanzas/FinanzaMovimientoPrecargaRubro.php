<?php

declare(strict_types=1);

namespace App\Support\Finanzas;

/**
 * Rubros de la posición bancaria diaria que completa la precarga
 * (bloque RRHH / SUSS / Descubierto / TRF / otras / intercompany).
 */
final class FinanzaMovimientoPrecargaRubro
{
    public const RRHH = 'rrhh';

    public const SUSS = 'suss';

    public const DESCUBIERTO = 'descubierto';

    public const TRF_OTROS_BANCOS = 'trf_otros_bancos';

    public const OTRAS_OPERACIONES = 'otras_operaciones';

    public const TRF_INTERCOMPANY = 'trf_intercompany';

    /** @var array<string, string> */
    public const ETIQUETAS = [
        self::RRHH => 'RRHH (sueldos)',
        self::SUSS => 'SUSS',
        self::DESCUBIERTO => 'Descubierto',
        self::TRF_OTROS_BANCOS => 'TRF desde otros bancos',
        self::OTRAS_OPERACIONES => 'Otras operaciones',
        self::TRF_INTERCOMPANY => 'TRF intercompany',
    ];

    /** Etiqueta exacta de la fila en las hojas de proyección. */
    /** @var array<string, string> */
    public const ETIQUETA_HOJA = [
        self::RRHH => 'RRHH',
        self::SUSS => 'SUSS',
        self::DESCUBIERTO => 'Descubierto',
        self::TRF_OTROS_BANCOS => 'TRF desde otros bancos',
        self::OTRAS_OPERACIONES => 'Otras operaciones',
        self::TRF_INTERCOMPANY => 'TRF intercompany',
    ];

    /** @var array<string, string> */
    public const TIPOS = [
        'ingreso' => 'Ingreso',
        'egreso' => 'Egreso',
        'transferencia' => 'Transferencia',
    ];

    public static function etiqueta(string $rubro): string
    {
        return self::ETIQUETAS[$rubro] ?? $rubro;
    }

    public static function etiquetaHoja(string $rubro): string
    {
        return self::ETIQUETA_HOJA[$rubro] ?? '';
    }

    public static function tipoEtiqueta(string $tipo): string
    {
        return self::TIPOS[$tipo] ?? $tipo;
    }

    /**
     * @return array<string, string> etiqueta hoja => rubro
     */
    public static function rubroPorEtiquetaHoja(): array
    {
        $out = [];
        foreach (self::ETIQUETA_HOJA as $rubro => $etiqueta) {
            $out[$etiqueta] = $rubro;
        }

        return $out;
    }
}
