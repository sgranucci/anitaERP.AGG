<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Articulo;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Models\Compras\Ordencompra_Comprobante_Cuota;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Configuracion\Empresa;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Compras\CondicioncompraRepositoryInterface;
use App\Repositories\Compras\CondicionentregaRepositoryInterface;
use App\Repositories\Compras\CondicionpagoRepositoryInterface;
use App\Repositories\Compras\Ordencompra_ArticuloRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Repositories\Ventas\TransporteRepositoryInterface;
use App\Support\Compras\AnitaSync\Ordencompra\AnitaOcClave;
use App\Support\Compras\AnitaSync\Ordencompra\ArticuloLineaFieldMapper;
use App\Support\Compras\AnitaSync\Ordencompra\CabeceraFieldMapper;
use App\Support\Compras\AnitaSync\Ordencompra\ComprobanteCuotaFieldMapper;
use App\Support\Compras\AnitaSync\Ordencompra\ComprobanteFieldMapper;
use App\Support\Compras\AnitaSync\Ordencompra\HistoriaFieldMapper;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaEstadosSupport;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaSyncContext;
use App\Support\Compras\OrdencompraCondicionesContratacionGenerator;
use App\Support\Compras\OrdencompraEstados;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

class OrdencompraAnitaSyncService
{
    public function __construct(
        private readonly OrdencompraRepositoryInterface $ordencompraRepository,
        private readonly Ordencompra_ArticuloRepositoryInterface $ordencompraArticuloRepository,
        private readonly Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
        private readonly OrdencompraGestionService $ordencompraGestionService,
        private readonly ProveedorQueryInterface $proveedorQuery,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
        private readonly MonedaRepositoryInterface $monedaRepository,
        private readonly CondicioncompraRepositoryInterface $condicioncompraRepository,
        private readonly CondicionentregaRepositoryInterface $condicionentregaRepository,
        private readonly CondicionpagoRepositoryInterface $condicionpagoRepository,
        private readonly TransporteRepositoryInterface $transporteRepository,
        private readonly PartidagastoRepositoryInterface $partidagastoRepository,
        private readonly CapexRepositoryInterface $capexRepository,
        private readonly ArticuloQueryInterface $articuloQuery,
    ) {
    }

    /**
     * @return array{en_anita:int, importados:int, omitidos:int, errores:list<string>}
     */
    public function sincronizarConAnita(?int $usuarioId = null): array
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $uid = $usuarioId ?? (int) (Auth::id() ?? 0);
        if ($uid <= 0) {
            throw new \RuntimeException('Usuario de sincronización no definido.');
        }

        $ctx = $this->nuevoContexto($uid);
        $fechaDesde = (int) config('ordencompra_anita.fecha_desde', 20250100);

        $api = new ApiAnita;
        $lista = json_decode($api->apiCall([
            'acc' => 'list',
            'campos' => 'penmp_nro',
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'sistema' => 'compras',
            'whereArmado' => " WHERE penmp_fecha >= {$fechaDesde}",
        ]));

        $ret = ['en_anita' => is_array($lista) ? count($lista) : 0, 'importados' => 0, 'omitidos' => 0, 'errores' => []];

        if (! is_array($lista)) {
            return $ret;
        }

        foreach ($lista as $item) {
            $nro = (int) ($item->penmp_nro ?? 0);
            if ($nro <= 0) {
                continue;
            }
            try {
                $r = $this->traerRegistroDeAnita($nro, $ctx);
                if ($r === 'importado') {
                    $ret['importados']++;
                } elseif ($r === 'omitido') {
                    $ret['omitidos']++;
                }
            } catch (\Throwable $e) {
                $msg = "OC {$nro}: ".$e->getMessage();
                $ret['errores'][] = $msg;
                Log::warning('OrdencompraAnitaSync: '.$msg, ['exception' => $e]);
            }
        }

