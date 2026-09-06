<?php

namespace App\Services\Compras;

use App\Models\Compras\Condicionpago;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Models\Compras\Ordencompra_Comprobante_Cuota;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Articulo;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Compras\Ordencompra_ArchivoRepositoryInterface;
use App\Repositories\Compras\Ordencompra_ArticuloRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Compras\ProveedorRepositoryInterface;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Services\Compras\Surmar\OrdencompraSurmarAnitaBridgeService;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Services\Configuracion\ImpuestoService;
use App\Services\Configuracion\ModuloAvisoService;
use App\Services\Configuracion\OcArbolTriggerDispatcherService;
use App\Support\Compras\ContratoPeriodoServicioSupport;
use App\Support\Compras\OrdencompraComprobanteEstados;
use App\Support\Compras\OrdencompraCondicionesContratacionGenerator;
use App\Support\Compras\OrdencompraCondicionPagoDefaultSupport;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Compras\OrdencompraDescuentoSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use App\Support\Compras\OrdencompraTotalesResumen;
use App\Support\Compras\OrdencompraTratamientoMovimientosSupport;
use App\Support\Compras\RequisicionLineasOcSupport;
use App\Support\Compras\SuscripcionSupport;
use App\Support\Compras\ValidacionPresupuestoPartidaCapexLineas;
use App\Support\Stock\MovimientoStockColorTalleExclusividadSupport;
use App\Support\Stock\SurmarSupport;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrdencompraGestionService
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
        private Ordencompra_ArticuloRepositoryInterface $ordencompraArticuloRepository,
        private Ordencompra_ArchivoRepositoryInterface $ordencompraArchivoRepository,
        private ArbolaprobacionService $arbolaprobacionService,
        private OcArbolTriggerDispatcherService $ocArbolTriggerDispatcher,
        private RequisicionRepositoryInterface $requisicionRepository,
        private Requisicion_EstadoRepositoryInterface $requisicionEstadoRepository,
        private RequisicionPresupuestoService $requisicionPresupuestoService,
        private OrdencompraAnitaBridgeService $ordencompraAnitaBridge,
        private OrdencompraSurmarAnitaBridgeService $ordencompraSurmarAnitaBridge,
        private ProveedorRepositoryInterface $proveedorRepository,
        private CotizacionQueryInterface $cotizacionQuery,
        private ImpuestoService $impuestoService,
        private OrdencompraLegajoFacturaPdfService $legajoFacturaPdfService,
        private ModuloAvisoService $moduloAvisoService,
    ) {}

    public function idSectorCompras(): ?int
    {
        return Sector_Legajocompra::query()->where('nombre', 'Compras')->value('id');
    }

    /**
     * @return array<int, array{id:int,numerorequisicion:int,fecha:string,proveedor:string,centrocosto:string}>
     */
    public function buscarRequisicionesAprobadas(?string $q, int $limite = 40): array
    {
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $query = Requisicion::query()
            ->select(['requisicion.id', 'requisicion.numerorequisicion', 'requisicion.fecha', 'proveedor.nombre as proveedor', 'centrocosto.nombre as centrocosto'])
            ->leftJoin('proveedor', 'proveedor.id', '=', 'requisicion.proveedor_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'requisicion.centrocosto_id')
            ->where('requisicion.estado', $aprobada)
            ->orderByDesc('requisicion.fecha')
            ->limit($limite);

        if ($q !== null && $q !== '') {
            $b = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($b) {
                $w->where('requisicion.numerorequisicion', 'like', $b)
                    ->orWhere('proveedor.nombre', 'like', $b)
                    ->orWhere('centrocosto.nombre', 'like', $b);
            });
        }

        return $query->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'numerorequisicion' => (int) $r->numerorequisicion,
            'fecha' => (string) $r->fecha,
            'proveedor' => (string) ($r->proveedor ?? ''),
            'centrocosto' => (string) ($r->centrocosto ?? ''),
        ])->all();
    }

    /**
     * Plantilla JSON para precargar formulario (sin historia ni archivos).
     *
     * @return array<string, mixed>
     */
    public function plantillaDesdeRequisicion(int $requisicionId): array
    {
        $aprobada = self::nombreEstadoRequisicionAprobada();
        $req = Requisicion::with([
            'proveedores',
            'requisicion_articulos.articulos.unidadesdemedidasalternativas',
            'requisicion_articulos.monedas',
            'requisicion_articulos.centrocostos_destino',
            'requisicion_articulos.partidagastos.articulos',
            'requisicion_articulos.capexs',
            'requisicion_articulos.color',
            'requisicion_articulos.talle',
            'requisicion_articulos.articulo_proveedor',
        ])->find($requisicionId);
        if (! $req) {
            throw new \InvalidArgumentException('La requisición no existe.');
        }
        $estadoPermitido = ($req->estado === $aprobada)
            || $this->estadoRequisicionEquivaleAGeneroOc($req->estado);
        if (! $estadoPermitido) {
            throw new \InvalidArgumentException('La requisición no está en un estado que permita generar órdenes de compra.');
        }

        $pendientesIds = array_flip(RequisicionLineasOcSupport::idsPendientesOc($requisicionId));
        if ($pendientesIds === []) {
            throw new \InvalidArgumentException('No quedan ítems pendientes de orden de compra en esta requisición.');
        }

        $articulos = [];
        foreach ($req->requisicion_articulos as $i => $lin) {
            if (! isset($pendientesIds[(int) $lin->id])) {
                continue;
            }
            $pg = $lin->partidagastos;
            $cpx = $lin->capexs;
            $art = $lin->articulos;
            $uxenv = $art ? (float) ($art->unidadesxenvase ?? 0) : 0.0;
            $umAltAbrev = ($art && $art->unidadesdemedidasalternativas)
                ? (string) ($art->unidadesdemedidasalternativas->abreviatura ?? '')
                : '';
            $articulos[] = [
                'articulo_id' => $lin->articulo_id,
                'sku' => $art ? (string) ($art->sku ?? '') : '',
                'descripcion_articulo' => $art ? (string) ($art->descripcion ?? '') : '',
                'color_id' => $lin->color_id ? (int) $lin->color_id : null,
                'talle_id' => $lin->talle_id ? (int) $lin->talle_id : null,
                'color_nombre' => $lin->color ? (string) ($lin->color->nombre ?? '') : '',
                'talle_nombre' => $lin->talle ? (string) ($lin->talle->nombre ?? '') : '',
                'maneja_stock_color_talle' => (bool) ($art->maneja_stock_color_talle ?? false),
                'cantidad' => $lin->cantidad,
                'precio' => $lin->precio,
                'moneda_id' => $lin->moneda_id,
                'fechaentrega' => $lin->fechaentrega,
                'cantidadalternativa' => $lin->cantidadalternativa,
                'unidadesxenvase' => $uxenv > 0 ? $uxenv : null,
                'um_alternativa_abreviatura' => $umAltAbrev,
                'peso' => $art ? (float) ($art->peso ?? 0) : 0.0,
                'peso_unitario' => $art && (float) ($art->peso ?? 0) > 0 ? (float) $art->peso : null,
                'detalle' => $lin->detalle,
                'centrocostodestino_id' => $lin->centrocostodestino_id,
                'partidagasto_id' => $lin->partidagasto_id,
                'codigopartidagasto' => $pg ? (string) ($pg->codigo ?? '') : '',
                'descripcionpartidagasto' => ($pg && $pg->articulos) ? (string) ($pg->articulos->detalle ?? '') : '',
                'capex_id' => $lin->capex_id,
                'codigocapex' => $cpx ? (string) ($cpx->codigo ?? '') : '',
                'descripcioncapex' => $cpx ? (string) ($cpx->nombre ?? '') : '',
                'cotizacion' => 1,
                'requisicion_articulo_id' => (int) $lin->id,
                'proveedor_id' => $lin->proveedor_id ? (int) $lin->proveedor_id : null,
                'articulo_proveedor_id' => $lin->articulo_proveedor_id ? (int) $lin->articulo_proveedor_id : null,
                'nombre_articulo_proveedor' => $lin->articulo_proveedor
                    ? (string) ($lin->articulo_proveedor->nombre_articulo_proveedor ?? '')
                    : '',
            ];
        }

        $prov = $req->proveedores;
        $detalleReq = trim((string) ($req->detalle ?? ''));
        $detalleAutogenerado = $detalleReq === '';
        if ($detalleAutogenerado) {
            $nro = trim((string) ($req->numerorequisicion ?? ''));
            $detalleReq = $nro !== ''
                ? 'Orden de compra desde requisición N° '.$nro
                : 'Orden de compra desde requisición #'.$req->id;
        }

        return [
            'requisicion_id' => $req->id,
            'empresa_id' => $req->empresa_id,
            'fecha' => $req->fecha,
            'fechaentrega' => $req->fechaentrega,
            'centrocosto_id' => $req->centrocosto_id,
            'comentario' => $req->comentario,
            'detalle' => $detalleReq,
            'detalle_autogenerado' => $detalleAutogenerado,
            'numerorequisicion' => $req->numerorequisicion,
            'tratamiento' => Ordencompra::mapearTratamientoDesdeRequisicion((string) ($req->tratamiento ?? '')),
            'proveedor_id' => $req->proveedor_id,
            'proveedor_codigo' => $prov ? (string) ($prov->codigo ?? '') : '',
            'proveedor_nombre' => $prov ? (string) ($prov->nombre ?? '') : '',
            'articulos' => $articulos,
        ];
    }

    /**
     * @return list<array{fechavencimiento:string,monto:float,moneda_id:int,cotizacion:float,formapago_id:?int,detalle:string}>
     */
    public function sugerirCuotasDesdeCondicionpago(int $condicionpagoId, string $fechaBaseYmd, float $montoTotal, int $monedaId): array
    {
        $cp = Condicionpago::with(['condicionpagocuotas' => fn ($q) => $q->orderBy('cuota')])->find($condicionpagoId);
        if (! $cp || $cp->condicionpagocuotas->isEmpty()) {
            return [];
        }

        $cuotas = $cp->condicionpagocuotas;
        $sumPct = (float) $cuotas->sum(fn ($c) => (float) ($c->porcentaje ?? 0));
        $cursor = Carbon::parse($fechaBaseYmd)->startOfDay();
        $salida = [];
        $n = $cuotas->count();
        $idx = 0;

        foreach ($cuotas as $c) {
            $idx++;
            $pct = (float) ($c->porcentaje ?? 0);
            if ($sumPct > 0 && $pct > 0) {
                $monto = round($montoTotal * $pct / $sumPct, 4);
            } else {
                $monto = round($montoTotal / max(1, $n), 4);
            }

            $tipo = (string) ($c->tipoplazo ?? '');
            $fv = null;
            if (! empty($c->fechavencimiento)) {
                $fv = Carbon::parse($c->fechavencimiento)->format('Y-m-d');
            } elseif ($tipo === 'D' || $tipo === 'O') {
                $dias = (int) ($c->plazo ?? 0);
                $cursor = $cursor->copy()->addDays($dias);
                $fv = $cursor->format('Y-m-d');
            } elseif ($tipo === 'F') {
                $cursor = $cursor->copy()->addMonth();
                $fv = $cursor->format('Y-m-d');
            } else {
                $dias = (int) ($c->plazo ?? 0);
                $cursor = $cursor->copy()->addDays($dias);
                $fv = $cursor->format('Y-m-d');
            }

            $salida[] = [
                'fechavencimiento' => $fv ?? $fechaBaseYmd,
                'monto' => $monto,
                'moneda_id' => $monedaId,
                'cotizacion' => 1.0,
                'formapago_id' => null,
                'detalle' => 'Cuota '.$c->cuota.' — '.$cp->nombre,
            ];
        }

        return $salida;
    }

    /**
     * @param  array<int, array<string, mixed>>  $ordenesPayloads  Cada elemento: cuerpo POST equivalente a guardar una OC.
     * @param  array<int>  $lineasSinOrdenRequisicionArticuloIds  Líneas de requisición que se cierran sin OC (sin precio elegido).
     * @param  array<int, array<int, \Symfony\Component\HttpFoundation\File\UploadedFile>>  $archivosPorOrden  Archivos a adjuntar a la OC creada por cada índice del array de órdenes.
     * @return array{mensaje: string, errores?: string, ids?: array<int>, advertencias?: array<string>, partial?: bool}
     */
    public function generarMultiplesOrdenesCompraDesdeRequisicion(
        int $requisicionId,
        array $ordenesPayloads,
        array $lineasSinOrdenRequisicionArticuloIds,
        array $archivosPorOrden = []
    ): array {
        $lineasSinOrdenRequisicionArticuloIds = array_values(array_unique(array_map('intval', $lineasSinOrdenRequisicionArticuloIds)));
        $ordenesPayloads = array_values(array_filter($ordenesPayloads, static fn ($p) => is_array($p)));

        if ($ordenesPayloads === []) {
            return [
                'mensaje' => 'error',
                'errores' => 'Debe elegir el origen de precio en al menos un ítem para generar una orden de compra. '
                    .'No se puede cerrar la requisición sin crear ninguna OC.',
            ];
        }

        if ($lineasSinOrdenRequisicionArticuloIds !== []) {
            $n = Requisicion_Articulo::query()
                ->where('requisicion_id', $requisicionId)
                ->whereIn('id', $lineasSinOrdenRequisicionArticuloIds)
                ->count();
            if ($n !== count($lineasSinOrdenRequisicionArticuloIds)) {
                return ['mensaje' => 'error', 'errores' => 'Alguna línea indicada como «sin orden» no pertenece a esta requisición.'];
            }
        }

        $creados = [];
        $advertencias = [];

        foreach ($ordenesPayloads as $idx => $payload) {
            if (! is_array($payload)) {
                continue;
            }
            $payload['requisicion_id'] = $requisicionId;
            $files = [];
            if (! empty($archivosPorOrden[$idx]) && is_array($archivosPorOrden[$idx])) {
                $files = ['nombrearchivos' => array_values($archivosPorOrden[$idx])];
            }
            $sub = Request::create('/compras/ordencompra', 'POST', $payload, [], $files);
            $ret = $this->guardar($sub, true);
            if (($ret['mensaje'] ?? '') !== 'ok') {
                return [
                    'mensaje' => 'error',
                    'errores' => 'Fallo al generar la orden #'.(((int) $idx) + 1).': '.($ret['errores'] ?? 'Error'),
                    'ids' => $creados,
                    'advertencias' => $advertencias,
                    'partial' => $creados !== [],
                ];
            }
            if (! empty($ret['id'])) {
                $creados[] = (int) $ret['id'];
            }
        }

        if ($creados === []) {
            return [
                'mensaje' => 'error',
                'errores' => 'No se generó ninguna orden de compra. Verifique el origen de precio y los datos de cada ítem.',
                'advertencias' => $advertencias,
            ];
        }

        if ($lineasSinOrdenRequisicionArticuloIds !== []) {
            $etiqueta = RequisicionLineasOcSupport::etiquetaLineaCerradaSinOc();
            Requisicion_Articulo::query()
                ->where('requisicion_id', $requisicionId)
                ->whereIn('id', $lineasSinOrdenRequisicionArticuloIds)
                ->update(['precio_origen_etiqueta' => $etiqueta]);
            $advertencias[] = count($lineasSinOrdenRequisicionArticuloIds).' ítem(es) quedaron sin OC (línea cerrada en la requisición).';
        }

        $this->sincronizarEstadoRequisicionSegunLineasOc($requisicionId, Auth::user()->id);

        return [
            'mensaje' => 'ok',
            'ids' => $creados,
            'advertencias' => $advertencias,
        ];
    }

    public function guardar(Request $request, bool $omitirMarcarRequisicionGeneroOc = false): array
    {
        $v = Validator::make($request->all(), $this->reglasCabecera());
        if ($v->fails()) {
            return ['mensaje' => 'error', 'errores' => $v->errors()->first()];
        }
        $data = $v->validated();
        $payload = $request->all();

        try {
            $this->validarContratoImputacion($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->asegurarComprobanteDesdeProveedor($payload);
            $this->validarComprobantesCuotas($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            ValidacionPresupuestoPartidaCapexLineas::validar($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->normalizarOrigenPrecioRequisicionEnPayload($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->validarOrigenPrecioDesdeRequisicion($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->validarProveedorObligatorioDesdeRequisicion($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $esSuscripcionPayload = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if (! $esSuscripcionPayload) {
                $this->arbolaprobacionService->validaOrdencompraRequestContraArbolOpcional($payload);
            }
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            MovimientoStockColorTalleExclusividadSupport::validarLineas(
                $payload['articulo_ids'] ?? [],
                $payload['colores_id'] ?? [],
                $payload['talles_id'] ?? [],
            );
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $sectorId = $this->idSectorCompras();
        $uid = Auth::user()->id;

        $cab = $this->armaCabeceraDesdeRequest($payload, OrdencompraEstados::PENDIENTE, $sectorId, $uid);

        if (! empty($cab['requisicion_id'])) {
            try {
                $this->assertRequisicionAprobadaParaAsociarOc((int) $cab['requisicion_id']);
            } catch (\InvalidArgumentException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        $oc = null;
        DB::beginTransaction();
        try {
            $oc = $this->ordencompraRepository->create($cab);

            $this->ordencompraEstadoRepository->create([
                'fechas' => [Carbon::now()->toDateTimeString()],
                'estados' => [OrdencompraEstados::PENDIENTE],
                'usuario_ids' => [$uid],
                'observacionestados' => ['Alta de orden de compra'],
            ], $oc->id);

            $this->ordencompraArticuloRepository->syncFromRequest(array_merge($payload, ['fecha' => $cab['fecha']]), $oc->id);

            $this->marcarPresupuestoElegidoSiAplica($payload, (int) ($cab['requisicion_id'] ?? 0));

            $this->sincronizarComprobantesCuotas($oc->id, $payload, $uid);
            $this->regenerarCondicionesContratacion($oc->id);
            $this->ordencompraArchivoRepository->create($request, $oc->id);

            if ($sectorId) {
                Ordencompra_Historia::create([
                    'ordencompra_id' => $oc->id,
                    'sector_legajocompra_id' => $sectorId,
                    'fecha' => Carbon::now(),
                    'observacion' => 'Ingreso al legajo de compras',
                    'leyenda' => 'Sector inicial',
                    'creousuario_id' => $uid,
                ]);
            }

            $esSuscripcion = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN);
            if ($esSuscripcion) {
                $obs = $this->observacionEnvioArbolDesdePayload($payload)
                    ?: 'Alta de suscripción (OC contrato) — árbol Suscripciones';
                $r = $this->arbolaprobacionService->procesaArbolaprobacion('SU', (int) $oc->id, 'insert', [
                    'observacion_envio' => $obs,
                ]);
                if ((int) $r === 0) {
                    throw new \RuntimeException(
                        'No hay árbol de aprobación tipo Suscripciones activo para la empresa, '
                        .'o no hay nivel aplicable. Configurá el ABM de árboles antes de guardar.'
                    );
                }
            } else {
                $this->ocArbolTriggerDispatcher->dispararPorAlta(
                    (int) $oc->id,
                    $this->observacionEnvioArbolDesdePayload($payload)
                );
            }

            if (! $omitirMarcarRequisicionGeneroOc && ! empty($cab['requisicion_id'])) {
                $this->sincronizarEstadoRequisicionSegunLineasOc((int) $cab['requisicion_id'], $uid);
            }

            $this->sincronizarAnitaAlta($this->ordencompraRepository->find($oc->id));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        if ($oc) {
            $this->avisarContratoSinComSiAplica((int) $oc->id);
        }

        return ['mensaje' => 'ok', 'id' => $oc ? $oc->id : null];
    }

    public function actualizar(Request $request, int $id): array
    {
        $existente = $this->ordencompraRepository->find($id);
        if (! in_array($existente->estadoordencompra, [OrdencompraEstados::PENDIENTE, OrdencompraEstados::SUSPENDIDA], true)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede editar en estado PENDIENTE o SUSPENDIDA.'];
        }
        $contratoAnterior = $this->snapshotContratoSinCom($existente);

        $v = Validator::make($request->all(), $this->reglasCabecera());
        if ($v->fails()) {
            return ['mensaje' => 'error', 'errores' => $v->errors()->first()];
        }
        $payload = $request->all();
        try {
            $this->validarContratoImputacion($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }
        try {
            $this->asegurarComprobanteDesdeProveedor($payload);
            $this->validarComprobantesCuotas($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            ValidacionPresupuestoPartidaCapexLineas::validar(
                $payload,
                ValidacionPresupuestoPartidaCapexLineas::idsAsignadosDesdeLineas($existente->ordencompra_articulos ?? [])
            );
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        try {
            $this->validarOrigenPrecioDesdeRequisicion($payload);
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        if (($existente->estadoordencompra ?? '') === OrdencompraEstados::PENDIENTE) {
            try {
                $payload['ordencompra_id'] = $id;
                $esSuscripcionPayload = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || (bool) ($existente->es_suscripcion ?? false);
                if (! $esSuscripcionPayload) {
                    $this->arbolaprobacionService->validaOrdencompraRequestContraArbolOpcional($payload);
                }
            } catch (\RuntimeException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        $cab = $this->armaCabeceraDesdeRequest($payload, $existente->estadoordencompra, $existente->sector_legajocompra_id, $existente->creousuario_id);
        unset($cab['creousuario_id']);

        try {
            OrdencompraTratamientoMovimientosSupport::assertPuedeCambiarTratamiento(
                $id,
                $existente->tratamiento ?? null,
                $cab['tratamiento'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $oldReqId = $existente->requisicion_id ? (int) $existente->requisicion_id : null;
        $newReqId = ! empty($cab['requisicion_id']) ? (int) $cab['requisicion_id'] : null;

        // Una OC ya vinculada a requisición no puede reasignarse a otra (ni desvincularse):
        // eso reabre la requi origen como APROBADA y permite generar OCs duplicadas.
        if ($oldReqId !== null && $newReqId !== $oldReqId) {
            return [
                'mensaje' => 'error',
                'errores' => 'No se puede cambiar ni quitar la requisición de origen de una orden de compra ya vinculada. '
                    .'Si necesita ítems de otra requisición, genere una OC nueva desde esa requisición.',
            ];
        }

        if ($newReqId !== null && $newReqId !== $oldReqId) {
            try {
                $this->assertRequisicionAprobadaParaAsociarOc($newReqId);
            } catch (\InvalidArgumentException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        try {
            MovimientoStockColorTalleExclusividadSupport::validarLineas(
                $payload['articulo_ids'] ?? [],
                $payload['colores_id'] ?? [],
                $payload['talles_id'] ?? [],
            );
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update($cab, $id);
            $this->ordencompraArticuloRepository->syncFromRequest(array_merge($payload, ['fecha' => $cab['fecha']]), $id);
            $this->marcarPresupuestoElegidoSiAplica($payload, (int) ($cab['requisicion_id'] ?? 0));
            $this->sincronizarComprobantesCuotas($id, $payload, Auth::user()->id);
            $this->regenerarCondicionesContratacion($id);
            $this->ordencompraArchivoRepository->update($request, $id);

            if (($existente->estadoordencompra ?? '') === OrdencompraEstados::PENDIENTE) {
                $esSuscripcion = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN)
                    || (bool) ($existente->es_suscripcion ?? false);
                if ($esSuscripcion) {
                    $obs = $this->observacionEnvioArbolDesdePayload($payload)
                        ?: 'Actualización de suscripción — árbol Suscripciones';
                    $this->arbolaprobacionService->procesaArbolaprobacion('SU', $id, 'insert', [
                        'observacion_envio' => $obs,
                    ]);
                } else {
                    $this->ocArbolTriggerDispatcher->dispararPorActualizacion(
                        $id,
                        $this->observacionEnvioArbolDesdePayload($payload)
                    );
                }
            }

            $uidAct = Auth::user()->id;
            $reqIdsASincronizar = array_values(array_unique(array_filter([$oldReqId, $newReqId])));
            foreach ($reqIdsASincronizar as $reqIdSync) {
                $this->sincronizarEstadoRequisicionSegunLineasOc((int) $reqIdSync, $uidAct);
            }

            $this->sincronizarAnitaActualizacion($this->ordencompraRepository->find($id));

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $this->avisarContratoSinComSiAplica($id, $contratoAnterior);

        return ['mensaje' => 'ok'];
    }

    public function eliminar(int $id): bool
    {
        $oc = null;
        try {
            $oc = $this->ordencompraRepository->find($id);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return false;
        }

        $requisicionId = $oc->requisicion_id ? (int) $oc->requisicion_id : null;

        DB::beginTransaction();
        try {
            $this->sincronizarAnitaBaja($oc);
            $this->ordencompraRepository->delete($id);
            // Sync después de borrar: con las líneas aún presentes el sync podía dejar GENERO huérfano.
            if ($requisicionId) {
                $this->sincronizarEstadoRequisicionSegunLineasOc($requisicionId, Auth::user()->id);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function cambiarEstado(int $id, string $nuevoEstado, string $observacion): array
    {
        if (! OrdencompraEstados::esNombreValido($nuevoEstado)) {
            return ['mensaje' => 'error', 'errores' => 'Estado inválido.'];
        }
        $oc = $this->ordencompraRepository->find($id);

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update(['estadoordencompra' => $nuevoEstado], $id);
            $this->ordencompraEstadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $nuevoEstado,
                Auth::user()->id,
                $observacion !== '' ? $observacion : 'Cambio de estado'
            );
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function reactivarDesdeSuspendida(int $id): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if ($oc->estadoordencompra !== OrdencompraEstados::SUSPENDIDA) {
            return ['mensaje' => 'error', 'errores' => 'Solo aplica a órdenes suspendidas.'];
        }

        return $this->cambiarEstado($id, OrdencompraEstados::PENDIENTE, 'Reactivación desde suspendida a pendiente');
    }

    public function cambiarSector(
        int $id,
        int $sectorLegajocompraId,
        ?string $observacion,
        ?string $leyenda,
        ?UploadedFile $facturaPdf = null,
        bool $omitirGateYTrigger = false,
        ?int $destinatarioUsuarioId = null,
    ): array {
        $sec = Sector_Legajocompra::find($sectorLegajocompraId);
        if (! $sec) {
            return ['mensaje' => 'error', 'errores' => 'Sector inválido.'];
        }

        $ocPrev = $this->ordencompraRepository->find($id);
        if (! $ocPrev) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        $sectorAnteriorId = (int) ($ocPrev->sector_legajocompra_id ?? 0);

        if (OrdencompraLegajoGastronomiaSupport::esSectorFinalizado($sectorLegajocompraId)
            && ! OrdencompraLegajoGastronomiaSupport::puedeFinalizar($ocPrev)) {
            return ['mensaje' => 'error', 'errores' => 'El legajo solo se puede finalizar desde Pagos.'];
        }

        $exigePaquete = ! $omitirGateYTrigger
            && (
                OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorLegajocompraId)
                || $this->cambioExigePaqueteGastronomia($ocPrev, $sectorLegajocompraId)
            );

        if ($exigePaquete) {
            try {
                if ($facturaPdf) {
                    $this->legajoFacturaPdfService->adjuntarPdfAlLegajo($ocPrev, $facturaPdf);
                    $ocPrev = $this->ordencompraRepository->find($id) ?: $ocPrev;
                }

                $gate = OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar($sectorLegajocompraId)
                    ? OrdencompraEnvioCuentasAPagarGateSupport::evaluarCuentasAPagar($ocPrev)
                    : OrdencompraEnvioCuentasAPagarGateSupport::evaluar($ocPrev);
                if (! $gate['ok']) {
                    return [
                        'mensaje' => 'error',
                        'errores' => implode(' ', $gate['errores']),
                        'requiere_pdf' => $gate['requiere_pdf'],
                        'gate' => $gate,
                    ];
                }
            } catch (\Throwable $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage(), 'requiere_pdf' => true];
            }
        }

        DB::beginTransaction();
        try {
            $this->ordencompraRepository->update(['sector_legajocompra_id' => $sectorLegajocompraId], $id);
            Ordencompra_Historia::create([
                'ordencompra_id' => $id,
                'sector_legajocompra_id' => $sectorLegajocompraId,
                'fecha' => Carbon::now(),
                'observacion' => $observacion,
                'leyenda' => $leyenda,
                'creousuario_id' => Auth::user()->id,
            ]);

            if (! $omitirGateYTrigger) {
                $this->ocArbolTriggerDispatcher->dispararPorCambioSector(
                    $id,
                    $sectorAnteriorId > 0 ? $sectorAnteriorId : null,
                    $sectorLegajocompraId,
                    $observacion,
                    $destinatarioUsuarioId
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * @return array{mensaje: string, errores?: string, requiere_pdf?: bool, gate?: array<string, mixed>}
     */
    public function enviarAGastronomia(
        int $id,
        ?string $observacion,
        ?string $leyenda,
        ?UploadedFile $facturaPdf = null,
        ?int $destinatarioUsuarioId = null,
    ): array {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }

        $erroresCircuito = OrdencompraLegajoGastronomiaSupport::erroresEnvioGastronomia($oc);
        if ($erroresCircuito !== []) {
            return ['mensaje' => 'error', 'errores' => implode(' ', $erroresCircuito)];
        }

        $circuito = OrdencompraLegajoGastronomiaSupport::circuitoDeEmpresa((int) ($oc->empresa_id ?? 0));
        $sectorId = (int) $circuito['sector_disparo_id'];
        if ($sectorId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'No está configurado el sector GASTRONOMIA.'];
        }

        $preview = $this->arbolaprobacionService->firmantesEnvioGastronomiaOrdencompra($oc);
        if (! empty($preview['requiere_seleccion'])) {
            if ($destinatarioUsuarioId === null || $destinatarioUsuarioId <= 0) {
                return [
                    'mensaje' => 'seleccionar_firmante',
                    'nivel' => $preview['nivel'],
                    'firmantes' => $preview['firmantes'],
                ];
            }
            $idsValidos = array_column($preview['firmantes'], 'id');
            if (! in_array($destinatarioUsuarioId, $idsValidos, true)) {
                return [
                    'mensaje' => 'error',
                    'errores' => 'El firmante seleccionado no es válido para este nivel del árbol.',
                    'nivel' => $preview['nivel'],
                    'firmantes' => $preview['firmantes'],
                ];
            }
        }

        $obs = trim((string) $observacion);
        if ($obs === '') {
            $obs = 'Enviar a Gastronomía para autorización del legajo';
        }

        return $this->cambiarSector(
            $id,
            $sectorId,
            $obs,
            $leyenda,
            $facturaPdf,
            false,
            ($preview['requiere_seleccion'] ?? false) ? $destinatarioUsuarioId : null
        );
    }

    /**
     * @return array{mensaje: string, errores?: string, nivel?: int, firmantes?: list<array<string, mixed>>, requiere_seleccion?: bool}
     */
    public function firmantesEnvioGastronomia(int $id): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }

        $erroresCircuito = OrdencompraLegajoGastronomiaSupport::erroresEnvioGastronomia($oc);
        if ($erroresCircuito !== []) {
            return ['mensaje' => 'error', 'errores' => implode(' ', $erroresCircuito)];
        }

        try {
            $preview = $this->arbolaprobacionService->firmantesEnvioGastronomiaOrdencompra($oc);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return array_merge(['mensaje' => 'ok'], $preview);
    }

    /**
     * @return array{mensaje: string, errores?: string, requiere_pdf?: bool, gate?: array<string, mixed>}
     */
    public function enviarACuentasAPagar(
        int $id,
        ?string $observacion,
        ?string $leyenda,
        ?UploadedFile $facturaPdf = null,
    ): array {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        if (! OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarCuentasAPagar($oc)) {
            return ['mensaje' => 'error', 'errores' => 'Este legajo no se puede enviar a Cuentas a pagar.'];
        }
        $sectorId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );
        if ($sectorId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'No está configurado el sector CUENTAS A PAGAR.'];
        }
        $obs = trim((string) $observacion);
        if ($obs === '') {
            $obs = 'Enviar a Cuentas a pagar';
        }

        return $this->cambiarSector($id, $sectorId, $obs, $leyenda, $facturaPdf);
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function enviarAPagos(int $id, ?string $observacion, ?string $leyenda): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        if (! OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarPagos($oc)) {
            return ['mensaje' => 'error', 'errores' => 'El legajo debe estar en Cuentas a pagar y tener la factura cargada.'];
        }
        $sectorId = OrdencompraLegajoGastronomiaSupport::sectorPagosId();
        if ($sectorId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'No está configurado el sector PAGOS.'];
        }
        $obs = trim((string) $observacion);
        if ($obs === '') {
            $obs = 'Enviar a Pagos';
        }

        return $this->cambiarSector($id, $sectorId, $obs, $leyenda);
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function devolverACuentasAPagar(int $id, string $observacion, ?string $leyenda): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        if (! OrdencompraLegajoGastronomiaSupport::puedeDevolverACuentasAPagar($oc)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede devolver a Cuentas a pagar un legajo que está en Pagos.'];
        }
        $obs = trim($observacion);
        if ($obs === '') {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar un comentario con el motivo de la devolución.'];
        }
        $sectorId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );
        if ($sectorId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'No está configurado el sector CUENTAS A PAGAR.'];
        }

        return $this->cambiarSector($id, $sectorId, $obs, $leyenda, null, true);
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function devolverACompras(int $id, string $observacion, ?string $leyenda): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        if (! OrdencompraLegajoGastronomiaSupport::puedeDevolverACompras($oc)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede devolver a Compras un legajo que está en Cuentas a pagar.'];
        }
        $obs = trim($observacion);
        if ($obs === '') {
            return ['mensaje' => 'error', 'errores' => 'Debe indicar un comentario con el motivo de la devolución.'];
        }
        $sectorId = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS
        );
        if ($sectorId <= 0) {
            return ['mensaje' => 'error', 'errores' => 'No está configurado el sector COMPRAS.'];
        }

        $ret = $this->cambiarSector($id, $sectorId, $obs, $leyenda, null, true);
        if (($ret['mensaje'] ?? '') === 'ok') {
            try {
                app(OrdencompraDevolverAComprasNotificacionService::class)
                    ->devolver($id, $obs, trim((string) $leyenda));
            } catch (\Throwable $e) {
                // El sector ya volvió; el mail no bloquea.
            }
        }

        return $ret;
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function finalizarLegajo(int $id, ?string $observacion): array
    {
        $oc = $this->ordencompraRepository->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Orden de compra inexistente.'];
        }
        if (! OrdencompraLegajoGastronomiaSupport::puedeFinalizar($oc)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede finalizar un legajo que está en Pagos.'];
        }
        $sectorId = OrdencompraLegajoGastronomiaSupport::sectorFinalizadoId();
        $obs = trim((string) $observacion);
        if ($obs === '') {
            $obs = 'Cierre del legajo';
        }

        return $this->cambiarSector($id, $sectorId, $obs, null);
    }

    private function cambioExigePaqueteGastronomia(object $oc, int $sectorDestinoId): bool
    {
        if ($sectorDestinoId <= 0 || ! $oc instanceof Ordencompra) {
            return false;
        }
        if (! OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc)) {
            return false;
        }
        $circuito = OrdencompraLegajoGastronomiaSupport::circuitoDeEmpresa((int) ($oc->empresa_id ?? 0));

        return $circuito['sector_disparo_id'] > 0 && $circuito['sector_disparo_id'] === $sectorDestinoId;
    }

    public function leerHistoriaLegajo(int $ordencompraId)
    {
        return Ordencompra_Historia::query()
            ->where('ordencompra_id', $ordencompraId)
            ->with(['sector_legajocompras', 'usuarios'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    public function leerHistoriaEstados(int $ordencompraId)
    {
        return DB::table('ordencompra_estado')
            ->where('ordencompra_id', $ordencompraId)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();
    }

    private static function nombreEstadoRequisicionAprobada(): string
    {
        return Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
    }

    private static function nombreEstadoRequisicionGeneroOc(): string
    {
        return Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];
    }

    /** Texto de estado usado antes del nombre completo (datos históricos). */
    private static function nombreLegacyGeneroOrdenCompraRequisicion(): string
    {
        return 'GENERO OC';
    }

    private function estadoRequisicionEquivaleAGeneroOc(?string $estado): bool
    {
        if ($estado === null || $estado === '') {
            return false;
        }

        return $estado === self::nombreEstadoRequisicionGeneroOc()
            || $estado === self::nombreLegacyGeneroOrdenCompraRequisicion();
    }

    private function assertRequisicionAprobadaParaAsociarOc(int $requisicionId): void
    {
        $req = Requisicion::query()->select('id', 'estado')->find($requisicionId);
        if (! $req) {
            throw new \InvalidArgumentException('La requisición asociada no existe.');
        }
        $ok = $req->estado === self::nombreEstadoRequisicionAprobada()
            || $this->estadoRequisicionEquivaleAGeneroOc($req->estado);
        if (! $ok) {
            throw new \InvalidArgumentException('La requisición debe estar en estado APROBADA o GENERO ORDEN COMPRA para asociarla a la orden de compra.');
        }
    }

    private function registrarCambioEstadoRequisicion(int $requisicionId, string $nombreEstado, int $usuarioId, string $observacion): void
    {
        $this->requisicionRepository->update(['estado' => $nombreEstado], $requisicionId);
        $this->requisicionEstadoRepository->creaEstado(
            $requisicionId,
            Carbon::now()->toDateTimeString(),
            $nombreEstado,
            $usuarioId,
            $observacion
        );
    }

    public function sincronizarEstadoRequisicionSegunLineasOc(int $requisicionId, int $usuarioId): void
    {
        if ($requisicionId <= 0) {
            return;
        }

        $req = Requisicion::query()->select('id', 'estado')->find($requisicionId);
        if (! $req) {
            return;
        }

        if (RequisicionLineasOcSupport::todasLineasResueltas($requisicionId)) {
            if ($req->estado === self::nombreEstadoRequisicionAprobada()) {
                $this->marcarRequisicionGeneroOc($requisicionId, $usuarioId, 'Todos los ítems de la requisición fueron procesados (OC o cierre sin OC)');
            }

            return;
        }

        if ($this->estadoRequisicionEquivaleAGeneroOc($req->estado)) {
            $this->marcarRequisicionAprobadaSiGeneroOc(
                $requisicionId,
                $usuarioId,
                'Quedan ítems pendientes de orden de compra'
            );
        }
    }

    private function marcarRequisicionGeneroOc(int $requisicionId, int $usuarioId, string $observacion): void
    {
        $req = Requisicion::query()->select('id', 'estado')->find($requisicionId);
        if (! $req) {
            return;
        }
        if ($this->estadoRequisicionEquivaleAGeneroOc($req->estado)) {
            return;
        }
        if ($req->estado !== self::nombreEstadoRequisicionAprobada()) {
            return;
        }
        $this->registrarCambioEstadoRequisicion(
            $requisicionId,
            self::nombreEstadoRequisicionGeneroOc(),
            $usuarioId,
            $observacion
        );
        $this->arbolaprobacionService->anulaMovimientosArbolPendientesAbiertosRequisicion(
            $requisicionId,
            'Sin efecto (requisición en GENERO ORDEN COMPRA)'
        );
    }

    private function marcarRequisicionAprobadaSiGeneroOc(int $requisicionId, int $usuarioId, string $observacion): void
    {
        $req = Requisicion::query()->select('id', 'estado')->find($requisicionId);
        if (! $req || ! $this->estadoRequisicionEquivaleAGeneroOc($req->estado)) {
            return;
        }
        $this->registrarCambioEstadoRequisicion(
            $requisicionId,
            self::nombreEstadoRequisicionAprobada(),
            $usuarioId,
            $observacion
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function observacionEnvioArbolDesdePayload(array $payload): ?string
    {
        $obs = $this->arbolaprobacionService->normalizarObservacionEnvio(
            $payload['comentario_envio_arbol'] ?? $payload['observacion_envio'] ?? null
        );

        return $obs !== '' ? $obs : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function armaCabeceraDesdeRequest(array $payload, string $estado, ?int $sectorId, int $creoUsuarioId): array
    {
        return [
            'fecha' => $payload['fecha'],
            'fechaentrega' => $payload['fechaentrega'],
            'empresa_id' => $payload['empresa_id'],
            'requisicion_id' => ! empty($payload['requisicion_id']) ? (int) $payload['requisicion_id'] : null,
            'centrocosto_id' => $payload['centrocosto_id'],
            'comentario' => $payload['comentario'] ?? '',
            'detalle' => $payload['detalle'] ?? '',
            'lugarentrega' => $payload['lugarentrega'] ?? null,
            'transporte_id' => ! empty($payload['transporte_id']) ? (int) $payload['transporte_id'] : null,
            'tratamiento' => $payload['tratamiento'],
            'proveedor_id' => ! empty($payload['proveedor_id']) ? (int) $payload['proveedor_id'] : null,
            'condicioncompra_id' => ! empty($payload['condicioncompra_id']) ? (int) $payload['condicioncompra_id'] : null,
            'condicionentrega_id' => ! empty($payload['condicionentrega_id']) ? (int) $payload['condicionentrega_id'] : null,
            'condicionpago_id' => ! empty($payload['condicionpago_id']) ? (int) $payload['condicionpago_id'] : null,
            'descuento' => isset($payload['descuento']) && $payload['descuento'] !== ''
                ? (float) $payload['descuento']
                : null,
            'descuento_tipo' => OrdencompraDescuentoSupport::normalizarTipo(
                $payload['descuento_tipo'] ?? null
            ),
            'estadoordencompra' => $estado,
            'sector_legajocompra_id' => $sectorId,
            'creousuario_id' => $creoUsuarioId,
        ] + $this->armaContratoDesdeRequest($payload);
    }

    /**
     * Contrato / OC abierta: vigencia, tope y datos de renovación automática.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function armaContratoDesdeRequest(array $payload): array
    {
        $esContrato = filter_var($payload['es_contrato'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (! $esContrato) {
            return [
                'es_contrato' => false,
                'es_suscripcion' => false,
                'suscripcion_nombre' => null,
                'suscripcion_periodicidad' => null,
                'suscripcion_monto_periodo' => null,
                'suscripcion_tolerancia_pct' => null,
                'suscripcion_tarjeta_ult4' => null,
                'suscripcion_area' => null,
                'suscripcion_solicitante' => null,
                'suscripcion_borrador' => false,
                'contrato_vigencia_desde' => null,
                'contrato_vigencia_hasta' => null,
                'contrato_monto_tope' => null,
                'contrato_moneda_id' => null,
                'contrato_auto_renovable' => false,
                'contrato_dias_preaviso' => null,
                'contrato_dias_aviso' => null,
                'contrato_responsable_id' => null,
                'contrato_requiere_recepcion' => true,
                'contrato_imputacion_contable' => null,
                'contrato_cuentacontable_id' => null,
                'contrato_periodo_servicio' => null,
                'contrato_requiere_validacion_abono' => false,
                'contrato_validacion_plantilla_id' => null,
                'contrato_exige_ingresos' => false,
                'contrato_minimo_ingresos' => null,
            ];
        }

        $autoRenovable = filter_var($payload['contrato_auto_renovable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $diasAviso = trim((string) ($payload['contrato_dias_aviso'] ?? ''));
        $requiereRecepcion = filter_var($payload['contrato_requiere_recepcion'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $imputacion = OrdencompraContratoRutaFacturaSupport::normalizarImputacion(
            $payload['contrato_imputacion_contable'] ?? null
        );
        $cuentaId = ! empty($payload['contrato_cuentacontable_id'])
            ? (int) $payload['contrato_cuentacontable_id']
            : 0;
        if ($requiereRecepcion || $imputacion !== OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL) {
            $cuentaId = 0;
        }

        $esSuscripcion = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $suscripcion = $this->armaSuscripcionDesdeRequest($payload, $esSuscripcion);

        $montoTope = isset($payload['contrato_monto_tope']) && $payload['contrato_monto_tope'] !== ''
            ? (float) $payload['contrato_monto_tope']
            : null;
        if ($esSuscripcion && ($montoTope === null || $montoTope <= 0)
            && isset($suscripcion['suscripcion_monto_periodo'])
            && (float) $suscripcion['suscripcion_monto_periodo'] > 0
        ) {
            $montoTope = SuscripcionSupport::topeAutorizado(
                (float) $suscripcion['suscripcion_monto_periodo'],
                (float) ($suscripcion['suscripcion_tolerancia_pct'] ?? SuscripcionSupport::TOLERANCIA_DEFAULT_PCT)
            );
        }

        return [
            'es_contrato' => true,
            'contrato_vigencia_desde' => ($payload['contrato_vigencia_desde'] ?? null) ?: null,
            'contrato_vigencia_hasta' => ($payload['contrato_vigencia_hasta'] ?? null) ?: null,
            'contrato_monto_tope' => $montoTope,
            'contrato_moneda_id' => ! empty($payload['contrato_moneda_id']) ? (int) $payload['contrato_moneda_id'] : null,
            'contrato_auto_renovable' => $autoRenovable,
            'contrato_dias_preaviso' => $autoRenovable && ! empty($payload['contrato_dias_preaviso'])
                ? (int) $payload['contrato_dias_preaviso']
                : null,
            'contrato_dias_aviso' => $diasAviso !== '' ? $diasAviso : null,
            'contrato_responsable_id' => ! empty($payload['contrato_responsable_id'])
                ? (int) $payload['contrato_responsable_id']
                : null,
            'contrato_requiere_recepcion' => $requiereRecepcion,
            'contrato_imputacion_contable' => $requiereRecepcion ? null : $imputacion,
            'contrato_cuentacontable_id' => $cuentaId > 0 ? $cuentaId : null,
            'contrato_periodo_servicio' => ContratoPeriodoServicioSupport::normalizar(
                $payload['contrato_periodo_servicio'] ?? null
            ),
            'contrato_requiere_validacion_abono' => filter_var(
                $payload['contrato_requiere_validacion_abono'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ) || filter_var($payload['contrato_exige_ingresos'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'contrato_validacion_plantilla_id' => ! empty($payload['contrato_validacion_plantilla_id'])
                ? (int) $payload['contrato_validacion_plantilla_id']
                : null,
            'contrato_exige_ingresos' => filter_var($payload['contrato_exige_ingresos'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'contrato_minimo_ingresos' => ! empty($payload['contrato_minimo_ingresos'])
                ? max(1, (int) $payload['contrato_minimo_ingresos'])
                : 1,
        ] + $suscripcion;
    }

    /**
     * Campos del módulo Suscripciones cuando la OC contrato se marca como tal.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function armaSuscripcionDesdeRequest(array $payload, bool $esSuscripcion): array
    {
        if (! $esSuscripcion) {
            return [
                'es_suscripcion' => false,
                'suscripcion_nombre' => null,
                'suscripcion_periodicidad' => null,
                'suscripcion_monto_periodo' => null,
                'suscripcion_tolerancia_pct' => null,
                'suscripcion_tarjeta_ult4' => null,
                'suscripcion_area' => null,
                'suscripcion_solicitante' => null,
                // No pisar borrador si se desmarca accidentalmente en edición: solo limpia flag de tipo.
                'suscripcion_borrador' => false,
            ];
        }

        $tol = isset($payload['suscripcion_tolerancia_pct']) && $payload['suscripcion_tolerancia_pct'] !== ''
            ? (float) $payload['suscripcion_tolerancia_pct']
            : SuscripcionSupport::TOLERANCIA_DEFAULT_PCT;
        $tarjeta = preg_replace('/\D/', '', (string) ($payload['suscripcion_tarjeta_ult4'] ?? ''));
        $tarjeta = $tarjeta !== '' ? substr($tarjeta, -4) : null;

        // Área = nombre del centro de costo (el CC de la OC es la fuente de verdad).
        $area = trim((string) ($payload['suscripcion_area'] ?? ''));
        if ($area === '') {
            $ccId = (int) ($payload['centrocosto_id'] ?? 0);
            if ($ccId > 0) {
                $cc = Centrocosto::query()->find($ccId);
                $area = $cc ? trim((string) ($cc->nombre ?? '')) : '';
            }
        }

        return [
            'es_suscripcion' => true,
            'suscripcion_nombre' => trim((string) ($payload['suscripcion_nombre'] ?? $payload['detalle'] ?? '')) ?: null,
            'suscripcion_periodicidad' => SuscripcionSupport::normalizarPeriodicidad(
                $payload['suscripcion_periodicidad'] ?? null
            ),
            'suscripcion_monto_periodo' => isset($payload['suscripcion_monto_periodo']) && $payload['suscripcion_monto_periodo'] !== ''
                ? (float) $payload['suscripcion_monto_periodo']
                : null,
            'suscripcion_tolerancia_pct' => $tol,
            'suscripcion_tarjeta_ult4' => $tarjeta,
            'suscripcion_area' => $area !== '' ? mb_substr($area, 0, 80) : null,
            'suscripcion_solicitante' => trim((string) ($payload['suscripcion_solicitante'] ?? '')) ?: null,
            'suscripcion_borrador' => filter_var($payload['suscripcion_borrador'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validarContratoImputacion(array $payload): void
    {
        $esContrato = filter_var($payload['es_contrato'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $esContrato) {
            return;
        }

        $esSuscripcion = filter_var($payload['es_suscripcion'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($esSuscripcion) {
            $nombre = trim((string) ($payload['suscripcion_nombre'] ?? ''));
            if ($nombre === '') {
                throw new \InvalidArgumentException('Indique el nombre de la suscripción.');
            }
            if ((int) ($payload['centrocosto_id'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Indique el área (centro de costo) de la suscripción.');
            }
            $tarjeta = preg_replace('/\D/', '', (string) ($payload['suscripcion_tarjeta_ult4'] ?? ''));
            if (strlen((string) $tarjeta) < 4) {
                throw new \InvalidArgumentException('Indique los últimos 4 dígitos de la tarjeta corporativa.');
            }
            if (! isset($payload['suscripcion_monto_periodo']) || (float) $payload['suscripcion_monto_periodo'] <= 0) {
                throw new \InvalidArgumentException('Indique el monto por período de la suscripción.');
            }
        }

        $requiereRecepcion = filter_var($payload['contrato_requiere_recepcion'] ?? true, FILTER_VALIDATE_BOOLEAN);
        if ($requiereRecepcion) {
            return;
        }

        $imputacion = OrdencompraContratoRutaFacturaSupport::normalizarImputacion(
            $payload['contrato_imputacion_contable'] ?? null
        );
        if ($imputacion !== OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL) {
            return;
        }

        $cuentaId = (int) ($payload['contrato_cuentacontable_id'] ?? 0);
        if ($cuentaId <= 0) {
            throw new \InvalidArgumentException(
                'El contrato imputa el neto con una cuenta del contrato. Indique la cuenta contable a imputar.'
            );
        }

        $cuenta = Cuentacontable::query()->find($cuentaId);
        if (! $cuenta) {
            throw new \InvalidArgumentException('La cuenta contable del contrato no existe.');
        }

        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId > 0 && (int) ($cuenta->empresa_id ?? 0) !== $empresaId) {
            throw new \InvalidArgumentException(
                'La cuenta contable del contrato debe pertenecer a la misma empresa de la orden de compra.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function reglasCabecera(): array
    {
        return [
            'fecha' => 'required|date',
            'fechaentrega' => 'required|date',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'comentario' => 'nullable|string|max:255',
            'comentario_envio_arbol' => 'nullable|string|max:255',
            'detalle' => 'required|string',
            'tratamiento' => ['required', 'string', 'max:50', Rule::in(array_column(Ordencompra::$enumTratamientoCompra, 'nombre'))],
            'requisicion_id' => 'nullable|integer|exists:requisicion,id',
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'transporte_id' => 'nullable|integer|exists:transporte,id',
            'condicioncompra_id' => 'nullable|integer|exists:condicioncompra,id',
            'condicionentrega_id' => 'nullable|integer|exists:condicionentrega,id',
            'condicionpago_id' => 'nullable|integer|exists:condicionpago,id',
            'descuento' => 'nullable|numeric|min:0',
            'descuento_tipo' => 'nullable|string|in:porcentaje,importe',
            'lugarentrega' => 'nullable|string|max:255',
            'es_contrato' => 'nullable|boolean',
            'contrato_vigencia_desde' => 'nullable|date',
            'contrato_vigencia_hasta' => 'nullable|date|after_or_equal:contrato_vigencia_desde',
            'contrato_monto_tope' => 'nullable|numeric|min:0',
            'contrato_moneda_id' => 'nullable|integer|exists:moneda,id',
            'contrato_auto_renovable' => 'nullable|boolean',
            'contrato_dias_preaviso' => 'nullable|integer|min:0|max:365',
            'contrato_dias_aviso' => ['nullable', 'string', 'max:60', 'regex:/^\s*\d{1,3}(\s*,\s*\d{1,3})*\s*$/'],
            'contrato_responsable_id' => 'nullable|integer|exists:usuario,id',
            'contrato_requiere_recepcion' => 'nullable|boolean',
            'contrato_imputacion_contable' => 'nullable|string|in:articulos,manual',
            'contrato_cuentacontable_id' => 'nullable|integer|exists:cuentacontable,id',
            'contrato_periodo_servicio' => 'nullable|string|in:mes_vencido,mismo_mes',
            'contrato_requiere_validacion_abono' => 'nullable|boolean',
            'contrato_validacion_plantilla_id' => 'nullable|integer|exists:validacion_abono_plantilla,id',
            'contrato_exige_ingresos' => 'nullable|boolean',
            'contrato_minimo_ingresos' => 'nullable|integer|min:1|max:99',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    private function listaComprobantesDesdePayload(array $payload): array
    {
        $raw = $payload['comprobantes_json'] ?? '[]';
        if (is_array($raw)) {
            return $raw;
        }
        $lista = json_decode(trim((string) $raw), true) ?: [];

        return is_array($lista) ? $lista : [];
    }

    /**
     * Precarga el primer comprobante a venir heredando la condición de pago del proveedor
     * y al menos una cuota con la forma de pago cargada en el ABM del proveedor.
     *
     * Si el payload ya trae comprobantes (form u wizard), respeta lo cargado.
     * Si el proveedor no tiene condición / forma de pago, asume «Contado» del maestro
     * (lo crea si aún no existe, típico en entornos nuevos como El Bierzo).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    private function asegurarComprobanteDesdeProveedor(array &$payload): void
    {
        if ($this->listaComprobantesDesdePayload($payload) !== []) {
            return;
        }

        $proveedorId = (int) ($payload['proveedor_id'] ?? 0);
        if ($proveedorId <= 0) {
            throw new \InvalidArgumentException(
                'No se puede grabar la orden de compra sin comprobante a venir asociado. '
                .'Seleccione un proveedor.'
            );
        }

        $proveedor = $this->proveedorRepository->find($proveedorId);
        if (! $proveedor) {
            throw new \InvalidArgumentException('El proveedor indicado no existe.');
        }

        $condicionpagoId = (int) ($payload['condicionpago_id'] ?? 0);
        if ($condicionpagoId <= 0) {
            $condicionpagoId = (int) ($proveedor->condicionpago_id ?? 0);
        }
        $condicionpagoId = OrdencompraCondicionPagoDefaultSupport::resolverCondicionpagoId($condicionpagoId);
        if ((int) ($payload['condicionpago_id'] ?? 0) <= 0) {
            $payload['condicionpago_id'] = $condicionpagoId;
        }

        $formapagoId = 0;
        foreach (($proveedor->proveedor_formapagos ?? []) as $fp) {
            $fid = (int) ($fp->formapago_id ?? 0);
            if ($fid > 0) {
                $formapagoId = $fid;
                break;
            }
        }
        $formapagoId = OrdencompraCondicionPagoDefaultSupport::resolverFormapagoId($formapagoId);

        $totales = OrdencompraTotalesResumen::desdeRequest($payload, $this->cotizacionQuery, $this->impuestoService);
        $montoTotal = round((float) ($totales['total'] ?? 0), 2);
        $monedaId = (int) ($totales['moneda_id'] ?? 1);
        if ($montoTotal <= 0) {
            throw new \InvalidArgumentException(
                'No se puede precargar el comprobante a venir: la orden de compra no tiene importe. '
                .'Cargue al menos un ítem con cantidad y precio.'
            );
        }
        $monedaId = $monedaId > 0 ? $monedaId : 1;

        $fecha = substr((string) ($payload['fecha'] ?? date('Y-m-d')), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $cuotas = $this->sugerirCuotasDesdeCondicionpago($condicionpagoId, $fecha, $montoTotal, $monedaId);
        if ($cuotas === []) {
            $cuotas = [[
                'fechavencimiento' => $fecha,
                'monto' => $montoTotal,
                'moneda_id' => $monedaId,
                'cotizacion' => 1.0,
                'formapago_id' => $formapagoId,
                'detalle' => 'Cuota 1',
            ]];
        }

        $suma = 0.0;
        foreach ($cuotas as &$q) {
            $q['monto'] = round((float) ($q['monto'] ?? 0), 2);
            $q['moneda_id'] = $monedaId;
            $q['formapago_id'] = $formapagoId;
            $suma += $q['monto'];
        }
        unset($q);
        $dif = round($montoTotal - $suma, 2);
        if (abs($dif) >= 0.01) {
            $ultimo = count($cuotas) - 1;
            $cuotas[$ultimo]['monto'] = round((float) $cuotas[$ultimo]['monto'] + $dif, 2);
        }

        $payload['comprobantes_json'] = json_encode([[
            'tipocomprobante' => 'FACTURA',
            'fechavencimiento' => $fecha,
            'monto' => $montoTotal,
            'moneda_id' => $monedaId,
            'cotizacion' => null,
            'detalle' => null,
            'cantidadcuota' => count($cuotas),
            'condicionpago_id' => $condicionpagoId,
            'cuotas' => array_values($cuotas),
        ]]);
    }

    /**
     * Genera y persiste el comprobante "a venir" por defecto para una OC ya existente que no
     * tiene comprobantes cargados, tomando la condición de pago y forma de pago del proveedor
     * (misma lógica que la precarga del CRUD). No escribe en Anita.
     *
     * @return bool true si generó el comprobante; false si la OC ya tenía comprobantes.
     *
     * @throws \InvalidArgumentException cuando faltan datos para precargar (OC sin importe, etc.)
     */
    public function generarComprobanteDefaultDesdeProveedor(int $ordencompraId, ?int $creousuarioId = null): bool
    {
        $oc = Ordencompra::with([
            'ordencompra_articulos.monedas',
            'ordencompra_articulos.articulos',
            'ordencompra_comprobantes',
            'proveedores.proveedor_formapagos',
        ])->find($ordencompraId);

        if (! $oc) {
            throw new \InvalidArgumentException("La orden de compra id {$ordencompraId} no existe.");
        }
        if ($oc->ordencompra_comprobantes->isNotEmpty()) {
            return false;
        }

        $proveedor = $oc->proveedores;
        if (! $proveedor) {
            throw new \InvalidArgumentException('La orden de compra no tiene proveedor asociado.');
        }

        $condicionpagoId = (int) ($oc->condicionpago_id ?? 0);
        if ($condicionpagoId <= 0) {
            $condicionpagoId = (int) ($proveedor->condicionpago_id ?? 0);
        }
        $condicionpagoId = OrdencompraCondicionPagoDefaultSupport::resolverCondicionpagoId($condicionpagoId);

        $formapagoId = 0;
        foreach (($proveedor->proveedor_formapagos ?? []) as $fp) {
            $fid = (int) ($fp->formapago_id ?? 0);
            if ($fid > 0) {
                $formapagoId = $fid;
                break;
            }
        }
        $formapagoId = OrdencompraCondicionPagoDefaultSupport::resolverFormapagoId($formapagoId);

        $totales = OrdencompraTotalesResumen::desdeModelo($oc, $this->cotizacionQuery, $this->impuestoService);
        $montoTotal = round((float) ($totales['total'] ?? 0), 2);
        $monedaId = (int) ($totales['moneda_id'] ?? 1);
        $monedaId = $monedaId > 0 ? $monedaId : 1;
        if ($montoTotal <= 0) {
            throw new \InvalidArgumentException('La orden de compra no tiene importe (sin ítems con cantidad y precio).');
        }

        $fecha = substr((string) ($oc->fecha ?? date('Y-m-d')), 0, 10);
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        $cuotas = $this->sugerirCuotasDesdeCondicionpago($condicionpagoId, $fecha, $montoTotal, $monedaId);
        if ($cuotas === []) {
            $cuotas = [[
                'fechavencimiento' => $fecha,
                'monto' => $montoTotal,
                'moneda_id' => $monedaId,
                'cotizacion' => 1.0,
                'formapago_id' => $formapagoId,
                'detalle' => 'Cuota 1',
            ]];
        }

        $suma = 0.0;
        foreach ($cuotas as &$q) {
            $q['monto'] = round((float) ($q['monto'] ?? 0), 2);
            $q['moneda_id'] = $monedaId;
            $q['formapago_id'] = $formapagoId;
            $suma += $q['monto'];
        }
        unset($q);
        $dif = round($montoTotal - $suma, 2);
        if (abs($dif) >= 0.01) {
            $ultimo = count($cuotas) - 1;
            $cuotas[$ultimo]['monto'] = round((float) $cuotas[$ultimo]['monto'] + $dif, 2);
        }

        $uid = $creousuarioId ?? (int) ($oc->creousuario_id ?? 0);
        if ($uid <= 0) {
            $uid = (int) (Auth::id() ?? 0);
        }

        DB::transaction(function () use ($oc, $fecha, $montoTotal, $monedaId, $condicionpagoId, $cuotas, $uid) {
            $comp = Ordencompra_Comprobante::create([
                'ordencompra_id' => (int) $oc->id,
                'tipocomprobante' => 'FACTURA',
                'fechavencimiento' => $fecha,
                'monto' => $montoTotal,
                'moneda_id' => $monedaId,
                'cotizacion' => null,
                'detalle' => null,
                'cantidadcuota' => count($cuotas),
                'condicionpago_id' => $condicionpagoId,
                'creousuario_id' => $uid,
            ]);

            foreach ($cuotas as $q) {
                Ordencompra_Comprobante_Cuota::create([
                    'ordencompra_comprobante_id' => (int) $comp->id,
                    'fechavencimiento' => (string) ($q['fechavencimiento'] ?? $fecha),
                    'monto' => (float) ($q['monto'] ?? 0),
                    'moneda_id' => (int) ($q['moneda_id'] ?? $monedaId),
                    'cotizacion' => isset($q['cotizacion']) ? (float) $q['cotizacion'] : null,
                    'formapago_id' => max(1, (int) ($q['formapago_id'] ?? 1)),
                    'detalle' => $q['detalle'] ?? null,
                    'creousuario_id' => $uid,
                ]);
            }

            $this->regenerarCondicionesContratacion((int) $oc->id);
        });

        return true;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    private function validarComprobantesCuotas(array $payload): void
    {
        $lista = $this->listaComprobantesDesdePayload($payload);
        if ($lista === []) {
            throw new \InvalidArgumentException(
                'No se puede grabar la orden de compra sin al menos un comprobante a venir asociado.'
            );
        }
        foreach ($lista as $idx => $c) {
            if (! is_array($c)) {
                continue;
            }
            $cuotas = $c['cuotas'] ?? [];
            if (! is_array($cuotas) || count($cuotas) === 0) {
                throw new \InvalidArgumentException(
                    'Comprobante #'.(((int) $idx) + 1).': debe tener al menos una cuota con forma de pago.'
                );
            }
            foreach ($cuotas as $q) {
                if (is_array($q) && (int) ($q['formapago_id'] ?? 0) <= 0) {
                    throw new \InvalidArgumentException(
                        'Comprobante #'.(((int) $idx) + 1).': todas las cuotas deben tener forma de pago.'
                    );
                }
            }
            $montoComp = (float) ($c['monto'] ?? 0);
            $monedaComp = (int) ($c['moneda_id'] ?? 1);
            $sum = 0.0;
            foreach ($cuotas as $q) {
                if (! is_array($q)) {
                    continue;
                }
                $mid = (int) ($q['moneda_id'] ?? $monedaComp);
                if ($mid !== $monedaComp) {
                    throw new \InvalidArgumentException(
                        'Comprobante #'.(((int) $idx) + 1).': todas las cuotas deben estar en la misma moneda que el comprobante.'
                    );
                }
                $sum += (float) ($q['monto'] ?? 0);
            }
            if (abs($sum - $montoComp) > 0.02) {
                throw new \InvalidArgumentException(
                    'Comprobante #'.(((int) $idx) + 1).': la suma de cuotas ('.number_format($sum, 2, ',', '.').') no coincide con el monto del comprobante ('.number_format($montoComp, 2, ',', '.').').'
                );
            }
        }
    }

    /**
     * Upsert de comprobantes a venir y cuotas. No hace delete-all/recreate:
     * las facturas de proveedor referencian ordencompra_comprobante_id y
     * ordencompra_comprobante_cuota_id (FK RESTRICT).
     *
     * @param  array<string, mixed>  $payload
     */
    private function sincronizarComprobantesCuotas(int $ordencompraId, array $payload, int $creousuarioId): void
    {
        $lista = $this->listaComprobantesDesdePayload($payload);

        $existentes = Ordencompra_Comprobante::query()
            ->where('ordencompra_id', $ordencompraId)
            ->with(['ordencompra_comprobante_cuotas' => static function ($q) {
                $q->orderBy('id');
            }])
            ->orderBy('id')
            ->get();

        $porId = $existentes->keyBy('id');
        /** @var list<Ordencompra_Comprobante> $sinAsignar */
        $sinAsignar = $existentes->values()->all();
        $idsCompKeep = [];

        foreach ($lista as $c) {
            if (! is_array($c)) {
                continue;
            }

            $attrs = [
                'tipocomprobante' => (string) ($c['tipocomprobante'] ?? 'FACTURA'),
                'fechavencimiento' => (string) ($c['fechavencimiento'] ?? date('Y-m-d')),
                'monto' => (float) ($c['monto'] ?? 0),
                'moneda_id' => (int) ($c['moneda_id'] ?? 1),
                'cotizacion' => isset($c['cotizacion']) ? (float) $c['cotizacion'] : null,
                'detalle' => $c['detalle'] ?? null,
                'cantidadcuota' => isset($c['cantidadcuota']) ? (int) $c['cantidadcuota'] : null,
                'condicionpago_id' => ! empty($c['condicionpago_id']) ? (int) $c['condicionpago_id'] : null,
                'estado' => OrdencompraComprobanteEstados::normalizar(
                    isset($c['estado']) ? (string) $c['estado'] : null
                ),
            ];

            $compIdPayload = (int) ($c['id'] ?? 0);
            $comp = null;
            if ($compIdPayload > 0 && $porId->has($compIdPayload)) {
                $comp = $porId->get($compIdPayload);
                $comp->fill($attrs)->save();
                $sinAsignar = array_values(array_filter(
                    $sinAsignar,
                    static fn (Ordencompra_Comprobante $x) => (int) $x->id !== $compIdPayload
                ));
            } elseif ($sinAsignar !== []) {
                // Fallback: payload sin id (pantallas viejas) → reutilizar por orden de id.
                $comp = array_shift($sinAsignar);
                $comp->fill($attrs)->save();
            } else {
                $comp = Ordencompra_Comprobante::create(array_merge($attrs, [
                    'ordencompra_id' => $ordencompraId,
                    'creousuario_id' => $creousuarioId,
                ]));
            }

            $idsCompKeep[] = (int) $comp->id;
            $this->sincronizarCuotasDeComprobante($comp, $c['cuotas'] ?? [], $creousuarioId);
        }

        $idsCompBorrar = $existentes
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->diff($idsCompKeep)
            ->values()
            ->all();

        if ($idsCompBorrar !== []) {
            $this->assertComprobantesOcEliminables($idsCompBorrar);
            $cuotasBorrar = Ordencompra_Comprobante_Cuota::query()
                ->whereIn('ordencompra_comprobante_id', $idsCompBorrar)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $this->assertCuotasOcEliminables($cuotasBorrar);
            if ($cuotasBorrar !== []) {
                Ordencompra_Comprobante_Cuota::query()->whereIn('id', $cuotasBorrar)->delete();
            }
            Ordencompra_Comprobante::query()->whereIn('id', $idsCompBorrar)->delete();
        }
    }

    private function sincronizarCuotasDeComprobante(
        Ordencompra_Comprobante $comp,
        mixed $cuotasPayload,
        int $creousuarioId
    ): void {
        $cuotas = is_array($cuotasPayload) ? $cuotasPayload : [];
        $existentes = $comp->ordencompra_comprobante_cuotas
            ? $comp->ordencompra_comprobante_cuotas->sortBy('id')->values()
            : Ordencompra_Comprobante_Cuota::query()
                ->where('ordencompra_comprobante_id', $comp->id)
                ->orderBy('id')
                ->get();

        $porId = $existentes->keyBy('id');
        /** @var list<Ordencompra_Comprobante_Cuota> $sinAsignar */
        $sinAsignar = $existentes->values()->all();
        $idsKeep = [];

        foreach ($cuotas as $q) {
            if (! is_array($q)) {
                continue;
            }

            $attrs = [
                'fechavencimiento' => (string) ($q['fechavencimiento'] ?? $comp->fechavencimiento),
                'monto' => (float) ($q['monto'] ?? 0),
                'moneda_id' => (int) ($q['moneda_id'] ?? $comp->moneda_id),
                'cotizacion' => isset($q['cotizacion']) ? (float) $q['cotizacion'] : null,
                'formapago_id' => max(1, (int) ($q['formapago_id'] ?? 1)),
                'detalle' => $q['detalle'] ?? null,
            ];

            $cuotaIdPayload = (int) ($q['id'] ?? 0);
            $cuota = null;
            if ($cuotaIdPayload > 0 && $porId->has($cuotaIdPayload)) {
                $cuota = $porId->get($cuotaIdPayload);
                $cuota->fill($attrs)->save();
                $sinAsignar = array_values(array_filter(
                    $sinAsignar,
                    static fn (Ordencompra_Comprobante_Cuota $x) => (int) $x->id !== $cuotaIdPayload
                ));
            } elseif ($sinAsignar !== []) {
                $cuota = array_shift($sinAsignar);
                $cuota->fill($attrs)->save();
            } else {
                $cuota = Ordencompra_Comprobante_Cuota::create(array_merge($attrs, [
                    'ordencompra_comprobante_id' => (int) $comp->id,
                    'creousuario_id' => $creousuarioId,
                ]));
            }

            $idsKeep[] = (int) $cuota->id;
        }

        $idsBorrar = $existentes
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->diff($idsKeep)
            ->values()
            ->all();

        if ($idsBorrar !== []) {
            $this->assertCuotasOcEliminables($idsBorrar);
            Ordencompra_Comprobante_Cuota::query()->whereIn('id', $idsBorrar)->delete();
        }
    }

    /**
     * @param  list<int>  $cuotaIds
     *
     * @throws \InvalidArgumentException
     */
    private function assertCuotasOcEliminables(array $cuotaIds): void
    {
        $cuotaIds = array_values(array_filter(array_map('intval', $cuotaIds), static fn (int $id) => $id > 0));
        if ($cuotaIds === []) {
            return;
        }

        $referidas = DB::table('comprobante_proveedor_cuota')
            ->whereIn('ordencompra_comprobante_cuota_id', $cuotaIds)
            ->distinct()
            ->pluck('comprobante_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($referidas !== []) {
            throw new \InvalidArgumentException(
                'No se pueden eliminar o regenerar cuotas de la OC que ya están vinculadas a facturas de proveedor (ids '
                .implode(', ', $referidas)
                .'). Modifique fechas/montos/forma de pago sobre las cuotas existentes, o anule/elimine la factura vinculada.'
            );
        }
    }

    /**
     * @param  list<int>  $comprobanteIds
     *
     * @throws \InvalidArgumentException
     */
    private function assertComprobantesOcEliminables(array $comprobanteIds): void
    {
        $comprobanteIds = array_values(array_filter(array_map('intval', $comprobanteIds), static fn (int $id) => $id > 0));
        if ($comprobanteIds === []) {
            return;
        }

        $referidos = DB::table('comprobante_proveedor')
            ->whereIn('ordencompra_comprobante_id', $comprobanteIds)
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();

        if ($referidos !== []) {
            throw new \InvalidArgumentException(
                'No se pueden eliminar comprobantes a venir de la OC que ya están vinculados a facturas de proveedor (ids '
                .implode(', ', $referidos)
                .').'
            );
        }
    }

    private function regenerarCondicionesContratacion(int $ordencompraId): void
    {
        $oc = Ordencompra::with([
            'ordencompra_comprobantes.monedas',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.formapagos',
            'ordencompra_comprobantes.ordencompra_comprobante_cuotas.monedas',
        ])->find($ordencompraId);
        if (! $oc) {
            return;
        }
        $texto = OrdencompraCondicionesContratacionGenerator::desdeModelo($oc);
        $this->ordencompraRepository->update(['condiciones_contratacion' => $texto], $ordencompraId);
    }

    /**
     * Si la línea viene de requisición con precio cargado pero sin origen explícito, asume REQUISICION.
     *
     * @param  array<string, mixed>  $payload
     */
    private function normalizarOrigenPrecioRequisicionEnPayload(array &$payload): void
    {
        $reqId = ! empty($payload['requisicion_id']) ? (int) $payload['requisicion_id'] : 0;
        if ($reqId <= 0) {
            return;
        }

        $articuloIds = $payload['articulo_ids'] ?? [];
        $cantidades = $payload['cantidades'] ?? [];
        $reqArtIds = $payload['requisicion_articulo_ids'] ?? [];
        $tipos = is_array($payload['precio_origen_tipos'] ?? null) ? $payload['precio_origen_tipos'] : [];
        $refs = is_array($payload['precio_origen_ref_ids'] ?? null) ? $payload['precio_origen_ref_ids'] : [];
        $etiquetas = is_array($payload['precio_origen_etiquetas'] ?? null) ? $payload['precio_origen_etiquetas'] : [];
        $precios = is_array($payload['precios'] ?? null) ? $payload['precios'] : [];

        $n = is_array($articuloIds) ? count($articuloIds) : 0;
        for ($i = 0; $i < $n; $i++) {
            $aid = $articuloIds[$i] ?? null;
            $cant = (float) ($cantidades[$i] ?? 0);
            if ($aid === null || $aid === '' || $cant <= 0) {
                continue;
            }
            $rid = isset($reqArtIds[$i]) ? (int) $reqArtIds[$i] : 0;
            if ($rid <= 0) {
                continue;
            }
            $tipo = trim((string) ($tipos[$i] ?? ''));
            if ($tipo !== '') {
                continue;
            }

            $ra = Requisicion_Articulo::query()
                ->where('id', $rid)
                ->where('requisicion_id', $reqId)
                ->first();
            if ($ra === null) {
                continue;
            }

            $precioLinea = (float) ($precios[$i] ?? 0);
            if ($precioLinea <= 0 && (float) $ra->precio > 0) {
                $precioLinea = (float) $ra->precio;
            }
            if ($precioLinea <= 0) {
                continue;
            }

            $tipos[$i] = OrdencompraOpcionesPrecioService::ORIGEN_REQUISICION;
            $refs[$i] = (string) $ra->id;
            $etiquetas[$i] = 'Precio cargado en la requisición';
            if (empty($precios[$i])) {
                $precios[$i] = $precioLinea;
            }
        }

        $payload['precio_origen_tipos'] = $tipos;
        $payload['precio_origen_ref_ids'] = $refs;
        $payload['precio_origen_etiquetas'] = $etiquetas;
        $payload['precios'] = $precios;
    }

    /**
     * OC desde requisición: proveedor obligatorio (puede venir de lista/presupuesto o elegirse al final del wizard).
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    private function validarProveedorObligatorioDesdeRequisicion(array $payload): void
    {
        $reqId = ! empty($payload['requisicion_id']) ? (int) $payload['requisicion_id'] : 0;
        if ($reqId <= 0) {
            return;
        }
        $pid = (int) ($payload['proveedor_id'] ?? 0);
        if ($pid <= 0) {
            throw new \InvalidArgumentException('Debe indicar el proveedor de la orden de compra.');
        }
    }

    /**
     * Si la OC está ligada a una requisición, cada línea que traiga requisicion_articulo_id debe tener origen de precio explícito.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws \InvalidArgumentException
     */
    private function validarOrigenPrecioDesdeRequisicion(array $payload): void
    {
        $reqId = ! empty($payload['requisicion_id']) ? (int) $payload['requisicion_id'] : 0;
        if ($reqId <= 0) {
            return;
        }

        $articuloIds = $payload['articulo_ids'] ?? [];
        $cantidades = $payload['cantidades'] ?? [];
        $reqArtIds = $payload['requisicion_articulo_ids'] ?? [];
        $tipos = $payload['precio_origen_tipos'] ?? [];
        $refs = $payload['precio_origen_ref_ids'] ?? [];

        $validTipos = [
            OrdencompraOpcionesPrecioService::ORIGEN_LISTA,
            OrdencompraOpcionesPrecioService::ORIGEN_PRESUPUESTO,
            OrdencompraOpcionesPrecioService::ORIGEN_REQUISICION,
        ];

        $n = is_array($articuloIds) ? count($articuloIds) : 0;
        for ($i = 0; $i < $n; $i++) {
            $aid = $articuloIds[$i] ?? null;
            $cant = (float) ($cantidades[$i] ?? 0);
            if ($aid === null || $aid === '' || $cant <= 0) {
                continue;
            }
            $rid = isset($reqArtIds[$i]) ? (int) $reqArtIds[$i] : 0;
            if ($rid <= 0) {
                continue;
            }
            $tipo = trim((string) ($tipos[$i] ?? ''));
            if ($tipo === '' || ! in_array($tipo, $validTipos, true)) {
                throw new \InvalidArgumentException(
                    'Línea '.($i + 1).': debe elegir el origen del precio (lista de proveedor, presupuesto o requisición) con el botón «Origen precio».'
                );
            }
        }

        $pids = OrdencompraOpcionesPrecioService::presupuestoIdsDistintosUsados(
            is_array($tipos) ? $tipos : [],
            is_array($refs) ? $refs : []
        );
        if (count($pids) > 1) {
            throw new \InvalidArgumentException('No puede combinar precios de más de un presupuesto en la misma orden de compra.');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function marcarPresupuestoElegidoSiAplica(array $payload, int $requisicionId): void
    {
        if ($requisicionId <= 0) {
            return;
        }
        $tipos = $payload['precio_origen_tipos'] ?? [];
        $refs = $payload['precio_origen_ref_ids'] ?? [];
        $ids = OrdencompraOpcionesPrecioService::presupuestoIdsDistintosUsados(
            is_array($tipos) ? $tipos : [],
            is_array($refs) ? $refs : []
        );
        if (count($ids) === 1) {
            $this->requisicionPresupuestoService->marcarComoElegidoParaOc($requisicionId, $ids[0]);
        }
    }

    /**
     * Alta generada por otro módulo (Suscripciones), que arma la OC sin pasar por `guardar`.
     */
    /**
     * Réplica en Anita para altas hechas desde otro módulo (suscripciones).
     *
     * No propaga la excepción: la OC ya existe en el ERP y el bridge se reintenta después.
     * Devuelve el motivo cuando no se pudo replicar, para poder avisarlo en pantalla.
     */
    public function sincronizarAltaEnAnita(int $ordencompraId): ?string
    {
        $oc = $this->ordencompraRepository->find($ordencompraId);
        if (! $oc) {
            return 'La orden de compra no existe.';
        }

        try {
            $this->sincronizarAnitaAlta($oc);

            return null;
        } catch (\Throwable $e) {
            Log::warning('ordencompra.anita.alta_externa', [
                'ordencompra_id' => $ordencompraId,
                'mensaje' => $e->getMessage(),
            ]);

            return $e->getMessage();
        }
    }

    /** Surmar/El Bierzo → bridge Surmar; resto → bridge AGG (sin cambios). */
    private function sincronizarAnitaAlta(Ordencompra $oc): void
    {
        if ($this->usaEscrituraAnitaSurmar($oc)) {
            $this->ordencompraSurmarAnitaBridge->sincronizarAlta($oc);

            return;
        }

        $this->ordencompraAnitaBridge->sincronizarAlta($oc);
    }

    private function sincronizarAnitaActualizacion(Ordencompra $oc): void
    {
        if ($this->usaEscrituraAnitaSurmar($oc)) {
            $this->ordencompraSurmarAnitaBridge->sincronizarActualizacion($oc);

            return;
        }

        $this->ordencompraAnitaBridge->sincronizarActualizacion($oc);
    }

    private function sincronizarAnitaBaja(Ordencompra $oc): void
    {
        if ($this->usaEscrituraAnitaSurmar($oc)) {
            $this->ordencompraSurmarAnitaBridge->sincronizarBaja($oc);

            return;
        }

        $this->ordencompraAnitaBridge->sincronizarBaja($oc);
    }

    private function usaEscrituraAnitaSurmar(Ordencompra $oc): bool
    {
        return SurmarSupport::esEmpresaSurmar((int) ($oc->empresa_id ?? 0));
    }

    /**
     * @return array{
     *     es_contrato: bool,
     *     requiere_recepcion: bool,
     *     imputacion: string|null,
     *     cuentacontable_id: int
     * }
     */
    private function snapshotContratoSinCom(Ordencompra $oc): array
    {
        $esContrato = (bool) ($oc->es_contrato ?? false);
        $requiereRecepcion = $esContrato
            ? (bool) ($oc->contrato_requiere_recepcion ?? true)
            : true;
        $imputacion = null;
        $cuentaId = 0;
        if ($esContrato && ! $requiereRecepcion) {
            $imputacion = OrdencompraContratoRutaFacturaSupport::normalizarImputacion(
                $oc->contrato_imputacion_contable ?? null
            );
            if ($imputacion === OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL) {
                $cuentaId = (int) ($oc->contrato_cuentacontable_id ?? 0);
            }
        }

        return [
            'es_contrato' => $esContrato,
            'requiere_recepcion' => $requiereRecepcion,
            'imputacion' => $imputacion,
            'cuentacontable_id' => $cuentaId,
        ];
    }

    /**
     * @param  array{
     *     es_contrato: bool,
     *     requiere_recepcion: bool,
     *     imputacion: string|null,
     *     cuentacontable_id: int
     * }|null  $anterior
     */
    private function avisarContratoSinComSiAplica(int $ordencompraId, ?array $anterior = null): void
    {
        $oc = Ordencompra::query()->find($ordencompraId);
        if (! $oc) {
            return;
        }

        $actual = $this->snapshotContratoSinCom($oc);
        if (! $actual['es_contrato'] || $actual['requiere_recepcion']) {
            return;
        }

        // Alta: avisa siempre. Actualización: solo si entra a sin COM o cambia imputación/cuenta.
        if ($anterior !== null) {
            $antesSinCom = $anterior['es_contrato'] && ! $anterior['requiere_recepcion'];
            $cambioRutaOImputacion = ! $antesSinCom
                || ($anterior['imputacion'] ?? null) !== ($actual['imputacion'] ?? null)
                || (int) ($anterior['cuentacontable_id'] ?? 0) !== (int) ($actual['cuentacontable_id'] ?? 0);
            if (! $cambioRutaOImputacion) {
                return;
            }
        }

        $this->moduloAvisoService->enviar(
            'compras',
            'ordencompra_contrato_sin_com',
            $ordencompraId
        );
    }
}
