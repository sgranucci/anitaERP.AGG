<?php

namespace App\Services\Ventas;

use App\Models\Ventas\Venta;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Ventas\CobranzaNclDescuentoAvisoSupport;
use Illuminate\Support\Facades\Log;

class CobranzaNclDescuentoAvisoService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despacharSiCorresponde(int $ventaNcId): void
    {
        if ($ventaNcId <= 0) {
            return;
        }

        try {
            $venta = Venta::query()
                ->with(['tipotransacciones:id,abreviatura'])
                ->find($ventaNcId);
            if (! $venta) {
                return;
            }

            $abrev = strtoupper(trim((string) (optional($venta->tipotransacciones)->abreviatura ?? '')));
            if ($abrev === '' && is_string($venta->codigo ?? null)) {
                $abrev = strtoupper(substr(trim((string) $venta->codigo), 0, 3));
            }
            if ($abrev !== 'NCL') {
                return;
            }

            $this->moduloAvisoService->enviar(
                CobranzaNclDescuentoAvisoSupport::MODULO,
                CobranzaNclDescuentoAvisoSupport::CODIGO,
                $ventaNcId
            );
        } catch (\Throwable $e) {
            Log::warning('ncl_descuento_cobranza.aviso_fallo', [
                'venta_id' => $ventaNcId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
