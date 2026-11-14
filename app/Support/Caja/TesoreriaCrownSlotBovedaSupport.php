<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownSlot;
use App\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Recuento CashBox (slot) → movimiento de bóveda (relevamiento JL).
 * Al confirmar el recuento se asienta un INGR en el depósito Bóveda con las mismas cantidades.
 */
final class TesoreriaCrownSlotBovedaSupport
{
    /**
     * @return string|null error
     */
    public static function asentarDesdeRecuento(TesoreriaCrownSlot $slot, Usuario $usuario): ?string
    {
        $slot->loadMissing(['lineas', 'jornada']);
        if (! $slot->esRecuento() || $slot->estado !== TesoreriaCrownSlot::RECIBIDO) {
            return null;
        }
        if ((int) ($slot->boveda_id ?? 0) > 0) {
            return null;
        }
        if ($slot->lineas->isEmpty()) {
            return 'El recuento no tiene cantidades para asentar en bóveda.';
        }
        $deposito = TesoreriaCrownBovedaSupport::lugares((int) $slot->sala_id)
            ->first(fn (TesoreriaCrownDeposito $lugar) => (string) ($lugar->tipoDeposito->codigo ?? '') === 'BOVEDA');
        if ($deposito === null) {
            return 'No hay depósito Bóveda en esta sala para asentar el CashBox.';
        }
        $slot->loadMissing('jornada');
        $fecha = $slot->jornada?->fecha;
        $qTipo = TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $slot->sala_id)
            ->where('abreviatura', 'INGR')
            ->where('activo', true);
        TesoreriaCrownBovedaVigenciaSupport::aplicarVigenteEn(
            $qTipo,
            TesoreriaCrownBovedaVigenciaSupport::normalizarFecha($fecha)
        );
        $tipo = $qTipo->first();
        if ($tipo === null) {
            return 'Falta el tipo INGR vigente para asentar el CashBox en bóveda.';
        }
        $subtipo = TesoreriaCrownKardexValoresSupport::subtiposDeTipo((int) $tipo->id, $fecha)
            ->firstWhere('codigo', 'ENC');

        DB::transaction(function () use ($slot, $usuario, $deposito, $tipo, $subtipo) {
            $mov = TesoreriaCrownBoveda::query()->create([
                'sala_id' => $slot->sala_id,
                'empresa_id' => $slot->empresa_id,
                'jornada_id' => $slot->jornada_id,
                'deposito_id' => $deposito->id,
                'tipo_id' => $tipo->id,
                'subtipo_id' => $subtipo?->id,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $slot->sala_id, 'V'),
                'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                'comentario' => mb_substr(
                    'CashBox '.$slot->maquina
                    .' · '.TesoreriaCrownSlot::tipo((string) $slot->tipo)
                    .' '.TesoreriaCrownNumeradorSupport::visible((int) $slot->numero),
                    0,
                    80
                ),
                'hora' => $slot->hora ?: now()->format('H:i:s'),
                'usuario_id' => $usuario->id,
                'confirmado_at' => now(),
            ]);
            foreach ($slot->lineas as $linea) {
                if ((float) $linea->cantidad < 0.0001) {
                    continue;
                }
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $mov->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => $linea->cantidad,
                ]);
            }
            $slot->boveda_id = $mov->id;
            $slot->save();
        });

        return null;
    }
}
