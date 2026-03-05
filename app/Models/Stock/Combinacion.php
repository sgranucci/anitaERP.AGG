<?php

namespace App\Models\Stock;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;
use App\ApiAnita;
use App\Models\Seguridad\Usuario;
use Auth;

class Combinacion extends Model
{
    protected $fillable = [ 'articulo_id', 'codigo', 'nombre', 'observacion', 'forro_id', 'colorforro_id', 'plvista_id', 'plarmado_id',
            'fondo_id', 'colorfondo_id', 'horma_id', 'serigrafia_id', 'estado', 'plvista_16_26', 'plvista_27_33', 'plvista_34_40', 'plvista_41_47',
            'usuarioultcambio_id', 'foto' ];
    protected $table = 'combinacion';
    protected $tableAnita = ['combinacion', 'stkfich'];
    protected $keyField = 'id';
    protected $keyFieldAnitacombinacion = ['comb_articulo', 'comb_combinacion'];
    protected $keyFieldAnitastkfich = ['stkfi_articulo', 'stkfi_combinacion'];

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

	public function pedido_combinaciones()
    {
        return $this->hasMany(Pedido_combinacion::class, 'id');
    }

    public function forros()
    {
        return $this->belongsTo(Forro::class, 'forro_id');
    }

    public function coloresforros()
    {
        return $this->belongsTo(Color::class, 'colorforro_id');
    }

    public function plvistas()
    {
        return $this->belongsTo(Plvista::class, 'plvista_id');
    }

    public function plarmados()
    {
        return $this->belongsTo(Plarmado::class, 'plarmado_id');
    }

    public function fondos()
    {
        return $this->belongsTo(Fondo::class, 'fondo_id');
    }

    public function coloresfondos()
    {
        return $this->belongsTo(Color::class, 'colorfondo_id');
    }

    public function hormas()
    {
        return $this->belongsTo(Horma::class, 'horma_id');
    }

    public function serigrafias()
    {
        return $this->belongsTo(Serigrafia::class, 'serigrafia_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuarioultcambio_id');
    }

    public function capearts()
    {
    	return $this->hasMany(Capeart::class, 'combinacion_id');
    }

    public function avioarts()
    {
    	return $this->hasMany(Avioart::class, 'combinacion_id');
    }

    public static function setFoto($foto, $nombre_foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_articulos/$actual");
            }
            $imageName = $nombre_foto . '.jpg';
            $imagen = Image::make($foto)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put("imagenes/fotos_articulos/$imageName", $imagen->stream());
            return $imageName;
        } else {
            return false;
        }
    }

    public function sincronizarConAnita(){
	  	ini_set('memory_limit', '512M');
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
		  				'campos' => "comb_articulo, comb_combinacion, comb_estado", 
            			//'whereArmado' => " WHERE not exists (select 1 from tmp8 where comb_articulo=articulo and 
											//comb_combinacion=combinacion) ",
		  				'tabla' => $this->tableAnita[0] );
        $dataAnita = json_decode($apiAnita->apiCall($data));

