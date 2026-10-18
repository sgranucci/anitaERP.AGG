<?php

namespace App\Repositories\Contable;

use App\Models\Contable\BienUso;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Contable\BienUsoListadoColumnas;
use App\Support\Contable\BienUsoListadoFiltros;
use App\Support\Contable\BienUsoVisibilidadSupport;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;

class BienUsoRepository implements BienUsoRepositoryInterface
{
    protected BienUso $model;

    public function __construct(
        BienUso $bienUso,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $bienUso;
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     */
    public function leeBienUso($filtros, ?bool $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(BienUsoListadoFiltros::filtrosVacios(), [
                'valor' => $texto,
                'busqueda' => $texto,
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = BienUsoListadoFiltros::filtrosVacios();
        }

        $query = $this->queryBienUsoListado($filtros);

        $perPage = (int) ($filtros['_per_page'] ?? 15);
        if ($perPage < 1) {
            $perPage = 15;
        }

        $result = $flPaginando === true
            ? $query->paginate($perPage)
            : $query->get();

        $items = method_exists($result, 'items') ? $result->items() : $result;
        foreach ($items as $row) {
            if (trim((string) ($row->nombreempresa ?? '')) === '') {
                $row->setAttribute('nombreempresa', optional($row->empresa)->nombre);
            }
            if (trim((string) ($row->codigocentrocosto ?? '')) === '') {
                $row->setAttribute('codigocentrocosto', optional($row->centrocostos)->codigo);
            }
            if (trim((string) ($row->nombrecentrocosto ?? '')) === '') {
                $row->setAttribute('nombrecentrocosto', optional($row->centrocostos)->nombre);
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesBienUso(array $filtros): array
    {
        $campos = BienUsoListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            BienUsoListadoColumnas::RECURSO,
            BienUsoListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            $this->queryBienUsoListado($filtros),
            $agrupar,
            $campos,
            'bien_uso.id',
            static fn (object $row, string $key): string => BienUsoListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => BienUsoListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            BienUsoListadoColumnas::medidasActivas()
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBienUsoListado(array $filtros)
    {
        $select = [
            'bien_uso.*',
            'empresa.nombre as nombreempresa',
            'centrocosto.codigo as codigocentrocosto',
            'centrocosto.nombre as nombrecentrocosto',
        ];

        $query = $this->model->newQuery()
            ->select($select)
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'bien_uso.centrocosto_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'bien_uso.empresa_id')
            ->with(['centrocostos', 'empresa']);

        if (Schema::hasColumn('bien_uso', 'rubro_id') && Schema::hasTable('rubro')) {
            $query->leftJoin('rubro', 'rubro.id', '=', 'bien_uso.rubro_id')
                ->addSelect('rubro.nombre as nombrerubro');
        }
        if (Schema::hasColumn('bien_uso', 'subrubro_id') && Schema::hasTable('subrubro')) {
            $query->leftJoin('subrubro', 'subrubro.id', '=', 'bien_uso.subrubro_id')
                ->addSelect('subrubro.nombre as nombresubrubro');
        }
        if (Schema::hasColumn('bien_uso', 'cuentacontable_origen_id') && Schema::hasTable('cuentacontable')) {
            $query->leftJoin('cuentacontable as cta_origen', 'cta_origen.id', '=', 'bien_uso.cuentacontable_origen_id')
                ->addSelect('cta_origen.codigo as codigo_cuenta_origen');
        }
        if (Schema::hasColumn('bien_uso', 'cuentacontable_amort_id') && Schema::hasTable('cuentacontable')) {
            $query->leftJoin('cuentacontable as cta_amort', 'cta_amort.id', '=', 'bien_uso.cuentacontable_amort_id')
                ->addSelect('cta_amort.codigo as codigo_cuenta_amort');
        }
        if (Schema::hasColumn('bien_uso', 'cuentacontable_contrap_id') && Schema::hasTable('cuentacontable')) {
            $query->leftJoin('cuentacontable as cta_contrap', 'cta_contrap.id', '=', 'bien_uso.cuentacontable_contrap_id')
                ->addSelect('cta_contrap.codigo as codigo_cuenta_contrap');
        }
        if (Schema::hasColumn('bien_uso', 'moneda_id') && Schema::hasTable('moneda')) {
            $query->leftJoin('moneda', 'moneda.id', '=', 'bien_uso.moneda_id')
                ->addSelect('moneda.nombre as nombremoneda');
        }
        if (Schema::hasColumn('bien_uso', 'proveedor_id') && Schema::hasTable('proveedor')) {
            $query->leftJoin('proveedor', 'proveedor.id', '=', 'bien_uso.proveedor_id')
                ->addSelect('proveedor.nombre as nombreproveedor');
        }
        if (Schema::hasColumn('bien_uso', 'bien_reemplazo_id')) {
            $query->leftJoin('bien_uso as bien_reemplazo', 'bien_reemplazo.id', '=', 'bien_uso.bien_reemplazo_id');
            if (Schema::hasColumn('bien_uso', 'descripcion')) {
                $query->addSelect('bien_reemplazo.descripcion as descripcion_bien_reemplazo');
            } elseif (Schema::hasColumn('bien_uso', 'codigo_bien')) {
                $query->addSelect('bien_reemplazo.codigo_bien as descripcion_bien_reemplazo');
            }
        }

        // Módulo estándar: siempre restringir a empresas asignadas del usuario.
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'bien_uso.empresa_id', true);

        $filtroCentro = (int) ($filtros['centrocosto_id'] ?? 0);
        BienUsoVisibilidadSupport::aplicarScope(
            $query,
            $filtroCentro > 0 ? $filtroCentro : null
        );

        BienUsoListadoFiltros::aplicar($query, $filtros);
        BienUsoListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarDatos($data));
    }

    public function update(array $data, $id)
    {
        return $this->model->findOrFail($id)->update($this->normalizarDatos($data));
    }

    public function delete($id)
    {
        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $bienUso = $this->model->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $bienUso;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    private function normalizarDatos(array $data): array
    {
        if (array_key_exists('codigo_inventario', $data) && $data['codigo_inventario'] === '') {
            $data['codigo_inventario'] = null;
        }

        if (array_key_exists('ip', $data) && $data['ip'] === '') {
            $data['ip'] = null;
        }

        if (array_key_exists('modelo', $data) && $data['modelo'] === '') {
            $data['modelo'] = null;
        }

        if (array_key_exists('numero_serie', $data) && $data['numero_serie'] === '') {
            $data['numero_serie'] = null;
        }

        if (array_key_exists('observaciones', $data) && $data['observaciones'] === '') {
            $data['observaciones'] = null;
        }

        if (array_key_exists('uid', $data) && $data['uid'] === '') {
            $data['uid'] = null;
        }

        if (array_key_exists('vendor', $data) && $data['vendor'] === '') {
            $data['vendor'] = null;
        }

        if (array_key_exists('tema', $data) && $data['tema'] === '') {
            $data['tema'] = null;
        }

        if (array_key_exists('empresa_id', $data) && ($data['empresa_id'] === '' || $data['empresa_id'] === null)) {
            $data['empresa_id'] = null;
        }

        if (array_key_exists('hostname', $data) && $data['hostname'] === '') {
            $data['hostname'] = null;
        }

        if (($data['tipo_bien'] ?? '') === 'M') {
            $data['hostname'] = null;
        }

        if (($data['tipo_bien'] ?? '') === 'M' && ! empty($data['uid']) && empty($data['codigo_inventario'])) {
            $partes = explode('-', (string) $data['uid']);
            $codigo = (int) end($partes);
            if ($codigo > 0) {
                $data['codigo_inventario'] = $codigo;
            }
        }

        return $data;
    }
}
