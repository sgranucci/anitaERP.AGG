<?php
namespace App\Services\Stock;

use App\Repositories\Stock\ArticuloRepositoryInterface;
use App\Repositories\Stock\MovimientoStockRepositoryInterface;
use App\Services\Stock\Articulo_MovimientoService;
use App\Services\Stock\Surmar\MovimientoStockSurmarEtiquetaService;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Repositories\Ventas\PedidoRepositoryInterface;
use App\Repositories\Ventas\Pedido_ArticuloRepositoryInterface;
use App\Models\Stock\Depmae;
use App\Models\Stock\Talle;
use App\Models\Stock\MovimientoStock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Repositories\Stock\Articulo_Saldo_DepositoRepositoryInterface;
use App\Support\Stock\ArticuloEmpresaAsignacionSupport;
use App\Support\Stock\ArticuloPrecioMovimientoStockSupport;
use App\Support\Stock\AltaNpuMovimientoStockSupport;
use App\Support\Stock\BajaNpuMovimientoStockSupport;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
use App\Support\Stock\MovimientoStockFerliSupport;
use App\Support\Stock\MovimientoStockSalidaSaldoSupport;
use App\Support\Stock\RecuentoBloqueoSalidaDepositoSupport;
use Auth;
use DB;
use Illuminate\Support\Facades\Log;

class MovimientoStockService 
{
	protected $movimientostockRepository;
	protected $tipotransaccionStockRepository;
	protected $articulo_movimientoService;
	protected $articuloRepository;
	protected $pedidoRepository;
	protected $pedido_articuloRepository;

    public function __construct(
        MovimientoStockRepositoryInterface $movimientostockrepository,
        ArticuloRepositoryInterface $articulorepository,
        Articulo_MovimientoService $articulo_movimientoservice,
        Tipotransaccion_StockRepositoryInterface $tipotransaccionstockrepository,
        PedidoRepositoryInterface $pedidoRepository,
        Pedido_ArticuloRepositoryInterface $pedido_articuloRepository,
        private MovimientoStockAsientoService $asientoService,
        private Articulo_Saldo_DepositoRepositoryInterface $saldoDepositoRepository,
    ) {
        $this->movimientostockRepository = $movimientostockrepository;
		$this->articuloRepository = $articulorepository;
		$this->articulo_movimientoService = $articulo_movimientoservice;
		$this->tipotransaccionStockRepository = $tipotransaccionstockrepository;
		$this->pedidoRepository = $pedidoRepository;
		$this->pedido_articuloRepository = $pedido_articuloRepository;
    }

	public function estadoEnum()
	{
		return $this->movimientostockRepository->estadoEnum(); 
	}

	public function all()
	{
        return $this->movimientostockRepository->leeMovimientoStock(
            \App\Support\Stock\MovimientoStockListadoFiltros::filtrosVacios(),
            false
        );
	}

	public function leeMovimientoStockListado($filtros, bool $paginar = false)
	{
        return $this->movimientostockRepository->leeMovimientoStock($filtros, $paginar);
	}

	public function leeMovimientoStock($id)
	{
        $movimientostock = $this->movimientostockRepository->find($id);

        if (! \App\Support\Stock\MovimientoStockVisibilidadSupport::movimientoAccesible($movimientostock)) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Movimiento de stock no encontrado');
        }

