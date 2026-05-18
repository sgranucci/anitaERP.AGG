<?php
namespace App\Services\Stock;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Models\Stock\Articulo;
use App\Models\Stock\Combinacion;
use App\Models\Stock\Precio;
use App\Models\Stock\Listaprecio;
use App\Models\Stock\Talle;
use App\Models\Stock\Linea;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use DB;

class PrecioService 
{
	public function __construct(
								)
    {
    }

	public function asignaListaPrecio($talle, $tiponumeracion_id)
	{
		$listaprecio = Listaprecio::all();

		$lista_id = 0;
		foreach($listaprecio as $lista)
		{
			if ($talle >= $lista->desdetalle && $talle <= $lista->hastatalle &&
				$tiponumeracion_id == $lista->tiponumeracion_id)
			{
				$lista_id = $lista->id;
				break;
			}
		}
		return $lista_id;
	}

	public function asignaPrecio($articulo_id, $talle_id, $fechavigencia)
	{
		$talle_id = preg_replace('([^A-Za-z0-9,])', '', $talle_id);
		$array_talle = explode(',', $talle_id);
		$array_precio = [];
		if ($talle_id)
		{
			$talle = Talle::select('nombre', 'id')->whereIn('id', $array_talle)->get();

			// Lee el articulo
			$articulo = Articulo::select('linea_id')->where('id', $articulo_id)->first();

			$tiponumeracion_id = 0;
			if ($articulo)
			{
				$linea = Linea::find($articulo->linea_id);

				if ($linea)
					$tiponumeracion_id = $linea->tiponumeracion_id;
			}
			foreach($talle as $value)
			{
				$lista = $this->asignaListaPrecio($value->nombre, $tiponumeracion_id);

				if (gettype($fechavigencia) == "string")
					$fecha = $fechavigencia;
				else
					$fecha = date('Y-m-d', strtotime($fechavigencia));
				
				$precio = Precio::with('listaprecios')
								->where('articulo_id',$articulo_id)
								->where('listaprecio_id',$lista)
								->where('fechavigencia', '<=', $fecha)
								->orderBy('fechavigencia', 'desc')
								->first();
				if ($precio)
				{
					$precio_talle = $precio->precio;
					$listaprecio_id = $precio->listaprecio_id;
					$moneda_id = $precio->moneda_id;
					$incluyeimpuesto = $precio->listaprecios->incluyeimpuesto;
				}
				else
				{
					$precio_talle = 0;
					$listaprecio_id = 0;
					$moneda_id = 1;
					$incluyeimpuesto = 1;
				}

				$array_precio[] = [
									'precio'=>$precio_talle,
				  					'listaprecio_id'=>$listaprecio_id,
				  					'moneda_id'=>$moneda_id,
				  					'incluyeimpuesto'=>$incluyeimpuesto,
				  					];
			}
		}

		return($array_precio);
	}

	public function asignaPrecioPorTipoNumeracion($articulo_id, $tiponumeracion_id, $fechavigencia)
	{	
		$listaprecio = Listaprecio::all();

		$array_precio = [];
		foreach($listaprecio as $lista)
		{
			if ($tiponumeracion_id == $lista->tiponumeracion_id)
			{
				$lista_id = $lista->id;
				
				$precio = Precio::with('listaprecios')
								->where('articulo_id',$articulo_id)
								->where('listaprecio_id',$lista_id)
								->whereDate('fechavigencia', '<=', date('Y-m-d', strtotime($fechavigencia)))
								->orderBy('fechavigencia', 'desc')
								->first();
	
				if ($precio)
				{
					$precio_talle = $precio->precio;
					$listaprecio_id = $precio->listaprecio_id;
					$moneda_id = $precio->moneda_id;
					$incluyeimpuesto = $precio->listaprecios->incluyeimpuesto;
				}
				else
				{
					$precio_talle = 0;
					$listaprecio_id = 0;
					$moneda_id = 1;
					$incluyeimpuesto = 1;
				}

				$array_precio[] = [
					'precio'=>$precio_talle,
					'listaprecio_id'=>$listaprecio_id,
					'moneda_id'=>$moneda_id,
					'incluyeimpuesto'=>$incluyeimpuesto,
					];
			}
		}
		return($array_precio);
	}

	public static function asignaPrecioPorLista($articulo_id, $listaprecio_id, $fechavigencia)
	{
		// Asigna precio por vigencia
		$precios = Precio::select('articulo_id', 'listaprecio_id', 'fechavigencia', 'precio', 'moneda_id', 'listaprecio.incluyeimpuesto')
						->join('listaprecio', 'listaprecio.id', 'precio.listaprecio_id')
						->where('articulo_id', '=', $articulo_id)
						->where('listaprecio_id', '=', $listaprecio_id)
						->orderBy('fechavigencia')
						->get();

		$precioRet = 0;
		$array_precio = [];
		foreach($precios as $precio)
		{
			if ($precio->fechavigencia <= $fechavigencia)
			{
				$precioRet = $precio->precio;

				$array_precio[] = [
						'precio'=>$precioRet,
						'listaprecio_id'=>$precio->listaprecio_id,
						'moneda_id'=>$precio->moneda_id,
						'incluyeimpuesto'=>$precio->incluyeimpuesto,
						];
			}
		}

		return($array_precio);
	}

