<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Importacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sincroniza datos mínimos de la carpeta hacia libro_iva_importacion_bien (cbte 066).
 */
class ImportacionLibroIvaSyncService
{
    /**
     * @param  array{
     *   fecha: string,
     *   despacho?: string,
     *   importe: float,
     *   iva?: float,
     *   cuit_despachante?: string,
     *   razon_social?: string
     * }  $datos
     */
    public function sincronizarBien(Importacion $importacion, array $datos): bool
    {
        if (! Schema::hasTable('libro_iva_importacion_bien')) {
            return false;
        }

        $despacho = (string) ($datos['despacho']
            ?? $importacion->lotes()->value('numerodespacho')
            ?? $importacion->codigo);

        $payload = [
            'fecha' => $datos['fecha'],
            'despacho' => $despacho,
            'importe' => (float) $datos['importe'],
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('libro_iva_importacion_bien', 'iva')) {
            $payload['iva'] = (float) ($datos['iva'] ?? 0);
        }
        if (Schema::hasColumn('libro_iva_importacion_bien', 'cuit_despachante') && isset($datos['cuit_despachante'])) {
            $payload['cuit_despachante'] = $datos['cuit_despachante'];
        }
        if (Schema::hasColumn('libro_iva_importacion_bien', 'razon_social') && isset($datos['razon_social'])) {
            $payload['razon_social'] = $datos['razon_social'];
        }
        if (Schema::hasColumn('libro_iva_importacion_bien', 'importacion_id')) {
            $payload['importacion_id'] = $importacion->id;
        }
        if (Schema::hasColumn('libro_iva_importacion_bien', 'empresa_id')) {
            $payload['empresa_id'] = $importacion->empresa_id;
        }

        $existeId = null;
        if (Schema::hasColumn('libro_iva_importacion_bien', 'importacion_id')) {
            $existeId = DB::table('libro_iva_importacion_bien')
                ->where('importacion_id', $importacion->id)
                ->value('id');
        }

        if ($existeId) {
            DB::table('libro_iva_importacion_bien')->where('id', $existeId)->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::table('libro_iva_importacion_bien')->insert($payload);
        }

        return true;
    }
}
