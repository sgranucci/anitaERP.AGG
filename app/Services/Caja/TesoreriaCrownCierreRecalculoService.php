<?php

namespace App\Services\Caja;

use App\Models\Caja\TesoreriaCrownCierre;
use App\Models\Caja\TesoreriaCrownCierreEvento;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownCierreSupport;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Recalcula los cierres importados de Anita con el cálculo vigente de TesoreriaCrownCierreCalculo.
 * Solo toca cierres sin intervención posterior (un único evento, el de la importación). El estado
 * sigue la misma regla del import: manda la aprobación de Anita; sin ella, Auditoría si supera la
 * tolerancia y Conforme si no.
 */
class TesoreriaCrownCierreRecalculoService
{
    private const OBSERVACION_IMPORT = 'Importado de Anita';

    public function __construct(private TesoreriaCrownCajaAnitaImportService $import)
    {
    }

    /**
     * @return list<array{cierre_id: int, fecha: string, caja: string, tarea: string, estado: string, estado_nuevo: string, diferencia: float, diferencia_nueva: float, calculo: array<string, mixed>}>
     */
    public function plan(string $desde, string $hasta): array
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            throw new RuntimeException('El recálculo de cierres solo corre en Crown.');
        }

        $cierres = TesoreriaCrownCierre::query()
            ->with(['asignacion.deposito', 'asignacion.comprobantes', 'jornada'])
            ->withCount('eventos')
            ->where('observacion', self::OBSERVACION_IMPORT)
            ->whereIn('estado', [TesoreriaCrownCierreSupport::CONFORME, TesoreriaCrownCierreSupport::AUDITORIA])
            ->whereHas('jornada', fn ($q) => $q->whereDate('fecha', '>=', $desde)->whereDate('fecha', '<=', $hasta))
            ->get()
            ->filter(fn (TesoreriaCrownCierre $c) => (int) $c->eventos_count === 1 && $c->asignacion !== null);

        $numeros = [];
        foreach ($cierres as $cierre) {
            foreach ($cierre->asignacion->comprobantes as $comprobante) {
                if ($comprobante->tipo === TesoreriaCrownComprobanteSupport::RENDICION) {
                    $numeros[] = (int) $comprobante->numero;
                }
            }
        }
        $aprobacion = $numeros === [] ? [] : $this->import->ultimaAprobacion(array_values(array_unique($numeros)));

        $cambios = [];
        foreach ($cierres as $cierre) {
            $asignacion = $cierre->asignacion;
            $asignacion->load(['comprobantes.lineas.valor.tipoValor', 'jornada']);
            $calculo = TesoreriaCrownCierreCalculo::de($asignacion);
            if (! $calculo['puede']) {
                continue;
            }
            $estadoAnita = null;
            foreach ($asignacion->comprobantes->sortBy('numero') as $comprobante) {
                if ($comprobante->tipo === TesoreriaCrownComprobanteSupport::RENDICION) {
                    $estadoAnita = $aprobacion[(int) $comprobante->numero] ?? $estadoAnita;
                }
            }
            $estadoNuevo = $estadoAnita ?: ($calculo['supera']
                ? TesoreriaCrownCierreSupport::AUDITORIA
                : TesoreriaCrownCierreSupport::CONFORME);

            $difVieja = (float) $cierre->diferencia;
            $difNueva = round((float) $calculo['diferencia'], 2);
            $cambiaImporte = abs($difVieja - $difNueva) > 0.009
                || abs((float) $cierre->valores_rendidos - (float) $calculo['rendido']) > 0.009
                || abs((float) $cierre->resultado - (float) $calculo['resultado']) > 0.009;
            if (! $cambiaImporte && $estadoNuevo === $cierre->estado) {
                continue;
            }
            $cambios[] = [
                'cierre_id' => (int) $cierre->id,
                'fecha' => $cierre->jornada->fecha->format('Y-m-d'),
                'caja' => (string) ($asignacion->deposito->codigo ?? ''),
                'tarea' => (string) $asignacion->tipo_tarea,
                'estado' => (string) $cierre->estado,
                'estado_nuevo' => (string) $estadoNuevo,
                'diferencia' => $difVieja,
                'diferencia_nueva' => $difNueva,
                'calculo' => $calculo,
            ];
        }

        usort($cambios, fn ($a, $b) => [$a['fecha'], $a['caja']] <=> [$b['fecha'], $b['caja']]);

        return $cambios;
    }

    /**
     * @param  list<array{cierre_id: int, estado: string, estado_nuevo: string, diferencia: float, diferencia_nueva: float, calculo: array<string, mixed>}>  $plan
     */
    public function aplicar(array $plan): int
    {
        $grabados = 0;
        DB::transaction(function () use ($plan, &$grabados) {
            foreach ($plan as $fila) {
                $cierre = TesoreriaCrownCierre::query()->lockForUpdate()->find($fila['cierre_id']);
                if ($cierre === null || $cierre->estado !== $fila['estado']) {
                    continue;
                }
                $calculo = $fila['calculo'];
                $cierre->efectivo_habilitado = $calculo['habilitado'];
                $cierre->valores_rendidos = $calculo['rendido'];
                $cierre->resultado = $calculo['resultado'];
                $cierre->diferencia = $calculo['diferencia'];
                $cierre->componentes = $calculo['componentes'];
                $cierre->estado = $fila['estado_nuevo'];
                $cierre->save();
                TesoreriaCrownCierreEvento::query()->create([
                    'cierre_id' => $cierre->id,
                    'estado' => $fila['estado_nuevo'],
                    'detalle' => sprintf(
                        'Recalculado (neto on-line, NC ya descontadas del X): diferencia %s → %s',
                        number_format($fila['diferencia'], 2, ',', '.'),
                        number_format($fila['diferencia_nueva'], 2, ',', '.')
                    ),
                ]);
                $grabados++;
            }
        });

        return $grabados;
    }
}
