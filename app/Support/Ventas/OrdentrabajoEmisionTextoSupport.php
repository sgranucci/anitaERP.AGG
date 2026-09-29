<?php

namespace App\Support\Ventas;

/**
 * Parte textos largos de la OT preimpresa (DejaVu Sans Bold 9 pt) sin cortar
 * un material al medio. El renglón de capellada mide 160 mm; un corte fijo a
 * 90 caracteres se pasa de ancho y recorta la palabra.
 *
 * Cada ítem de capellada termina en " -0.00- P" o " -0.00- D" y el siguiente
 * arranca después de "/". Se corta en esa barra. Si no hay barra que entre,
 * se corta en el último espacio.
 */
final class OrdentrabajoEmisionTextoSupport
{
    /** Margen bajo el max-width de 160 mm del campo, para que el overflow no recorte. */
    public const ANCHO_MATERIAL_MM = 156.0;

    public const LINEAS_MATERIAL = 3;

    private const TAMANO_PT = 9.0;

    /**
     * Anchos AFM (WX) de DejaVu Sans Bold, ASCII 32..126.
     *
     * @var list<int>
     */
    private const ANCHO_GLIFO = [
        348, 456, 521, 838, 696, 1002, 872, 306, 457, 457, 523, 838, 380, 415, 380, 365,
        696, 696, 696, 696, 696, 696, 696, 696, 696, 696, 400, 400, 838, 838, 838, 580,
        1000, 774, 762, 734, 830, 683, 683, 821, 837, 372, 372, 775, 637, 995, 837, 850,
        733, 850, 770, 720, 682, 812, 774, 1103, 771, 724, 725, 457, 365, 457, 838, 500,
        500, 675, 716, 593, 716, 678, 435, 716, 712, 343, 343, 665, 343, 1042, 712, 687,
        716, 716, 493, 595, 478, 712, 652, 924, 645, 652, 582, 712, 365, 712, 838,
    ];

    /**
     * @return list<string>
     */
    public static function partirMaterial(string $texto): array
    {
        return self::partir($texto, self::LINEAS_MATERIAL, self::ANCHO_MATERIAL_MM);
    }

    /**
     * @return list<string>
     */
    public static function partir(string $texto, int $lineas, float $anchoMm): array
    {
        $texto = trim($texto);
        $salida = [];
        while (count($salida) < $lineas) {
            if ($texto === '') {
                $salida[] = '';
                continue;
            }
            if (self::anchoMm($texto) <= $anchoMm) {
                $salida[] = $texto;
                $texto = '';
                continue;
            }
            $corte = self::indiceCorte($texto, $anchoMm);
            $salida[] = rtrim(substr($texto, 0, $corte));
            $texto = ltrim(substr($texto, $corte), " /");
        }

        return $salida;
    }

    public static function anchoMm(string $texto): float
    {
        $mm = 0.0;
        $n = strlen($texto);
        for ($i = 0; $i < $n; $i++) {
            $mm += self::anchoCaracter($texto[$i]);
        }

        return $mm;
    }

    private static function indiceCorte(string $texto, float $anchoMm): int
    {
        $n = strlen($texto);
        $fin = 0;
        $acumulado = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $acumulado += self::anchoCaracter($texto[$i]);
            if ($acumulado > $anchoMm) {
                break;
            }
            $fin = $i + 1;
        }
        if ($fin <= 0) {
            return 1;
        }

        $trozo = substr($texto, 0, $fin);
        if (preg_match_all('/(?<=\s[PD])\//', $trozo, $coincidencias, PREG_OFFSET_CAPTURE) && $coincidencias[0] !== []) {
            $ultimo = $coincidencias[0][count($coincidencias[0]) - 1];
            $pos = (int) $ultimo[1];
            if ($pos > 0) {
                return $pos;
            }
        }

        $espacio = strrpos($trozo, ' ');
        if ($espacio !== false && $espacio > 0) {
            return $espacio;
        }

        $barra = strrpos($trozo, '/');
        if ($barra !== false && $barra > 0) {
            return $barra;
        }

        return $fin;
    }

    private static function anchoCaracter(string $caracter): float
    {
        $ord = ord($caracter);
        $wx = ($ord >= 32 && $ord <= 126) ? self::ANCHO_GLIFO[$ord - 32] : 600;

        return $wx / 1000 * self::TAMANO_PT * 25.4 / 72;
    }
}
