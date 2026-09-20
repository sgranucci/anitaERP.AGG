<?php
namespace App\Services\Stock;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use App\Repositories\Stock\Articulo_MovimientoRepositoryInterface;
use App\Repositories\Stock\Articulo_Movimiento_TalleRepositoryInterface;
use App\Repositories\Ventas\TipotransaccionRepositoryInterface;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Models\Ventas\Tipotransaccion;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Ventas\Ordentrabajo_TareaRepositoryInterface;
use App\Queries\Stock\Articulo_MovimientoQueryInterface;
use App\Models\Stock\Modulo;
use App\Models\Stock\Talle;
use App\Support\Stock\ArticuloMovimientoCantidadSignoSupport;
use App\Support\Stock\ReporteStockOtSituacionSupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Articulo_MovimientoService 
{
	protected $articulo_movimientoRepository;
	protected $articulo_movimiento_talleRepository;
	protected $tipotransaccionRepository;
	protected $tipotransaccionStockRepository;
	protected $articulo_movimientoQuery;
	protected $ordentrabajo_tareaRepository;

	public function __construct(
								Articulo_MovimientoRepositoryInterface $articulo_movimientorepository,
								Articulo_Movimiento_TalleRepositoryInterface $articulo_movimiento_tallerepository,
								TipotransaccionRepositoryInterface $tipotransaccionrepository,
								Tipotransaccion_StockRepositoryInterface $tipotransaccionstockrepository,
								Ordentrabajo_tareaRepositoryInterface $ordentrabajo_tarearepository,
								Articulo_MovimientoQueryInterface $articulo_movimientoquery
								)
    {
		$this->articulo_movimientoRepository = $articulo_movimientorepository;
		$this->articulo_movimiento_talleRepository = $articulo_movimiento_tallerepository;
		$this->articulo_movimientoQuery = $articulo_movimientoquery;
		$this->tipotransaccionRepository = $tipotransaccionrepository;
		$this->tipotransaccionStockRepository = $tipotransaccionstockrepository;
		$this->ordentrabajo_tareaRepository = $ordentrabajo_tarearepository;
    }
	
	public function guardaArticuloMovimiento($funcion, $dataMovimiento, $dataTalle)
	{
		$tipotransaccion = $this->resolveTipoTransaccion($dataMovimiento);
		if ($tipotransaccion)
		{
			$cantidadYaFirmada = ! empty($dataMovimiento['cantidad_ya_firmada']);
			unset($dataMovimiento['cantidad_ya_firmada']);

			if (! $cantidadYaFirmada) {
				$dataMovimiento['cantidad'] = $this->firmarCantidadMovimiento(
					(float) $dataMovimiento['cantidad'],
					$tipotransaccion,
					$dataMovimiento['signo_cantidad'] ?? null
				);
				unset($dataMovimiento['signo_cantidad']);
			} else {
				unset($dataMovimiento['signo_cantidad']);
			}

			unset($dataMovimiento['tipotransaccion_id'], $dataMovimiento['tipotransaccion_stock_id']);
			if ($tipotransaccion instanceof Tipotransaccion_Stock) {
				$dataMovimiento['tipotransaccion_stock_id'] = $tipotransaccion->id;
				$dataMovimiento['tipotransaccion_id'] = null;
			} else {
				$dataMovimiento['tipotransaccion_id'] = $tipotransaccion->id;
				$dataMovimiento['tipotransaccion_stock_id'] = null;
			}
			$dataMovimiento['precio'] = str_replace(',', '', $dataMovimiento['precio']);

			//if (!array_key_exists('deposito_id', $dataMovimiento))
			$bienUsoId = (int) ($dataMovimiento['bien_uso_id'] ?? 0);
			if ($bienUsoId > 0) {
				$dataMovimiento['bien_uso_id'] = $bienUsoId;
				if (! isset($dataMovimiento['deposito_id']) || (int) $dataMovimiento['deposito_id'] <= 0) {
					$dataMovimiento['deposito_id'] = null;
				}
			} elseif (! isset($dataMovimiento['deposito_id']) || $dataMovimiento['deposito_id'] == 0) {
				$dataMovimiento['deposito_id'] = 1;
			}
			if (isset($dataMovimiento['bien_uso_id']) && (int) $dataMovimiento['bien_uso_id'] <= 0) {
				$dataMovimiento['bien_uso_id'] = null;
			}
			if ($dataMovimiento['listaprecio_id'] == 'NaN')
				$dataMovimiento['listaprecio_id'] = null;
			if (! isset($dataMovimiento['listaprecio_id'])
				|| $dataMovimiento['listaprecio_id'] === ''
				|| (int) $dataMovimiento['listaprecio_id'] <= 0)
				$dataMovimiento['listaprecio_id'] = null;
			if ($dataMovimiento['moneda_id'] == 'NaN')
				$dataMovimiento['moneda_id'] = null;
			if ($dataMovimiento['incluyeimpuesto'] == 'NaN')
				$dataMovimiento['incluyeimpuesto'] = null;
			foreach ([
				'pedido_combinacion_id',
				'ordentrabajo_id',
				'modulo_id',
				'movimientostock_id',
				'pedido_articulo_id',
				'venta_emision_id',
				'loteimportacion_id',
				'combinacion_id',
			] as $fk) {
				if (! isset($dataMovimiento[$fk])
					|| $dataMovimiento[$fk] === ''
					|| (int) $dataMovimiento[$fk] <= 0) {
					$dataMovimiento[$fk] = null;
				}
			}

			unset($dataMovimiento['omitir_validacion_saldo']);

			$dataMovimiento = $this->filtrarDatosParaTablaArticuloMovimiento($dataMovimiento);

			$articulo_movimiento = $this->articulo_movimientoRepository->create($dataMovimiento);

			if (isset($anita['error']))
			{
				if ($anita['error'] == 'Error')
					throw new Exception('Error en grabacion anita. '.$anita['mensaje']);

				if ($anita['error'] == 'Errvend')
					throw new Exception('No tiene vendedor asignado.');
			}

			if ($articulo_movimiento)
			{
				if (isset($dataTalle))
				{
					foreach($dataTalle as $talle)
					{
						$data = [];
						$data['articulo_movimiento_id'] = $articulo_movimiento->id;
						$data['pedido_combinacion_talle_id'] = $talle['id'];
						$data['talle_id'] = $talle['talle_id'];
						$data['cantidad'] = $talle['cantidad'];
						$data['precio'] = str_replace(',', '', $talle['precio']);
						$this->guardaArticuloMovimientoTalle($dataMovimiento['pedido_combinacion_id'], $data);
					}
				}
			}
			else
			{
				throw new Exception('No pudo grabar movimiento de stock del articulo.');
			}
		}
		else
		{
			throw new Exception('No encontro tipo de transaccion.');
		}
	}

	// Actualiza movimiento por pedido_combinacion_id
	public function guardaArticuloMovimientoPorPedidoCombinacionId($pedido_combinacion_id, $data)
	{
		if (array_key_exists('cantidad', $data))
		{
			$articulo_movimiento = $this->articulo_movimientoRepository->findPorPedidoCombinacionId($pedido_combinacion_id);
		
			if ($articulo_movimiento)
			{
				$tipotransaccion = $this->resolveTipoTransaccionDesdeMovimiento($articulo_movimiento);

				$data['cantidad'] = $data['cantidad']*($tipotransaccion->signo == 'S' ? 1 : -1);
			}
		}
		return $this->articulo_movimientoRepository->updatePorPedidoCombinacionId($pedido_combinacion_id, $data);
	}

	// Guarda articulo_movimiento_talle
	public function guardaArticuloMovimientoTalle($pedido_combinacion_id, $data)
	{
		// Busca id de articulo_movimiento
		if (!array_key_exists('articulo_movimiento_id', $data))
		{
			$articulo_movimiento = $this->articulo_movimientoRepository->findPorPedidoCombinacionId($pedido_combinacion_id);

			// Lee tipo de transaccion
			$articulo_movimiento_talle = '';
			if ($articulo_movimiento)
			{
				$data['articulo_movimiento_id'] = $articulo_movimiento->id;

				$tipotransaccion = $this->resolveTipoTransaccionDesdeMovimiento($articulo_movimiento);
				if (array_key_exists('cantidad', $data) && $data['cantidad'] > 0)
					$data['cantidad'] = $data['cantidad']*($tipotransaccion->signo == 'S' ? 1 : -1);
				 
				$articulo_movimiento_talle = $this->articulo_movimiento_talleRepository->create($data);
			}
			else
				throw new Exception('No encontro movimiento en articulo_movimiento.');
		}
		else
		{
			$articulo_movimiento = $this->articulo_movimientoRepository->find($data['articulo_movimiento_id']);

			$tipotransaccion = $this->resolveTipoTransaccionDesdeMovimiento($articulo_movimiento);

			if (array_key_exists('cantidad', $data))
			{
				if ($data['cantidad'] > 0)
				{
					if ($tipotransaccion->signo == 'S')
						$data['cantidad'] = abs($data['cantidad']);
					else
						$data['cantidad'] = -$data['cantidad'];
				}
			}
			$articulo_movimiento_talle = $this->articulo_movimiento_talleRepository->create($data);
		}

		return $articulo_movimiento_talle;
	}

	/**
	 * @param  array<string, mixed>  $dataMovimiento
	 */
	private function resolveTipoTransaccion(array $dataMovimiento): Tipotransaccion|Tipotransaccion_Stock
	{
		if (! empty($dataMovimiento['tipotransaccion_stock_id'])) {
			return $this->tipotransaccionStockRepository->find((int) $dataMovimiento['tipotransaccion_stock_id']);
		}

		if (! empty($dataMovimiento['tipotransaccion_id'])) {
			$legacyId = (int) $dataMovimiento['tipotransaccion_id'];
			// Ferli / post-migración: CONOT/ALTAP viven en tipotransaccion_stock (mapa).
			$stockId = $this->tipotransaccionStockRepository->resolveIdFromLegacy($legacyId);
			try {
				return $this->tipotransaccionStockRepository->find($stockId);
			} catch (ModelNotFoundException $e) {
				// Entornos sin tipo stock equivalente: seguir con tipo ventas.
			}

			return $this->tipotransaccionRepository->find($legacyId);
		}

		throw new \Exception('No encontro tipo de transaccion.');
	}

	/**
	 * Firma cantidad: tipo stock expone S/R (accessor); formularios también envían S/R;
	 * valor crudo ±1 se normaliza con ArticuloMovimientoCantidadSignoSupport.
	 */
	private function firmarCantidadMovimiento(
		float $cantidad,
		Tipotransaccion|Tipotransaccion_Stock $tipotransaccion,
		mixed $signoCantidad = null
	): float {
		$signo = $signoCantidad ?? $tipotransaccion->signo;

		if ($signo === 'S' || $signo === 'R') {
			return $cantidad * ($signo === 'S' ? 1 : -1);
		}

		if (is_numeric($signo)) {
			return ArticuloMovimientoCantidadSignoSupport::cantidadFirmadaSignoStock($cantidad, (int) $signo);
		}

		return $cantidad * ($signo == 'S' ? 1 : -1);
	}

	private function resolveTipoTransaccionDesdeMovimiento($articulo_movimiento): Tipotransaccion|Tipotransaccion_Stock
	{
		if (! empty($articulo_movimiento->tipotransaccion_stock_id)) {
			return $this->tipotransaccionStockRepository->find((int) $articulo_movimiento->tipotransaccion_stock_id);
		}

		if (! empty($articulo_movimiento->tipotransaccion_id)) {
			return $this->tipotransaccionRepository->find((int) $articulo_movimiento->tipotransaccion_id);
		}

		throw new \Exception('No encontro tipo de transaccion.');
	}

	// Genera datos reporte stock de OT
	public function generaDatosRepStockOt($estado, $mventa_id, $desdearticulo, $hastaarticulo,
										$desdelinea_id, $hastalinea_id,
										$desdecategoria_id, $hastacategoria_id,
										$desdelote, $hastalote, $estadoot, $apertura, $deposito_id)
	{
		$data = collect($this->articulo_movimientoQuery->generaDatosRepStockOt($estado, $mventa_id,
				$desdearticulo, $hastaarticulo,
				$desdelinea_id, $hastalinea_id,
				$desdecategoria_id, $hastacategoria_id,
				$desdelote, $hastalote, $deposito_id, $apertura));

		// Overlay EN PRODUCCION: solo si no filtramos depósito ni «Entrega inmediata».
		$incluirExtrasProduccion = (int) $deposito_id === 0
			&& (string) $estadoot !== 'ENTREGA';
		if ($incluirExtrasProduccion) {
			$extras = $this->articulo_movimientoQuery->generaDatosOtEnProduccion(
				$estado,
				$mventa_id,
				$desdearticulo,
				$hastaarticulo,
				$desdelinea_id,
				$hastalinea_id,
				$desdecategoria_id,
				$hastacategoria_id,
				$desdelote,
				$hastalote,
				[]
			);
			$data = $data->concat($extras);
		}

		$otIdsSituacion = [];
		foreach ($data as $row) {
			if ((int) ($row['deposito_id'] ?? 0) > 0) {
				continue;
			}
			if (! empty($row['en_produccion_forzada'])) {
				continue;
			}
			$otId = (int) ($row['ordentrabajo_id'] ?? 0);
			if ($otId > 0) {
				$otIdsSituacion[] = $otId;
			}
		}
		$situacionesPorOt = $this->situacionesReporteStockOtPorIds($otIdsSituacion);
		$modulosCache = [];

		/** @var array<string, array<string, mixed>> $grupos */
		$grupos = [];
		$esMovimientos = $apertura === 'MOVIMIENTOS';

		foreach ($data as $movimiento) {
			$claveAgrupacion = ReporteStockOtSituacionSupport::claveAgrupacion(
				$movimiento['lote'] ?? 0,
				(int) ($movimiento['ordentrabajo_id'] ?? 0),
				(int) ($movimiento['deposito_id'] ?? 0)
			);
			$clave = implode('|', [
				(string) ($movimiento['sku'] ?? ''),
				(string) ($movimiento['codigocombinacion'] ?? ''),
				$claveAgrupacion,
			]);
			if ($esMovimientos) {
				$clave .= '|'.(int) ($movimiento['modulo_id'] ?? 0)
					.'|'.(int) ($movimiento['ordentrabajo_id'] ?? 0)
					.'|'.(int) ($movimiento['id'] ?? 0);
			}

			if (! isset($grupos[$clave])) {
				$meta = $this->situacionFilaReporteStockOt($movimiento, $situacionesPorOt);
				[$modulo, $cantidadModulo] = $this->curvaModuloReporteStockOt(
					(int) ($movimiento['modulo_id'] ?? 0),
					$modulosCache
				);
				$grupos[$clave] = [
					'foto' => $movimiento['foto'] ?? null,
					'nombrelinea' => $movimiento['nombrelinea'] ?? '',
					'sku' => $movimiento['sku'] ?? '',
					'codigocombinacion' => $movimiento['codigocombinacion'] ?? '',
					'nombrecombinacion' => $movimiento['nombrecombinacion'] ?? '',
					'lote' => ReporteStockOtSituacionSupport::identificadorExcel(
						$movimiento['lote'] ?? 0,
						$movimiento['ordentrabajo_codigo'] ?? ''
					),
					'precio' => $movimiento['precio'] ?? 0,
					'situacion' => $meta['situacion'],
					'en_produccion' => $meta['en_produccion'],
					'modulo_id' => $movimiento['modulo_id'] ?? 0,
					'cantidadmodulo' => $cantidadModulo,
					'modulo' => $modulo,
					'pedido' => $movimiento['pedido'] ?? null,
					'ordencompra' => $movimiento['ordentrabajo_codigo'] ?? '',
					'deposito_id' => (int) ($movimiento['deposito_id'] ?? 0),
					'deposito_codigo' => (string) ($movimiento['depositocodigo'] ?? ''),
					'deposito_nombre' => (string) ($movimiento['depositonombre'] ?? ''),
					'medidas' => [],
					'total_pares' => 0.0,
					'_sort' => implode('|', [
						(string) ($movimiento['nombrelinea'] ?? ''),
						(string) ($movimiento['sku'] ?? ''),
						sprintf('%05d', (int) ($movimiento['codigocombinacion'] ?? 0)),
						ReporteStockOtSituacionSupport::identificadorExcel(
							$movimiento['lote'] ?? 0,
							$movimiento['ordentrabajo_codigo'] ?? ''
						),
						sprintf('%010d', (int) ($movimiento['deposito_id'] ?? 0)),
					]),
				];
			}

			$talle = (string) ($movimiento['nombretalle'] ?? '');
			$cant = (float) ($movimiento['cantidad'] ?? 0);
			if ($talle === '') {
				continue;
			}
			if (! isset($grupos[$clave]['medidas'][$talle])) {
				$grupos[$clave]['medidas'][$talle] = 0.0;
			}
			$grupos[$clave]['medidas'][$talle] += $cant;
			$grupos[$clave]['total_pares'] += $cant;
		}

		$datas = [];
		uasort($grupos, static fn ($a, $b) => strcmp((string) $a['_sort'], (string) $b['_sort']));
		foreach ($grupos as $grupo) {
			if (abs((float) $grupo['total_pares']) < 0.0001) {
				continue;
			}
			if (! ReporteStockOtSituacionSupport::pasaFiltroEstadoOt(
				(string) $estadoot,
				(string) $grupo['situacion'],
				(bool) $grupo['en_produccion']
			)) {
				continue;
			}
			$medidas = [];
			foreach ($grupo['medidas'] as $medida => $cantidad) {
				if (abs((float) $cantidad) < 0.0001) {
					continue;
				}
				$medidas[] = ['medida' => $medida, 'cantidad' => $cantidad];
			}
			$datas[] = $this->filaReporteStockOt(
				$grupo['foto'],
				$grupo['nombrelinea'],
				$grupo['sku'],
				$grupo['codigocombinacion'],
				$grupo['nombrecombinacion'],
				$grupo['lote'],
				$grupo['precio'],
				(string) $grupo['situacion'],
				(bool) $grupo['en_produccion'],
				$grupo['modulo_id'],
				$grupo['cantidadmodulo'],
				$grupo['modulo'],
				$grupo['pedido'],
				$grupo['ordencompra'],
				$medidas,
				(int) $grupo['deposito_id'],
				(string) $grupo['deposito_codigo'],
				(string) $grupo['deposito_nombre']
			);
		}

		return $datas;
	}

	// Lee stock por numero de lote antes de generar OT consumiendo de stock
	public function leeStockPorLote($codigoOt, $articulo_id, $combinacion_id)
	{
		return $this->articulo_movimientoQuery->leeStockPorLote($codigoOt, $articulo_id, $combinacion_id);
	}

	/**
	 * Lotes/OT con saldo pendiente (> 0) para artículo+combinación (modal picking Ferli).
	 * El saldo se netea por lote (todos los módulos), igual que el reporte Stock por OT.
	 * Un consumo Abierto sobre un lote cargado en 12 D no debe esconder el remanente.
	 *
	 * @return list<array{lote: string, modulo_id: int, modulo: string, saldo: float, deposito_id: int, deposito: string, medidas: string}>
	 */
	public function leeLotesStockPendientes(int $articuloId, int $combinacionId, ?int $moduloId = null, ?string $texto = null): array
	{
		$filtraModuloId = ($moduloId && $moduloId > 0 && ! $this->esModuloAbiertoPicking($moduloId))
			? $moduloId
			: null;

		// Siempre leer todos los módulos: el saldo físico del lote es el neto.
		$movimientos = $this->articulo_movimientoQuery->leeMovimientosLotesArticuloCombinacion(
			$articuloId,
			$combinacionId,
			null,
			$texto
		);

		$tipoAlta = (int) config('consprod.TIPOTRANSACCION_ALTA_PRODUCCION', 3);
		$agrupados = [];

		foreach ($movimientos as $mov) {
			$lote = trim((string) ($mov->lote ?? ''));
			$otCodigo = trim((string) ($mov->ordentrabajo_codigo ?? ''));
			$otIdMov = (int) ($mov->ordentrabajo_id ?? 0);
			if (ReporteStockOtSituacionSupport::esLoteImportado($lote)) {
				$clave = 'L:'.$lote;
				$numero = $lote;
			} elseif ($otIdMov > 0 && $otCodigo !== '' && $otCodigo !== '0') {
				$clave = 'OT:'.$otIdMov;
				$numero = $otCodigo;
			} else {
				continue;
			}
			$moduloMovId = (int) ($mov->modulo_id ?? 0);
			$cantidad = (float) ($mov->cantidad ?? 0);
			if (! isset($agrupados[$clave])) {
				$agrupados[$clave] = [
					'lote' => $numero,
					'modulo_id' => 0,
					'modulo' => '',
					'saldo' => 0.0,
					'deposito_id' => 0,
					'deposito' => '',
					'deposito_codigo' => '',
					'deposito_nombre' => '',
					'medidas' => '',
					'_modulos' => [],
					'_talles' => [],
					'_modulos_ids' => [],
				];
			}
			$agrupados[$clave]['saldo'] += $cantidad;
			$agrupados[$clave]['_modulos_ids'][$moduloMovId] = true;
			$agrupados[$clave]['_modulos'][$moduloMovId] = [
				'saldo' => (float) (($agrupados[$clave]['_modulos'][$moduloMovId]['saldo'] ?? 0) + $cantidad),
				'etiqueta' => trim((string) (($mov->modulo_codigo ?? '').' '.($mov->modulo_nombre ?? ''))),
			];
			$talleNom = trim((string) ($mov->nombretalle ?? ''));
			if ($talleNom !== '') {
				$agrupados[$clave]['_talles'][$talleNom] = (float) (($agrupados[$clave]['_talles'][$talleNom] ?? 0) + $cantidad);
			}
			if ((int) ($mov->tipotransaccion_id ?? 0) === $tipoAlta && (int) ($mov->deposito_id ?? 0) > 0) {
				$agrupados[$clave]['deposito_id'] = (int) $mov->deposito_id;
				$agrupados[$clave]['deposito_codigo'] = (string) ($mov->deposito_codigo ?? '');
				$agrupados[$clave]['deposito_nombre'] = (string) ($mov->deposito_nombre ?? '');
				$agrupados[$clave]['deposito'] = trim(
					($mov->deposito_codigo ?? '').'-'.($mov->deposito_nombre ?? ''),
					'-'
				);
			} elseif ($agrupados[$clave]['deposito_id'] <= 0 && (int) ($mov->deposito_id ?? 0) > 0) {
				$agrupados[$clave]['deposito_id'] = (int) $mov->deposito_id;
				$agrupados[$clave]['deposito_codigo'] = (string) ($mov->deposito_codigo ?? '');
				$agrupados[$clave]['deposito_nombre'] = (string) ($mov->deposito_nombre ?? '');
				$agrupados[$clave]['deposito'] = trim(
					($mov->deposito_codigo ?? '').'-'.($mov->deposito_nombre ?? ''),
					'-'
				);
			}
		}

		$filas = [];
		foreach ($agrupados as $fila) {
			if ($fila['saldo'] <= 0) {
				continue;
			}
			if ($filtraModuloId && empty($fila['_modulos_ids'][$filtraModuloId])) {
				continue;
			}

			$mejorModuloId = 0;
			$mejorSaldo = -INF;
			$mejorEtiqueta = '';
			foreach ($fila['_modulos'] as $modId => $info) {
				if ((float) $info['saldo'] > $mejorSaldo) {
					$mejorSaldo = (float) $info['saldo'];
					$mejorModuloId = (int) $modId;
					$mejorEtiqueta = (string) $info['etiqueta'];
				}
			}
			$fila['modulo_id'] = $mejorModuloId;
			$fila['modulo'] = $mejorEtiqueta;

			$talles = $fila['_talles'];
			ksort($talles, SORT_NATURAL);
			$medidas = [];
			foreach ($talles as $nombre => $cant) {
				if (abs((float) $cant) < 0.0001) {
					continue;
				}
				$medidas[] = $nombre.':'.(int) round((float) $cant);
			}
			$fila['medidas'] = implode(' ', $medidas);
			// Módulo Abierto no trae la numeración en el nombre (a diferencia de 12 C, 12 D, etc.).
			if ($this->esModuloAbiertoPicking($mejorModuloId) && $fila['medidas'] !== '') {
				$estilo = $this->numeracionEstiloModuloDesdeMedidas($fila['medidas']);
				if ($estilo !== '' && stripos($fila['modulo'], $estilo) === false) {
					$fila['modulo'] = trim($fila['modulo'].' '.$estilo);
				}
			}
			unset($fila['_modulos'], $fila['_talles'], $fila['_modulos_ids']);
			$filas[] = $fila;
		}

		usort($filas, static function (array $a, array $b) {
			return [$a['lote'], $a['modulo_id']] <=> [$b['lote'], $b['modulo_id']];
		});

		return $filas;
	}

	private function esModuloAbiertoPicking(int $moduloId): bool
	{
		if ($moduloId === 30) {
			return true;
		}
		$modulo = Modulo::query()->find($moduloId, ['id', 'codigo', 'nombre']);
		if (! $modulo) {
			return false;
		}

		return stripos((string) ($modulo->nombre ?? ''), 'abierto') !== false
			|| trim((string) ($modulo->codigo ?? '')) === '99';
	}

	/**
	 * Convierte "36:2 37:3 38:3 39:2 40:1 41:1" en "(36-41) 2-3-3-2-1-1" (mismo estilo que el nombre del módulo cerrado).
	 */
	private function numeracionEstiloModuloDesdeMedidas(string $medidas): string
	{
		$pares = [];
		foreach (preg_split('/\s+/', trim($medidas)) ?: [] as $parte) {
			$bits = explode(':', $parte, 2);
			if (count($bits) !== 2) {
				continue;
			}
			$nombre = trim((string) $bits[0]);
			$cant = (int) round((float) $bits[1]);
			if ($nombre === '' || $cant <= 0) {
				continue;
			}
			$pares[] = ['medida' => $nombre, 'cantidad' => $cant];
		}
		if ($pares === []) {
			return '';
		}

		$desde = $pares[0]['medida'];
		$hasta = $pares[count($pares) - 1]['medida'];
		$cants = implode('-', array_map(static fn (array $p) => (string) $p['cantidad'], $pares));

		return '('.$desde.'-'.$hasta.') '.$cants;
	}

	// Borra un registro por ID de movimiento de stock
	public function deletePorMovimientoStockId($movimientostock_id)
    {
		return $this->articulo_movimientoRepository->deletePorMovimientoStockId($movimientostock_id);
    }

	// Borra un registro por ID de orden de trabajo 
	public function deletePorOrdentrabajoId($ordentrabajo_id)
    {
		return $this->articulo_movimientoRepository->deletePorOrdentrabajoId($ordentrabajo_id);
    }

	// Borra un registro por ID de orden de trabajo 
	public function deletePorPedido_combinacionId($pedido_combinacion_id)
	{
		return $this->articulo_movimientoRepository->deletePorPedido_combinacionId($pedido_combinacion_id);
	}

	public function buscaLoteImportacion($lotestock_id)
	{
		$loteimportacion = $this->articulo_movimientoQuery->buscaLoteImportacion($lotestock_id);

		if (count($loteimportacion) > 0)
			return $loteimportacion[0]->loteimportacion_id;

		return 0;
	}

	// Busca item referenciado por pedido_articulo_id
	public function findPorPedidoArticuloId($pedido_articulo_id)
	{
		return $this->articulo_movimientoRepository->findPorPedidoArticuloId($pedido_articulo_id);
	}

	/**
	 * Quita campos legacy (Anita / formulario) que no existen en articulo_movimiento.
	 *
	 * @param  array<string, mixed>  $data
	 * @return array<string, mixed>
	 */
	private function filtrarDatosParaTablaArticuloMovimiento(array $data): array
	{
		static $columnasPermitidas = null;

		if ($columnasPermitidas === null) {
			$columnasPermitidas = array_flip(array_diff(
				Schema::getColumnListing('articulo_movimiento'),
				['id', 'created_at', 'updated_at']
			));
		}

		return array_intersect_key($data, $columnasPermitidas);
	}

	/**
	 * @param  list<int>  $ordentrabajoIds
	 * @return array<int, array{situacion: string, en_produccion: bool}>
	 */
	private function situacionesReporteStockOtPorIds(array $ordentrabajoIds): array
	{
		$ids = array_values(array_unique(array_filter(array_map('intval', $ordentrabajoIds))));
		$out = [];
		foreach ($ids as $id) {
			$out[$id] = ReporteStockOtSituacionSupport::desdeTareaIds([]);
		}
		if ($ids === []) {
			return $out;
		}

		$rows = DB::table('ordentrabajo_tarea')
			->whereIn('ordentrabajo_id', $ids)
			->get(['ordentrabajo_id', 'tarea_id']);
		$porOt = [];
		foreach ($rows as $row) {
			$porOt[(int) $row->ordentrabajo_id][] = (int) $row->tarea_id;
		}
		foreach ($porOt as $otId => $tareaIds) {
			$out[$otId] = ReporteStockOtSituacionSupport::desdeTareaIds($tareaIds);
		}

		return $out;
	}

	/**
	 * @param  array<string, mixed>|\ArrayAccess  $movimiento
	 * @param  array<int, array{situacion: string, en_produccion: bool}>  $situacionesPorOt
	 * @return array{situacion: string, en_produccion: bool}
	 */
	private function situacionFilaReporteStockOt($movimiento, array $situacionesPorOt): array
	{
		if (! empty($movimiento['en_produccion_forzada'])) {
			return [
				'situacion' => ReporteStockOtSituacionSupport::EN_PRODUCCION,
				'en_produccion' => true,
			];
		}

		// Stock ya en depósito (import Excel / estantería) = ENTREGA INMEDIATA,
		// aunque la OT vinculada siga sin tarea de cierre (ej. identificador 8021).
		$depositoId = (int) ($movimiento['deposito_id'] ?? 0);
		if ($depositoId > 0) {
			return [
				'situacion' => ReporteStockOtSituacionSupport::ENTREGA_INMEDIATA,
				'en_produccion' => false,
			];
		}

		$otId = (int) ($movimiento['ordentrabajo_id'] ?? 0);
		if ($otId > 0 && isset($situacionesPorOt[$otId])) {
			return $situacionesPorOt[$otId];
		}

		return ReporteStockOtSituacionSupport::desdeTareaIds([]);
	}

	/**
	 * @param  array<int, array{0: list<array{medida: mixed, cantidad: mixed}>, 1: float|int}>  $modulosCache
	 * @return array{0: list<array{medida: mixed, cantidad: mixed}>, 1: float|int}
	 */
	private function curvaModuloReporteStockOt(int $moduloId, array &$modulosCache): array
	{
		if ($moduloId <= 0) {
			return [[], 0];
		}
		if (isset($modulosCache[$moduloId])) {
			return $modulosCache[$moduloId];
		}

		$modulo = [];
		$cantidadModulo = 0;
		$moduloTalle = Modulo::where('id', $moduloId)->with('talles')->first();
		if ($moduloTalle && $moduloTalle->talles) {
			foreach ($moduloTalle->talles as $unModulo) {
				$talle = Talle::find($unModulo->pivot->talle_id);
				if ($talle) {
					$modulo[] = [
						'medida' => $unModulo->nombre,
						'cantidad' => $unModulo->pivot->cantidad,
					];
					$cantidadModulo += $unModulo->pivot->cantidad;
				}
			}
		}
		$modulosCache[$moduloId] = [$modulo, $cantidadModulo];

		return $modulosCache[$moduloId];
	}

	/**
	 * @param  list<array{medida: mixed, cantidad: mixed}>  $medidas
	 * @param  list<array{medida: mixed, cantidad: mixed}>  $modulo
	 * @return array<string, mixed>
	 */
	private function filaReporteStockOt(
		$foto,
		$nombreLinea,
		$sku,
		$codigoCombinacion,
		$nombreCombinacion,
		$lote,
		$precio,
		string $situacion,
		bool $enProduccion,
		$modulo_id,
		$cantidadModulo,
		array $modulo,
		$pedido,
		$ordencompra,
		array $medidas,
		int $depositoId = 0,
		string $depositoCodigo = '',
		string $depositoNombre = ''
	): array {
		$totalPares = 0.0;
		foreach ($medidas as $m) {
			$totalPares += (float) ($m['cantidad'] ?? 0);
		}

		return [
			'foto' => $foto,
			'nombrelinea' => $nombreLinea,
			'sku' => $sku,
			'sku_excel' => ReporteStockOtSituacionSupport::skuConGuiones($sku),
			'codigo' => $codigoCombinacion,
			'nombrecombinacion' => $nombreCombinacion,
			'descripcion_excel' => ReporteStockOtSituacionSupport::descripcionCombinacion($codigoCombinacion, $nombreCombinacion),
			'lote' => $lote,
			'precio' => $precio,
			'situacion' => $situacion,
			'en_produccion' => $enProduccion,
			'modulo_id' => $modulo_id,
			'cantidadmodulo' => $cantidadModulo,
			'modulo' => $modulo,
			'pedido' => $pedido,
			'ordencompra' => $ordencompra,
			'medidas' => $medidas,
			'total_pares' => $totalPares,
			'deposito_id' => $depositoId,
			'deposito_codigo' => $depositoCodigo,
			'deposito_nombre' => $depositoNombre,
		];
	}

}

