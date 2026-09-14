<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Presupuesto;
use App\Models\Presupuesto\Presupuesto_Escenario;
use App\Support\Presupuesto\PresupuestoListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Presupuesto\Presupuesto_EscenarioRepositoryInterface;
use DB;

class PresupuestoRepository implements PresupuestoRepositoryInterface
{
    protected $model;
    private $presupuesto_escenarioRepository;

    public function __construct(Presupuesto $presupuesto,
                                Presupuesto_EscenarioRepositoryInterface $presupuesto_escenariorepository)
    {
        $this->model = $presupuesto;
        $this->presupuesto_escenarioRepository = $presupuesto_escenariorepository;
    }

    public function all()
    {
        return $this->leePresupuesto(PresupuestoListadoFiltros::filtrosVacios(), false);
    }

    public function leePresupuesto($filtros, $flPaginando = null)
    {
        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => PresupuestoListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = PresupuestoListadoFiltros::filtrosVacios();
        }

        $query = $this->model->with('creousuarios')
            ->with('presupuesto_escenarios')
            ->orderBy('id', 'desc');

        if (PresupuestoListadoFiltros::tieneCriteriosAplicados($filtros)) {
            PresupuestoListadoFiltros::aplicar($query, $filtros);
        }

        if (isset($flPaginando)) {
            if ($flPaginando) {
                return $query->paginate(10);
            }

            return $query->get();
        }

        return $query->get();
    }

    public function create(array $data)
    {
        $data['codigo'] = $this->siguienteCodigoPresupuesto();

        try {
            DB::beginTransaction();

            $presupuesto = $this->model->create($data);

            $nombres = $data['nombres'];
            $tipos = $data['tipos'];
            $codigosEscenarios = [];
            for ($i = 0; $i < count($nombres); $i++) {
                if ($nombres[$i] != '') {
                    $codigosEscenarios[$i] = $this->siguienteCodigoEscenario();

                    $this->presupuesto_escenarioRepository->create([
                        'presupuesto_id' => $presupuesto->id,
                        'nombre' => $nombres[$i],
                        'tipo' => $tipos[$i],
                        'codigo' => $codigosEscenarios[$i],
                        'creousuario_id' => auth()->id(),
                    ]);
                }
            }
            $data['codigos'] = $codigosEscenarios;

            DB::commit();

            return redirect('presupuesto/presupuesto')->with('mensaje', 'Presupuesto creado con éxito');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

    public function update(array $data, $id)
    {
        try {
            DB::beginTransaction();

            $this->model->findOrFail($id)->update($data);

            $this->presupuesto_escenarioRepository->deletePorPresupuesto($id);

            $nombres = $data['nombres'];
            $tipos = $data['tipos'];
            $codigos = $data['codigos'];
            $creousuario_escenario_ids = $data['creousuario_escenario_ids'];
            for ($i = 0; $i < count($nombres); $i++) {
                if ($nombres[$i] != '') {
                    if ($codigos[$i] == '') {
                        $codigos[$i] = $this->siguienteCodigoEscenario();
                        $data['codigos'] = $codigos;
                    }

                    $this->presupuesto_escenarioRepository->create([
                        'presupuesto_id' => $id,
                        'nombre' => $nombres[$i],
                        'tipo' => $tipos[$i],
                        'codigo' => $codigos[$i],
                        'creousuario_id' => $creousuario_escenario_ids[$i],
                    ]);
                }
            }

            DB::commit();

            return redirect('presupuesto/presupuesto')->with('mensaje', 'Presupuesto actualizado con éxito');
        } catch (\Exception $exception) {
            DB::rollBack();

            return back()
                ->with('mensaje', $exception->getMessage());
        }
    }

    public function delete($id)
    {
        $presupuesto = $this->model->find($id);
        if (! $presupuesto) {
            return false;
        }

        return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $presupuesto = $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                        ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                        ->where('codigo',$codigo)->first();
    }

    public function findOrFail($id)
    {
        if (null == $presupuesto = $this->model->with('creousuarios')->with('presupuesto_escenarios')
                                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $presupuesto;
    }

    private function siguienteCodigoPresupuesto(): string
    {
        $ultimo = $this->model->orderByDesc('id')->value('codigo');
        if ($ultimo === null || $ultimo === '') {
            return '1';
        }

        return (string) (((int) filter_var((string) $ultimo, FILTER_SANITIZE_NUMBER_INT)) + 1);
    }

    private function siguienteCodigoEscenario(): string
    {
        $ultimo = Presupuesto_Escenario::query()->orderByDesc('id')->value('codigo');
        if ($ultimo === null || $ultimo === '') {
            return '1';
        }

        return (string) (((int) filter_var((string) $ultimo, FILTER_SANITIZE_NUMBER_INT)) + 1);
    }
}
