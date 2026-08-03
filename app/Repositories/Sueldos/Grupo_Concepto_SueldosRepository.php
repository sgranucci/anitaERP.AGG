<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Grupo_Concepto_Sueldos;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Item_Sueldos;
use App\Models\Sueldos\Grupo_Concepto_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\GrupoConceptoSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class Grupo_Concepto_SueldosRepository implements Grupo_Concepto_SueldosRepositoryInterface
{
    public function __construct(
        protected Grupo_Concepto_Sueldos $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeGrupo($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => GrupoConceptoSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'descripcion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => null,
                'empresa_scope' => 'todas',
            ];
        } elseif (! is_array($filtros)) {
            $filtros = GrupoConceptoSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('grupo_concepto_sueldos.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'grupo_concepto_sueldos.empresa_id')
            ->withCount('items')
            ->with('empresa:id,nombre');

        // Seguridad: solo empresas asignadas (+ grupos sin empresa = todas).
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas(
            $query,
            'grupo_concepto_sueldos.empresa_id',
            true
        );

        // Empresa externa + texto se aplican siempre (empresa aún sin texto).
        GrupoConceptoSueldosListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('grupo_concepto_sueldos.codigo')
            ->orderBy('grupo_concepto_sueldos.id');

        $result = isset($flPaginando) && $flPaginando
            ? $query->paginate(15)
            : $query->get();

        $items = method_exists($result, 'items') ? $result->items() : $result;
        foreach ($items as $row) {
            $row->setAttribute('nombreempresa', optional($row->empresa)->nombre);
        }

        return $result;
    }

    public function findOrFail($id)
    {
        $g = $this->model->newQuery()
            ->with(['items.concepto:id,codigo,descripcion,tipo', 'empresa:id,nombre'])
            ->find($id);
        if (! $g) {
            throw (new ModelNotFoundException)->setModel(Grupo_Concepto_Sueldos::class, [$id]);
        }

        return $g;
    }

    public function create(array $data)
    {
        return DB::transaction(function () use ($data) {
            $items = $data['conceptos'] ?? $data['items'] ?? [];
            unset($data['conceptos'], $data['items']);
            $grupo = $this->model->create($this->normalizar($data));
            $this->syncItems($grupo, is_array($items) ? $items : []);

            return $grupo->load('items.concepto');
        });
    }

    public function update(array $data, $id)
    {
        return DB::transaction(function () use ($data, $id) {
            $grupo = $this->model->findOrFail($id);
            $items = $data['conceptos'] ?? $data['items'] ?? null;
            unset($data['conceptos'], $data['items']);
            $grupo->update($this->normalizar($data, $grupo));
            if (is_array($items)) {
                $this->syncItems($grupo, $items);
            }

            return $grupo->load('items.concepto');
        });
    }

    public function delete($id)
    {
        $g = $this->model->find($id);
        if (! $g) {
            return false;
        }

        return (bool) $g->delete();
    }

    /**
     * Anita `grupo`: (empresa, codigo, linea, concepto) → cabecera + ítems.
     */
    public function sincronizarConAnita(): array
    {
        ini_set('max_execution_time', '600');
        ini_set('memory_limit', '-1');

        $res = ['en_anita' => 0, 'grupos' => 0, 'items' => 0, 'omitidos' => 0, 'errores' => []];

        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'grupo',
            'campos' => 'grp_empresa, grp_codigo, grp_linea, grp_concepto',
            'orderBy' => 'grp_empresa, grp_codigo, grp_linea',
        ]));

        if (! empty($parsed['error_lectura'])) {
            $res['errores'][] = (string) $parsed['error_lectura'];

            return $res;
        }

        $res['en_anita'] = count($parsed['filas']);
        $conceptos = Concepto_Sueldos::query()->pluck('id', 'codigo')->map(fn ($id) => (int) $id)->all();

        /** @var array<string, list<array{linea: int, concepto: int}>> $porGrupo */
        $porGrupo = [];
        foreach ($parsed['filas'] as $f) {
            $emp = (int) ($f->grp_empresa ?? 0);
            $cod = (int) ($f->grp_codigo ?? 0);
            $conc = (int) ($f->grp_concepto ?? 0);
            if ($emp <= 0 || $cod <= 0 || $conc <= 0) {
                $res['omitidos']++;
                continue;
            }
            $porGrupo[$emp.':'.$cod][] = [
                'linea' => (int) ($f->grp_linea ?? 0),
                'concepto' => $conc,
            ];
        }

        Grupo_Concepto_Sueldos::withoutAuditing(function () use ($porGrupo, $conceptos, &$res) {
            foreach ($porGrupo as $key => $lineas) {
                [$empresaId, $codigo] = array_map('intval', explode(':', $key));
                $grupo = Grupo_Concepto_Sueldos::query()
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $codigo)
                    ->first();

                if (! $grupo) {
                    $grupo = Grupo_Concepto_Sueldos::create([
                        'empresa_id' => $empresaId,
                        'codigo' => $codigo,
                        'descripcion' => 'Grupo Anita '.$codigo,
                        'activo' => true,
                        'origen' => 'sync_anita',
                    ]);
                    $res['grupos']++;
                }

                $orden = 0;
                foreach ($lineas as $ln) {
                    $conceptoId = $conceptos[$ln['concepto']] ?? null;
                    if (! $conceptoId) {
                        $res['omitidos']++;
                        continue;
                    }
                    $orden++;
                    $existe = Grupo_Concepto_Item_Sueldos::query()
                        ->where('grupo_concepto_id', $grupo->id)
                        ->where('concepto_id', $conceptoId)
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    Grupo_Concepto_Item_Sueldos::create([
                        'grupo_concepto_id' => $grupo->id,
                        'concepto_id' => $conceptoId,
                        'orden' => $ln['linea'] > 0 ? $ln['linea'] : $orden,
                        'activo' => true,
                    ]);
                    $res['items']++;
                }
            }
        });

        $res['codigos_empleado'] = $this->importarCodigosGrpDesdeAnita();
        $res['vinculados'] = $this->vincularEmpleadosConGrupos();

        return $res;
    }

    /**
     * Trae emp_grp1/2/3 de Anita y los guarda en empleados ya existentes (insert-only de códigos).
     */
    public function importarCodigosGrpDesdeAnita(): int
    {
        $api = new ApiAnita();
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'empleado',
            'campos' => 'emp_empresa, emp_legajo, emp_grp1, emp_grp2, emp_grp3',
            'orderBy' => 'emp_empresa, emp_legajo',
        ]));
        if (! empty($parsed['error_lectura'])) {
            return 0;
        }

        $porKey = [];
        foreach ($parsed['filas'] as $f) {
            $emp = (int) ($f->emp_empresa ?? 0);
            $leg = (int) ($f->emp_legajo ?? 0);
            if ($emp <= 0 || $leg <= 0) {
                continue;
            }
            $porKey[$emp.':'.$leg] = [
                1 => (int) ($f->emp_grp1 ?? 0) ?: null,
                2 => (int) ($f->emp_grp2 ?? 0) ?: null,
                3 => (int) ($f->emp_grp3 ?? 0) ?: null,
            ];
        }

        $n = 0;
        Empleado_Sueldos::query()
            ->select(['id', 'empresa_id', 'legajo', 'grupo_concepto_1_codigo', 'grupo_concepto_2_codigo', 'grupo_concepto_3_codigo'])
            ->orderBy('id')
            ->chunkById(300, function ($rows) use ($porKey, &$n) {
                foreach ($rows as $e) {
                    $g = $porKey[$e->empresa_id.':'.$e->legajo] ?? null;
                    if (! $g) {
                        continue;
                    }
                    $upd = [];
                    foreach ([1, 2, 3] as $slot) {
                        $nuevo = $g[$slot];
                        $actual = $e->{'grupo_concepto_'.$slot.'_codigo'};
                        if ($nuevo && (int) $actual !== (int) $nuevo) {
                            $upd['grupo_concepto_'.$slot.'_codigo'] = $nuevo;
                        }
                    }
                    if ($upd !== []) {
                        Empleado_Sueldos::query()->where('id', $e->id)->update($upd);
                        $n++;
                    }
                }
            });

        return $n;
    }

    /**
     * Reescribe pivots origen=sync_anita desde emp_grp1/2/3 (códigos espejo).
     * Conserva grupos origen=manual (N adicionales del ERP).
     */
    public function vincularEmpleadosConGrupos(): int
    {
        $grupos = Grupo_Concepto_Sueldos::query()
            ->get(['id', 'empresa_id', 'codigo']);
        $map = [];
        foreach ($grupos as $g) {
            $map[$g->empresa_id.':'.$g->codigo] = (int) $g->id;
            if (! isset($map['0:'.$g->codigo])) {
                $map['0:'.$g->codigo] = (int) $g->id;
            }
        }

        $n = 0;
        Empleado_Sueldos::query()
            ->select(['id', 'empresa_id', 'grupo_concepto_1_codigo', 'grupo_concepto_2_codigo', 'grupo_concepto_3_codigo'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($map, &$n) {
                foreach ($rows as $e) {
                    $deseados = [];
                    foreach ([1, 2, 3] as $slot) {
                        $cod = (int) ($e->{'grupo_concepto_'.$slot.'_codigo'} ?? 0);
                        if ($cod <= 0) {
                            continue;
                        }
                        $gid = $map[$e->empresa_id.':'.$cod] ?? $map['0:'.$cod] ?? null;
                        if ($gid && ! isset($deseados[$gid])) {
                            $deseados[$gid] = true;
                        }
                    }

                    $manuales = Empleado_Grupo_Concepto_Sueldos::query()
                        ->where('empleado_id', $e->id)
                        ->where('origen', 'manual')
                        ->pluck('grupo_concepto_id')
                        ->map(fn ($id) => (int) $id)
                        ->all();
                    $manualSet = array_fill_keys($manuales, true);

                    Empleado_Grupo_Concepto_Sueldos::query()
                        ->where('empleado_id', $e->id)
                        ->where('origen', 'sync_anita')
                        ->delete();

                    $maxOrden = (int) Empleado_Grupo_Concepto_Sueldos::query()
                        ->where('empleado_id', $e->id)
                        ->max('orden');
                    $orden = $maxOrden;
                    $cambio = false;
                    foreach (array_keys($deseados) as $gid) {
                        if (isset($manualSet[$gid])) {
                            continue; // ya está como manual
                        }
                        $orden++;
                        Empleado_Grupo_Concepto_Sueldos::create([
                            'empleado_id' => $e->id,
                            'grupo_concepto_id' => $gid,
                            'orden' => $orden,
                            'origen' => 'sync_anita',
                        ]);
                        $cambio = true;
                    }
                    if ($cambio || $deseados !== []) {
                        $n++;
                    }
                }
            });

        return $n;
    }

    /**
     * @param  list<int|array{concepto_id?: int, id?: int, orden?: int}>  $items
     */
    private function syncItems(Grupo_Concepto_Sueldos $grupo, array $items): void
    {
        Grupo_Concepto_Item_Sueldos::query()->where('grupo_concepto_id', $grupo->id)->delete();
        $orden = 0;
        foreach ($items as $it) {
            $conceptoId = is_array($it)
                ? (int) ($it['concepto_id'] ?? $it['id'] ?? 0)
                : (int) $it;
            if ($conceptoId <= 0) {
                continue;
            }
            $orden++;
            Grupo_Concepto_Item_Sueldos::create([
                'grupo_concepto_id' => $grupo->id,
                'concepto_id' => $conceptoId,
                'orden' => is_array($it) ? (int) ($it['orden'] ?? $orden) : $orden,
                'activo' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizar(array $data, ?Grupo_Concepto_Sueldos $existente = null): array
    {
        return [
            'empresa_id' => ! empty($data['empresa_id']) ? (int) $data['empresa_id'] : null,
            'codigo' => (int) ($data['codigo'] ?? $existente->codigo ?? 0),
            'descripcion' => isset($data['descripcion']) ? trim((string) $data['descripcion']) : ($existente->descripcion ?? null),
            'activo' => array_key_exists('activo', $data) ? (bool) $data['activo'] : ($existente->activo ?? true),
            'origen' => $data['origen'] ?? ($existente->origen ?? 'manual'),
        ];
    }
}
