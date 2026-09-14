<?php

namespace App\Support\Stock;

/**
 * Resuelve ruta absoluta de foto de combinación / artículo (calzado Ferli).
 */
final class ArticuloCombinacionFotoSupport
{
    /**
     * @return string|null path absoluto legible por PhpSpreadsheet Drawing
     */
    public static function rutaAbsoluta(?string $foto, ?string $sku = null, ?string $codigoCombinacion = null): ?string
    {
        $candidatos = [];

        $foto = trim((string) $foto);
        if ($foto !== '') {
            $candidatos[] = $foto;
            if (! str_contains($foto, '.')) {
                $candidatos[] = $foto.'.jpg';
            }
        }

        $sku = trim((string) $sku);
        $codigo = trim((string) $codigoCombinacion);
        if ($sku !== '' && $codigo !== '') {
            $candidatos[] = $sku.'-'.$codigo.'.jpg';
            $candidatos[] = $sku.'-'.$codigo.'.jpeg';
            $candidatos[] = $sku.'-'.$codigo.'.png';
        }

        $bases = [
            storage_path('app/public/imagenes/fotos_articulos'),
            public_path('storage/imagenes/fotos_articulos'),
        ];

        foreach ($candidatos as $nombre) {
            $nombre = ltrim(str_replace(['\\', '..'], ['/', ''], $nombre), '/');
            if ($nombre === '') {
                continue;
            }
            foreach ($bases as $base) {
                $path = $base.DIRECTORY_SEPARATOR.$nombre;
                if (is_file($path) && is_readable($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
