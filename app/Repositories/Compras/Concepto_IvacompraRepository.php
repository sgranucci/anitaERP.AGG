<?php

namespace App\Repositories\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Concepto_Ivacompra_Condicioniva;
use App\Repositories\Compras\Columna_IvacompraRepositoryInterface;
use App\Repositories\Configuracion\ProvinciaRepositoryInterface;
use App\Repositories\Configuracion\ImpuestoRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Auth;

class Concepto_IvacompraRepository implements Concepto_IvacompraRepositoryInterface
{
    protected $model, $model_condicioniva;
    private $columna_ivacompraRepository;
    private $provinciaRepository;
    private $impuestoRepository;
    private $cuentacontableRepository;
    protected $tableAnita = 'conccomp';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'concc_concepto';

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Concepto_Ivacompra $concepto_ivacompra,
                                Concepto_Ivacompra_Condicioniva $concepto_ivacompra_condicioniva,
                                Columna_IvacompraRepositoryInterface $columna_ivacomprarepository,
                                ProvinciaRepositoryInterface $provinciarepository,
                                ImpuestoRepositoryInterface $impuestorepository,
                                CuentacontableRepositoryInterface $cuentacontablerepository)
    {
        $this->model = $concepto_ivacompra;
        $this->model_condicioniva = $concepto_ivacompra_condicioniva;
        $this->columna_ivacompraRepository = $columna_ivacomprarepository;
        $this->provinciaRepository = $provinciarepository;
        $this->impuestoRepository = $impuestorepository;
        $this->cuentacontableRepository = $cuentacontablerepository;
    }

    public function all()
    {
        $hay_concepto_ivacompra = Concepto_Ivacompra::first();

		if (!$hay_concepto_ivacompra)
			Self::sincronizarConAnita();

        return $this->model->with('concepto_ivacompra_condicionivas')->with('columna_ivacompras')->with('empresas')->with('cuentacontablesdebe')
                        ->with('cuentacontableshaber')->with('provincias')->with('impuestos')
                        ->orderBy('nombre','ASC')->get();
    }

    public function create(array $data)
    {
        $concepto_ivacompra = $this->model->create($data);
		//
		// Graba anita
		Self::guardarAnita($data);
    }

    public function update(array $data, $id)
    {
        $concepto_ivacompra = $this->model->findOrFail($id)
            ->update($data);
		//
		// Actualiza anita

		Self::actualizarAnita($data, $data['codigo']);

        return $concepto_ivacompra;
    }

    public function delete($id)
    {
    	$concepto_ivacompra = Concepto_Ivacompra::find($id);
		//
		// Elimina anita
		Self::eliminarAnita($concepto_ivacompra->codigo);

        $concepto_ivacompra = $this->model->destroy($id);

		return $concepto_ivacompra;
    }

    public function find($id)
    {
        if (null == $concepto_ivacompra = $this->model->with('concepto_ivacompra_condicionivas')->with('columna_ivacompras')->with('empresas')
                                            ->with('cuentacontablesdebe')->with('cuentacontableshaber')
                                            ->with('provincias')->with('impuestos')->find($id)) 
        {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_ivacompra;
    }

    public function findOrFail($id)
    {
        if (null == $concepto_ivacompra = $this->model->with('concepto_ivacompra_condicionivas')->with('columna_ivacompras')->with('empresas')
                                            ->with('cuentacontablesdebe')->with('cuentacontableshaber')
                                            ->with('provincias')->with('impuestos')->findOrFail($id)) 
        {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $concepto_ivacompra;
    }

    public function findPorCodigo($codigo)
    {
        return $this->model->with('concepto_ivacompra_condicionivas')->with('columna_ivacompras')
                           ->with('empresas')
                           ->with('cuentacontablesdebe')->with('cuentacontableshaber')
                           ->with('provincias')->with('impuestos')->where('codigo', $codigo)->first(); 
    }

    public function sincronizarConAnita(){
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
                        'sistema' => 'compras',
						'campos' => "
                        			concc_concepto as codigo,
    		                        concc_concepto",
						'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Concepto_Ivacompra::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
            if (!in_array(ltrim($value->{$this->keyField}, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'sistema' => 'compras',
            'campos' => '
                concc_concepto,
                concc_desc,
                concc_formula,
                concc_columna_sub,
                concc_contenido,
                concc_cta_debe,
                concc_cta_haber,
                concc_ctapte_debe,
                concc_ctapte_haber,
                concc_tipo_conc,
                concc_alicuota_iva,
                concc_retiene_ibr
            ',
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

        	$datamov = array( 
            	'acc' => 'list', 
				'sistema' => 'compras',
				'tabla' => 'concciva', 
            	'campos' => '
                	conci_concepto,
					conci_cond_iva
            	' , 
            	'whereArmado' => " WHERE conci_concepto = '".$key."' " 
        	);
        	$dataAnitamov = json_decode($apiAnita->apiCall($datamov));

            // Busca columna de subdiario
            $columna_ivacompra = $this->columna_ivacompraRepository->findPorNumeroColumna($data->concc_columna_sub);
            $columna_ivacompra_id = null;
            if ($columna_ivacompra)
                $columna_ivacompra_id = $columna_ivacompra->id;

            $retieneGanancia = 'N';
            switch($data->concc_contenido)
            {
            case 'C':
                $retieneGanancia = 'S';
                break;
            case 'D':
            case 'I':
            case 'O':
            case 'E':
                $retieneGanancia = 'N';
                break;
            }

            // Si es ingresos brutos busca la jurisdiccion
            $provincia_id = null;
            if ($data->concc_tipo_conc == 'B' || $data->concc_tipo_conc == 'S' || $data->concc_tipo_conc == 'A')
            {
                $provincia = $this->provinciaRepository->findPorJurisdiccion($data->concc_alicuota_iva);
                if ($provincia)
                    $provincia_id = $provincia->id;
            }

            // Si es alicuota busca id de impuesto
            $impuesto_id = null;
            if ($data->concc_tipo_conc == 'G' || $data->concc_tipo_conc == 'P' || $data->concc_tipo_conc == 'I')
            {
                $impuesto = $this->impuestoRepository->findPorValor($data->concc_alicuota_iva);
                if ($impuesto)
                    $impuesto_id = $impuesto->id;
            }

            // Lee cuenta contable al debe
            $cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->concc_cta_debe);
			if ($cuenta)
				$cuentacontabledebe_id = $cuenta->id;
			else
				$cuentacontabledebe_id = NULL;

            // Lee cuenta contable al haber
            $cuenta = $this->cuentacontableRepository->findPorCodigo(1, $data->concc_cta_haber);
            if ($cuenta)
                $cuentacontablehaber_id = $cuenta->id;
            else
                $cuentacontablehaber_id = NULL;
            
			$arr_campos = [
                'nombre' => $data->concc_desc, 
                'codigo' => $data->concc_concepto, 
                'formula' => $data->concc_formula, 
                'columna_ivacompra_id' => $columna_ivacompra_id, 
                'empresa_id' => null, 
                'cuentacontabledebe_id' => $cuentacontabledebe_id, 
                'cuentacontablehaber_id' => $cuentacontablehaber_id, 
                'tipoconcepto' => $data->concc_tipo_conc, 
                'retieneganancia' => $retieneGanancia, 
                'retieneIIBB' => $data->concc_retiene_ibr, 
                'provincia_id' => $provincia_id, 
                'impuesto_id' => $impuesto_id
            ];
        	$concepto_ivacompra = $this->model->create($arr_campos);

            if ($concepto_ivacompra)
			{
				for ($i = 0; $i < count($dataAnitamov); $i++)
				{
        			$concepto_ivacomp_condicioniva = $this->model_condicioniva->create([
            											'concepto_ivacompra_id' => $concepto_ivacompra->id,
            											'condicioniva_id' => $dataAnitamov[$i]->conci_cond_iva
														]);
				}
			}
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        Self::armaVariablesParaGrabar($request, $columnaSubdiario, $contenido, $cuentaDebe,
                                            $cuentaHaber, $alicuotaIva);

        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'sistema' => 'compras',
            'campos' => ' 
                concc_concepto,
                concc_desc,
                concc_formula,
                concc_columna_sub,
                concc_contenido,
                concc_cta_debe,
                concc_cta_haber,
                concc_ctapte_debe,
                concc_ctapte_haber,
                concc_tipo_conc,
                concc_alicuota_iva,
                concc_retiene_ibr
				',
            'valores' => " 
				'".$request['codigo']."', 
                '".$request['nombre']."', 
                '".$request['formula']."', 
                '".$columnaSubdiario."', 
                '".$contenido."', 
                '".$cuentaDebe."', 
                '".$cuentaHaber."', 
                '".'0'."', 
                '".'0'."', 
                '".$request['tipoconcepto']."',
                '".$alicuotaIva."',
                '".$request['retieneIIBB']."' "
        );
        $apiAnita->apiCall($data);

        if (isset($request['condicioniva_ids']))
        {
            $condicioniva_ids = $request['condicioniva_ids'];

            for ($i_rango=0; $i_rango < count($condicioniva_ids); $i_rango++) 
            {
                if ($condicioniva_ids[$i_rango] > 0)
                {
                    $apiAnita = new ApiAnita();

                    $data = array( 'tabla' => 'concciva', 
                        'acc' => 'insert',
                        'sistema' => 'compras',
                        'campos' => '
                                conci_concepto,
                                conci_cond_iva
                                ',
                        'valores' => " 
                                '".$request['codigo']."', 
                                '".$condicioniva_ids[$i_rango]."' "
                        );
                        
                    $apiAnita->apiCall($data);
                }
            }
        }
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        Self::armaVariablesParaGrabar($request, $columnaSubdiario, $contenido, $cuentaDebe,
                                            $cuentaHaber, $alicuotaIva);

		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'valores' => " 
                concc_concepto 	        = '".$request['codigo']."',
                concc_desc              = '".$request['nombre']."',
                concc_formula           = '".$request['formula']."',
                concc_columna_sub       = '".$columnaSubdiario."',
                concc_contenido         = '".$contenido."',
                concc_cta_debe          = '".$cuentaDebe."',
                concc_cta_haber         = '".$cuentaHaber."',
                concc_tipo_conc         = '".$request['tipoconcepto']."',
                concc_alicuota_iva      = '".$alicuotaIva."',
                concc_retiene_ibr       = '".$request['retieneIIBB']." ' ",
				'whereArmado' => " WHERE concc_concepto = '".$id."' " );
        $apiAnita->apiCall($data);

        // Elimina los movimientos
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
            'tabla' => 'concciva',
            'sistema' => 'compras',
            'whereArmado' => " WHERE conci_concepto = '".$id."' " );
        $apiAnita->apiCall($data);

        if (isset($request['condicioniva_ids']))
        {
            $condicioniva_ids = $request['condicioniva_ids'];

            // Graba los movimientos
            for ($i_rango=0; $i_rango < count($condicioniva_ids); $i_rango++) 
            {
                if ($condicioniva_ids[$i_rango] > 0)
                {
                    $apiAnita = new ApiAnita();

                    $data = array( 'tabla' => 'concciva', 
                        'acc' => 'insert',
                        'sistema' => 'compras',
                        'campos' => '
                                conci_concepto,
                                conci_cond_iva
                                ',
                        'valores' => " 
                                '".$id."', 
                                '".$condicioniva_ids[$i_rango]."' "
                        );

                    $apiAnita->apiCall($data);
                }
            }
        }
	}

    private function armaVariablesParaGrabar($request, &$columnaSubdiario, &$contenido, &$cuentaDebe,
                                            &$cuentaHaber, &$alicuotaIva)
    {
        $columna_ivacompra = $this->columna_ivacompraRepository->find($request['columna_ivacompra_id']);
        $columnaSubdiario = 1;
        if ($columna_ivacompra)
            $columnaSubdiario = $columna_ivacompra->numerocolumna;

        $contenido = 'C';
        switch($request['tipoconcepto'])
        {
        case 'N':
        case 'G':
        case 'E':
            if ($request['retieneganancia'] == 'S')
                $contenido = 'C';
            else    
                $contenido = 'D';
            break;
        case 'I':
            $contenido = 'I';
            break;
        case 'P':
        case 'B':
        case 'M':
        case 'T':
        case 'S':
        case 'A':
            $contenido = 'O';
            break;
        }

        // Lee cuenta contable
        $cuenta = $this->cuentacontableRepository->findPorId($request['cuentacontabledebe_id']);
        if ($cuenta)
            $cuentaDebe = $cuenta->codigo;
        else
            $cuentaDebe = 0;

        // Lee cuenta contable
        $cuenta = $this->cuentacontableRepository->findPorId($request['cuentacontablehaber_id']);
        if ($cuenta)
            $cuentaHaber = $cuenta->codigo;
        else
            $cuentaHaber = 0;

        $alicuotaIva = null;
        if ($request['tipoconcepto'] == 'B' || $request['tipoconcepto'] == 'S' || $request['tipoconcepto'] == 'A')            
        {
            $provincia = $this->provinciaRepository->findPorId($request['provincia_id']);
            if ($provincia)
                $alicuotaIva = $provincia->jurisdiccion;
        }
        else
        {
            $impuesto = $this->impuestoRepository->find($request['impuesto_id']);
            $alicuotaIva = 0;
            if ($impuesto)
                $alicuotaIva = $impuesto->valor;
        }
    }

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                'sistema' => 'compras',
				'whereArmado' => " WHERE concc_concepto = '".$id."' " );
        $apiAnita->apiCall($data);

        // Elimina los movimientos
        $apiAnita = new ApiAnita();

        $data = array( 'acc' => 'delete', 
            'tabla' => 'concciva',
            'sistema' => 'compras',
            'whereArmado' => " WHERE conci_concepto = '".$id."' " );
        $apiAnita->apiCall($data);
	}
	
}
