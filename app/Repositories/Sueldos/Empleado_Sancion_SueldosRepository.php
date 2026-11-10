<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Empleado_Sancion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Listado\ListadoAgrupacionSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Sueldos\SancionReporteListadoColumnas;
use App\Support\Sueldos\SancionReporteListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Empleado_Sancion_SueldosRepository implements Empleado_Sancion_SueldosRepositoryInterface
{
    public function __construct(
        protected Empleado_Sancion_Sueldos $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeSancionesReporte(array $filtros, bool $flPaginando = false)
    {
        $query = $this->querySanciones($filtros);
        $perPage = (int) ($filtros['_per_page'] ?? 15);
        if ($perPage < 1) {
            $perPage = 15;
        }

        return $flPaginando ? $query->paginate($perPage) : $query->get();
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function cortesSanciones(array $filtros): array
    {
        $campos = SancionReporteListadoFiltros::camposOrdenables();
        $agrupar = ListadoAgrupacionSupport::normalizar($filtros['agrupar'] ?? [], $campos);
        $etiquetas = ListadoColumnaEtiquetaSupport::etiquetasEfectivas(
            SancionReporteListadoColumnas::RECURSO,
            SancionReporteListadoColumnas::catalogoActivo()
        );

        return ListadoCortesSupport::calcular(
            $this->querySanciones($filtros),
            $agrupar,
            $campos,
            'empleado_sancion_sueldos.id',
            static fn (object $row, string $key): string => SancionReporteListadoColumnas::valorCelda($row, $key),
            static fn (string $key): ?array => SancionReporteListadoColumnas::sqlAgrupacion($key),
            $etiquetas,
            SancionReporteListadoColumnas::MEDIDAS
        );
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function querySanciones(array $filtros)
    {
        $query = $this->model->newQuery()
            ->select([
                'empleado_sancion_sueldos.*',
                'empleado_sueldos.legajo as legajo',
                'empleado_sueldos.nombre as nombreempleado',
                'empresa.nombre as nombreempresa',
                'centrocosto.nombre as nombrecentrocosto',
                'tipo_sancion_sueldos.nombre as nombretipo',
                'motivo_sancion_sueldos.nombre as nombremotivo',
            ])
            ->with([
                'empleado:id,empresa_id,legajo,nombre,centrocosto_id',
                'empleado.empresa:id,nombre',
                'tipo:id,codigo,nombre,clase',
                'motivo:id,codigo,nombre',
            ])
            ->join('empleado_sueldos', 'empleado_sueldos.id', '=', 'empleado_sancion_sueldos.empleado_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'empleado_sueldos.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'empleado_sueldos.centrocosto_id')
            ->leftJoin('tipo_sancion_sueldos', 'tipo_sancion_sueldos.id', '=', 'empleado_sancion_sueldos.tipo_sancion_id')
            ->leftJoin('motivo_sancion_sueldos', 'motivo_sancion_sueldos.id', '=', 'empleado_sancion_sueldos.motivo_sancion_id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'empleado_sueldos.empresa_id');
        SancionReporteListadoFiltros::aplicar($query, $filtros);
        SancionReporteListadoFiltros::aplicarOrden($query, $filtros);

        return $query;
    }

    public function create(array $data)
    {
        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $registro = $this->model->findOrFail($id);
        $registro->update($data);

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);

        return $registro ? (bool) $registro->delete() : false;
    }

    public function find($id)
    {
        $registro = $this->model->find($id);
        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }
}
