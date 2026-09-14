<?php

namespace App\Services\Caja;

use App\Models\Caja\Cheque;
use App\Support\Caja\Echeq\ChequeEcheqProviderResolver;
use InvalidArgumentException;

/**
 * Operaciones eCheq sobre cheques ERP (CHT/CHP electrónicos).
 */
final class ChequeEcheqService
{
    public function esEcheq(Cheque $cheque): bool
    {
        return strtoupper(trim((string) ($cheque->negociable ?? ''))) === 'E'
            || trim((string) ($cheque->nro_echeq ?? '')) !== '';
    }

    /**
     * @return array{cheque_id:int, estado:string, provider:string, mensaje?:string}
     */
    public function sincronizarEstado(int $chequeId): array
    {
        if (! ChequeEcheqProviderResolver::habilitado()) {
            throw new InvalidArgumentException('Módulo eCheq deshabilitado.');
        }

        $cheque = Cheque::query()->find($chequeId);
        if (! $cheque) {
            throw new InvalidArgumentException('Cheque no encontrado.');
        }
        if (! $this->esEcheq($cheque)) {
            throw new InvalidArgumentException('El cheque no es electrónico (negociable≠E / sin nro_echeq).');
        }

        $nro = trim((string) ($cheque->nro_echeq ?: $cheque->numerocheque));
        if ($nro === '') {
            throw new InvalidArgumentException('Falta número eCheq.');
        }

        $provider = ChequeEcheqProviderResolver::resolve(
            $cheque->echeq_provider ?: (string) config('cheque.echeq.provider', 'manual')
        );
        $resp = $provider->consultarEstado($nro, [
            'estado_actual' => $cheque->echeq_estado,
            'cheque_id' => $cheque->id,
            'empresa_id' => $cheque->empresa_id,
            'banco_id' => $cheque->banco_id,
        ]);

        if (! ($resp['ok'] ?? false)) {
            throw new InvalidArgumentException($resp['mensaje'] ?? 'Consulta eCheq falló.');
        }

        $cheque->echeq_estado = (string) ($resp['estado'] ?? $cheque->echeq_estado ?? 'activo');
        $cheque->echeq_sync_at = now();
        $cheque->echeq_provider = $provider->codigo();
        $cheque->save();

        return [
            'cheque_id' => (int) $cheque->id,
            'estado' => (string) $cheque->echeq_estado,
            'provider' => $provider->codigo(),
            'mensaje' => $resp['mensaje'] ?? null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPendientes(?int $empresaId = null, int $limite = 100): array
    {
        $q = Cheque::query()
            ->with(['bancos:id,nombre', 'clientes:id,nombre', 'empresas:id,nombre', 'monedas:id,abreviatura'])
            ->where(function ($w) {
                $w->where('negociable', 'E')
                    ->orWhere(function ($x) {
                        $x->whereNotNull('nro_echeq')->where('nro_echeq', '!=', '');
                    });
            })
            ->where(function ($w) {
                $w->whereNull('estado')->orWhereNotIn('estado', ['A', 'R']);
            })
            ->orderByDesc('id')
            ->limit(max(1, $limite));

        if ($empresaId && $empresaId > 0) {
            $q->where('empresa_id', $empresaId);
        }

        $filas = [];
        foreach ($q->get() as $c) {
            $filas[] = [
                'id' => (int) $c->id,
                'origen' => (string) $c->origen,
                'numerocheque' => (string) $c->numerocheque,
                'nro_echeq' => (string) ($c->nro_echeq ?: $c->numerocheque),
                'fechapago' => (string) ($c->fechapago ?? ''),
                'monto' => round((float) $c->monto, 2),
                'moneda' => (string) ($c->monedas->abreviatura ?? ''),
                'banco' => (string) ($c->bancos->nombre ?? ''),
                'cliente' => (string) ($c->clientes->nombre ?? ''),
                'empresa' => (string) ($c->empresas->nombre ?? ''),
                'echeq_estado' => (string) ($c->echeq_estado ?? ''),
                'echeq_provider' => (string) ($c->echeq_provider ?? config('cheque.echeq.provider')),
                'echeq_sync_at' => $c->echeq_sync_at ? (string) $c->echeq_sync_at : '',
            ];
        }

        return $filas;
    }
}
