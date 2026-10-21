<?php

namespace App\Support\Sueldos;

/**
 * Taxonomía de presentación del reporte de estructura (dirección, sigla, convenio, tramos).
 * No es un umbral de negocio: ordena el tablero como el informe de dotación.
 */
final class EstructuraPersonalPresentacion
{
    /** @var list<string> */
    private const ORDEN_CENTRO = [
        'maquinas',
        'sala de maquinas',
        'bingo',
        'sala de bingo',
        'tecnica',
        'gcia. tecnica',
        'gcia tecnica',
        'limpieza',
        'parking',
        'vip',
        'logistica',
        'gastronomia',
        'marketing y cac',
        'seguridad',
        'analisis de slots',
        'obras y mantenimiento',
        'gerencia de tecnologia',
        'tecnologia',
        'sindicatos',
        'tesoreria operativa',
        'tesoreria',
        'legales',
        'capital humano',
        'gcia de administracion',
        'gerencia de administracion',
        'ctrl de gestion y pto',
        'directorio',
    ];

    /** @var array<int, string> */
    private const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    /** @var array<int, string> */
    private const MESES_CORTOS = [
        1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
        5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
    ];

    public static function normalizar(string $texto): string
    {
        $t = mb_strtolower(trim($texto));
        $t = strtr($t, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'ä' => 'a', 'ë' => 'e', 'ï' => 'i', 'ö' => 'o', 'ü' => 'u',
            'ñ' => 'n',
        ]);
        $t = preg_replace('/[^a-z0-9]+/', ' ', $t) ?? $t;

        return trim($t);
    }

    public static function mesNombre(int $mes): string
    {
        return self::MESES[$mes] ?? (string) $mes;
    }

    public static function mesCorto(int $mes): string
    {
        return self::MESES_CORTOS[$mes] ?? (string) $mes;
    }

    public static function sigla(string $empresa): string
    {
        $n = self::normalizar($empresa);
        if (str_contains($n, 'biyema')) {
            return 'BSA';
        }
        if (str_contains($n, 'kandiko')) {
            return 'KSA';
        }
        if (str_contains($n, 'rebisco')) {
            return 'RSA';
        }
        $letras = preg_replace('/[^A-Z]/', '', mb_strtoupper($empresa)) ?? '';

        return $letras !== '' ? mb_substr($letras, 0, 3) : 'EMP';
    }

    /**
     * @return array{id: string, nombre: string, badge: string}
     */
    public static function direccion(string $centro): array
    {
        $n = self::normalizar($centro);
        if ($n === '' || $n === 'sin centro de costo') {
            return ['id' => 'otras', 'nombre' => 'Sin centro de costo', 'badge' => 'svc'];
        }
        foreach (['tesoreria', 'legal', 'capital humano', 'administracion', 'gestion', 'directorio'] as $clave) {
            if (str_contains($n, $clave)) {
                return ['id' => 'admin', 'nombre' => 'Dirección Adm. y Finanzas', 'badge' => 'fin'];
            }
        }

        return ['id' => 'operaciones', 'nombre' => 'Dirección de Operaciones', 'badge' => 'op'];
    }

    public static function ordenCentro(string $centro): int
    {
        $n = self::normalizar($centro);
        $i = array_search($n, self::ORDEN_CENTRO, true);

        return $i === false ? 1000 : (int) $i;
    }

    public static function convenio(string $sindicato): string
    {
        $n = self::normalizar($sindicato);
        if ($n === '') {
            return 'Sin dato';
        }
        if (str_contains($n, 'fuera')) {
            return 'Fuera de convenio';
        }

        return 'Dentro de convenio';
    }

    public static function sexo(?string $valor): string
    {
        $v = strtoupper(trim((string) $valor));

        return match ($v) {
            '1', 'M' => 'M',
            '2', 'F' => 'F',
            default => '',
        };
    }

    public static function esConduccion(string $categoria): bool
    {
        $n = self::normalizar($categoria);

        return $n !== '' && (str_contains($n, 'director') || str_contains($n, 'manager'));
    }

    public static function tramoEdad(?int $edad): string
    {
        if ($edad === null || $edad < 0) {
            return 'Sin dato';
        }
        if ($edad < 25) {
            return 'Hasta 24';
        }
        if ($edad < 35) {
            return '25 a 34';
        }
        if ($edad < 45) {
            return '35 a 44';
        }
        if ($edad < 55) {
            return '45 a 54';
        }

        return '55 o más';
    }

    public static function tramoAntiguedad(?float $anios): string
    {
        if ($anios === null || $anios < 0) {
            return 'Sin dato';
        }
        if ($anios < 1) {
            return 'Menos de 1 año';
        }
        if ($anios < 3) {
            return '1 a 3 años';
        }
        if ($anios < 5) {
            return '3 a 5 años';
        }
        if ($anios < 10) {
            return '5 a 10 años';
        }
        if ($anios < 20) {
            return '10 a 20 años';
        }

        return 'Más de 20 años';
    }

    public static function ordenSigla(string $sigla): int
    {
        return match ($sigla) {
            'BSA' => 0,
            'KSA' => 1,
            'RSA' => 2,
            default => 10,
        };
    }
}
