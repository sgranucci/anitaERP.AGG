<?php

namespace App\Support\Compras\Tracking;

/**
 * Antigüedad de la deuda al estilo SAP / Oracle AP aging.
 *
 * Se mide desde el **vencimiento** cuando existe; si no, desde la fecha del
 * comprobante. Los días negativos son «a vencer» (corriente); los positivos
 * son días de atraso.
 *
 * Tramos: a vencer · 0-30 · 31-60 · 61-90 · +90.
 */
final class TrackingAntiguedadDeuda
{
    public const CORRIENTE = 'corriente';

    public const HASTA_30 = '0_30';

    public const DE_31_A_60 = '31_60';

    public const DE_61_A_90 = '61_90';

    public const MAS_DE_90 = '90_mas';

    public const ORIGEN_VENCIMIENTO = 'vencimiento';

    public const ORIGEN_COMPROBANTE = 'comprobante';

    /** @var array<string, array{label: string, min: int|null, max: int|null}> */
    public const TRAMOS = [
        self::CORRIENTE => ['label' => 'A vencer', 'min' => null, 'max' => -1],
        self::HASTA_30 => ['label' => '0-30 días', 'min' => 0, 'max' => 30],
        self::DE_31_A_60 => ['label' => '31-60 días', 'min' => 31, 'max' => 60],
        self::DE_61_A_90 => ['label' => '61-90 días', 'min' => 61, 'max' => 90],
        self::MAS_DE_90 => ['label' => '+90 días', 'min' => 91, 'max' => null],
    ];

    /**
     * Expresión SQL de la fecha base del aging (vencimiento si es usable).
     *
     * Se usa en el resumen y en los filtros para que la grilla y las tarjetas
     * no puedan contradecirse.
     */
    public static function sqlFechaBase(string $aliasComprobante = 'comprobante_proveedor'): string
    {
        $vto = $aliasComprobante.'.fechavencimiento';
        $comp = $aliasComprobante.'.fechacomprobante';

        return 'case'
            .' when '.$vto.' is not null'
            .' and '.$vto." >= '2000-01-01'"
            .' and '.$vto.' <= date_add(curdate(), interval 20 year)'
            .' then '.$vto
            .' else '.$comp
            .' end';
    }

    /**
     * @return list<string>
     */
    public static function claves(): array
    {
        return array_keys(self::TRAMOS);
    }

    public static function esTramoValido(?string $tramo): bool
    {
        return $tramo !== null && isset(self::TRAMOS[$tramo]);
    }

    public static function etiqueta(?string $tramo): string
    {
        return self::TRAMOS[$tramo]['label'] ?? '';
    }

    /**
     * Elige la fecha base: vencimiento válido gana; si no, fecha del comprobante.
     *
     * @return array{0: string|null, 1: string|null} [fecha ISO, origen]
     */
    public static function fechaBase(?string $fechavencimiento, ?string $fechacomprobante): array
    {
        $vto = self::normalizarFecha($fechavencimiento);
        if ($vto !== null) {
            return [$vto, self::ORIGEN_VENCIMIENTO];
        }

        $comp = self::normalizarFecha($fechacomprobante);
        if ($comp !== null) {
            return [$comp, self::ORIGEN_COMPROBANTE];
        }

        return [null, null];
    }

    /**
     * Días relativos a hoy: negativo = a vencer, positivo = vencido.
     */
    public static function dias(?string $fechaBase, ?\DateTimeInterface $hoy = null): ?int
    {
        $fecha = self::normalizarFecha($fechaBase);
        if ($fecha === null) {
            return null;
        }

        $desde = \DateTimeImmutable::createFromFormat('Y-m-d', $fecha);
        if ($desde === false) {
            return null;
        }

        $hasta = $hoy instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($hoy)->setTime(0, 0)
            : new \DateTimeImmutable('today');

        return (int) $desde->diff($hasta)->format('%r%a');
    }

    public static function tramo(?int $dias): ?string
    {
        if ($dias === null) {
            return null;
        }

        return match (true) {
            $dias < 0 => self::CORRIENTE,
            $dias <= 30 => self::HASTA_30,
            $dias <= 60 => self::DE_31_A_60,
            $dias <= 90 => self::DE_61_A_90,
            default => self::MAS_DE_90,
        };
    }

    public static function clasePill(?string $tramo): string
    {
        return match ($tramo) {
            self::CORRIENTE => 'tf-ok',
            self::HASTA_30 => 'tf-neutro',
            self::DE_31_A_60 => 'tf-pendiente',
            self::DE_61_A_90, self::MAS_DE_90 => 'tf-alerta',
            default => 'tf-neutro',
        };
    }

    /**
     * Predicado SQL para filtrar un tramo sobre la fecha base.
     *
     * @return array{0: string, 1: list<mixed>}
     */
    public static function sqlPredicadoTramo(string $tramo, string $aliasComprobante = 'comprobante_proveedor'): array
    {
        $fecha = '('.self::sqlFechaBase($aliasComprobante).')';
        $dias = 'datediff(curdate(), '.$fecha.')';

        return match ($tramo) {
            self::CORRIENTE => [$dias.' < 0', []],
            self::HASTA_30 => [$dias.' between 0 and 30', []],
            self::DE_31_A_60 => [$dias.' between 31 and 60', []],
            self::DE_61_A_90 => [$dias.' between 61 and 90', []],
            self::MAS_DE_90 => [$dias.' > 90', []],
            default => ['1 = 0', []],
        };
    }

    private static function normalizarFecha(?string $valor): ?string
    {
        $fecha = substr(trim((string) $valor), 0, 10);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $fecha, $partes)) {
            return null;
        }

        [, $anio, $mes, $dia] = $partes;
        if ((int) $anio < 2000 || ! checkdate((int) $mes, (int) $dia, (int) $anio)) {
            return null;
        }

        // Un vencimiento a 20+ años es basura de datos (cuotas mal cargadas).
        $limite = (new \DateTimeImmutable('today'))->modify('+20 years')->format('Y-m-d');
        if ($fecha > $limite) {
            return null;
        }

        return $fecha;
    }
}
