<?php

namespace App\Repositories\Caja;

use App\Models\Caja\Cheque;
use App\Models\Caja\Cuentacaja;
use App\Models\Caja\Estadocheque_Banco;
use App\Models\Contable\Cuentacontable;
use App\Models\Configuracion\Empresa;
use App\Support\Caja\ChequeAnitaSyncSupport;
use App\Support\Caja\ChequeListadoFiltros;
use App\Support\Caja\ChequePropioAnitaNumeracionSupport;
use App\Support\Caja\ChequePropioCpromaeAnitaMapper;
use App\Support\Caja\ChequePropioImputacionSupport;
use App\Support\Caja\ChequePropioInstrumentoSupport;
use App\Support\Caja\ChequeTerceroCtermaeAnitaMapper;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;
use App\Repositories\Caja\BancoRepositoryInterface;
use App\Repositories\Caja\CuentacajaRepositoryInterface;
use App\Repositories\Caja\Estadocheque_BancoRepositoryInterface;
use App\Repositories\Caja\ChequeraRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Configuracion\TipodocumentoRepositoryInterface;
use App\Repositories\Ventas\ClienteRepositoryInterface;
use App\ApiAnita;
use DB;
use Carbon\Carbon;
use Exception;

class ChequeRepository implements ChequeRepositoryInterface
{
    protected $model;
    protected $tableAnita = 'cpromae';
    protected $keyField = 'codigo';
    protected $keyFieldAnita = ['cpro_cuenta', 'cpro_nro_cheque', 'cpro_fecha_cheque'];

