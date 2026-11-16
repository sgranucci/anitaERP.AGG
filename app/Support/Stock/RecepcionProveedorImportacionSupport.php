<?php

declare(strict_types=1);

namespace App\Support\Stock;

use App\Models\Compras\Ordencompra;
use App\Support\Compras\ImportacionEstados;

/**
 * Contexto de carpeta de importación heredado desde la OC hacia la COM.
 */
final class RecepcionProveedorImportacionSupport
{
    /**
     * @return array{
     *   tiene_importacion: bool,
     *   importacion_id: int|null,
     *   importacion_codigo: string,
     *   importacion_estado: string,
     *   incoterm_id: int|null,
     *   incoterm_abreviatura: string,
     *   incoterm_nombre: string,
     *   numerodespacho: string
     * }
     */
    public static function contextoDesdeOrdencompra(?Ordencompra $oc): array
    {
        $vacio = [
            'tiene_importacion' => false,
            'importacion_id' => null,
            'importacion_codigo' => '',
            'importacion_estado' => '',
            'incoterm_id' => null,
            'incoterm_abreviatura' => '',
            'incoterm_nombre' => '',
            'numerodespacho' => '',
        ];

        if (! $oc) {
            return $vacio;
        }

        $oc->loadMissing(['incoterm', 'importaciones.lotes']);

        $importacion = $oc->importaciones->first();
        $incoterm = $oc->incoterm;
        $despacho = '';
        if ($importacion) {
            $lote = $importacion->lotes->first();
            $despacho = trim((string) ($lote->numerodespacho ?? ''));
        }

        $tiene = $importacion !== null || $incoterm !== null;

        return [
            'tiene_importacion' => $tiene,
            'importacion_id' => $importacion ? (int) $importacion->id : null,
            'importacion_codigo' => $importacion ? (string) $importacion->codigo : '',
            'importacion_estado' => $importacion
                ? ImportacionEstados::etiqueta((string) $importacion->estado)
                : '',
            'incoterm_id' => $incoterm ? (int) $incoterm->id : null,
            'incoterm_abreviatura' => $incoterm ? (string) $incoterm->abreviatura : '',
            'incoterm_nombre' => $incoterm ? (string) $incoterm->nombre : '',
            'numerodespacho' => $despacho,
        ];
    }

    public static function importacionIdDesdeOrdencompra(?Ordencompra $oc): ?int
    {
        if (! $oc) {
            return null;
        }
        $oc->loadMissing('importaciones');
        $id = (int) ($oc->importaciones->first()->id ?? 0);

        return $id > 0 ? $id : null;
    }
}
