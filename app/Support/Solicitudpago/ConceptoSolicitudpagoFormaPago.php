<?php

namespace App\Support\Solicitudpago;

final class ConceptoSolicitudpagoFormaPago
{
    public const SIN_CUOTAS = 'SIN_CUOTAS';

    public const CUOTAS = 'CUOTAS';

    public static function desdeAnita(int|string|null $valor): string
    {
        return ((int) $valor) === 1 ? self::CUOTAS : self::SIN_CUOTAS;
    }

    /**
     * Obligatoriedad: concepto CUOTAS y SP que no sea hija.
     * Las hijas heredan el concepto de la madre; el plan de cuotas vive solo en la madre.
     */
    public static function requiereCuotas(?string $formaPagoConcepto, int|string|null $solicitudpagoMadreId = null): bool
    {
        if ((int) $solicitudpagoMadreId > 0) {
            return false;
        }

        return strtoupper(trim((string) $formaPagoConcepto)) === self::CUOTAS;
    }

    /**
     * Visibilidad del bloque: CUOTAS siempre; sin concepto, Plan/Recurrente.
     * Con concepto SIN_CUOTAS (u otra distinta de CUOTAS): oculto.
     * SP hija: nunca (el plan se consulta/edita en la madre).
     */
    public static function muestraBloqueCuotas(
        ?string $formaPagoConcepto,
        ?string $tratamiento,
        bool $tieneConcepto = false,
        int|string|null $solicitudpagoMadreId = null
    ): bool {
        if ((int) $solicitudpagoMadreId > 0) {
            return false;
        }

        $forma = strtoupper(trim((string) $formaPagoConcepto));
        if ($tieneConcepto || $forma !== '') {
            return $forma === self::CUOTAS;
        }

        return SolicitudpagoTratamientos::usaCuotas($tratamiento);
    }

    /** @deprecated Usar requiereCuotas / muestraBloqueCuotas */
    public static function usaCuotasEnSolicitud(?string $formaPagoConcepto, ?string $tratamiento, bool $tieneConcepto = false): bool
    {
        if ($tieneConcepto || strtoupper(trim((string) $formaPagoConcepto)) !== '') {
            return self::requiereCuotas($formaPagoConcepto);
        }

        return self::muestraBloqueCuotas($formaPagoConcepto, $tratamiento, false);
    }

    /** @return list<array{valor: string, nombre: string}> */
    public static function opciones(): array
    {
        return [
            ['valor' => self::SIN_CUOTAS, 'nombre' => 'Sin cuotas'],
            ['valor' => self::CUOTAS, 'nombre' => 'Cuotas'],
        ];
    }
}
