<?php

namespace App\Queries\Contable;

use App\Models\Contable\Asiento;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;

class AsientoQuery implements AsientoQueryInterface
{
    protected $model;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Asiento $asiento, EmpresaRepositoryInterface $empresaRepository)
    {
        $this->model = $asiento;
        $this->empresaRepository = $empresaRepository;
    }

    public function first()
    {
        return $this->model->first();
    }

    public function all()
    {
        return $this->model->get();
    }

    public function allQuery(array $campos)
    {
        return $this->model->select($campos)->get();
    }

    public function leeAsiento($busqueda, $flPaginando = null, $empresaId = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $busqueda = is_string($busqueda) ? trim($busqueda) : $busqueda;
        $empresaId = (int) $empresaId;

        $asientos = $this->model->select('asiento.id as id',
                                        'asiento.empresa_id as empresa',
                                        'empresa.nombre as nombreempresa',
                                        'asiento.numeroasiento as numeroasiento',
                                        'asiento.tipoasiento_id as tipoasiento_id',
                                        'tipoasiento.nombre as nombretipoasiento',
                                        'asiento.fecha as fecha',
                                        'asiento.observacion as observacion',
                                        'asiento.estado_aprobacion as estado_aprobacion')
                                ->join('tipoasiento', 'tipoasiento.id', '=', 'asiento.tipoasiento_id')
                                ->join('empresa', 'empresa.id', '=', 'asiento.empresa_id')
                                ->with('asiento_movimientos');

        if ($busqueda !== null && $busqueda !== '')
        {
            // Solo compara contra la columna DATE si el texto es una fecha válida
            // (soporta d/m/Y, d-m-Y y Y-m-d); si no, evita el error 1525 de MySQL.
            $fechaBuscada = $this->normalizarFecha($busqueda);

            $asientos->where(function ($query) use ($busqueda, $fechaBuscada)
            {
                $query->where('asiento.numeroasiento', $busqueda)
                      ->orWhere('empresa.nombre', 'like', '%'.$busqueda.'%')
                      ->orWhere('tipoasiento.nombre', 'like', '%'.$busqueda.'%');

                if ($fechaBuscada !== null)
                    $query->orWhere('asiento.fecha', $fechaBuscada);
            });
        }

        if ($empresaId > 0)
            $asientos->where('asiento.empresa_id', $empresaId);

        // Restringe a las empresas asignadas al operador (acceso total si no tiene asignaciones).
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($asientos, 'asiento.empresa_id');

        $asientos->orderby('asiento.id', 'DESC');

        if (isset($flPaginando))
        {
            if ($flPaginando)
                $asientos = $asientos->paginate(10);
            else
                $asientos = $asientos->get();
        }
        else
            $asientos = $asientos->get();

        return $asientos;
    }

    /**
     * Devuelve la fecha en formato Y-m-d si el texto es una fecha válida; null si no lo es.
     */
    private function normalizarFecha($texto): ?string
    {
        $texto = trim((string) $texto);

        if ($texto === '')
            return null;

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d'] as $formato)
        {
            $fecha = \DateTime::createFromFormat($formato, $texto);

            if ($fecha !== false && $fecha->format($formato) === $texto)
                return $fecha->format('Y-m-d');
        }

        return null;
    }

}

