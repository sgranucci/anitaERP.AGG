<?php

declare(strict_types=1);

namespace App\Support\Caja\Macro;

/**
 * Genera ZIP (o TXT sueltos) con BNF / OPG / RTN — canal diskette.
 */
final class MacroPagoCanalArchivo implements MacroPagoCanal
{
    public function codigo(): string
    {
        return 'archivo';
    }

    public function exportar(array $lote): array
    {
        $archivos = MacroArchivoPagoFormatoSupport::generarArchivos(
            $lote['beneficiarios'] ?? [],
            $lote['ordenes'] ?? [],
            $lote['retenciones'] ?? []
        );

        $zipBin = MacroArchivoPagoFormatoSupport::empaquetarZip($archivos);
        if ($zipBin === null) {
            // Fallback: solo OPG si no hay ZipArchive
            $opg = $archivos[MacroArchivoPagoFormatoSupport::ARCHIVO_OPG] ?? '';

            return [
                'ok' => $opg !== '',
                'mensaje' => $opg !== '' ? 'Archivo OPG generado.' : 'Sin órdenes para exportar.',
                'contenido' => $opg,
                'nombre' => 'OPG.TXT',
                'mime' => 'text/plain; charset=UTF-8',
                'archivos' => $archivos,
            ];
        }

        $stamp = date('Ymd_His');

        return [
            'ok' => true,
            'mensaje' => 'ZIP Macro (BNF/OPG/RTN) generado.',
            'contenido' => $zipBin,
            'nombre' => 'macro_pagos_'.$stamp.'.zip',
            'mime' => 'application/zip',
            'archivos' => $archivos,
        ];
    }
}
