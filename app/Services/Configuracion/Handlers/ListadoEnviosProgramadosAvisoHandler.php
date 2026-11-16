<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Models\Configuracion\ModuloAvisoTipo;
use Illuminate\Support\Facades\Log;

/**
 * Marcador para listado.envios_workbench / consulta_inteligente.envios_programados.
 * El envío real lo hace el comando Artisan del Programador (no ModuloAvisoService::enviar).
 */
class ListadoEnviosProgramadosAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        Log::info('ListadoEnviosProgramadosAvisoHandler: sin despacho directo; usar Programador / comando', [
            'clave' => $tipo->claveCompleta(),
            'entity_id' => $entityId,
        ]);
    }

    public function contextoFiltro(int $entityId): array
    {
        return [
            'empresa_id' => null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        return [];
    }

    public function linkConsulta(int $entityId): ?string
    {
        return null;
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }
}
