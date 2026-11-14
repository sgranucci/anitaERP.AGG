<?php

declare(strict_types=1);

namespace App\Services\Ventas\TransferenciaCrown;

use App\Models\Ventas\Venta;
use App\Services\Ventas\FacturacionService;
use App\Services\Ventas\Gastronomia\GastronomiaReplicarVentasAnitaErpService;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownVencaeSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tras Importación ARCA: deja la factura también en Anita (venta + vencae).
 */
final class TransferenciaCrownAnitaReplicaDesdeErpSupport
{
    public function __construct(
        private readonly GastronomiaReplicarVentasAnitaErpService $replicar,
        private readonly FacturacionService $facturacion,
    ) {
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function replicarVentaId(int $ventaId): array
    {
        $venta = Venta::query()
            ->with(['puntoventas', 'tipotransacciones', 'clientes', 'venta_emisiones', 'venta_impuestos'])
            ->find($ventaId);
        if ($venta === null) {
            return ['ok' => false, 'mensaje' => 'Venta '.$ventaId.' no encontrada.'];
        }

        try {
            $this->replicar->replicarVenta($venta, false, true);
            $this->grabarVencae($venta->fresh());

            return ['ok' => true, 'mensaje' => ''];
        } catch (Throwable $e) {
            Log::error('transferencia_crown.replica_anita_arca.fallo', [
                'venta_id' => $ventaId,
                'msg' => $e->getMessage(),
            ]);

            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }
    }

    private function grabarVencae(?Venta $venta): void
    {
        if ($venta === null) {
            return;
        }
        $cae = trim((string) ($venta->cae ?? ''));
        $vto = TransferenciaCrownVencaeSupport::fechaAnitaDesdeIso((string) ($venta->fechavencimientocae ?? ''));
        if ($cae === '' || $vto === '') {
            return;
        }
        $codigo = strtoupper(trim((string) ($venta->codigo ?? '')));
        $tipo = preg_match('/^([A-Z]{2,3})\b/', $codigo, $m) ? $m[1] : 'FAC';
        $letra = preg_match('/\s+([A-Z])(?:[\s\-]|$)/', $codigo, $m2) ? $m2[1] : 'B';
        $pv = (int) ($venta->puntoventas->codigo ?? 0);
        $nro = (int) $venta->numerocomprobante;
        $ok = $this->facturacion->grabaVenCae($tipo, $letra, $pv, $nro, $cae, $vto);
        if ($ok === 'Error') {
            throw new \RuntimeException('vencae Anita falló para '.$venta->codigo);
        }
    }
}
