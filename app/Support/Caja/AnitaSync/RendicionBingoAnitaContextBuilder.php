<?php

declare(strict_types=1);

namespace App\Support\Caja\AnitaSync;

use App\Models\Caja\Bingo\BingoCarton;
use App\Models\Caja\Bingo\BingoConceptoRendicion;
use App\Models\Caja\Bingo\RendicionBingoCaja;
use App\Support\Caja\Bingo\BingoTurnoLetraSupport;
use Carbon\Carbon;

final class RendicionBingoAnitaContextBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function desdeRendicion(RendicionBingoCaja $rendicion): array
    {
        $rendicion->loadMissing([
            'empresa',
            'cuentacaja',
            'turnoOperativo.turno',
            'turnoOperativo.usuarioHabilitado',
            'turnoOperativo.jornada',
            'creousuario',
        ]);

        $fechaJornada = $rendicion->fecha_jornada
            ? Carbon::parse($rendicion->fecha_jornada)->startOfDay()
            : Carbon::today()->startOfDay();
        $fechaRend = Carbon::parse($rendicion->fecharendicion ?? now());

        $nroOper = (int) ($rendicion->nro_oper_anita
            ?? RendicionBingoCabeceraAnitaMapper::nroOperDesdeCodigo($rendicion->codigo));

        $empresaErpId = (int) $rendicion->empresa_id;
        $empresaAnita = (int) ($rendicion->empresa?->codigo ?? $empresaErpId);
        $cajaDefault = (int) (config('rendicion_bingo_anita.caja_id_default_por_empresa')[$empresaErpId] ?? 1);
        $cajaId = (int) ($rendicion->cuentacaja?->codigo ?? 0);
        if ($cajaId <= 0) {
            $cajaId = $cajaDefault > 0 ? $cajaDefault : 1;
        }

        $turnoOperativo = $rendicion->turnoOperativo;
        $usuarioHabilitadoId = (int) ($turnoOperativo?->usuario_habilitado_id ?? $rendicion->creousuario_id ?? 0);
        $usuarioCajeroId = (int) ($rendicion->creousuario_id ?? 0);

        return [
            'nro_oper' => $nroOper,
            'tipo_oper' => substr((string) config('rendicion_bingo_anita.tipo_oper', 'F'), 0, 1),
            'empresa_anita' => $empresaAnita,
            'empresa_id' => $empresaErpId,
            'caja_id' => $cajaId,
            'usuario_id' => $usuarioCajeroId,
            'usuario_habilitado_id' => $usuarioHabilitadoId,
            'fecha_entera' => (int) $fechaJornada->format('Ymd'),
            'fecha_alfa' => $fechaJornada->format('d/m/y'),
            'hora' => $fechaRend->format('H:i:s'),
            'fecha_carga' => (int) now()->format('Ymd'),
            'hora_carga' => now()->format('H:i:s'),
            'cant_cartones' => (int) ($rendicion->cant_cartones ?? 0),
            'total_cartones' => round((float) ($rendicion->total_cartones ?? 0), 2),
            'sobrante_faltante' => round((float) ($rendicion->sobrante_faltante ?? 0), 2),
            'vales' => round((float) ($rendicion->vales ?? 0), 2),
            'redondeo' => round((float) ($rendicion->redondeo ?? 0), 2),
            'deposito' => round((float) ($rendicion->deposito ?? 0), 2),
            'observacion' => (string) ($rendicion->observacion ?? ''),
            'turno_letra' => BingoTurnoLetraSupport::desdeTurno($turnoOperativo?->turno),
            'estado' => (string) config('rendicion_bingo_anita.estado_pendiente', ' '),
            'lineas_carton' => self::lineasCarton($rendicion),
            'lineas_premio' => self::lineasPremio($rendicion),
        ];
    }

    /**
     * Cartones vendidos → rendcarton. Se omiten líneas anuladas, sin cantidad
     * o sin código Anita configurado en el maestro de cartones.
     *
     * @return list<array{carton:int, valor:float, cantidad:int, total:float}>
     */
    public static function lineasCarton(RendicionBingoCaja $rendicion): array
    {
        $lineas = is_array($rendicion->cartones_json) ? $rendicion->cartones_json : [];
        if ($lineas === []) {
            return [];
        }

        $cartonIds = array_values(array_filter(array_map(
            static fn ($l) => (int) ($l['carton_id'] ?? 0),
            $lineas,
        )));
        $codigosAnita = $cartonIds === []
            ? collect()
            : BingoCarton::query()->whereIn('id', $cartonIds)->pluck('codigo_anita', 'id');

        $acum = [];
        foreach ($lineas as $linea) {
            if (! empty($linea['anulado'])) {
                continue;
            }

            $cantidad = (int) ($linea['cantidad'] ?? 0);
            $precio = round((float) ($linea['precio_unitario'] ?? 0), 2);
            if ($cantidad <= 0) {
                continue;
            }

            $cartonId = (int) ($linea['carton_id'] ?? 0);
            $codigoAnita = (int) ($codigosAnita[$cartonId] ?? 0);
            if ($codigoAnita <= 0) {
                continue;
            }

            if (! isset($acum[$codigoAnita])) {
                $acum[$codigoAnita] = ['carton' => $codigoAnita, 'valor' => $precio, 'cantidad' => 0, 'total' => 0.0];
            }
            $acum[$codigoAnita]['cantidad'] += $cantidad;
            $acum[$codigoAnita]['total'] = round($acum[$codigoAnita]['total'] + $cantidad * $precio, 2);
        }

        return array_values($acum);
    }

    /**
     * Conceptos de la rendición → rendpremio. Se omiten los conceptos sin código
     * Anita configurado (por ejemplo, el saldo/depósito que ya va en rendbingo).
     *
     * @return list<array{concepto:int, porcentaje:float, pagado:float, real:float}>
     */
    public static function lineasPremio(RendicionBingoCaja $rendicion): array
    {
        $lineas = is_array($rendicion->conceptos_json) ? $rendicion->conceptos_json : [];
        if ($lineas === []) {
            return [];
        }

        $conceptoIds = array_values(array_filter(array_map(
            static fn ($l) => (int) ($l['concepto_id'] ?? 0),
            $lineas,
        )));
        $codigosAnita = $conceptoIds === []
            ? collect()
            : BingoConceptoRendicion::query()->whereIn('id', $conceptoIds)->pluck('codigo_anita', 'id');

        $acum = [];
        foreach ($lineas as $linea) {
            $conceptoId = (int) ($linea['concepto_id'] ?? 0);
            $codigoAnita = (int) ($codigosAnita[$conceptoId] ?? 0);
            if ($codigoAnita <= 0) {
                continue;
            }

            $monto = round((float) ($linea['monto'] ?? 0), 2);
            $porcentaje = round((float) ($linea['porcentaje'] ?? 0), 4);

            if (! isset($acum[$codigoAnita])) {
                $acum[$codigoAnita] = [
                    'concepto' => $codigoAnita,
                    'porcentaje' => $porcentaje,
                    'pagado' => 0.0,
                    'real' => 0.0,
                ];
            }
            $acum[$codigoAnita]['pagado'] = round($acum[$codigoAnita]['pagado'] + $monto, 2);
            $acum[$codigoAnita]['real'] = round($acum[$codigoAnita]['real'] + $monto, 2);
        }

        return array_values($acum);
    }
}
