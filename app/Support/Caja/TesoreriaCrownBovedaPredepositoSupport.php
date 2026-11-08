<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use Illuminate\Support\Facades\DB;

/**
 * Circuito paquete pre-depósito (relevamiento JL / a-efeboveda):
 * - PDEP: sale de bóveda y entra al depósito PREDEPOSITO (espejo).
 * - RDEP: valida que el retiro coincida con el saldo del pre-depósito y lo baja.
 *   En Anita RDEP no mueve bóveda (signo 2); acá tampoco se toca el saldo de bóveda.
 */
final class TesoreriaCrownBovedaPredepositoSupport
{
    public const ABREV_PDEP = 'PDEP';

    public const ABREV_RDEP = 'RDEP';

    public static function esPdep(TesoreriaCrownBoveda $movimiento): bool
    {
        return strtoupper(trim((string) ($movimiento->tipo->abreviatura ?? ''))) === self::ABREV_PDEP;
    }

    public static function esRdep(TesoreriaCrownBoveda $movimiento): bool
    {
        return strtoupper(trim((string) ($movimiento->tipo->abreviatura ?? ''))) === self::ABREV_RDEP;
    }

    public static function depositoPredeposito(int $salaId): ?TesoreriaCrownDeposito
    {
        return TesoreriaCrownBovedaSupport::lugares($salaId)
            ->first(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'PREDEPOSITO');
    }

    /**
     * Tras confirmar PDEP en bóveda: crea el espejo en PREDEPOSITO (entra).
     */
    public static function asentarEspejoPdep(TesoreriaCrownBoveda $movimiento): ?string
    {
        $movimiento->loadMissing(['tipo', 'lineas', 'deposito.tipoDeposito']);
        if (! self::esPdep($movimiento) || $movimiento->par_id !== null) {
            return null;
        }
        if ((string) ($movimiento->deposito->tipoDeposito->codigo ?? '') !== 'BOVEDA') {
            return 'El pre-depósito (PDEP) se carga desde el depósito Bóveda.';
        }
        $destino = self::depositoPredeposito((int) $movimiento->sala_id);
        if ($destino === null) {
            return 'No hay depósito PREDEPOSITO en esta sala. Cargalo en Depósitos.';
        }
        $tipoEspejo = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $movimiento->sala_id)
            ->where('abreviatura', 'INGR')
            ->first();
        if ($tipoEspejo === null) {
            return 'Falta el tipo INGR para asentar el ingreso al pre-depósito.';
        }

