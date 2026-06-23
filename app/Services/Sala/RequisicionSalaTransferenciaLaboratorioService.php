<?php

namespace App\Services\Sala;

use App\Models\Sala\RequisicionSala;
use App\Models\Stock\Depmae;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Services\Stock\TransferenciaMercaderiaService;
use Auth;
use Illuminate\Support\Facades\Log;

/**
 * Transferencia de stock automática al aprobar requisición sala con ítems reparación/devolución.
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

        $lineas = $this->lineasTransferibles($req);
        if ($lineas === []) {
            return;
        }

        $depositoLabId = $this->resolverDepositoLaboratorioId();
        if ($depositoLabId <= 0) {
            Log::warning('RequisicionSala: depósito laboratorio no configurado o inexistente', [
                'requisicion_sala_id' => $requisicionSalaId,
                'codigo' => config('sala.requisicion_deposito_laboratorio_codigo'),
            ]);

            return;
        }

        $depositoSalidaId = (int) $req->deposito_id;
        if ($depositoSalidaId === $depositoLabId) {
            return;
        }

        $observacion = 'Transferencia automática requisición sala #'.($req->numerorequisicion ?? $requisicionSalaId)
            .' (destino reparación/devolución)';

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

            return;
        }

        Log::info('RequisicionSala: transferencia automática a laboratorio', [
            'requisicion_sala_id' => $requisicionSalaId,
            'transferencia_id' => $result['transferencia_id'] ?? null,
            'codigo' => $result['codigo'] ?? null,
        ]);
    }

    /**
     * @return list<array{articulo_id: int, cantidad: float}>
     */
    private function lineasTransferibles(RequisicionSala $req): array
    {
        $lineas = [];
        foreach ($req->requisicion_sala_articulos as $articulo) {
            $destino = strtoupper((string) ($articulo->destino ?? ''));
            if (! in_array($destino, ['R', 'D'], true)) {
                continue;
            }
            $cantidad = (float) ($articulo->cantidad ?? 0);
            $articuloId = (int) ($articulo->articulo_id ?? 0);
            if ($articuloId <= 0 || $cantidad <= 0) {
                continue;
            }
            $lineas[] = [
                'articulo_id' => $articuloId,
                'cantidad' => $cantidad,
            ];
        }

        return $lineas;
    }

    private function resolverDepositoLaboratorioId(): int
    {
        $codigo = trim((string) config('sala.requisicion_deposito_laboratorio_codigo', '406'));
        if ($codigo === '') {
            return 0;
        }
        $id = Depmae::query()->where('codigo', $codigo)->value('id');

        return $id ? (int) $id : 0;
    }

    private function yaEjecutada(RequisicionSala $req): bool
    {
        $needle = 'requisición sala #'.($req->numerorequisicion ?? $req->id);

        return Transferencia_Mercaderia::query()
            ->where('observacion', 'like', '%'.$needle.'%')
            ->exists();
    }
}
