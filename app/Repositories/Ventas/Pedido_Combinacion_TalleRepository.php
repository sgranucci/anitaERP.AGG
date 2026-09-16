<?php

namespace App\Repositories\Ventas;

use App\Queries\Ventas\PedidoQueryInterface;
use App\Queries\Ventas\Pedido_CombinacionQueryInterface;
use App\Models\Ventas\Pedido_Combinacion_Talle;
use App\Models\Ventas\Ordentrabajo_Combinacion_Talle;
use App\Models\Stock\Articulo_Movimiento_Talle;
use App\Models\Stock\Talle;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\ApiAnita;
use Carbon\Carbon;
use Auth;

class Pedido_Combinacion_TalleRepository implements Pedido_Combinacion_TalleRepositoryInterface
{
    protected $model;
	protected $pedidoQuery;
	protected $pedidoCombinacionQuery;
    protected $keyField = 'codigo';
    protected $tableAnita = 'pendmov';
    protected $keyFieldAnita = ['penm_sucursal', 'penm_nro'];

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Pedido_Combinacion_Talle $pedido_combinacion_talle,
								PedidoQueryInterface $pedidoquery,
								Pedido_CombinacionQueryInterface $pedidocombinacionquery)
    {
        $this->model = $pedido_combinacion_talle;
        $this->pedidoQuery = $pedidoquery;
        $this->pedidoCombinacionQuery = $pedidocombinacionquery;
    }

    public function all()
    {
        return $this->model->get();
    }

	public function create($pedido_combinacion_id, $talle_id, $cantidad, $precio)
	{
        $pedido_combinacion_talle = $this->model->create([
						'pedido_combinacion_id' => $pedido_combinacion_id,
						'talle_id' => $talle_id,
						'cantidad' => $cantidad,
						'precio' => $precio,
								]);

		return($pedido_combinacion_talle);
    }

    public function delete($id)
    {
    	$pedido_combinacion_talle = $this->model->find($id);

        $pedido_combinacion_talle = $this->model->destroy($id);
		return $pedido_combinacion_talle;
    }

    public function deleteporpedido_combinacion($pedido_combinacion_id)
    {
		$pedido_combinacion_id = (int) $pedido_combinacion_id;
		if ($pedido_combinacion_id <= 0) {
			return 0;
		}

		$talleIds = $this->model
			->where('pedido_combinacion_id', $pedido_combinacion_id)
			->pluck('id')
			->filter()
			->values()
			->all();

		// Con foreign_key_checks=0 el ON DELETE CASCADE no limpia hijas: borrar a mano.
		if ($talleIds !== []) {
			Ordentrabajo_Combinacion_Talle::query()
				->whereIn('pedido_combinacion_talle_id', $talleIds)
				->delete();
			Articulo_Movimiento_Talle::query()
				->whereIn('pedido_combinacion_talle_id', $talleIds)
				->delete();
		}

    	return $this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->delete();
    }

	/**
	 * Sync por talle_id: actualiza cantidades, inserta nuevos, borra quitados.
	 * Si hay OT, mantiene OCT existentes (mismo pedido_combinacion_talle_id) y solo crea faltantes.
	 *
	 * @param  array<int, object|array>  $medidas
	 * @param  array<string, mixed>|null  $contextoOt  ordentrabajo_id, cliente_id, usuario_id, estado, ordentrabajo_stock_id
	 * @return array{total_pares: float, grabo: bool, talles: \Illuminate\Support\Collection}
	 */
	public function sincronizarMedidas(int $pedidoCombinacionId, array $medidas, ?array $contextoOt = null): array
	{
		$deseados = [];
		foreach ($medidas as $medida) {
			$talleId = (int) (is_array($medida) ? ($medida['talle_id'] ?? 0) : ($medida->talle_id ?? 0));
			$cantidad = (float) (is_array($medida) ? ($medida['cantidad'] ?? 0) : ($medida->cantidad ?? 0));
			$precio = (float) (is_array($medida) ? ($medida['precio'] ?? 0) : ($medida->precio ?? 0));
			if ($talleId <= 0 || $cantidad <= 0 || isset($deseados[$talleId])) {
				continue;
			}
			$deseados[$talleId] = [
				'talle_id' => $talleId,
				'cantidad' => $cantidad,
				'precio' => $precio,
			];
		}

		$existentes = $this->model
			->where('pedido_combinacion_id', $pedidoCombinacionId)
			->get()
			->keyBy(static fn ($row) => (int) $row->talle_id);

		$tallesFinales = collect();
		$totalPares = 0.0;

		foreach ($deseados as $talleId => $dato) {
			$totalPares += $dato['cantidad'];
			if ($existentes->has($talleId)) {
				$row = $existentes->get($talleId);
				if (
					(float) $row->cantidad !== $dato['cantidad']
					|| (float) $row->precio !== $dato['precio']
				) {
					$row->update([
						'cantidad' => $dato['cantidad'],
						'precio' => $dato['precio'],
					]);
					$row->cantidad = $dato['cantidad'];
					$row->precio = $dato['precio'];
				}
				$tallesFinales->put($talleId, $row);
			} else {
				$tallesFinales->put(
					$talleId,
					$this->create($pedidoCombinacionId, $talleId, $dato['cantidad'], $dato['precio'])
				);
			}
		}

		$aBorrar = $existentes->filter(
			static fn ($row) => ! isset($deseados[(int) $row->talle_id])
		);
		if ($aBorrar->isNotEmpty()) {
			$idsBorrar = $aBorrar->pluck('id')->all();
			Ordentrabajo_Combinacion_Talle::query()
				->whereIn('pedido_combinacion_talle_id', $idsBorrar)
				->delete();
			Articulo_Movimiento_Talle::query()
				->whereIn('pedido_combinacion_talle_id', $idsBorrar)
				->delete();
			$this->model->whereIn('id', $idsBorrar)->delete();
		}

		$ordentrabajoId = (int) ($contextoOt['ordentrabajo_id'] ?? 0);
		if ($ordentrabajoId > 0 && $tallesFinales->isNotEmpty()) {
			$pctIds = $tallesFinales->pluck('id')->all();
			$octPorPct = Ordentrabajo_Combinacion_Talle::query()
				->whereIn('pedido_combinacion_talle_id', $pctIds)
				->get()
				->keyBy('pedido_combinacion_talle_id');

			$clienteId = (int) ($contextoOt['cliente_id'] ?? 0);
			$usuarioId = $contextoOt['usuario_id'] ?? null;
			$estado = (string) ($contextoOt['estado'] ?? '');
			$otStockId = $contextoOt['ordentrabajo_stock_id'] ?? null;

			foreach ($tallesFinales as $pct) {
				$oct = $octPorPct->get($pct->id);
				if ($oct) {
					$upd = [];
					if ($clienteId > 0 && (int) $oct->cliente_id !== $clienteId) {
						$upd['cliente_id'] = $clienteId;
					}
					if (array_key_exists('ordentrabajo_stock_id', $contextoOt ?? [])
						&& $oct->ordentrabajo_stock_id != $otStockId) {
						$upd['ordentrabajo_stock_id'] = $otStockId;
					}
					if ($upd !== []) {
						$oct->update($upd);
					}
					continue;
				}

				Ordentrabajo_Combinacion_Talle::query()->create([
					'ordentrabajo_id' => $ordentrabajoId,
					'pedido_combinacion_talle_id' => $pct->id,
					'cliente_id' => $clienteId,
					'estado' => $estado,
					'ordentrabajo_stock_id' => $otStockId,
					'usuario_id' => $usuarioId,
				]);
			}
		}

		return [
			'total_pares' => $totalPares,
			'grabo' => $tallesFinales->isNotEmpty(),
			'talles' => $tallesFinales->values(),
		];
	}

    public function find($id)
    {
        if (null == $pedido_combinacion_talle = $this->model->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_combinacion_talle;
    }

    public function findOrFail($id)
    {
        if (null == $pedido_combinacion_talle = $this->model->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $pedido_combinacion_talle;
    }

    public function findporpedido_combinacion($pedido_combinacion_id)
    {
    	$pedido_combinacion_talle = $this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->get();

		return $pedido_combinacion_talle;
    }

    public function findporpedido_combinacion_medida($pedido_combinacion_id, $medida)
    {
		$talle = Talle::where('nombre',$medida)->first();
		if ($talle)
			$talle_id = $talle->id;

    	$pedido_combinacion_talle = $this->model->where('pedido_combinacion_id', $pedido_combinacion_id)->where('talle_id',$talle_id)->first();

		return $pedido_combinacion_talle;
    }
    public function sincronizarConAnita()
	{
		ini_set('max_execution_time', '300');

        $apiAnita = new ApiAnita();
        $data = array( 'acc' => 'list', 
						'campos' => "penm_sucursal, penm_nro", 
            			'whereArmado' => " WHERE penm_tipo='PED' and penm_fecha>20230624 ",
						'tabla' => "pendmae" );
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $datosLocal = self::all();
        $datosLocalArray = [];
        foreach ($datosLocal as $value) {
            $datosLocalArray[] = $value->{$this->keyField};
        }

        foreach ($dataAnita as $value) {
			$claveAnita = $value->{$this->keyFieldAnita[0]}.'-'.$value->{$this->keyFieldAnita[1]};
            if (!in_array(ltrim($claveAnita, '0'), $datosLocalArray)) {
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, true);
            }
			else
			{
                $this->traerRegistroDeAnita($value->{$this->keyFieldAnita[0]}, $value->{$this->keyFieldAnita[1]}, false);
			}
        }
    }

    private function traerRegistroDeAnita($sucursal, $nro, $fl_crea_registro){
        $apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 'tabla' => $this->tableAnita, 
            'campos' => '
			    penv_cliente, penv_tipo, penv_letra, penv_sucursal, penv_nro, penv_orden, penv_articulo, penv_desc,
    			penv_agrupacion, penv_unidad_medida, penv_cantidad, penv_cantaentr, penv_cantentr, penv_cantfact, penv_precio,
    			penv_dto_art, penv_deposito, penv_tipo_iva, penv_fecha, penv_incl_impuesto, penv_cod_mon, penv_vendedor,
    			penv_zonavta, penv_zonamult, penv_partida, penv_fecha_ent, penv_color, penv_medida, penv_linea,
    			penv_forro, penv_imprime_ped, penv_observacion, penv_nro_orden, penv_fondo, penv_color_fondo, penv_capellada,
    			penv_color_cap, penv_color_forro, penv_aplique, penv_modulo1, penv_modulo2, penv_modulo3
			',
            'whereArmado' => " WHERE penv_tipo='PED' and penv_letra='A' and penv_sucursal='".
				$sucursal."' and penv_nro='".$nro."' " 
        );
        $data = json_decode($apiAnita->apiCall($data));

		$usuario_id = Auth::user()->id;

        if (count($data) > 0) 
		{
			$i = 0;
        	while ($i < count($data))
			{
			  	$codigo = $data[$i]->penv_tipo.'-'.$data[$i]->penv_letra.'-'.
						str_pad($data[$i]->penv_sucursal, 5, "0", STR_PAD_LEFT).'-'.
						str_pad($data[$i]->penv_nro, 8, "0", STR_PAD_LEFT);
        		$pedido = $this->pedidoQuery->leePedidoporCodigo($codigo);
				if ($pedido)
					$pedido_id = $pedido->id;
				else
					return;

        		$pedido_combinacion = $this->pedidoCombinacionQuery->leePedido_CombinacionporNumeroItem($pedido_id, $data[$i]->penv_orden);
				if ($pedido_combinacion)
					$pedido_combinacion_id = $pedido_combinacion->id;
				else
					return;
	
        		$talle = Talle::select('id')->where('nombre', $data[$i]->penv_medida)->first();
				if ($talle)
					$talle_id = $talle->id;
				else
				  	$talle_id = 1;

				$arr_campos = [
					"pedido_combinacion_id" => $pedido_combinacion_id,
					"talle_id" => $talle_id,
					"cantidad" => $data[$i]->penv_cantidad,
					"precio" => $data[$i]->penv_precio,
            		];
		
				if ($fl_crea_registro)
            		$this->model->create($arr_campos);
				else
            		$this->model->where('codigo', ltrim($data[$i]->clim_cliente, '0'))->update($arr_campos);
				$i++;
        	}
		}
    }

}
