<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Support\Sueldos\LiquidacionSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Corridas de liquidacion (cabecera). Maestro completo en el ERP (Anita maeliq).
 */
class Liquidacion_SueldosRepository implements Liquidacion_SueldosRepositoryInterface
{
    protected $model;

    public function __construct(Liquidacion_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        return $this->model->newQuery()->orderByDesc('periodo')->orderByDesc('numero')->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeLiquidacion($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = LiquidacionSueldosListadoFiltros::filtrosVacios();
            $filtros['valor'] = $texto;
            $filtros['busqueda'] = $texto;
        } elseif (! is_array($filtros)) {
            $filtros = LiquidacionSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('liquidacion_sueldos.*')
            ->with(['empresa:id,nombre', 'usuario:id,nombre']);

        if (LiquidacionSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            LiquidacionSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderByDesc('liquidacion_sueldos.periodo')
            ->orderByDesc('liquidacion_sueldos.numero');

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(15);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function proximoNumero(int $empresaId): int
    {
        return (int) ($this->model->newQuery()->where('empresa_id', $empresaId)->max('numero') ?? 0) + 1;
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $empresaId = (int) ($data['empresa_id'] ?? 0);
            $data['numero'] = $this->proximoNumero($empresaId);
            $data['estado'] = 'borrador';
            $data['usuario_id'] = auth()->id();

            return $this->model->create($this->normalizar($data));
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $registro = $this->model->findOrFail($id);
            // No se cambia empresa ni numero en edicion.
            unset($data['empresa_id'], $data['numero'], $data['estado']);
            $registro->update($this->normalizar($data, $registro));

            return $registro->fresh();
        });
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            return false;
        }
        // Solo se puede borrar en borrador (regla reforzada en el controller).
        if (! $registro->esEditable()) {
            return false;
        }

        return (bool) $this->model->destroy($id);
    }

    public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null)
    {
        return DB::transaction(function () use ($id, $estado, $usuarioId) {
            $registro = $this->model->findOrFail($id);
            $payload = ['estado' => $estado];
            if ($estado === 'cerrada') {
                $payload['fecha_cierre'] = now();
                $payload['usuario_cierre_id'] = $usuarioId ?? auth()->id();
            }
            $registro->update($payload);

            return $registro->fresh();
        });
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data, ?Liquidacion_Sueldos $existente = null): array
    {
        $out = [];

        if ($existente === null) {
            $out['empresa_id'] = (int) ($data['empresa_id'] ?? 0);
            $out['numero'] = (int) ($data['numero'] ?? 0);
            $out['estado'] = $data['estado'] ?? 'borrador';
            $out['usuario_id'] = $data['usuario_id'] ?? auth()->id();
        }

        $periodo = preg_replace('/\D+/', '', (string) ($data['periodo'] ?? ''));
        if (strlen($periodo) === 6) {
            $out['periodo'] = $periodo;
            $out['periodo_anio'] = (int) substr($periodo, 0, 4);
            $out['periodo_mes'] = (int) substr($periodo, 4, 2);
        }

        $mapDirect = [
            'descripcion', 'tipo', 'lugar_pago', 'banco_deposito', 'periodo_deposito', 'observacion', 'alcance',
        ];
        foreach ($mapDirect as $campo) {
            if (array_key_exists($campo, $data)) {
                $out[$campo] = $data[$campo] !== '' ? $data[$campo] : ($campo === 'alcance' ? 'todos' : null);
            }
        }
        if (array_key_exists('motivoegreso_id', $data)) {
            $out['motivoegreso_id'] = $data['motivoegreso_id'] !== '' && $data['motivoegreso_id'] !== null
                ? (int) $data['motivoegreso_id']
                : null;
        }

        $fechas = ['periodo_desde', 'periodo_hasta', 'fecha_liquidacion', 'fecha_pago', 'fecha_ultimo_deposito'];
        foreach ($fechas as $campo) {
            if (array_key_exists($campo, $data)) {
                $out[$campo] = $data[$campo] !== '' ? $data[$campo] : null;
            }
        }

        if (array_key_exists('simulacion', $data)) {
            $out['simulacion'] = (bool) $data['simulacion'];
        }
        if (array_key_exists('acumula_novedades', $data)) {
            $out['acumula_novedades'] = (bool) $data['acumula_novedades'];
        }

        return $out;
    }
}
