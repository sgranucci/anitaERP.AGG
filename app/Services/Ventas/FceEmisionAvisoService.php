<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Ventas\FceEmisionAvisoSupport;
use Illuminate\Support\Facades\Log;

class FceEmisionAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despacharSiCorresponde(int $ventaId): void
    {
        if ($ventaId <= 0) {
            return;
        }

        try {
            $venta = Venta::query()
                ->with(['tipotransacciones:id,abreviatura'])
                ->find($ventaId);
            if (! $venta) {
                return;
            }

            $abrev = strtoupper(trim((string) (optional($venta->tipotransacciones)->abreviatura ?? '')));
            if ($abrev === '' && is_string($venta->codigo ?? null)) {
                $abrev = strtoupper(substr(trim((string) $venta->codigo), 0, 3));
            }
            if (! FceEmisionAvisoSupport::esAbreviaturaFce($abrev)) {
                return;
            }

            $this->moduloAvisoService->enviar(
                FceEmisionAvisoSupport::MODULO,
                FceEmisionAvisoSupport::CODIGO,
                $ventaId
            );
        } catch (\Throwable $e) {
            Log::warning('fce_emision.aviso_fallo', [
                'venta_id' => $ventaId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