	private $bancoRepository;
    private $cuentacajaRepository;
    private $proveedorRepository;
    private $empresaRepository;
    private $tipodocumentoRepository;
    private $estadocheque_bancoRepository;
    private $chequeraRepository;
    private $clienteRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Cheque $cheque,
                                CuentacajaRepositoryInterface $cuentacajarepository,
                                ProveedorRepositoryInterface $proveedorrepository,
                                EmpresaRepositoryInterface $empresarepository,
                                ChequeraRepositoryInterface $chequerarepository,
                                TipodocumentoRepositoryInterface $tipodocumentorepository,
                                BancoRepositoryInterface $bancorepository,
                                Estadocheque_BancoRepositoryInterface $estadocheque_bancorepository,
                                ClienteRepositoryInterface $clienterepository)
    {
        $this->model = $cheque;
        $this->cuentacajaRepository = $cuentacajarepository;
        $this->proveedorRepository = $proveedorrepository;
        $this->empresaRepository = $empresarepository;
        $this->chequeraRepository = $chequerarepository;
        $this->tipodocumentoRepository = $tipodocumentorepository;
        $this->bancoRepository = $bancorepository;
        $this->estadocheque_bancoRepository = $estadocheque_bancorepository;
        $this->clienteRepository = $clienterepository;
    }

    public function all()
    {
        $this->asegurarSyncInicialDesdeAnita();

        $query = $this->model->with('empresas')
            ->with('cuentacajas')
            ->with('bancos')
            ->with('tipodocumentos')
            ->with('proveedores')
            ->with('clientes')
            ->with('monedas')
            ->with('cajas')
            ->with('chequeras')
            ->orderByDesc('fechapago')
            ->orderByDesc('id');

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query);

        return $query->get();
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection<int, Cheque>
     */
    public function leeCheque($filtros, bool $flPaginando = true)
    {
        $this->asegurarSyncInicialDesdeAnita();

        if (is_string($filtros)) {
            $texto = trim($filtros);
            $filtros = array_merge(ChequeListadoFiltros::filtrosVacios(), [
                'modo' => ChequeListadoFiltros::MODO_TODOS,
                'campo' => 'numerocheque',
                'operador' => 'contiene',
                'valor' => $texto,
                'valor_hasta' => '',
                'busqueda' => $texto,
                'empresa_scope' => 'todas',
            ]);
        } elseif (! is_array($filtros)) {
            $filtros = ChequeListadoFiltros::filtrosVacios();
        }

        $query = $this->model->select('cheque.*')
            ->leftJoin('banco', 'banco.id', '=', 'cheque.banco_id')
            ->leftJoin('empresa', 'empresa.id', '=', 'cheque.empresa_id')
            ->leftJoin('cliente', 'cliente.id', '=', 'cheque.cliente_id')
            ->leftJoin('moneda', 'moneda.id', '=', 'cheque.moneda_id')
            ->with(['empresas', 'bancos', 'clientes', 'monedas', 'cuentacajas']);

        $this->empresaRepository->aplicarFiltroEmpresasAsignadas($query, 'cheque.empresa_id');

        ChequeListadoFiltros::aplicar($query, $filtros);

        $query->orderByDesc('cheque.fechapago')
            ->orderByDesc('cheque.id');

        if ($flPaginando) {
            return $query->paginate(15);
        }

        return $query->get();
    }

    /**
     * Primera carga: CHP (cpromae) y CHT (ctermae) por separado si falta cada origen.
     */
    private function asegurarSyncInicialDesdeAnita(): void
    {
        try {
            if (! $this->model->newQuery()->where('origen', 'E')->exists()) {
                $this->sincronizarCpromaeConAnita();
            }
            if (! $this->model->newQuery()->where('origen', 'R')->exists()) {
                $this->sincronizarCtermaeConAnita();
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function create(array $data)
    {
        $cheque = $this->model->create($data);

        // Graba anita
        $anita = self::guardarAnita($data);


        return($cheque);
    }

    public function update(array $data, $id)
    {
        $cheque = $this->model->findOrFail($id)->update($data);

        // Actualiza anita
        $anita = self::actualizarAnita($data, $data['codigo']);


        return($cheque);
    }

    public function delete($id)
    {
   	    $cheque = $this->model->find($id);
        		
	    // Elimina anita
	    $anita = self::eliminarAnita($cheque->origen, $cheque->cuenta_cajas->codigo, $cheque->codigo);

        $cheque = $this->model->destroy($id);


        return $cheque;
    }

    public function find($id)
    {
        if (null == $cheque = $this->model->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('cajas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cheque;
    }

    public function findOrFail($id)
    {
        if (null == $cheque = $this->model->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('cajas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return $cheque;
    }

    public function findPorNumeroCheque($codigo)
    {
        return $this->model->where('numerocheque', $codigo)->with('empresas')
                            ->with('cuentacajas')
                            ->with('bancos')
                            ->with('tipodocumentos')
                            ->with('proveedores')
                            ->with('clientes')
                            ->with('monedas')
                            ->with('chequeras')
                            ->with('caja_movimientos')
                            ->with('cajas')->get();
    }

	public function guardarChequeCobranza($data, $funcion, $id = null)
	{
		if ($funcion == 'update')
		{
			// Trae todos los id
        	$cheque = $this->model->where('cobranza_id', $id)->get()->pluck('id')->toArray();
			$q_cheque = count($cheque);
		}

		// Graba cuentas contables
		if (isset($data['cheque_ids']))
		{
            $cheque_ids = $data['cheque_ids'];
			$fechapagos = $data['fechapagos'];
			$banco_ids = $data['banco_ids'];
			$numerocheques = $data['numerocheques'];
			$cotizacioncheques = $data['cotizacioncheques'];
			$sucursalpagos = $data['sucursalpagos'];
            $cuentalibradoras = $data['cuentalibradoras'];
            $monedacheque_ids = $data['monedacheque_ids'];
			$montocheques = $data['montocheques'];

			if ($funcion == 'update')
			{
				$_id = $cheque;

				// Borra los que sobran
				if ($q_cheque > count($cheque_ids))
				{
					for ($d = count($cheque_ids); $d < $q_cheque; $d++)
						$this->model->find($_id[$d])->delete();
				}

				// Actualiza los que ya existian
				for ($i = 0; $i < $q_cheque && $i < count($cheque_ids); $i++)
				{
					if ($i < count($cheque_ids))
					{
						$cheque = $this->model->findOrFail($_id[$i])->update([
									"cobranza_id" => $id,
                                    'origen' => 'R',
                                    'fechaemision' => $data['fecha'],
                                    'fechapago' => $fechapagos[$i],
                                    'empresa_id' => $data['empresa_id'],
                                    'caja_id' => $data['caja_id'],
                                    'numerocheque' => $numerocheques[$i],
                                    'moneda_id' => $monedacheque_ids[$i],
                                    'monto' => $montocheques[$i],
                                    'cotizacion' => $cotizacioncheques[$i],
                                    'cliente_id' => $data['cliente_id'],
                                    'sucursalpago' => $sucursalpagos[$i],
                                    'banco_id' => $banco_ids[$i],
                                    'cuentalibradora' => $cuentalibradoras[$i]
									]);
					}
				}
				if ($q_cheque > count($cheque_ids))
					$i = $d; 
			}
			else
				$i = 0;
			for ($i_movimiento = $i; $i_movimiento < count($cheque_ids); $i_movimiento++)
			{
				if ($monedacheque_ids[$i_movimiento] != '') 
                {
                    $cheque = $this->model->create([
                                "cobranza_id" => $id,
                                'origen' => 'R',
                                'fechaemision' => $data['fecha'],
                                'fechapago' => $fechapagos[$i_movimiento],
                                'empresa_id' => $data['empresa_id'],
                                'caja_id' => $data['caja_id'],
                                'numerocheque' => $numerocheques[$i_movimiento],
                                'moneda_id' => $monedacheque_ids[$i_movimiento],
                                'monto' => $montocheques[$i_movimiento],
                                'cotizacion' => $cotizacioncheques[$i_movimiento],
                                'cliente_id' => $data['cliente_id'],
                                'sucursalpago' => $sucursalpagos[$i_movimiento],
                                'banco_id' => $banco_ids[$i_movimiento],
                                'cuentalibradora' => $cuentalibradoras[$i_movimiento]
                                ]);
				}
			}
		}
		else
		{
			$cheque = EloquentAuditDeleteSupport::each(
				$this->model->newQuery()->where('cobranza_id', $id)
			);
		}
		return $cheque;
	}

    /**
     * @param  array<string, mixed>  $data
     */
    public function guardarChequeIngresoEgreso(array $data, string $funcion, int $cajaMovimientoId)
    {
        $idsPersistidos = [];

        $fechaOperacion = (string) ($data['fecha'] ?? date('Y-m-d'));
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        $cajaId = isset($data['caja_id']) ? (int) $data['caja_id'] : null;

        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasEmitidos($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );
        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasRecibidos($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );
        $idsPersistidos = array_merge(
            $idsPersistidos,
            $this->persistirFilasReemplazo($data, $funcion, $cajaMovimientoId, $fechaOperacion, $empresaId, $cajaId)
        );

        if ($funcion === 'update') {
            $aDesvincular = $this->model->query()
                ->where('caja_movimiento_id', $cajaMovimientoId)
                ->whereNull('cobranza_id')
                ->when(count($idsPersistidos) > 0, fn ($q) => $q->whereNotIn('id', $idsPersistidos))
                ->when(count($idsPersistidos) === 0, fn ($q) => $q)
                ->get();

            foreach ($aDesvincular as $chequeQuitar) {
                if (! empty($chequeQuitar->nro_interno_anita)) {
                    // Vuelve a cartera; no borrar el valor de terceros.
                    $chequeQuitar->update([
                        'caja_movimiento_id' => null,
                        'pagoproveedor_id' => null,
                        'caja_id' => null,
                    ]);
                } else {
                    $chequeQuitar->delete();
                }
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasEmitidos(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['numerocheque_emitidos']) || ! is_array($data['numerocheque_emitidos'])) {
            return $ids;
        }

        $chequeIds = $data['cheque_emitido_ids'] ?? [];
        $chequeraIds = $data['chequera_emitido_ids'] ?? [];
        $cuentacajaIds = $data['cuentacaja_emitido_ids'] ?? [];
        $numeros = $data['numerocheque_emitidos'];
        $fechasPago = $data['fechapago_emitidos'] ?? [];
        $monedaIds = $data['moneda_emitido_ids'] ?? [];
        $montos = $data['montocheque_emitidos'] ?? [];
        $cotizaciones = $data['cotizacioncheque_emitidos'] ?? [];
        $caracteres = $data['caracter_emitidos'] ?? [];
        $paraDeps = $data['para_dep_emitidos'] ?? [];
        $negociables = $data['negociable_emitidos'] ?? [];
        $nrosEcheq = $data['nro_echeq_emitidos'] ?? [];
        $fechasEntrega = $data['fecha_entrega_emitidos'] ?? [];
        $anombrede = $data['anombrede_emitidos'] ?? [];
        $proveedorIds = $data['proveedor_emitido_ids'] ?? [];

        foreach ($numeros as $i => $numero) {
            $numero = trim((string) $numero);
            if ($numero === '' || (float) ($montos[$i] ?? 0) <= 0) {
                continue;
            }

            $cuentacajaId = (int) ($cuentacajaIds[$i] ?? 0);
            $cuentacaja = $this->cuentacajaRepository->find($cuentacajaId);
            $bancoId = (int) ($cuentacaja->banco_id ?? 0);
            if ($bancoId <= 0) {
                throw new Exception('La cuenta de caja del cheque emitido no tiene banco asociado.');
            }

            $fechaPago = (string) ($fechasPago[$i] ?? $fechaOperacion);
            $chequeraId = ($chequeraIds[$i] ?? '') !== '' ? (int) $chequeraIds[$i] : null;
            $chequera = $chequeraId ? $this->chequeraRepository->find($chequeraId) : null;
            $negociable = ChequePropioInstrumentoSupport::negociable(
                (string) ($negociables[$i] ?? ''),
                (string) ($chequera->tipochequera
                    ?? ChequePropioInstrumentoSupport::negociableDefault())
            );
            $nroCheque = $numero;
            $payload = [
                'origen' => 'E',
                'chequera_id' => $chequeraId,
                'caracter' => ($caracteres[$i] ?? '') !== ''
                    ? (string) $caracteres[$i]
                    : ChequePropioInstrumentoSupport::caracterDefault(),
                'para_dep' => ChequePropioInstrumentoSupport::paraDep(
                    (string) ($paraDeps[$i] ?? ''),
                    ChequePropioInstrumentoSupport::paraDepDefault()
                ),
                'negociable' => $negociable,
                'estado' => ChequePropioImputacionSupport::estadoInicialEmitido($fechaOperacion, $fechaPago),
                'fechaemision' => $fechaOperacion,
                'fechapago' => $fechaPago,
                'fecha_entrega' => (string) ($fechasEntrega[$i] ?? '') ?: null,
                'cuentacaja_id' => $cuentacajaId,
                'empresa_id' => $empresaId,
                'caja_id' => $cajaId,
                'caja_movimiento_id' => $cajaMovimientoId,
                'numerocheque' => $nroCheque,
                'nro_echeq' => ChequePropioInstrumentoSupport::nroEcheq(
                    $negociable,
                    (string) ($nrosEcheq[$i] ?? $nroCheque)
                ) ?: null,
                'moneda_id' => (int) ($monedaIds[$i] ?? 1),
                'cotizacion' => ChequePropioCpromaeAnitaMapper::cotizacion((float) ($cotizaciones[$i] ?? 1)),
                'monto' => (float) ($montos[$i] ?? 0),
                'proveedor_id' => ($proveedorIds[$i] ?? '') !== '' ? (int) $proveedorIds[$i] : null,
                'anombrede' => (string) ($anombrede[$i] ?? ''),
                'banco_id' => $bancoId,
            ];

            $chequeId = (int) ($chequeIds[$i] ?? 0);
            if ($funcion === 'update' && $chequeId > 0) {
                $this->model->findOrFail($chequeId)->update($payload);
                $ids[] = $chequeId;
            } else {
                $cheque = $this->model->create($payload);
                $ids[] = (int) $cheque->id;
            }
        }

        ChequePropioAnitaNumeracionSupport::actualizarDesdeFilasEmitidas($data);

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasRecibidos(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['numerocheque_recibidos']) || ! is_array($data['numerocheque_recibidos'])) {
            return $ids;
        }

        $chequeIds = $data['cheque_recibido_ids'] ?? [];
        $nrosInternos = $data['nro_interno_anita_recibidos'] ?? [];
        $fechasPago = $data['fechapago_recibidos'] ?? [];
        $bancoIds = $data['banco_recibido_ids'] ?? [];
        $numeros = $data['numerocheque_recibidos'];
        $sucursales = $data['sucursalpago_recibidos'] ?? [];
        $cuentasLib = $data['cuentalibradora_recibidos'] ?? [];
        $monedaIds = $data['monedacheque_recibido_ids'] ?? [];
        $montos = $data['montocheque_recibidos'] ?? [];
        $cotizaciones = $data['cotizacioncheque_recibidos'] ?? [];

        foreach ($numeros as $i => $numero) {
            $numero = trim((string) $numero);
            if ($numero === '' || (float) ($montos[$i] ?? 0) <= 0) {
                continue;
            }

            $nroInterno = (int) ($nrosInternos[$i] ?? 0);
            $chequeId = (int) ($chequeIds[$i] ?? 0);
            if ($chequeId <= 0 && $nroInterno > 0) {
                $porInterno = $this->model->newQuery()
                    ->where('origen', 'R')
                    ->where('nro_interno_anita', $nroInterno)
                    ->first();
                if ($porInterno) {
                    $chequeId = (int) $porInterno->id;
                }
            }

            $payload = [
                'origen' => 'R',
                'caracter' => 'R',
                'estado' => ' ',
                'fechaemision' => $fechaOperacion,
                'fechapago' => (string) ($fechasPago[$i] ?? $fechaOperacion),
                'empresa_id' => $empresaId,
                'caja_id' => $cajaId,
                'caja_movimiento_id' => $cajaMovimientoId,
                'numerocheque' => $numero,
                'nro_interno_anita' => $nroInterno > 0 ? $nroInterno : null,
                'moneda_id' => (int) ($monedaIds[$i] ?? 1),
                'monto' => (float) ($montos[$i] ?? 0),
                'cotizacion' => (float) ($cotizaciones[$i] ?? 1),
                'sucursalpago' => (string) ($sucursales[$i] ?? ''),
                'banco_id' => (int) ($bancoIds[$i] ?? 0),
                'cuentalibradora' => (string) ($cuentasLib[$i] ?? ''),
                'cliente_id' => isset($data['cliente_id']) ? (int) $data['cliente_id'] : null,
                'proveedor_id' => isset($data['proveedor_id']) ? (int) $data['proveedor_id'] : null,
            ];

            if ($payload['banco_id'] <= 0) {
                throw new Exception('Debe indicar banco en cheque recibido / de cartera.');
            }

            if ($chequeId > 0) {
                $existente = $this->model->findOrFail($chequeId);
                if (empty($payload['nro_interno_anita']) && ! empty($existente->nro_interno_anita)) {
                    $payload['nro_interno_anita'] = (int) $existente->nro_interno_anita;
                }
                if (empty($payload['cliente_id']) && ! empty($existente->cliente_id)) {
                    $payload['cliente_id'] = (int) $existente->cliente_id;
                }
                // Conservar fecha emisión original de cartera (ingreso).
                if (! empty($existente->fechaemision)) {
                    $payload['fechaemision'] = $existente->fechaemision instanceof \DateTimeInterface
                        ? $existente->fechaemision->format('Y-m-d')
                        : (string) $existente->fechaemision;
                }
                $existente->update($payload);
                $ids[] = $chequeId;
            } else {
                $cheque = $this->model->create($payload);
                $ids[] = (int) $cheque->id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private function persistirFilasReemplazo(
        array $data,
        string $funcion,
        int $cajaMovimientoId,
        string $fechaOperacion,
        int $empresaId,
        ?int $cajaId
    ): array {
        $ids = [];
        if (! isset($data['cheque_anulado_ids']) || ! is_array($data['cheque_anulado_ids'])) {
            return $ids;
        }

        $anulados = $data['cheque_anulado_ids'];
        $origenReemplazo = $data['origen_reemplazo'] ?? [];
        $numerosReemplazo = $data['numerocheque_reemplazo'] ?? [];
        $montosReemplazo = $data['montocheque_reemplazo'] ?? [];
        $monedaReemplazo = $data['moneda_reemplazo_ids'] ?? [];
        $cotizReemplazo = $data['cotizacioncheque_reemplazo'] ?? [];
        $fechasReemplazo = $data['fechapago_reemplazo'] ?? [];
        $cuentacajaReemplazo = $data['cuentacaja_reemplazo_ids'] ?? [];
        $chequeraReemplazo = $data['chequera_reemplazo_ids'] ?? [];
        $bancoReemplazo = $data['banco_reemplazo_ids'] ?? [];

        foreach ($anulados as $i => $anuladoId) {
            $anuladoId = (int) $anuladoId;
            if ($anuladoId <= 0) {
                continue;
            }

            $anulado = $this->model->find($anuladoId);
            if ($anulado === null) {
                throw new Exception('Cheque a anular no encontrado (id '.$anuladoId.').');
            }

            $anulado->estado = 'A';
            $anulado->save();

            $montoReemplazo = (float) ($montosReemplazo[$i] ?? $anulado->monto);
            $numeroReemplazo = trim((string) ($numerosReemplazo[$i] ?? ''));
            if ($numeroReemplazo === '' || $montoReemplazo <= 0) {
                continue;
            }

            $tipoReemplazo = strtoupper((string) ($origenReemplazo[$i] ?? 'E'));
            $fechaPago = (string) ($fechasReemplazo[$i] ?? $fechaOperacion);

            if ($tipoReemplazo === 'E') {
                $cuentacajaId = (int) ($cuentacajaReemplazo[$i] ?? $anulado->cuentacaja_id ?? 0);
                $cuentacaja = $this->cuentacajaRepository->find($cuentacajaId);
                $chequeraId = ($chequeraReemplazo[$i] ?? '') !== '' ? (int) $chequeraReemplazo[$i] : $anulado->chequera_id;
                $chequera = $chequeraId ? $this->chequeraRepository->find($chequeraId) : null;
                $negociable = ChequePropioInstrumentoSupport::negociable(
                    (string) ($anulado->negociable ?? ''),
                    (string) ($chequera->tipochequera
                        ?? ChequePropioInstrumentoSupport::negociableDefault())
                );
                $payload = [
                    'origen' => 'E',
                    'chequera_id' => $chequeraId,
                    'caracter' => $anulado->caracter
                        ?: ChequePropioInstrumentoSupport::caracterDefault(),
                    'para_dep' => ChequePropioInstrumentoSupport::paraDep(
                        (string) ($anulado->para_dep ?? ''),
                        ChequePropioInstrumentoSupport::paraDepDefault()
                    ),
                    'negociable' => $negociable,
                    'estado' => ChequePropioImputacionSupport::estadoInicialEmitido($fechaOperacion, $fechaPago),
                    'fechaemision' => $fechaOperacion,
                    'fechapago' => $fechaPago,
                    'fecha_entrega' => $anulado->fecha_entrega,
                    'cuentacaja_id' => $cuentacajaId,
                    'empresa_id' => $empresaId,
                    'caja_id' => $cajaId,
                    'caja_movimiento_id' => $cajaMovimientoId,
                    'cheque_reemplaza_id' => $anuladoId,
                    'numerocheque' => $numeroReemplazo,
                    'nro_echeq' => ChequePropioInstrumentoSupport::nroEcheq($negociable, $numeroReemplazo) ?: null,
                    'moneda_id' => (int) ($monedaReemplazo[$i] ?? $anulado->moneda_id),
                    'monto' => $montoReemplazo,
                    'cotizacion' => ChequePropioCpromaeAnitaMapper::cotizacion((float) ($cotizReemplazo[$i] ?? $anulado->cotizacion)),
                    'proveedor_id' => $anulado->proveedor_id,
                    'anombrede' => $anulado->anombrede,
                    'banco_id' => (int) ($cuentacaja->banco_id ?? $anulado->banco_id),
                ];
            } else {
                $payload = [
                    'origen' => 'R',
                    'caracter' => 'R',
                    'estado' => ' ',
                    'fechaemision' => $fechaOperacion,
                    'fechapago' => $fechaPago,
                    'empresa_id' => $empresaId,
                    'caja_id' => $cajaId,
                    'caja_movimiento_id' => $cajaMovimientoId,
                    'cheque_reemplaza_id' => $anuladoId,
                    'numerocheque' => $numeroReemplazo,
                    'moneda_id' => (int) ($monedaReemplazo[$i] ?? $anulado->moneda_id),
                    'monto' => $montoReemplazo,
                    'cotizacion' => (float) ($cotizReemplazo[$i] ?? $anulado->cotizacion),
                    'banco_id' => (int) ($bancoReemplazo[$i] ?? $anulado->banco_id),
                    'sucursalpago' => $anulado->sucursalpago,
                    'cuentalibradora' => $anulado->cuentalibradora,
                ];
            }

            $cheque = $this->model->create($payload);
            $ids[] = (int) $cheque->id;
        }

        return $ids;
    }

    public function sincronizarConAnita()
    {
        $this->sincronizarCpromaeConAnita();
        $this->sincronizarCtermaeConAnita();
    }

    public function sincronizarCpromaeConAnita(): void
    {
        ini_set('max_execution_time', '300');
        $fechaDesde = ChequeAnitaSyncSupport::fechaDesdeSyncAnios(2);
        foreach (ChequeAnitaSyncSupport::listarCpromaeAbiertos($fechaDesde) as $fila) {
            try {
                $this->importarFilaCpromae($fila);
            } catch (\Throwable $e) {
                $this->logSyncOmitida('Cheque sync cpromae: fila omitida', [
                    'cuenta' => $fila->cpro_cuenta ?? null,
                    'nro' => $fila->cpro_nro_cheque ?? null,
                    'fecha' => $fila->cpro_fecha_cheque ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function sincronizarCtermaeConAnita(bool $soloCartera = false): void
    {
        ini_set('max_execution_time', '600');
        $anios = (int) config('cheque.sync_anios', 5);
        $fechaDesde = ChequeAnitaSyncSupport::fechaDesdeSyncAnios($anios);
        $filas = $soloCartera
            ? ChequeAnitaSyncSupport::listarCtermaeEnCartera($fechaDesde)
            : ChequeAnitaSyncSupport::listarCtermaeTodos($fechaDesde);

        foreach ($filas as $fila) {
            try {
                $this->importarFilaCtermae($fila);
            } catch (\Throwable $e) {
                $this->logSyncOmitida('Cheque sync ctermae: fila omitida', [
                    'nro_interno' => $fila->cter_nro_interno ?? null,
                    'nro' => $fila->cter_nro_cheque ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function logSyncOmitida(string $mensaje, array $ctx): void
    {
        try {
            Log::warning($mensaje, $ctx);
        } catch (\Throwable) {
            // storage/logs no escribible por el usuario CLI: no abortar el sync.
        }
    }

    public function traerRegistroDeAnita($key1, $key2, $key3)
    {
        $apiAnita = new ApiAnita();
        $where = ' WHERE '.$this->keyFieldAnita[0]." = '".$key1."' AND ".
                    $this->keyFieldAnita[1]." = '".$key2."' AND ".
                    $this->keyFieldAnita[2]." = '".$key3."' ";
        $camposBase = '
                    cpro_cuenta,
                    cpro_nro_cheque,
                    cpro_fecha_cheque,
                    cpro_fecha_emision,
                    cpro_importe,
                    cpro_proveedor,
                    cpro_entregado_a,
                    cpro_nro_op,
                    cpro_cod_mon,
                    cpro_cotizacion,
                    cpro_estado,
                    cpro_contrapartida,
                    cpro_fecha_anula,
                    cpro_fl_imprimio,
                    cpro_a_nombre_de,
                    cpro_modelo,
                    cpro_para_dep';
        $camposExtendidos = $camposBase.',
                    cpro_fecha_entrega,
                    cpro_empresa,
                    cpro_negociable,
                    cpro_estado_banco,
                    cpro_sucursal_pago,
                    cpro_tipo_distrib,
                    cpro_nro_e_cheq';

        $dataAnita = null;
        $intentos = EntornoEmpresaSupport::esFerli()
            ? [$camposBase]
            : [$camposExtendidos, $camposBase];
        foreach ($intentos as $campos) {
            $dataAnita = json_decode($apiAnita->apiCall([
                'acc' => 'list',
                'tabla' => $this->tableAnita,
                'sistema' => 'che_ban',
                'campos' => $campos,
                'whereArmado' => $where,
            ]));
            if (is_array($dataAnita) && count($dataAnita) > 0) {
                break;
            }
        }

        if (! is_array($dataAnita) || count($dataAnita) === 0) {
            return;
        }

        $this->importarFilaCpromae($dataAnita[0]);
    }

    /**
     * @param  object  $data
     */
    private function importarFilaCpromae($data): void
    {
        $estado = null;
        $fechaEmision = null;
        $fechaCheque = null;
        $cuentacaja_id = null;
        $empresa_id = null;
        $proveedor_id = null;
        $estadoChequeBanco_id = null;
        $chequera_id = null;
        $para_dep = null;
        $banco_id = null;
        $negociable = 'N';

        $this->convierteDatosDeAnita(
            $data,
            $estado,
            $fechaEmision,
            $fechaCheque,
            $cuentacaja_id,
            $empresa_id,
            $proveedor_id,
            $estadoChequeBanco_id,
            $chequera_id,
            $para_dep,
            $banco_id,
            $negociable
        );

        if ($empresa_id === null || (int) $empresa_id <= 0) {
            throw new Exception(
                'No se pudo resolver empresa_id para cheque Anita '
                .($data->cpro_nro_cheque ?? '').' cuenta '.($data->cpro_cuenta ?? '')
                .' cpro_empresa='.($data->cpro_empresa ?? '')
            );
        }

        $yaExiste = $this->model->newQuery()
            ->where('origen', 'E')
            ->where('numerocheque', $data->cpro_nro_cheque ?? null)
            ->where('cuentacaja_id', $cuentacaja_id)
            ->when($fechaCheque, fn ($q) => $q->whereDate('fechapago', $fechaCheque))
            ->exists();
        if ($yaExiste) {
            return;
        }

        $fechaEntregaYmd = ChequePropioCpromaeAnitaMapper::ymd((string) ($data->cpro_fecha_entrega ?? ''));
        $fechaEntrega = ($fechaEntregaYmd !== '0' && strlen($fechaEntregaYmd) === 8)
            ? substr($fechaEntregaYmd, 0, 4).'-'.substr($fechaEntregaYmd, 4, 2).'-'.substr($fechaEntregaYmd, 6, 2)
            : null;

        $this->model->create([
            'origen' => 'E',
            'chequera_id' => $chequera_id,
            'caracter' => 'O',
            'para_dep' => $para_dep,
            'negociable' => $negociable,
            'estado' => $estado,
            'fechaemision' => $fechaEmision,
            'fechapago' => $fechaCheque,
            'fecha_entrega' => $fechaEntrega,
            'cuentacaja_id' => $cuentacaja_id,
            'empresa_id' => $empresa_id,
            'caja_id' => null,
            'caja_movimiento_id' => null,
            'numerocheque' => $data->cpro_nro_cheque ?? null,
            'nro_echeq' => trim((string) ($data->cpro_nro_e_cheq ?? '')) ?: null,
            'moneda_id' => (int) ($data->cpro_cod_mon ?? 1) ?: 1,
            'monto' => $data->cpro_importe ?? 0,
            'cotizacion' => ChequePropioCpromaeAnitaMapper::cotizacion((float) ($data->cpro_cotizacion ?? 0)),
            'proveedor_id' => $proveedor_id,
            'cliente_id' => null,
            'tipodocumento_id' => null,
            'numerodocumento' => null,
            'entregado' => $data->cpro_entregado_a ?? null,
            'anombrede' => $data->cpro_a_nombre_de ?? null,
            'estadocheque_banco_id' => $estadoChequeBanco_id,
            'sucursalpago' => (($data->cpro_sucursal_pago ?? '') !== '0') ? ($data->cpro_sucursal_pago ?? null) : null,
            'tipodistribucion' => (($data->cpro_tipo_distrib ?? '') !== '0') ? ($data->cpro_tipo_distrib ?? null) : null,
            'banco_id' => $banco_id,
            'cuentalibradora' => null,
        ]);
    }

    /**
     * @param  object  $data
     */
    private function importarFilaCtermae($data): void
    {
        $nroInterno = (int) preg_replace('/\D/', '', (string) ($data->cter_nro_interno ?? '0'));
        if ($nroInterno <= 0) {
            throw new Exception('cter_nro_interno inválido');
        }

        $codigoCliente = ltrim((string) ($data->cter_cliente ?? ''), '0');
        $cliente = $codigoCliente !== '' ? $this->clienteRepository->findPorCodigo($codigoCliente) : null;

        $codigoProveedor = ltrim((string) ($data->cter_proveedor ?? ''), '0');
        $proveedor = $codigoProveedor !== '' ? $this->proveedorRepository->findPorCodigo($codigoProveedor) : null;

        $codigoBanco = ltrim((string) ($data->cter_cod_banco ?? ''), '0');
        $banco = $codigoBanco !== '' ? $this->bancoRepository->findPorCodigo($codigoBanco) : null;

        $empresaId = ChequeAnitaSyncSupport::resolverEmpresaId(
            $data->cter_empresa ?? null,
            null,
            fn ($codigo) => $this->empresaRepository->findPorCodigo($codigo),
            fn ($id) => $this->empresaRepository->find($id)
        );

        if ($empresaId === null || $empresaId <= 0) {
            throw new Exception(
                'No se pudo resolver empresa_id para CHT nro_interno='.$nroInterno
                .' cter_empresa='.($data->cter_empresa ?? '')
            );
        }

        $attrs = ChequeTerceroCtermaeAnitaMapper::aAtributosErp($data, [
            'empresa_id' => $empresaId,
            'cliente_id' => $cliente?->id,
            'proveedor_id' => $proveedor?->id,
            'banco_id' => $banco?->id,
        ]);

        // Depósito Anita → columnas ERP si existen.
        $fechaDep = ChequeTerceroCtermaeAnitaMapper::fechaAYMD($data->cter_fecha_dep ?? null);
        if ($fechaDep) {
            $attrs['fecha_deposito'] = $fechaDep;
        }
        $nroBoleta = trim((string) ($data->cter_nro_boleta ?? ''));
        if ($nroBoleta !== '' && $nroBoleta !== '0') {
            $attrs['nro_boleta_deposito'] = mb_substr($nroBoleta, 0, 40);
        }

        $existente = $this->model->newQuery()->where('nro_interno_anita', $nroInterno)->first();
        if ($existente) {
            // No pisar vínculos operativos ni ND ya emitida.
            unset($attrs['cobranza_id'], $attrs['pagoproveedor_id'], $attrs['venta_nd_id'], $attrs['fecha_rechazo'], $attrs['motivo_rechazo']);
            if ((int) ($existente->venta_nd_id ?? 0) > 0) {
                unset($attrs['estado']);
            }
            $existente->fill($attrs);
            $existente->save();

            return;
        }

        $this->model->create($attrs);
    }

    public function findPorNroInternoAnita(int $nroInterno): ?Cheque
    {
        if ($nroInterno <= 0) {
            return null;
        }

        return $this->model->newQuery()->where('nro_interno_anita', $nroInterno)->first();
    }

    public function vincularNroInternoAnita(int $chequeId, int $nroInterno): void
    {
        if ($chequeId <= 0 || $nroInterno <= 0) {
            return;
        }

        $this->model->newQuery()->whereKey($chequeId)->update([
            'nro_interno_anita' => $nroInterno,
        ]);
    }

	public function guardarAnita($request) {
        $apiAnita = new ApiAnita();
        
        // Verifica si fue emitido o recibido
        if ($request['origen'] == '1')
        {

        }
        else
        {
            Self::convierteDatosParaAnita($request, $codigo, $fechaCheque, $fechaEmision, $proveedor, $modelo, 
                                            $caracter, $empresa, $negociable, $estadoBanco, $numeroEcheq,
                                            $estado);

            $data = array( 'tabla' => $this->tableAnita, 'acc' => 'insert',
                'sistema' => 'che_ban',
                'campos' => ' 
                        cpro_cuenta,
                        cpro_nro_cheque,
                        cpro_fecha_cheque,
                        cpro_fecha_emision,
                        cpro_importe,
                        cpro_proveedor,
                        cpro_entregado_a,
                        cpro_nro_op,
                        cpro_cod_mon,
                        cpro_cotizacion,
                        cpro_estado,
                        cpro_contrapartida,
                        cpro_fecha_anula,
                        cpro_fl_imprimio,
                        cpro_a_nombre_de,
                        cpro_modelo,
                        cpro_para_dep',
                        //,
                        //cpro_fecha_entrega,
                        //cpro_empresa,
                        //cpro_negociable,
                        //cpro_estado_banco,
                        //cpro_sucursal_pago,
                        //cpro_tipo_distrib,
                        //cpro_nro_e_cheq
                    //',
                'valores' => " 
                    '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."', 
                    '".$request['numerocheque']."',
                    '".$fechaCheque."',
                    '".$fechaEmision."',
                    '".$request['monto']."',
                    '".str_pad($proveedor, 6, "0", STR_PAD_LEFT)."',
                    '".$request['entregado']."',
                    '0',
                    '".$request['moneda_id']."',
                    '".$request['cotizacion']."',
                    '".$estado."',
                    ' ',
                    '0',
                    ' ',
                    '".$request['anombrede']."',
                    '".$modelo."', 
                    '".$caracter."'"
                    //,
                    //'0',
                    //'".$empresa."',
                    //'".$negociable."',
                    //'".$estadoBanco."',
                    //'".$request['sucursalpago']."',
                    //'".$request['tipodistribucion']."',
                    //.".$numeroEcheq."'"
            );
        }
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function actualizarAnita($request, $id) {
        $apiAnita = new ApiAnita();

        // Verifica si fue emitido o recibido
        if ($request['origen'] == '1')
        {

        }
        else
        {        
            Self::convierteDatosParaAnita($request, $codigo, $fechaCheque, $fechaEmision, $proveedor, $modelo, 
                                            $caracter, $empresa, $negociable, $estadoBanco, $numeroEcheq,
                                            $estado);

            $data = array( 'acc' => 'update', 'tabla' => $this->tableAnita, 
                    'sistema' => 'che_ban',
                    'valores' => " 
                            cpro_fecha_cheque               = '".$fechaCheque."',
                            cpro_fecha_emision              = '".$fechaEmision."',
                            cpro_importe                    = '".$request['monto']."',
                            cpro_proveedor                  = '".str_pad($proveedor, 6, "0", STR_PAD_LEFT)."',
                            cpro_entregado_a                = '".$request['entregado']."',
                            cpro_cod_mon                    = '".$request['moneda_id']."',
                            cpro_cotizacion                 = '".$request['cotizacion']."',
                            cpro_estado                     = '".$estado."',
                            cpro_fecha_anula                = '".$fechaAnula."',,
                            cpro_a_nombre_de                = '".$request['anombrede']."',
                            cpro_modelo                     = '".$modelo."',
                            cpro_para_dep                   = '".$caracter."' "
                            //,
                            //cpro_empresa                    = '".$empresa."',
                            //cpro_negociable                 = '".$negociable."',
                            //cpro_estado_banco               = '".$estadoBanco."',
                            //cpro_sucursal_pago              = '".$request['sucursalpago']."',
                            //cpro_tipo_distrib               = '".$request['tipodistribucion']."',
                            //cpro_nro_e_cheq                 = '".$numeroEcheq."' "
                    ,
                    'whereArmado' => " WHERE cpro_cuenta = '".str_pad($codigo, 8, "0", STR_PAD_LEFT)."' AND
                                        cpro_nro_cheque = '".$request['numerocheque']."'");
        }
        $anita = $apiAnita->apiCallEscritura($data);

        return $anita;
	}

	public function eliminarAnita($origen, $cuenta, $numeroCheque) {
        $apiAnita = new ApiAnita();

        if ($origen == '1')
        {

        }
        else
        {
            $data = array( 'acc' => 'delete', 'tabla' => $this->tableAnita, 
                    'sistema' => 'che_ban',
                    'whereArmado' => " WHERE cpro_cuenta = '".str_pad($cuenta, 8, "0", STR_PAD_LEFT)."' AND
                                        cpro_nro_cheque = '".$numeroCheque."'");
        }
        $anita = $apiAnita->apiCallEscritura($data);
        
        return $anita;
	}

    private function convierteDatosDeAnita(
        $data,
        &$estado,
        &$fechaEmision,
        &$fechaCheque,
        &$cuentacaja_id,
        &$empresa_id,
        &$proveedor_id,
        &$estadoChequeBanco_id,
        &$chequera_id,
        &$para_dep = null,
        &$banco_id = null,
        &$negociable = null
    ) {
        $fechaEmision = $this->fechaAnitaAYMD($data->cpro_fecha_emision ?? null);
        $fechaCheque = $this->fechaAnitaAYMD($data->cpro_fecha_cheque ?? null);

        $estadoAnita = (string) ($data->cpro_estado ?? ' ');
        $estado = $estadoAnita !== '' ? $estadoAnita : ' ';

        $para_dep = ChequePropioInstrumentoSupport::paraDep(
            (string) ($data->cpro_para_dep ?? ''),
            ChequePropioInstrumentoSupport::paraDepDefault()
        );

        $negociableIn = strtoupper(trim((string) ($data->cpro_negociable ?? '')));
        $negociable = in_array($negociableIn, ['E', 'N'], true) ? $negociableIn : 'N';

        $codigoModelo = ltrim((string) ($data->cpro_modelo ?? ''), '0');
        $chequera = $codigoModelo !== ''
            ? $this->chequeraRepository->findPorCodigo($codigoModelo)
            : null;
        $chequera_id = $chequera ? $chequera->id : null;

        $codigoCuenta = ltrim((string) ($data->cpro_cuenta ?? ''), '0');
        $cuentacaja = $codigoCuenta !== ''
            ? $this->cuentacajaRepository->findPorCodigo($codigoCuenta)
            : null;
        $cuentacaja_id = $cuentacaja ? $cuentacaja->id : null;
        $banco_id = $cuentacaja && ! empty($cuentacaja->banco_id) ? (int) $cuentacaja->banco_id : null;

        $empresa_id = $this->resolverEmpresaIdDesdeAnita(
            $data->cpro_empresa ?? null,
            $cuentacaja
        );

        $codigoProveedor = ltrim((string) ($data->cpro_proveedor ?? ''), '0');
        $proveedor = $codigoProveedor !== ''
            ? $this->proveedorRepository->findPorCodigo($codigoProveedor)
            : null;
        $proveedor_id = $proveedor ? $proveedor->id : null;

        $codigoEstadoBanco = trim((string) ($data->cpro_estado_banco ?? ''));
        $estadocheque_banco = ($codigoEstadoBanco !== '' && $codigoEstadoBanco !== '0')
            ? Estadocheque_Banco::query()->where('codigoexterno', $codigoEstadoBanco)->first()
            : null;
        $estadoChequeBanco_id = $estadocheque_banco ? $estadocheque_banco->id : null;
    }

    /**
     * @param  mixed  $codigoEmpresaAnita
     * @param  mixed  $cuentacaja
     */
    private function resolverEmpresaIdDesdeAnita($codigoEmpresaAnita, $cuentacaja): ?int
    {
        return ChequeAnitaSyncSupport::resolverEmpresaId(
            $codigoEmpresaAnita,
            $cuentacaja,
            fn ($codigo) => $this->empresaRepository->findPorCodigo($codigo),
            fn ($id) => $this->empresaRepository->find($id)
        );
    }

    private function fechaAnitaAYMD($valor): ?string
    {
        $ymd = ChequePropioCpromaeAnitaMapper::ymd((string) ($valor ?? ''));
        if ($ymd === '0' || strlen($ymd) !== 8) {
            return null;
        }

        return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
    }

    private function convierteDatosParaAnita($data, &$codigo, &$fechaCheque, &$fechaEmision, &$proveedor, &$modelo, 
                                        &$caracter, &$empresa, &$negociable, &$estadoBanco, &$numeroEcheq,
                                        &$estado)
    {
        $cuentacaja = $this->cuentacajaRepository->find($data['cuentacaja_id']);
        if ($cuentacaja)
            $codigo = $cuentacaja->codigo;
        else
            $codigo = null;

        $fechaEmision = date('Ymd', $data['fechaemision']);
        $fechaCheque = date('Ymd', $data['fechacheque']);

        $proveedor = $this->proveedorRepository->find($data['proveedor_id']);
        if ($proveedor)
            $proveedor = $proveedor->codigo;
        else
            $proveedor = null;

        $caracter = ($data['caracter'] == 'N' ? 'S' : 'N');

        $empresa = $this->empresaRepository->find($data['empresa_id']);
        if ($empresa)
            $empresa = $empresa->codigo;
        else
            $empresa = null;

        $chequera = $this->chequeraRepository->find($data['chequera_id']);
        if ($chequera)
        {
            $modelo = $chequera->codigo;
            $tipoChequera = $chequera->tipochequera;
        }
        else
        {
            $modelo = null;
            $tipoChequera = null;
        }
        if ($tipoChequera == 'E')
            $caracter = 'E';
        else
            $caracter = ($data['caracter'] == 'N' ? 'S' : 'N');

        switch($tipoChequera)
        {
        case 'F': // Fisica
            $negociable = 'N';
            break;
        case 'E': // Electronica
            $negociable = 'E';
            break;
        }

        $estadocheque_banco = $this->estadocheque_bancoRepository->find($data['estadocheque_banco_id']);

        if ($estadocheque_banco)
            $estadoBanco = $estadocheque_banco->codigoexterno;
        else
            $estadoBanco = null;

        $numeroEcheq = '';
        if ($negociable == 'E')
            $numeroEcheq = $data['numerocheque'];

        switch($data['estado'])
        {
        case 'DIFERIDO':
            $estado = ' ';
            break;
        case 'DEBITADO':
            $estado = '*';
            break;
        case 'CIERRE':
            $estado = 'C';
            break;
        case 'ANULADO':
            $estado = 'A';
            break;
        case 'RECHAZADO':
            $estado = 'R';
            break;
        case 'NO_PRESENTADO':
            $estado = 'N';
            break;
        }
    }
}
