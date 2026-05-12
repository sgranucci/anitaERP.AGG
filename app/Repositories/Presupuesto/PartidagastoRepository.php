<?php

namespace App\Repositories\Presupuesto;

use App\Models\Presupuesto\Partidagasto;
use App\Models\Presupuesto\Presupuesto;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Exception;
use Carbon\Carbon;
use Auth;
use DB;

class PartidagastoRepository implements PartidagastoRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
	public function __construct(Partidagasto $partidagasto)
    {
        $this->model = $partidagasto;
    }

	public function all()
	{

	}
	
    public function create(array $data)
    {
		$data['codigo'] = Self::ultimoPartidagasto();

		return $this->model->create($data);
    }

    public function createDesdeAnita(array $data)
    {
		return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
		$partidagasto = $this->model->findOrFail($id)->update($data);

		return $partidagasto;
    }

    public function delete($id)
    {
		$partidagasto = $this->model->findOrFail($id);

		if ($partidagasto)
        	$partidagasto = $this->model->destroy($id);

		return $partidagasto;
    }

    public function find($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")
									->with("centrocostos")
									->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }
		return($partidagasto);
	}

    public function findOrFail($id)
    {
        if (null == $partidagasto = $this->model->with("partidagasto_estados")
									->with("partidagasto_montos")
									->with("partidagasto_archivos")
									->with("empresas")
									->with("monedas")
									->with("presupuestos")
									->with("presupuesto_escenarios")
									->with("articulos")
									->with("proveedores")
									->with("cuentacontables")									
									->with("centrocostos")
									->findOrFail($id))
		{
            throw new ModelNotFoundException("Registro no encontrado");
        }
        return $partidagasto;
    }

	public function findPorCodigo($codigo)
	{
		return $this->model->with("empresas")
							->with("presupuestos")
							->with("presupuesto_escenarios")
							->with("articulos")
							->with("proveedores")
							->with("cuentacontables")
							->with("centrocostos")
							->where('codigo', $codigo)->first();	
	}

	// Devuelve ultimo numero de partidagasto + 1 (campo codigo)
	private function ultimoPartidagasto()
	{
		$partidagasto = $this->model->select('codigo')->orderBy('id', 'desc')->first();
		
		$numeropartidagasto = 0;
        if ($partidagasto) 
		{
			$numeropartidagasto = $partidagasto->codigo;
			$numeropartidagasto = $numeropartidagasto + 1;
		}
		else	
			$numeropartidagasto = 1;

		return $numeropartidagasto;
	}	

	public function leePartidaGasto($empresa_id, $presupuesto_id, $presupuesto_escenario_id)
	{
		$partidagasto = $this->model->select('partidagasto.id as id', 
											'partidagasto.presupuesto_id as presupuesto_id',
											'partidagasto.presupuesto_escenario_id as escenario_id',
											'partidagasto.empresa_id as empresa_id',
											'empresa.nombre as nombreempresa',
											'partidagasto.codigo as codigopartida',
											'presupuesto.nombre as nombrepresupuesto',
											'centrocosto.codigo as codigocentrocosto',
											'centrocosto.nombre as nombrecentrocosto',
											'centrocosto.id as centrocosto_id',
											'articulo.descripcion as descripcionarticulo',
											'proveedor.nombre as nombreproveedor',
											'partidagasto.moneda_id as moneda_id',
											'moneda.codigo as codigomoneda',
											'moneda.abreviatura as abreviaturamoneda',
											'partidagasto.cuentacontable_id as cuentacontable_id',
											'cuentacontable.codigo as codigocuentacontable',
											'cuentacontable.nombre as nombrecuentacontable',
											'partidagasto.detalle as detalle',
											'partidagasto.estado as estado',
											'partidagasto_monto.periodo as periodo',
											'partidagasto_monto.monto as monto')
											->join('empresa', 'empresa.id', '=', 'partidagasto.empresa_id')
											->join('centrocosto', 'centrocosto.id', '=', 'partidagasto.centrocosto_id')
											->join('moneda', 'moneda.id', '=', 'partidagasto.moneda_id')
											->join('presupuesto', 'presupuesto.id', '=', 'partidagasto.presupuesto_id')
											->leftjoin('proveedor', 'proveedor.id', '=', 'partidagasto.proveedor_id')
											->leftjoin('articulo', 'articulo.id', '=', 'partidagasto.articulo_id')
											->leftjoin('cuentacontable', 'cuentacontable.id', '=', 'partidagasto.cuentacontable_id')	
											->join('partidagasto_monto', 'partidagasto_monto.partidagasto_id', '=', 'partidagasto.id')
											->where('partidagasto.empresa_id', $empresa_id)
											->where('partidagasto.presupuesto_id', $presupuesto_id)
											->where('partidagasto.presupuesto_escenario_id', $presupuesto_escenario_id)
											->where('partidagasto_monto.monto', '!=', 0)
											->get();
		return $partidagasto;
	}

	public function consultaPartidagasto($consulta, $empresa_id, $centrocostodestino_id = null)
	{
		ini_set('max_execution_time', '120');
		ini_set('memory_limit', '256M');

		$output = ['data' => ''];
		$empresa_id = (int) $empresa_id;
		if ($empresa_id <= 0) {
			$output['data'] .= '<tr><td colspan="5">Seleccione una empresa en el encabezado.</td></tr>';

			return $output;
		}

		$ultimoPresupuestoId = Presupuesto::query()->max('id');
		if (!$ultimoPresupuestoId) {
			$output['data'] .= '<tr><td colspan="5">No hay presupuestos cargados.</td></tr>';

			return $output;
		}

		$q = $this->model->query()
			->where('empresa_id', $empresa_id)
			->where('presupuesto_id', $ultimoPresupuestoId);

		$centrocostodestino_id = (int) $centrocostodestino_id;
		if ($centrocostodestino_id > 0) {
			$q->where('centrocosto_id', $centrocostodestino_id);
		}

		$consulta = is_string($consulta) ? trim($consulta) : '';
		if ($consulta !== '') {
			$c = $consulta;
			$q->where(function ($query) use ($c) {
				$query->where('codigo', 'like', '%'.$c.'%')
					->orWhere('detalle', 'like', '%'.$c.'%');
				if (ctype_digit($c)) {
					$query->orWhere('id', (int) $c);
				}
			});
		}

		$data = $q->orderBy('codigo')->limit(200)->get();
		if ($data->isEmpty()) {
			$output['data'] .= '<tr><td colspan="5">Sin resultados para el último presupuesto y esta empresa.</td></tr>';

			return $output;
		}

		foreach ($data as $row) {
			$conceptoArt = optional($row->articulos)->descripcion;
			$conceptoTxt = ($conceptoArt !== null && trim((string) $conceptoArt) !== '')
				? trim((string) $conceptoArt)
				: '(Sin descripción en artículo — partida asignada)';
			$output['data'] .= '<tr>';
			$output['data'] .= '<td class="id">'.e($row->id).'</td>';
			$output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
			$output['data'] .= '<td class="detalle">'.e($row->detalle).'</td>';
			$output['data'] .= '<td class="concepto">'.e($conceptoTxt).'</td>';
			$output['data'] .= '<td>'
				.'<a class="btn btn-warning btn-sm eligeconsultapartidagasto">Elegir</a> '
				.'<a class="btn btn-info btn-sm" href="'.e(url('presupuesto/partidagasto/'.$row->id.'/editar')).'" target="_blank" rel="noopener">Consultar</a>'
				.'</td>';
			$output['data'] .= '</tr>';
		}

		return $output;
	}

	public function diagnosticarCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): array
	{
		$empresa_id = (int) $empresa_id;
		if ($empresa_id <= 0) {
			return ['ok' => false, 'row' => null, 'mensaje' => 'Seleccione una empresa en el encabezado.'];
		}
		$codigo = trim($codigo);
		if ($codigo === '') {
			return ['ok' => false, 'row' => null, 'mensaje' => 'Indique el código de partida.'];
		}

		$ultimoPresupuestoId = (int) Presupuesto::query()->max('id');
		if ($ultimoPresupuestoId <= 0) {
			return ['ok' => false, 'row' => null, 'mensaje' => 'No hay presupuestos cargados.'];
		}

		// Solo presupuesto vigente: evita tomar una fila vieja que luego se rechaza por presupuesto_id.
		$matches = $this->model->query()
			->with('articulos')
			->where('empresa_id', $empresa_id)
			->where('presupuesto_id', $ultimoPresupuestoId)
			->where(function ($w) use ($codigo) {
				$w->where('codigo', $codigo);
				if (is_numeric($codigo)) {
					$w->orWhere('codigo', (string) ((int) (0 + $codigo)));
				}
			})
			->get();

		if ($matches->isEmpty()) {
			return ['ok' => false, 'row' => null, 'mensaje' => 'No existe partida de gastos con ese código en el presupuesto vigente para la empresa.'];
		}

		$centrocostodestino_id = (int) ($centrocostodestino_id ?? 0);

		if ($matches->count() === 1) {
			return ['ok' => true, 'row' => $matches->first(), 'mensaje' => null];
		}

		if ($centrocostodestino_id > 0) {
			$row = $matches->firstWhere('centrocosto_id', $centrocostodestino_id);
			if ($row) {
				return ['ok' => true, 'row' => $row, 'mensaje' => null];
			}

			return ['ok' => false, 'row' => null, 'mensaje' => 'Hay varias partidas con ese código en el presupuesto vigente; ninguna coincide con el centro de costo de destino de la línea.'];
		}

		return ['ok' => false, 'row' => null, 'mensaje' => 'Hay varias partidas con ese código en el presupuesto vigente; indique centro de costo destino en la línea o elija desde la lupa.'];
	}

	public function resolverPorCodigoLinea(string $codigo, int $empresa_id, ?int $centrocostodestino_id): ?Partidagasto
	{
		$d = $this->diagnosticarCodigoLinea($codigo, $empresa_id, $centrocostodestino_id);

		return $d['ok'] ? $d['row'] : null;
	}
}
