<?php

namespace App\Support\Compras;

use Illuminate\Database\Eloquent\Builder;

/**
 * El buscador de tipos de comprobante de compras entiende «no retiene» / «retiene»
 * sobre retieneiva, retieneganancia y retieneIIBB. Esas marcas no están en el nombre.
 */
final class TipotransaccionCompraRetencionFiltro
{
    /**
     * @param  Builder<\App\Models\Compras\Tipotransaccion_Compra>  $query
     */
    public static function aplicar(Builder $query, string $consulta): void
    {
        $texto = trim($consulta);
        if ($texto === '') {
            return;
        }

        $criterio = self::interpretar($texto);
        if ($criterio === null) {
            self::aplicarTexto($query, $texto);

            return;
        }

        self::aplicarFlags($query, $criterio['valor'], $criterio['campo']);
        if ($criterio['resto'] !== '') {
            self::aplicarTexto($query, $criterio['resto']);
        }
    }

    /**
     * @return array{valor: string, campo: ?string, resto: string}|null
     */
    public static function interpretar(string $consulta): ?array
    {
        $texto = self::normalizar($consulta);
        if ($texto === '') {
            return null;
        }

        $frases = [
            'no retiene ingresos brutos' => ['N', 'retieneIIBB'],
            'no retiene ganancias' => ['N', 'retieneganancia'],
            'no retiene ganancia' => ['N', 'retieneganancia'],
            'no retiene iibb' => ['N', 'retieneIIBB'],
            'no retiene iva' => ['N', 'retieneiva'],
            'no retiene' => ['N', null],
            'retiene ingresos brutos' => ['S', 'retieneIIBB'],
            'retiene ganancias' => ['S', 'retieneganancia'],
            'retiene ganancia' => ['S', 'retieneganancia'],
            'retiene iibb' => ['S', 'retieneIIBB'],
            'retiene iva' => ['S', 'retieneiva'],
            'retiene' => ['S', null],
        ];

        foreach ($frases as $frase => [$valor, $campo]) {
            if (! str_contains($texto, $frase)) {
                continue;
            }

            $resto = trim((string) preg_replace('/\s+/', ' ', str_replace($frase, ' ', $texto)));

            return [
                'valor' => $valor,
                'campo' => $campo,
                'resto' => $resto,
            ];
        }

        return null;
    }

    /**
     * @param  Builder<\App\Models\Compras\Tipotransaccion_Compra>  $query
     */
    private static function aplicarFlags(Builder $query, string $valor, ?string $campo): void
    {
        if ($campo !== null) {
            $query->where($campo, $valor);

            return;
        }

        if ($valor === 'N') {
            $query->where('retieneiva', 'N')
                ->where('retieneganancia', 'N')
                ->where('retieneIIBB', 'N');

            return;
        }

        $query->where(function ($q) {
            $q->where('retieneiva', 'S')
                ->orWhere('retieneganancia', 'S')
                ->orWhere('retieneIIBB', 'S');
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Tipotransaccion_Compra>  $query
     */
    private static function aplicarTexto(Builder $query, string $texto): void
    {
        $like = '%'.addcslashes($texto, '%_\\').'%';
        $query->where(function ($q) use ($like) {
            $q->where('abreviatura', 'LIKE', $like)
                ->orWhere('nombre', 'LIKE', $like);
        });
    }

    private static function normalizar(string $consulta): string
    {
        $texto = mb_strtolower(trim($consulta));
        $texto = strtr($texto, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u',
            'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u',
        ]);
        $texto = (string) preg_replace('/[^a-z0-9]+/u', ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }
}
