<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use Auth;

class Capeart extends Model
{
    protected $fillable = ['articulo_id', 'combinacion_id', 'material_id', 'color_id', 'piezas', 'tipo', 'tipocalculo', 'consumo1', 'consumo2', 'consumo3', 'consumo4', 'usuarioultcambio_id'];
    protected $table = 'capeart';
    protected $keyField = 'id';
    protected $keyFieldAnita = ['capea_articulo', 'capea_combinacion', 'capea_orden'];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function combinaciones()
    {
        return $this->belongsTo(Combinacion::class, 'combinacion_id');
    }

    public function materiales()
    {
        return $this->belongsTo(Materialcapellada::class, 'material_id');
    }

    public function colores()
    {
        return $this->belongsTo(Color::class, 'color_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuarioulcambio_id');
    }

	public function setConsumo1Attribute($value)
	{
		if ($value == null)
    		$this->attributes['consumo1'] = 0;
		else
    		$this->attributes['consumo1'] = $value;
	}

	public function setConsumo2Attribute($value)
	{
		if ($value == null)
    		$this->attributes['consumo2'] = 0;
		else
    		$this->attributes['consumo2'] = $value;
	}

	public function setConsumo3Attribute($value)
	{
		if ($value == null)
    		$this->attributes['consumo3'] = 0;
		else
    		$this->attributes['consumo3'] = $value;
	}

	public function setConsumo4Attribute($value)
	{
		if ($value == null)
    		$this->attributes['consumo4'] = 0;
		else
    		$this->attributes['consumo4'] = $value;
	}

    public function sincronizarConAnita($articulo = null, $combinacion = null){
        $apiAnita = new ApiAnita();

		if ($articulo != null)
        	$data = array( 'acc' => 'list', 
		  				'campos' => "capea_articulo, capea_combinacion, capea_orden", 
            			'whereArmado' => " WHERE exists (select 1 from combinacion where capea_articulo=comb_articulo and capea_combinacion=comb_combinacion) AND capea_articulo='".$articulo."' AND capea_combinacion='".$combinacion."'",
		  				'tabla' => $this->table);
		else
        	$data = array( 'acc' => 'list', 
		  				'campos' => "capea_articulo, capea_combinacion, capea_orden", 
            			'whereArmado' => " WHERE not exists (select 1 from tmp8 where capea_articulo=articulo and 
											capea_combinacion=combinacion) ",
		  				'tabla' => $this->table);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		/*for ($ii = 9954; $ii < count($dataAnita); $ii++)
		{
        	$this->traerRegistroDeAnita($dataAnita[$ii]->{$this->keyFieldAnita[0]}, $dataAnita[$ii]->{$this->keyFieldAnita[1]}, $dataAnita[$ii]->{$this->keyFieldAnita[2]});
		}*/

        foreach ($dataAnita as $value) {
        	$this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, $value->{$this->keyFieldAnita[2]});
        }
    }

    public function traerRegistroDeAnita($articulo, $combinacion, $orden){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->table, 
            'campos' => '
				capea_articulo,
				capea_orden,
				capea_material,
				capea_color,
				capea_piezas,
				capea_consumo1,
				capea_consumo2,
				capea_consumo3,
				capea_consumo4,
				capea_combinacion,
				capea_tipo,
				capea_tipo_calculo
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$articulo.
							"' AND ".$this->keyFieldAnita[1]." = '".$combinacion.
							"' AND ".$this->keyFieldAnita[2]." = '".$orden."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if ($dataAnita) {
            $data = $dataAnita[0];

        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->capea_articulo, '0'))->first();
			$articulo_id = $articulo->id;

			$combinacion_id = 0;
			if ($articulo)
			{
				// Leo la combinacion para sacar el id
        		$combinacion = Combinacion::select('id', 'articulo_id', 'codigo')->where('articulo_id', $articulo->id)->where('codigo', $data->capea_combinacion)->first();
				if ($combinacion)
					$combinacion_id = $combinacion->id;
				else
					return;
			}

			$material_id = NULL;
        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->capea_material, '0'))->first();
			if ($articulo)
				$material_id = $articulo->id;

			$color_id = NULL;
        	$color = Color::select('id', 'codigo')->where('codigo' , $data->capea_color)->first();
			if ($color)
				$color_id = $color->id;

            Capeart::create([
    			"articulo_id" => $articulo_id,
				"combinacion_id" => $combinacion_id,
				"material_id" => $material_id,
				"color_id" => $color_id,
				"piezas" => $data->capea_piezas,
				"tipo" => $data->capea_tipo,
				"tipocalculo" => $data->capea_tipo_calculo,
				"consumo1" => $data->capea_consumo1,
				"consumo2" => $data->capea_consumo2,
				"consumo3" => $data->capea_consumo3,
				"consumo4" => $data->capea_consumo4,
				"usuarioultcambio_id" => $usuario_id
            ]);
        }
    }

	public function guardarAnita($request, $materiales, $colores, $piezas, $consumo1, $consumo2, $consumo3, $consumo4, $tipo, $tipocalculo, $orden) {
        $apiAnita = new ApiAnita();

		$material_sku = NULL;
        $articulo = Articulo::select('id', 'sku')->where('id' , $materiales)->first();
		if ($articulo)
			$material_sku = $articulo->sku;

		$color = NULL;
        $color = Color::select('id', 'codigo')->where('id' , $colores)->first();
		if ($color)
			$color = $color->codigo;

		if ($tipo == '')
			$tipo = 'C';

        $data = array( 'acc' => 'insert',
			'tabla' => $this->table, 
            'campos' => '
				capea_articulo,
				capea_orden,
				capea_material,
				capea_color,
				capea_piezas,
				capea_consumo1,
				capea_consumo2,
				capea_consumo3,
				capea_consumo4,
				capea_combinacion,
				capea_tipo,
				capea_tipo_calculo
				',
            'valores' => "
				'".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."', 
				'".$orden."',
				'".str_pad($material_sku, 13, "0", STR_PAD_LEFT)."',
				'".$color."',
				'".$piezas."',
				'".$consumo1."',
				'".$consumo2."',
				'".$consumo3."',
				'".$consumo4."',
				'".$request->codigo."', 
				'".$tipo."',
				'".($tipocalculo == 'true' ? 'D' : 'P')."'"
        );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($articulo, $combinacion) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->table, 
						'whereArmado' => " WHERE capea_articulo = '".str_pad($articulo, 13, "0", STR_PAD_LEFT)."' AND capea_combinacion = '".$combinacion."' " );
        $apiAnita->apiCall($data);
	}
}
