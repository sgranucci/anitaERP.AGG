<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Cm05;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Carbon;

class Proveedor_Cm05Repository implements Proveedor_Cm05RepositoryInterface
{
    public function create(array $data, int $proveedorId): void
    {
        $this->sincronizarDesdeRequest($proveedorId, $data);
    }

    public function update(array $data, int $proveedorId): void
    {
        if (! array_key_exists('cm05_presentes', $data) && ! array_key_exists('cm05_provincia_ids', $data)) {
            return;
        }

        $this->sincronizarDesdeRequest($proveedorId, $data);
    }

    public function sincronizarDesdeRequest(int $proveedorId, array $data): void
    {
        $filas = $this->filasDesdeRequest($data);
        $idsConservar = [];

        foreach ($filas as $fila) {
            $existente = Proveedor_Cm05::query()
                ->where('proveedor_id', $proveedorId)
                ->where('provincia_id', $fila['provincia_id'])
                ->first();

            if ($existente) {
                $existente->update(['fechavigencia' => $fila['fechavigencia']]);
                $idsConservar[] = (int) $existente->id;
                continue;
            }

            $nuevo = Proveedor_Cm05::query()->create([
                'proveedor_id' => $proveedorId,
                'provincia_id' => $fila['provincia_id'],
                'fechavigencia' => $fila['fechavigencia'],
            ]);
            $idsConservar[] = (int) $nuevo->id;
        }

        $borrar = Proveedor_Cm05::query()->where('proveedor_id', $proveedorId);
        if ($idsConservar !== []) {
            $borrar->whereNotIn('id', $idsConservar);
        }
        EloquentAuditDeleteSupport::each($borrar);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array{provincia_id: int, fechavigencia: ?string}>
     */
    private function filasDesdeRequest(array $data): array
    {
        $provinciaIds = $data['cm05_provincia_ids'] ?? [];
        if (! is_array($provinciaIds)) {
            $provinciaIds = [];
        }
        $fechas = $data['cm05_fechavigencias'] ?? [];
        if (! is_array($fechas)) {
            $fechas = [];
        }

        $out = [];
        $vistos = [];
        foreach ($provinciaIds as $i => $rawId) {
            $provinciaId = (int) $rawId;
            if ($provinciaId <= 0 || isset($vistos[$provinciaId])) {
                continue;
            }
            $vistos[$provinciaId] = true;
            $out[] = [
                'provincia_id' => $provinciaId,
                'fechavigencia' => $this->normalizarFecha($fechas[$i] ?? null),
            ];
        }

        return $out;
    }

    private function normalizarFecha(mixed $valor): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
