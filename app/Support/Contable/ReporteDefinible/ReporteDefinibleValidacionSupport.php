<?php

namespace App\Support\Contable\ReporteDefinible;

use App\Models\Contable\Cuentacontable;
use App\Models\Contable\ReporteContable;
use App\Models\Contable\ReporteContableAlerta;
use App\Models\Contable\ReporteContableConjunto;

/**
 * Validación estática de la definición del informe (sin ejecutar saldos).
 */
class ReporteDefinibleValidacionSupport
{
    /** @var list<string> */
    private const TIPOS_DATO = ['actual', 'ytd', 'anio_ant', 'plan', 'periodo_offset', 'valuacion'];

    /**
     * @return list<array{nivel: string, codigo?: string, mensaje: string}>
     */
    public function validar(ReporteContable $reporte): array
    {
        $reporte->loadMissing([
            'rubros.cuentas',
            'rubros.conjunto.cuentas',
            'layouts.columnas',
        ]);

        $issues = [];
        $codigosLinea = [];
        foreach ($reporte->rubros as $rubro) {
            $cod = trim((string) ($rubro->codigo_linea ?? ''));
            if ($cod !== '') {
                $codigosLinea[strtoupper($cod)] = true;
            }
        }

        foreach ($reporte->rubros as $rubro) {
            $etiqueta = trim((string) (($rubro->codigo_linea ? $rubro->codigo_linea.' ' : '').$rubro->nombre));
            $tipo = (string) $rubro->tipo;

            if ($tipo === ReporteDefinibleSupport::RUBRO_FORMULA) {
                $formula = trim((string) ($rubro->formula ?? ''));
                if ($formula === '') {
                    $issues[] = [
                        'nivel' => 'error',
                        'codigo' => (string) ($rubro->codigo_linea ?? ''),
                        'mensaje' => "Rubro «{$etiqueta}»: fórmula vacía.",
                    ];
                } else {
                    $formulaUp = strtoupper($formula);
                    if (! preg_match('/^[R0-9+\-*\/().\s]+$/', $formulaUp)) {
                        $issues[] = [
                            'nivel' => 'error',
                            'codigo' => (string) ($rubro->codigo_linea ?? ''),
                            'mensaje' => "Rubro «{$etiqueta}»: fórmula con caracteres inválidos.",
                        ];
                    } else {
                        preg_match_all('/R\d+/', $formulaUp, $m);
                        foreach ($m[0] ?? [] as $ref) {
                            if (! isset($codigosLinea[$ref])) {
                                $issues[] = [
                                    'nivel' => 'error',
                                    'codigo' => (string) ($rubro->codigo_linea ?? ''),
                                    'mensaje' => "Rubro «{$etiqueta}»: referencia inexistente {$ref}.",
                                ];
                            }
                        }
                        $dummy = [];
                        foreach (array_keys($codigosLinea) as $c) {
                            $dummy[$c] = 1.0;
                        }
                        if (ReporteDefinibleFormulaSupport::evaluar($formula, $dummy) === null) {
                            $issues[] = [
                                'nivel' => 'error',
                                'codigo' => (string) ($rubro->codigo_linea ?? ''),
                                'mensaje' => "Rubro «{$etiqueta}»: fórmula no evaluable.",
                            ];
                        }
                    }
                }
            }

            if ($rubro->conjunto_id) {
                $conjunto = $rubro->conjunto;
                if (! $conjunto) {
                    $conjunto = ReporteContableConjunto::query()->with('cuentas')->find((int) $rubro->conjunto_id);
                }
                if (! $conjunto || $conjunto->cuentas->isEmpty()) {
                    $issues[] = [
                        'nivel' => 'warning',
                        'codigo' => (string) ($rubro->codigo_linea ?? ''),
                        'mensaje' => "Rubro «{$etiqueta}»: conjunto de cuentas vacío o inexistente.",
                    ];
                }
            }
        }

        foreach ($reporte->layouts as $layout) {
            $tieneDato = false;
            foreach ($layout->columnas as $col) {
                if (in_array((string) $col->tipo, self::TIPOS_DATO, true)) {
                    $tieneDato = true;
                    break;
                }
            }
            if (! $tieneDato) {
                $issues[] = [
                    'nivel' => 'warning',
                    'mensaje' => sprintf(
                        'Layout «%s» (%s) sin columnas de datos (actual/YTD/año ant./plan/valuación).',
                        $layout->nombre,
                        $layout->codigo
                    ),
                ];
            }
        }

        $codigosAsignados = [];
        foreach ($reporte->rubros as $rubro) {
            foreach ($rubro->cuentas as $cta) {
                if ($cta->origen === ReporteDefinibleSupport::ORIGEN_PRESUPUESTO) {
                    continue;
                }
                $codigosAsignados[(int) $cta->codigo_cuenta] = true;
                if ($cta->codigo_hasta !== null) {
                    // Solo validamos extremos del rango (expansión completa es costosa en validación UI)
                    $codigosAsignados[(int) $cta->codigo_hasta] = true;
                }
            }
        }

        if ($codigosAsignados !== []) {
            $enPlan = Cuentacontable::query()
                ->where('tipocuenta', 1)
                ->whereIn('codigo', array_keys($codigosAsignados))
                ->pluck('codigo')
                ->map(fn ($c) => (int) $c)
                ->all();
            $enPlanSet = array_fill_keys($enPlan, true);
            foreach (array_keys($codigosAsignados) as $codigo) {
                if (! isset($enPlanSet[$codigo])) {
                    $issues[] = [
                        'nivel' => 'warning',
                        'mensaje' => sprintf(
                            'Cuenta %d no está en el plan de cuentas (tipocuenta=1).',
                            $codigo
                        ),
                    ];
                }
            }
        }

        foreach ($this->issuesEcuaciones($reporte, $codigosLinea) as $issue) {
            $issues[] = $issue;
        }

        return $issues;
    }

    /**
     * Las validaciones contables cruzadas se declaran como alerta de tipo ecuación:
     * si referencian una línea que no existe, el chequeo pasaría siempre en silencio.
     *
     * @param  array<string, bool>  $codigosLinea
     * @return list<array{nivel: string, codigo?: string, mensaje: string}>
     */
    private function issuesEcuaciones(ReporteContable $reporte, array $codigosLinea): array
    {
        $alertas = ReporteContableAlerta::query()
            ->where('reporte_contable_id', (int) $reporte->id)
            ->where('tipo', ReporteContableAlerta::TIPO_ECUACION)
            ->get();

        $issues = [];
        foreach ($alertas as $alerta) {
            $expresion = strtoupper(trim((string) ($alerta->expresion ?? '')));
            $etiqueta = trim((string) ($alerta->etiqueta ?? '')) ?: $expresion;
            if ($expresion === '') {
                $issues[] = ['nivel' => 'error', 'mensaje' => 'Validación contable sin ecuación definida.'];
                continue;
            }

            preg_match_all('/R\d+/', $expresion, $m);
            foreach ($m[0] ?? [] as $ref) {
                if (! isset($codigosLinea[$ref])) {
                    $issues[] = [
                        'nivel' => 'error',
                        'mensaje' => "Validación contable «{$etiqueta}»: referencia inexistente {$ref}.",
                    ];
                }
            }

            $dummy = array_fill_keys(array_keys($codigosLinea), 1.0);
            if (ReporteDefinibleFormulaSupport::evaluar($expresion, $dummy) === null) {
                $issues[] = [
                    'nivel' => 'error',
                    'mensaje' => "Validación contable «{$etiqueta}»: ecuación no evaluable.",
                ];
            }
        }

        return $issues;
    }
}
