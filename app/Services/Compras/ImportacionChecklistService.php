<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Models\Compras\Importacion;
use App\Models\Compras\Importacion_Checklist_Item;

/**
 * Checklist documental de compliance (Fase 4).
 */
class ImportacionChecklistService
{
    /** @var list<array{codigo: string, nombre: string, obligatorio: bool}> */
    public const DEFAULTS = [
        ['codigo' => 'FACTURA', 'nombre' => 'Factura comercial', 'obligatorio' => true],
        ['codigo' => 'BL', 'nombre' => 'Conocimiento de embarque / AWB', 'obligatorio' => true],
        ['codigo' => 'PACKING', 'nombre' => 'Packing list', 'obligatorio' => false],
        ['codigo' => 'ORIGEN', 'nombre' => 'Certificado de origen', 'obligatorio' => false],
        ['codigo' => 'SEGURO', 'nombre' => 'Póliza de seguro', 'obligatorio' => false],
        ['codigo' => 'SIM', 'nombre' => 'SIM / licencia importación', 'obligatorio' => false],
        ['codigo' => 'DESPACHO', 'nombre' => 'Despacho de importación', 'obligatorio' => true],
    ];

    public function asegurarDefaults(Importacion $importacion): void
    {
        foreach (self::DEFAULTS as $item) {
            Importacion_Checklist_Item::query()->firstOrCreate(
                [
                    'importacion_id' => $importacion->id,
                    'codigo' => $item['codigo'],
                ],
                [
                    'nombre' => $item['nombre'],
                    'obligatorio' => $item['obligatorio'],
                    'cumplido' => false,
                ]
            );
        }
    }

    /**
     * @return list<Importacion_Checklist_Item>
     */
    public function vencidos(?int $empresaId = null): array
    {
        $q = Importacion_Checklist_Item::query()
            ->with('importacion')
            ->where('cumplido', false)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->toDateString());

        if ($empresaId) {
            $q->whereHas('importacion', fn ($iq) => $iq->where('empresa_id', $empresaId));
        }

        return $q->orderBy('fecha_vencimiento')->get()->all();
    }

    public function marcar(Importacion_Checklist_Item $item, bool $cumplido, ?string $observacion = null): Importacion_Checklist_Item
    {
        $item->cumplido = $cumplido;
        if ($observacion !== null) {
            $item->observacion = $observacion;
        }
        $item->save();

        return $item;
    }
}