		return $movimientostock;
	}

	public function guardaMovimientoStock($data, $funcion, $id = null)
	{
	  	ini_set('memory_limit', '512M');

		$estadoEnum = Self::estadoEnum();
		$data['estado'] = array_search('Activa', $estadoEnum);
		$data['usuario_id'] = Auth::user()->id;
		$data['descuentointegrado'] = ' ';

		if (!array_key_exists('leyenda',$data))
			$data['leyenda'] = ' ';

		$movimientostock_id = null;
		$asientoIdNuevo = null;
		$ctamovNuevo = null;
		$ctamovSincronizadoEnEdicion = false;
		$movimientoIdCtamovResync = null;

		DB::beginTransaction();
		try 
		{
			$this->assertPeriodoContableStock($data);

			$tipotransaccionStockId = $this->resolveTipotransaccionStockId($data);
			$data['tipotransaccion_stock_id'] = $tipotransaccionStockId;

			$tipotransaccion = $this->tipotransaccionStockRepository->find($tipotransaccionStockId);

			if (!$tipotransaccion)
				throw new \Exception('No puede leer tipo de transacción de stock');

			BajaNpuMovimientoStockSupport::validarAntesDeGrabar($data, $tipotransaccion);
			BajaNpuMovimientoStockSupport::normalizarLineasParaGrabar($data, $tipotransaccion);
			AltaNpuMovimientoStockSupport::validarAntesDeGrabar($data, $tipotransaccion);
			AltaNpuMovimientoStockSupport::normalizarLineasParaGrabar($data, $tipotransaccion);

			$signoCantidadMovimiento = $data['signo_cantidad'] ?? $tipotransaccion->signo;

			if (empty($data['omitir_validacion_recuento_abierto'])) {
				RecuentoBloqueoSalidaDepositoSupport::assertSalidaPermitida(
					(int) ($data['deposito_id'] ?? 0),
					isset($data['fecha']) ? (string) $data['fecha'] : null,
					is_string($signoCantidadMovimiento)
						? $signoCantidadMovimiento
						: (string) ($tipotransaccion->signo ?? ''),
				);
			}

			$existente = null;
			if ($funcion === 'update' && $id) {
				$existente = $this->leeMovimientoStock($id);
			}

			if (! $this->omitirAsientoContable($data)) {
				$this->asientoService->assertCuadreAntesDeGrabar($data, $tipotransaccion, $existente);
			}

			if ($funcion == 'create')
			{
				// Si no existe el codigo numera secuencial
				if (!isset($data['codigo']))
				{
					$movimientostock = $this->movimientostockRepository->latest('id');

					if ($data['lote'] == 'LOTE DE ALTA')
					{
						$lote = 500000;
						if ($movimientostock)
							$lote = $movimientostock->id+500000;
						$data['lote'] = $lote + 1;
					}
					
					$id = 0;
					if ($movimientostock)
						$id = $movimientostock->id;

					$data['codigo'] = $id + 1;
				}

				// Guarda maestro de movimientostocks 
				$movimientostock = $this->movimientostockRepository->create($data);

				// Guarda remito en pendmae Anita
				if (strtoupper(config('app.empresa') == 'EL BIERZO') && substr($data['codigo'],0,3) == 'REM')
				{
					$this->pedidoRepository->guardarPedidoEnAnita($data);
				}
			}
			else
			{
				// Actualiza maestro de movimientostocks
				$movimientostock = $this->movimientostockRepository->update($data, $id);
			}

			// Guarda items
			if ($movimientostock)
			{
				$movimientostock_id = ($funcion == 'update' ? $id : $movimientostock->id);

				// Borra los registros de movimientos antes de grabar nuevamente
				if ($funcion == 'update')
				{
					// Surmar: revertir piqueo (consumos/hijas) antes de borrar líneas AM
					app(MovimientoStockSurmarEtiquetaService::class)
						->revertirEtiquetasPorMovimientos([(int) $movimientostock_id]);
					$this->articulo_movimientoService->deletePorMovimientoStockId($movimientostock_id);
				}
				$articulos = $this->normalizarArrayLineasFormulario($data['articulos_id'] ?? []);
				$skus = [];
				if (isset($data['skus']))
					$skus = $this->normalizarArrayLineasFormulario($data['skus']);
				$combinaciones = $this->normalizarArrayLineasFormulario($data['combinaciones_id'] ?? []);
				$modulos = $this->normalizarArrayLineasFormulario($data['modulos_id'] ?? []);
				$numeroitems = $data['items'] ?? count($articulos);
				$cantidades = $this->normalizarArrayLineasFormulario($data['cantidades'] ?? []);
				$cajas = $this->normalizarArrayLineasFormulario($data['cajas'] ?? array_fill(0, count($articulos), 0));
				$piezas = $this->normalizarArrayLineasFormulario($data['piezas'] ?? array_fill(0, count($articulos), 0));
				$precios = $this->normalizarArrayLineasFormulario($data['precios'] ?? []);
				$listaprecios = $this->normalizarArrayLineasFormulario($data['listasprecios_id'] ?? []);
				$incluyeimpuestos = $this->normalizarArrayLineasFormulario($data['incluyeimpuestos'] ?? []);
				$monedas = $this->normalizarArrayLineasFormulario($data['monedas_id'] ?? []);
				$descuentos = $this->normalizarArrayLineasFormulario($data['descuentos'] ?? []);
				$loteids = $this->normalizarArrayLineasFormulario($data['loteids'] ?? []);
				$medidas = $this->normalizarArrayLineasFormulario($data['medidas'] ?? []);
				$numeropartes = $this->normalizarArrayLineasFormulario($data['numeropartes'] ?? []);
				$colores = $this->normalizarArrayLineasFormulario($data['colores_id'] ?? []);
				$talles = $this->normalizarArrayLineasFormulario($data['talles_id'] ?? []);
				$fechaPrecio = ! empty($data['fecha']) ? \Carbon\Carbon::parse($data['fecha']) : \Carbon\Carbon::today();

				if (! MovimientoStockFerliSupport::esCalzadosFerli()) {
					MovimientoStockColorTalleExclusividadSupport::validarLineas($articulos, $colores, $talles);
				}

				if (
					empty($data['omitir_validacion_saldo'])
					&& MovimientoStockSalidaSaldoSupport::esSignoRestaStock($signoCantidadMovimiento)
				) {
					MovimientoStockSalidaSaldoSupport::validarDesdeLineasFormulario(
						(int) ($data['deposito_id'] ?? 0),
						$articulos,
						$cantidades,
						$this->saldoDepositoRepository,
						$colores,
						$talles,
					);
				}

				// Graba items
				$dataArticuloMovimiento = [];
				for ($i = 0; $i < count($articulos); $i++)
				{
					$articuloId = (int) ($articulos[$i] ?? 0);
					$cantidadLinea = (float) ($cantidades[$i] ?? 0);
					if ($articuloId <= 0 && abs($cantidadLinea) < 1e-9) {
						continue;
					}

					$articulo = $articuloId > 0 ? $this->articuloRepository->find($articuloId) : null;

					$codigoCategoria = ''; $sku = '';
					if ($articulo)
					{
						$sku = $articulo->sku;
						$codigoCategoria = optional($articulo->categorias)->codigo ?? '';
					}
					$combinacion = null;
					if (isset($combinaciones[$i]))
						$combinacion = $combinaciones[$i];
					$modulo = null;
					if (isset($modulos[$i]))
						$modulo = $modulos[$i];

					$precioLinea = (float) str_replace(',', '', (string) ($precios[$i] ?? 0));
					$forzarUltimaCompra = (bool) ($tipotransaccion->baja_npu ?? false)
						|| (bool) ($tipotransaccion->alta_npu ?? false);
					if (($precioLinea <= 0 || $forzarUltimaCompra) && (int) $articulos[$i] > 0) {
						$datoPrecio = ArticuloPrecioMovimientoStockSupport::resolverParaLinea(
							(int) $articulos[$i],
							$tipotransaccion,
							$fechaPrecio
						);
						$precioLinea = (float) ($datoPrecio['precio'] ?? 0);
						if (empty($listaprecios[$i]) && ! empty($datoPrecio['listaprecio_id'])) {
							$listaprecios[$i] = $datoPrecio['listaprecio_id'];
						}
						if (empty($monedas[$i]) && ! empty($datoPrecio['moneda_id'])) {
							$monedas[$i] = $datoPrecio['moneda_id'];
						}
						if (($incluyeimpuestos[$i] ?? '') === '' && $datoPrecio['incluyeimpuesto'] !== null) {
							$incluyeimpuestos[$i] = $datoPrecio['incluyeimpuesto'];
						}
					}

					$dataArticuloMovimiento = [
						'fecha' => $data['fecha'],
						'fechajornada' => $data['fecha'],
						'signo_cantidad' => $signoCantidadMovimiento,
						'tipotransaccion_stock_id' => $tipotransaccionStockId,
						'movimientostock_id' => $movimientostock_id,
						'deposito_id' => ! empty($data['deposito_id']) ? $data['deposito_id'] : null,
						'bien_uso_id' => ! empty($data['bien_uso_id']) ? (int) $data['bien_uso_id'] : null,
						'venta_id' => null,
						'pedido_combinacion_id' => null,
						'ordentrabajo_id' => null,
						'lote' => $data['lote'],
						'articulo_id' => $articulos[$i],
						'color_id' => (($c = (int) ($colores[$i] ?? 0)) > 0) ? $c : null,
						'talle_id' => (($t = (int) ($talles[$i] ?? 0)) > 0) ? $t : null,
						'numeroparte' => ($np = trim((string) ($numeropartes[$i] ?? ''))) !== '' ? $np : null,
						'sku' => $sku,
						'combinacion_id' => $combinacion,
						'modulo_id' => $modulo,
						'concepto' => $tipotransaccion->nombre,
						'cantidad' => $cantidades[$i],
						'caja' => $cajas[$i],
						'pieza' => $piezas[$i],
						'precio' => $precioLinea,
						'costo' => ArticuloPrecioMovimientoStockSupport::usaPrecioVenta($tipotransaccion)
							? 0
							: $precioLinea,
						'descuento' => $descuentos[$i],
						'descuentopie' => 0,
						'descuentointegrado' => null,
						'moneda_id' => $monedas[$i],
						'incluyeimpuesto' => $incluyeimpuestos[$i],
						'listaprecio_id' => $listaprecios[$i],
						'loteimportacion_id' => $data['loteimportacion_id'],
						'categoria' => $codigoCategoria,
						'codigo' => $data['codigo'],
						'letra' => $data['letra'] ?? '',
						'puntoventa' => $data['puntoventa'] ?? '',
						'numerocomprobante' => $data['numerocomprobante'] ?? '',
						'item' => $i,
						'codigocliente' => $data['codigocliente'] ?? '',
						'codigotransporte' => $data['codigotransporte'] ?? '',
						'codigovendedor' => $data['codigovendedor'] ?? '',
						'codigozonavta' => $data['codigozona'] ?? '',
						'codigoprovincia' => $data['codigoprovincia'] ?? '',
						'codigosubzona' => '',
						'codigocombinacion' => '',
						'pedido' => $data['pedido'] ?? '',
						'partida' => 0,
						'empresa' => $data['empresa'] ?? config('app.empresa')
					];

					$dataTalle = [];
					$medidasJson = trim((string) ($medidas[$i] ?? ''));
					if ($medidasJson !== '') {
						$jtalles = json_decode($medidasJson);
						if (is_array($jtalles)) {
							foreach ($jtalles as $medida) {
								$dataTalle[] = [
									'id' => null,
									'talle_id' => $medida->talle_id,
									'cantidad' => $medida->cantidad * ($signoCantidadMovimiento == 'S' ? 1 : -1),
									'precio' => $precioLinea,
								];
							}
						}
					}
					
					$articulo_movimiento = $this->articulo_movimientoService->
									guardaArticuloMovimiento('create',
									$dataArticuloMovimiento, $dataTalle);

					$empresaIdArticulo = (int) ($data['empresa_id'] ?? 0);
					if ($empresaIdArticulo <= 0 && ! empty($data['deposito_id'])) {
						$empresaIdArticulo = (int) (Depmae::query()->whereKey((int) $data['deposito_id'])->value('empresa_id') ?? 0);
					}
					if ($empresaIdArticulo > 0) {
						ArticuloEmpresaAsignacionSupport::asignarSiVacia((int) $articulos[$i], $empresaIdArticulo);
					}

					// Guarda remito en pendmae Anita
					//if (strtoupper(config('app.empresa') == 'EL BIERZO') && substr($data['codigo'],0,3) == 'REM')
					//{
				//		$this->pedido_articuloRepository->guardarPedidoEnAnita($dataArticuloMovimiento,
				//					$articulos[$i], 0, $i,
	  			//					0, $cantidades[$i], $precios[$i], $listaprecios[$i], $incluyeimpuestos[$i], 
	  			//					$monedas[$i], $descuentos[$i], '', $data['leyendafactura'], 'create');
				//	}									
				}

				$resultadoAsiento = $this->omitirAsientoContable($data)
					? [
						'asiento_id_nuevo' => null,
						'ctamov_nuevo' => null,
						'ctamov_sincronizado_edicion' => false,
					]
					: $this->sincronizarAsientoContable($movimientostock_id, $tipotransaccion, $data);
				$asientoIdNuevo = $resultadoAsiento['asiento_id_nuevo'] ?? null;
				$ctamovNuevo = $resultadoAsiento['ctamov_nuevo'] ?? null;
				$ctamovSincronizadoEnEdicion = (bool) ($resultadoAsiento['ctamov_sincronizado_edicion'] ?? false);
				$movimientoIdCtamovResync = $ctamovSincronizadoEnEdicion ? $movimientostock_id : null;

				if (in_array($funcion, ['create', 'update'], true) && $movimientostock_id > 0) {
					if ($funcion === 'create') {
						BajaNpuMovimientoStockSupport::procesarDespuesDeGrabar(
							(int) $movimientostock_id,
							$data,
							$tipotransaccion,
						);
						AltaNpuMovimientoStockSupport::procesarDespuesDeGrabar(
							(int) $movimientostock_id,
							$data,
							$tipotransaccion,
						);
					}

					$movFresh = MovimientoStock::query()->find($movimientostock_id);
					if ($movFresh) {
						$surmarStats = app(MovimientoStockSurmarEtiquetaService::class)
							->procesarDespuesDeGrabar($movFresh, $tipotransaccion, $data, $funcion);
						$data['_surmar_etiquetas'] = $surmarStats;
					}
				}
			}

			DB::commit();

			$asientoIdNuevo = null;
			$ctamovNuevo = null;
			$ctamovSincronizadoEnEdicion = false;
			$movimientoIdCtamovResync = null;
		} catch (\Throwable $e) 
		{
			if ($asientoIdNuevo > 0) {
				try {
					$stub = new MovimientoStock(['id' => $movimientostock_id ?? 0]);
					$stub->asiento_id = $asientoIdNuevo;
					$this->asientoService->anularAsiento($stub);
				} catch (\Throwable $rollbackAsiento) {
					report($rollbackAsiento);
					if ($ctamovNuevo) {
						try {
							$this->asientoService->revertirCtamovAnita(
								(int) $ctamovNuevo['empresa_id'],
								(string) $ctamovNuevo['numeroasiento']
							);
						} catch (\Throwable $rollbackCtamov) {
							report($rollbackCtamov);
						}
					}
				}
			}

			DB::rollBack();

			if ($ctamovSincronizadoEnEdicion && $movimientoIdCtamovResync) {
				try {
					$movResync = MovimientoStock::query()
						->with([
							'asientos',
							'tipotransaccion_stock',
							'articulos_movimiento.articulos.articulo_cuentacontables',
						])
						->find($movimientoIdCtamovResync);
					if ($movResync && (int) ($movResync->asiento_id ?? 0) > 0) {
						$this->asientoService->sincronizarCtamovAnitaMovimiento($movResync);
					}
				} catch (\Throwable $resyncCtamov) {
					Log::warning('MovimientoStock: no se pudo resincronizar ctamov Anita tras rollback', [
						'movimientostock_id' => $movimientoIdCtamovResync,
						'mensaje' => $resyncCtamov->getMessage(),
					]);
				}
			}

			throw $e;
		}
		
		$resultado = ['id' => $movimientostock_id, 'codigo' => $data['codigo']];
		$npusGenerados = $data['_npus_generados_alta'] ?? null;
		if (is_array($npusGenerados) && $npusGenerados !== []) {
			$resultado['npus_generados'] = array_values(array_map('intval', $npusGenerados));
			$resultado['mensaje'] = 'Movimiento de stock creado con éxito. NPUs generados: '
				.implode(', ', $resultado['npus_generados']).'.';
		}
		$surmar = $data['_surmar_etiquetas'] ?? null;
		if (is_array($surmar) && (int) ($surmar['consumos'] ?? 0) > 0) {
			$resultado['surmar_etiquetas'] = $surmar;
			$resultado['surmar_hijas_ids'] = array_values(array_map('intval', $surmar['hijas_ids'] ?? []));
			$extra = ' Surmar: '.$surmar['consumos'].' etiqueta(s) consumida(s)';
			if ((int) ($surmar['etiquetas_hijas'] ?? 0) > 0) {
				$extra .= ', '.$surmar['etiquetas_hijas'].' etiqueta(s) nueva(s)';
			}
			$extra .= '.';
			$resultado['mensaje'] = ($resultado['mensaje'] ?? 'Movimiento de stock creado con éxito').$extra;
		}

		return $resultado;
	}

	private function resolveTipotransaccionStockId(array $data): int
	{
		if (! empty($data['tipotransaccion_stock_id'])) {
			return (int) $data['tipotransaccion_stock_id'];
		}

		if (! empty($data['tipotransaccion_id'])) {
			return (int) $data['tipotransaccion_id'];
		}

		throw new \Exception('Debe indicar tipo de transacción de stock');
	}

	public function borraMovimientoStock($id)
	{
		DB::beginTransaction();
		try {
			$movimiento = $this->leeMovimientoStock($id);
			if ((int) ($movimiento->asiento_id ?? 0) > 0) {
				$this->asientoService->anularAsiento($movimiento);
			}

			app(MovimientoStockSurmarEtiquetaService::class)
				->revertirEtiquetasPorMovimientos([(int) $id]);

			$this->articulo_movimientoService->deletePorMovimientoStockId($id);
			$movimientostock = $this->movimientostockRepository->deletePorId($id);

			DB::commit();

			return $movimientostock;
		} catch (\Throwable $e) {
			DB::rollBack();
			throw $e;
		}
	}

	/**
	 * @return array{
	 *   asiento_id_nuevo: int|null,
	 *   ctamov_nuevo: array{empresa_id: int, numeroasiento: string}|null,
	 *   ctamov_sincronizado_edicion: bool
	 * }
	 */
	private function sincronizarAsientoContable(int $movimientostockId, Tipotransaccion_Stock $tipo, array $data): array
	{
		$resultado = [
			'asiento_id_nuevo' => null,
			'ctamov_nuevo' => null,
			'ctamov_sincronizado_edicion' => false,
		];

		$movimiento = MovimientoStock::query()
			->with([
				'tipotransaccion_stock',
				'articulos_movimiento.articulos.articulo_cuentacontables',
			])
			->findOrFail($movimientostockId);

		$ccDestino = (int) ($data['centrocosto_destino_id'] ?? 0);
		if ($ccDestino > 0) {
			$movimiento->centrocosto_destino_id = $ccDestino;
			$movimiento->save();
		}

		if (! $this->asientoService->debeGenerarAsiento($tipo)) {
			if ((int) ($movimiento->asiento_id ?? 0) > 0) {
				$this->asientoService->anularAsiento($movimiento);
				$movimiento->update(['asiento_id' => null]);
			}

			return $resultado;
		}

		$this->asientoService->assertCuadreMovimiento($movimiento);

		$asientoId = (int) ($movimiento->asiento_id ?? 0);
		if ($asientoId > 0) {
			$this->asientoService->recuadrarAsientoExistente($movimiento->fresh([
				'asientos',
				'tipotransaccion_stock',
				'articulos_movimiento.articulos.articulo_cuentacontables',
			]));
			$resultado['ctamov_sincronizado_edicion'] = true;

			return $resultado;
		}

		$nuevoAsientoId = $this->asientoService->generarAsiento($movimiento);
		if ($nuevoAsientoId > 0) {
			$movimiento->update(['asiento_id' => $nuevoAsientoId]);
			$movimiento->loadMissing('asientos');
			$asiento = $movimiento->asientos;
			$resultado['asiento_id_nuevo'] = $nuevoAsientoId;
			if ($asiento) {
				$resultado['ctamov_nuevo'] = [
					'empresa_id' => (int) $asiento->empresa_id,
					'numeroasiento' => (string) $asiento->numeroasiento,
				];
			}
		}

		return $resultado;
	}

	private function omitirAsientoContable(array $data): bool
	{
		return ! empty($data['omitir_asiento_contable']);
	}

	private function assertPeriodoContableStock(array $data): void
	{
		$empresaId = (int) ($data['empresa_id'] ?? 0);
		if ($empresaId <= 0 && ! empty($data['deposito_id'])) {
			$empresaId = (int) (Depmae::query()->whereKey((int) $data['deposito_id'])->value('empresa_id') ?? 0);
		}

		if ($empresaId <= 0 || empty($data['fecha'])) {
			return;
		}

		PeriodoContableCierreSupport::assertOperacionPermitida(
			$empresaId,
			(string) $data['fecha'],
			$this->resolverAlcanceCierreStock($data)
		);
	}

	/**
	 * Indumentaria (EIND) valida bajo sueldos; el resto bajo movimientos de stock.
	 */
	private function resolverAlcanceCierreStock(array $data): string
	{
		$alcance = (string) ($data['alcance_cierre_contable'] ?? '');
		if ($alcance !== '' && PeriodoContableCierreSupport::alcanceEsValido($alcance)) {
			return $alcance;
		}

		$tipoId = 0;
		if (! empty($data['tipotransaccion_stock_id'])) {
			$tipoId = (int) $data['tipotransaccion_stock_id'];
		} elseif (! empty($data['tipotransaccion_id'])) {
			$tipoId = (int) $data['tipotransaccion_id'];
		}

		if ($tipoId > 0) {
			$abrev = strtoupper((string) (Tipotransaccion_Stock::query()->whereKey($tipoId)->value('abreviatura') ?? ''));
			if ($abrev === 'EIND') {
				return PeriodoContableCierreSupport::ALCANCE_INDUMENTARIA;
			}
		}

		return PeriodoContableCierreSupport::ALCANCE_STOCK;
	}

	/**
	 * @return list<mixed>
	 */
	private function normalizarArrayLineasFormulario(mixed $valor): array
	{
		if (is_array($valor)) {
			return $valor;
		}

		if ($valor === null || $valor === '') {
			return [];
		}

		return [$valor];
	}


}
