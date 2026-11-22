<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Cliente;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Ventas\ClienteSuspensionAvisoSupport;
use Illuminate\Support\Facades\Log;

class ClienteSuspensionAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despacharSiPasoASuspendido(int $clienteId, string $estadoAnterior, string $estadoNuevo): void
    {
        if ($clienteId <= 0) {
            return;
        }
        if (trim($estadoNuevo) !== Cliente::ESTADO_SUSPENDIDO) {
            return;
        }
        if (trim($estadoAnterior) === Cliente::ESTADO_SUSPENDIDO) {
            return;
        }

        try {
            $this->moduloAvisoService->enviar(
                ClienteSuspensionAvisoSupport::MODULO,
                ClienteSuspensionAvisoSupport::CODIGO,
                $clienteId
            );
        } catch (\Throwable $e) {
            Log::warning('cliente_suspension.aviso_fallo', [
                'cliente_id' => $clienteId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
