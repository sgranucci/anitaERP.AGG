<?php

declare(strict_types=1);

namespace App\Support\Tesoreria\PosicionBancaria;

use App\Models\Caja\Cheque;
use App\Models\Tesoreria\PosicionBancariaCheque;
use App\Support\Finanzas\FinanzaPosicionHojaSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cheques propios ya emitidos y todavía diferidos.
 * Entran a la cartera de diferidos de la posición el día en que se genera el pago.
 */
final class PosicionBancariaChequeEmitidoSupport
{
    /** @var array<string, string> hoja de la cuenta → banco canónico de la cartera */
    private const CANONICO_POR_HOJA = [
        'Macro' => PosicionBancariaChequeAgingSupport::BANCO_MACRO,
        'Macro (BMA)' => PosicionBancariaChequeAgingSupport::BANCO_ITAU,
        'Bind' => PosicionBancariaChequeAgingSupport::BANCO_BIND,
    ];

    /**
     * @param  Collection<int, PosicionBancariaCheque>  $yaEnCartera
     * @return Collection<int, PosicionBancariaCheque>
     */
    public function diferidosNoDuplicados(Carbon $fechaPosicion, int $empresaId, Collection $yaEnCartera): Collection
    {
        $claves = [];
        foreach ($yaEnCartera as $cheque) {
            $claves[$this->clave((int) $cheque->cuentacaja_id, (string) $cheque->numero_cheque)] = true;
        }

        $nuevos = collect();
        $emitidos = Cheque::query()
            ->with(['cuentacajas.bancos', 'proveedores'])
            ->where('empresa_id', $empresaId)
            ->where('origen', 'E')
            ->where(function ($q) {
                $q->where('estado', ' ')->orWhere('estado', '')->orWhereNull('estado');
            })
            ->whereDate('fechapago', '>', $fechaPosicion->toDateString())
            ->whereNotNull('cuentacaja_id')
            ->orderBy('fechapago')
            ->get();

        foreach ($emitidos as $cheque) {
            $numero = trim((string) $cheque->numerocheque);
            if ($numero === '') {
                continue;
            }
            $clave = $this->clave((int) $cheque->cuentacaja_id, $numero);
            if (isset($claves[$clave])) {
                continue;
            }
            $cuenta = $cheque->cuentacajas;
            $hoja = FinanzaPosicionHojaSupport::hojaDesdeCuenta($cuenta);
            $canonico = self::CANONICO_POR_HOJA[$hoja] ?? null;
            if ($canonico === null || $cheque->fechapago === null) {
                continue;
            }
            $claves[$clave] = true;
            $fila = new PosicionBancariaCheque([
                'empresa_id' => $empresaId,
                'cuentacaja_id' => (int) $cheque->cuentacaja_id,
                'banco_canonico' => $canonico,
                'tip' => 'CHP',
                'numero_cheque' => $numero,
                'fecha_emision' => $cheque->fechaemision,
                'fecha_cheque' => $cheque->fechapago,
                'importe' => abs((float) $cheque->monto),
                'estado' => ' ',
                'entregado_a' => trim((string) ($cheque->proveedores->nombre ?? $cheque->anombrede ?? '')),
                'activo' => true,
                'en_portfolio_posicion' => true,
            ]);
            $nuevos->push($fila);
        }

        return $nuevos;
    }

    private function clave(int $cuentacajaId, string $numero): string
    {
        return $cuentacajaId.'|'.ltrim($numero, '0');
    }
}
