<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Concepto_Acumulador_Sueldos;
use App\Models\Sueldos\Concepto_Formula_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoAnitaMapeo;
use App\Support\Sueldos\ReciboBaseCalculoSupport;
use App\Support\Sueldos\RubroCostoLaboral;
use App\Support\Sueldos\ConceptoSueldosListadoFiltros;
use App\Support\Sueldos\ConceptoTipo;
use App\Support\Sueldos\Formula\Anita\AnitaFormulaTraductor;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Conceptos de liquidacion (Anita sueldos / haberes + habformula).
 * El maestro vive completo en el ERP; sin write-back a Anita.
 */
class Concepto_SueldosRepository implements Concepto_SueldosRepositoryInterface
{
    protected $model;

    protected string $tablaHaberes = 'haberes';

    protected string $tablaHabformula = 'habformula';

    public function __construct(Concepto_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->newQuery()->orderBy('codigo')->get();
    }

    /**
     * Listado paginado/completo del index con filtros inteligentes.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeConcepto($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ConceptoSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ConceptoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('concepto_sueldos.*');

        if (ConceptoSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ConceptoSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('concepto_sueldos.codigo');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(15);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $registro = $this->model->create($this->normalizar($data, null));
            $this->sincronizarOverrides($registro->id, $data['acumuladores_override'] ?? []);

            return $registro->fresh(['acumuladoresOverride']);
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            $registro->update($this->normalizar($data, $registro));
            $this->sincronizarOverrides((int) $id, $data['acumuladores_override'] ?? []);

            return $registro->fresh(['acumuladoresOverride']);
        });
    }

    /**
     * Persiste overrides concepto <-> acumulador.
     * Payload: [acumulador_id => ['accion' => 'auto'|'incluir'|'excluir', 'signo' => 1|-1]]
     *
     * @param  array<int|string, mixed>  $overrides
     */
    private function sincronizarOverrides(int $conceptoId, array $overrides): void
    {
        Concepto_Acumulador_Sueldos::where('concepto_id', $conceptoId)->delete();

        foreach ($overrides as $acumuladorId => $row) {
            if (! is_array($row)) {
                continue;
            }
            $accion = (string) ($row['accion'] ?? 'auto');
            if ($accion === 'auto' || $accion === '') {
                continue;
            }
            $acumuladorId = (int) $acumuladorId;
            if ($acumuladorId <= 0) {
                continue;
            }
            Concepto_Acumulador_Sueldos::create([
                'concepto_id' => $conceptoId,
                'acumulador_id' => $acumuladorId,
                'excluir' => $accion === 'excluir',
                'signo' => ((int) ($row['signo'] ?? 1)) === -1 ? -1 : 1,
            ]);
        }
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $registro = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function findPorCodigo(int $codigo)
    {
        return $this->model->newQuery()->where('codigo', $codigo)->first();
    }

    public function listadoParaConsulta(string $consulta, int $limit = 60)
    {
        $query = $this->model->newQuery()
            ->where('activo', true)
            ->select(['id', 'codigo', 'descripcion', 'tipo']);

        $consulta = trim($consulta);
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                if (ctype_digit($consulta)) {
                    $q->where('codigo', (int) $consulta)
                        ->orWhere('codigo', 'like', $consulta.'%');
                }
                $q->orWhere('descripcion', 'like', '%'.$consulta.'%');
            });
        }

        return $query->orderBy('codigo')->limit(max(1, min(200, $limit)))->get();
    }

    public function findActivoPorCodigo(int $codigo): ?Concepto_Sueldos
    {
        if ($codigo <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('activo', true)
            ->where('codigo', $codigo)
            ->first();
    }

    /**
     * Trae desde Anita (bridge) haberes + habformula.
     * - Inserta códigos faltantes.
     * - Actualiza seeds/provisorios sin líneas en concepto_formula_sueldos.
     * - Guarda fórmulas Anita crudas en concepto_formula_sueldos.
     * - Intenta traducción best-effort a sintaxis ERP (traductor existente);
     *   lo no traducible queda pendiente de revisión.
     *
     * @return array{
     *     en_anita: int,
     *     importados: int,
     *     actualizados: int,
     *     omitidos: int,
     *     con_formula: int,
     *     traducidos: int,
     *     pendientes_traduccion: int,
     *     errores: list<string>
     * }
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '-1');

        $resultado = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'omitidos' => 0,
            'con_formula' => 0,
            'traducidos' => 0,
            'pendientes_traduccion' => 0,
            'errores' => [],
        ];

        $api = new ApiAnita();
        $parsedHaberes = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tablaHaberes,
            'campos' => 'hab_codigo, hab_desc, hab_forma, hab_tipo, hab_total, hab_factor,'
                .' hab_momento, hab_formula, hab_formula_cant, hab_formula_valor,'
                .' hab_retroactivo, hab_va_recibo',
            'orderBy' => 'hab_codigo',
        ]));

        if (! empty($parsedHaberes['error_lectura'])) {
            $resultado['errores'][] = 'haberes: '.(string) $parsedHaberes['error_lectura'];

            return $resultado;
        }

        $parsedFormulas = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tablaHabformula,
            'campos' => 'habf_concepto, habf_linea, habf_formula',
            'orderBy' => 'habf_concepto, habf_linea',
        ]));

        if (! empty($parsedFormulas['error_lectura'])) {
            $resultado['errores'][] = 'habformula: '.(string) $parsedFormulas['error_lectura'];

            return $resultado;
        }

        /** @var array<int, list<array{linea: int, formula: string}>> $formulasPorCodigo */
        $formulasPorCodigo = [];
        foreach ($parsedFormulas['filas'] as $fila) {
            $concepto = (int) ($fila->habf_concepto ?? 0);
            if ($concepto <= 0) {
                continue;
            }
            $texto = ConceptoAnitaMapeo::textoFormula($fila->habf_formula ?? null);
            if ($texto === null) {
                continue;
            }
            $formulasPorCodigo[$concepto][] = [
                'linea' => (int) ($fila->habf_linea ?? 0),
                'formula' => $texto,
            ];
        }

        $traductor = new AnitaFormulaTraductor();

        Concepto_Sueldos::withoutAuditing(function () use (
            $parsedHaberes,
            $formulasPorCodigo,
            $traductor,
            &$resultado
        ) {
            Concepto_Formula_Sueldos::withoutAuditing(function () use (
                $parsedHaberes,
                $formulasPorCodigo,
                $traductor,
                &$resultado
            ) {
                foreach ($parsedHaberes['filas'] as $row) {
                    $codigo = (int) ($row->hab_codigo ?? 0);
                    $descripcion = $this->recortar(trim((string) ($row->hab_desc ?? '')), 60);
                    if ($codigo <= 0 || $descripcion === '') {
                        continue;
                    }

                    $resultado['en_anita']++;

                    $existente = $this->findPorCodigo($codigo);
                    $tieneLineasAnita = $existente !== null
                        && Concepto_Formula_Sueldos::query()
                            ->where('concepto_id', $existente->id)
                            ->exists();

                    if ($existente !== null && $tieneLineasAnita) {
                        $resultado['omitidos']++;

                        continue;
                    }

                    $payload = $this->payloadDesdeAnita($row, $formulasPorCodigo[$codigo] ?? [], $traductor, $resultado);
                    $lineasRaw = $payload['_lineas_raw'];
                    unset($payload['_lineas_raw']);

                    if ($existente === null) {
                        $registro = $this->model->create($payload);
                        $resultado['importados']++;
                    } else {
                        $existente->update($payload);
                        $registro = $existente;
                        Concepto_Formula_Sueldos::where('concepto_id', $registro->id)->delete();
                        $resultado['actualizados']++;
                    }

                    $this->persistirLineasFormula((int) $registro->id, $lineasRaw);
                    if ($lineasRaw !== []) {
                        $resultado['con_formula']++;
                    }
                }
            });
        });

        return $resultado;
    }

    /**
     * @param  list<array{linea: int, formula: string}>  $lineasHabformula
     * @param  array<string, mixed>  $resultado
     * @return array<string, mixed>
     */
    private function payloadDesdeAnita(
        object $row,
        array $lineasHabformula,
        AnitaFormulaTraductor $traductor,
        array &$resultado
    ): array {
        $codigo = (int) $row->hab_codigo;
        $habTipo = (int) ($row->hab_tipo ?? 1);
        $habVaRecibo = $row->hab_va_recibo ?? 0;
        $tipo = ConceptoAnitaMapeo::tipo($codigo, $habTipo, $habVaRecibo);
        $momento = ConceptoAnitaMapeo::momento((int) ($row->hab_momento ?? 1));

        $lineasTexto = [];
        usort($lineasHabformula, static fn ($a, $b) => $a['linea'] <=> $b['linea']);
        foreach ($lineasHabformula as $l) {
            $lineasTexto[] = $l['formula'];
        }

        if ($lineasTexto === []) {
            foreach ([
                ConceptoAnitaMapeo::textoFormula($row->hab_formula ?? null),
                ConceptoAnitaMapeo::textoFormula($row->hab_formula_cant ?? null),
                ConceptoAnitaMapeo::textoFormula($row->hab_formula_valor ?? null),
            ] as $idx => $cab) {
                if ($cab === null) {
                    continue;
                }
                if ($idx === 1) {
                    $lineasTexto[] = 'CA := '.$cab;
                } elseif ($idx === 2) {
                    $lineasTexto[] = 'VA := '.$cab;
                } else {
                    $lineasTexto[] = $cab;
                }
            }
        }

        $formula = null;
        $formulaCantidad = null;
        $formulaValor = null;

        if ($lineasTexto !== []) {
            try {
                $trad = $traductor->traducirConcepto($lineasTexto);
                if ($trad->traducible && ($trad->formula !== null || $trad->formulaCantidad !== null || $trad->formulaValor !== null)) {
                    $formula = $trad->formula;
                    $formulaCantidad = $trad->formulaCantidad;
                    $formulaValor = $trad->formulaValor;
                    if ($trad->requiereRevision) {
                        $resultado['pendientes_traduccion']++;
                    } else {
                        $resultado['traducidos']++;
                    }
                } else {
                    $resultado['pendientes_traduccion']++;
                }
            } catch (\Throwable $e) {
                $resultado['pendientes_traduccion']++;
            }
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) $row->hab_desc), 60),
            'tipo' => $tipo,
            'suma_a' => ConceptoAnitaMapeo::sumaA($tipo),
            'momento' => $momento,
            'factor' => ConceptoAnitaMapeo::factor($row->hab_factor ?? null),
            'formula' => $formula,
            'formula_cantidad' => $formulaCantidad,
            'formula_valor' => $formulaValor,
            'va_recibo' => ConceptoAnitaMapeo::vaRecibo($habVaRecibo),
            'mes_retroactivo' => (int) ($row->hab_retroactivo ?? 0),
            'leyenda_recibo' => null,
            'concepto_afip' => null,
            'rubro_costo_laboral' => $tipo === 'contribucion'
                ? RubroCostoLaboral::inferirDesdeDescripcion((string) ($row->hab_desc ?? ''))
                : null,
            'unidad_medida' => ReciboBaseCalculoSupport::inferirUnidad(
                (string) ($row->hab_desc ?? ''),
                ConceptoAnitaMapeo::factor($row->hab_factor ?? null),
                $tipo
            ),
            'activo' => true,
            'orden' => $codigo,
            '_lineas_raw' => $lineasTexto,
        ];
    }

    /**
     * @param  list<string>  $lineas
     */
    private function persistirLineasFormula(int $conceptoId, array $lineas): void
    {
        $nro = 1;
        foreach ($lineas as $texto) {
            $texto = trim((string) $texto);
            if ($texto === '') {
                continue;
            }
            Concepto_Formula_Sueldos::create([
                'concepto_id' => $conceptoId,
                'nro_linea' => $nro,
                'formula' => $this->recortar($texto, 2000),
            ]);
            $nro++;
        }
    }

    /**
     * Re-traduce todas las fórmulas Anita ya persistidas (sin volver a llamar al bridge).
     *
     * @return array{
     *     procesados: int,
     *     traducidos: int,
     *     pendientes_traduccion: int,
     *     sin_lineas: int,
     *     errores: list<string>
     * }
     */
    public function retraducirFormulasDesdeLineas()
    {
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '-1');

        $resultado = [
            'procesados' => 0,
            'traducidos' => 0,
            'pendientes_traduccion' => 0,
            'sin_lineas' => 0,
            'errores' => [],
        ];

        $traductor = new AnitaFormulaTraductor();

        Concepto_Sueldos::withoutAuditing(function () use ($traductor, &$resultado) {
            $this->model->newQuery()->orderBy('codigo')->chunkById(100, function ($chunk) use ($traductor, &$resultado) {
                foreach ($chunk as $concepto) {
                    $lineas = Concepto_Formula_Sueldos::query()
                        ->where('concepto_id', $concepto->id)
                        ->orderBy('nro_linea')
                        ->pluck('formula')
                        ->map(fn ($t) => trim((string) $t))
                        ->filter(fn ($t) => $t !== '')
                        ->values()
                        ->all();

                    if ($lineas === []) {
                        $resultado['sin_lineas']++;

                        continue;
                    }

                    $resultado['procesados']++;
                    try {
                        $trad = $traductor->traducirConcepto($lineas);
                    } catch (\Throwable $e) {
                        $resultado['pendientes_traduccion']++;
                        $resultado['errores'][] = 'código '.$concepto->codigo.': '.$e->getMessage();

                        continue;
                    }

                    $ok = $trad->traducible
                        && ($trad->formula !== null || $trad->formulaCantidad !== null || $trad->formulaValor !== null);

                    $concepto->update([
                        'formula' => $ok ? $trad->formula : null,
                        'formula_cantidad' => $ok ? $trad->formulaCantidad : null,
                        'formula_valor' => $ok ? $trad->formulaValor : null,
                    ]);

                    if ($ok && ! $trad->requiereRevision) {
                        $resultado['traducidos']++;
                    } else {
                        $resultado['pendientes_traduccion']++;
                    }
                }
            });
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data, ?Concepto_Sueldos $existente): array
    {
        $codigo = $existente !== null
            ? (int) $existente->codigo
            : (isset($data['codigo']) && (int) $data['codigo'] > 0
                ? (int) $data['codigo']
                : $this->proximoCodigo());

        $factor = $data['factor'] ?? null;
        if ($factor === '' || $factor === null) {
            $factor = null;
        }

        return [
            'codigo' => $codigo,
            'descripcion' => $this->recortar(trim((string) ($data['descripcion'] ?? '')), 60),
            'tipo' => ConceptoTipo::normalizarTipo($data['tipo'] ?? null),
            'suma_a' => in_array($data['suma_a'] ?? null, ConceptoTipo::basesPermitidas(), true)
                ? $data['suma_a']
                : null,
            'momento' => ConceptoTipo::normalizarMomento($data['momento'] ?? null),
            'factor' => $factor !== null ? (float) $factor : null,
            'formula' => $this->nullSiVacio($data['formula'] ?? null),
            'formula_cantidad' => $this->nullSiVacio($data['formula_cantidad'] ?? null),
            'formula_valor' => $this->nullSiVacio($data['formula_valor'] ?? null),
            'va_recibo' => (bool) ($data['va_recibo'] ?? false),
            'mes_retroactivo' => (int) ($data['mes_retroactivo'] ?? 0),
            'leyenda_recibo' => $this->nullSiVacio($data['leyenda_recibo'] ?? null),
            'concepto_afip' => $this->nullSiVacio($data['concepto_afip'] ?? null),
            'rubro_costo_laboral' => $this->nullSiVacio($data['rubro_costo_laboral'] ?? null),
            'unidad_medida' => $this->nullSiVacio($data['unidad_medida'] ?? null),
            'activo' => (bool) ($data['activo'] ?? true),
            'orden' => (int) ($data['orden'] ?? 0),
        ];
    }

    private function nullSiVacio($valor): ?string
    {
        $valor = trim((string) ($valor ?? ''));

        return $valor === '' ? null : $valor;
    }

    private function proximoCodigo(): int
    {
        return (int) ($this->model->newQuery()->max('codigo') ?? 0) + 1;
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