	public function generaDatosRepListaPrecio($estado, $mventa_id,
											$desdearticulo_id, $hastaarticulo_id,
											$desdecategoria_id, $hastacategoria_id, $listasprecio,
											$nofactura)
	{
		$listasPrecio_id = explode(',', $listasprecio);

		// Asigna precio por vigencia
		$precios = Precio::select('precio.articulo_id as articulo_id', 
								'articulo.sku as sku', 
								'articulo.descripcion as descripcion', 
								'articulo.categoria_id as categoria_id',
								'articulo.mventa_id as mventa_id',
								'articulo.nofactura as nofactura',
								'mventa.nombre as marca',
								'categoria.nombre as categoria',
								'precio.listaprecio_id as listaprecio_id', 
								'precio.fechavigencia as fechavigencia', 
								'precio.precio')
				->join('articulo', 'articulo.id', 'precio.articulo_id')
				->join('mventa', 'mventa.id', 'articulo.mventa_id')
				->join('categoria', 'categoria.id', 'articulo.categoria_id')
				->whereBetween('articulo_id', [$desdearticulo_id, $hastaarticulo_id]);

		if ($nofactura != "2")
			$precios = $precios->where('nofactura', $nofactura);
		
		if ($mventa_id > 0)
			$precios = $precios->where('articulo.mventa_id', $mventa_id);

		$precios = $precios->whereBetween('articulo.categoria_id', [$desdecategoria_id, $hastacategoria_id])
				->whereIn('precio.listaprecio_id', $listasPrecio_id)
				->orderBy('articulo.descripcion')
				->orderBy('articulo.sku')
				->orderBy('listaprecio_id')
				->orderBy('fechavigencia', 'desc');		
		
		if ($estado != 'TODAS')
		{
			$query = $precios->whereExists(function($query) use($estado)
				{
					$query->select(DB::raw(1))
						->from("combinacion")
						  ->whereRaw("combinacion.articulo_id=articulo.id and combinacion.estado='".substr($estado,0,1)."'");
				});

			$query = $query->get();
		}
		else
			$query = $precios->get();

		$data = [];
		$anterSku = '';
		foreach($query as $articulo)
		{
			if ($articulo['sku'] != $anterSku)
			{
				if ($anterSku != '')
				{
					$data[] = [
						'articulo_id' => $articulo_id,
						'sku' => $sku,
						'descripcion' => $descripcion,
						'marca' => $marca,
						'categoria' => $categoria,
						'precios' => $precios
					];
				}
				$anterSku = $articulo['sku'];
				$precios = [];
			}
			
			$articulo_id = $articulo['articulo_id'];
			$sku = $articulo['sku'];
			$descripcion = $articulo['descripcion'];
			$marca = $articulo['marca'];
			$categoria = $articulo['categoria'];

			for ($i = 0, $flEncontro = false; $i < count($precios); $i++)
			{
				if ($articulo['listaprecio_id'] == $precios[$i]['listaprecio_id'])
				{
					$flEncontro = true;
				}
			}
			if (!$flEncontro)
				$precios[] = [ 'listaprecio_id' => $articulo['listaprecio_id'],
								'precio' => $articulo['precio'],
								'fechavigencia' => $articulo['fechavigencia']
						];
		}
		if ($anterSku != '')
		{
			$data[] = [
				'articulo_id' => $articulo_id,
				'sku' => $sku,
				'descripcion' => $descripcion,
				'marca' => $marca,
				'categoria' => $categoria,
				'precios' => $precios
			];
		}
		return($data);
	}

	/**
	 * Alta de precio (siempre crea un registro nuevo).
	 */
	public function crearDesdeFormulario(Request $request): Precio
	{
		$fechavigencia = Carbon::createFromFormat('d-m-Y', $request->fechavigencia);

		return Precio::create([
			'articulo_id' => $request->articulo_id,
			'listaprecio_id' => $request->listaprecio_id,
			'fechavigencia' => $fechavigencia,
			'moneda_id' => $request->moneda_id,
			'precio' => $request->precio,
			'precioanterior' => 0,
			'usuarioultcambio_id' => Auth::id(),
		]);
	}

	/**
	 * Actualiza el registro si la fecha de vigencia no cambia; si cambia, crea uno nuevo y conserva el anterior.
	 *
	 * @return array{precio: Precio, creado_nueva_vigencia: bool}
	 */
	public function actualizarDesdeFormulario(Request $request, int $id): array
	{
		$precioRegistro = Precio::findOrFail($id);
		$fechavigencia = Carbon::createFromFormat('d-m-Y', $request->fechavigencia);
		$fechaOriginal = Carbon::parse($precioRegistro->fechavigencia)->format('Y-m-d');
		$fechaNueva = $fechavigencia->format('Y-m-d');

		$payload = [
			'listaprecio_id' => $request->listaprecio_id,
			'moneda_id' => $request->moneda_id,
			'precio' => $request->precio,
			'usuarioultcambio_id' => Auth::id(),
		];

		if ($fechaOriginal === $fechaNueva) {
			$precioRegistro->update(array_merge($payload, [
				'fechavigencia' => $fechavigencia,
				'precioanterior' => $precioRegistro->precio,
			]));

			return [
				'precio' => $precioRegistro->fresh(),
				'creado_nueva_vigencia' => false,
			];
		}

		$precioNuevo = Precio::create(array_merge($payload, [
			'articulo_id' => $precioRegistro->articulo_id,
			'fechavigencia' => $fechavigencia,
			'precioanterior' => $precioRegistro->precio,
		]));

		return [
			'precio' => $precioNuevo,
			'creado_nueva_vigencia' => true,
		];
	}
}
