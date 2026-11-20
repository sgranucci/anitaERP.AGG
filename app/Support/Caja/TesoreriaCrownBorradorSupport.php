<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownComprobanteLinea;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;

/**
 * Borrador guardado: composición congelada. No se edita ni se elimina;
 * solo se revierte (vacía) o se sigue el circuito (pedir firmas / confirmar).
 * Un borrador vacío (recién abierto) sí se puede eliminar.
 */
final class TesoreriaCrownBorradorSupport
{
    public static function comprobanteTieneComposicion(TesoreriaCrownComprobante $comprobante): bool
    {
        if (self::relacionLineasConCantidad($comprobante)) {
            return true;
        }
        if (TesoreriaCrownRendicionCampos::tieneMediosCargados($comprobante, $comprobante->tipoTareaOperativa())) {
            return true;
        }
        if ($comprobante->relationLoaded('cierresFiscales')) {
            return $comprobante->cierresFiscales->isNotEmpty();
        }

        return $comprobante->cierresFiscales()->exists();
    }

    public static function lineasTieneComposicion(Model $movimiento): bool
    {
        return self::relacionLineasConCantidad($movimiento);
    }

    /**
     * Bóveda: billetes o monto informativo (HIB / sin billetaje).
     */
    public static function bovedaTieneComposicion(TesoreriaCrownBoveda $movimiento): bool
    {
        if (self::lineasTieneComposicion($movimiento)) {
            return true;
        }

        return abs((float) ($movimiento->importe_pesos ?? 0)) >= 0.01;
    }

    public static function mesaTieneComposicion(TesoreriaCrownMesa $movimiento): bool
    {
        if (self::lineasTieneComposicion($movimiento)) {
            return true;
        }

        return abs((float) ($movimiento->propina ?? 0)) >= 0.0001;
    }

    public static function vaciarComprobante(TesoreriaCrownComprobante $comprobante): void
    {
        DB::transaction(function () use ($comprobante) {
            TesoreriaCrownBovedaSupport::anularAsientoCajero($comprobante);
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownComprobanteLinea::query()->where('comprobante_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownCierreFiscal::query()->where('comprobante_id', $comprobante->id)
            );
            foreach (TesoreriaCrownRendicionCampos::camposDe($comprobante->tipoTareaOperativa()) as $clave => $_meta) {
                $comprobante->{$clave} = null;
            }
            $comprobante->iva_facturas = null;
            $comprobante->iva_nc = null;
            $comprobante->recargo_fin = null;
            $comprobante->save();
        });
    }

    public static function vaciarLineas(Model $movimiento): void
    {
        DB::transaction(function () use ($movimiento) {
            /** @var Relation $lineas */
            $lineas = $movimiento->lineas();
            EloquentAuditDeleteSupport::each($lineas->getQuery());
            if (array_key_exists('propina', $movimiento->getAttributes())
                || $movimiento->isFillable('propina')) {
                $movimiento->propina = null;
                if (array_key_exists('propina_billetes', $movimiento->getAttributes())
                    || $movimiento->isFillable('propina_billetes')) {
                    $movimiento->propina_billetes = null;
                }
                $movimiento->save();
            }
            if (array_key_exists('total', $movimiento->getAttributes())
                || $movimiento->isFillable('total')) {
                $movimiento->total = 0;
                $movimiento->save();
            }
            if (array_key_exists('importe_pesos', $movimiento->getAttributes())
                || $movimiento->isFillable('importe_pesos')) {
                $movimiento->importe_pesos = null;
                $movimiento->save();
            }
        });
    }

    private static function relacionLineasConCantidad(Model $modelo): bool
    {
        if ($modelo->relationLoaded('lineas')) {
            return $modelo->lineas->contains(
                static fn ($linea) => abs((float) ($linea->cantidad ?? 0)) >= 0.0001
            );
        }

        return $modelo->lineas()->whereRaw('ABS(cantidad) >= 0.0001')->exists();
    }
}
