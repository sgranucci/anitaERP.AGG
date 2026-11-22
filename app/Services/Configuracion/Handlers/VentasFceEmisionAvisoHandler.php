<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Ventas\Venta;
use App\Support\Navegacion\ModoConsultaUrlSupport;

class VentasFceEmisionAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $venta = Venta::query()
            ->with(['puntoventas:id,empresa_id'])
            ->find($entityId);
        $empresaId = (int) (optional($venta?->puntoventas)->empresa_id ?? 0);

        return [
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $venta = $this->cargar($entityId);
        $abrev = strtoupper(trim((string) (optional($venta->tipotransacciones)->abreviatura ?? '')));
        if ($abrev === '' && is_string($venta->codigo ?? null)) {
            $abrev = strtoupper(substr(trim((string) $venta->codigo), 0, 3));
        }
        $numero = (string) ($venta->numerocomprobante ?? '');
        if ($numero === '') {
            $numero = (string) ($venta->codigo ?? $entityId);
        }

        return [
            'tipo' => $abrev !== '' ? $abrev : 'FCE',
            'numero' => $numero,
            'codigo' => (string) ($venta->codigo ?? ''),
            'cliente' => (string) (optional($venta->clientes)->nombre ?? '—'),
            'cliente_codigo' => (string) (optional($venta->clientes)->codigo ?? ''),
            'fecha' => $venta->fecha ? $venta->fecha->format('d/m/Y') : '—',
            'total' => number_format((float) ($venta->total ?? 0), 2, ',', '.'),
            'empresa' => (string) (optional(optional($venta->puntoventas)->empresas)->nombre ?? '—'),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return ModoConsultaUrlSupport::route('editar_factura', ['id' => $entityId]);
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function cargar(int $entityId): Venta
    {
        return Venta::query()
            ->with([
                'clientes:id,codigo,nombre',
                'tipotransacciones:id,abreviatura,nombre',
                'puntoventas:id,empresa_id',
                'puntoventas.empresas:id,nombre',
            ])
            ->findOrFail($entityId);
    }
}
