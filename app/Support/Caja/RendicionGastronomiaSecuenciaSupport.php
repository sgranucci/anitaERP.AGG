<?php

namespace App\Support\Caja;

/**
 * Cálculo del siguiente nro_oper Anita a partir de máximos en Informix y en ERP.
 */
final class RendicionGastronomiaSecuenciaSupport
{
    public const FUENTE_ANITA = 'anita';

    public const FUENTE_ERP = 'erp';

    public const FUENTE_COMBINADO = 'combinado';

    public const FUENTE_ERP_FALLBACK = 'erp_fallback';

    /**
     * @return array{
     *   siguiente: int,
     *   fuente: string,
     *   ultimo_anita: int,
     *   ultimo_erp: int
     * }
     */
    public static function calcularSiguiente(?int $ultimoAnita, int $ultimoErp): array
    {
        $ultimoAnita = max(0, (int) ($ultimoAnita ?? 0));
        $ultimoErp = max(0, $ultimoErp);

        if ($ultimoAnita >= $ultimoErp) {
            $fuente = $ultimoAnita > 0 && $ultimoErp > 0 && $ultimoAnita !== $ultimoErp
                ? self::FUENTE_COMBINADO
                : ($ultimoAnita > 0 ? self::FUENTE_ANITA : self::FUENTE_ERP);
        } else {
            $fuente = $ultimoAnita > 0 ? self::FUENTE_COMBINADO : self::FUENTE_ERP;
        }

        $maximo = max($ultimoAnita, $ultimoErp);

        return [
            'siguiente' => $maximo + 1,
            'fuente' => $fuente,
            'ultimo_anita' => $ultimoAnita,
            'ultimo_erp' => $ultimoErp,
        ];
    }

    public static function extraerNroOperDesdeCodigo(?string $codigo): ?int
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '' || ! preg_match('/^(\d+)$/', $codigo, $m)) {
            return null;
        }

        $n = (int) $m[1];

        return $n > 0 ? $n : null;
    }
}
