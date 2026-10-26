<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownCotizacion;

/**
 * Cierre de caja, en pesos: valores rendidos − efectivo habilitado − resultado.
 * El resultado es el informe X de gastronomía u hotel. En el resto de las tareas queda en cero.
 */
final class TesoreriaCrownCierreCalculo
{
    /**
     * @return array{
     *     habilitado: float,
     *     rendido: float,
     *     resultado: float,
     *     diferencia: float,
     *     signo: string,
     *     tolerancia: float,
     *     supera: bool,
     *     puede: bool,
     *     motivo: string
     * }
     */
    public static function de(TesoreriaCrownAsignacion $asignacion): array
    {
        $tolerancia = TesoreriaCrownCierreSupport::tolerancia();
        $docs = $asignacion->comprobantes()->with('lineas.valor.tipoValor')->get();
        $pendiente = $docs->first(fn (TesoreriaCrownComprobante $doc) => in_array($doc->estado, [
            TesoreriaCrownComprobanteSupport::BORRADOR,
            TesoreriaCrownComprobanteSupport::PENDIENTE,
        ], true));
        $conforme = $docs->filter(fn (TesoreriaCrownComprobante $doc) => $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME);
        $hayRendicion = $conforme->contains(fn (TesoreriaCrownComprobante $doc) => $doc->tipo === TesoreriaCrownComprobanteSupport::RENDICION);

        $habilitado = 0.0;
        $rendido = 0.0;
        $resultado = 0.0;
        $fecha = $asignacion->jornada->fecha->format('Y-m-d');
        $sinCotizacion = [];
        foreach ($conforme as $doc) {
            $pesos = self::pesos($doc, (int) $asignacion->sala_id, $fecha, $sinCotizacion);
            if ($doc->tipo === TesoreriaCrownComprobanteSupport::HABILITACION) {
                $habilitado += $pesos;
            } else {
                $rendido += $pesos;
                $resultado += self::resultado($doc, (string) $asignacion->tipo_tarea);
            }
        }
        $habilitado = round($habilitado, 2);
        $rendido = round($rendido, 2);
        $resultado = round($resultado, 2);
        $diferencia = round($rendido - $habilitado - $resultado, 2);

        $motivo = '';
        $puede = true;
        if ($pendiente !== null) {
            $puede = false;
            $motivo = 'Hay comprobantes en borrador o pendientes de firma.';
        } elseif (! $hayRendicion) {
            $puede = false;
            $motivo = 'El cajero no tiene una rendición conforme.';
        } elseif ($sinCotizacion !== []) {
            $puede = false;
            $motivo = 'Falta cotización de '.implode(', ', array_unique($sinCotizacion)).' para expresar el cierre en pesos.';
        }

        return [
            'habilitado' => $habilitado,
            'rendido' => $rendido,
            'resultado' => $resultado,
            'diferencia' => $diferencia,
            'signo' => TesoreriaCrownCierreSupport::signo($diferencia),
            'tolerancia' => $tolerancia,
            'supera' => abs($diferencia) > $tolerancia + 0.001,
            'puede' => $puede,
            'motivo' => $motivo,
        ];
    }

    /**
     * @param  array<string, string>  $sinCotizacion
     */
    private static function pesos(TesoreriaCrownComprobante $doc, int $salaId, string $fecha, array &$sinCotizacion): float
    {
        $total = 0.0;
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            $importe = (float) $linea->cantidad * (float) $valor->denominacion;
            if ($tipo->cotizacion_fija || $tipo->es_moneda_referencia) {
                $total += $importe;
                continue;
            }
            $tasa = self::tasa($salaId, $fecha, (int) $tipo->id);
            if ($tasa <= 0) {
                $sinCotizacion[$tipo->codigo] = (string) $tipo->codigo;
                continue;
            }
            $total += $importe * $tasa;
        }

        return $total;
    }

    private static function resultado(TesoreriaCrownComprobante $doc, string $tarea): float
    {
        if (! TesoreriaCrownRendicionCampos::esCajaGastroHotel($tarea)) {
            return 0.0;
        }
        $x = (float) $doc->total_x - (float) $doc->total_nc;
        if ($tarea === 'H') {
            $x += (float) $doc->ctacte_hotel;
        }

        return $x;
    }

    private static function tasa(int $salaId, string $fecha, int $tipoValorId): float
    {
        $venta = TesoreriaCrownCotizacion::query()
            ->where('sala_id', $salaId)
            ->where('tipo_valor_id', $tipoValorId)
            ->whereDate('jornada', '<=', $fecha)
            ->where('venta', '>', 0)
            ->orderByDesc('jornada')
            ->value('venta');

        return (float) $venta;
    }
}
