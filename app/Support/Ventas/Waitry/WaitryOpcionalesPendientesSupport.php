<?php

namespace App\Support\Ventas\Waitry;

/**
 * Ítems Waitry omitidos en importación por opcionales de fórmula (carga manual en POS).
 */
final class WaitryOpcionalesPendientesSupport
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    public static function normalizarLineas(array $lineas): array
    {
        $out = [];
        foreach ($lineas as $ln) {
            if (! is_array($ln)) {
                continue;
            }
            $sku = trim((string) ($ln['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $cantidad = (float) ($ln['cantidad'] ?? 1);
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }
            $out[] = [
                'sku' => $sku,
                'titulo' => trim((string) ($ln['titulo'] ?? '')),
                'cantidad' => $cantidad,
                'precio_unitario' => (float) ($ln['precio_unitario'] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{sku:string,titulo?:string,cantidad?:float}>  $lineas
     * @return list<string>
     */
    public static function etiquetasParaAviso(array $lineas): array
    {
        $etiquetas = [];
        foreach (self::normalizarLineas($lineas) as $ln) {
            $etiquetas[] = $ln['sku'].self::sufijoCantidad($ln['cantidad']);
        }

        return $etiquetas;
    }

    /**
     * Fallback cuando la API vieja solo devolvía mensajes de error (cantidad 1).
     *
     * @param  list<string>  $errores
     * @return list<array{sku:string,titulo:string,cantidad:float,precio_unitario:float}>
     */
    public static function desdeErrores(array $errores): array
    {
        $out = [];
        foreach ($errores as $err) {
            $err = trim((string) $err);
            if ($err === '') {
                continue;
            }
            $esOpcional = str_contains($err, 'requiere opcionales de fórmula')
                || str_contains($err, 'modal de opcionales')
                || str_contains($err, 'Debe seleccionar opcional');
            if (! $esOpcional) {
                continue;
            }

            $sku = '';
            $titulo = '';
            $cantidad = 1.0;
            if (preg_match('/SKU «([^»]+)»(?:\s*\(([^)]*)\))?(?:\s*×\s*([0-9]+(?:[.,][0-9]+)?))?/u', $err, $m)) {
                $sku = trim($m[1]);
                $titulo = trim((string) ($m[2] ?? ''));
                if (isset($m[3]) && $m[3] !== '') {
                    $cantidad = (float) str_replace(',', '.', $m[3]);
                }
            } elseif (preg_match('/^([A-Za-z0-9]+):\s*Debe seleccionar opcional/u', $err, $m)) {
                $sku = $m[1];
            }

            if ($sku === '') {
                continue;
            }
            if ($cantidad <= 0) {
                $cantidad = 1.0;
            }

            $out[] = [
                'sku' => $sku,
                'titulo' => $titulo,
                'cantidad' => $cantidad,
                'precio_unitario' => 0.0,
            ];
        }

        return $out;
    }

    public static function sufijoCantidad(float $cantidad): string
    {
        if (abs($cantidad - 1.0) < 0.0001) {
            return '';
        }

        if (fmod($cantidad, 1.0) < 0.0001) {
            return ' ×'.(string) (int) round($cantidad);
        }

        return ' ×'.rtrim(rtrim(number_format($cantidad, 4, '.', ''), '0'), '.');
    }
}
