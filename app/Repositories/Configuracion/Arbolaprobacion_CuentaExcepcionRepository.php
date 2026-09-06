<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Arbolaprobacion_CuentaExcepcion;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Schema;

class Arbolaprobacion_CuentaExcepcionRepository implements Arbolaprobacion_CuentaExcepcionRepositoryInterface
{
    public function syncFromRequest(array $data, int $arbolaprobacionId): void
    {
        if (! Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            return;
        }

        if (! isset($data['re_exc_ids']) || ! is_array($data['re_exc_ids'])) {
            $data['re_exc_ids'] = [];
        }

        $ids = $data['re_exc_ids'];
        $count = count($ids);
        $guardados = [];

        for ($i = 0; $i < $count; $i++) {
            $centrocostoId = (int) ($data['re_exc_centrocosto_ids'][$i] ?? 0);
            $empresaId = (int) ($data['re_exc_empresa_ids'][$i] ?? 0);
            $cuentaId = (int) ($data['re_exc_cuentacontable_ids'][$i] ?? 0);
            if ($centrocostoId <= 0 || $empresaId <= 0 || $cuentaId <= 0) {
                continue;
            }

            $rowId = isset($ids[$i]) && $ids[$i] !== '' ? (int) $ids[$i] : null;
            $activo = strtoupper(trim((string) ($data['re_exc_activos'][$i] ?? 'S'))) === 'N' ? 'N' : 'S';

            $payload = [
                'arbolaprobacion_id' => $arbolaprobacionId,
                'centrocosto_id' => $centrocostoId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuentaId,
                'activo' => $activo,
            ];

            if ($rowId) {
                $existente = Arbolaprobacion_CuentaExcepcion::query()
                    ->where('id', $rowId)
                    ->where('arbolaprobacion_id', $arbolaprobacionId)
                    ->first();
                if ($existente) {
                    $existente->update($payload);
                    $guardados[] = $rowId;

                    continue;
                }
            }

            $creado = Arbolaprobacion_CuentaExcepcion::query()->create($payload);
            $guardados[] = (int) $creado->id;
        }

        $query = Arbolaprobacion_CuentaExcepcion::query()->where('arbolaprobacion_id', $arbolaprobacionId);
        if ($guardados !== []) {
            $query->whereNotIn('id', $guardados);
        }
        EloquentAuditDeleteSupport::each($query);
    }

    public function deleteByArbol(int $arbolaprobacionId): void
    {
        if (! Schema::hasTable('arbolaprobacion_cuenta_excepcion')) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Arbolaprobacion_CuentaExcepcion::query()->where('arbolaprobacion_id', $arbolaprobacionId)
        );
    }
}
