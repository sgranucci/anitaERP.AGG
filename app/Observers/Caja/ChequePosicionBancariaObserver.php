<?php

namespace App\Observers\Caja;

use App\Models\Caja\Cheque;
use App\Support\Tesoreria\PosicionBancaria\PosicionBancariaChequeUpsertDesdeChequeSupport;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Al grabar un cheque propio, mantiene el padrón Macro de posición/conciliación.
 */
class ChequePosicionBancariaObserver
{
    public function saved(Cheque $cheque): void
    {
        if (strtoupper((string) $cheque->origen) !== 'E') {
            return;
        }

        try {
            app(PosicionBancariaChequeUpsertDesdeChequeSupport::class)
                ->sincronizarSiAplica($cheque->fresh(['cuentacajas', 'proveedores']) ?? $cheque);
        } catch (Throwable $e) {
            Log::warning('posicion_bancaria_cheque sync observer: '.$e->getMessage(), [
                'cheque_id' => $cheque->id ?? null,
            ]);
        }
    }
}
