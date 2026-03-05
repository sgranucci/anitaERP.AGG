<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use App\Models\Configuracion\Moneda;
use Carbon\Carbon;
use Auth;

class Precio extends Model
{
    protected $fillable = ['articulo_id', 'listaprecio_id', 'fechavigencia', 'moneda_id', 'precio', 'precioanterior', 'usuarioultcambio_id'];
    protected $table = 'precio';
    protected $tableAnita = 'stkpre';
    protected $keyField = 'sku';
    protected $keyFieldAnita = ['stkp_articulo','stkp_lista'];

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuarioultcambio_id');
    }

    public function listaprecios()
    {
        return $this->belongsTo(Listaprecio::class, 'listaprecio_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function sincronizarConAnita(){
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
                stkp_articulo,
                stkp_lista,
				stkp_precio,
				stkp_precio_ant,
				stkp_cod_mon,
				stkp_fe_ult_act,
				stkp_usuario,
				stkp_terminal
            ' , 
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        for ($ii = 0; $ii < count($dataAnita); $ii++)
		{
			$sku = ltrim($dataAnita[$ii]->stkp_articulo, '0');
			$articulo = Articulo::where('sku', $sku)->first();

			$listaprecio = Listaprecio::where('codigo', $dataAnita[$ii]->stkp_lista)->first();

			if ($dataAnita[$ii]->stkp_fe_ult_act < 19000000)
				$dataAnita[$ii]->stkp_fe_ult_act = 20100101;
			$fechavigencia = date('Y-m-d', strtotime($dataAnita[$ii]->stkp_fe_ult_act));

			if ($articulo && $listaprecio)
            	Precio::create([
					"articulo_id" => $articulo->id,
					"listaprecio_id" => $listaprecio->id,
					"fechavigencia" => $fechavigencia,
					"moneda_id" => $dataAnita[$ii]->stkp_cod_mon,
					"precio" => $dataAnita[$ii]->stkp_precio,
					"precioanterior" => $dataAnita[$ii]->stkp_precio_ant,
					"usuarioultcambio_id" => $usuario_id
            	]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

		$fecha_vigencia = $request->fechavigencia;
		$fecha_vigencia = str_replace("-", "", $fecha_vigencia);

		$codigo = str_pad($request->articulos->sku, 13, "0", STR_PAD_LEFT);
		$usuario = Auth::user()->nombre;

        $data = array( 'tabla' => $this->tableAnita, 
			'acc' => 'insert',
            'campos' => ' stkp_articulo, stkp_lista, stkp_precio, stkp_precio_ant, stkp_cod_mon, stkp_fe_ult_act, stkp_usuario, stkp_terminal',
            'valores' => " '".$codigo."', '".$request->listaprecios->codigo."', '".$request->precio."', '".$request->precioanterior."', '".$request->moneda_id."', '".$fecha_vigencia."', '".$usuario."', 'www'"
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request) {
        $apiAnita = new ApiAnita();

		$fecha_vigencia = Carbon::now();
		$fecha_vigencia = $fecha_vigencia->format('Ymd');

		$codigo = str_pad($request->articulos->sku, 13, "0", STR_PAD_LEFT);
		$usuario = Auth::user()->nombre;

		$data = array( 'acc' => 'update', 
				'tabla' => $this->tableAnita,
            	'valores' => " stkp_articulo = '".$codigo."', stkp_lista = '".$request->listaprecios->codigo."', stkp_precio = '".$request->precio."', stkp_precio_ant = '".$request->precioanterior."', stkp_cod_mon = '".$request->moneda_id."', stkp_fe_ult_act = '".$fecha_vigencia."', stkp_usuario = '".$usuario."', stkp_terminal = ' '",
            	'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$codigo."' AND ".$this->keyFieldAnita[1]." = '".$request->listaprecios->codigo."' " );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($codigo, $lista) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
            			'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$codigo."' AND ".$this->keyFieldAnita[1]." = '".$lista."' " );
        $apiAnita->apiCall($data);
	}
}
