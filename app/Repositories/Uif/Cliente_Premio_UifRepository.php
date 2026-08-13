<?php

namespace App\Repositories\Uif;

use App\Models\Uif\Cliente_Premio_Uif;
use App\Models\Uif\Cliente_Uif;
use App\Services\Uif\ClientePremioUifFotoTesoreria;
use App\Support\Uif\ClientePremioUifListadoFiltros;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Auth;

class Cliente_Premio_UifRepository implements Cliente_Premio_UifRepositoryInterface
{
    protected $model;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cliente_Premio_Uif $cliente_premio_uif)
    {
        $this->model = $cliente_premio_uif;
    }

	public function leeCliente_Premio_Uif($filtros, $flPaginando = null)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = [
                'modo' => ClientePremioUifListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
            ];
        } elseif (! is_array($filtros)) {
            $filtros = [
                'modo' => ClientePremioUifListadoFiltros::MODO_TODOS,
                'campo' => 'nombre',
                'operador' => 'contiene',
                'valor' => '',
                'valor_hasta' => '',
                'busqueda' => '',
            ];
        }

        $cliente_premio_uifs = $this->model->select(
            'cliente_premio_uif.id as id',
            'cliente_uif.anita_origen as anita_origen',
            'cliente_uif.nombre as nombrecliente',
            'sala.nombre as nombresala',
            'juego_uif.nombre as nombrejuego',
            'cliente_premio_uif.fechaentrega as fechaentrega',
            'cliente_premio_uif.monto as monto',
            'cliente_premio_uif.posicion as posicion',
            'cliente_premio_uif.numerotito as numerotito',
            'formapago.nombre as nombreformapago',
            'cliente_premio_uif.foto as foto'
        )
            ->join('cliente_uif', 'cliente_uif.id', '=', 'cliente_premio_uif.cliente_uif_id')
            ->leftjoin('sala', 'sala.id', '=', 'cliente_premio_uif.sala_id')
            ->leftjoin('juego_uif', 'juego_uif.id', '=', 'cliente_premio_uif.juego_uif_id')
            ->leftjoin('formapago', 'formapago.id', '=', 'cliente_premio_uif.formapago_id')
            ->whereNull('cliente_uif.deleted_at')
            ->orderBy('cliente_premio_uif.id', 'DESC');

        ClientePremioUifListadoFiltros::aplicar($cliente_premio_uifs, $filtros);

        if (isset($flPaginando)) {
            if ($flPaginando) {
                $cliente_premio_uifs = $cliente_premio_uifs->paginate(10);
            } else {
                $cliente_premio_uifs = $cliente_premio_uifs->get();
            }
        } else {
            $cliente_premio_uifs = $cliente_premio_uifs->get();
        }

        return $cliente_premio_uifs;
    }

    /**
     * Premios de un cliente UIF (export grilla solapa Premios).
     */
    public function leePremiosPorClienteUif(int $clienteUifId)
    {
        return $this->model->select(
            'cliente_premio_uif.id as id',
            'cliente_uif.nombre as nombrecliente',
            'cliente_uif.numerodocumento as numerodocumento',
            'sala.nombre as nombresala',
            'juego_uif.nombre as nombrejuego',
            'cliente_premio_uif.fechaentrega as fechaentrega',
            'cliente_premio_uif.monto as monto',
            'cliente_premio_uif.posicion as posicion',
            'cliente_premio_uif.numerotito as numerotito',
            'formapago.nombre as nombreformapago',
            'empresa.nombre as nombreempresa'
        )
            ->join('cliente_uif', 'cliente_uif.id', '=', 'cliente_premio_uif.cliente_uif_id')
            ->leftjoin('sala', 'sala.id', '=', 'cliente_premio_uif.sala_id')
            ->leftjoin('empresa', 'empresa.id', '=', 'sala.empresa_id')
            ->leftjoin('juego_uif', 'juego_uif.id', '=', 'cliente_premio_uif.juego_uif_id')
            ->leftjoin('formapago', 'formapago.id', '=', 'cliente_premio_uif.formapago_id')
            ->where('cliente_premio_uif.cliente_uif_id', $clienteUifId)
            ->whereNull('cliente_uif.deleted_at')
            ->orderBy('cliente_premio_uif.fechaentrega', 'DESC')
            ->orderBy('cliente_premio_uif.id', 'DESC')
            ->get();
    }

    public function create(array $data, $id)
    {
		return self::guardarCliente_Premio_Uif($data, 'create', $id);
    }

	public function createUnique(array $data)
	{
        $cid = isset($data['cliente_uif_id']) ? filter_var($data['cliente_uif_id'], FILTER_VALIDATE_INT) : false;
        $anita = isset($data['anita_inropremioid']) ? filter_var($data['anita_inropremioid'], FILTER_VALIDATE_INT) : false;
        $salaId = isset($data['sala_id']) ? (int) $data['sala_id'] : 0;

        if ($cid === false || $cid <= 0) {
            return $this->model->create($data);
        }

        return DB::transaction(function () use ($data, $cid, $anita, $salaId) {
            Cliente_Uif::query()->whereKey($cid)->lockForUpdate()->first();

            if ($anita !== false && $anita > 0) {
                $existQ = $this->model->newQuery()
                    ->where('cliente_uif_id', $cid)
                    ->where('anita_inropremioid', $anita);
                if ($salaId > 0) {
                    $existQ->where('sala_id', $salaId);
                }
                $exist = $existQ->first();
                if ($exist !== null) {
                    return $this->actualizarPremioExistente($exist, $data);
                }

                $legacy = $this->model->newQuery()
                    ->where('cliente_uif_id', $cid)
                    ->whereNull('anita_inropremioid')
                    ->where('fechaentrega', $data['fechaentrega'] ?? null)
                    ->where('monto', $data['monto'] ?? null)
                    ->when($salaId > 0, fn ($q) => $q->where('sala_id', $salaId))
                    ->orderBy('id')
                    ->first();
                if ($legacy !== null) {
                    return $this->actualizarPremioExistente($legacy, $data);
                }

                return $this->model->create($data);
            }

            $manual = $this->buscarPremioManualDuplicado($cid, $data, $salaId);
            if ($manual !== null) {
                return $this->actualizarPremioExistente($manual, $data);
            }

            return $this->model->create($data);
        });
	}

    /**
     * Alta manual: mismo cliente + fecha + monto + tito + posición (+ sala) = el mismo premio.
     * Evita el doble POST del formulario (Guardar dos veces / doble clic).
     */
    private function buscarPremioManualDuplicado(int $clienteUifId, array $data, int $salaId): ?Cliente_Premio_Uif
    {
        $fecha = $data['fechaentrega'] ?? null;
        $monto = $data['monto'] ?? null;
        if ($fecha === null || $fecha === '' || $monto === null || $monto === '') {
            return null;
        }

        $q = $this->model->newQuery()
            ->where('cliente_uif_id', $clienteUifId)
            ->whereNull('anita_inropremioid')
            ->where('fechaentrega', $fecha)
            ->where('monto', $monto)
            ->when($salaId > 0, fn ($query) => $query->where('sala_id', $salaId));

        $this->aplicarIgualdadTextoOVacio($q, 'numerotito', $data['numerotito'] ?? null);
        $this->aplicarIgualdadTextoOVacio($q, 'posicion', $data['posicion'] ?? null);

        return $q->orderBy('id')->first();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<Cliente_Premio_Uif>  $query
     */
    private function aplicarIgualdadTextoOVacio($query, string $columna, $valor): void
    {
        $texto = is_string($valor) ? trim($valor) : (string) ($valor ?? '');
        if ($texto === '') {
            $query->where(function ($q) use ($columna) {
                $q->whereNull($columna)->orWhere($columna, '');
            });

            return;
        }

        $query->where($columna, $texto);
    }

    private function actualizarPremioExistente(Cliente_Premio_Uif $existente, array $data): Cliente_Premio_Uif
    {
        $fotoAnterior = (string) ($existente->foto ?? '');
        $existente->update($data);
        $fotoNueva = (string) ($existente->fresh()->foto ?? '');
        if ($fotoAnterior !== '' && $fotoNueva !== $fotoAnterior) {
            ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused($fotoAnterior);
        }

        return $existente->fresh();
    }

    public function update(array $data, $id)
    {
		return self::guardarCliente_Premio_Uif($data, 'update', $id);
    }

	public function updateUnique(array $data, $id)
    {
		$cliente_premio_uif = $this->model->findOrFail($id);
		$cliente_premio_uif->update($data);

		return $cliente_premio_uif->fresh();
    }

    public function delete($id)
    {
        return $this->model->where('id', $id)->delete();
    }

    public function find($id)
    {
        if (null == $cliente_premio_uif = $this->model->with('clientes_uif')->with('cliente_premio_archivos_uif')->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_uif;
    }

    public function findOrFail($id)
    {
        if (null == $cliente_premio_uif = $this->model->with('clientes_uif')->with('cliente_premio_archivos_uif')->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cliente_premio_uif;
    }

	private function guardarCliente_Premio_Uif($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cliente_premio_uif = $this->model->where('cliente_uif_id', $id)->get()->pluck('id')->toArray();
			$q_cliente_premio_uif = count($cliente_premio_uif);
		}

		// Graba premios
		if (isset($data))
		{
			$premio_ids = $data['idpremios'];
			$sala_ids = $data['sala_ids'];
			$juego_uif_ids = $data['juego_uif_ids'];
			$fechaEntregas = $data['fechaentregas'];
			$detalles = $data['detalles'];
			$montos = $data['montos'];
			$moneda_ids = $data['moneda_ids'];
			$posiciones = $data['posiciones'];
			$numeroTitos = $data['numerotitos'];
			$fechaTitos = $data['fechatitos'];
			$formapago_ids = $data['formapago_ids'];
			$piderecibopagos = $data['piderecibopagos'];
			$creousuario_ids = $data['creousuario_premio_ids'];

			if ($funcion == 'update')
			{
				$_id = $cliente_premio_uif;

				// Borra los que sobran
				if ($q_cliente_premio_uif > count($premio_ids))
				{
					for ($d = count($premio_ids); $d < $q_cliente_premio_uif; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cliente_premio_uif && $i < count($premio_ids); $i++)
				{
					if ($i < count($premio_ids))
					{
						$cliente_premio_uif = $this->model->findOrFail($_id[$i])->update([
									"cliente_uif_id" => $id,
									"premio_id" => $premio_ids[$i],
									"sala_id" => $sala_ids[$i],
									"juego_uif_id" => $juego_uif_ids[$i],
									"fechaentrega" => $fechaEntregas[$i],
									"detalle" => $detalles[$i],
									"monto" => $montos[$i],
									"moneda_id" => $moneda_ids[$i],
									"posicion" => $posiciones[$i],
									"numerotito" => $numeroTitos[$i],
									"fechatito" => $fechaTitos[$i],
									"formapago_id" => $formapago_ids[$i],
									"piderecibopago" => $piderecibopagos[$i],
									"creousuario_id" => $creousuario_ids[$i]
									]);
					}
				}
				if ($q_cliente_premio_uif > count($premio_ids))
					$i = $d; 
			}
			else
				$i = 0;

			for ($i_movimiento = $i; $i_movimiento < count($premio_ids); $i_movimiento++)
			{
				if ($premio_ids[$i_movimiento] != '') 
				{
					$cliente_premio_uif = $this->model->create([
						"cliente_uif_id" => $id,
						"premio_id" => $premio_ids[$i_movimiento],
						"sala_id" => $sala_ids[$i_movimiento],
						"juego_uif_id" => $juego_uif_ids[$i_movimiento],
						"fechaentrega" => $fechaEntregas[$i_movimiento],
						"detalle" => $detalles[$i_movimiento],
						"monto" => $montos[$i_movimiento],
						"moneda_id" => $moneda_ids[$i_movimiento],
						"posicion" => $posiciones[$i_movimiento],
						"numerotito" => $numeroTitos[$i_movimiento],
						"fechatito" => $fechaTitos[$i_movimiento],
						"formapago_id" => $formapago_ids[$i_movimiento],
						"piderecibopago" => $piderecibopagos[$i_movimiento],
						"creousuario_id" => $creousuario_ids[$i_movimiento]						
						]);
				}
			}
		}
		else
		{
			$cliente_premio_uif = $this->model->where('cliente_uif_id', $id)->delete();
		}

		return $cliente_premio_uif;
	}

	public function listaPremioParaExportar($periodo, $limiteinformeuif, $empresaId = null)
	{
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

		$fecha = conviertePeriodoEnRangoFecha($periodo, true);
		$desdeFecha = $fecha['desdefecha'];
		$hastaFecha = $fecha['hastafecha'];
		$limite = (float) $limiteinformeuif;

        $cliente_premio_uifs = $this->model->select(
                                        'cliente_premio_uif.id as premioid',
										'cliente_premio_uif.monto as monto',
										'cliente_premio_uif.fechaentrega as fechaentrega',
										'cliente_premio_uif.posicion as posicion',
										'cliente_premio_uif.created_at as fechaalta',
										'cliente_uif.id as clienteid',
                                        'cliente_uif.inroclienteid as inroclienteid',
                                        'cliente_uif.nombre as nombrecliente',
                                        'cliente_uif.cuit as cuit',
                                        'cliente_uif.fechanacimiento as fechanacimiento',
                                        'cliente_uif.sexo as sexo',
                                        'cliente_uif.codigopostal as codigopostal',
                                        'cliente_uif.estado as estado',
                                        'cliente_uif.resideexterior as resideexterior',
                                        'cliente_uif.resideparaisofiscal as resideparaisofiscal',
                                        'cliente_uif.actividad_uif_id as actividad_uif_id',
                                        'cliente_uif.pais_uif_id as pais_uif_id',
                                        'tipodocumento.abreviatura as abreviaturatipodocumento',
                                        'tipodocumento.nombre as nombretipodocumento',
                                        'cliente_uif.numerodocumento as numerodocumento',
                                        'cliente_uif.domicilio as domicilio',
										'cliente_uif.piso as piso',
										'cliente_uif.departamento as departamento',
                                        'localidad_uif.nombre as nombrelocalidad',
                                        'provincia_uif.nombre as nombreprovincia',
										'pais_uif.nombre as nombrepais',
                                        'loc_nac.nombre as nombrelocalidadnacimiento',
                                        'pais_nac.nombre as nombrepaisnacimiento',
                                        'estadocivil_uif.nombre as nombreestadocivil',
                                        'pep_uif.nombre as nombrepep',
                                        'so_uif.nombre as nombreso',
                                        'moneda.nombre as nombremoneda',
                                        'juego_uif.nombre as nombrejuego',
                                        'usuario_alta.nombre as nombreusuarioalta',
										'cliente_uif.telefono as telefono',
                                        'cliente_uif.email as email',
										'sala.nombre as nombresala',
                                        'empresa.id as empresaid',
                                        'empresa.nombre as nombreempresa')
								->join('cliente_uif', 'cliente_uif.id', '=', 'cliente_premio_uif.cliente_uif_id')
								->join('tipodocumento', 'tipodocumento.id', '=', 'cliente_uif.tipodocumento_id')
                                ->leftJoin('localidad_uif', 'localidad_uif.id', '=', 'cliente_uif.localidad_uif_id')
                                ->leftJoin('provincia_uif', 'provincia_uif.id', '=', 'cliente_uif.provincia_uif_id')
								->leftJoin('pais_uif', 'pais_uif.id', '=', 'cliente_uif.pais_uif_id')
                                ->leftJoin('localidad_uif as loc_nac', 'loc_nac.id', '=', 'cliente_uif.localidadnacimiento_id')
                                ->leftJoin('pais_uif as pais_nac', 'pais_nac.id', '=', 'cliente_uif.paisnacimiento_id')
                                ->leftJoin('estadocivil_uif', 'estadocivil_uif.id', '=', 'cliente_uif.estadocivil_uif_id')
                                ->leftJoin('pep_uif', 'pep_uif.id', '=', 'cliente_uif.pep_uif_id')
                                ->leftJoin('so_uif', 'so_uif.id', '=', 'cliente_uif.so_uif_id')
                                ->leftJoin('moneda', 'moneda.id', '=', 'cliente_premio_uif.moneda_id')
                                ->leftJoin('juego_uif', 'juego_uif.id', '=', 'cliente_premio_uif.juego_uif_id')
                                ->leftJoin('usuario as usuario_alta', 'usuario_alta.id', '=', 'cliente_premio_uif.creousuario_id')
                                ->leftJoin('sala', 'sala.id', '=', 'cliente_premio_uif.sala_id')
                                ->leftJoin('empresa', 'empresa.id', '=', 'sala.empresa_id')
								->whereNull('cliente_premio_uif.deleted_at')
								->whereNull('cliente_uif.deleted_at')
								->where('cliente_premio_uif.monto', '>=', $limite)
								->when($empresaId, function ($query, $empresaId) {
                                    $query->where('empresa.id', (int) $empresaId);
                                })
								->whereBetween('cliente_premio_uif.fechaentrega', [$desdeFecha, $hastaFecha])
                                ->orderBy('cliente_premio_uif.fechaentrega', 'ASC')
                                ->orderBy('cliente_premio_uif.id', 'ASC')
                                ->get();
                                
		return $cliente_premio_uifs;
	}
}
