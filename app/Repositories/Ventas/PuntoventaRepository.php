<?php

namespace App\Repositories\Ventas;

use App\ApiAnita;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Provincia;
use App\Models\Ventas\Puntoventa;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PuntoventaRepository implements PuntoventaRepositoryInterface
{
    protected $model;

    protected $tableAnita = 'sucursal';

    /**
     * PostRepository constructor.
     *
     * @param  Post  $post
     */
    public function __construct(
        Puntoventa $puntoventa,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $puntoventa;
    }

    public function all($estado = null)
    {
        $query = $this->model->newQuery();

        if ($estado != null) {
            $query->where('estado', $estado);
        }

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function create(array $data, ?bool $syncAnita = null)
    {
        $puntoventa = $this->model->create($data);

        if ($syncAnita ?? config('app.anita_sync_puntoventa_write')) {
            $this->guardarAnita($this->prepararDatosAnita($data));
        }

        return $puntoventa;
    }

    public function update(array $data, $id, ?bool $syncAnita = null)
    {
        $puntoventa = $this->model->findOrFail($id);
        $codigoAnterior = (string) $puntoventa->codigo;

        $puntoventa->update($data);

        if ($syncAnita ?? config('app.anita_sync_puntoventa_write')) {
            $this->actualizarAnita(
                $this->prepararDatosAnita($data, $puntoventa->fresh()),
                $codigoAnterior
            );
        }

        return $puntoventa;
    }

    public function delete($id, ?bool $syncAnita = null)
    {
        $puntoventa = $this->model->find($id);

        if ($puntoventa) {
            if ($syncAnita ?? config('app.anita_sync_puntoventa_write')) {
                $this->eliminarAnita($puntoventa->codigo);
            }

            $puntoventa = $this->model->destroy($id);
        }

        return $puntoventa;
    }

    public function find($id)
    {
        if (null == $puntoventa = $this->model->with('empresas')->with('actividad_arcas')->find($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $puntoventa;
    }

    public function findOrFail($id)
    {
        if (null == $puntoventa = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException('Registro no encontrado');
        }

        return $puntoventa;
    }

    public function guardarAnita($request)
    {
        $apiAnita = new ApiAnita;

        $this->setCondicionIvaAnita($request, $condicioniva_id);

        $data = ['tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' 
				expr_codigo,
    			expr_nombre,
    			expr_direccion,
    			expr_localidad,
    			expr_provincia,
    			expr_cod_postal,
    			expr_telefono,
    			expr_cuit,
    			expr_cond_iva,
    			expr_nro_interno,
				expr_pat_vehiculo,
				expr_pag_acoplado,
				expr_hs_entrega
				',
            'valores' => " 
				'".$request['codigo']."', 
				'".$request['nombre']."',
				'".$request['domicilio']."',
				'".$request['desc_localidad']."',
				'".$request['desc_provincia']."',
				'".$request['codigopostal']."',
				'".$request['telefono']."',
				'".$request['nroinscripcion']."',
				'".$condicioniva_id."',
				'0',
				'".$request['patentevehiculo']."',
				'".$request['patenteacoplado']."',
				'".$request['horarioentrega']."' ",
        ];
        $apiAnita->apiCallEscritura($data);
    }

    public function actualizarAnita($request, $id)
    {
        $apiAnita = new ApiAnita;

        $this->setCondicionIvaAnita($request, $condicioniva);

        $data = ['acc' => 'update', 'tabla' => $this->tableAnita,
            'valores' => " 
                expr_codigo 	                = '".$request['codigo']."',
                expr_nombre 	                = '".$request['nombre']."',
                expr_direccion 	                = '".$request['domicilio']."',
                expr_localidad 	                = '".$request['desc_localidad']."',
                expr_provincia 	                = '".$request['desc_provincia']."',
                expr_cod_postal 	            = '".$request['codigopostal']."',
                expr_telefono 	                = '".$request['telefono']."',
                expr_cuit 	                    = '".$request['nroinscripcion']."',
                expr_cond_iva 	                = '".$condicioniva."',
                expr_pat_vehiculo 	            = '".$request['patentevehiculo']."',
                expr_pag_acoplado 	            = '".$request['patenteacoplado']."',
                expr_hs_entrega	                = '".$request['horarioentrega']."' ",
            'whereArmado' => " WHERE expr_codigo = '".$id."' "];
        $apiAnita->apiCallEscritura($data);
    }

    public function eliminarAnita($id)
    {
        $apiAnita = new ApiAnita;
        $data = ['acc' => 'delete', 'tabla' => $this->tableAnita,
            'whereArmado' => " WHERE expr_codigo = '".$id."' "];
        $apiAnita->apiCallEscritura($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function prepararDatosAnita(array $data, ?Puntoventa $model = null): array
    {
        $prepared = $data;

        if (empty($prepared['desc_localidad']) && ! empty($prepared['localidad_id'])) {
            $prepared['desc_localidad'] = Localidad::query()
                ->whereKey((int) $prepared['localidad_id'])
                ->value('nombre') ?? '';
        }

        if (empty($prepared['desc_provincia']) && ! empty($prepared['provincia_id'])) {
            $prepared['desc_provincia'] = Provincia::query()
                ->whereKey((int) $prepared['provincia_id'])
                ->value('nombre') ?? '';
        }

        if ($model !== null) {
            $prepared['desc_localidad'] = $prepared['desc_localidad'] ?? $model->localidades?->nombre ?? '';
            $prepared['desc_provincia'] = $prepared['desc_provincia'] ?? $model->provincias?->nombre ?? '';
        }

        foreach (['domicilio', 'codigopostal', 'telefono', 'desc_localidad', 'desc_provincia', 'nroinscripcion', 'patentevehiculo', 'patenteacoplado', 'horarioentrega'] as $campo) {
            $prepared[$campo] = $prepared[$campo] ?? '';
        }

        $prepared['condicioniva_id'] = $prepared['condicioniva_id'] ?? '1';

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function setCondicionIvaAnita(array $data, &$condicioniva): void
    {
        $condicioniva = '0';

        switch ($data['condicioniva_id'] ?? null) {
            case '1':
                $condicioniva = '0';
                break;
            case '2':
                $condicioniva = '4';
                break;
            case '3':
                $condicioniva = '3';
                break;
            case '4':
                $condicioniva = '5';
                break;
        }
    }
}
