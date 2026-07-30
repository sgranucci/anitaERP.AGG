<?php

namespace App\Services\Caja\RendicionMaquina;

use App\Models\Caja\RendicionMaquinaFormula;
use App\Support\Caja\RendicionMaquina\EntornoRendicionMaquina;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaFormulaCatalogo;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaResultadoCalculo;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaTurno;
use App\Support\Caja\RendicionMaquina\RendicionMaquinaVariables;
use App\Support\Sueldos\Formula\EvaluadorFormula;
use App\Support\Sueldos\Formula\FormulaException;
use Illuminate\Support\Facades\Schema;

/**
 * Orquestador del cálculo: siembra contexto + pipeline AST.
 * Sin side-effects (Anita / vale / asiento quedan fuera).
 */
final class RendicionMaquinaCalculoService
{
    private EvaluadorFormula $evaluador;

    public function __construct(?EvaluadorFormula $evaluador = null)
    {
        $this->evaluador = $evaluador ?? new EvaluadorFormula();
    }

    /**
     * @param  array<string, float|int|string|bool>  $contexto
     *                                                     Debe incluir al menos meta.turno.
     *                                                     Puede traer inputs.*, valores.*, gastos.*, prev.*,
     *                                                     calc.comprobante, calc.vale_rep_fondo.
     * @param  list<array<string, mixed>>|null  $formulasOverride  para tests / preview
     */
    public function calcular(array $contexto, ?array $formulasOverride = null): RendicionMaquinaResultadoCalculo
    {
        $turno = RendicionMaquinaTurno::normalizar((string) ($contexto['meta.turno'] ?? RendicionMaquinaTurno::MANIANA));
        $entorno = EntornoRendicionMaquina::desdeDefaults($turno);
        $entorno->merge($contexto);
        $entorno->merge([
            'meta.turno' => $turno,
            'meta.es_maniana' => RendicionMaquinaTurno::esManiana($turno) ? 1 : 0,
            'meta.es_tarde' => $turno === RendicionMaquinaTurno::TARDE ? 1 : 0,
            'meta.es_noche' => $turno === RendicionMaquinaTurno::NOCHE ? 1 : 0,
            'meta.es_completo' => RendicionMaquinaTurno::esCompleto($turno) ? 1 : 0,
            'meta.modo_wigos' => RendicionMaquinaTurno::modoWigos($turno),
        ]);

        $formulas = $formulasOverride ?? $this->cargarFormulas();
        $esCompleto = RendicionMaquinaTurno::esCompleto($turno);
        $rastro = [];

        foreach ($formulas as $paso) {
            if (! (bool) ($paso['activo'] ?? true)) {
                continue;
            }
            if (! empty($paso['solo_completo']) && ! $esCompleto) {
                continue;
            }

            $destino = (string) $paso['destino'];
            $expresion = trim((string) $paso['expresion']);
            $codigo = (string) ($paso['codigo'] ?? '');
            $seccion = (string) ($paso['seccion'] ?? '');

            try {
                $valor = (float) $this->evaluador->evaluar($expresion, $entorno);
            } catch (FormulaException $e) {
                throw new FormulaException(
                    "Fórmula {$codigo} ({$destino}): ".$e->getMessage(),
                    $e->getCode(),
                    $e
                );
            }

            $valor = round($valor, 2);
            $entorno->set($destino, $valor);
            $rastro[] = [
                'codigo' => $codigo,
                'destino' => $destino,
                'expresion' => $expresion,
                'valor' => $valor,
                'seccion' => $seccion,
            ];
        }

        return new RendicionMaquinaResultadoCalculo(
            $entorno->snapshot(),
            $rastro,
            $turno,
            RendicionMaquinaTurno::modoWigos($turno),
        );
    }

    /**
     * Valida sintaxis de todas las fórmulas del catálogo / BD.
     *
     * @return list<string> errores (vacío = OK)
     */
    public function validarCatalogo(?array $formulasOverride = null): array
    {
        $formulas = $formulasOverride ?? $this->cargarFormulas();
        $errores = [];
        foreach ($formulas as $paso) {
            $err = $this->evaluador->validar((string) $paso['expresion']);
            if ($err !== null) {
                $errores[] = ($paso['codigo'] ?? '?').': '.$err;
            }
        }

        return $errores;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cargarFormulas(): array
    {
        if (class_exists(RendicionMaquinaFormula::class)
            && Schema::hasTable('rendicion_maquina_formula')
            && RendicionMaquinaFormula::query()->exists()
        ) {
            return RendicionMaquinaFormula::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('id')
                ->get()
                ->map(static fn (RendicionMaquinaFormula $f) => [
                    'codigo' => $f->codigo,
                    'destino' => $f->destino,
                    'expresion' => $f->expresion,
                    'seccion' => $f->seccion,
                    'orden' => (int) $f->orden,
                    'activo' => (bool) $f->activo,
                    'solo_completo' => (bool) $f->solo_completo,
                ])
                ->all();
        }

        return RendicionMaquinaFormulaCatalogo::canonicos();
    }
}
