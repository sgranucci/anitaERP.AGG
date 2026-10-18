<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Proveedor_Sala;
use App\Support\Database\EloquentAuditDeleteSupport;

class Proveedor_SalaRepository implements Proveedor_SalaRepositoryInterface
{
    public function create(array $data, int $proveedorId): void
    {
        $this->reemplazar($proveedorId, $this->idsDesdeRequest($data));
    }

    public function update(array $data, int $proveedorId): void
    {
        if (! array_key_exists('sala_ids', $data) && ! array_key_exists('salas_presentes', $data)) {
            return;
        }

        $this->reemplazar($proveedorId, $this->idsDesdeRequest($data));
    }

    public function reemplazar(int $proveedorId, array $salaIds): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $salaIds), static fn (int $id) => $id > 0)));

        EloquentAuditDeleteSupport::each(
            Proveedor_Sala::query()->where('proveedor_id', $proveedorId)
        );

        foreach ($ids as $salaId) {
            Proveedor_Sala::query()->create([
                'proveedor_id' => $proveedorId,
                'sala_id' => $salaId,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function idsDesdeRequest(array $data): array
    {
        $raw = $data['sala_ids'] ?? [];
        if (! is_array($raw)) {
            $raw = $raw !== null && $raw !== '' ? [$raw] : [];
        }

        return array_values(array_filter(array_map('intval', $raw), static fn (int $id) => $id > 0));
    }
}
