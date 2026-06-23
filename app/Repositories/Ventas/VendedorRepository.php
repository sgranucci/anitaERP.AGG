<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Vendedor;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Services\Ventas\VendedorAnitaSyncService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class VendedorRepository implements VendedorRepositoryInterface
{
    protected $model;
    protected $table = 'vendedor';
    protected $keyField = 'vend_codigo';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(
        Vendedor $vendedor,
        private EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->model = $vendedor;
    }

    public function all()
    {
        $hay_vendedor = Vendedor::first();

        if (!$hay_vendedor)
			Self::sincronizarConAnita();

        $query = $this->model->with('vendedorasociados')->orderBy('nombre', 'ASC');
        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    public function create(array $data, ?bool $syncAnita = null)
    {
        $vendedor = $this->model->create($data);

        if ($syncAnita ?? config('app.anita_sync_vendedor_write', true)) {
            self::guardarAnita($data, $data['codigo']);
        }

        return $vendedor;
    }

    public function update(array $data, $id, ?bool $syncAnita = null)
    {
        $vendedor = $this->model->findOrFail($id)
            ->update($data);

        if ($syncAnita ?? config('app.anita_sync_vendedor_write', true)) {
            self::actualizarAnita($data, $data['codigo']);
        }

        return $vendedor;
    }

    public function delete($id)
    {
    	$vendedor = $this->model->find($id);

		// Elimina anita
		self::eliminarAnita($vendedor->codigo);

        $vendedor = $this->model->destroy($id);

		return $vendedor;
    }

    public function find($id)
    {
        if (null == $vendedor = $this->model->with('vendedorasociados')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $vendedor;
    }

    public function findPorId($id)
    {
        $vendedor = $this->model->with('vendedorasociados')->where('id', $id)->first();

        return $vendedor;
    }

    public function findPorCodigo($codigo)
    {
        $codigo = trim((string) $codigo);
        if ($codigo === '') {
            return null;
        }

        $vendedor = $this->model->with('vendedorasociados')->where('codigo', $codigo)->first();
        if ($vendedor) {
            return $vendedor;
        }

        $alt = ltrim($codigo, '0');
        if ($alt !== '' && $alt !== $codigo) {
            return $this->model->with('vendedorasociados')->where('codigo', $alt)->first();
        }

        return null;
    }

    public function findOrFail($id)
    {
        if (null == $vendedor = $this->model->with('vendedorasociados')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $vendedor;
    }

	public function leeVendedor($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $vendedor = Vendedor::select('vendedor.id as id',
                        'vendedor.nombre as nombre',
                        'vendedor.codigo as codigo')
                ->where('vendedor.id', $busqueda)
                ->orWhere('vendedor.nombre', 'like', '%'.$busqueda.'%')
                ->orWhere('vendedor.codigo', 'like', '%'.$busqueda,'%')
                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $vendedor = $vendedor->paginate(10);
            else
                $vendedor = $vendedor->get();
        }
        else
            $vendedor = $vendedor->get();

        return $vendedor;
    }

    public function consultaVendedor($consulta)
    {
        ini_set('max_execution_time', '300');
	  	ini_set('memory_limit', '512M');

        $columns = ['vendedor.id', 'vendedor.nombre',  'vendedor.codigo'];
        $columnsOut = ['id', 'nombre', 'codigo'];

		$consulta = strtoupper($consulta);

		$count = count($columns);

        $data = $this->model->select('vendedor.id as id',
									'vendedor.nombre as nombre',
									'vendedor.codigo as codigo')
							->where(function ($query) use ($count, $consulta, $columns) {
                        			for ($i = 0; $i < $count; $i++)
                                    {
                           			    $query->orWhere($columns[$i], "LIKE", '%'. $consulta . '%');
                                    }
                })	
				->orderBy('nombre')
				->limit(200)
				->get();	

        $puedeAbrirAbm = can('editar-vendedores', false) || can('listar-vendedores', false);

        $output = [];
		$output['data'] = '';	
        $flSinDatos = true;
        $count = count($columns);
		if (count($data) > 0)
		{
			foreach ($data as $row)
			{
                $flSinDatos = false;
                $output['data'] .= '<tr>';
                for ($i = 0; $i < $count; $i++)
                    $output['data'] .= '<td class="'.$columnsOut[$i].'">' . e($row->{$columnsOut[$i]}) . '</td>';	
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultavendedor">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_vendedor', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
			}
		}

        if ($flSinDatos)
		{
			$output['data'] .= '<tr>';
			$output['data'] .= '<td colspan="4">Sin resultados</td>';
			$output['data'] .= '</tr>';
		}
		return(json_encode($output, JSON_UNESCAPED_UNICODE));
    }

    // Lee vendedores asociados del usuario

    public function leeVendedoresAsociados()
    {
		$permisos = traePermisosUsuario();
        $vendedores = [];
        
		if (in_array("listar-clientes-vendedor", $permisos['permisos'])) 
		{
			if (session('vendedor_id') > 0)
            {
                $vendedores[] = session('vendedor_id');

                // Agrega los vendedores asociados
                $vendedor = Self::find(session('vendedor_id'));
                foreach($vendedor->vendedorasociados as $asociado)
                    $vendedores[] = $asociado->vendedorasociado_id;
            }
		}					
        return $vendedores;
    }

    public function sincronizarConAnita()
    {
        app(VendedorAnitaSyncService::class)->sincronizarConAnita();
    }

    public function traerRegistroDeAnita($key)
    {
        return app(VendedorAnitaSyncService::class)->traerRegistroDeAnita((string) $key);
    }

	public function guardarAnita($data, $id) {
        $apiAnita = new ApiAnita();

        if ($data['aplicaSobre'] == "Sobre Neto")
            $aplicaSobre = 'N';
        else
            $aplicaSobre = 'B';

        if ($data['estado'] == "No Carga Clientes")
            $estado = 'N';
        else
            $estado = ' ';

        if (config('app.empresa') == 'EL BIERZO')
            $data = array( 'tabla' => 'vendedor', 'acc' => 'insert',
                'sistema' => 'ventas',
                'campos' => ' vend_codigo, vend_nombre, vend_comision_vta, vend_aplicacion, vend_empresa, vend_legajo, vend_comision_cob, vend_mercaderia, vend_email, vend_estado ',
                'valores' => " '".$id."', 
                            '".$data['nombre']."',
                            '".$data['comisionventa']."',
                            '".$aplicaSobre."',
                            '".$data['empresa_id']."',
                            '".$data['legajo_id']."',
                            '".$data['comisioncobranza']."',
                            '".$estado."',
                            '".$data['email']."',
                            '".' '."' "
            );
        else
            $data = array( 'tabla' => 'vendedor', 'acc' => 'insert',
                'sistema' => 'ventas',
                'campos' => ' vend_codigo, vend_nombre, vend_comision_vta, vend_aplicacion, vend_empresa, vend_legajo, vend_comision_cob, vend_mercaderia ',
                'valores' => " '".$id."', 
                            '".$data['nombre']."',
                            '".$data['comisionventa']."',
                            '".$aplicaSobre."',
                            '".$data['empresa_id']."',
                            '".$data['legajo_id']."',
                            '".$data['comisioncobranza']."',
                            '".' '."' "
            );
        return $apiAnita->apiCallEscritura($data);
	}

	public function actualizarAnita($data, $id) {
        $apiAnita = new ApiAnita();
        if ($data['aplicasobre'] == "Sobre Neto")
            $aplicaSobre = 'N';
        else
            $aplicaSobre = 'B';
        if ($data['estado'] == "Activo")
            $estado = ' ';
        else
            $estado = 'N';
        if (config('app.empresa') == 'EL BIERZO')
		    $data = array( 'acc' => 'update', 'tabla' => 'vendedor', 
                    'sistema' => 'ventas',
					'valores' => " 
								vend_nombre = '".  $data['nombre']."',
								vend_comision_vta = '".  $data['comisionventa']."', 
								vend_comision_cob = '".  $data['comisioncobranza']."',
                                vend_aplicacion = '". $aplicaSobre."',
                                vend_empresa = '".$data['empresa_id']."',
                                vend_legajo = '".$data['legajo_id']."',
                                vend_codigo = '".$data['codigo']."',
                                vend_estado = '".$estado."',
                                vend_email = '".$data['email']."' ", 
					'whereArmado' => " WHERE vend_codigo = '".$id."' " );
        else
		    $data = array( 'acc' => 'update', 'tabla' => 'vendedor', 
                    'sistema' => 'ventas',
					'valores' => " 
								vend_nombre = '".  $data['nombre']."',
								vend_comision_vta = '".  $data['comisionventa']."', 
								vend_comision_cob = '".  $data['comisioncobranza']."',
                                vend_aplicacion = '". $aplicaSobre."',
                                vend_empresa = '".$data['empresa_id']."',
                                vend_legajo = '".$data['legajo_id']."',
                                vend_codigo = '".$data['codigo']."' ",
					'whereArmado' => " WHERE vend_codigo = '".$id."' " );
        return $apiAnita->apiCallEscritura($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'vendedor', 
						'whereArmado' => " WHERE vend_codigo = '".$id."' " );
        $apiAnita->apiCallEscritura($data);
	}    
}
