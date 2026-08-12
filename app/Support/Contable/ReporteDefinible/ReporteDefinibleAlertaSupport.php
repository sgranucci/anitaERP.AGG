<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableAlerta;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Alertas post-corrida (umbral var % / cobertura rota).
 */
class ReporteDefinibleAlertaSupport
{
    /**
     * @return Collection<int, ReporteContableAlerta>
     */
    public function listar(int $reporteId): Collection
    {
        return ReporteContableAlerta::query()
            ->where('reporte_contable_id', $reporteId)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function payloadUi(int $reporteId): array
    {
        $out = [];
        foreach ($this->listar($reporteId) as $a) {
            $out[] = [
                'id' => (int) $a->id,
                'tipo' => (string) $a->tipo,
                'tipo_label' => ReporteContableAlerta::tipos()[$a->tipo] ?? $a->tipo,
                'etiqueta' => (string) ($a->etiqueta ?? ''),
                'expresion' => (string) ($a->expresion ?? ''),
                'umbral' => (float) $a->umbral,
                'activo' => (bool) $a->activo,
                'orden' => (int) $a->orden,
            ];
        }

        return $out;
    }

    /**
     * ¿Hay alerta activa que necesite el análisis de cobertura del plan?
     */
    public function requiereCobertura(int $reporteId): bool
    {
        return ReporteContableAlerta::query()
            ->where('reporte_contable_id', $reporteId)
            ->where('tipo', ReporteContableAlerta::TIPO_COBERTURA_ROTA)
            ->where('activo', true)
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function crear(int $reporteId, array $data): ReporteContableAlerta
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (! array_key_exists($tipo, ReporteContableAlerta::tipos())) {
            throw ValidationException::withMessages(['tipo' => 'Tipo de alerta inválido.']);
        }
        $orden = (int) ($data['orden'] ?? ((int) (ReporteContableAlerta::query()
            ->where('reporte_contable_id', $reporteId)
            ->max('orden') ?? -1) + 1));

        $expresion = $this->normalizarExpresion($tipo, $data['expresion'] ?? null);

        return ReporteContableAlerta::query()->create([
            'reporte_contable_id' => $reporteId,
            'tipo' => $tipo,
            'etiqueta' => trim((string) ($data['etiqueta'] ?? '')) ?: null,
            'expresion' => $expresion,
            'umbral' => (float) ($data['umbral'] ?? 0),
            'activo' => (bool) ($data['activo'] ?? true),
            'orden' => $orden,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizar(ReporteContableAlerta $alerta, array $data): ReporteContableAlerta
    {
        if (array_key_exists('tipo', $data)) {
            $tipo = (string) $data['tipo'];
            if (! array_key_exists($tipo, ReporteContableAlerta::tipos())) {
                throw ValidationException::withMessages(['tipo' => 'Tipo de alerta inválido.']);
            }
            $alerta->tipo = $tipo;
        }
        if (array_key_exists('etiqueta', $data)) {
            $alerta->etiqueta = trim((string) $data['etiqueta']) ?: null;
        }
        if (array_key_exists('expresion', $data)) {
            $alerta->expresion = $this->normalizarExpresion((string) $alerta->tipo, $data['expresion']);
        }
        if (array_key_exists('umbral', $data)) {
            $alerta->umbral = (float) $data['umbral'];
        }
        if (array_key_exists('activo', $data)) {
            $alerta->activo = (bool) $data['activo'];
        }
        if (array_key_exists('orden', $data)) {
            $alerta->orden = (int) $data['orden'];
        }
        $alerta->save();

        return $alerta;
    }

    /**
     * La ecuación usa el mismo lenguaje que los rubros fórmula: R001-(R050+R080).
     */
    private function normalizarExpresion(string $tipo, mixed $expresion): ?string
    {
        if ($tipo !== ReporteContableAlerta::TIPO_ECUACION) {
            return null;
        }

        $expr = strtoupper(trim((string) $expresion));
        if ($expr === '') {
            throw ValidationException::withMessages([
                'expresion' => 'La ecuación es obligatoria (ej. R001-(R050+R080)).',
            ]);
        }
        if (! preg_match('/^[R0-9+\-*\/().\s]+$/', $expr)) {
            throw ValidationException::withMessages([
                'expresion' => 'La ecuación solo admite códigos de línea Rnnn, números y + - * / ( ).',
            ]);
        }
        if (! preg_match('/R\d+/', $expr)) {
            throw ValidationException::withMessages([
                'expresion' => 'La ecuación debe referenciar al menos un código de línea (ej. R001).',
            ]);
        }
        if (ReporteDefinibleFormulaSupport::evaluar($expr, []) === null) {
            throw ValidationException::withMessages([
                'expresion' => 'La ecuación no se puede evaluar: revise paréntesis y operadores.',
            ]);
        }

        return $expr;
    }

    public function eliminar(int $reporteId, int $alertaId): void
    {
        ReporteContableAlerta::query()
            ->where('reporte_contable_id', $reporteId)
            ->whereKey($alertaId)
            ->delete();
    }

    /**
     * @param  array<string, mixed>  $resultado
     * @return list<string>
     */
    public function evaluar(ReporteContable $reporte, array $resultado): array
    {
        $mensajes = [];
        $alertas = $this->listar((int) $reporte->id)->where('activo', true);
        if ($alertas->isEmpty()) {
            return [];
        }

        $keysVarPct = [];
        foreach ($resultado['columnas'] ?? [] as $col) {
            if (($col['tipo'] ?? '') === 'var_pct') {
                $keysVarPct[] = (string) ($col['key'] ?? 'var_pct');
            }
        }

        foreach ($alertas as $alerta) {
            if ($alerta->tipo === ReporteContableAlerta::TIPO_COBERTURA_ROTA) {
                if (! empty($resultado['cobertura_rota'])) {
                    $mensajes[] = 'Cobertura del plan de cuentas rota (hay cuentas sin asignar o fuera de plan).';
                }
                continue;
            }

            if ($alerta->tipo === ReporteContableAlerta::TIPO_ECUACION) {
                foreach ($this->evaluarEcuacion($alerta, $resultado) as $mensaje) {
                    $mensajes[] = $mensaje;
                }
                continue;
            }

            if ($alerta->tipo !== ReporteContableAlerta::TIPO_VAR_PCT_ABS) {
                continue;
            }

            $umbral = (float) $alerta->umbral;
            foreach ($resultado['filas'] ?? [] as $fila) {
                if (($fila['kind'] ?? 'rubro') !== 'rubro') {
                    continue;
                }
                $saldos = $fila['saldos'] ?? null;
                if (! is_array($saldos)) {
                    continue;
                }
                foreach ($keysVarPct as $key) {
                    if (! array_key_exists($key, $saldos) || $saldos[$key] === null) {
                        continue;
                    }
                    $varPct = (float) $saldos[$key];
                    if (abs($varPct) >= $umbral) {
                        $codigo = (string) ($fila['codigo'] ?? '');
                        $nombre = (string) ($fila['nombre'] ?? '');
                        $mensajes[] = sprintf(
                            'Var %% |%s| ≥ %.2f en %s %s (%.2f%%).',
                            $key,
                            $umbral,
                            $codigo !== '' ? $codigo : 'fila',
                            $nombre,
                            $varPct
                        );
                    }
                }
            }
        }

        return array_values(array_unique($mensajes));
    }

    /**
     * Chequeo contable declarativo: la expresión debe dar cero (± umbral) en cada
     * columna de valores del informe. Sirve para Activo = Pasivo + PN, resultado
     * del EERR = variación de PN, o cualquier control propio del informe.
     *
     * @param  array<string, mixed>  $resultado
     * @return list<string>
     */
    private function evaluarEcuacion(ReporteContableAlerta $alerta, array $resultado): array
    {
        $expresion = trim((string) ($alerta->expresion ?? ''));
        if ($expresion === '') {
            return [];
        }

        $etiqueta = trim((string) ($alerta->etiqueta ?? '')) ?: $expresion;
        // Umbral 0 en un chequeo contable sería intolerante al redondeo de conversión.
        $umbral = abs((float) $alerta->umbral) > 0 ? abs((float) $alerta->umbral) : 0.01;

        $valoresPorColumna = [];
        foreach ($resultado['filas'] ?? [] as $fila) {
            if (($fila['kind'] ?? 'rubro') !== 'rubro') {
                continue;
            }
            $codigo = strtoupper(trim((string) ($fila['codigo'] ?? '')));
            if ($codigo === '' || ! is_array($fila['saldos'] ?? null)) {
                continue;
            }
            foreach ($fila['saldos'] as $key => $valor) {
                if ($valor === null) {
                    continue;
                }
                $valoresPorColumna[(string) $key][$codigo] = (float) $valor;
            }
        }

        if ($valoresPorColumna === []) {
            return [];
        }

        $etiquetasColumna = [];
        foreach ($resultado['columnas'] ?? [] as $columna) {
            $etiquetasColumna[(string) ($columna['key'] ?? '')] = (string) ($columna['label'] ?? '');
        }

        $mensajes = [];
        foreach ($valoresPorColumna as $key => $valores) {
            $valor = ReporteDefinibleFormulaSupport::evaluar($expresion, $valores);
            if ($valor === null || abs($valor) <= $umbral) {
                continue;
            }

            $mensajes[] = sprintf(
                'Validación contable «%s» no cumple en la columna %s: %s = %s (tolerancia %s).',
                $etiqueta,
                $etiquetasColumna[$key] ?? $key,
                $expresion,
                number_format($valor, 2, ',', '.'),
                number_format($umbral, 2, ',', '.')
            );
        }

        return $mensajes;
    }
}
