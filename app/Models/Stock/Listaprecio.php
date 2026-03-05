<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Tiponumeracion;
use Carbon\Carbon;
use Auth;

class Listaprecio extends Model
{
    protected $fillable = ['nombre', 'formula', 'incluyeimpuesto', 'codigo', 'desdetalle', 'hastatalle', 
                            'tiponumeracion_id', 'usuarioultcambio_id'];
    protected $table = 'listaprecio';
    protected $tableAnita = 'premae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = 'prem_lista';

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuarioultcambio_id');
    }

    public function tiposnumeracion()
    {
        return $this->belongsTo(Tiponumeracion::class, 'tiponumeracion_id');
    }

	public function lineas()
    {
        return $this->hasMany(Linea::class);
    }

	public function graba_id_incl_impuesto($Pincl_impuesto)
	{
		return($Pincl_impuesto == 'S' ? '1' : '2');
	}

	public function graba_incl_impuesto($Pincl_impuesto)
	{
		return($Pincl_impuesto == '1' ? 'S' : 'N');
	}

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyFieldAnita, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Listaprecio::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyFieldAnita}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                prem_lista,
                prem_desc,
				prem_incl_impuesto,
				prem_formula,
				prem_usuario,
				prem_fe_ult_act
            ' , 
            'whereArmado' => " WHERE ".$this->keyFieldAnita." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
			$usuario_id = Auth::user()->id;

            Listaprecio::create([
				"nombre" => $data->prem_desc,
				"formula" => $data->prem_formula,
				"incluyeimpuesto" => $this->graba_id_incl_impuesto($data->prem_incl_impuesto),
				"codigo" => $data->prem_lista,
                "tiponumeracion_id" => null,
				"usuarioultcambio_id" => $usuario_id
            ]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$fecha_vigencia = Carbon::now();
		$fecha_vigencia = $fecha_vigencia->format('Ymd');
		$usuario = Auth::user()->nombre;

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'campos' => ' prem_lista, prem_desc, prem_incl_impuesto, prem_vigencia, prem_vigencia_ant,
				prem_formula, prem_usuario, prem_fe_ult_act ',
            'valores' => " '".$request->codigo."', '".$request->nombre."', '".$this->graba_incl_impuesto($request->incluyeimpuesto)."', '".$fecha_vigencia."', '0', '".$request->formula."', '".$usuario."', '".$fecha_vigencia."'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();

		$fecha_vigencia = Carbon::now();
		$fecha_vigencia = $fecha_vigencia->format('Ymd');
		$usuario = Auth::user()->nombre;

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
				'valores' => " prem_desc = '".$request->nombre."' , prem_incl_impuesto = '".$this->graba_incl_impuesto($request->incluyeimpuesto)."' , prem_vigencia = '".$fecha_vigencia."' , prem_formula = '".$request->formula."' , prem_usuario = '".$usuario."' , prem_fe_ult_act = '".$fecha_vigencia."'",
				'whereArmado' => " WHERE prem_lista = '".$request->codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE prem_lista = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