        DB::transaction(function () use ($movimiento, $destino, $tipoEspejo) {
            $espejo = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $movimiento->sala_id,
                'empresa_id' => $movimiento->empresa_id,
                'jornada_id' => $movimiento->jornada_id,
                'deposito_id' => $destino->id,
                'tipo_id' => $tipoEspejo->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $movimiento->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => mb_substr('Pre-depósito ← PDEP '.TesoreriaCrownNumeradorSupport::visible((int) $movimiento->numero), 0, 80),
                'hora' => $movimiento->hora,
                'par_id' => $movimiento->id,
                'usuario_id' => $movimiento->usuario_id,
                'confirmado_at' => $movimiento->confirmado_at ?? now(),
            ]);
            foreach ($movimiento->lineas as $linea) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $espejo->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => $linea->cantidad,
                ]);
            }
            $movimiento->par_id = $espejo->id;
            $movimiento->save();
        });

        return null;
    }

    /**
     * Antes de confirmar RDEP: el paquete a retirar debe coincidir con el saldo del PREDEPOSITO.
     */
    public static function validarRdep(TesoreriaCrownBoveda $movimiento): ?string
    {
        $movimiento->loadMissing(['tipo', 'lineas', 'deposito.tipoDeposito', 'jornada']);
        if (! self::esRdep($movimiento)) {
            return null;
        }
        $pre = self::depositoPredeposito((int) $movimiento->sala_id);
        if ($pre === null) {
            return 'No hay depósito PREDEPOSITO en esta sala.';
        }
        $fecha = $movimiento->jornada->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        $saldo = TesoreriaCrownBovedaSupport::saldoCantidades((int) $pre->id, $fecha);
        foreach ($movimiento->lineas as $linea) {
            $pedido = (float) $linea->cantidad;
            if ($pedido < 0.0001) {
                continue;
            }
            $hay = (float) ($saldo[(int) $linea->valor_id] ?? 0);
            if (abs($hay - $pedido) > 0.01 && $hay + 0.01 < $pedido) {
                return 'El retiro de depósito no coincide con el pre-depósito: pedís '
                    .number_format($pedido, 0, ',', '.')
                    .' y hay '
                    .number_format($hay, 0, ',', '.')
                    .' en pre-depósito.';
            }
        }
        // Coincidencia total por moneda (como Anita efeboveda cod 22)
        $totPedido = [];
        $totSaldo = [];
        $valores = \App\Models\Caja\TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->whereIn('id', $movimiento->lineas->pluck('valor_id')->merge(array_keys($saldo))->unique())
            ->get()
            ->keyBy('id');
        foreach ($movimiento->lineas as $linea) {
            $valor = $valores->get((int) $linea->valor_id);
            $codigo = (string) ($valor->tipoValor->codigo ?? 'VAL');
            $totPedido[$codigo] = ($totPedido[$codigo] ?? 0.0)
                + ((float) $linea->cantidad * (float) ($valor->denominacion ?? 0));
        }
        foreach ($saldo as $valorId => $cant) {
            $valor = $valores->get((int) $valorId);
            if ($valor === null || abs($cant) < 0.0001) {
                continue;
            }
            $codigo = (string) ($valor->tipoValor->codigo ?? 'VAL');
            $totSaldo[$codigo] = ($totSaldo[$codigo] ?? 0.0) + ((float) $cant * (float) $valor->denominacion);
        }
        foreach ($totPedido as $codigo => $monto) {
            $hay = (float) ($totSaldo[$codigo] ?? 0);
            if (abs($hay - $monto) > 0.01) {
                return 'El depósito no es igual al pre-depósito acumulado ('.$codigo
                    .': depósito '.number_format($monto, 2, ',', '.')
                    .' / pre-depósito '.number_format($hay, 2, ',', '.').').';
            }
        }

        return null;
    }

    /**
     * Tras confirmar RDEP: baja el saldo del PREDEPOSITO (espejo de salida).
     */
    public static function asentarBajaPredepositoRdep(TesoreriaCrownBoveda $movimiento): ?string
    {
        $movimiento->loadMissing(['tipo', 'lineas']);
        if (! self::esRdep($movimiento) || $movimiento->par_id !== null) {
            return null;
        }
        $pre = self::depositoPredeposito((int) $movimiento->sala_id);
        if ($pre === null) {
            return 'No hay depósito PREDEPOSITO en esta sala.';
        }
        $tipoSalida = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $movimiento->sala_id)
            ->where('abreviatura', 'OEGR')
            ->first();
        if ($tipoSalida === null) {
            return 'Falta el tipo OEGR para bajar el pre-depósito.';
        }

        DB::transaction(function () use ($movimiento, $pre, $tipoSalida) {
            $espejo = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $movimiento->sala_id,
                'empresa_id' => $movimiento->empresa_id,
                'jornada_id' => $movimiento->jornada_id,
                'deposito_id' => $pre->id,
                'tipo_id' => $tipoSalida->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $movimiento->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => mb_substr('Entrega Caudales ← RDEP '.TesoreriaCrownNumeradorSupport::visible((int) $movimiento->numero), 0, 80),
                'hora' => $movimiento->hora,
                'par_id' => $movimiento->id,
                'usuario_id' => $movimiento->usuario_id,
                'confirmado_at' => $movimiento->confirmado_at ?? now(),
            ]);
            foreach ($movimiento->lineas as $linea) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $espejo->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => $linea->cantidad,
                ]);
            }
            $movimiento->par_id = $espejo->id;
            $movimiento->save();
        });

        return null;
    }
}
