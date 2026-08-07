<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSala;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Support\Sala\RequisicionSalaDepositoLaboratorioSupport;
use App\Support\Sala\RequisicionSalaLineasLaboratorioSupport;
use App\Support\Sala\RequisicionSalaNpuTransferenciaSupport;
use App\Support\Sala\RequisicionSalaTransferenciaAsociadaSupport;
use Auth;
use Illuminate\Support\Facades\Log;

/**
 * Transferencia de stock al laboratorio tras aprobación del árbol (ítems reparación/devolución).
 * Destino: depósito laboratorio compartido (código/empresa configurados, tip. 406 Biyemas).
 */
class RequisicionSalaTransferenciaLaboratorioService
{
    public function __construct(
        private TransferenciaMercaderiaService $transferenciaService,
    ) {
    }

    public function ejecutarSiCorresponde(int $requisicionSalaId, int $usuarioId): void
    {
        $req = RequisicionSala::query()
            ->with('requisicion_sala_articulos')
            ->find($requisicionSalaId);

        if (! $req || (int) $req->deposito_id <= 0) {
            return;
        }

        if ($this->yaEjecutada($req)) {
            return;
        }

        $lineas = RequisicionSalaLineasLaboratorioSupport::payloadLineasTransferencia($req);
        if ($lineas === []) {
            return;
        }

        RequisicionSalaNpuTransferenciaSupport::asegurarRegistrados($lineas);

        $depositoLabId = RequisicionSalaDepositoLaboratorioSupport::resolverId();
        if ($depositoLabId <= 0) {
            Log::warning('RequisicionSala: depósito laboratorio no configurado o inexistente', [
                'requisicion_sala_id' => $requisicionSalaId,
                'codigo' => RequisicionSalaDepositoLaboratorioSupport::codigoConfigurado(),
                'empresa_id' => RequisicionSalaDepositoLaboratorioSupport::empresaIdConfigurada(),
            ]);

            return;
        }

        $depositoSalidaId = (int) $req->deposito_id;
        if ($depositoSalidaId === $depositoLabId) {
            return;
        }

        $observacion = 'Transferencia automática requisición sala #'.($req->numerorequisicion ?? $requisicionSalaId)
            .' (destino reparación/devolución — aprobación árbol)';

        $authPrev = Auth::id();
        if ($usuarioId > 0) {
            Auth::loginUsingId($usuarioId);
        }

        try {
            $result = $this->transferenciaService->grabarTransferencia([
                'deposito_salida_id' => $depositoSalidaId,
                'deposito_entrada_id' => $depositoLabId,
                'tipotransaccion_stock_id' => (int) config('sala.requisicion_transferencia_tipotransaccion_id', 1),
                'empresa_id' => (int) $req->empresa_id,
                'observacion' => $observacion,
                // Lab usa 406 Biyemas aunque la requisición sea Kandiko/Rebisco.
                'permitir_intercompany' => true,
            ], $lineas);
        } finally {
            if ($authPrev) {
                Auth::loginUsingId((int) $authPrev);
            } else {
                Auth::logout();
            }
        }

        if (! ($result['ok'] ?? false)) {
            Log::error('RequisicionSala: falló transferencia automática a laboratorio', [
                'requisicion_sala_id' => $requisicionSalaId,
                'mensaje' => $result['mensaje'] ?? 'error desconocido',
            ]);

            throw new \RuntimeException($result['mensaje'] ?? 'No se pudo registrar la transferencia a laboratorio.');
        }

        Log::info('RequisicionSala: transferencia automática a laboratorio', [
            'requisicion_sala_id' => $requisicionSalaId,
            'transferencia_id' => $result['transferencia_id'] ?? null,
            'codigo' => $result['codigo'] ?? null,
        ]);
    }

    private function yaEjecutada(RequisicionSala $req): bool
    {
        return RequisicionSalaTransferenciaAsociadaSupport::tieneTransferenciaLaboratorio($req);
    }
}
