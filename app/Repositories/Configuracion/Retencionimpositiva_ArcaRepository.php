<?php

namespace App\Repositories\Configuracion;

use App\Models\Configuracion\Retencionimpositiva_Arca;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Auth;
use Carbon\Carbon;
use App\ApiAnita;

class Retencionimpositiva_ArcaRepository implements Retencionimpositiva_ArcaRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Retencionimpositiva_Arca $retencionimpositiva_arca)
    {
        $this->model = $retencionimpositiva_arca;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function create(array $data)
    {
        $retencionimpositiva_arca = $this->model->create($data);

        return($retencionimpositiva_arca);
    }

    public function update(array $data, $id)
    {
        $retencionimpositiva_arca = $this->model->findOrFail($id)->update($data);

		return $retencionimpositiva_arca;
    }

    public function delete($id)
    {
    	$retencionimpositiva_arca = $this->model->find($id);

        $retencionimpositiva_arca = $this->model->destroy($id);

		return $retencionimpositiva_arca;
    }

    public function find($id)
    {
        if (null == $retencionimpositiva_arca = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionimpositiva_arca;
    }

    public function findOrFail($id)
    {
        if (null == $retencionimpositiva_arca = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $retencionimpositiva_arca;
    }

    public function buscaRetencionimpositiva_Arca($cuit, $fechainicio)
    {
        $retencionimpositiva_arca = $this->model->where('cuit', $cuit)
                                    ->where('fechainicio', $fechainicio)->get();
    }

	public function leeRetencionimpositiva_Arca($busqueda, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $retencionimpositiva_arca = $this->model->select('retencionimpositiva_arca.id as id',
                                        'retencionimpositiva_arca.empresa_id as empresa_id',
                                        'empresa.nombre as nombreempresa',
                                        'retencionimpositiva_arca.nombre as nombre',
										'retencionimpositiva_arca.cuit as cuit',
                                        'retencionimpositiva_arca.descripcionimpuesto as descripcionimpuesto',
										'retencionimpositiva_arca.fecharetencion as fecharetencion',
                                        'retencionimpositiva_arca.numerocertificado as numerocertificado',
                                        'retencionimpositiva_arca.montoretencion as montoretencion',
                                        'retencionimpositiva_arca.numerocomprobante as numerocomprobante',
                                        'retencionimpositiva_arca.fechacomprobante as fechacomprobante',
                                        'retencionimpositiva_arca.descripcioncomprobante as descripcioncomprobante',
                                        'retencionimpositiva_arca.fecharegistracion as fecharegistracion')
                                ->join('empresa', 'empresa.id', '=', 'retencionimpositiva_arca.empresa_id')
                                ->where('retencionimpositiva_arca.id', $busqueda)
                                ->orWhere('retencionimpositiva_arca.nombre', 'like', '%'.$busqueda.'%')
                                ->orWhere('retencionimpositiva_arca.cuit', 'like', '%'.$busqueda.'%')
								->orWhere('retencionimpositiva_arca.descripcionimpuesto', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.fecharetencion', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.numerocertificado', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.montoretencion', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.numerocomprobante', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.fechacomprobante', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.descripcioncomprobante', 'like', '%'.$busqueda,'%')
                                ->orWhere('retencionimpositiva_arca.fecharegistracion', 'like', '%'.$busqueda,'%')
                                ->orderby('id', 'DESC');
                                
        if (isset($flPaginando))
        {
            if ($flPaginando)
                $retencionimpositiva_arca = $retencionimpositiva_arca->paginate(10);
            else
                $retencionimpositiva_arca = $retencionimpositiva_arca->get();
        }
        else
            $retencionimpositiva_arca = $retencionimpositiva_arca->get();

        return $retencionimpositiva_arca;
    }

    // Lee lista de impuestos
    public function leeImpuesto()
    {
        return $this->model->select('impuesto', 'descripcionimpuesto')->distinct()->get();
    }

    // Lee lista de regimenes
    public function leeRegimen()
    {
        return $this->model->select('regimen', 'descripcionregimen')->distinct()->get();
    }    

	public function leeRetencionPorEmpresaFecha($empresa_id, $desdefecha, $hastafecha, $impuesto, $regimen)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $startDate = Carbon::parse($desdefecha)->format('Y-m-d');
        $endDate = Carbon::parse($hastafecha)->format('Y-m-d');

        $retencionimpositiva_arca = $this->model->select('retencionimpositiva_arca.id as id',
                                        'retencionimpositiva_arca.empresa_id as empresa_id',
                                        'empresa.nombre as nombreempresa',
                                        'retencionimpositiva_arca.nombre as nombre',
										'retencionimpositiva_arca.cuit as cuit',
                                        'retencionimpositiva_arca.descripcionimpuesto as descripcionimpuesto',
										'retencionimpositiva_arca.fecharetencion as fecharetencion',
                                        'retencionimpositiva_arca.numerocertificado as numerocertificado',
                                        'retencionimpositiva_arca.montoretencion as montoretencion',
                                        'retencionimpositiva_arca.numerocomprobante as numerocomprobante',
                                        'retencionimpositiva_arca.fechacomprobante as fechacomprobante',
                                        'retencionimpositiva_arca.descripcioncomprobante as descripcioncomprobante',
                                        'retencionimpositiva_arca.fecharegistracion as fecharegistracion')
                                ->join('empresa', 'empresa.id', '=', 'retencionimpositiva_arca.empresa_id')
                                ->where('retencionimpositiva_arca.empresa_id', $empresa_id)
                                ->where('retencionimpositiva_arca.fecharetencion', '>=', $desdefecha)
                                ->where('retencionimpositiva_arca.fecharetencion', '<=', $hastafecha)
                                ->where('retencionimpositiva_arca.impuesto', $impuesto);

        if ($regimen != 'TODOS')
            $retencionimpositiva_arca->where('retencionimpositiva_arca.regimen', $regimen);
                                
        $retencionimpositiva_arca = $retencionimpositiva_arca->orderby('cuit', 'DESC')->get();

        return $retencionimpositiva_arca;
    }

    public function leeRetencionSistemaAnita($empresa_id, $desdefecha, $hastafecha, $impuesto, $regimen)
    {
        $desdefecha = str_replace("-", "", $desdefecha); 
        $hastafecha = str_replace("-", "", $hastafecha); 

        if ($impuesto == '217')
            $tipo = 'RGC';

        $apiAnita = new ApiAnita();

        $data = array( 
            'acc' => 'list', 'tabla' => 'auxpag, climae',
            'sistema' => 'che_ban',
            'campos' => '
                axp_pro as codigocliente,
                clim_nombre as nombrecliente,
                clim_cuit as cuit,
                sum(axp_monto_ap) as totalretencion
            ' , 
            'whereArmado' => " WHERE axp_tipo_ap='".$tipo."' and axp_fecha >= ".$desdefecha." AND axp_fecha <= ".$hastafecha."
                                AND clim_cliente=axp_pro",
            'groupBy' => 'axp_pro, clim_nombre, clim_cuit',
            'orderBy' => 'clim_cuit'
        );            
        $dataAnita = json_decode($apiAnita->apiCall($data));
        return($dataAnita);
    }
}
