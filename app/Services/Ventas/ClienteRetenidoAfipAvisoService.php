<?php

namespace App\Services\Ventas;

use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Ventas\ClienteRetenidoAfipAvisoSupport;
use Illuminate\Support\Facades\Log;

class ClienteRetenidoAfipAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @param  array{motivo?: string, detalle?: string}  $opciones
     */
    public function despachar(int $clienteId, array $opciones = []): void
    {
        if ($clienteId <= 0) {
            return;
        }

        try {
            $this->moduloAvisoService->enviar(
                ClienteRetenidoAfipAvisoSupport::MODULO,
                ClienteRetenidoAfipAvisoSupport::CODIGO,
                $clienteId,
                $opciones
            );
        } catch (\Throwable $e) {
            Log::warning('cliente_retenido_afip.aviso_fallo', [
                'cliente_id' => $clienteId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