/*		$articulos = [ '0000071212602'
			];*/

        $datosLocal = Combinacion::with("articulos")->get();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->articulos->sku.'-'.$value->codigo;
		}

		/*for ($ii = 7004; $ii < count($dataAnita); $ii++)
		{
        	$this->traerRegistroDeAnita($dataAnita[$ii]->comb_articulo, $dataAnita[$ii]->comb_combinacion);
		}*/

        foreach ($dataAnita as $value) {
			$data = $value->{$this->keyFieldAnitacombinacion[0]}.'-'.$value->{$this->keyFieldAnitacombinacion[1]};

			if (!in_array($data, $datosLocalArray))
			{
				$comb = ltrim($value->{$this->keyFieldAnitacombinacion[0]},'0').'-'.$value->{$this->keyFieldAnitacombinacion[1]};

            	if (!in_array($comb, $datosLocalArray))
				{
        			$this->traerRegistroDeAnita($value->{$this->keyFieldAnitacombinacion[0]}, $value->{$this->keyFieldAnitacombinacion[1]});
				}
			}
		  }
    }

    public function traerRegistroDeAnita($articulo, $combinacion){
        $apiAnita = new ApiAnita();
        $datacombinacion = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
            'campos' => '
				comb_articulo,
				comb_desc_articulo,
				comb_combinacion,
				comb_desc,
				comb_observacion,
				comb_estado
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnitacombinacion[0]." = '".$articulo.
							"' AND ".$this->keyFieldAnitacombinacion[1]." = '".$combinacion."' "
        );
        $dataAnitacombinacion = json_decode($apiAnita->apiCall($datacombinacion));

        $datastkfich = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[1], 
            'campos' => '
				stkfi_articulo,
				stkfi_forro,
				stkfi_color_forro,
				stkfi_plvista,
				stkfi_plarmado,
				stkfi_fondo,
				stkfi_codigo_fondo,
				stkfi_color_fondo,
				stkfi_horma,
				stkfi_combinacion,
				stkf_serigrafia,
				stkfi_plvi_16_26,
				stkfi_plvi_27_33,
				stkfi_plvi_34_40,
				stkfi_plvi_41_45
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnitastkfich[0]." = '".$articulo.
							"' AND ".$this->keyFieldAnitastkfich[1]." = '".$combinacion."' "
        );
        $dataAnitastkfich = json_decode($apiAnita->apiCall($datastkfich));

		$usuario_id = Auth::user()->id;

        if (count($dataAnitacombinacion) > 0 && count($dataAnitastkfich) > 0) {
            $datac = $dataAnitacombinacion[0];
            $datas = $dataAnitastkfich[0];

        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($datac->comb_articulo, '0'))->first();
			if ($articulo)
				$articulo_id = $articulo->id;
			else
				return 0;

			$forro_id = NULL;
        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($datas->stkfi_forro, '0'))->first();
			if ($articulo)
			{
        		$forro = Forro::select('id', 'articulo_id')->where('articulo_id' , $articulo->id)->first();
				if ($forro)
					$forro_id = $forro->id;
			}

			$plvista_id = NULL;
        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($datas->stkfi_plvista, '0'))->first();
			if ($articulo)
			{
        		$plvista = Plvista::select('id', 'articulo_id')->where('articulo_id' , $articulo->id)->first();
				if ($plvista)
					$plvista_id = $plvista->id;
			}

			$fondo_id = NULL;
        	$fondo = Fondo::select('id', 'codigo')->where('codigo' , $datas->stkfi_codigo_fondo)->first();
			if ($fondo)
				$fondo_id = $fondo->id;

			$serigrafia_id = NULL;
        	$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($datas->stkf_serigrafia, '0'))->first();
			if ($articulo)
			{
        		$serigrafia = Serigrafia::select('id', 'articulo_id')->where('articulo_id' , $articulo->id)->first();
				if ($serigrafia)
					$serigrafia_id = $serigrafia->id;
			}

			$colorforro_id = NULL;
        	$color = Color::select('id', 'codigo')->where('codigo' , $datas->stkfi_color_forro)->first();
			if ($color)
				$colorforro_id = $color->id;

			$colorfondo_id = NULL;
        	$color = Color::select('id', 'codigo')->where('codigo' , $datas->stkfi_color_fondo)->first();
			if ($color)
				$colorfondo_id = $color->id;

            Combinacion::create([
    				"articulo_id" => $articulo_id,
					"codigo" => $datac->comb_combinacion,
					"nombre" => $datac->comb_desc,
					"observacion" => $datac->comb_observacion,
					"forro_id" => $forro_id,
					"colorforro_id" => $colorforro_id,
					"plvista_id" => $plvista_id,
					"plarmado_id" => ($datas->stkfi_plarmado == 0 ? NULL : $datas->stkfi_plarmado),
            		"fondo_id" => $fondo_id,
					"colorfondo_id" => $colorfondo_id,
					"horma_id" => ($datas->stkfi_horma == 0 ? NULL : $datas->stkfi_horma),
					"serigrafia_id" => $serigrafia_id,
					"estado" => $datac->comb_estado,
					"plvista_16_26" => $datas->stkfi_plvi_16_26,
					"plvista_17_33" => $datas->stkfi_plvi_27_33,
					"plvista_34_40" => $datas->stkfi_plvi_34_40,
					"plvista_41_45" => $datas->stkfi_plvi_41_45,
            		"usuarioultcambio_id" => $usuario_id,
            ]);
        }
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();

        $articulo = Articulo::select('id', 'sku', 'descripcion')->where('id', $request->articulo_id)->first();

		// Graba combinacion
		$data = array( 'tabla' => $this->tableAnita[0], 
		  	'acc' => 'insert',
            'campos' => ' 
				comb_articulo,
				comb_desc_articulo,
				comb_combinacion,
				comb_desc,
				comb_observacion,
				comb_estado
					',
            'valores' => " 
				'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."', 
				'".$articulo->descripcion."',
				'".$request->codigo."',
				'".$request->nombre."',
				'".$request->observacion."',
				'".$request->estado."' "
        );
        $apiAnita->apiCall($data);

		// Graba stkfich
		$data = array( 'tabla' => $this->tableAnita[1], 
		  	'acc' => 'insert',
            'campos' => ' 
				stkfi_articulo,
				stkfi_forro,
				stkfi_color_forro,
				stkfi_plvista,
				stkfi_plarmado,
				stkfi_fondo,
				stkfi_codigo_fondo,
				stkfi_color_fondo,
				stkfi_horma,
				stkfi_combinacion,
				stkf_serigrafia,
				stkfi_plvi_16_26,
				stkfi_plvi_27_33,
				stkfi_plvi_34_40,
				stkfi_plvi_41_45
					',
            'valores' => " 
				'".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."', 
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".$request->codigo."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."',
				'".'0'."' "
        );
        $apiAnita->apiCall($data);
	}

	public function actualizarAnita($request, $origen) {
        $apiAnita = new ApiAnita();

        $articulo = Articulo::select('id', 'sku', 'descripcion')->where('id', $request->articulo_id)->first();

		// Lee combinacion 
        $datacombinacion = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita[0], 
            'campos' => '
				comb_articulo,
				comb_desc_articulo,
				comb_combinacion,
				comb_desc,
				comb_observacion,
				comb_estado
			',
            'whereArmado' => " WHERE ".$this->keyFieldAnitacombinacion[0]." = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT).
							"' AND ".$this->keyFieldAnitacombinacion[1]." = '".$request->codigo."' "
        );
        $dataAnitacombinacion = json_decode($apiAnita->apiCall($datacombinacion));
        if (count($dataAnitacombinacion) == 0) 
		{
		  	self::guardarAnita($request);
		}

		// Actualiza combinacion
		$data = array( 'acc' => 'update', 
		  		'tabla' => $this->tableAnita[0], 
				'valores' => " 
					comb_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."', 
					comb_desc_articulo = '".$articulo->descripcion."',
					comb_combinacion = '".$request->codigo."',
					comb_desc = '".$request->nombre."',
					comb_observacion = '".$request->observacion."',
					comb_estado = '".$request->estado."' ",
				'whereArmado' => " WHERE comb_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' AND comb_combinacion = '".$request->codigo."' ");
        $apiAnita->apiCall($data);

		if ($origen == 'tecnica')
		{
			$forro_sku = ' ';
			if ($request->forro_id != NULL)
			{
        		$forro = Forro::with('articulos')->where('id' , $request->forro_id)->first();
				if ($forro)
					$forro_sku = $forro->articulos->sku;
			}
			$plvista_sku = ' ';
			if ($request->plvista_id != NULL)
			{
        		$plvista = Plvista::with('articulos')->where('id' , $request->plvista_id)->first();
				if ($plvista)
					$plvista_sku = $plvista->articulos->sku;
			}
			$fondo_sku = ' ';
			$fondo_codigo = ' ';
			if ($request->fondo_id != NULL)
			{
        		$fondo = Fondo::with('articulos')->where('id' , $request->fondo_id)->first();
				if ($fondo)
				{	
					$fondo_codigo = $fondo->codigo;
					if ($fondo->articulos)
						$fondo_sku = $fondo->articulos->sku;
				}
			}
			$serigrafia_sku = ' ';
			if ($request->serigrafia_id != NULL)
			{
        		$serigrafia = Serigrafia::with('articulos')->where('id' , $request->serigrafia_id)->first();
				if ($serigrafia)
					$serigrafia_sku = $serigrafia->articulos->sku;
			}
			$horma_id = 0;
			if ($request->horma_id != NULL)
				$horma_id = $request->horma_id;
			$plarmado_id = 0;
			if ($request->plarmado_id != NULL)
				$plarmado_id = $request->plarmado_id;

			$colorfondo = NULL;
        	$color = Color::select('id', 'codigo')->where('id' , $request->colorfondo_id)->first();
			if ($color)
				$colorfondo = $color->codigo;
	
			// Actualiza stkfich
			$data = array( 'acc' => 'update', 
		  			'tabla' => $this->tableAnita[1], 
					'valores' => " 
						stkfi_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."', 
						stkfi_forro = '".str_pad($forro_sku, 13, "0", STR_PAD_LEFT)."',
						stkfi_color_forro = '".$request->colorforro_id."',
						stkfi_plvista = '".str_pad($plvista_sku, 13, "0", STR_PAD_LEFT)."',
						stkfi_plarmado = '".$plarmado_id."',
						stkfi_fondo = '".str_pad($fondo_sku, 13, "0", STR_PAD_LEFT)."',
						stkfi_codigo_fondo = '".$fondo_codigo."',
						stkfi_color_fondo = '".$colorfondo."',
						stkfi_horma = '".$horma_id."',
						stkfi_combinacion = '".$request->codigo."',
						stkf_serigrafia = '".str_pad($serigrafia_sku, 13, "0", STR_PAD_LEFT)."',
						stkfi_plvi_16_26 = '".$request->plvista_16_26."',
						stkfi_plvi_27_33 = '".$request->plvista_27_33."',
						stkfi_plvi_34_40 = '".$request->plvista_34_40."',
						stkfi_plvi_41_45 = '".$request->plvista_41_45."' ",
					'whereArmado' => " WHERE stkfi_articulo = '".str_pad($articulo->sku, 13, "0", STR_PAD_LEFT)."' AND stkfi_combinacion = '".$request->codigo."' ");
        	$apiAnita->apiCall($data);
		}
	}

	public function inactivarAnita() {
        $apiAnita = new ApiAnita();

		// Actualiza combinacion
		$data = array( 'acc' => 'update', 
		  		'tabla' => $this->tableAnita[0], 
				'valores' => " 
					comb_estado = 'I'",
				'whereArmado' => " WHERE comb_estado = 'A'");
        $apiAnita->apiCall($data);
	}

	public function eliminarAnita($articulo, $combinacion) {
        $apiAnita = new ApiAnita();

		// Borra combinacion
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[0], 
				'whereArmado' => " WHERE comb_articulo = '".str_pad($articulo, 13, "0", STR_PAD_LEFT)."' AND comb_combinacion = '".$combinacion."' ");
        $apiAnita->apiCall($data);

		// Borra stkfich
        $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita[1], 
				'whereArmado' => " WHERE stkfi_articulo = '".str_pad($articulo, 13, "0", STR_PAD_LEFT)."' AND stkfi_combinacion = '".$combinacion."' ");
        $apiAnita->apiCall($data);
	}

    public function actualizaEstadoAnita() {
	  	ini_set('memory_limit', '512M');
        $apiAnita = new ApiAnita();
		$data = array( 'acc' => 'list', 
		  				'campos' => "co2_articulo, co2_combinacion, co2_estado", 
		  				'tabla' => "comb2" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

		foreach($dataAnita as $data)
		{
			if ($data->co2_estado == 'A')
			{
        		$articulo = Articulo::select('id', 'sku')->where('sku' , ltrim($data->co2_articulo, '0'))->first();
				if ($articulo)
				{
					$articulo_id = $articulo->id;

        			$combinaciones = Combinacion::where("articulo_id", "=", $articulo_id)->where("codigo","=",$data->co2_combinacion)->
								update(array("estado" => $data->co2_estado));
				}
			}
		}
	}

}
