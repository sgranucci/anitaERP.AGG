<?php

namespace App\Support\Sueldos\ReporteDefinible;

use App\Models\Sueldos\ReporteSueldosDefinibleAlerta;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReporteSueldosDefinibleAlertaSupport
{
    /**
     * @return Collection<int, ReporteSueldosDefinibleAlerta>
     */
    public function listar(int $reporteId): Collection
    {
        return ReporteSueldosDefinibleAlerta::query()
            ->where('reporte_sueldos_definible_id', $reporteId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function guardar(int $reporteId, array $datos, ?int $alertaId = null): ReporteSueldosDefinibleAlerta
    {
        $tipo = (string) ($datos['tipo'] ?? '');
        if (! array_key_exists($tipo, ReporteSueldosDefinibleAlerta::tipos())) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de control inválido.']);
        }

        $operadores = ['>', '>=', '<', '<=', '=', '!=', 'entre'];
        $operador = (string) ($datos['operador'] ?? '>');
        if (! in_array($operador, $operadores, true)) {
            throw ValidationException::withMessages(['operador' => 'Operador inválido.']);
        }

        $alerta = $alertaId !== null
            ? ReporteSueldosDefinibleAlerta::query()
                ->where('reporte_sueldos_definible_id', $reporteId)
                ->findOrFail($alertaId)
            : new ReporteSueldosDefinibleAlerta([
                'reporte_sueldos_definible_id' => $reporteId,
            ]);

        $alerta->fill([
            'nombre' => trim((string) ($datos['nombre'] ?? '')) ?: ReporteSueldosDefinibleAlerta::tipos()[$tipo],
            'tipo' => $tipo,
            'columna_nro' => isset($datos['columna_nro']) && $datos['columna_nro'] !== ''
                ? (int) $datos['columna_nro']
                : null,
            'operador' => $operador,
            'umbral' => (float) ($datos['umbral'] ?? 0),
            'umbral_hasta' => isset($datos['umbral_hasta']) && $datos['umbral_hasta'] !== ''
                ? (float) $datos['umbral_hasta']
                : null,
            'bloqueante' => (bool) ($datos['bloqueante'] ?? false),
            'activo' => (bool) ($datos['activo'] ?? true),
            'orden' => (int) ($datos['orden'] ?? 0),
        ]);
        $alerta->save();

        return $alerta;
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return array{mensajes:list<string>,bloqueantes:list<string>}
     */
    public function evaluar(int $reporteId, array $resultado): array
    {
        $mensajes = [];
        $bloqueantes = [];
        $filas = count((array) ($resultado['filas'] ?? []));
        $totales = (array) ($resultado['totales'] ?? []);

        foreach ($this->listar($reporteId)->where('activo', true) as $alerta) {
            $dispara = false;
            $valor = null;

            if ($alerta->tipo === ReporteSueldosDefinibleAlerta::TIPO_SIN_FILAS) {
                $dispara = $filas === 0;
            } elseif ($alerta->tipo === ReporteSueldosDefinibleAlerta::TIPO_FILAS_MAYOR) {
                $valor = (float) $filas;
                $dispara = $alerta->comparar($valor);
            } elseif ($alerta->tipo === ReporteSueldosDefinibleAlerta::TIPO_TOTAL_FUERA_RANGO) {
                $valor = (float) ($totales[(int) $alerta->columna_nro] ?? 0);
                $dispara = $alerta->comparar($valor);
            } elseif ($alerta->tipo === ReporteSueldosDefinibleAlerta::TIPO_VARIACION_PCT) {
                $valor = $this->variacionMaxima($resultado, (int) ($alerta->columna_nro ?? 0));
                $dispara = $alerta->comparar(abs($valor));
            } elseif ($alerta->tipo === ReporteSueldosDefinibleAlerta::TIPO_PARIDAD) {
                $valor = (float) (($resultado['meta']['paridad_diferencia_maxima'] ?? 0));
                $dispara = $alerta->comparar(abs($valor));
            }

            if (! $dispara) {
                continue;
            }

            $mensaje = (string) $alerta->nombre;
            if ($valor !== null) {
                $mensaje .= ': '.number_format($valor, 4, ',', '.');
            }
            $mensajes[] = $mensaje;
            if ($alerta->bloqueante) {
                $bloqueantes[] = $mensaje;
            }
        }

        return [
            'mensajes' => array_values(array_unique($mensajes)),
            'bloqueantes' => array_values(array_unique($bloqueantes)),
        ];
    }

    /**
     * @param  array<string, mixed>  $resultado
     */
    private function variacionMaxima(array $resultado, int $columnaNro): float
    {
        $key = 'c'.($columnaNro + 1000);
        $max = 0.0;
        foreach ((array) ($resultado['filas'] ?? []) as $fila) {
            $base = (float) ($fila['c'.$columnaNro] ?? 0);
            $delta = (float) ($fila[$key] ?? 0);
            if (abs($base) < 0.0001) {
                continue;
            }
            $max = max($max, abs(($delta / $base) * 100));
        }

        return $max;
    }
}
