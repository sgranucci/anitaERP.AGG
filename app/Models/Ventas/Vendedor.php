<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\Empresa;
use Illuminate\Support\Str;
use App\Traits\Ventas\VendedorTrait;
use App\ApiAnita;

class Vendedor extends Model
{
    use VendedorTrait;
    protected $fillable = ['nombre', 'comisionventa', 'comisioncobranza', 'aplicasobre', 'empresa_id', 'legajo_id', 'email',
                            'codigo', 'estado'];
    protected $table = 'vendedor';
    protected $keyField = 'vend_codigo';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function sincronizarConAnita(){
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 'campos' => $this->keyField, 
                        'sistema' => 'ventas',
						'tabla' => $this->table, 
						'orderBy' => 'vend_codigo' );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = Vendedor::all();
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
            'acc' => 'list', 'tabla' => $this->table, 
            'sistema' => 'ventas',
            'campos' => '
                vend_codigo,
				vend_nombre,
				vend_comision_vta,
				vend_comision_cob,
                vend_aplicacion,
                vend_empresa,
                vend_legajo,
                vend_email,
                vend_estado
            ' , 
            'whereArmado' => " WHERE ".$this->keyField." = '".$key."' " 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

            if ($data->vend_aplicacion == 'B')
                $aplicaSobre = "Sobre Bruto";
            else
                $aplicaSobre = "Sobre Neto";

            if ($data->vend_empresa == 0)
                $data->vend_empresa = null;

            if ($data->vend_estado == 'N')
                $estado = "No Carga Clientes";
            else
                $estado = "Activo";

            Vendedor::create([
                "id" => $key,
                "nombre" => $data->vend_nombre,
                "comisionventa" => $data->vend_comision_vta,
                "comisioncobranza" => $data->vend_comision_cob,
                "aplicasobre" => $aplicaSobre,
                "empresa_id" => $data->vend_empresa,
                "legajo_id" => $data->vend_legajo,
                "email" => $data->vend_email,
                "codigo" => $data->vend_codigo,
                "estado" => $estado
            ]);
        }
    }

	public function guardarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        if ($request->aplcaSobre == "Sobre Neto")
            $aplicaSobre = 'N';
        else
            $aplicaSobre = 'B';
        $data = array( 'tabla' => 'vendedor', 'acc' => 'insert',
            'sistema' => 'ventas',
            'campos' => ' vend_codigo, vend_nombre, vend_comision_vta, vend_aplicacion, vend_empresa, vend_legajo, vend_comision_cob, vend_mercaderia, vend_email, vend_estado ',
            'valores' => " '".$id."', 
						   '".$request->nombre."',
						   '".$request->comisionventa."',
						   '".$aplicaSobre."',
						   '".$request->empresa_id."',
						   '".$request->legajo_id."',
            			   '".$request->comisioncobranza."',
						   ' ',
                           '".$request->email."',
                           '".' '."' "
        );
        return $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();
        if ($request->aplicaSobre == "Sobre Neto")
            $aplicaSobre = 'N';
        else
            $aplicaSobre = 'B';
        if ($request->estado == "Activo")
            $estado = ' ';
        else
            $estado = 'N';
		$data = array( 'acc' => 'update', 'tabla' => 'vendedor', 
                    'sistema' => 'ventas',
					'valores' => " 
								vend_nombre = '".  $request->nombre."',
								vend_comision_vta = '".  $request->comisionventa."', 
								vend_comision_cob = '".  $request->comisioncobranza."',
                                vend_aplicacion = '". $aplicaSobre."',
                                vend_empresa = '".$request->empresa_id."',
                                vend_legajo = '".$request->legajo_id."',
                                vend_codigo = '".$request->codigo."',
                                vend_estado = '".$estado."',
                                vend_email = '".$request->email."' ", 
					'whereArmado' => " WHERE vend_codigo = '".$id."' " );
        return $apiAnita->apiCall($data);
	}

	public function eliminarAnita($id) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
                        'sistema' => 'ventas',
						'tabla' => 'vendedor', 
						'whereArmado' => " WHERE vend_codigo = '".$id."' " );
        $apiAnita->apiCall($data);
	}
}
