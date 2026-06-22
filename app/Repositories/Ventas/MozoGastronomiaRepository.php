<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class MozoGastronomiaRepository implements MozoGastronomiaRepositoryInterface
{
    /** vend_codigo Anita: numérico, hasta 5 dígitos (excluye basura importada tipo legajo/DNI). */
    private const LONGITUD_MAXIMA_CODIGO_NUMERICO = 5;
    protected $model;

    public function __construct(
        MozoGastronomia $mozoGastronomia,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $mozoGastronomia;
    }

    public function all()
    {
        $query = $this->model->with('empresa')->orderBy('nombre')->orderBy('codigo');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function create(array $data)
    {
        if (! empty($data['clave'])) {
            $data['clave'] = \Illuminate\Support\Facades\Hash::make((string) $data['clave']);
        }

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        if (array_key_exists('clave', $data)) {
            if ($data['clave'] === null || $data['clave'] === '') {
                unset($data['clave']);
            } else {
                $data['clave'] = \Illuminate\Support\Facades\Hash::make((string) $data['clave']);
            }
        }

        return $this->model->findOrFail($id)->update($data);
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        return $this->model->find($id);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function existeRegistro(): bool
    {
        return $this->model->query()->exists();
    }

    public function consultaMozo(string $consulta, int $empresaId, bool $filtrarEmpresasAsignadas = false): string
    {
        $columns = ['mozo_gastronomia.id', 'mozo_gastronomia.nombre', 'mozo_gastronomia.codigo'];
        $columnsOut = ['id', 'nombre', 'codigo'];
        $consulta = strtoupper(trim($consulta));

        $query = $this->model->newQuery();
        if ($filtrarEmpresasAsignadas) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        } else {
            $query->where('empresa_id', $empresaId);
        }
        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta, $columns) {
                foreach ($columns as $col) {
                    $q->orWhere($col, 'LIKE', '%'.$consulta.'%');
                }
            });
        }

        $data = $query->orderBy('nombre')->limit(200)->get(['id', 'nombre', 'codigo']);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                foreach ($columnsOut as $col) {
                    $output['data'] .= '<td class="'.$col.'">'.e($row->{$col}).'</td>';
                }
                $output['data'] .= '<td><a class="btn btn-warning btn-sm eligeconsultamozo">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function findPorCodigo(string $codigo, int $empresaId, bool $filtrarEmpresasAsignadas = false): ?MozoGastronomia
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return null;
        }

        $mozo = $this->buscarMozoPorCodigoQuery($codigo, $empresaId, $filtrarEmpresasAsignadas)->first();

        if ($mozo) {
            return $mozo;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->buscarMozoPorCodigoQuery($alt, $empresaId, $filtrarEmpresasAsignadas)->first();
        }

        return null;
    }

    private function buscarMozoPorCodigoQuery(string $codigo, int $empresaId, bool $filtrarEmpresasAsignadas)
    {
        $query = $this->model->newQuery()->where('codigo', $codigo);
        if ($filtrarEmpresasAsignadas) {
            $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);
        } else {
            $query->where('empresa_id', $empresaId);
        }

        return $query;
    }

    public function findPorId(int $id, int $empresaId): ?MozoGastronomia
    {
        if ($id <= 0) {
            return null;
        }

        return $this->model->newQuery()
            ->where('id', $id)
            ->where('empresa_id', $empresaId)
            ->first();
    }

    public function proximoCodigoLocal(int $empresaId): string
    {
        if ($empresaId <= 0) {
            return '1';
        }

        $max = (int) $this->queryCodigosNumericosMozo($empresaId)
            ->selectRaw('MAX(CAST(codigo AS UNSIGNED)) as max_codigo')
            ->value('max_codigo');

        $proximo = (string) ($max + 1);

        while ($this->codigoExisteEnEmpresa($empresaId, $proximo)) {
            $max++;
            $proximo = (string) ($max + 1);
        }

        return $proximo;
    }

    public function codigoExisteEnEmpresa(int $empresaId, string $codigo, ?int $ignorarId = null): bool
    {
        $codigo = trim($codigo);
        if ($codigo === '' || $empresaId <= 0) {
            return false;
        }

        $query = $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->where('codigo', $codigo);

        if ($ignorarId !== null && $ignorarId > 0) {
            $query->where('id', '!=', $ignorarId);
        }

        return $query->exists();
    }

    private function queryCodigosNumericosMozo(int $empresaId)
    {
        return $this->model->newQuery()
            ->where('empresa_id', $empresaId)
            ->whereRaw('codigo REGEXP ?', ['^[0-9]+$'])
            ->whereRaw('CHAR_LENGTH(codigo) <= ?', [self::LONGITUD_MAXIMA_CODIGO_NUMERICO]);
    }
}
