<?php

namespace App\Support\Compras;

/**
 * Bridge propuesto/OP ↔ Interbanking.
 * Delega al clearing avanzado (scoring, neto/bruto, exclusividad, extracto).
 */
class PropuestaPagoBridgeBancarioSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('propuesta_pago.bridge_bancario_habilitado', false);
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Models\Compras\Pagoproveedor>
     */
    public static function listarOpsDelLote(int $propuestaPagoId)
    {
        return \App\Models\Compras\Pagoproveedor::query()
            ->where('propuesta_pago_id', $propuestaPagoId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{ok:bool,mensaje:string,conciliadas:list<int>,sin_match:list<int>,omitidas:list<int>}
     */
    public static function intentarConciliarLote(int $propuestaPagoId): array
    {
        $r = PropuestaPagoClearingBancarioSupport::procesarPropuesta($propuestaPagoId, true);

        return [
            'ok' => (bool) $r['ok'],
            'mensaje' => (string) $r['mensaje'],
            'conciliadas' => $r['auto'],
            'sin_match' => array_values(array_unique(array_merge($r['sugeridas'], $r['excepciones']))),
            'omitidas' => $r['omitidas'],
        ];
    }

    public static function buscarTransferenciaParaOp(\App\Models\Compras\Pagoproveedor $op): ?\App\Models\Caja\InterbankingTransferencia
    {
        // Compatibilidad: el clearing rankea; aquí solo devolvemos top transferencia auto-viable.
        $op->loadMissing(['proveedores', 'pagoproveedor_retenciones']);
        $usadas = [];
        foreach (\App\Models\Compras\Pagoproveedor::query()
            ->whereNotNull('interbanking_transferencia_id')
            ->pluck('interbanking_transferencia_id') as $id) {
            $usadas[(int) $id] = true;
        }
        $comp = app(\App\Support\Caja\InterbankingTransferenciaComprobanteSupport::class);
        $cbu = self::extraerCbuDesdeDetalle((string) ($op->detalle ?? ''));
        if ($cbu === '') {
            $fp = PropuestaPagoInstrumentoSupport::resolverFormapagoProveedor((int) $op->proveedor_id, null);
            $cbu = CbuSupport::normalizar((string) ($fp->cbu ?? ''));
        }
        $bruto = round((float) $op->monto, 2);
        $neto = round(max(0, $bruto - (float) $op->pagoproveedor_retenciones->sum('monto')), 2);
        $fecha = $op->fecha ? \Carbon\Carbon::parse($op->fecha) : \Carbon\Carbon::today();
        $dias = PropuestaPagoClearingBancarioSupport::diasVentana();
        $tol = PropuestaPagoClearingBancarioSupport::toleranciaMonto();

        $cands = \App\Models\Caja\InterbankingTransferencia::query()
            ->where('empresa_id', (int) $op->empresa_id)
            ->whereBetween('request_date', [$fecha->copy()->subDays($dias), $fecha->copy()->addDays($dias)])
            ->where(function ($q) use ($neto, $bruto, $tol) {
                $q->whereRaw('ABS(amount - ?) < ?', [$neto, $tol])
                    ->orWhereRaw('ABS(amount - ?) < ?', [$bruto, $tol]);
            })
            ->orderByDesc('request_date')
            ->limit(50)
            ->get()
            ->filter(fn ($t) => ! isset($usadas[(int) $t->id]));

        $best = null;
        $bestScore = -1;
        foreach ($cands as $t) {
            $cbuCred = CbuSupport::normalizar($comp->cbuCuenta($t->credit_account_json, $t->credit_account));
            $score = 0;
            if (abs((float) $t->amount - $neto) < $tol) {
                $score += 40;
            } elseif (abs((float) $t->amount - $bruto) < $tol) {
                $score += 25;
            }
            if ($cbu !== '' && $cbuCred === $cbu) {
                $score += 40;
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $t;
            }
        }

        return $bestScore >= PropuestaPagoClearingBancarioSupport::scoreSugerir() ? $best : null;
    }

    public static function extraerCbuDesdeDetalle(string $detalle): string
    {
        if (preg_match('/CBU\s*([0-9\-\s]{16,30})/i', $detalle, $m)) {
            return preg_replace('/\D+/', '', $m[1]) ?? '';
        }

        return '';
    }

    public static function fijarMontoAutorizado(\App\Models\Compras\PropuestaPago $propuesta): void
    {
        $propuesta->monto_autorizado = (float) $propuesta->monto_total;
        $propuesta->save();
    }
}
