<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\ApiAnita;
use Auth;

class Avioart extends Model
{
    protected $fillable = ['articulo_id', 'combinacion_id', 'material_id', 'color_id', 'tipo', 'consumo1', 'consumo2', 'consumo3', 'consumo4', 'usuarioultcambio_id'];
    protected $table = 'avioart';
    protected $tableAnita = 'aviosart';
    protected $keyField = 'id';
    protected $keyFieldAnita = ['avioa_articulo', 'avioa_combinacion', 'avioa_orden'];

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
        return $this->belongsTo(Material::class, 'material_id');
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
	  	ini_set('memory_limit', '512M');
        $apiAnita = new ApiAnita();

		if ($articulo != null)
        	$data = array( 'acc' => 'list', 
		  				'campos' => "avioa_articulo, avioa_combinacion, avioa_orden", 
            			'whereArmado' => " WHERE exists (select 1 from combinacion where avioa_articulo=comb_articulo and avioa_combinacion=comb_combinacion) AND avioa_articulo='".$articulo."' AND avioa_combinacion='".$combinacion."'",
		  				'tabla' => $this->tableAnita);
		else
        	$data = array( 'acc' => 'list', 
		  				'campos' => "avioa_articulo, avioa_combinacion, avioa_orden", 
            			'whereArmado' => " WHERE exists (select 1 from combinacion where avioa_articulo=comb_articulo and avioa_combinacion=comb_combinacion) ",
		  				'tabla' => $this->tableAnita);
        $dataAnita = json_decode($apiAnita->apiCall($data));

		/*for ($ii = 1294; $ii < count($dataAnita); $ii++)
        	$this->traerRegistroDeAnita($dataAnita[$ii]->{$this->keyFieldAnita[0]}, $dataAnita[$ii]->{$this->keyFieldAnita[1]}, $dataAnita[$ii]->{$this->keyFieldAnita[2]});*/

        foreach ($dataAnita as $value) {
        		$this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, $value->{$this->keyFieldAnita[2]});
        }
    }

    public function traerRegistroDeAnita($articulo, $combinacion, $orden){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
				avioa_articulo,
				avioa_orden,
				avioa_material,
				avioa_color,
				avioa_consumo1,
				avioa_consumo2,
				avioa_consumo3,
				avioa_consumo4,
				avioa_combinacion,
				avioa_tipo
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnita[0]." = '".$articulo.
							"' AND ".$this->keyFieldAnita[1]." = '".$combinacion.
							"' AND ".$this->keyFieldAnita[2]." = '".$orden."' "
        );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $data = $dataAnita[0];

			// Lee id del articulo por sku
        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->avioa_articulo, '0'))->first();
			$articulo_id = $articulo->id;

			// Lee combinacion
			$combinacion_id = 0;
			if ($articulo)
			{
				// Leo la combinacion para sacar el id
        		$combinacion = Combinacion::select('id', 'articulo_id', 'codigo')->where('articulo_id', $articulo->id)->where('codigo', $data->avioa_combinacion)->first();
				if ($combinacion)
					$combinacion_id = $combinacion->id;
			}

			if ($combinacion_id != 0)
			{
				$material_id = NULL;
        		$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->avioa_material, '0'))->first();
				if ($articulo)
					$material_id = $articulo->id;
	
				$color_id = NULL;
        		$color = Color::select('id', 'codigo')->where('codigo' , $data->avioa_color)->first();
				if ($color)
					$color_id = $color->id;
	
            	AvioArt::create([
    				"articulo_id" => $articulo_id,
					"combinacion_id" => $combinacion_id,
					"material_id" => $material_id,
					"color_id" => $color_id,
					"tipo" => $data->avioa_tipo,
					"consumo1" => $data->avioa_consumo1,
					"consumo2" => $data->avioa_consumo2,
					"consumo3" => $data->avioa_consumo3,
					"consumo4" => $data->avioa_consumo4,
					"usuarioultcambio_id" => $usuario_id
            	]);
			 }
        }
    }

	public function guardarAnita($request, $materiales, $colores, $consumo1, $consumo2, $consumo3, $consumo4, $tipos, $orden) {
        $apiAnita = new ApiAnita();

		$material_sku = NULL;
        $articulo = Articulo::select('id', 'sku')->where('id' , $materiales)->first();
		if ($articulo)
			$material_sku = $articulo->sku;

		$color = NULL;
        $color = Color::select('id', 'codigo')->where('id' , $colores)->first();
		if ($color)
			$color = $color->codigo;

        $data = array( 'acc' => 'insert',
			'tabla' => $this->tableAnita, 
            'campos' => '
				avioa_articulo,
				avioa_orden,
				avioa_material,
				avioa_color,
				avioa_consumo1,
				avioa_consumo2,
				avioa_consumo3,
				avioa_consumo4,
				avioa_combinacion,
				avioa_tipo
				',
            'valores' => "
				'".str_pad($request->sku, 13, "0", STR_PAD_LEFT)."', 
				'".$orden."',
				'".str_pad($material_sku, 13, "0", STR_PAD_LEFT)."',
				'".$color."',
				'".$consumo1."',
				'".$consumo2."',
				'".$consumo3."',
				'".$consumo4."',
				'".$request->codigo."', 
				'".$tipos."'"
        );
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($articulo, $combinacion) {
        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'delete', 
						'tabla' => $this->tableAnita, 
						'whereArmado' => " WHERE avioa_articulo = '".str_pad($articulo, 13, "0", STR_PAD_LEFT)."' AND avioa_combinacion = '".$combinacion."' " );
        $apiAnita->apiCall($data);
	}
}
