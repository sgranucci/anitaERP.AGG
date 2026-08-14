<?php

namespace App\Repositories\Caja;

use App\ApiAnita;
use App\Models\Caja\ConceptoPerdida;
use App\Support\Caja\ConceptoPerdidaListadoFiltros;
use DB;
use Exception;

class ConceptoPerdidaRepository implements ConceptoPerdidaRepositoryInterface
{
    protected string $keyFieldAnita = 'concp_concepto';

    public function __construct(
        private readonly ConceptoPerdida $model,
    ) {}

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Support\Collection<int, ConceptoPerdida>
     */
    public function leeConceptoPerdida($filtros, bool $paginar = false)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ConceptoPerdidaListadoFiltros::MODO_TODOS,
                'campo' => 'codigo',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ConceptoPerdidaListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('concepto_perdida.*');

        if (ConceptoPerdidaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            ConceptoPerdidaListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('concepto_perdida.codigo');

        return $paginar
            ? $query->paginate(10)->appends(ConceptoPerdidaListadoFiltros::paraQueryString($filtros))
            : $query->get();
    }

    public function create(array $data)
    {
        $payload = $this->normalizarDatos($data);

        DB::beginTransaction();
        try {
            $registro = $this->model->create($payload);
            $this->guardarAnita($payload);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh();
    }

    public function update(array $data, $id)
    {
        $payload = $this->normalizarDatos($data);

        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $codigoAnita = (int) $registro->codigo;
            $registro->update($payload);
            $payload['codigo'] = $codigoAnita;
            $this->actualizarAnita($payload, $codigoAnita);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $registro->fresh();
    }

    public function delete($id)
    {
        DB::beginTransaction();
        try {
            $registro = $this->model->findOrFail($id);
            $codigo = (int) $registro->codigo;
            $this->eliminarAnita($codigo);
            $resultado = $this->model->destroy($id);

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return $resultado;
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function sincronizarConAnita(?int $codigo = null): array
    {
        $ret = [
            'en_anita' => 0,
            'importados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', [
                'concp_concepto',
                'concp_desc',
            ]),
        ];

        if ($codigo !== null && $codigo > 0) {
            $data['whereArmado'] = " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ";
        }

        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita)) {
            $ret['errores'][] = 'Anita no devolvió datos válidos para concperd.';

            return $ret;
        }

        $ret['en_anita'] = count($dataAnita);
        $codigosLocales = $this->model->newQuery()->pluck('codigo')->map(fn ($c) => (int) $c)->all();

        foreach ($dataAnita as $row) {
            $codigoLocal = (int) ($row->{$this->keyFieldAnita} ?? 0);
            if ($codigoLocal <= 0) {
                continue;
            }

            if (in_array($codigoLocal, $codigosLocales, true)) {
                $ret['omitidos']++;

                continue;
            }

            try {
                $estado = $this->traerRegistroDeAnita($codigoLocal);
                if ($estado === 'importado') {
                    $ret['importados']++;
                    $codigosLocales[] = $codigoLocal;
                } else {
                    $ret['errores'][] = "Concepto Anita {$codigoLocal}: {$estado}.";
                }
            } catch (\Throwable $e) {
                $ret['errores'][] = "Concepto Anita {$codigoLocal}: ".$e->getMessage();
            }
        }

        return $ret;
    }

    public function traerRegistroDeAnita(int $codigo): string
    {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'campos' => implode(', ', [
                'concp_concepto',
                'concp_desc',
            ]),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return 'no_encontrado';
        }

        $payload = $this->convierteDatosDeAnita($dataAnita[0]);
        if ($payload === null) {
            return 'invalido';
        }

        DB::beginTransaction();
        try {
            $this->model->create($payload);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();

            throw $e;
        }

        return 'importado';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{codigo: int, nombre: string}
     */
    private function normalizarDatos(array $data): array
    {
        return [
            'codigo' => (int) ($data['codigo'] ?? 0),
            'nombre' => mb_substr(trim((string) ($data['nombre'] ?? '')), 0, 30),
        ];
    }

    private function guardarAnita(array $data): bool
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'tabla' => $this->tablaAnita(),
            'acc' => 'insert',
            'sistema' => $this->sistemaAnita(),
            'campos' => '
                concp_concepto,
                concp_desc
            ',
            'valores' => "
                '".(int) $data['codigo']."',
                '".$this->escaparAnita((string) $data['nombre'])."'
            ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function actualizarAnita(array $data, int $codigoAnita): bool
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'update',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'valores' => "
                concp_desc = '".$this->escaparAnita((string) $data['nombre'])."'
            ",
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigoAnita."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    private function eliminarAnita(int $codigo): bool
    {
        $apiAnita = new ApiAnita();
        $payload = [
            'acc' => 'delete',
            'tabla' => $this->tablaAnita(),
            'sistema' => $this->sistemaAnita(),
            'whereArmado' => " WHERE {$this->keyFieldAnita} = '".(int) $codigo."' ",
        ];

        return (bool) $apiAnita->apiCallEscritura($payload);
    }

    /**
     * @return array{codigo: int, nombre: string}|null
     */
    private function convierteDatosDeAnita(object $row): ?array
    {
        $codigo = (int) ($row->concp_concepto ?? 0);
        if ($codigo <= 0) {
            return null;
        }

        return [
            'codigo' => $codigo,
            'nombre' => mb_substr(trim((string) ($row->concp_desc ?? '')), 0, 30),
        ];
    }

    private function escaparAnita(string $valor): string
    {
        return str_replace("'", "''", trim($valor));
    }

    private function sistemaAnita(): string
    {
        return (string) config('concepto_perdida_anita.sistema', 'caja');
    }

    private function tablaAnita(): string
    {
        return (string) config('concepto_perdida_anita.tabla', 'concperd');
    }
}
