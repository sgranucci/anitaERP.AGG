<?php

namespace App\Repositories\Ventas;

use App\Models\Ventas\Venta;
use App\Models\Ventas\Tipotransaccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Models\Ventas\Cliente_Cuentacorriente_Aplicacion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\SqlDialectSupport;
use App\Support\Ventas\FacturaListadoFiltros;
use App\Support\Ventas\FacturacionAnitaGrabacionSupport;
use App\Support\Ventas\PedidoFacturacionProfiler;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaEmisionCajaPiezaSupport;
use App\Support\Ventas\VentaImpuestoResolucionSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Auth;
use App\ApiAnita;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VentaRepository implements VentaRepositoryInterface
{
    protected $model;
    protected $empresaRepository;

    /**
     * PostRepository constructor.
     *
     * @param Post $post
     */
    public function __construct(Venta $venta,
                                EmpresaRepositoryInterface $empresarepository)
    {
        $this->model = $venta;
        $this->empresaRepository = $empresarepository;
    }

    public function all()
    {
        return $this->model->get();
    }

    public function leeSinPaginar($filtros)
    {
        return $this->construyeQueryListado($filtros)->get();
    }

    public function leePaginando($filtros)
    {
        return $this->construyeQueryListado($filtros)->paginate(12);
    }

    /**
     * Totales de kilos/cajas/unidades y cantidad por reparto (mismos filtros que el index).
     * `ultimo_venta_id` es el último del grupo en el orden por reparto (código DESC, id DESC).
     * Los subtotales solo se muestran cuando el listado está en orden por reparto.
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return Collection<string, object>
     */
    public function totalesIndexPorReparto($filtros): Collection
    {
        $filtros = $this->normalizarFiltrosListado($filtros);

        $query = $this->queryListadoFiltrado($filtros)
            ->leftJoin('transporte', 'transporte.id', '=', 'venta.transporte_id')
            ->leftJoin('venta_emision', 'venta_emision.venta_id', '=', 'venta.id');

        $select = [
            'venta.transporte_id',
            'transporte.codigo as codigotransporte',
            'transporte.nombre as nombretransporte',
            DB::raw('COUNT(DISTINCT venta.id) as cantidad_comprobantes'),
            DB::raw('COALESCE(SUM(venta_emision.cantidad), 0) as kilo'),
            DB::raw('MIN(venta.id) as ultimo_venta_id'),
        ];

        if (VentaEmisionCajaPiezaSupport::columnasDisponibles()) {
            $select[] = DB::raw('COALESCE(SUM(venta_emision.caja), 0) as caja');
            $select[] = DB::raw('COALESCE(SUM(venta_emision.pieza), 0) as pieza');
        } else {
            $select[] = DB::raw('0 as caja');
            $select[] = DB::raw('0 as pieza');
        }

        return $query
            ->select($select)
            ->groupBy('venta.transporte_id', 'transporte.codigo', 'transporte.nombre')
            ->get()
            ->keyBy(static fn ($row) => (string) ((int) ($row->transporte_id ?? 0)));
    }

    /**
     * Totales del rango filtrado (todos los comprobantes del filtro, no solo la página).
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return object{cantidad_comprobantes: int, caja: float, pieza: float, kilo: float, total: float}
     */
    public function totalesIndexRango($filtros): object
    {
        $filtros = $this->normalizarFiltrosListado($filtros);

        $selectEmision = [
            DB::raw('COUNT(DISTINCT venta.id) as cantidad_comprobantes'),
            DB::raw('COALESCE(SUM(venta_emision.cantidad), 0) as kilo'),
        ];
        if (VentaEmisionCajaPiezaSupport::columnasDisponibles()) {
            $selectEmision[] = DB::raw('COALESCE(SUM(venta_emision.caja), 0) as caja');
            $selectEmision[] = DB::raw('COALESCE(SUM(venta_emision.pieza), 0) as pieza');
        } else {
            $selectEmision[] = DB::raw('0 as caja');
            $selectEmision[] = DB::raw('0 as pieza');
        }

        $totalesEmision = $this->queryListadoFiltrado($filtros)
            ->leftJoin('venta_emision', 'venta_emision.venta_id', '=', 'venta.id')
            ->select($selectEmision)
            ->first();

        // Sin join a líneas: SUM(venta.total) no se infla.
        $totalImporte = (float) $this->queryListadoFiltrado($filtros)
            ->select(DB::raw('COALESCE(SUM(venta.total), 0) as total'))
            ->value('total');

        return (object) [
            'cantidad_comprobantes' => (int) ($totalesEmision->cantidad_comprobantes ?? 0),
            'caja' => (float) ($totalesEmision->caja ?? 0),
            'pieza' => (float) ($totalesEmision->pieza ?? 0),
            'kilo' => (float) ($totalesEmision->kilo ?? 0),
            'total' => $totalImporte,
        ];
    }

    /**
     * IDs de comprobantes del listado para un reparto (mismos filtros que el index).
     *
     * @param  array<string, mixed>|string|null  $filtros
     * @return list<int>
     */
    public function idsIndexPorReparto($filtros, int $transporteId): array
    {
        $filtros = $this->normalizarFiltrosListado($filtros);

        $query = $this->queryListadoFiltrado($filtros)
            ->select('venta.id')
            ->leftJoin('transporte', 'transporte.id', '=', 'venta.transporte_id');

        if ($transporteId > 0) {
            $query->where('venta.transporte_id', $transporteId);
        } else {
            $query->where(function ($q): void {
                $q->whereNull('venta.transporte_id')->orWhere('venta.transporte_id', 0);
            });
        }

        return $this->aplicarOrdenIndexPorReparto($query)
            ->pluck('venta.id')
            ->map(static fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Query base del listado de comprobantes de venta.
     *
     * Restringe siempre a las empresas asignadas al usuario (vía punto de venta)
     * y aplica los filtros inteligentes de texto, empresa y rango de fechas.
     *
     * @param  array<string, mixed>|string|null  $filtros  Array de filtros o búsqueda legacy (string).
     * @return Builder<Venta>
     */
    private function construyeQueryListado($filtros): Builder
    {
        $filtros = $this->normalizarFiltrosListado($filtros);

        $query = $this->queryListadoFiltrado($filtros)
            ->select('venta.*')
            ->with([
                'puntoventas.empresas',
                'clientes.condicionivas',
                'tipotransacciones',
                'transportes',
                'monedas',
                'venta_emisiones',
            ]);

        if (FacturaListadoFiltros::esOrdenReparto($filtros)) {
            $query->leftJoin('transporte', 'transporte.id', '=', 'venta.transporte_id');
        }

        return $this->aplicarOrdenIndex($query, $filtros);
    }

    /**
     * @param  array<string, mixed>|string|null  $filtros
     * @return array<string, mixed>
     */
    private function normalizarFiltrosListado($filtros): array
    {
        if (is_array($filtros)) {
            return $filtros;
        }

        $legacy = FacturaListadoFiltros::filtrosVacios();
        $legacy['valor'] = trim((string) $filtros);
        $legacy['busqueda'] = $legacy['valor'];
        $legacy['fecha_desde'] = '';
        $legacy['fecha_hasta'] = '';

        return $legacy;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Venta>
     */
    private function queryListadoFiltrado(array $filtros): Builder
    {
        $query = $this->model->newQuery();

        $empresas = $this->empresaRepository->traeEmpresasAsignadas();
        if (count($empresas) >= 1) {
            $query->whereHas('puntoventas', static function ($q) use ($empresas): void {
                $q->whereIn('empresa_id', $empresas);
            });
        }

        FacturaListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    /**
     * Impresión por reparto: siempre agrupa por código, independiente del toggle del index.
     */
    private function aplicarOrdenIndexPorReparto(Builder $query): Builder
    {
        return $query
            ->orderByRaw(SqlDialectSupport::coalesce(
                SqlDialectSupport::castEntero('transporte.codigo'),
                '0'
            ).' DESC')
            ->orderBy('venta.id', 'desc');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarOrdenIndex(Builder $query, array $filtros): Builder
    {
        if (FacturaListadoFiltros::esOrdenId($filtros)) {
            return $query->orderBy('venta.id', 'desc');
        }

        return $this->aplicarOrdenIndexPorReparto($query);
    }

    public function create(array $data)
    {
        $data = $this->aplicarCodigoAfipElBierzo($data);

        return $this->model->create($data);
    }

    public function update(array $data, $id)
    {
        $data = $this->aplicarCodigoAfipElBierzo($data, (int) $id);

        return $this->model->findOrFail($id)->update($data);
    }

    /**
     * Tipo ARCA efectivo (001+letra → 1/6, 003 NC, 201 FCE…) para unique y max()+1.
     * Desde 2026-09-13 la columna/unique existen en todos los entornos (no solo El Bierzo).
     * Si el payload ya trae codigo_afip (FacturacionService post-armaTipoTransaccion), se respeta:
     * no se recalcula ni se altera el camino del POS.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function aplicarCodigoAfipElBierzo(array $data, ?int $ventaId = null): array
    {
        if (! Schema::hasColumn('venta', 'codigo_afip')) {
            unset($data['codigo_afip']);

            return $data;
        }

        // POS / FacturacionService ya dejan el tipo AFIP correcto (ej. FAC B → 6). No tocar.
        if ((int) ($data['codigo_afip'] ?? 0) > 0) {
            return $data;
        }

        $tipoId = (int) ($data['tipotransaccion_id'] ?? 0);
        $codigoVenta = (string) ($data['codigo'] ?? '');
        if ($tipoId <= 0 || $codigoVenta === '') {
            $existente = $ventaId !== null && $ventaId > 0
                ? $this->model->query()->whereKey($ventaId)->first(['tipotransaccion_id', 'codigo'])
                : null;
            $tipoId = $tipoId > 0 ? $tipoId : (int) ($existente->tipotransaccion_id ?? 0);
            $codigoVenta = $codigoVenta !== '' ? $codigoVenta : (string) ($existente->codigo ?? '');
        }

        $codigoAlmacenado = $tipoId > 0
            ? (string) (Tipotransaccion::query()->whereKey($tipoId)->value('codigo') ?? '')
            : '';
        $afip = TipotransaccionCodigoAfipSupport::codigoAfipDesdeVentaGrabada($codigoAlmacenado, $codigoVenta);
        $data['codigo_afip'] = $afip > 0 ? $afip : null;

        return $data;
    }

    public function delete($id)
    {
    	return $this->model->destroy($id);
    }

    public function find($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones.articulos')
                                ->with('venta_emisiones.conceptoVenta')
                                ->with('venta_emisiones.talles')
                                ->with('venta_emisiones.combinaciones')
                                ->with('venta_emisiones.modulos')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')        
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('ordenventas')
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
                                ->find($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return VentaImpuestoResolucionSupport::adjuntarAVenta($venta);
    }

    public function findOrFail($id)
    {
        if (null == $venta = $this->model
                                ->with('venta_impuestos')
                                ->with('venta_emisiones.articulos')
                                ->with('venta_emisiones.conceptoVenta')
                                ->with('venta_exportaciones')
                                ->with('cliente_cuentacorrientes')
                                ->with('clientes')    
                                ->with('tipotransacciones')
                                ->with('puntoventas')
                                ->with('ordenventas')                                    
                                ->with('asientos')
                                ->with('pedidos')
                                ->with('cobranzas')
                                ->findOrFail($id)) {
            throw new ModelNotFoundException("Registro no encontrado");
        }

        return VentaImpuestoResolucionSupport::adjuntarAVenta($venta);
    }

    public function traeUltimoNumeroRemito($tipo, $letra, $sucursal)
    {
        // El Bierzo numera como a-remito/a-comprob: compemis resuelve la clave
        // y numerador contiene el ultimo numero reservado. No usar MAX(pendmae):
        // el remito puede originarse al facturar sin existir aun en pendmae.
        // Con Anita apagada NO numerar desde MAX(ERP): genera huecos vs Informix
        // (incidente 19/nov/2026: 905925/905926 con numerador Anita en 9070xx).
        // Sin bridge no se emite factura/remito hasta reencender BIERZO_FACTURACION_GRABA_ANITA.
        if (EntornoEmpresaSupport::esElBierzo() && ! FacturacionAnitaGrabacionSupport::habilitada()) {
            FacturacionAnitaGrabacionSupport::logSiApagada();
            Log::error('ventas.remito.numerador_bloqueado_anita_apagada', [
                'tipo' => $tipo,
                'letra' => $letra,
                'sucursal' => $sucursal,
                'motivo' => 'BIERZO_FACTURACION_GRABA_ANITA=false',
            ]);

            return 'error';
        }

        // El Bierzo + Anita: reservar acá (avance síncrono). Con réplica diferida el
        // numeraAnita de grabaAnita corre después y dejaba N facturas con el mismo remito.
        // Reintenta ante fallos transitorios del bridge (Permission denied/cmd_sql,
        // lectura vacía de numerador bajo carga concurrente con la cola diferida).
        if (EntornoEmpresaSupport::esElBierzo()) {
            return $this->reservarNumeroAnitaConReintentos((string) $tipo, (string) $letra, (string) $sucursal, null, 'ventas.remito');
        }

        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'pendmae', 
            'sistema' => 'ventas',
            'campos' => '
                max(penm_nro) as ultnro
			' , 
            'whereArmado' => " WHERE penm_tipo='".$tipo."' and penm_letra='".$letra."' 
                                    and penm_sucursal='".$sucursal."' " 
        );
        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));

        if ($fila !== null && isset($fila->ultnro)) {
            return (int) $fila->ultnro + 1;
        }

        return 'error';
    }

    /**
     * Reserva (lee + avanza) el numerador Anita de tipo + letra + sucursal, reintentando
     * los fallos transitorios del bridge. Devuelve el número reservado o 'error'.
     * Sin fila en compemis devuelve 'error': nunca se numera desde 1.
     */
    public function reservarNumeroAnitaConReintentos(
        string $tipo,
        string $letra,
        string $sucursal,
        ?string $path_sistema = null,
        string $logPrefijo = 'ventas.remito',
    ): int|string {
            $maxIntentos = max(5, (int) config('anita.bridge_csv_faltante_reintentos', 3));
            $ultimoError = null;
            PedidoFacturacionProfiler::etapa('anita_compemis_numerador_inicio');
            for ($intento = 1; $intento <= $maxIntentos; $intento++) {
                try {
                    $reservado = $this->numeraAnita($tipo, $letra, $sucursal, $path_sistema);
                    if (is_int($reservado) && $reservado > 0) {
                        if ($intento > 1) {
                            Log::info($logPrefijo.'.numerador_anita_ok_reintento', [
                                'tipo' => $tipo,
                                'letra' => $letra,
                                'sucursal' => $sucursal,
                                'numero' => $reservado,
                                'intento' => $intento,
                            ]);
                        }
                        PedidoFacturacionProfiler::etapa('anita_compemis_numerador_fin');

                        return $reservado;
                    }

                    $ultimoError = is_string($reservado) ? $reservado : 'reserva_invalida';
                    $reintentar = $intento < $maxIntentos
                        && $this->esErrorNumeradorAnitaReintentable($ultimoError);
                    Log::error($logPrefijo.'.numerador_anita_reserva_fallida', [
                        'tipo' => $tipo,
                        'letra' => $letra,
                        'sucursal' => $sucursal,
                        'resultado' => $reservado,
                        'intento' => $intento,
                        'reintenta' => $reintentar,
                    ]);
                    if ($reintentar) {
                        usleep(300000 * $intento);
                        continue;
                    }
                    break;
                } catch (\Throwable $e) {
                    $ultimoError = $e->getMessage();
                    $reintentar = $intento < $maxIntentos
                        && $this->esErrorNumeradorAnitaReintentable($ultimoError);
                    Log::error($logPrefijo.'.numerador_anita_no_disponible', [
                        'tipo' => $tipo,
                        'letra' => $letra,
                        'sucursal' => $sucursal,
                        'error' => $ultimoError,
                        'intento' => $intento,
                        'reintenta' => $reintentar,
                    ]);
                    if ($reintentar) {
                        usleep(300000 * $intento);
                        continue;
                    }

                    PedidoFacturacionProfiler::etapa('anita_compemis_numerador_fin');

                    return 'error';
                }
            }
        PedidoFacturacionProfiler::etapa('anita_compemis_numerador_fin');
        Log::error($logPrefijo.'.numerador_anita_agotado', [
            'tipo' => $tipo,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'error' => $ultimoError,
            'intentos' => $maxIntentos,
        ]);

        return 'error';
    }

    /**
     * Siguiente remito desde el ERP. Se usa en El Bierzo cuando la grabación Anita está apagada.
     */
    private function ultimoNumeroRemitoErp(string $sucursal): int
    {
        $codigo = trim($sucursal);
        $digitos = preg_replace('/\D+/', '', $codigo) ?? '';
        $variantes = array_values(array_unique(array_filter([
            $codigo,
            $digitos,
            $digitos !== '' ? str_pad($digitos, 5, '0', STR_PAD_LEFT) : '',
        ], static fn ($valor) => $valor !== '')));

        if ($variantes === []) {
            return 1;
        }

        $pvIds = DB::table('puntoventa')->whereIn('codigo', $variantes)->pluck('id');
        if ($pvIds->isEmpty()) {
            return 1;
        }

        return ((int) DB::table('remito')->whereIn('puntoventa_id', $pvIds)->max('numero')) + 1;
    }

    public function numeraAnita($tipo, $letra, $sucursal, $path_sistema = null)
    {
        // Leer + incrementar el numerador no es atómico en Anita: sin este candado global,
        // dos facturaciones de pedidos distintos pueden reservar el mismo número.
        $clave = 'anita:numerador:'.md5(implode('|', [
            (string) ($path_sistema ?? ''),
            strtoupper(trim((string) $tipo)),
            strtoupper(trim((string) $letra)),
            (int) preg_replace('/\D+/', '', (string) $sucursal),
        ]));

        try {
            return Cache::lock($clave, 30)->block(15, fn () => $this->numeraAnitaSinCandado($tipo, $letra, $sucursal, $path_sistema));
        } catch (LockTimeoutException) {
            return 'Error al leer numerador: otro proceso está numerando (reintentar)';
        }
    }

    private function numeraAnitaSinCandado($tipo, $letra, $sucursal, $path_sistema = null)
    {
        // Lee numerador desde anita
		$apiAnita = new ApiAnita();
        $data = array( 
            'acc' => 'list', 
			'tabla' => 'compemis', 
            'campos' => '
                compe_numero
			' , 
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."' 
                                    and ".$this->sqlSucursalAnita('compe_sucursal', (string) $sucursal) 
        );
        if (isset($path_sistema))
            $data['path_sistema'] = $path_sistema;
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            return 'Error al leer compemis: '.$errCompe;
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            // Vacío bajo carga del bridge: reintentable. Sin fila real de compemis también.
            $trimCompe = trim((string) $rawCompe);
            if ($trimCompe === '' || $trimCompe === '[]') {
                return 'Error al leer compemis: respuesta vacia del bridge';
            }

            return 0;
        }

        $claveNumero = $filaCompe->compe_numero;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => '
                num_ult_numero
            ',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        $errNumerador = ApiAnita::extraerMensajeError($rawNumerador);
        if ($errNumerador !== null) {
            return 'Error al leer numerador: '.$errNumerador;
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            // Antes decía "actualizar": era lectura vacía (carrera bridge / cmd_sql).
            return 'Error al leer numerador: respuesta vacia del bridge';
        }

        $numero = (int) $filaNumerador->num_ult_numero + 1;

        $apiAnita = new ApiAnita();
        $data = array(
            'acc' => 'update',
            'tabla' => 'numerador',
            'valores' => "num_ult_numero = '".$numero."' ",
            'whereArmado' => " WHERE num_clave = '".$claveNumero."' ",
        );
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        // exigirFilasAfectadas: [] / 0 rows bajo carga no se toma como OK silencioso.
        $apiAnita->apiCallEscritura(
            $data,
            'numerador update',
            'facturacion.anita_bridge.fallo',
            true,
        );

        return $numero;
    }

    /**
     * Fallos del bridge al reservar remito que conviene reintentar (carga concurrente,
     * cmd_sql Permission denied, list vacío). No reintenta ausencia real de compemis (0).
     */
    private function esErrorNumeradorAnitaReintentable(?string $mensaje): bool
    {
        if ($mensaje === null || trim($mensaje) === '') {
            return false;
        }

        if (ApiAnita::esErrorCsvUnloadFaltante($mensaje) || ApiAnita::esErrorCurlTransitorio($mensaje)) {
            return true;
        }

        $m = mb_strtolower($mensaje);

        return str_contains($m, 'error al actualizar numerador')
            || str_contains($m, 'error al leer numerador')
            || str_contains($m, 'error al leer compemis')
            || str_contains($m, 'respuesta vacia')
            || str_contains($m, 'respuesta vacía')
            || str_contains($m, 'sin respuesta del bridge')
            || str_contains($m, 'respuesta vacía del bridge')
            || str_contains($m, 'advertencia php en bridge')
            || str_contains($m, 'error al grabar en anita')
            || str_contains($m, '0 filas afectadas')
            || str_contains($m, 'sin confirmación de filas afectadas');
    }

    /**
     * Último número en Anita (MAX venta + numerador compemis), sin incrementar.
     * Misma fuente que AGG usaba con Informix vivo: tipo + letra + sucursal.
     */
    public function maxNumeroComprobanteAnitaBridge(
        string $tipo,
        string $letra,
        string $sucursal,
        $path_sistema = null,
    ): int {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        $sucursal = trim($sucursal);
        if ($tipo === '' || $letra === '' || $sucursal === '') {
            return 0;
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => 'max(ven_nro) as ultimonumero',
            'whereArmado' => " WHERE ven_tipo = '".$tipo."' AND
									ven_letra = '".$letra."' AND
									".$this->sqlSucursalAnita('ven_sucursal', $sucursal),
        ];
        if ($path_sistema !== null && $path_sistema !== '') {
            $data['path_sistema'] = $path_sistema;
        }
        $filaUltimo = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));
        $maxVenta = 0;
        if ($filaUltimo !== null && isset($filaUltimo->ultimonumero)) {
            $maxVenta = (int) $filaUltimo->ultimonumero;
        }

        $maxNumerador = $this->leerUltimoNumeradorCompemis($tipo, $letra, $sucursal, $path_sistema);

        return max($maxVenta, $maxNumerador);
    }

    /**
     * Número Anita ya emitido ese día (tipo + letra + sucursal). 0 si no hay.
     * FSL/FBI: p-vtamaquina / p-vtabingo graban uno por jornada.
     */
    public function numeroComprobanteAnitaDelDia(
        string $tipo,
        string $letra,
        string $sucursal,
        string $fechaYmd,
        ?int $empresaAnita = null,
        $path_sistema = null,
    ): int {
        $tipo = strtoupper(trim($tipo));
        $letra = strtoupper(trim($letra));
        $sucursal = trim($sucursal);
        $fecha = (int) str_replace('-', '', $fechaYmd);
        if ($tipo === '' || $letra === '' || $sucursal === '' || $fecha <= 0) {
            return 0;
        }

        $where = " WHERE ven_tipo = '".$tipo."' AND ven_letra = '".$letra."' AND "
            .$this->sqlSucursalAnita('ven_sucursal', $sucursal)
            .' AND ven_fecha = '.$fecha;
        if ($empresaAnita !== null && $empresaAnita > 0) {
            $where .= ' AND ven_empresa = '.(int) $empresaAnita;
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'venta',
            'campos' => 'max(ven_nro) as ultimonumero',
            'whereArmado' => $where,
        ];
        if ($path_sistema !== null && $path_sistema !== '') {
            $data['path_sistema'] = $path_sistema;
        }

        $fila = ApiAnita::primeraFilaLista($apiAnita->apiCall($data));
        if ($fila === null || ! isset($fila->ultimonumero)) {
            return 0;
        }

        return (int) $fila->ultimonumero;
    }

    public function traeUltimoComprobanteVenta($tipotransaccion_id, $puntoventa_id, ?int $empresa_id = null)
    {
        $query = $this->model->select('venta.numerocomprobante')
            ->where('venta.tipotransaccion_id', $tipotransaccion_id)
            ->where('venta.puntoventa_id', $puntoventa_id);

        if ($empresa_id !== null && $empresa_id > 0) {
            $query->whereHas('puntoventas', static function ($q) use ($empresa_id): void {
                $q->where('empresa_id', $empresa_id);
            });
        }

        return $query->orderBy('venta.numerocomprobante', 'desc')->first();
    }

    /**
     * Último número reservado en compemis/numerador Anita (sin incrementar).
     */
    public function leerUltimoNumeradorCompemis(string $tipo, string $letra, string $sucursal, $path_sistema = null): int
    {
        try {
            return $this->leerUltimoNumeradorCompemisEstricto($tipo, $letra, $sucursal, $path_sistema);
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function leerUltimoNumeradorCompemisEstricto(
        string $tipo,
        string $letra,
        string $sucursal,
        $path_sistema = null
    ): int {
        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'compemis',
            'campos' => 'compe_numero',
            'whereArmado' => " WHERE compe_tipo='".$tipo."' and compe_letra='".$letra."'
                                    and ".$this->sqlSucursalAnita('compe_sucursal', $sucursal),
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }
        $rawCompe = $apiAnita->apiCallEscritura($data);
        $errCompe = ApiAnita::extraerMensajeError($rawCompe);
        if ($errCompe !== null) {
            throw new RuntimeException('Error al leer compemis: '.$errCompe);
        }

        $filaCompe = ApiAnita::primeraFilaLista((string) $rawCompe);
        if ($filaCompe === null || ! isset($filaCompe->compe_numero)) {
            throw new RuntimeException(
                "No existe compemis para {$tipo} {$letra} sucursal {$sucursal}."
            );
        }

        $claveNumero = trim((string) ($filaCompe->compe_numero ?? ''));
        if ($claveNumero === '' || (int) $claveNumero <= 0) {
            throw new RuntimeException(
                "compemis no tiene una clave de numerador valida para {$tipo} {$letra} sucursal {$sucursal}."
            );
        }

        $apiAnita = new ApiAnita();
        $data = [
            'acc' => 'list',
            'tabla' => 'numerador',
            'campos' => 'num_ult_numero',
            'whereArmado' => " WHERE num_clave='".$claveNumero."' ",
        ];
        if (isset($path_sistema)) {
            $data['path_sistema'] = $path_sistema;
        }

        $rawNumerador = $apiAnita->apiCallEscritura($data);
        $errNumerador = ApiAnita::extraerMensajeError($rawNumerador);
        if ($errNumerador !== null) {
            throw new RuntimeException('Error al leer numerador: '.$errNumerador);
        }

        $filaNumerador = ApiAnita::primeraFilaLista((string) $rawNumerador);
        if ($filaNumerador === null || ! isset($filaNumerador->num_ult_numero)) {
            throw new RuntimeException("No existe numerador Anita con clave {$claveNumero}.");
        }

        return max(0, (int) $filaNumerador->num_ult_numero);
    }

    /**
     * Anita guarda sucursal como 15 o 00015 según la tabla. El ERP usa siempre 5 dígitos.
     */
    private function sqlSucursalAnita(string $campo, string $sucursal): string
    {
        $sucursal = trim($sucursal);
        $variantes = [$sucursal];
        if ($sucursal !== '' && ctype_digit($sucursal)) {
            $sinCeros = ltrim($sucursal, '0');
            if ($sinCeros === '') {
                $sinCeros = '0';
            }
            $variantes[] = $sinCeros;
            $variantes[] = str_pad($sinCeros, 5, '0', STR_PAD_LEFT);
        }
        $variantes = array_values(array_unique(array_filter(
            $variantes,
            static fn ($v) => $v !== ''
        )));
        if ($variantes === []) {
            return $campo." = ''";
        }
        $in = implode(', ', array_map(
            static fn (string $v): string => "'".str_replace("'", "''", $v)."'",
            $variantes
        ));

        return $campo.' IN ('.$in.')';
    }

    public function leeComprobantePorOrdenVenta($ordenventa_id)
    {
        return $this->model->select('venta.id as id', 
                                    'venta.codigo as codigo', 
                                    'venta.fecha as fecha', 
                                    'cliente_cuentacorriente.fechavencimiento as fechavencimiento',
                                    'moneda.abreviatura as moneda', 
                                    'venta.total as total')
                                ->leftjoin('cliente_cuentacorriente', 'cliente_cuentacorriente.venta_id', '=', 'venta.id')
                                ->addSelect([
                                    'aplicado' => Cliente_Cuentacorriente_Aplicacion::query()
                                        ->selectRaw('SUM(total)')
                                        ->whereColumn('cliente_cuentacorriente_id', 'cliente_cuentacorriente.id')
                                ])                                    
                                ->join('moneda', 'moneda.id', 'venta.moneda_id')
                                ->with('cliente_cuentacorrientes')
                                ->where('ordenventa_id', $ordenventa_id)
                                ->where('cliente_cuentacorriente.cobranza_id', null)
                                ->orderBy('venta.fecha')->get();
    }
}