        return $ret;
    }

    /**
     * Completa ítems faltantes cuando la cabecera ya está en ERP pero sin líneas en ordencompra_articulo.
     *
     * @return int Cantidad de líneas importadas
     */
    public function completarLineasFaltantesDesdeAnita(int $numeroOc, ?OrdencompraAnitaSyncContext $ctx = null): int
    {
        $ctx ??= $this->nuevoContexto((int) (Auth::id() ?? 1));

        $oc = Ordencompra::query()->where('numeroordencompra', $numeroOc)->first();
        if (! $oc || $oc->ordencompra_articulos()->exists()) {
            return 0;
        }

        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            Log::warning('OrdencompraAnitaSync: OC sin ítems en ERP y pendmaep inexistente en Anita', [
                'numero_oc' => $numeroOc,
            ]);

            return 0;
        }

        $clave = AnitaOcClave::desdePendmaep($cabecera);
        $lineas = $this->leerPendmovp($clave);
        if ($lineas === []) {
            Log::warning('OrdencompraAnitaSync: OC sin ítems en ERP y pendmovp vacío en Anita', [
                'numero_oc' => $numeroOc,
            ]);

            return 0;
        }

        $payload = CabeceraFieldMapper::mapAll($cabecera, $ctx);
        $monedaDefault = $ctx->fkMoneda($cabecera->penmp_cod_mon ?? '1') ?? 1;
        $movpresupPorInterno = $this->indexarMovpresup($clave);
        $leyendasPorOrden = $this->indexarOcvley($clave);

        DB::beginTransaction();
        try {
            $importadas = $this->importarLineasPendmovp(
                (int) $oc->id,
                $lineas,
                $cabecera,
                $payload,
                $movpresupPorInterno,
                $leyendasPorOrden,
                $ctx,
                $monedaDefault,
            );
            $this->regenerarCondicionesContratacion((int) $oc->id);
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($importadas > 0) {
            Log::info('OrdencompraAnitaSync: líneas completadas desde Anita', [
                'numero_oc' => $numeroOc,
                'ordencompra_id' => $oc->id,
                'lineas' => $importadas,
            ]);
        }

        return $importadas;
    }

    /**
     * @return 'importado'|'omitido'|'lineas_completadas'|'sin_datos'
     */
    public function traerRegistroDeAnita(int $numeroOc, ?OrdencompraAnitaSyncContext $ctx = null): string
    {
        $ctx ??= $this->nuevoContexto((int) (Auth::id() ?? 1));

        if ($ctx->existeOrdencompraPorNumero($numeroOc)) {
            $completadas = $this->completarLineasFaltantesDesdeAnita($numeroOc, $ctx);

            return $completadas > 0 ? 'lineas_completadas' : 'omitido';
        }

        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            return 'sin_datos';
        }

        $payload = CabeceraFieldMapper::mapAll($cabecera, $ctx);
        $this->validarCabeceraMinima($payload, $numeroOc);

        $clave = AnitaOcClave::desdePendmaep($cabecera);
        $lineas = $this->leerPendmovp($clave);
        $payload['estadoordencompra'] = OrdencompraAnitaEstadosSupport::haciaEstadoErpImportacion(
            $cabecera->penmp_estado ?? '0',
            $lineas
        );
        if ($lineas === []) {
            Log::warning('OrdencompraAnitaSync: importación OC sin líneas pendmovp (posible fallo del bridge Anita)', [
                'numero_oc' => $numeroOc,
            ]);
        }
        $movpresupPorInterno = $this->indexarMovpresup($clave);
        $leyendasPorOrden = $this->indexarOcvley($clave);
        $cuotas = $this->leerOccuota($clave);
        $historias = $this->leerLegcompra($numeroOc);

        DB::beginTransaction();
        try {
            $oc = $this->ordencompraRepository->createDesdeAnita($payload);

            $this->ordencompraEstadoRepository->creaEstado(
                $oc->id,
                Carbon::now()->toDateTimeString(),
                (string) ($payload['estadoordencompra'] ?? OrdencompraEstados::PENDIENTE),
                $ctx->usuarioSyncId,
                'Alta de orden de compra desde Anita'
            );

            $monedaDefault = $ctx->fkMoneda($cabecera->penmp_cod_mon ?? '1') ?? 1;

            $this->importarLineasPendmovp(
                (int) $oc->id,
                $lineas,
                $cabecera,
                $payload,
                $movpresupPorInterno,
                $leyendasPorOrden,
                $ctx,
                $monedaDefault,
            );

            $this->importarComprobantesCuotas($cuotas, $cabecera, $ctx, $oc->id);
            $this->importarHistorias($historias, $ctx, $oc->id, $payload['sector_legajocompra_id'] ?? null);

            $this->regenerarCondicionesContratacion($oc->id);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return 'importado';
    }

    private function nuevoContexto(int $usuarioSyncId): OrdencompraAnitaSyncContext
    {
        return new OrdencompraAnitaSyncContext(
            $this->proveedorQuery,
            $this->centrocostoRepository,
            $this->monedaRepository,
            $this->condicioncompraRepository,
            $this->condicionentregaRepository,
            $this->condicionpagoRepository,
            $this->transporteRepository,
            $this->partidagastoRepository,
            $this->capexRepository,
            $this->articuloQuery,
            $this->ordencompraGestionService,
            $usuarioSyncId,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarCabeceraMinima(array $payload, int $numeroOc): void
    {
        if (empty($payload['fecha']) || empty($payload['fechaentrega'])) {
            throw new \InvalidArgumentException('Fechas inválidas en pendmaep.');
        }
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0 || ! Empresa::query()->where('id', $empresaId)->exists()) {
            throw new \InvalidArgumentException("empresa_id {$empresaId} inexistente (OC {$numeroOc}).");
        }
        if (empty($payload['centrocosto_id'])) {
            throw new \InvalidArgumentException('centrocosto_id obligatorio (penmp_ccosto sin match en ERP).');
        }
        if (trim((string) ($payload['detalle'] ?? '')) === '') {
            throw new \InvalidArgumentException('detalle vacío (penmp_leyenda).');
        }
    }

    private function leerPendmaep(int $numeroOc): ?object
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'sistema' => 'compras',
            'campos' => implode(', ', [
                'penmp_proveedor', 'penmp_tipo', 'penmp_letra', 'penmp_sucursal', 'penmp_nro',
                'penmp_fecha', 'penmp_fecha_ent', 'penmp_cond_compra', 'penmp_cond_entrega', 'penmp_cond_pago',
                'penmp_entrega', 'penmp_dto', 'penmp_expreso', 'penmp_razon_susp', 'penmp_cod_mon', 'penmp_cotizacion',
                'penmp_fecha_ing', 'penmp_hora_ing', 'penmp_estado', 'penmp_leyenda', 'penmp_requisicion',
                'penmp_ccosto', 'penmp_legajo', 'penmp_empresa', 'penmp_usuario_ini', 'penmp_estado_aprob',
                'penmp_fecha_aprob', 'penmp_hora_aprob', 'penmp_usu_aprob', 'penmp_ccosto_dest', 'penmp_es_anticipo',
            ]),
            'whereArmado' => " WHERE penmp_nro={$numeroOc}",
        ]));

        return (is_array($rows) && count($rows) > 0) ? $rows[0] : null;
    }

    /**
     * @return list<object>
     */
    private function leerPendmovp(AnitaOcClave $clave): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.linea'),
            'sistema' => 'compras',
            'campos' => implode(', ', [
                'penvp_proveedor', 'penvp_tipo', 'penvp_letra', 'penvp_sucursal', 'penvp_nro', 'penvp_orden',
                'penvp_articulo', 'penvp_desc', 'penvp_agrupacion', 'penvp_unidad_med', 'penvp_cantidad',
                'penvp_cantentr', 'penvp_cantfact', 'penvp_precio', 'penvp_dto_art', 'penvp_deposito',
                'penvp_tipo_iva', 'penvp_fecha', 'penvp_incl_imp', 'penvp_cod_mon', 'penvp_partida',
                'penvp_fecha_ent', 'penvp_ccosto', 'penvp_requisicion', 'penvp_empresa', 'penvp_nro_interno',
            ]),
            'whereArmado' => $clave->wherePendmovp(),
        ]));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, object>
     */
    private function indexarMovpresup(AnitaOcClave $clave): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.presupuesto_linea'),
            'sistema' => 'compras',
            'campos' => 'movp_nro_interno, movp_partida, movp_presupuesto, movp_escenario, movp_proyecto, movp_mes, movp_cod_proyecto, movp_importe, movp_articulo',
            'whereArmado' => $clave->whereMovpresup(),
        ]));

        $idx = [];
        if (! is_array($rows)) {
            return $idx;
        }
        foreach ($rows as $row) {
            $idx[(int) ($row->movp_nro_interno ?? 0)] = $row;
        }

        return $idx;
    }

    /**
     * @return array<int, object>
     */
    private function indexarOcvley(AnitaOcClave $clave): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.leyenda_linea'),
            'sistema' => 'compras',
            'campos' => 'ocvl_nro_orden, ocvl_linea, ocvl_leyenda',
            'whereArmado' => $clave->whereOcvley(),
        ]));

        $idx = [];
        if (! is_array($rows)) {
            return $idx;
        }
        foreach ($rows as $row) {
            $idx[(int) ($row->ocvl_nro_orden ?? 0)] = $row;
        }

        return $idx;
    }

    /**
     * @return list<object>
     */
    private function leerOccuota(AnitaOcClave $clave): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.cuota'),
            'sistema' => 'compras',
            'campos' => 'occ_nro_cuota, occ_fecha_vto, occ_monto, occ_cond_pago, occ_medio_pago, occ_detalle',
            'whereArmado' => $clave->whereOccuota().' ORDER BY occ_nro_cuota',
        ]));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<object>
     */
    private function leerLegcompra(int $numeroOc): array
    {
        $api = new ApiAnita;
        $rows = json_decode($api->apiCall([
            'acc' => 'list',
            'tabla' => config('ordencompra_anita.tablas.historia'),
            'sistema' => 'compras',
            'campos' => 'legc_id, legc_fecha, legc_hora, legc_usuario, legc_estado, legc_observacion, legc_id_carga',
            'whereArmado' => " WHERE legc_id={$numeroOc}",
        ]));

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  list<object>  $cuotas
     */
    private function importarComprobantesCuotas(array $cuotas, object $cabecera, OrdencompraAnitaSyncContext $ctx, int $ordencompraId): void
    {
        if ($cuotas === []) {
            return;
        }

        $grupos = [];
        foreach ($cuotas as $c) {
            $key = (string) ($c->occ_cond_pago ?? '0');
            $grupos[$key][] = $c;
        }

        foreach ($grupos as $grupo) {
            $compData = ComprobanteFieldMapper::mapAll($grupo, $cabecera, $ctx, $ordencompraId, $ctx->usuarioSyncId);
            if (empty($compData['moneda_id'])) {
                $compData['moneda_id'] = $ctx->fkMoneda($cabecera->penmp_cod_mon ?? '1') ?? 1;
            }

            $comp = Ordencompra_Comprobante::create($compData);

            foreach ($grupo as $cuota) {
                $cuotaData = ComprobanteCuotaFieldMapper::mapAll(
                    $cuota,
                    $cabecera,
                    $ctx,
                    $comp->id,
                    $ctx->usuarioSyncId,
                );
                if (empty($cuotaData['moneda_id'])) {
                    $cuotaData['moneda_id'] = $compData['moneda_id'];
                }
                Ordencompra_Comprobante_Cuota::create($cuotaData);
            }
        }
    }

    /**
     * @param  list<object>  $historias
     */
    private function importarHistorias(array $historias, OrdencompraAnitaSyncContext $ctx, int $ordencompraId, ?int $sectorId): void
    {
        if ($historias === [] && $sectorId) {
            Ordencompra_Historia::create([
                'ordencompra_id' => $ordencompraId,
                'sector_legajocompra_id' => $sectorId,
                'fecha' => Carbon::now(),
                'observacion' => 'Importación desde Anita',
                'leyenda' => 'Alta inicial',
                'creousuario_id' => $ctx->usuarioSyncId,
            ]);

            return;
        }

        foreach ($historias as $row) {
            $data = HistoriaFieldMapper::mapAll($row, $ctx, $ordencompraId);
            if (empty($data['sector_legajocompra_id']) && $sectorId) {
                $data['sector_legajocompra_id'] = $sectorId;
            }
            if (empty($data['sector_legajocompra_id'])) {
                continue;
            }
            if (empty($data['fecha'])) {
                $data['fecha'] = Carbon::now();
            }
            Ordencompra_Historia::create($data);
        }
    }

    private function regenerarCondicionesContratacion(int $ordencompraId): void
    {
        $oc = $this->ordencompraRepository->find($ordencompraId);
        $texto = OrdencompraCondicionesContratacionGenerator::desdeModelo($oc);
        $this->ordencompraRepository->update(['condiciones_contratacion' => $texto], $ordencompraId);
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, mixed>  $payloadCabecera
     * @param  array<int, object>  $movpresupPorInterno
     * @param  array<int, object>  $leyendasPorOrden
     */
    private function importarLineasPendmovp(
        int $ordencompraId,
        array $lineas,
        object $cabecera,
        array $payloadCabecera,
        array $movpresupPorInterno,
        array $leyendasPorOrden,
        OrdencompraAnitaSyncContext $ctx,
        int $monedaDefault,
    ): int {
        $importadas = 0;

        foreach ($lineas as $linea) {
            $nroInterno = (int) ($linea->penvp_nro_interno ?? 0);
            $orden = (int) ($linea->penvp_orden ?? 0);
            $movp = $movpresupPorInterno[$nroInterno] ?? null;
            $leyenda = $leyendasPorOrden[$orden] ?? null;

            $lineaPayload = ArticuloLineaFieldMapper::mapAll(
                $linea,
                $cabecera,
                $movp,
                $leyenda,
                $ctx,
                $ordencompraId,
            );

            if (empty($lineaPayload['moneda_id'])) {
                $lineaPayload['moneda_id'] = $monedaDefault;
            }
            if (empty($lineaPayload['centrocostodestino_id'])) {
                $lineaPayload['centrocostodestino_id'] = $payloadCabecera['centrocosto_id'];
            }
            if (empty($lineaPayload['fechaentrega'])) {
                $lineaPayload['fechaentrega'] = $payloadCabecera['fechaentrega'] ?? $payloadCabecera['fecha'];
            }

            $this->ordencompraArticuloRepository->createDesdeAnita($lineaPayload);
            $importadas++;
        }

        return $importadas;
    }

    /**
     * Alinea penvp_nro_interno y penvp_orden de cada línea ERP con pendmovp Anita (SKU + cantidad).
     */
    public function reconciliarLineasOcDesdeAnita(int $numeroOc): int
    {
        $oc = Ordencompra::query()->where('numeroordencompra', $numeroOc)->first();
        if (! $oc || $oc->ordencompra_articulos()->count() === 0) {
            return 0;
        }

        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            return 0;
        }

        $lineasAnita = $this->leerPendmovp(AnitaOcClave::desdePendmaep($cabecera));
        if ($lineasAnita === []) {
            return 0;
        }

        $oc->load('ordencompra_articulos.articulos');
        $actualizadas = 0;
        $usadosNroInterno = [];

        foreach ($oc->ordencompra_articulos as $ocArt) {
            $lineaAnita = $this->resolverLineaAnitaParaOcArticulo($ocArt, $lineasAnita, $usadosNroInterno);
            if ($lineaAnita === null) {
                continue;
            }

            $penvpOrden = (int) ($lineaAnita->penvp_orden ?? 0);
            $nroInterno = (int) ($lineaAnita->penvp_nro_interno ?? 0);
            if ($nroInterno > 0) {
                $usadosNroInterno[$nroInterno] = true;
            }
            $cambios = [];

            if ($penvpOrden > 0 && (int) ($ocArt->penvp_orden ?? 0) !== $penvpOrden) {
                $cambios['penvp_orden'] = $penvpOrden;
            }
            if ($nroInterno > 0 && (int) ($ocArt->penvp_nro_interno ?? 0) !== $nroInterno) {
                $cambios['penvp_nro_interno'] = $nroInterno;
            }

            if ($cambios !== []) {
                $ocArt->update($cambios);
                $actualizadas++;
            }
        }

        return $actualizadas;
    }

    /** @deprecated Use reconciliarLineasOcDesdeAnita */
    public function reconciliarPenvpOrdenOcDesdeAnita(int $numeroOc): int
    {
        return $this->reconciliarLineasOcDesdeAnita($numeroOc);
    }

    /**
     * @param  list<object>  $lineasAnita
     * @param  array<int, true>  $usadosNroInterno
     */
    private function resolverLineaAnitaParaOcArticulo(
        Ordencompra_Articulo $ocArt,
        array $lineasAnita,
        array $usadosNroInterno = []
    ): ?object {
        $nroInterno = (int) ($ocArt->penvp_nro_interno ?? 0);
        if ($nroInterno > 0) {
            foreach ($lineasAnita as $lineaAnita) {
                if ((int) ($lineaAnita->penvp_nro_interno ?? 0) === $nroInterno) {
                    return $lineaAnita;
                }
            }
        }

        $sku = self::skuComparable(optional($ocArt->articulos)->sku);
        if ($sku === '') {
            return null;
        }

        $lineaAnita = null;
        foreach ($lineasAnita as $candidata) {
            $nroCandidato = (int) ($candidata->penvp_nro_interno ?? 0);
            if ($nroCandidato > 0 && isset($usadosNroInterno[$nroCandidato])) {
                continue;
            }
            if (self::skuComparable($candidata->penvp_articulo ?? null) !== $sku) {
                continue;
            }
            if (abs((float) ($candidata->penvp_cantidad ?? 0) - (float) $ocArt->cantidad) < 0.0001) {
                return $candidata;
            }
            if ($lineaAnita === null) {
                $lineaAnita = $candidata;
            }
        }

        return $lineaAnita;
    }

    private static function skuComparable(mixed $sku): string
    {
        return ltrim(trim((string) $sku), '0');
    }

    /**
     * Completa penvp_orden en líneas ERP existentes leyendo pendmovp de Anita.
     */
    public function sincronizarPenvpOrdenDesdeAnita(int $numeroOc, ?OrdencompraAnitaSyncContext $ctx = null): int
    {
        $oc = Ordencompra::query()->where('numeroordencompra', $numeroOc)->first();
        if (! $oc || $oc->ordencompra_articulos()->count() === 0) {
            return 0;
        }

        $sinOrden = $oc->ordencompra_articulos()
            ->where(function ($q) {
                $q->whereNull('penvp_orden')->orWhere('penvp_orden', '<=', 0)
                    ->orWhereNull('penvp_nro_interno')->orWhere('penvp_nro_interno', '<=', 0);
            })
            ->exists();

        if (! $sinOrden) {
            return 0;
        }

        $ctx ??= $this->nuevoContexto((int) (Auth::id() ?? 1));
        $cabecera = $this->leerPendmaep($numeroOc);
        if ($cabecera === null) {
            return 0;
        }

        $clave = AnitaOcClave::desdePendmaep($cabecera);
        $lineas = $this->leerPendmovp($clave);
        if ($lineas === []) {
            return 0;
        }

        $actualizadas = 0;
        $oc->load('ordencompra_articulos.articulos');

        foreach ($lineas as $lineaAnita) {
            $penvpOrden = (int) ($lineaAnita->penvp_orden ?? 0);
            $nroInterno = (int) ($lineaAnita->penvp_nro_interno ?? 0);
            if ($penvpOrden <= 0 && $nroInterno <= 0) {
                continue;
            }

            $articuloId = $ctx->fkArticuloSku($lineaAnita->penvp_articulo ?? null);
            if (! $articuloId) {
                continue;
            }

            $candidatos = $oc->ordencompra_articulos->filter(function ($ocArt) use ($articuloId, $penvpOrden, $nroInterno) {
                if ((int) $ocArt->articulo_id !== (int) $articuloId) {
                    return false;
                }
                if ($nroInterno > 0 && (int) ($ocArt->penvp_nro_interno ?? 0) === $nroInterno) {
                    return false;
                }
                if ($penvpOrden > 0 && (int) ($ocArt->penvp_orden ?? 0) === $penvpOrden) {
                    return false;
                }

                return (empty($ocArt->penvp_orden) || (int) $ocArt->penvp_orden <= 0)
                    || (empty($ocArt->penvp_nro_interno) || (int) $ocArt->penvp_nro_interno <= 0);
            });

            if ($candidatos->isEmpty()) {
                continue;
            }

            $cantidadAnita = (float) ($lineaAnita->penvp_cantidad ?? 0);
            $ocArt = $candidatos->first(function ($ocArt) use ($cantidadAnita) {
                return abs((float) $ocArt->cantidad - $cantidadAnita) < 0.0001;
            }) ?? $candidatos->first();

            if (! $ocArt) {
                continue;
            }

            $cambios = [];
            if ($penvpOrden > 0) {
                $cambios['penvp_orden'] = $penvpOrden;
            }
            if ($nroInterno > 0) {
                $cambios['penvp_nro_interno'] = $nroInterno;
            }
            $ocArt->update($cambios);
            foreach ($cambios as $k => $v) {
                $ocArt->{$k} = $v;
            }
            $actualizadas++;
        }

        return $actualizadas;
    }
}
