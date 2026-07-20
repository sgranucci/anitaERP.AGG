<?php

namespace App\Repositories\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Fallocaja_Sueldos;
use App\Support\Sueldos\FalloCajaTipo;
use App\Support\Sueldos\FallocajaSueldosListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Tabla de fallos de caja (Anita sueldos / tblfallo).
 * Sync pull desde el bridge solo para llenado inicial; el CRUD vive completo en el ERP
 * y no replica altas/bajas/modificaciones hacia Anita.
 */
class Fallocaja_SueldosRepository implements Fallocaja_SueldosRepositoryInterface
{
    protected $model;

    protected string $tableAnita = 'tblfallo';

    public function __construct(Fallocaja_Sueldos $model)
    {
        $this->model = $model;
    }

    public function all()
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        return $this->model->newQuery()
            ->orderBy('tipo')
            ->orderBy('orden')
            ->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @param  bool|null  $flPaginando
     */
    public function leeFallocaja($filtros, $flPaginando = null)
    {
        if (! $this->model->newQuery()->exists()) {
            $this->sincronizarConAnita();
        }

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => FallocajaSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'sancion',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = FallocajaSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()->select('fallocaja_sueldos.*');

        if (FallocajaSueldosListadoFiltros::tieneCriteriosAplicados($filtros)) {
            FallocajaSueldosListadoFiltros::aplicar($query, $filtros);
        }

        $query->orderBy('fallocaja_sueldos.tipo')->orderBy('fallocaja_sueldos.orden');

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
        return $this->model->create($this->normalizarPayload($data));
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($this->normalizarPayload($data));

        return $registro;
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

    /**
     * Trae desde Anita (bridge) los fallos de caja y los inserta si no existen (llenado inicial).
     * No actualiza ni borra: el maestro vive en el ERP. Dedup por (tipo, orden).
     *
     * @return array{en_anita: int, importados: int, omitidos: int, errores: list<string>}
     */
    public function sincronizarConAnita()
    {
        ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => $this->tableAnita,
            'campos' => 'tblf_id, tblf_orden, tblf_desde, tblf_hasta, tblf_sancion',
            'orderBy' => 'tblf_id, tblf_orden',
        ];
        $parsed = ApiAnita::parsearRespuestaLista($apiAnita->apiCall($data));

        $resultado = ['en_anita' => 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = (string) $parsed['error_lectura'];

            return $resultado;
        }

        // El llenado inicial no debe generar registros de auditoría (solo audita el CRUD del ERP).
        Fallocaja_Sueldos::withoutAuditing(function () use ($parsed, &$resultado) {
            foreach ($parsed['filas'] as $row) {
                $resultado['en_anita']++;

                $tipo = FalloCajaTipo::desdeCodigoAnita((int) ($row->tblf_id ?? 0));
                if ($tipo === null) {
                    $resultado['omitidos']++;
                    continue;
                }

                $orden = (int) ($row->tblf_orden ?? 0);

                $existe = $this->model->newQuery()
                    ->where('tipo', $tipo)
                    ->where('orden', $orden)
                    ->exists();
                if ($existe) {
                    $resultado['omitidos']++;
                    continue;
                }

                $this->model->create([
                    'tipo' => $tipo,
                    'orden' => $orden,
                    'desde' => (float) ($row->tblf_desde ?? 0),
                    'hasta' => (float) ($row->tblf_hasta ?? 0),
                    'sancion' => $this->recortar(trim((string) ($row->tblf_sancion ?? '')), 40),
                ]);
                $resultado['importados']++;
            }
        });

        return $resultado;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data): array
    {
        $tipo = (string) ($data['tipo'] ?? '');
        if (! FalloCajaTipo::esValido($tipo)) {
            $tipo = FalloCajaTipo::BINGO;
        }

        return [
            'tipo' => $tipo,
            'orden' => (int) ($data['orden'] ?? 0),
            'desde' => (float) ($data['desde'] ?? 0),
            'hasta' => (float) ($data['hasta'] ?? 0),
            'sancion' => $this->recortar(trim((string) ($data['sancion'] ?? '')), 40),
        ];
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
