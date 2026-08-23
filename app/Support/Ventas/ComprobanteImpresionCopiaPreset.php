<?php

namespace App\Support\Ventas;

final class ComprobanteImpresionCopiaPreset
{
    /**
     * @return list<array{codigo: string, leyenda: string, destinatario: string, etiqueta: string}>
     */
    public static function todos(): array
    {
        return [
            ['codigo' => 'ORI', 'leyenda' => 'ORIGINAL', 'destinatario' => 'Cliente', 'etiqueta' => 'Original'],
            ['codigo' => 'DUP', 'leyenda' => 'DUPLICADO', 'destinatario' => 'Chofer', 'etiqueta' => 'Duplicado'],
            ['codigo' => 'TRI', 'leyenda' => 'TRIPLICADO', 'destinatario' => 'Archivo', 'etiqueta' => 'Triplicado'],
            ['codigo' => 'CUA', 'leyenda' => 'CUADRIPLICADO', 'destinatario' => 'Administración', 'etiqueta' => 'Cuadriplicado'],
            ['codigo' => 'QUI', 'leyenda' => 'QUINTUPLICADO', 'destinatario' => 'Depósito', 'etiqueta' => 'Quintuplicado'],
            ['codigo' => 'SEX', 'leyenda' => 'SEXTUPLICADO', 'destinatario' => 'Otro', 'etiqueta' => 'Sextuplicado'],
            ['codigo' => 'NAS', 'leyenda' => 'ARCHIVO', 'destinatario' => 'NAS', 'etiqueta' => 'Archivo NAS'],
        ];
    }

    public static function porCodigo(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        foreach (self::todos() as $preset) {
            if ($preset['codigo'] === $codigo) {
                return $preset;
            }
        }

        return null;
    }
}
