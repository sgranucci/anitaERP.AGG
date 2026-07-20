<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Concepto_Acumulador_Sueldos;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Support\Sueldos\ConceptoSueldosListadoFiltros;
use App\Support\Sueldos\ConceptoTipo;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Conceptos de liquidacion (Anita sueldos / haberes + habformula).
 * El maestro vive completo en el ERP; sin write-back a Anita.
 */
class Concepto_SueldosRepository implements Concepto_SueldosRepositoryInterface
{
    protected $model;

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
