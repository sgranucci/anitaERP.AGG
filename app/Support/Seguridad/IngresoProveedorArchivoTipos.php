<?php

namespace App\Support\Seguridad;

/**
 * Los tres primeros adjuntos de un ingreso tienen tipo fijo.
 * Los renglones que se agregan después quedan libres.
 */
final class IngresoProveedorArchivoTipos
{
    public const ART = 'ART';

    public const PAGO_931 = '931_PAGO';

    public const SEGURO_VIDA = 'SEGURO_VIDA';

    /**
     * @return array<string, array{codigo: string, etiqueta: string, pide_vencimiento: bool}>
     */
    public static function predefinidos(): array
    {
        return [
            self::ART => [
                'codigo' => self::ART,
                'etiqueta' => 'ART',
                'pide_vencimiento' => true,
            ],
            self::PAGO_931 => [
                'codigo' => self::PAGO_931,
                'etiqueta' => '931 PAGO',
                'pide_vencimiento' => false,
            ],
            self::SEGURO_VIDA => [
                'codigo' => self::SEGURO_VIDA,
                'etiqueta' => 'Seguro de vida obligatorio',
                'pide_vencimiento' => false,
            ],
        ];
    }

    public static function normalizar(?string $tipo): ?string
    {
        $tipo = strtoupper(trim((string) $tipo));

        return array_key_exists($tipo, self::predefinidos()) ? $tipo : null;
    }

    public static function etiqueta(?string $tipo): string
    {
        $codigo = self::normalizar($tipo);

        return $codigo ? self::predefinidos()[$codigo]['etiqueta'] : '';
    }

    public static function pideVencimiento(?string $tipo): bool
    {
        $codigo = self::normalizar($tipo);

        return $codigo ? (bool) self::predefinidos()[$codigo]['pide_vencimiento'] : false;
    }

    /**
     * Slots fijos que el ticket todavía no tiene cargados, en el orden de carga.
     *
     * @param  iterable<mixed>  $archivos
     * @return list<array{codigo: string, etiqueta: string, pide_vencimiento: bool}>
     */
    public static function slotsPendientes(iterable $archivos = []): array
    {
        $presentes = [];
        foreach ($archivos as $arch) {
            $tipo = is_object($arch) ? ($arch->tipo ?? null) : (is_array($arch) ? ($arch['tipo'] ?? null) : null);
            $codigo = self::normalizar(is_string($tipo) ? $tipo : null);
            if ($codigo) {
                $presentes[$codigo] = true;
            }
        }

        $slots = [];
        foreach (self::predefinidos() as $codigo => $def) {
            if (! isset($presentes[$codigo])) {
                $slots[] = $def;
            }
        }

        return $slots;
    }
}
