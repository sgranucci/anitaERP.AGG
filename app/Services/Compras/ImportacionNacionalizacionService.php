<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Importacion;
use App\Models\Stock\Lote;
use App\Support\Compras\ImportacionEstados;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Nacionalización: crea/vincula despacho (`lote`) y marca la carpeta.
 */
class ImportacionNacionalizacionService
{
    /**
     * @param  array{numerodespacho: string, pais_id: int, fechaingreso?: string|null}  $despacho
     */
    public function nacionalizar(Importacion $importacion, array $despacho, ?int $loteExistenteId = null): Importacion
    {
        if (! ImportacionEstados::puedeTransicionar((string) $importacion->estado, ImportacionEstados::NACIONALIZADA)
            && (string) $importacion->estado !== ImportacionEstados::NACIONALIZADA) {
            throw new RuntimeException('No se puede nacionalizar desde el estado '.$importacion->estado.'.');
        }

        return DB::transaction(function () use ($importacion, $despacho, $loteExistenteId) {
            if ($loteExistenteId) {
                $lote = Lote::query()->findOrFail($loteExistenteId);
            } else {
                $nro = trim((string) ($despacho['numerodespacho'] ?? ''));
                if ($nro === '') {
                    throw new RuntimeException('Debe indicar el número de despacho aduanero.');
                }
                $paisId = (int) ($despacho['pais_id'] ?? 0);
                if ($paisId <= 0) {
                    throw new RuntimeException('Debe indicar el país de origen del despacho.');
                }
                $lote = Lote::query()->where('numerodespacho', $nro)->first();
                if (! $lote) {
                    $lote = Lote::query()->create([
                        'numerodespacho' => $nro,
                        'pais_id' => $paisId,
                        'fechaingreso' => $despacho['fechaingreso'] ?? now()->toDateString(),
                        'usuario_id' => Auth::id(),
                        'importacion_id' => $importacion->id,
                    ]);
                }
            }

            $lote->importacion_id = $importacion->id;
            $lote->save();

            if (! $importacion->lotes()->where('lote.id', $lote->id)->exists()) {
                $importacion->lotes()->attach($lote->id);
            }

            $importacion->estado = ImportacionEstados::NACIONALIZADA;
            $importacion->fecha_nacionalizacion = $despacho['fechaingreso'] ?? now()->toDateString();
            $importacion->save();

            return $importacion->fresh(['lotes']);
        });
    }

    /**
     * Vincula una recepción COM ya existente a la carpeta (Fase 3).
     */
    public function vincularRecepcion(Importacion $importacion, int $recepcionId): void
    {
        $recepcion = \App\Models\Stock\Recepcion_Proveedor::query()->findOrFail($recepcionId);
        $recepcion->importacion_id = $importacion->id;
        $recepcion->save();

        $loteIds = $importacion->lotes()->pluck('lote.id');
        if ($loteIds->isEmpty()) {
            return;
        }

        // Propaga loteimportacion_id a movimientos de stock de la recepción si existen.
        $movId = (int) ($recepcion->movimientostock_id ?? 0);
        if ($movId <= 0) {
            return;
        }

        $loteImportacionId = (int) $loteIds->first();
        \App\Models\Stock\Articulo_Movimiento::query()
            ->where('movimientostock_id', $movId)
            ->where(function ($q) {
                $q->whereNull('loteimportacion_id')->orWhere('loteimportacion_id', 0);
            })
            ->update(['loteimportacion_id' => $loteImportacionId]);
    }
}
