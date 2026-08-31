<?php

namespace App\Repositories\Sueldos;

use App\Models\Sueldos\Concepto_Imputacion_Sueldos;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Sueldos\ConceptoImputacionSueldosListadoFiltros;
use App\Support\Sueldos\SueldosAsientoMapeoSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class Concepto_Imputacion_SueldosRepository implements Concepto_Imputacion_SueldosRepositoryInterface
{
    public function __construct(
        protected Concepto_Imputacion_Sueldos $model,
        protected EmpresaRepositoryInterface $empresaRepository,
    ) {
    }

    public function leeImputacion($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ConceptoImputacionSueldosListadoFiltros::MODO_TODOS,
                'campo' => 'clave',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_id' => null,
                'empresa_scope' => 'todas',
            ];
        } elseif (! is_array($filtros)) {
            $filtros = ConceptoImputacionSueldosListadoFiltros::filtrosVacios();
        }

        $query = $this->model->newQuery()
            ->select('concepto_imputacion_sueldos.*')
            ->leftJoin('empresa', 'empresa.id', '=', 'concepto_imputacion_sueldos.empresa_id')
            ->leftJoin('concepto_sueldos', 'concepto_sueldos.id', '=', 'concepto_imputacion_sueldos.concepto_id')
            ->leftJoin('cuentacontable as cta_debe', 'cta_debe.id', '=', 'concepto_imputacion_sueldos.cuenta_debe_id')
            ->leftJoin('cuentacontable as cta_haber', 'cta_haber.id', '=', 'concepto_imputacion_sueldos.cuenta_haber_id')
            ->with([
                'empresa:id,nombre',
                'concepto:id,codigo,descripcion,tipo',
                'cuentaDebe:id,codigo,nombre',
                'cuentaHaber:id,codigo,nombre',
            ]);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas(
            $query,
            'concepto_imputacion_sueldos.empresa_id',
            false
        );

        ConceptoImputacionSueldosListadoFiltros::aplicar($query, $filtros);

        $query->orderBy('concepto_imputacion_sueldos.alcance')
            ->orderBy('concepto_imputacion_sueldos.clave')
            ->orderBy('concepto_imputacion_sueldos.id');

        $result = isset($flPaginando) && $flPaginando
            ? $query->paginate(15)
            : $query->get();

        $items = method_exists($result, 'items') ? $result->items() : $result;
        foreach ($items as $row) {
            $row->setAttribute('nombreempresa', optional($row->empresa)->nombre);
            $row->setAttribute('clave_label', $row->claveLabel());
        }

        return $result;
    }

    public function create(array $data)
    {
        return $this->model->create($this->normalizarPayload($data));
    }

    public function update(array $data, $id)
    {
        $registro = $this->findOrFail($id);
        $registro->update($this->normalizarPayload($data));

        return $registro;
    }

    public function delete($id)
    {
        $registro = $this->model->find($id);

        return $registro ? (bool) $registro->delete() : false;
    }

    public function find($id)
    {
        $registro = $this->model->newQuery()
            ->with(['empresa:id,nombre', 'concepto:id,codigo,descripcion,tipo', 'cuentaDebe:id,codigo,nombre', 'cuentaHaber:id,codigo,nombre'])
            ->find($id);
        if ($registro === null) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $registro;
    }

    public function findOrFail($id)
    {
        return $this->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizarPayload(array $data): array
    {
        $alcance = (string) ($data['alcance'] ?? SueldosAsientoMapeoSupport::ALCANCE_TIPO);
        $conceptoId = $alcance === SueldosAsientoMapeoSupport::ALCANCE_CONCEPTO
            ? (int) ($data['concepto_id'] ?? 0)
            : null;
        $rubro = $alcance === SueldosAsientoMapeoSupport::ALCANCE_RUBRO
            ? trim((string) ($data['rubro'] ?? ''))
            : null;
        $tipo = $alcance === SueldosAsientoMapeoSupport::ALCANCE_TIPO
            ? trim((string) ($data['tipo'] ?? ''))
            : null;

        if ($conceptoId !== null && $conceptoId <= 0) {
            $conceptoId = null;
        }
        if ($rubro === '') {
            $rubro = null;
        }
        if ($tipo === '') {
            $tipo = null;
        }

        $clave = SueldosAsientoMapeoSupport::clavePara($alcance, $conceptoId, $rubro, $tipo);

        return [
            'empresa_id' => (int) $data['empresa_id'],
            'alcance' => $alcance,
            'clave' => $clave,
            'concepto_id' => $conceptoId,
            'rubro' => $rubro,
            'tipo' => $tipo,
            'cuenta_debe_id' => $this->idONull($data['cuenta_debe_id'] ?? null),
            'cuenta_haber_id' => $this->idONull($data['cuenta_haber_id'] ?? null),
            'observacion' => $this->nullSiVacio($data['observacion'] ?? null),
        ];
    }

    private function idONull(mixed $id): ?int
    {
        $n = (int) $id;

        return $n > 0 ? $n : null;
    }

    private function nullSiVacio(mixed $valor): ?string
    {
        $t = trim((string) $valor);

        return $t === '' ? null : $t;
    }
}
