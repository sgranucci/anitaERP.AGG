<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;

class Material extends Model
{
    protected $fillable = ['nombre', 'codigo', 'formula'];
    protected $table = 'material';
    protected $tableAnita = 'marmae';
    protected $keyField = 'marm_marca';

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 'tabla' => $this->tableAnita );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Material::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }
        
        foreach ($dataAnita as $value) {
            if (!in_array($value->{$this->keyField}, $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyField});
            }
        }
    }

    public function traerRegistroDeAnita($key){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                marm_marca,
				marm_desc,
                marm_formula
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];
			$codigo = ltrim($data->marm_marca, '0');

            Material::create([
				"nombre" => $data->marm_desc,
				"codigo" => $codigo,
				"formula" => $data->marm_formula
            ]);
        }
    }

	public function guardarAnita($request) {
		$codigo = str_pad($request->codigo, 8, "0", STR_PAD_LEFT);

        $apiAnita = new ApiAnita();
        $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
            'campos' => ' marm_marca, marm_desc, marm_formula ',
            'valores' => " '".$codigo."', '".$request->nombre."', '".$request->formula."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request) {
		$codigo = str_pad($request->codigo, 8, "0", STR_PAD_LEFT);

        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
				'valores' => " marm_desc = '".$request->nombre."' , marm_marca = '".$codigo."' , marm_formula = '".$request->formula."'",
				'whereArmado' => " WHERE marm_marca = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($codigo) {
		$codigo = str_pad($codigo, 8, "0", STR_PAD_LEFT);

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
			'whereArmado' => " WHERE marm_marca = '".$codigo."' " );
        $apiAnita->apiCall($data);
	}
}
