<?php

namespace App\Services\Configuracion;

use App\Mail\Configuracion\MailArbolAprobacion;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Sector_Legajocompra;
use App\Models\Contable\Centrocosto;
use App\Models\Configuracion\Arbolaprobacion;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Configuracion\Arbolaprobacion_Nivel;
use App\Models\Configuracion\Arbolaprobacion_OcTrigger;
use App\Models\Ordenventa\Ordenventa_Estado;
use App\Queries\Configuracion\CotizacionQueryInterface;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Repositories\Configuracion\Arbolaprobacion_MovimientoRepositoryInterface;
use App\Repositories\Configuracion\ArbolaprobacionRepositoryInterface;
use App\Repositories\Ordenventa\Ordenventa_EstadoRepositoryInterface;
use App\Repositories\Ordenventa\OrdenventaRepositoryInterface;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\OrdencompraTotalesCabecera;
use App\Support\Compras\RequisicionCentrocostoArbolOrigenSupport;
use App\Support\Compras\RequisicionTotalesCabecera;
use App\Services\Ai\AiPolicy;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillRegistry;
use App\Services\Configuracion\Ai\ExplicarContextoArbolAprobacionSkill;
use App\Support\Configuracion\ArbolAprobacionContextoSupport;
use App\Support\Configuracion\ArbolAprobacionEnlaceSupport;
use App\Support\Configuracion\OcArbolTriggerCatalog;
use App\Support\Sala\RequisicionSalaTransferenciaLaboratorioDeferred;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mail;

class ArbolaprobacionService
{
    public const CIRCUITO_OC_CAMBIO_SECTOR = 'sector';

    private $arbolaprobacionRepository;

    private $arbolaprobacion_movimientoRepository;

    private $ordenventaRepository;

    private $ordenventa_estadoRepository;

    private $requisicionRepository;

    private $requisicion_estadoRepository;

    private OrdencompraRepositoryInterface $ordencompraRepository;

    private Ordencompra_EstadoRepositoryInterface $ordencompra_estadoRepository;

    private $usuarioRepository;

    private $cotizacionService;

    private CotizacionQueryInterface $cotizacionQuery;

    public function __construct(ArbolaprobacionRepositoryInterface $arbolaprobacionrepository,
        Arbolaprobacion_MovimientoRepositoryInterface $arbolaprobacion_movimientorepository,
        OrdenventaRepositoryInterface $ordenventarepository,
        Ordenventa_EstadoRepositoryInterface $ordenventa_estadorepository,
        RequisicionRepositoryInterface $requisicionrepository,
        Requisicion_EstadoRepositoryInterface $requisicion_estadorepository,
        OrdencompraRepositoryInterface $ordencomprarepository,
        Ordencompra_EstadoRepositoryInterface $ordencompra_estadorepository,
        UsuarioRepositoryInterface $usuariorepository,
        CotizacionService $cotizacionservice,
        CotizacionQueryInterface $cotizacionquery)
    {
        $this->arbolaprobacionRepository = $arbolaprobacionrepository;
        $this->arbolaprobacion_movimientoRepository = $arbolaprobacion_movimientorepository;
        $this->ordenventaRepository = $ordenventarepository;
        $this->ordenventa_estadoRepository = $ordenventa_estadorepository;
        $this->requisicionRepository = $requisicionrepository;
        $this->requisicion_estadoRepository = $requisicion_estadorepository;
        $this->ordencompraRepository = $ordencomprarepository;
        $this->ordencompra_estadoRepository = $ordencompra_estadorepository;
        $this->usuarioRepository = $usuariorepository;
        $this->cotizacionService = $cotizacionservice;
        $this->cotizacionQuery = $cotizacionquery;
    }

    public function procesaArbolaprobacion($tipocomprobante, $comprobante_id, $operacion, array $opciones = [])
    {
        $arrayReplace = ArbolAprobacionEnlaceSupport::CARACTERES_REEMPLAZO;
        $tipoarbol = Arbolaprobacion::$enumTipoArbol[array_search($tipocomprobante, array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];

        // SP: el árbol vive en el concepto (concepto_solicitudpago_usuario), no en el ABM global.
        if ($tipocomprobante === 'SP') {
            if (! \App\Models\Solicitudpago\Solicitudpago::query()->whereKey($comprobante_id)->exists()) {
                return 0;
            }

            return app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)->procesaArbol(
                (int) $comprobante_id,
                $operacion,
                fn ($tipo, $id) => $this->leeAprobacionComprobante($tipo, $id),
                fn (...$args) => $this->buscaProximoNivel(...$args),
                fn (...$args) => $this->enviaCorreo(...$args),
            );
        }

        // PP: propuesta de pagos (ABM global por empresa + monto del lote).
        if ($tipocomprobante === 'PP') {
            if (! \App\Models\Compras\PropuestaPago::query()->whereKey($comprobante_id)->exists()) {
                return 0;
            }

            return app(\App\Services\Compras\PropuestaPagoArbolIntegracionService::class)->procesaArbol(
                (int) $comprobante_id,
                $operacion,
                fn ($tipo, $id) => $this->leeAprobacionComprobante($tipo, $id),
                fn (...$args) => $this->buscaProximoNivel(...$args),
                fn (...$args) => $this->enviaCorreo(...$args),
            );
        }

        if ($tipocomprobante === 'RE') {
            $requisicionPre = $this->requisicionRepository->find($comprobante_id);
            if (! $requisicionPre) {
                return 0;
            }
            $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $requisicionPre->empresa_id);
        } elseif ($tipocomprobante === 'RS') {
            $reqSalaPre = app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($comprobante_id);
            if (! $reqSalaPre) {
                return 0;
            }
            $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $reqSalaPre->empresa_id);
        } elseif ($tipocomprobante === 'OC') {
            $ocPre = $this->ordencompraRepository->find($comprobante_id);
            if (! $ocPre) {
                return 0;
            }
            $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $ocPre->empresa_id);
        } else {
            $arbolaprobacion = $this->arbolaprobacionRepository->findPorTipoArbol($tipoarbol);
        }

        if (! $arbolaprobacion || ! $arbolaprobacion->count()) {
            return 0;
        }

        switch ($tipocomprobante) {
            case 'OV':
                return $this->procesaArbolOrdenVenta($arbolaprobacion, $tipoarbol, $comprobante_id, $arrayReplace);
            case 'RE':
                if ($operacion === 'resume') {
                    $opciones['es_retome'] = true;
                }

                return $this->procesaArbolRequisicion($arbolaprobacion, $tipoarbol, $comprobante_id, $arrayReplace, $opciones);
            case 'RS':
                return app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)->procesaArbol(
                    $comprobante_id,
                    $operacion,
                    fn ($tipo, $id) => $this->leeAprobacionComprobante($tipo, $id),
                    fn (...$args) => $this->buscaProximoNivel(...$args),
                );
            case 'PE':
                return app(\App\Services\Ventas\PedidoInterformingArbolIntegracionService::class)->procesaArbol(
                    (int) $comprobante_id,
                    $operacion,
                    fn ($tipo, $id) => $this->leeAprobacionComprobante($tipo, $id),
                    fn (...$args) => $this->buscaProximoNivel(...$args),
                    fn (...$args) => $this->enviaCorreo(...$args),
                );
            case 'OC':
                return $this->procesaArbolOrdencompra($arbolaprobacion, $tipoarbol, $comprobante_id, $arrayReplace, $opciones);
            default:
                return 0;
        }
    }

    private function procesaArbolOrdenVenta($arbolaprobacion, $tipoarbol, $comprobante_id, array $arrayReplace): int
    {
        $ordenventa = $this->ordenventaRepository->find($comprobante_id);
        if (! $ordenventa) {
            return 0;
        }

        $arbol = $arbolaprobacion->first();
        if (! $arbol) {
            return 0;
        }

        while (true) {
            $estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, $comprobante_id);
            $proximoNivel = $this->buscaProximoNivel($arbol, $ordenventa->centrocosto_id,
                $estadoAprobacionActual['nivelactual'],
                $ordenventa->fecha, $ordenventa->monto, $ordenventa->moneda_id);

            if ($proximoNivel['proximonivel'] === -1) {
                $uid = Auth::check() ? Auth::user()->id : $ordenventa->creousuario_id;
                $this->finalizaOrdenVentaTrasArbolCompleto($comprobante_id, $uid);

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $this->grabaMovimientoArbolAutomatico($arbol->id, 'OV', $comprobante_id,
                    $proximoNivel['proximonivel'], $arrayReplace);

                continue;
            }

            $ip = config('arbolaprobacion.ip_link');
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'ordenventa/visualizar', (int) $comprobante_id, $hashVisualizar);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $ya = Arbolaprobacion_Movimiento::where('ordenventa_id', $comprobante_id)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('OV'.'A'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('OV'.'R'.$comprobante_id.$ordenventa->fecha.$ordenventa->numeroordenventa.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, 'OV', (int) $comprobante_id, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, 'OV', (int) $comprobante_id, $hashRechazo);

                $this->enviaCorreo($uid, $tipoarbol, $ordenventa, $linkAprobacion, $linkRechazo, $linkVisualizar, null);

                $this->arbolaprobacion_movimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => Auth::user()->id,
                    'requisicion_id' => null,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => $comprobante_id,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'fechaproceso' => null,
                    'estado' => $nombrePendiente,
                    'observacion' => '',
                ]);
            }

            return $proximoNivel['proximonivel'];
        }
    }

    private function procesaArbolRequisicion($arbolaprobacion, $tipoarbol, $comprobante_id, array $arrayReplace, array $opciones = []): int
    {
        $requisicion = $this->requisicionRepository->find($comprobante_id);
        if (! $requisicion) {
            return 0;
        }

        if ($arbolaprobacion->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para la empresa; debe quedar uno solo.');
        }

        $arbol = $arbolaprobacion->first();
        if (! $arbol) {
            return 0;
        }

        $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeModelo($requisicion);
        $esRetome = ! empty($opciones['es_retome']);
        $nivelRetome = (int) ($opciones['nivel_retome'] ?? 0);
        $destinatarioRetome = (int) ($opciones['destinatario_usuario_id'] ?? 0);

        while (true) {
            $requisicion = $this->requisicionRepository->find($comprobante_id);
            $totalesReq = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);

            $nombreAprobadaReq = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $nombreGeneroOcReq = Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $nombreCumplidaReq = Requisicion_Estado::$enumEstado[array_search('C', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            if (in_array($requisicion->estado, [$nombreAprobadaReq, $nombreGeneroOcReq, $nombreCumplidaReq, 'GENERO OC'], true)) {
                $this->anulaMovimientosArbolPendientesAbiertosRequisicion(
                    $comprobante_id,
                    'Sin efecto (requisición ya no en circuito activo del árbol de aprobación)'
                );

                return 0;
            }

            $estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, $comprobante_id);
            $proximoNivel = $this->buscaProximoNivel($arbol, $centrocostoArbol,
                $estadoAprobacionActual['nivelactual'],
                $requisicion->fecha, $totalesReq['monto'], $totalesReq['moneda_id']);
            $proximoNivel = $this->filtrarProximoNivelUsuariosPorEmpresa($proximoNivel, (int) $requisicion->empresa_id);

            if ($proximoNivel['proximonivel'] === -1) {
                $uid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
                $this->finalizaRequisicionTrasArbolCompleto($comprobante_id, $uid);

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $this->aplicaEstadoRequisicionPorNombre($comprobante_id, $proximoNivel['documento_estado_al_aprobar'],
                    'Árbol de aprobación: nivel '.$proximoNivel['proximonivel'].' sin usuario (automático)',
                    $this->usuarioHistoriaRequisicion($requisicion));

                $this->grabaMovimientoArbolAutomatico($arbol->id, 'RE', $comprobante_id,
                    $proximoNivel['proximonivel'], $arrayReplace);

                $reqTrasAuto = $this->requisicionRepository->find($comprobante_id);
                $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
                if ($reqTrasAuto && $reqTrasAuto->estado === $nombreEnCompras) {
                    return 0;
                }

                continue;
            }

            $ip = config('arbolaprobacion.ip_link');
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'compras/requisicion/visualizar', (int) $comprobante_id, $hashVisualizar);

            $observacionEnvio = $this->normalizarObservacionEnvio($opciones['observacion_envio'] ?? null);
            $mailExtras = $this->armaExtrasMailRequisicion($requisicion, $proximoNivel['documento_estado_al_aprobar'], $observacionEnvio);
            $envioUid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            if ($esRetome
                && $nivelRetome > 0
                && (int) $proximoNivel['proximonivel'] === $nivelRetome
                && count($uids) > 1) {
                if ($destinatarioRetome <= 0 || ! in_array($destinatarioRetome, $uids, true)) {
                    throw new \RuntimeException('Debe seleccionar un firmante válido para continuar el árbol de aprobación.');
                }
                $uids = [$destinatarioRetome];
            }

            $ya = Arbolaprobacion_Movimiento::where('requisicion_id', $comprobante_id)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente)
                ->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('RE'.'A'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('RE'.'R'.$comprobante_id.$requisicion->fecha.$requisicion->numerorequisicion.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, 'RE', (int) $comprobante_id, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, 'RE', (int) $comprobante_id, $hashRechazo);

                $this->enviaCorreo($uid, $tipoarbol, $requisicion, $linkAprobacion, $linkRechazo, $linkVisualizar, $mailExtras);

                $this->arbolaprobacion_movimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => $envioUid,
                    'requisicion_id' => $comprobante_id,
                    'ordencompra_id' => null,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => null,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'fechaproceso' => null,
                    'estado' => $nombrePendiente,
                    'observacion' => $observacionEnvio,
                ]);
            }

            return $proximoNivel['proximonivel'];
        }
    }

    private function procesaArbolOrdencompra($arbolaprobacion, $tipoarbol, $comprobante_id, array $arrayReplace, array $opciones = []): int
    {
        $ordencompra = $this->ordencompraRepository->find($comprobante_id);
        if (! $ordencompra) {
            return 0;
        }

        if ($arbolaprobacion->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de órdenes de compra para la empresa; debe quedar uno solo.');
        }

        $arbol = $arbolaprobacion->first();
        if (! $arbol) {
            return 0;
        }

        $trigger = $this->resolverOcTriggerDesdeOpciones($opciones, (int) $arbol->id);
        $ocTriggerId = $trigger ? (int) $trigger->id : null;

        $esCircuitoCambioSector = ! empty($opciones['circuito_sector']);
        if ($trigger !== null && $trigger->evento === OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR) {
            $esCircuitoCambioSector = true;
        }
        $circuitoOc = $esCircuitoCambioSector ? self::CIRCUITO_OC_CAMBIO_SECTOR : null;

        if ($trigger !== null) {
            $centrocostoArbol = (int) ($trigger->centrocosto_circuito_id ?? 0);
            if ($centrocostoArbol <= 0) {
                $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($ordencompra);
            }
            if ($trigger->tipo === OcArbolTriggerCatalog::TIPO_EVENTO
                && $trigger->evento === OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR
                && $centrocostoArbol <= 0) {
                return 0;
            }
        } elseif ($esCircuitoCambioSector) {
            $centrocostoArbol = (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0);
            if ($centrocostoArbol <= 0) {
                return 0;
            }
        } else {
            $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($ordencompra);
        }

        $permiteEstadoNoPendiente = $trigger !== null && (
            ($trigger->tipo === OcArbolTriggerCatalog::TIPO_EVENTO && $trigger->evento === OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR)
            || $trigger->tipo === OcArbolTriggerCatalog::TIPO_CONDICION
        );

        while (true) {
            $ordencompra = $this->ordencompraRepository->find($comprobante_id);
            $totalesOc = OrdencompraTotalesCabecera::desdeModelo($ordencompra, $this->cotizacionQuery);

            if (! $permiteEstadoNoPendiente && ! $esCircuitoCambioSector && $ordencompra->estadoordencompra !== OrdencompraEstados::PENDIENTE) {
                $this->anulaMovimientosArbolPendientesAbiertosOrdencompra(
                    $comprobante_id,
                    'Sin efecto (orden de compra ya no en circuito activo del árbol de aprobación)'
                );

                return 0;
            }

            $estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, $comprobante_id, $circuitoOc, $ocTriggerId);
            $proximoNivel = $this->buscaProximoNivel($arbol, $centrocostoArbol,
                $estadoAprobacionActual['nivelactual'],
                $ordencompra->fecha, $totalesOc['monto'], $totalesOc['moneda_id']);

            if ($proximoNivel['proximonivel'] === -1) {
                $uid = Auth::check() ? Auth::user()->id : $ordencompra->creousuario_id;
                if ($trigger !== null) {
                    $this->finalizaOrdencompraTrasArbolTriggerCompleto($comprobante_id, $uid, $trigger);
                } elseif ($esCircuitoCambioSector) {
                    $this->finalizaOrdencompraTrasArbolCambioSectorCompleto($comprobante_id, $uid, $arbol);
                } else {
                    $this->finalizaOrdencompraTrasArbolCompleto($comprobante_id, $uid);
                }

                return -1;
            }

            if ($proximoNivel['proximonivel'] <= 0) {
                return 0;
            }

            if (empty($proximoNivel['proximousuario'])) {
                $estadoAuto = $proximoNivel['documento_estado_al_aprobar'];
                if (($estadoAuto === null || $estadoAuto === '') && $trigger !== null) {
                    $estadoAuto = trim((string) ($trigger->documento_estado_al_aprobar ?? ''));
                }
                if (($estadoAuto === null || $estadoAuto === '') && $esCircuitoCambioSector) {
                    $estadoAuto = OrdencompraEstados::APROBADA;
                }
                $this->aplicaEstadoOrdencompraPorNombre($comprobante_id, $estadoAuto,
                    'Árbol de aprobación: nivel '.$proximoNivel['proximonivel'].' sin usuario (automático)',
                    $this->usuarioHistoriaOrdencompra($ordencompra));

                $this->grabaMovimientoArbolAutomatico($arbol->id, 'OC', $comprobante_id,
                    $proximoNivel['proximonivel'], $arrayReplace, $circuitoOc, $ocTriggerId);

                continue;
            }

            $ip = config('arbolaprobacion.ip_link');
            $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('VIS'.$comprobante_id.$ordencompra->fecha.$ordencompra->numeroordencompra));
            $linkVisualizar = ArbolAprobacionEnlaceSupport::enlaceVisualizar($ip, 'compras/ordencompra/visualizar', (int) $comprobante_id, $hashVisualizar);

            $observacionEnvio = $this->normalizarObservacionEnvio($opciones['observacion_envio'] ?? null);
            $mailExtras = $this->armaExtrasMailOrdencompra($ordencompra, $proximoNivel['documento_estado_al_aprobar'], $observacionEnvio);
            $envioUid = Auth::check() ? Auth::user()->id : $ordencompra->creousuario_id;
            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

            $uids = $proximoNivel['proximousuarios'] ?? [];
            if (! is_array($uids) || count($uids) === 0) {
                $uids = [$proximoNivel['proximousuario']];
            }
            $uids = array_values(array_unique(array_filter($uids)));

            $yaQuery = Arbolaprobacion_Movimiento::where('ordencompra_id', $comprobante_id)
                ->where('nivel', $proximoNivel['proximonivel'])
                ->where('estado', $nombrePendiente);
            $this->aplicarFiltroCircuitoOcQuery($yaQuery, $circuitoOc, $ocTriggerId);
            $ya = $yaQuery->pluck('destinatariousuario_id')
                ->map(fn ($x) => (int) $x)
                ->all();

            foreach ($uids as $uid) {
                $uid = (int) $uid;
                if ($uid <= 0 || in_array($uid, $ya, true)) {
                    continue;
                }

                $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('OC'.'A'.$comprobante_id.$ordencompra->fecha.$ordencompra->numeroordencompra.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make('OC'.'R'.$comprobante_id.$ordencompra->fecha.$ordencompra->numeroordencompra.'N'.
                    $estadoAprobacionActual['nivelactual'].'U'.$uid));
                $linkAprobacion = ArbolAprobacionEnlaceSupport::enlaceAprobar($ip, 'OC', (int) $comprobante_id, $hashAprobacion);
                $linkRechazo = ArbolAprobacionEnlaceSupport::enlaceRechazo($ip, 'OC', (int) $comprobante_id, $hashRechazo);

                $this->enviaCorreo($uid, $tipoarbol, $ordencompra, $linkAprobacion, $linkRechazo, $linkVisualizar, $mailExtras);

                $this->arbolaprobacion_movimientoRepository->create([
                    'arbolaprobacion_id' => $arbol->id,
                    'fechaenvio' => Carbon::now(),
                    'enviousuario_id' => $envioUid,
                    'requisicion_id' => null,
                    'ordencompra_id' => $comprobante_id,
                    'solicitudpago_id' => null,
                    'ordenventa_id' => null,
                    'hashaprobacion' => $hashAprobacion,
                    'hashrechazo' => $hashRechazo,
                    'hashvisualizar' => $hashVisualizar,
                    'nivel' => $proximoNivel['proximonivel'],
                    'destinatariousuario_id' => $uid,
                    'fechaproceso' => null,
                    'estado' => $nombrePendiente,
                    'observacion' => $observacionEnvio,
                    'circuito_oc' => $circuitoOc,
                    'arbolaprobacion_oc_trigger_id' => $ocTriggerId,
                ]);
            }

            return $proximoNivel['proximonivel'];
        }
    }

    public function leeAprobacionComprobante($tipoarbol, $comprobante_id, ?string $circuitoOc = null, ?int $ocTriggerId = null)
    {
        $nivelActual = 0;
        $estadoActual = '';
        $usuarioActual_id = null;

        switch ($tipoarbol) {
            case 'Ordenes de venta':
                // Trae las aprobaciones por orden de venta
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobante_id);
                break;
            case 'Requisiciones':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($comprobante_id);
                break;
            case 'Requisiciones de sala':
                $arbolaprobacion_movimiento = app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)
                    ->findPorRequisicionSala((int) $comprobante_id);
                break;
            case 'Pedidos':
                $arbolaprobacion_movimiento = app(\App\Services\Ventas\PedidoInterformingArbolIntegracionService::class)
                    ->findPorPedido((int) $comprobante_id);
                break;
            case 'Solicitudes de pago':
                $arbolaprobacion_movimiento = app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                    ->findPorSolicitudpago((int) $comprobante_id);
                break;
            case 'Propuesta de pagos':
                $arbolaprobacion_movimiento = app(\App\Services\Compras\PropuestaPagoArbolIntegracionService::class)
                    ->findPorPropuestaPago((int) $comprobante_id);
                break;
            case 'Ordenes de compra':
                $arbolaprobacion_movimiento = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($comprobante_id);
                $arbolaprobacion_movimiento = $this->filtrarMovimientosOrdencompraPorCircuito($arbolaprobacion_movimiento, $circuitoOc, $ocTriggerId);
                break;
        }
        if ($arbolaprobacion_movimiento) {
            $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            foreach ($arbolaprobacion_movimiento as $aprobacion) {
                $estadoActual = $aprobacion->estado;
                if ($aprobacion->estado === $nombreAprobado && $aprobacion->nivel >= $nivelActual) {
                    $nivelActual = $aprobacion->nivel;
                    $usuarioActual_id = $aprobacion->destinatariousuario_id;
                }
            }
        }

        return ['nivelactual' => $nivelActual, 'estado' => $estadoActual, 'usuario_id' => $usuarioActual_id];
    }

    public function buscaProximoNivel(Arbolaprobacion $arbol, $centrocosto_id, $nivelactual, $fecha, $monto, $moneda_id)
    {
        $nivelesCc = [];
        foreach ($arbol->arbolaprobacion_niveles as $nivel) {
            if ((int) $nivel->centrocosto_id === (int) $centrocosto_id) {
                $nivelesCc[] = $nivel;
            }
        }

        $dobleAprobacion = $this->centrocostoTieneDobleAprobacion($nivelesCc);
        $umbralPisoDoble = $dobleAprobacion
            ? $this->umbralPisoDobleAprobacion($nivelesCc)
            : null;

        $candidatos = [];
        foreach ($nivelesCc as $nivel) {
            $coeficienteConversion = 1.;
            if ($nivel->moneda_id != $moneda_id) {
                $cotizacion = $this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id);
                $coeficienteConversion = (float) calculaCoeficienteMoneda($nivel->moneda_id, $moneda_id, $cotizacion);
                if ($coeficienteConversion == 0) {
                    $coeficienteConversion = 1.;
                }
            }

            $montoEnMonedaNivel = (float) $monto * $coeficienteConversion;
            $enRango = $this->nivelAplicaPorMonto(
                $nivel,
                $montoEnMonedaNivel,
                $dobleAprobacion,
                $umbralPisoDoble
            );

            if ($nivelactual < $nivel->nivel && $enRango) {
                $candidatos[] = [
                    'nivel' => $nivel->nivel,
                    'usuario_id' => $nivel->usuario_id ?: null,
                    'documento_estado_al_aprobar' => $nivel->documento_estado_al_aprobar,
                ];
            }
        }

        if (count($candidatos) === 0) {
            $proximoNivel = 0;
            $proximoUsuario = null;
            $proximoUsuarios = [];
            $estadoReq = null;
        } else {
            usort($candidatos, fn ($a, $b) => $a['nivel'] <=> $b['nivel']);
            $proximoNivel = (int) $candidatos[0]['nivel'];
            $enNivel = array_values(array_filter($candidatos, fn ($c) => (int) $c['nivel'] === $proximoNivel));

            $uids = [];
            $estadoReq = null;
            foreach ($enNivel as $c) {
                if (! empty($c['usuario_id'])) {
                    $uids[] = (int) $c['usuario_id'];
                }
                if ($estadoReq === null && filled($c['documento_estado_al_aprobar'])) {
                    $estadoReq = $c['documento_estado_al_aprobar'];
                }
            }
            $uids = array_values(array_unique($uids));
            $proximoUsuarios = $uids;
            $proximoUsuario = $uids[0] ?? null; // compat
        }

        if ($nivelactual > 0 && $proximoNivel === 0) {
            $proximoNivel = -1;
        }

        return [
            'proximonivel' => $proximoNivel,
            'proximousuario' => $proximoUsuario,
            'proximousuarios' => $proximoUsuarios,
            'documento_estado_al_aprobar' => $estadoReq,
        ];
    }

    /**
     * Un CC opera en doble aprobación si alguna fila activa del CC tiene doble_aprobacion = S.
     *
     * @param  iterable<int, Arbolaprobacion_Nivel>  $nivelesCc
     */
    private function centrocostoTieneDobleAprobacion(iterable $nivelesCc): bool
    {
        foreach ($nivelesCc as $nivel) {
            if (strtoupper(trim((string) ($nivel->doble_aprobacion ?? 'N'))) === 'S') {
                return true;
            }
        }

        return false;
    }

    /**
     * Umbral a partir del cual el matching pasa a piso (estilo SP).
     * Por debajo se mantienen bandas exclusivas (requis &lt; ~5M sin cambio).
     *
     * @param  iterable<int, Arbolaprobacion_Nivel>  $nivelesCc
     */
    private function umbralPisoDobleAprobacion(iterable $nivelesCc): float
    {
        $minAlto = null;
        foreach ($nivelesCc as $nivel) {
            $desde = (float) ($nivel->desdemonto ?? 0);
            if ($desde >= 5000000.0) {
                $minAlto = $minAlto === null ? $desde : min($minAlto, $desde);
            }
        }

        return $minAlto ?? 5000001.0;
    }

    /**
     * Matching de monto por nivel.
     * - Sin doble / monto &lt; umbral alto: banda exclusiva [desde, hasta] (comportamiento histórico).
     * - Con doble y monto &gt;= umbral alto: piso desdemonto &lt;= monto (área + umbrales altos en secuencia por nivel).
     */
    private function nivelAplicaPorMonto(
        Arbolaprobacion_Nivel $nivel,
        float $montoEnMonedaNivel,
        bool $dobleAprobacion,
        ?float $umbralPisoDoble
    ): bool {
        $desde = (float) ($nivel->desdemonto ?? 0);
        $hasta = (float) ($nivel->hastamonto ?? 0);
        $sinTope = ($desde == 0.0 && $hasta == 0.0);

        $usarPiso = $dobleAprobacion
            && $umbralPisoDoble !== null
            && $montoEnMonedaNivel >= $umbralPisoDoble;

        if ($usarPiso) {
            return $sinTope || $desde <= $montoEnMonedaNivel;
        }

        if ($sinTope) {
            return true;
        }

        return $desde <= $montoEnMonedaNivel && $hasta >= $montoEnMonedaNivel;
    }

    /**
     * Restringe firmantes del nivel a usuarios asignados a la empresa del comprobante (usuario_empresa).
     * Usuario sin filas en usuario_empresa aplica a todas las empresas (aprobador grupal).
     *
     * @param  array{proximonivel: int, proximousuario: mixed, proximousuarios: array, documento_estado_al_aprobar: mixed}  $proximoNivel
     * @return array{proximonivel: int, proximousuario: mixed, proximousuarios: array, documento_estado_al_aprobar: mixed}
     */
    private function filtrarProximoNivelUsuariosPorEmpresa(array $proximoNivel, int $empresaId): array
    {
        $uids = $proximoNivel['proximousuarios'] ?? [];
        if (! is_array($uids) || count($uids) === 0) {
            $uid = (int) ($proximoNivel['proximousuario'] ?? 0);
            $uids = $uid > 0 ? [$uid] : [];
        }

        $antes = count($uids);
        $filtrados = $empresaId > 0
            ? $this->usuarioRepository->filtrarIdsOperativosPorEmpresa($uids, $empresaId)
            : $this->usuarioRepository->filtrarIdsOperativos($uids);

        if ($antes > 0 && count($filtrados) === 0) {
            throw new \RuntimeException(
                $empresaId > 0
                    ? 'El árbol de aprobación no tiene un firmante aplicable para la empresa de la requisición en el nivel correspondiente.'
                    : 'El árbol de aprobación no tiene un firmante operativo en el nivel correspondiente.'
            );
        }

        $proximoNivel['proximousuarios'] = $filtrados;
        $proximoNivel['proximousuario'] = $filtrados[0] ?? null;

        return $proximoNivel;
    }

    public function enviaCorreo($usuario_id, $tipoarbol, $ptrcomprobante, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras = null)
    {
        $usuario = $this->usuarioRepository->findOperativo((int) $usuario_id);

        if ($usuario) {
            $receivers = $usuario->email;

            Mail::to($receivers)->send(new MailArbolAprobacion($ptrcomprobante, $tipoarbol, $linkaprobacion, $linkrechazo, $linkvisualizar, $mailExtras));

            $this->logArbolAprobacion('correo_enviado', [
                'tipo_arbol' => $tipoarbol,
                'comprobante' => $this->contextoComprobanteArbol($ptrcomprobante),
                'destinatario_usuario_id' => (int) $usuario_id,
                'destinatario_login' => (string) ($usuario->usuario ?? ''),
                'email' => (string) $receivers,
                'estado_tras_aprobar' => is_array($mailExtras) ? ($mailExtras['estado_tras_aprobar'] ?? null) : null,
            ]);
        } else {
            throw new ModelNotFoundException('Usuario en arbol de aprobación no encontrado');
        }
    }

    /**
     * Texto y montos adicionales para el correo de aprobación de requisiciones.
     *
     * @return array<string, mixed>
     */
    private function armaExtrasMailRequisicion(
        Requisicion $requisicion,
        ?string $estadoAlAprobarEsteNivel,
        string $observacionEnvio = ''
    ): array {
        $totales = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);

        return [
            'estado_tras_aprobar' => $this->estadoRequisicionAlAprobarNivel($estadoAlAprobarEsteNivel),
            'monto_items' => $totales['monto'],
            'moneda_abrev_items' => $totales['monedacabecera_abreviatura'],
            'solicitante' => optional($requisicion->usuarios)->nombre ?? '',
            'centrocosto' => trim(
                (string) (optional($requisicion->centrocostos)->codigo ?? '').' '.
                (string) (optional($requisicion->centrocostos)->nombre ?? '')
            ),
            'historial_aprobaciones' => $this->historialAprobacionesRequisicion((int) $requisicion->id),
            'comentario_envio' => $observacionEnvio,
        ];
    }

    /**
     * Comentario opcional al enviar el documento al árbol (columna observacion del movimiento pendiente).
     */
    public function normalizarObservacionEnvio(?string $observacion): string
    {
        $texto = trim((string) $observacion);
        if ($texto === '') {
            return '';
        }

        return Str::limit($texto, 255, '');
    }

    public function aprobar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, ?string $observacion = null): array
    {
        $this->logArbolAprobacion('aprobacion_intento', [
            'tipocomprobante' => $tipocomprobante,
            'comprobante_id' => (int) $comprobante_id,
            'movimiento_id' => (int) $aprobacion_id,
            'usuario_id' => (int) $usuario_id,
        ]);

        DB::beginTransaction();
        try {
            $movimientoPre = Arbolaprobacion_Movimiento::findOrFail($aprobacion_id);

            if (strtoupper((string) $tipocomprobante) === 'SP') {
                $spAviso = \App\Models\Solicitudpago\Solicitudpago::query()->find((int) $comprobante_id);
                if ($spAviso
                    && app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                        ->esNivelAvisoPago($spAviso, (int) $movimientoPre->nivel)) {
                    throw new \RuntimeException(
                        'Nivel aviso a pagadores: no se aprueba por el árbol. Use Ingresos/egresos o Rechazar.'
                    );
                }
            }

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

            $obsAprobacion = $observacion !== null ? trim($observacion) : '';

            // First-wins: si otro ya aprobó/gestionó, no avanzamos.
            $rows = Arbolaprobacion_Movimiento::where('id', $aprobacion_id)
                ->where('estado', $nombrePendiente)
                ->update([
                    'fechaproceso' => Carbon::now(),
                    'estado' => $nombreAprobado,
                    'observacion' => $obsAprobacion,
                ]);
            if ($rows === 0) {
                $this->logArbolAprobacion('aprobacion_sin_efecto', [
                    'tipocomprobante' => $tipocomprobante,
                    'comprobante_id' => (int) $comprobante_id,
                    'movimiento_id' => (int) $aprobacion_id,
                    'usuario_id' => (int) $usuario_id,
                    'motivo' => 'movimiento ya no estaba pendiente',
                ]);

                return $this->commitAprobacion($tipocomprobante);
            }

            // Invalida el resto de los usuarios del mismo nivel/comprobante.
            $q = Arbolaprobacion_Movimiento::where('arbolaprobacion_id', $movimientoPre->arbolaprobacion_id)
                ->where('nivel', $movimientoPre->nivel)
                ->where('estado', $nombrePendiente)
                ->where('id', '!=', $aprobacion_id);
            if ($movimientoPre->requisicion_id) {
                $q->where('requisicion_id', $movimientoPre->requisicion_id);
            } elseif ($movimientoPre->requisicion_sala_id) {
                $q->where('requisicion_sala_id', $movimientoPre->requisicion_sala_id);
            } elseif ($movimientoPre->ordenventa_id) {
                $q->where('ordenventa_id', $movimientoPre->ordenventa_id);
            } elseif ($movimientoPre->pedido_id) {
                $q->where('pedido_id', $movimientoPre->pedido_id);
            } elseif ($movimientoPre->solicitudpago_id) {
                $q->where('solicitudpago_id', $movimientoPre->solicitudpago_id);
            } elseif ($movimientoPre->propuesta_pago_id) {
                $q->where('propuesta_pago_id', $movimientoPre->propuesta_pago_id);
            } elseif ($movimientoPre->ordencompra_id) {
                $q->where('ordencompra_id', $movimientoPre->ordencompra_id);
            }
            $q->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => 'Sin efecto (otro usuario aprobó el nivel)',
            ]);

            if ($tipocomprobante === 'RE') {
                $arbol = $this->arbolaprobacionRepository->find($movimientoPre->arbolaprobacion_id);
                $requisicion = $this->requisicionRepository->find($comprobante_id);
                $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeModelo($requisicion);
                $totalesReq = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);
                $nivelCfg = $this->encuentraNivelCoincidente(
                    $arbol,
                    $centrocostoArbol,
                    $movimientoPre->nivel,
                    $requisicion->fecha,
                    $totalesReq['monto'],
                    $totalesReq['moneda_id']
                );
                if ($nivelCfg !== null) {
                    $this->aplicaEstadoRequisicionPorNombre(
                        $comprobante_id,
                        $nivelCfg->documento_estado_al_aprobar,
                        'Árbol de aprobación: nivel '.$movimientoPre->nivel.' aprobado',
                        $usuario_id
                    );
                }
            }

            if ($tipocomprobante === 'RS') {
                $arbol = $this->arbolaprobacionRepository->find($movimientoPre->arbolaprobacion_id);
                $reqSala = app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($comprobante_id);
                $totales = \App\Support\Sala\RequisicionSalaTotalesCabecera::desdeModelo($reqSala);
                $nivelCfg = $this->encuentraNivelCoincidente(
                    $arbol,
                    (int) $reqSala->centrocosto_id,
                    $movimientoPre->nivel,
                    $reqSala->fecha,
                    $totales['monto'],
                    $totales['moneda_id']
                );
                if ($nivelCfg !== null && filled($nivelCfg->documento_estado_al_aprobar)) {
                    app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)->aplicaEstadoPorNombre(
                        $comprobante_id,
                        trim($nivelCfg->documento_estado_al_aprobar),
                        'Árbol de aprobación: nivel '.$movimientoPre->nivel.' aprobado',
                        $usuario_id
                    );
                }
            }

            if ($tipocomprobante === 'OC') {
                $arbol = $this->arbolaprobacionRepository->find($movimientoPre->arbolaprobacion_id);
                $ordencompra = $this->ordencompraRepository->find($comprobante_id);
                $circuitoOcMov = $movimientoPre->circuito_oc ?? null;
                $ocTriggerId = (int) ($movimientoPre->arbolaprobacion_oc_trigger_id ?? 0);
                $trigger = $ocTriggerId > 0
                    ? Arbolaprobacion_OcTrigger::query()->find($ocTriggerId)
                    : null;

                if ($trigger !== null) {
                    $centrocostoArbol = (int) ($trigger->centrocosto_circuito_id ?? 0);
                    if ($centrocostoArbol <= 0) {
                        $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($ordencompra);
                    }
                } elseif ($circuitoOcMov === self::CIRCUITO_OC_CAMBIO_SECTOR) {
                    $centrocostoArbol = (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0);
                } else {
                    $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($ordencompra);
                }
                $totalesOc = OrdencompraTotalesCabecera::desdeModelo($ordencompra, $this->cotizacionQuery);
                $nivelCfg = $this->encuentraNivelCoincidente(
                    $arbol,
                    $centrocostoArbol,
                    $movimientoPre->nivel,
                    $ordencompra->fecha,
                    $totalesOc['monto'],
                    $totalesOc['moneda_id']
                );
                if ($nivelCfg !== null) {
                    $estadoOc = trim((string) ($nivelCfg->documento_estado_al_aprobar ?? ''));
                    if ($estadoOc === '' && $trigger !== null) {
                        $estadoOc = trim((string) ($trigger->documento_estado_al_aprobar ?? ''));
                    }
                    if ($estadoOc === '' && $circuitoOcMov === self::CIRCUITO_OC_CAMBIO_SECTOR) {
                        $estadoOc = OrdencompraEstados::APROBADA;
                    }
                    if ($estadoOc !== '') {
                        $this->aplicaEstadoOrdencompraPorNombre(
                            $comprobante_id,
                            $estadoOc,
                            'Árbol de aprobación: nivel '.$movimientoPre->nivel.' aprobado',
                            $usuario_id
                        );
                    }
                }
            }

            if ($tipocomprobante === 'SP') {
                app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                    ->aplicaEstadoTrasAprobarNivel(
                        (int) $comprobante_id,
                        (int) $movimientoPre->nivel,
                        (int) $usuario_id
                    );
            }

            if ($tipocomprobante === 'PP') {
                // Tras aprobar, procesaArbol (abajo) marcará AUTORIZADA si no hay más niveles.
            }

            if ($tipocomprobante === 'RE') {
                $reqActual = $this->requisicionRepository->find($comprobante_id);
                $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
                if ($reqActual && $reqActual->estado === $nombreEnCompras) {
                    $this->logArbolAprobacion('aprobacion_ok', [
                        'tipocomprobante' => $tipocomprobante,
                        'comprobante_id' => (int) $comprobante_id,
                        'movimiento_id' => (int) $aprobacion_id,
                        'nivel' => (int) $movimientoPre->nivel,
                        'usuario_id' => (int) $usuario_id,
                        'estado_documento' => $reqActual->estado,
                        'detiene_arbol' => 'requisicion_en_compras',
                    ]);

                    return $this->commitAprobacion($tipocomprobante);
                }
            }

            if ($tipocomprobante === 'RS') {
                $reqSalaActual = app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($comprobante_id);
                $nombreEnLaboratorioSala = \App\Models\Sala\RequisicionSalaEstado::$enumEstado[array_search('5', array_column(\App\Models\Sala\RequisicionSalaEstado::$enumEstado, 'valor'))]['nombre'];
                if ($reqSalaActual && $reqSalaActual->estado === $nombreEnLaboratorioSala) {
                    return $this->commitAprobacion($tipocomprobante);
                }
            }

            $opcionesProceso = [];
            $ocTriggerIdProceso = (int) ($movimientoPre->arbolaprobacion_oc_trigger_id ?? 0);
            if ($tipocomprobante === 'OC' && $ocTriggerIdProceso > 0) {
                $opcionesProceso['oc_trigger_id'] = $ocTriggerIdProceso;
            } elseif ($tipocomprobante === 'OC' && ($movimientoPre->circuito_oc ?? '') === self::CIRCUITO_OC_CAMBIO_SECTOR) {
                $opcionesProceso['circuito_sector'] = true;
            }
            $this->procesaArbolaprobacion($tipocomprobante, $comprobante_id, 'self', $opcionesProceso);

            if ($tipocomprobante === 'SP') {
                app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                    ->asegurarEstadoTrasProcesarArbol((int) $comprobante_id);
            }

            $this->logArbolAprobacion('aprobacion_ok', [
                'tipocomprobante' => $tipocomprobante,
                'comprobante_id' => (int) $comprobante_id,
                'movimiento_id' => (int) $aprobacion_id,
                'nivel' => (int) $movimientoPre->nivel,
                'usuario_id' => (int) $usuario_id,
                'estado_documento' => $this->estadoDocumentoTrasAprobacion($tipocomprobante, (int) $comprobante_id),
            ]);

            return $this->commitAprobacion($tipocomprobante);
        } catch (\Exception $e) {
            DB::rollback();
            if ($tipocomprobante === 'RS') {
                RequisicionSalaTransferenciaLaboratorioDeferred::descartarPendientes();
            }

            $this->logArbolAprobacion('aprobacion_error', [
                'tipocomprobante' => $tipocomprobante,
                'comprobante_id' => (int) $comprobante_id,
                'movimiento_id' => (int) $aprobacion_id,
                'usuario_id' => (int) $usuario_id,
                'error' => $e->getMessage(),
            ], 'error');

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }
    }

    private function commitAprobacion(string $tipocomprobante): array
    {
        DB::commit();
        if ($tipocomprobante !== 'RS') {
            return ['mensaje' => 'ok'];
        }

        return [
            'mensaje' => 'ok',
            'transferencias_sala' => RequisicionSalaTransferenciaLaboratorioDeferred::procesarPendientes(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logArbolAprobacion(string $evento, array $context, string $nivel = 'info'): void
    {
        Log::log($nivel, 'ArbolAprobacion: '.$evento, $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function contextoComprobanteArbol($ptrcomprobante): array
    {
        if ($ptrcomprobante instanceof Requisicion) {
            return [
                'tipo' => 'RE',
                'id' => (int) $ptrcomprobante->id,
                'numero' => (int) ($ptrcomprobante->numerorequisicion ?? 0),
                'estado' => (string) ($ptrcomprobante->estado ?? ''),
            ];
        }

        if ($ptrcomprobante instanceof Ordencompra) {
            return [
                'tipo' => 'OC',
                'id' => (int) $ptrcomprobante->id,
                'numero' => (int) ($ptrcomprobante->numeroordencompra ?? 0),
                'estado' => (string) ($ptrcomprobante->estadoordencompra ?? ''),
            ];
        }

        return ['tipo' => 'desconocido', 'id' => (int) ($ptrcomprobante->id ?? 0)];
    }

    private function estadoDocumentoTrasAprobacion(string $tipocomprobante, int $comprobanteId): ?string
    {
        if ($tipocomprobante === 'RE') {
            return $this->requisicionRepository->find($comprobanteId)?->estado;
        }
        if ($tipocomprobante === 'OC') {
            return $this->ordencompraRepository->find($comprobanteId)?->estadoordencompra;
        }
        if ($tipocomprobante === 'RS') {
            return app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($comprobanteId)?->estado;
        }
        if ($tipocomprobante === 'OV') {
            return $this->ordenventaRepository->find($comprobanteId)?->estado;
        }

        return null;
    }

    private function grabaMovimientoArbolAutomatico(
        int $arbolaprobacion_id,
        string $tipoComprobante,
        int $comprobante_id,
        int $numeroNivel,
        array $arrayReplace,
        ?string $circuitoOc = null,
        ?int $ocTriggerId = null
    ): void {
        $token = $tipoComprobante.'AUTO'.$comprobante_id.'N'.$numeroNivel.str_replace([' ', ':'], '', microtime(false));
        $hashAprobacion = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'A'));
        $hashRechazo = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'R'));
        $hashVisualizar = ArbolAprobacionEnlaceSupport::prepararHashAlmacenado(Hash::make($token.'V'));
        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

        if ($tipoComprobante === 'OV') {
            $ordenventa = $this->ordenventaRepository->find($comprobante_id);
            $envioUid = Auth::check() ? Auth::user()->id : $ordenventa->creousuario_id;
            $this->arbolaprobacion_movimientoRepository->create([
                'arbolaprobacion_id' => $arbolaprobacion_id,
                'fechaenvio' => Carbon::now(),
                'enviousuario_id' => $envioUid,
                'requisicion_id' => null,
                'ordencompra_id' => null,
                'solicitudpago_id' => null,
                'ordenventa_id' => $comprobante_id,
                'hashaprobacion' => $hashAprobacion,
                'hashrechazo' => $hashRechazo,
                'hashvisualizar' => $hashVisualizar,
                'nivel' => $numeroNivel,
                'destinatariousuario_id' => null,
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreAprobado,
                'observacion' => 'Nivel sin usuario (automático)',
            ]);

            return;
        }

        if ($tipoComprobante === 'RE') {
            $requisicion = $this->requisicionRepository->find($comprobante_id);
            $envioUid = Auth::check() ? Auth::user()->id : $requisicion->creousuario_id;
            $this->arbolaprobacion_movimientoRepository->create([
                'arbolaprobacion_id' => $arbolaprobacion_id,
                'fechaenvio' => Carbon::now(),
                'enviousuario_id' => $envioUid,
                'requisicion_id' => $comprobante_id,
                'ordencompra_id' => null,
                'solicitudpago_id' => null,
                'ordenventa_id' => null,
                'hashaprobacion' => $hashAprobacion,
                'hashrechazo' => $hashRechazo,
                'hashvisualizar' => $hashVisualizar,
                'nivel' => $numeroNivel,
                'destinatariousuario_id' => null,
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreAprobado,
                'observacion' => 'Nivel sin usuario (automático)',
            ]);

            return;
        }

        if ($tipoComprobante !== 'OC') {
            return;
        }

        $ordencompra = $this->ordencompraRepository->find($comprobante_id);
        $envioUid = Auth::check() ? Auth::user()->id : $ordencompra->creousuario_id;
        $this->arbolaprobacion_movimientoRepository->create([
            'arbolaprobacion_id' => $arbolaprobacion_id,
            'fechaenvio' => Carbon::now(),
            'enviousuario_id' => $envioUid,
            'requisicion_id' => null,
            'ordencompra_id' => $comprobante_id,
            'solicitudpago_id' => null,
            'ordenventa_id' => null,
            'hashaprobacion' => $hashAprobacion,
            'hashrechazo' => $hashRechazo,
            'hashvisualizar' => $hashVisualizar,
            'nivel' => $numeroNivel,
            'destinatariousuario_id' => null,
            'fechaproceso' => Carbon::now(),
            'estado' => $nombreAprobado,
            'observacion' => 'Nivel sin usuario (automático)',
            'circuito_oc' => $circuitoOc,
            'arbolaprobacion_oc_trigger_id' => $ocTriggerId,
        ]);
    }

    /**
     * Marca como sin efecto los movimientos de árbol aún pendientes (cabecera ya avanzó fuera del circuito).
     */
    public function anulaMovimientosArbolPendientesAbiertosRequisicion(int $requisicionId, string $observacion): void
    {
        if ($requisicionId <= 0) {
            return;
        }
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $obs = Str::limit(trim($observacion) !== '' ? trim($observacion) : 'Sin efecto', 255, '');
        Arbolaprobacion_Movimiento::query()
            ->where('requisicion_id', $requisicionId)
            ->where('estado', $nombrePendiente)
            ->whereNull('fechaproceso')
            ->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => $obs,
            ]);
    }

    /**
     * @see anulaMovimientosArbolPendientesAbiertosRequisicion
     */
    public function anulaMovimientosArbolPendientesAbiertosOrdencompra(int $ordencompraId, string $observacion): void
    {
        if ($ordencompraId <= 0) {
            return;
        }
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $obs = Str::limit(trim($observacion) !== '' ? trim($observacion) : 'Sin efecto', 255, '');
        Arbolaprobacion_Movimiento::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('estado', $nombrePendiente)
            ->whereNull('fechaproceso')
            ->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => $obs,
            ]);
    }

    private function finalizaOrdenVentaTrasArbolCompleto(int $ordenventa_id, $usuarioHistoriaId): void
    {
        $estado = Ordenventa_Estado::$enumEstado[array_search('P', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];
        $data = [];
        $data['fechas'][] = Carbon::now();
        $data['estados'][] = $estado;
        $data['usuario_ids'][] = $usuarioHistoriaId;
        $data['observacionestados'][] = 'Orden de Venta Aprobada';

        $this->ordenventa_estadoRepository->create($data, $ordenventa_id);
        $this->ordenventaRepository->update(['estado' => $estado], $ordenventa_id);
    }

    private function finalizaRequisicionTrasArbolCompleto(int $requisicion_id, $usuarioHistoriaId): void
    {
        $aprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $req = $this->requisicionRepository->find($requisicion_id);
        if ($req->estado === $aprobada) {
            return;
        }

        $this->requisicion_estadoRepository->creaEstado(
            $requisicion_id,
            Carbon::now()->toDateTimeString(),
            $aprobada,
            $usuarioHistoriaId,
            'Requisición aprobada (árbol completo)'
        );
        $this->requisicionRepository->update(['estado' => $aprobada], $requisicion_id);
    }

    private function finalizaOrdencompraTrasArbolCompleto(int $ordencompra_id, $usuarioHistoriaId): void
    {
        $aprobada = OrdencompraEstados::APROBADA;
        $oc = $this->ordencompraRepository->find($ordencompra_id);
        if ($oc->estadoordencompra === $aprobada) {
            return;
        }

        $this->ordencompra_estadoRepository->creaEstado(
            $ordencompra_id,
            Carbon::now()->toDateTimeString(),
            $aprobada,
            $usuarioHistoriaId,
            'Orden de compra aprobada (árbol completo)'
        );
        $this->ordencompraRepository->update(['estadoordencompra' => $aprobada], $ordencompra_id);
    }

    private function finalizaOrdencompraTrasArbolCambioSectorCompleto(int $ordencompra_id, $usuarioHistoriaId, Arbolaprobacion $arbol): void
    {
        $this->finalizaOrdencompraTrasArbolCompleto($ordencompra_id, $usuarioHistoriaId);

        $sectorDestinoId = (int) ($arbol->oc_sector_destino_aprobacion_id ?? 0);
        if ($sectorDestinoId <= 0) {
            $sectorDestinoId = (int) Sector_Legajocompra::query()
                ->whereRaw('UPPER(TRIM(nombre)) = ?', ['CUENTAS A PAGAR'])
                ->value('id');
        }
        if ($sectorDestinoId <= 0) {
            return;
        }

        $oc = $this->ordencompraRepository->find($ordencompra_id);
        if (! $oc || (int) ($oc->sector_legajocompra_id ?? 0) === $sectorDestinoId) {
            return;
        }

        $this->ordencompraRepository->update(['sector_legajocompra_id' => $sectorDestinoId], $ordencompra_id);
        Ordencompra_Historia::create([
            'ordencompra_id' => $ordencompra_id,
            'sector_legajocompra_id' => $sectorDestinoId,
            'fecha' => Carbon::now(),
            'observacion' => 'Traslado automático tras aprobación por cambio de sector',
            'leyenda' => 'Árbol de aprobación OC — circuito cambio de sector',
            'creousuario_id' => $usuarioHistoriaId,
        ]);
    }

    private function finalizaOrdencompraTrasArbolTriggerCompleto(int $ordencompra_id, $usuarioHistoriaId, Arbolaprobacion_OcTrigger $trigger): void
    {
        if ($trigger->evento === OcArbolTriggerCatalog::EVENTO_CAMBIO_SECTOR
            || $trigger->accion_final === OcArbolTriggerCatalog::ACCION_CAMBIAR_SECTOR) {
            $this->finalizaOrdencompraTrasArbolCompleto($ordencompra_id, $usuarioHistoriaId);
        }

        app(OcArbolTriggerAccionFinalService::class)->ejecutar(
            $trigger,
            $ordencompra_id,
            (int) $usuarioHistoriaId
        );
    }

    public function aplicarEstadoOrdencompraPublico(int $ordencompraId, string $estadoNombre, string $observacion, int $usuarioHistoriaId): void
    {
        $this->aplicaEstadoOrdencompraPorNombre($ordencompraId, $estadoNombre, $observacion, $usuarioHistoriaId);
    }

    private function resolverOcTriggerDesdeOpciones(array $opciones, int $arbolId): ?Arbolaprobacion_OcTrigger
    {
        if (! empty($opciones['oc_trigger']) && $opciones['oc_trigger'] instanceof Arbolaprobacion_OcTrigger) {
            return $opciones['oc_trigger'];
        }

        $triggerId = (int) ($opciones['oc_trigger_id'] ?? 0);
        if ($triggerId <= 0) {
            return null;
        }

        return Arbolaprobacion_OcTrigger::query()
            ->where('id', $triggerId)
            ->where('arbolaprobacion_id', $arbolId)
            ->where('activo', 'S')
            ->first();
    }

    private function aplicaEstadoOrdencompraPorNombre(int $ordencompra_id, ?string $estadoNombre, string $observacion, $usuarioHistoriaId): void
    {
        if ($estadoNombre === null || $estadoNombre === '') {
            return;
        }
        if (! OrdencompraEstados::esNombreValido($estadoNombre)) {
            return;
        }

        $this->ordencompra_estadoRepository->creaEstado(
            $ordencompra_id,
            Carbon::now()->toDateTimeString(),
            $estadoNombre,
            $usuarioHistoriaId,
            $observacion
        );
        $this->ordencompraRepository->update(['estadoordencompra' => $estadoNombre], $ordencompra_id);
    }

    private function usuarioHistoriaOrdencompra(Ordencompra $ordencompra): int
    {
        if (Auth::check()) {
            return Auth::user()->id;
        }

        return $ordencompra->creousuario_id;
    }

    /**
     * @throws \RuntimeException
     */
    public function centroCostoParaArbolAprobacionDesdeOrdencompra(Ordencompra $ordencompra): int
    {
        $ordencompra->loadMissing('ordencompra_articulos');
        $ids = [];
        foreach ($ordencompra->ordencompra_articulos as $linea) {
            if (empty($linea->articulo_id) || (float) $linea->cantidad <= 0) {
                continue;
            }
            $cid = $linea->centrocostodestino_id ?? $ordencompra->centrocosto_id;
            if ($cid !== null && $cid !== '') {
                $ids[] = (int) $cid;
            }
        }
        $unique = array_unique($ids);
        if (count($unique) > 1) {
            throw new \RuntimeException('Todos los renglones deben tener el mismo centro de costo de destino para el árbol de aprobación.');
        }
        if (count($unique) === 1) {
            return (int) reset($unique);
        }

        return (int) $ordencompra->centrocosto_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function armaExtrasMailOrdencompra(
        Ordencompra $ordencompra,
        ?string $estadoAlAprobarEsteNivel,
        string $observacionEnvio = ''
    ): array {
        $totales = OrdencompraTotalesCabecera::desdeModelo($ordencompra, $this->cotizacionQuery);

        return [
            'estado_tras_aprobar' => $estadoAlAprobarEsteNivel !== null && $estadoAlAprobarEsteNivel !== ''
                ? $estadoAlAprobarEsteNivel
                : null,
            'monto_items' => $totales['monto'],
            'moneda_abrev_items' => $totales['monedacabecera_abreviatura'],
            'comentario_envio' => $observacionEnvio,
        ];
    }

    /**
     * Estado de requisición al aprobar un nivel: el configurado en el árbol o APROBADA si no hay ninguno.
     */
    private function estadoRequisicionAlAprobarNivel(?string $estadoConfigurado): string
    {
        $s = $estadoConfigurado !== null ? trim($estadoConfigurado) : '';
        if ($s !== '' && Requisicion_Estado::esNombreEstadoValido($s)) {
            return $s;
        }

        return Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
    }

    private function aplicaEstadoRequisicionPorNombre(int $requisicion_id, ?string $estadoNombre, string $observacion, $usuarioHistoriaId): void
    {
        $estadoNombre = $this->estadoRequisicionAlAprobarNivel($estadoNombre);

        $this->requisicion_estadoRepository->creaEstado(
            $requisicion_id,
            Carbon::now()->toDateTimeString(),
            $estadoNombre,
            $usuarioHistoriaId,
            $observacion
        );
        $this->requisicionRepository->update(['estado' => $estadoNombre], $requisicion_id);
    }

    private function usuarioHistoriaRequisicion($requisicion): int
    {
        if (Auth::check()) {
            return Auth::user()->id;
        }

        return $requisicion->creousuario_id;
    }

    protected function encuentraNivelCoincidente(
        Arbolaprobacion $arbol,
        $centrocosto_id,
        $numeroNivel,
        $fecha,
        $montoOriginal,
        $moneda_id
    ): ?Arbolaprobacion_Nivel {
        $nivelesCc = [];
        foreach ($arbol->arbolaprobacion_niveles as $nivel) {
            if ((int) $nivel->centrocosto_id === (int) $centrocosto_id) {
                $nivelesCc[] = $nivel;
            }
        }
        $dobleAprobacion = $this->centrocostoTieneDobleAprobacion($nivelesCc);
        $umbralPisoDoble = $dobleAprobacion
            ? $this->umbralPisoDobleAprobacion($nivelesCc)
            : null;

        $candidatosMismoNivel = [];
        foreach ($nivelesCc as $nivel) {
            if ((int) $nivel->nivel !== (int) $numeroNivel) {
                continue;
            }

            $coeficienteConversion = 1.;
            if ($nivel->moneda_id != $moneda_id && $moneda_id !== null && $moneda_id !== '') {
                $cotizacion = $this->cotizacionService->leeCotizacionDiaria($fecha, $moneda_id);
                $coeficienteConversion = (float) calculaCoeficienteMoneda($nivel->moneda_id, $moneda_id, $cotizacion);
                if ($coeficienteConversion == 0) {
                    $coeficienteConversion = 1.;
                }
            }

            $montoEnMonedaNivel = (float) $montoOriginal * $coeficienteConversion;
            $enRango = $this->nivelAplicaPorMonto(
                $nivel,
                $montoEnMonedaNivel,
                $dobleAprobacion,
                $umbralPisoDoble
            );
            if ($enRango) {
                return $nivel;
            }
            $candidatosMismoNivel[] = $nivel;
        }

        // El movimiento ya existió para este nivel; si el monto de cabecera cambió o no calzaba el rango,
        // usar la fila de configuración del mismo centro y número de nivel para aplicar documento_estado_al_aprobar.
        return $candidatosMismoNivel[0] ?? null;
    }

    public function rechazar($tipocomprobante, $comprobante_id, $aprobacion_id, $usuario_id, $observacion)
    {
        DB::beginTransaction();
        try {
            $movimientoPre = Arbolaprobacion_Movimiento::findOrFail($aprobacion_id);

            $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombreRechazado = Arbolaprobacion_Movimiento::$enumEstado[array_search('R', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
            $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

            // First-wins: si otro ya gestionó, no avanzamos.
            $rows = Arbolaprobacion_Movimiento::where('id', $aprobacion_id)
                ->where('estado', $nombrePendiente)
                ->update([
                    'fechaproceso' => Carbon::now(),
                    'estado' => $nombreRechazado,
                    'observacion' => (string) ($observacion ?? ''),
                ]);
            if ($rows === 0) {
                DB::commit();

                return;
            }

            // Invalida el resto de los usuarios del mismo nivel/comprobante.
            $q = Arbolaprobacion_Movimiento::where('arbolaprobacion_id', $movimientoPre->arbolaprobacion_id)
                ->where('nivel', $movimientoPre->nivel)
                ->where('estado', $nombrePendiente)
                ->where('id', '!=', $aprobacion_id);
            if ($movimientoPre->requisicion_id) {
                $q->where('requisicion_id', $movimientoPre->requisicion_id);
            } elseif ($movimientoPre->requisicion_sala_id) {
                $q->where('requisicion_sala_id', $movimientoPre->requisicion_sala_id);
            } elseif ($movimientoPre->ordenventa_id) {
                $q->where('ordenventa_id', $movimientoPre->ordenventa_id);
            } elseif ($movimientoPre->pedido_id) {
                $q->where('pedido_id', $movimientoPre->pedido_id);
            } elseif ($movimientoPre->solicitudpago_id) {
                $q->where('solicitudpago_id', $movimientoPre->solicitudpago_id);
            } elseif ($movimientoPre->propuesta_pago_id) {
                $q->where('propuesta_pago_id', $movimientoPre->propuesta_pago_id);
            } elseif ($movimientoPre->ordencompra_id) {
                $q->where('ordencompra_id', $movimientoPre->ordencompra_id);
            }
            $q->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => 'Sin efecto (otro usuario rechazó el nivel)',
            ]);

            // Actualiza comprobantes
            switch ($tipocomprobante) {
                case 'OV':
                    $estado = Ordenventa_Estado::$enumEstado[array_search('R', array_column(Ordenventa_Estado::$enumEstado, 'valor'))]['nombre'];

                    // Graba estado de aprobacion
                    $data = [];

                    $data['fechas'][] = Carbon::now();
                    $data['estados'][] = $estado;
                    $data['usuario_ids'][] = $usuario_id;
                    $data['observacionestados'][] = 'Orden de Venta Rechazada';

                    $ordenventa_estado = $this->ordenventa_estadoRepository->create($data, $comprobante_id);

                    $this->ordenventaRepository->update([
                        'estado' => $estado,
                        'observacion' => $observacion,
                    ], $comprobante_id);
                    break;
                case 'RE':
                    $estado = Requisicion_Estado::$enumEstado[array_search('S', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
                    $this->requisicion_estadoRepository->creaEstado(
                        $comprobante_id,
                        Carbon::now()->toDateTimeString(),
                        $estado,
                        $usuario_id,
                        'Requisición suspendida / rechazada en árbol: '.$observacion
                    );
                    $this->requisicionRepository->update(['estado' => $estado], $comprobante_id);
                    break;
                case 'RS':
                    app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)
                        ->rechazaPorRechazo($comprobante_id, $usuario_id, $observacion);
                    break;
                case 'PE':
                    app(\App\Services\Ventas\PedidoInterformingArbolIntegracionService::class)
                        ->rechazaPorRechazo((int) $comprobante_id, $usuario_id, $observacion);
                    break;
                case 'SP':
                    app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                        ->rechazaPorRechazo((int) $comprobante_id, $usuario_id, $observacion);
                    break;
                case 'PP':
                    app(\App\Services\Compras\PropuestaPagoArbolIntegracionService::class)
                        ->rechazaPorRechazo((int) $comprobante_id, $usuario_id, $observacion);
                    break;
                case 'OC':
                    $estadoOc = OrdencompraEstados::SUSPENDIDA;
                    $this->ordencompra_estadoRepository->creaEstado(
                        (int) $comprobante_id,
                        Carbon::now()->toDateTimeString(),
                        $estadoOc,
                        (int) $usuario_id,
                        'Orden de compra suspendida / rechazada en árbol: '.$observacion
                    );
                    $this->ordencompraRepository->update(['estadoordencompra' => $estadoOc], $comprobante_id);
                    break;
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }
    }

    public function nombreTipoArbolRequisiciones(): string
    {
        return Arbolaprobacion::$enumTipoArbol[array_search('RE', array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    }

    /**
     * Firmantes del siguiente nivel al retomar el árbol desde EN COMPRAS (botón «Envía al árbol»).
     *
     * @return array{
     *     nivel?: int,
     *     requiere_seleccion?: bool,
     *     firmantes?: list<array{id: int, nombre: string, usuario: string, email: string}>,
     *     requiere_seleccion_centrocosto?: bool,
     *     centros_costo?: list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     * }
     */
    public function firmantesRetomeArbolRequisicion(Requisicion $requisicion, ?int $centrocostoArbolSeleccionado = null): array
    {
        $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        if ($requisicion->estado !== $nombreEnCompras) {
            throw new \RuntimeException('Solo se puede consultar firmantes cuando la requisición está en estado EN COMPRAS.');
        }

        $idsDistintos = $this->centrosCostoDestinoDistintosIdsDesdeModelo($requisicion);
        $centrocostoArbol = $this->resolverCentroCostoArbolRequisicion($requisicion, $centrocostoArbolSeleccionado, $idsDistintos);

        if ($centrocostoArbol === null) {
            return [
                'requiere_seleccion_centrocosto' => true,
                'centros_costo' => $this->armarListadoCentrosCostoDestinoArbol($idsDistintos),
            ];
        }

        $this->validaRequisicionModeloContraArbolConCentrocosto($requisicion, $centrocostoArbol);

        $tipoarbol = $this->nombreTipoArbolRequisiciones();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($tipoarbol, (int) $requisicion->empresa_id);
        if ($trees->isEmpty() || $trees->count() !== 1) {
            throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones para la empresa de la requisición.');
        }

        $arbol = $trees->first();
        $estadoAprobacionActual = $this->leeAprobacionComprobante($tipoarbol, (int) $requisicion->id);
        $totalesReq = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);
        $proximoNivel = $this->buscaProximoNivel(
            $arbol,
            $centrocostoArbol,
            $estadoAprobacionActual['nivelactual'],
            $requisicion->fecha,
            $totalesReq['monto'],
            $totalesReq['moneda_id']
        );
        $proximoNivel = $this->filtrarProximoNivelUsuariosPorEmpresa($proximoNivel, (int) $requisicion->empresa_id);

        if ($proximoNivel['proximonivel'] <= 0) {
            throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para continuar el circuito.');
        }

        $uids = $proximoNivel['proximousuarios'] ?? [];
        if (! is_array($uids) || count($uids) === 0) {
            $uids = $proximoNivel['proximousuario'] ? [(int) $proximoNivel['proximousuario']] : [];
        }
        $uids = array_values(array_unique(array_filter(array_map('intval', $uids))));

        $firmantes = [];
        foreach ($uids as $uid) {
            $usuario = $this->usuarioRepository->findOperativo($uid);
            if (! $usuario) {
                continue;
            }
            $nombre = trim((string) ($usuario->nombre ?? ''));
            $firmantes[] = [
                'id' => (int) $usuario->id,
                'nombre' => $nombre !== '' ? $nombre : (string) ($usuario->usuario ?? ''),
                'usuario' => (string) ($usuario->usuario ?? ''),
                'email' => (string) ($usuario->email ?? ''),
            ];
        }

        return [
            'nivel' => (int) $proximoNivel['proximonivel'],
            'requiere_seleccion' => count($firmantes) > 1,
            'firmantes' => $firmantes,
            'centrocosto_arbol_id' => $centrocostoArbol,
        ];
    }

    /**
     * IDs de centros de costo de destino distintos en renglones válidos de la requisición.
     *
     * @return list<int>
     */
    public function centrosCostoDestinoDistintosIdsDesdeModelo(Requisicion $requisicion): array
    {
        $requisicion->loadMissing('requisicion_articulos');
        $ids = [];
        foreach ($requisicion->requisicion_articulos as $linea) {
            if (empty($linea->articulo_id) || (float) $linea->cantidad <= 0) {
                continue;
            }
            $cid = $linea->centrocostodestino_id ?? $requisicion->centrocosto_id;
            if ($cid !== null && $cid !== '') {
                $ids[] = (int) $cid;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resuelve el centro de costo del circuito de aprobación. Null si hay varios y falta selección.
     * La selección del usuario puede ser un CC de los renglones o uno adicional (fuera de la grilla).
     *
     * @param  list<int>  $idsDistintos
     *
     * @throws \RuntimeException
     */
    public function resolverCentroCostoArbolRequisicion(Requisicion $requisicion, ?int $seleccionUsuario, array $idsDistintos): ?int
    {
        if ($seleccionUsuario !== null && $seleccionUsuario > 0) {
            $this->assertCentrocostoExisteParaArbol($seleccionUsuario);

            return $seleccionUsuario;
        }

        // Regla general: un solo CC de destino de renglón manda el circuito.
        // Excepción Capital Humano: pueden fijar otro CC (p. ej. origen) y conservarlo.
        if (count($idsDistintos) === 1
            && ! RequisicionCentrocostoArbolOrigenSupport::permiteCircuitoDistintoDeDestino(
                (int) ($requisicion->creousuario_id ?? 0)
            )
        ) {
            return (int) reset($idsDistintos);
        }

        $persistido = (int) ($requisicion->centrocostodestino_arbol_id ?? 0);
        if ($persistido > 0) {
            if (Centrocosto::query()->whereKey($persistido)->exists()) {
                return $persistido;
            }
        }

        if (count($idsDistintos) === 0) {
            return (int) $requisicion->centrocosto_id;
        }
        if (count($idsDistintos) === 1) {
            return (int) reset($idsDistintos);
        }

        return null;
    }

    /**
     * @throws \RuntimeException
     */
    private function assertCentrocostoExisteParaArbol(int $centrocostoId): void
    {
        if ($centrocostoId <= 0 || ! Centrocosto::query()->whereKey($centrocostoId)->exists()) {
            throw new \RuntimeException('El centro de costo de destino seleccionado no existe.');
        }
    }

    /**
     * @param  list<int>  $idsDistintos
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    public function listadoCentrosCostoDestinoArbol(array $idsDistintos): array
    {
        if ($idsDistintos === []) {
            return [];
        }

        $centrocostos = Centrocosto::query()
            ->whereIn('id', $idsDistintos)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $listado = [];
        foreach ($centrocostos as $cc) {
            $listado[] = [
                'id' => (int) $cc->id,
                'codigo' => (string) $cc->codigo,
                'nombre' => (string) $cc->nombre,
                'etiqueta' => trim((string) $cc->codigo.' '.(string) $cc->nombre),
            ];
        }

        return $listado;
    }

    /**
     * @param  list<int>  $idsDistintos
     * @return list<array{id: int, codigo: string, nombre: string, etiqueta: string}>
     */
    private function armarListadoCentrosCostoDestinoArbol(array $idsDistintos): array
    {
        return $this->listadoCentrosCostoDestinoArbol($idsDistintos);
    }

    /**
     * Centro de costo usado para niveles del árbol: CC destino de los ítems (debe ser único); si no hay ítems válidos, cabecera.
     *
     * @throws \RuntimeException
     */
    public function centroCostoParaArbolAprobacionDesdeModelo(Requisicion $requisicion): int
    {
        $idsDistintos = $this->centrosCostoDestinoDistintosIdsDesdeModelo($requisicion);
        $cc = $this->resolverCentroCostoArbolRequisicion($requisicion, null, $idsDistintos);
        if ($cc === null || $cc <= 0) {
            throw new \RuntimeException('Todos los renglones deben tener el mismo centro de costo de destino para el árbol de aprobación.');
        }

        return $cc;
    }

    /**
     * @throws \RuntimeException
     */
    public function validaRequisicionRequestContraArbol(array $data): void
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }
        $nombreTipo = $this->nombreTipoArbolRequisiciones();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);
        if ($trees->isEmpty()) {
            throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones para la empresa seleccionada.');
        }
        if ($trees->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.');
        }
        $cc = $this->centroCostoParaArbolDesdeRequest($data);
        [$monto, $monedaId] = $this->montoYMonedaDesdeLineasRequisicionRequest($data);
        $fecha = $data['fecha'] ?? date('Y-m-d');
        $rid = (int) ($data['requisicion_id'] ?? 0);
        $nivelActual = $rid > 0 ? $this->leeAprobacionComprobante($nombreTipo, $rid)['nivelactual'] : 0;
        $arbol = $trees->first();
        $prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $fecha, $monto, $monedaId);
        $prox = $this->filtrarProximoNivelUsuariosPorEmpresa($prox, $empresaId);
        if ($prox['proximonivel'] === 0) {
            throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la requisición.');
        }
    }

    /**
     * @throws \RuntimeException
     */
    public function validaRequisicionModeloContraArbol(Requisicion $req): void
    {
        $cc = $this->centroCostoParaArbolAprobacionDesdeModelo($req);
        $this->validaRequisicionModeloContraArbolConCentrocosto($req, $cc);
    }

    /**
     * @throws \RuntimeException
     */
    private function validaRequisicionModeloContraArbolConCentrocosto(Requisicion $req, int $cc): void
    {
        $nombreTipo = $this->nombreTipoArbolRequisiciones();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $req->empresa_id);
        if ($trees->isEmpty()) {
            throw new \RuntimeException('No hay un árbol de aprobación activo de requisiciones para la empresa de la requisición.');
        }
        if ($trees->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.');
        }
        $nivelActual = $this->leeAprobacionComprobante($nombreTipo, $req->id)['nivelactual'];
        $arbol = $trees->first();
        $totalesReq = RequisicionTotalesCabecera::desdeModelo($req, $this->cotizacionQuery);
        $prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $req->fecha, $totalesReq['monto'], $totalesReq['moneda_id']);
        $prox = $this->filtrarProximoNivelUsuariosPorEmpresa($prox, (int) $req->empresa_id);
        if ($prox['proximonivel'] === 0) {
            throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la requisición.');
        }
    }

    /**
     * Mensaje si la empresa no tiene exactamente un árbol activo de requisiciones (solo cabecera empresa).
     */
    public function mensajeEmpresaSinArbolRequisicionActivoUnico(int $empresaId): ?string
    {
        if ($empresaId <= 0) {
            return null;
        }
        $nombreTipo = $this->nombreTipoArbolRequisiciones();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);
        if ($trees->isEmpty()) {
            return 'No hay un árbol de aprobación activo de requisiciones para la empresa seleccionada.';
        }
        if ($trees->count() > 1) {
            return 'Hay más de un árbol de aprobación activo de requisiciones para esa empresa; debe quedar uno solo.';
        }

        return null;
    }

    /**
     * En la consulta del árbol: si la requisición ya no está en el circuito activo de aprobación,
     * oculta ruido: cualquier movimiento "Sin efecto" y los pendientes sin procesar que quedaron en BD.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $movimientos
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function filtraMovimientosArbolRequisicionParaVistaConsulta($movimientos, ?Requisicion $req)
    {
        if (! $req) {
            return $movimientos;
        }
        $nombresDocFueraCircuitoActivo = [
            Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            Requisicion_Estado::$enumEstado[array_search('C', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'],
            'GENERO OC',
        ];
        if (! in_array($req->estado, $nombresDocFueraCircuitoActivo, true)) {
            return $movimientos;
        }
        $nombrePendienteMov = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

        return $movimientos->filter(function (array $row) use ($nombrePendienteMov, $nombreSinEfecto) {
            $estado = (string) ($row['estado'] ?? '');
            if ($estado === $nombreSinEfecto) {
                return false;
            }
            if ($estado === $nombrePendienteMov && empty($row['fechaproceso'])) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @return array{movimientos: array<int, array<string, mixed>>, aviso_grabacion_pendiente: string|null}
     */
    public function movimientosRequisicionConAvisoGrabacion(int $requisicionId): array
    {
        $movs = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($requisicionId);
        $req = $this->requisicionRepository->find($requisicionId);
        $enriquecidos = $this->adjuntaIndicacionEstadoRequisicionMovimientos($movs, $requisicionId);
        $enriquecidos = $this->filtraMovimientosArbolRequisicionParaVistaConsulta($enriquecidos, $req);
        $aviso = null;
        if ($req) {
            $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            if ($req->estado === $pendiente) {
                try {
                    $this->validaRequisicionModeloContraArbol($req);
                } catch (\RuntimeException $e) {
                    $aviso = $e->getMessage();
                }
            }
        }

        $panelIa = null;
        if ($req) {
            $movPendiente = $this->movimientoPendientePorTipo('RE', $requisicionId);
            $estadoTras = $movPendiente
                ? $this->estadoTrasAprobarSegunMovimientoRequisicion($req, $movPendiente)
                : null;
            $panelIa = $this->panelIaContextoArbol('RE', $req, $movPendiente, $estadoTras);
        }

        return [
            'movimientos' => $enriquecidos->values()->all(),
            'aviso_grabacion_pendiente' => $aviso,
            'ai_contexto_arbol' => $panelIa,
        ];
    }

    /**
     * @param  int  $empresaId  empresa elegida en el formulario (alta)
     * @param  int  $requisicionId  id de requisición en edición, o 0 en alta
     */
    public function avisoGrabacionRequisicionAjax(int $empresaId, int $requisicionId): ?string
    {
        if ($requisicionId > 0) {
            $req = $this->requisicionRepository->find($requisicionId);
            if (! $req) {
                return null;
            }
            $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            if ($req->estado !== $pendiente) {
                return null;
            }
            $idsDistintos = $this->centrosCostoDestinoDistintosIdsDesdeModelo($req);
            if (count($idsDistintos) > 1 && (int) ($req->centrocostodestino_arbol_id ?? 0) <= 0) {
                return 'La requisición tiene renglones con distintos centros de costo de destino. Al guardar deberá elegir con cuál continuar el árbol de aprobación.';
            }

            try {
                $this->validaRequisicionModeloContraArbol($req);
            } catch (\RuntimeException $e) {
                return $e->getMessage();
            }

            return null;
        }

        return $this->mensajeEmpresaSinArbolRequisicionActivoUnico($empresaId);
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $movimientos
     * @return \Illuminate\Support\Collection
     */
    public function adjuntaIndicacionEstadoRequisicionMovimientos($movimientos, int $requisicion_id)
    {
        $req = $this->requisicionRepository->find($requisicion_id);
        $nombreTipo = $this->nombreTipoArbolRequisiciones();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $req->empresa_id);
        $arbol = $trees->first();
        $cc = null;
        $ccIndicacionError = null;
        try {
            $cc = $this->centroCostoParaArbolAprobacionDesdeModelo($req);
        } catch (\RuntimeException $e) {
            $ccIndicacionError = $e->getMessage();
            $cc = (int) $req->centrocosto_id;
        }

        $totalesReq = RequisicionTotalesCabecera::desdeModelo($req, $this->cotizacionQuery);

        return $movimientos->map(function ($m) use ($arbol, $cc, $req, $ccIndicacionError, $totalesReq) {
            $row = $m->toArray();
            if ($ccIndicacionError !== null) {
                $row['indicacion_estado_requisicion'] = $ccIndicacionError;

                return $row;
            }
            if ($arbol) {
                $nivelCfg = $this->encuentraNivelCoincidente(
                    $arbol,
                    $cc,
                    (int) $m->nivel,
                    $req->fecha,
                    $totalesReq['monto'],
                    $totalesReq['moneda_id']
                );
                $est = $nivelCfg
                    ? $this->estadoRequisicionAlAprobarNivel($nivelCfg->documento_estado_al_aprobar)
                    : null;
                $row['indicacion_estado_requisicion'] = $est !== null
                    ? 'Tras aprobar este nivel, la requisición quedaría en estado: '.$est.'.'
                    : 'No se pudo determinar el estado al aprobar este nivel.';
            } else {
                $row['indicacion_estado_requisicion'] = 'No hay árbol de aprobación activo para la empresa de esta requisición.';
            }

            return $row;
        })->values();
    }

    /**
     * IDs de centros de costo de destino distintos en renglones válidos del request.
     *
     * @return list<int>
     */
    public function centrosCostoDestinoDistintosIdsDesdeRequest(array $data): array
    {
        $articulo_ids = $data['articulo_ids'] ?? [];
        if (! is_array($articulo_ids)) {
            return [];
        }
        $headerCc = (int) ($data['centrocosto_id'] ?? 0);
        $n = count($articulo_ids);
        $ids = [];
        for ($i = 0; $i < $n; $i++) {
            $aid = $articulo_ids[$i] ?? null;
            if ($aid === null || $aid === '') {
                continue;
            }
            $cant = (float) ($data['cantidades'][$i] ?? 0);
            if ($cant <= 0) {
                continue;
            }
            $dest = isset($data['centrocostodestino_ids'][$i]) && $data['centrocostodestino_ids'][$i] !== ''
                ? (int) $data['centrocostodestino_ids'][$i]
                : $headerCc;
            if ($dest > 0) {
                $ids[] = $dest;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Resuelve el CC del circuito desde el request. Null si hay varios y falta selección.
     * Acepta un CC adicional fuera de los renglones (mismo criterio que el modal de envío al árbol).
     *
     * @param  list<int>  $idsDistintos
     *
     * @throws \RuntimeException
     */
    public function resolverCentroCostoArbolDesdeRequest(array $data, ?int $seleccionUsuario, array $idsDistintos): ?int
    {
        $creousuarioId = (int) ($data['creousuario_id'] ?? 0);
        if ($creousuarioId <= 0 && ! empty($data['requisicion_id'])) {
            $creousuarioId = (int) (Requisicion::query()->whereKey((int) $data['requisicion_id'])->value('creousuario_id') ?? 0);
        }
        $permiteDistinto = RequisicionCentrocostoArbolOrigenSupport::permiteCircuitoDistintoDeDestino(
            $creousuarioId > 0 ? $creousuarioId : null
        );

        // Sin excepción CH: el único destino de renglón gana sobre selección/persistido obsoleto.
        if (count($idsDistintos) === 1 && ! $permiteDistinto) {
            return (int) reset($idsDistintos);
        }

        if ($seleccionUsuario !== null && $seleccionUsuario > 0) {
            $this->assertCentrocostoExisteParaArbol($seleccionUsuario);

            return $seleccionUsuario;
        }

        $persistido = (int) ($data['centrocostodestino_arbol_id'] ?? 0);
        if ($persistido > 0 && Centrocosto::query()->whereKey($persistido)->exists()) {
            return $persistido;
        }

        if (count($idsDistintos) === 0) {
            return (int) ($data['centrocosto_id'] ?? 0);
        }
        if (count($idsDistintos) === 1) {
            return (int) reset($idsDistintos);
        }

        return null;
    }

    private function centroCostoParaArbolDesdeRequest(array $data): int
    {
        $idsDistintos = $this->centrosCostoDestinoDistintosIdsDesdeRequest($data);
        $seleccion = (int) ($data['centrocostodestino_arbol_id'] ?? 0);
        $seleccion = $seleccion > 0 ? $seleccion : null;
        $cc = $this->resolverCentroCostoArbolDesdeRequest($data, $seleccion, $idsDistintos);
        if ($cc === null) {
            throw new \RuntimeException('Todos los renglones deben tener el mismo centro de costo de destino para el árbol de aprobación.');
        }

        return $cc;
    }

    /**
     * @return array{0: float, 1: int|null}
     */
    private function montoYMonedaDesdeLineasRequisicionRequest(array $data): array
    {
        return RequisicionTotalesCabecera::montoYMonedaDesdeRequest($data, $this->cotizacionQuery);
    }

    public function movimientoRequisicionPendientePorHash(int $requisicionId, string $hash, string $modo): ?Arbolaprobacion_Movimiento
    {
        $hash = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $movimientos = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($requisicionId);
        foreach ($movimientos as $movimiento) {
            if ($movimiento->estado !== $nombrePendiente) {
                continue;
            }
            if ($modo === 'aprobacion' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashaprobacion)) {
                return $movimiento;
            }
            if ($modo === 'rechazo' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashrechazo)) {
                return $movimiento;
            }
        }

        return null;
    }

    /**
     * @return array{requisicion: Requisicion, movimiento: Arbolaprobacion_Movimiento, estado_tras_aprobar: ?string, monto_items: float, moneda_abrev_items: string, historial_aprobaciones: list<array<string, mixed>>}|null
     */
    public function portalDatosRequisicionPorHash(int $requisicionId, string $hash, string $modo): ?array
    {
        $mov = $this->movimientoRequisicionPendientePorHash($requisicionId, $hash, $modo);
        if (! $mov) {
            return null;
        }
        $requisicion = $this->requisicionRepository->find($requisicionId);
        $totales = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);
        RequisicionTotalesCabecera::aplicarAtributosVirtuales($requisicion, $this->cotizacionQuery);
        $estadoTrasAprobar = null;
        if ($modo === 'aprobacion') {
            $estadoTrasAprobar = $this->estadoTrasAprobarSegunMovimientoRequisicion($requisicion, $mov);
        }

        return [
            'requisicion' => $requisicion,
            'movimiento' => $mov,
            'estado_tras_aprobar' => $estadoTrasAprobar,
            'monto_items' => (float) ($totales['monto'] ?? 0),
            'moneda_abrev_items' => (string) ($totales['monedacabecera_abreviatura'] ?? '—'),
            'historial_aprobaciones' => $this->historialAprobacionesRequisicion((int) $requisicionId),
            'ai_contexto_arbol' => $this->panelIaContextoArbol('RE', $requisicion, $mov, $estadoTrasAprobar),
        ];
    }

    /**
     * Aprobaciones previas del circuito (para portal/mail del 2° nivel / Finanzas).
     *
     * @return list<array{nivel: int, firmante: string, fecha: ?string, observacion: string}>
     */
    public function historialAprobacionesRequisicion(int $requisicionId): array
    {
        if ($requisicionId <= 0) {
            return [];
        }

        $nombreAprobado = Arbolaprobacion_Movimiento::$enumEstado[
            array_search('A', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $movimientos = $this->arbolaprobacion_movimientoRepository->findPorRequisicion($requisicionId);
        $salida = [];
        foreach ($movimientos as $mov) {
            if ((string) ($mov->estado ?? '') !== $nombreAprobado) {
                continue;
            }
            $firmante = trim((string) (optional($mov->destinatariousuarios)->nombre ?? ''));
            if ($firmante === '') {
                $firmante = trim((string) (optional($mov->destinatariousuarios)->usuario ?? ''));
            }
            $fecha = null;
            if (! empty($mov->fechaproceso)) {
                try {
                    $fecha = date('d/m/Y H:i', strtotime((string) $mov->fechaproceso));
                } catch (\Throwable $e) {
                    $fecha = (string) $mov->fechaproceso;
                }
            }
            $salida[] = [
                'nivel' => (int) ($mov->nivel ?? 0),
                'firmante' => $firmante !== '' ? $firmante : '—',
                'fecha' => $fecha,
                'observacion' => trim((string) ($mov->observacion ?? '')),
            ];
        }

        return $salida;
    }

    public function estadoTrasAprobarSegunMovimientoRequisicion(Requisicion $requisicion, Arbolaprobacion_Movimiento $mov): ?string
    {
        $arbol = $this->arbolaprobacionRepository->find($mov->arbolaprobacion_id);
        $centrocostoArbol = $this->centroCostoParaArbolAprobacionDesdeModelo($requisicion);
        $totales = RequisicionTotalesCabecera::desdeModelo($requisicion, $this->cotizacionQuery);
        $nivelCfg = $this->encuentraNivelCoincidente(
            $arbol,
            $centrocostoArbol,
            $mov->nivel,
            $requisicion->fecha,
            $totales['monto'],
            $totales['moneda_id']
        );
        if ($nivelCfg === null) {
            return null;
        }

        return $this->estadoRequisicionAlAprobarNivel($nivelCfg->documento_estado_al_aprobar);
    }

    public function nombreTipoArbolOrdenesCompra(): string
    {
        return Arbolaprobacion::$enumTipoArbol[array_search('OC', array_column(Arbolaprobacion::$enumTipoArbol, 'valor'))]['nombre'];
    }

    public function empresaTieneArbolOrdencompraActivoUnico(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return false;
        }
        $nombreTipo = $this->nombreTipoArbolOrdenesCompra();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);

        return $trees->count() === 1;
    }

    /**
     * Al cambiar el legajo de una OC a un sector configurado, dispara el circuito opcional del árbol OC.
     */
    public function dispararArbolOrdencompraAlCambiarSector(
        int $ordencompraId,
        int $sectorLegajocompraId,
        ?string $observacionEnvio = null
    ): void {
        if ($ordencompraId <= 0 || $sectorLegajocompraId <= 0) {
            return;
        }

        $oc = $this->ordencompraRepository->find($ordencompraId);
        if (! $oc) {
            return;
        }

        $arbol = $this->arbolOrdencompraActivoParaEmpresa((int) $oc->empresa_id);
        if (! $this->tieneCircuitoCambioSectorConfigurado($arbol)) {
            return;
        }
        if (! $this->sectorDisparaCircuitoCambio($arbol, $sectorLegajocompraId)) {
            return;
        }

        $this->anulaMovimientosArbolPendientesOrdencompraCircuito(
            $ordencompraId,
            self::CIRCUITO_OC_CAMBIO_SECTOR,
            'Reinicio del circuito por cambio de sector'
        );

        $opciones = ['circuito_sector' => true];
        $obs = $this->normalizarObservacionEnvio($observacionEnvio);
        if ($obs !== '') {
            $opciones['observacion_envio'] = $obs;
        }

        $this->procesaArbolaprobacion('OC', $ordencompraId, 'insert', $opciones);
    }

    public function arbolOrdencompraActivoParaEmpresa(int $empresaId): ?Arbolaprobacion
    {
        if ($empresaId <= 0) {
            return null;
        }
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa(
            $this->nombreTipoArbolOrdenesCompra(),
            $empresaId
        );

        return $trees->count() === 1 ? $trees->first() : null;
    }

    public function ocDispararArbolAlAlta(?Arbolaprobacion $arbol): bool
    {
        if (! $arbol) {
            return false;
        }

        return strtoupper((string) ($arbol->oc_disparar_arbol_al_alta ?? 'N')) === 'S';
    }

    public function debeProcesarArbolOrdencompraAlAlta(int $empresaId): bool
    {
        return $this->ocDispararArbolAlAlta($this->arbolOrdencompraActivoParaEmpresa($empresaId));
    }

    public function tieneCircuitoCambioSectorConfigurado(?Arbolaprobacion $arbol): bool
    {
        return $arbol !== null && (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0) > 0;
    }

    private function sectorDisparaCircuitoCambio(Arbolaprobacion $arbol, int $sectorLegajocompraId): bool
    {
        $disparoId = (int) ($arbol->oc_sector_disparo_aprobacion_id ?? 0);
        if ($disparoId > 0) {
            return $disparoId === $sectorLegajocompraId;
        }

        $sector = Sector_Legajocompra::query()->find($sectorLegajocompraId);

        return $sector !== null && strtoupper(trim((string) $sector->nombre)) === 'GASTRONOMIA';
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|null  $movimientos
     * @return \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection
     */
    private function filtrarMovimientosOrdencompraPorCircuito($movimientos, ?string $circuitoOc, ?int $ocTriggerId = null)
    {
        if ($movimientos === null) {
            return collect();
        }

        if ($ocTriggerId !== null && $ocTriggerId > 0) {
            return $movimientos->filter(function ($mov) use ($ocTriggerId) {
                return (int) ($mov->arbolaprobacion_oc_trigger_id ?? 0) === $ocTriggerId;
            })->values();
        }

        return $movimientos->filter(function ($mov) use ($circuitoOc) {
            $circuitoMov = $mov->circuito_oc ?? null;
            if ($circuitoOc === self::CIRCUITO_OC_CAMBIO_SECTOR) {
                return $circuitoMov === self::CIRCUITO_OC_CAMBIO_SECTOR;
            }

            return ($circuitoMov === null || $circuitoMov === '')
                && empty($mov->arbolaprobacion_oc_trigger_id);
        })->values();
    }

    private function aplicarFiltroCircuitoOcQuery($query, ?string $circuitoOc, ?int $ocTriggerId = null): void
    {
        if ($ocTriggerId !== null && $ocTriggerId > 0) {
            $query->where('arbolaprobacion_oc_trigger_id', $ocTriggerId);

            return;
        }

        if ($circuitoOc === self::CIRCUITO_OC_CAMBIO_SECTOR) {
            $query->where('circuito_oc', self::CIRCUITO_OC_CAMBIO_SECTOR);

            return;
        }

        $query->where(function ($w) {
            $w->whereNull('circuito_oc')->orWhere('circuito_oc', '');
        })->whereNull('arbolaprobacion_oc_trigger_id');
    }

    public function anulaMovimientosArbolPendientesOrdencompraTrigger(int $ordencompraId, int $ocTriggerId, string $observacion): void
    {
        if ($ordencompraId <= 0 || $ocTriggerId <= 0) {
            return;
        }
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $obs = Str::limit(trim($observacion) !== '' ? trim($observacion) : 'Sin efecto', 255, '');
        Arbolaprobacion_Movimiento::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('arbolaprobacion_oc_trigger_id', $ocTriggerId)
            ->where('estado', $nombrePendiente)
            ->whereNull('fechaproceso')
            ->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => $obs,
            ]);
    }

    public function anulaMovimientosArbolPendientesOrdencompraCircuito(int $ordencompraId, string $circuitoOc, string $observacion): void
    {
        if ($ordencompraId <= 0 || $circuitoOc === '') {
            return;
        }
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $obs = Str::limit(trim($observacion) !== '' ? trim($observacion) : 'Sin efecto', 255, '');
        Arbolaprobacion_Movimiento::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where('circuito_oc', $circuitoOc)
            ->where('estado', $nombrePendiente)
            ->whereNull('fechaproceso')
            ->update([
                'fechaproceso' => Carbon::now(),
                'estado' => $nombreSinEfecto,
                'observacion' => $obs,
            ]);
    }

    /**
     * Si no hay árbol activo para la empresa, no valida. Si hay uno, aplica las mismas reglas que requisiciones.
     *
     * @param  array<string, mixed>  $data
     */
    public function validaOrdencompraRequestContraArbolOpcional(array $data): void
    {
        $empresaId = (int) ($data['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }
        $nombreTipo = $this->nombreTipoArbolOrdenesCompra();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, $empresaId);
        if ($trees->isEmpty()) {
            return;
        }
        if ($trees->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de órdenes de compra para esa empresa; debe quedar uno solo.');
        }
        $arbol = $trees->first();
        $tieneTriggerAlta = Arbolaprobacion_OcTrigger::query()
            ->where('arbolaprobacion_id', $arbol->id)
            ->where('activo', 'S')
            ->where('tipo', OcArbolTriggerCatalog::TIPO_EVENTO)
            ->where('evento', OcArbolTriggerCatalog::EVENTO_ALTA)
            ->exists();
        if (! $tieneTriggerAlta && ! $this->ocDispararArbolAlAlta($arbol)) {
            return;
        }
        $cc = $this->centroCostoParaArbolDesdeRequest($data);
        [$monto, $monedaId] = OrdencompraTotalesCabecera::montoYMonedaDesdeRequest($data, $this->cotizacionQuery);
        $fecha = $data['fecha'] ?? date('Y-m-d');
        $oid = (int) ($data['ordencompra_id'] ?? 0);
        $nivelActual = $oid > 0 ? $this->leeAprobacionComprobante($nombreTipo, $oid)['nivelactual'] : 0;
        $prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $fecha, $monto, $monedaId);
        if ($prox['proximonivel'] === 0) {
            throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la orden de compra.');
        }
    }

    public function movimientoOrdencompraPendientePorHash(int $ordencompraId, string $hash, string $modo): ?Arbolaprobacion_Movimiento
    {
        $hash = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $movimientos = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($ordencompraId);
        foreach ($movimientos as $movimiento) {
            if ($movimiento->estado !== $nombrePendiente) {
                continue;
            }
            if ($modo === 'aprobacion' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashaprobacion)) {
                return $movimiento;
            }
            if ($modo === 'rechazo' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashrechazo)) {
                return $movimiento;
            }
        }

        return null;
    }

    /**
     * @return array{ordencompra: Ordencompra, movimiento: Arbolaprobacion_Movimiento, estado_tras_aprobar: ?string, monto_items: float, moneda_abrev_items: string}|null
     */
    public function portalDatosOrdencompraPorHash(int $ordencompraId, string $hash, string $modo): ?array
    {
        $mov = $this->movimientoOrdencompraPendientePorHash($ordencompraId, $hash, $modo);
        if (! $mov) {
            return null;
        }
        $ordencompra = $this->ordencompraRepository->find($ordencompraId);
        $totales = OrdencompraTotalesCabecera::desdeModelo($ordencompra, $this->cotizacionQuery);
        OrdencompraTotalesCabecera::aplicarAtributosVirtuales($ordencompra, $this->cotizacionQuery);
        $estadoTrasAprobar = null;
        if ($modo === 'aprobacion') {
            $estadoTrasAprobar = $this->estadoTrasAprobarSegunMovimientoOrdencompra($ordencompra, $mov);
        }

        return [
            'ordencompra' => $ordencompra,
            'movimiento' => $mov,
            'estado_tras_aprobar' => $estadoTrasAprobar,
            'monto_items' => (float) ($totales['monto'] ?? 0),
            'moneda_abrev_items' => (string) ($totales['monedacabecera_abreviatura'] ?? '—'),
            'ai_contexto_arbol' => $this->panelIaContextoArbol('OC', $ordencompra, $mov, $estadoTrasAprobar),
        ];
    }

    /**
     * Contexto IA de solo lectura para cualquier tipocomprobante del árbol.
     * Permiso de skill vacío: seguridad = hash del portal o permiso del ABM que llama al AJAX.
     *
     * @return array<string,mixed>|null
     */
    public function panelIaContextoArbol(
        string $tipocomprobante,
        object $documento,
        ?Arbolaprobacion_Movimiento $movimientoPendiente = null,
        ?string $estadoTrasAprobar = null,
    ): ?array {
        $skill = ExplicarContextoArbolAprobacionSkill::NOMBRE;
        /** @var AiSkillRegistry $registry */
        $registry = app(AiSkillRegistry::class);
        /** @var AiPolicy $policy */
        $policy = app(AiPolicy::class);

        if (! $registry->tiene($skill) || ! $policy->skillHabilitada($skill)) {
            return null;
        }
        if (auth()->check() && ! $policy->puedeEjecutar($skill)) {
            return null;
        }

        $snapshot = $this->snapshotDocumentoArbol($tipocomprobante, $documento);
        if ($snapshot === null) {
            return null;
        }

        $result = $registry->ejecutar($skill, new AiSkillContext(
            entradas: [
                'snapshot' => $snapshot,
                'movimiento' => $movimientoPendiente,
                'estado_tras_aprobar' => $estadoTrasAprobar,
            ],
            empresaId: $snapshot['empresa_id'] ?? null,
            entidadTipo: ArbolAprobacionContextoSupport::entidadTipoAi($tipocomprobante),
            entidadId: (int) ($snapshot['documento_id'] ?? 0),
        ));

        if (! $result->ok) {
            return [
                'ai_score' => null,
                'ai_decision_id' => null,
                'ai_parrafos' => [],
                'ai_advertencias' => [$result->error ?? 'No se pudo armar el contexto IA.'],
                'contexto' => null,
            ];
        }

        return [
            'ai_score' => $result->score,
            'ai_decision_id' => $result->decisionId,
            'ai_parrafos' => $result->datos['parrafos'] ?? $result->advertencias,
            'ai_advertencias' => [],
            'contexto' => $result->datos,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function snapshotDocumentoArbol(string $tipocomprobante, object $documento): ?array
    {
        $tipo = strtoupper(trim($tipocomprobante));
        $etiqueta = ArbolAprobacionContextoSupport::etiquetaTipo($tipo);

        return match ($tipo) {
            'OC' => (function () use ($documento, $etiqueta) {
                if (! $documento instanceof Ordencompra) {
                    return null;
                }
                $totales = OrdencompraTotalesCabecera::desdeModelo($documento, $this->cotizacionQuery);

                return [
                    'tipocomprobante' => 'OC',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->numeroordencompra ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => $this->centroCostoParaArbolAprobacionDesdeOrdencompra($documento),
                    'fecha' => $documento->fecha,
                    'monto' => (float) ($totales['monto'] ?? 0),
                    'moneda_id' => (int) ($totales['moneda_id'] ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            'RE' => (function () use ($documento, $etiqueta) {
                if (! $documento instanceof Requisicion) {
                    return null;
                }
                $totales = RequisicionTotalesCabecera::desdeModelo($documento, $this->cotizacionQuery);

                return [
                    'tipocomprobante' => 'RE',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->numerorequisicion ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => $this->centroCostoParaArbolAprobacionDesdeModelo($documento),
                    'fecha' => $documento->fecha,
                    'monto' => (float) ($totales['monto'] ?? 0),
                    'moneda_id' => (int) ($totales['moneda_id'] ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            'RS' => (function () use ($documento, $etiqueta) {
                if (! $documento instanceof \App\Models\Sala\RequisicionSala) {
                    return null;
                }
                $totales = \App\Support\Sala\RequisicionSalaTotalesCabecera::desdeModelo($documento);

                return [
                    'tipocomprobante' => 'RS',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->numerorequisicion ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => (int) ($documento->centrocosto_id ?? 0),
                    'fecha' => $documento->fecha,
                    'monto' => (float) ($totales['monto'] ?? 0),
                    'moneda_id' => (int) ($totales['moneda_id'] ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            'OV' => (function () use ($documento, $etiqueta) {
                return [
                    'tipocomprobante' => 'OV',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->numeroordenventa ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => (int) ($documento->centrocosto_id ?? 0),
                    'fecha' => $documento->fecha,
                    'monto' => (float) ($documento->monto ?? 0),
                    'moneda_id' => (int) ($documento->moneda_id ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            'SP' => (function () use ($documento, $etiqueta) {
                return [
                    'tipocomprobante' => 'SP',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->codigo ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => (int) ($documento->centrocosto_id ?? 0),
                    'fecha' => $documento->fecha,
                    'monto' => (float) ($documento->monto ?? 0),
                    'moneda_id' => (int) ($documento->moneda_id ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            'PE' => (function () use ($documento, $etiqueta) {
                $monto = (float) app(\App\Services\Ventas\PedidoInterformingArbolIntegracionService::class)
                    ->montoPedidoPublico($documento);

                return [
                    'tipocomprobante' => 'PE',
                    'documento_id' => (int) $documento->id,
                    'numero' => $documento->numero_comprobante ?? null,
                    'empresa_id' => isset($documento->empresa_id) ? (int) $documento->empresa_id : null,
                    'centrocosto_id' => 0,
                    'fecha' => $documento->fecha,
                    'monto' => $monto > 0 ? $monto : (float) ($documento->monto ?? 0),
                    'moneda_id' => (int) ($documento->moneda_id ?? 0),
                    'etiqueta_tipo' => $etiqueta,
                    'documento' => $documento,
                ];
            })(),
            default => null,
        };
    }

    public function movimientoPendientePorTipo(string $tipocomprobante, int $comprobanteId): ?Arbolaprobacion_Movimiento
    {
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $movimientos = $this->coleccionMovimientosPorTipo($tipocomprobante, $comprobanteId);
        foreach ($movimientos as $movimiento) {
            if ($movimiento->estado === $nombrePendiente) {
                return $movimiento;
            }
        }

        return null;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Arbolaprobacion_Movimiento>
     */
    public function coleccionMovimientosPorTipo(string $tipocomprobante, int $comprobanteId)
    {
        return match (strtoupper($tipocomprobante)) {
            'OV' => $this->arbolaprobacion_movimientoRepository->findPorOrdenVenta($comprobanteId),
            'RE' => $this->arbolaprobacion_movimientoRepository->findPorRequisicion($comprobanteId),
            'OC' => $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($comprobanteId),
            'RS' => app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)
                ->findPorRequisicionSala($comprobanteId),
            'SP' => app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                ->findPorSolicitudpago($comprobanteId),
            'PE' => app(\App\Services\Ventas\PedidoInterformingArbolIntegracionService::class)
                ->findPorPedido($comprobanteId),
            'PP' => app(\App\Services\Compras\PropuestaPagoArbolIntegracionService::class)
                ->findPorPropuestaPago($comprobanteId),
            default => collect(),
        };
    }

    public function movimientoPendientePorHashTipo(string $tipocomprobante, int $comprobanteId, string $hash, string $modo): ?Arbolaprobacion_Movimiento
    {
        $hash = ArbolAprobacionEnlaceSupport::normalizarHashRecibido($hash);
        $nombrePendiente = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        foreach ($this->coleccionMovimientosPorTipo($tipocomprobante, $comprobanteId) as $movimiento) {
            if ($movimiento->estado !== $nombrePendiente) {
                continue;
            }
            if ($modo === 'aprobacion' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashaprobacion)) {
                return $movimiento;
            }
            if ($modo === 'rechazo' && ArbolAprobacionEnlaceSupport::hashesCoinciden($hash, (string) $movimiento->hashrechazo)) {
                return $movimiento;
            }
        }

        return null;
    }

    /**
     * Portal genérico (OV / SP / PE) con resumen + ayuda IA.
     *
     * @return array<string,mixed>|null
     */
    public function portalDatosComprobantePorHash(string $tipocomprobante, int $comprobanteId, string $hash, string $modo): ?array
    {
        $tipo = strtoupper(trim($tipocomprobante));
        if (! in_array($tipo, ['OV', 'SP', 'PE'], true)) {
            return null;
        }
        $mov = $this->movimientoPendientePorHashTipo($tipo, $comprobanteId, $hash, $modo);
        if (! $mov) {
            return null;
        }
        $documento = $this->cargarDocumentoArbol($tipo, $comprobanteId);
        if (! $documento) {
            return null;
        }
        $snapshot = $this->snapshotDocumentoArbol($tipo, $documento);
        $estadoTras = null;
        if ($modo === 'aprobacion' && $snapshot) {
            if ($tipo === 'SP' && $documento) {
                $estadoCodigo = app(\App\Services\Solicitudpago\SolicitudpagoArbolIntegracionService::class)
                    ->estadoTrasAprobarNivel($documento, (int) $mov->nivel);
                $estadoTras = $estadoCodigo !== null
                    ? \App\Support\Solicitudpago\SolicitudpagoEstados::label($estadoCodigo)
                    : null;
            } else {
                $estadoTras = $this->estadoTrasAprobarDesdeSnapshot($mov, $snapshot);
            }
        }

        return [
            'tipocomprobante' => $tipo,
            'documento' => $documento,
            'movimiento' => $mov,
            'estado_tras_aprobar' => $estadoTras,
            'monto_items' => (float) ($snapshot['monto'] ?? 0),
            'moneda_abrev_items' => optional($documento->monedas ?? null)->abreviatura ?? '',
            'etiqueta_tipo' => $snapshot['etiqueta_tipo'] ?? ArbolAprobacionContextoSupport::etiquetaTipo($tipo),
            'numero_comprobante' => $snapshot['numero'] ?? null,
            'ai_contexto_arbol' => $this->panelIaContextoArbol($tipo, $documento, $mov, $estadoTras),
        ];
    }

    public function cargarDocumentoArbol(string $tipocomprobante, int $comprobanteId): ?object
    {
        return match (strtoupper($tipocomprobante)) {
            'OV' => $this->ordenventaRepository->find($comprobanteId),
            'RE' => $this->requisicionRepository->find($comprobanteId),
            'OC' => $this->ordencompraRepository->find($comprobanteId),
            'RS' => app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($comprobanteId),
            'SP' => app(\App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface::class)->find($comprobanteId),
            'PE' => \App\Models\Ventas\PedidoInterforming::query()->with(['pedido_articulos', 'clientes', 'moneda'])->find($comprobanteId),
            default => null,
        };
    }

    /**
     * @param  array<string,mixed>  $snapshot
     */
    public function estadoTrasAprobarDesdeSnapshot(Arbolaprobacion_Movimiento $mov, array $snapshot): ?string
    {
        $arbol = $this->arbolaprobacionRepository->find($mov->arbolaprobacion_id);
        if (! $arbol) {
            return null;
        }
        $nivelCfg = $this->encuentraNivelCoincidente(
            $arbol,
            (int) ($snapshot['centrocosto_id'] ?? 0),
            $mov->nivel,
            $snapshot['fecha'] ?? null,
            $snapshot['monto'] ?? 0,
            (int) ($snapshot['moneda_id'] ?? 0),
        );
        if ($nivelCfg === null) {
            return null;
        }
        $s = trim((string) ($nivelCfg->documento_estado_al_aprobar ?? ''));

        return $s !== '' ? $s : null;
    }

    public function estadoTrasAprobarSegunMovimientoOrdencompra(Ordencompra $ordencompra, Arbolaprobacion_Movimiento $mov): ?string
    {
        $arbol = $this->arbolaprobacionRepository->find($mov->arbolaprobacion_id);
        $circuitoOcMov = $mov->circuito_oc ?? null;
        $centrocostoArbol = ($circuitoOcMov === self::CIRCUITO_OC_CAMBIO_SECTOR)
            ? (int) ($arbol->oc_sector_cambio_centrocosto_id ?? 0)
            : $this->centroCostoParaArbolAprobacionDesdeOrdencompra($ordencompra);
        $totales = OrdencompraTotalesCabecera::desdeModelo($ordencompra, $this->cotizacionQuery);
        $nivelCfg = $this->encuentraNivelCoincidente(
            $arbol,
            $centrocostoArbol,
            $mov->nivel,
            $ordencompra->fecha,
            $totales['monto'],
            $totales['moneda_id']
        );
        if ($nivelCfg === null) {
            return null;
        }
        $s = trim((string) ($nivelCfg->documento_estado_al_aprobar ?? ''));
        if ($s === '' && $circuitoOcMov === self::CIRCUITO_OC_CAMBIO_SECTOR) {
            return OrdencompraEstados::APROBADA;
        }

        return $s !== '' ? $s : null;
    }

    /**
     * Igual que en requisiciones: si la orden ya no está en PENDIENTE de cabecera, no listar
     * movimientos "Sin efecto" ni pendientes de árbol sin procesar.
     *
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $movimientos
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function filtraMovimientosArbolOrdencompraParaVistaConsulta($movimientos, ?Ordencompra $oc)
    {
        if (! $oc || $oc->estadoordencompra === OrdencompraEstados::PENDIENTE) {
            return $movimientos;
        }
        $nombrePendienteMov = Arbolaprobacion_Movimiento::$enumEstado[array_search('P', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];
        $nombreSinEfecto = Arbolaprobacion_Movimiento::$enumEstado[array_search('X', array_column(Arbolaprobacion_Movimiento::$enumEstado, 'valor'))]['nombre'];

        return $movimientos->filter(function (array $row) use ($nombrePendienteMov, $nombreSinEfecto) {
            $estado = (string) ($row['estado'] ?? '');
            if ($estado === $nombreSinEfecto) {
                return false;
            }
            if ($estado === $nombrePendienteMov && empty($row['fechaproceso'])) {
                return false;
            }

            return true;
        })->values();
    }

    /**
     * @return array{movimientos: array<int, array<string, mixed>>, aviso_grabacion_pendiente: string|null}
     */
    public function movimientosOrdencompraConAvisoGrabacion(int $ordencompraId): array
    {
        $movs = $this->arbolaprobacion_movimientoRepository->findPorOrdencompra($ordencompraId);
        $oc = $this->ordencompraRepository->find($ordencompraId);
        $enriquecidos = $this->adjuntaIndicacionEstadoOrdencompraMovimientos($movs, $ordencompraId);
        $enriquecidos = $this->filtraMovimientosArbolOrdencompraParaVistaConsulta($enriquecidos, $oc);
        $aviso = null;
        if ($oc && $oc->estadoordencompra === OrdencompraEstados::PENDIENTE) {
            try {
                $this->validaOrdencompraModeloContraArbol($oc);
            } catch (\RuntimeException $e) {
                $aviso = $e->getMessage();
            }
        }

        $panelIa = null;
        if ($oc) {
            $movPendiente = $this->movimientoPendientePorTipo('OC', $ordencompraId);
            $estadoTras = $movPendiente
                ? $this->estadoTrasAprobarSegunMovimientoOrdencompra($oc, $movPendiente)
                : null;
            $panelIa = $this->panelIaContextoArbol('OC', $oc, $movPendiente, $estadoTras);
        }

        return [
            'movimientos' => $enriquecidos->values()->all(),
            'aviso_grabacion_pendiente' => $aviso,
            'ai_contexto_arbol' => $panelIa,
        ];
    }

    public function validaOrdencompraModeloContraArbol(Ordencompra $oc): void
    {
        $nombreTipo = $this->nombreTipoArbolOrdenesCompra();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $oc->empresa_id);
        if ($trees->isEmpty()) {
            throw new \RuntimeException('No hay un árbol de aprobación activo de órdenes de compra para la empresa de la orden.');
        }
        if ($trees->count() > 1) {
            throw new \RuntimeException('Hay más de un árbol de aprobación activo de órdenes de compra para esa empresa; debe quedar uno solo.');
        }
        $cc = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($oc);
        $nivelActual = $this->leeAprobacionComprobante($nombreTipo, $oc->id)['nivelactual'];
        $arbol = $trees->first();
        $totales = OrdencompraTotalesCabecera::desdeModelo($oc, $this->cotizacionQuery);
        $prox = $this->buscaProximoNivel($arbol, $cc, $nivelActual, $oc->fecha, $totales['monto'], $totales['moneda_id']);
        if ($prox['proximonivel'] === 0) {
            throw new \RuntimeException('El árbol de aprobación no tiene un nivel aplicable para el centro de costo de destino, el monto total y la moneda de la orden de compra.');
        }
    }

    /**
     * @param  \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection  $movimientos
     * @return \Illuminate\Support\Collection
     */
    public function adjuntaIndicacionEstadoOrdencompraMovimientos($movimientos, int $ordencompra_id)
    {
        $oc = $this->ordencompraRepository->find($ordencompra_id);
        $nombreTipo = $this->nombreTipoArbolOrdenesCompra();
        $trees = $this->arbolaprobacionRepository->findPorTipoArbolYEmpresa($nombreTipo, (int) $oc->empresa_id);
        $arbol = $trees->first();
        $cc = null;
        $ccIndicacionError = null;
        try {
            $cc = $this->centroCostoParaArbolAprobacionDesdeOrdencompra($oc);
        } catch (\RuntimeException $e) {
            $ccIndicacionError = $e->getMessage();
            $cc = (int) $oc->centrocosto_id;
        }

        $totalesOc = OrdencompraTotalesCabecera::desdeModelo($oc, $this->cotizacionQuery);

        return $movimientos->map(function ($m) use ($arbol, $cc, $oc, $ccIndicacionError, $totalesOc) {
            $row = $m->toArray();
            if ($ccIndicacionError !== null) {
                $row['indicacion_estado_ordencompra'] = $ccIndicacionError;

                return $row;
            }
            if ($arbol) {
                $nivelCfg = $this->encuentraNivelCoincidente(
                    $arbol,
                    $cc,
                    (int) $m->nivel,
                    $oc->fecha,
                    $totalesOc['monto'],
                    $totalesOc['moneda_id']
                );
                $est = $nivelCfg && filled($nivelCfg->documento_estado_al_aprobar)
                    ? trim((string) $nivelCfg->documento_estado_al_aprobar)
                    : null;
                $row['indicacion_estado_ordencompra'] = $est !== null && $est !== ''
                    ? 'Tras aprobar este nivel, la orden quedaría en estado: '.$est.'.'
                    : 'Sin estado configurado al aprobar este nivel (continúa el circuito del árbol).';
            } else {
                $row['indicacion_estado_ordencompra'] = 'No hay árbol de aprobación activo de órdenes de compra para la empresa de esta orden.';
            }

            return $row;
        })->values();
    }

    public function validaRequisicionSalaRequestContraArbol(array $data): void
    {
        app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)->validaRequestContraArbol($data);
    }

    public function validaRequisicionSalaModeloContraArbol(\App\Models\Sala\RequisicionSala $req): void
    {
        app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class)->validaModeloContraArbol($req);
    }

    public function portalDatosRequisicionSalaPorHash(int $id, string $hash, string $modo): ?array
    {
        $integracion = app(\App\Services\Sala\RequisicionSalaArbolIntegracionService::class);
        $mov = $integracion->movimientoPendientePorHash($id, $hash, $modo);
        if (! $mov) {
            return null;
        }
        $requisicionSala = app(\App\Repositories\Sala\RequisicionSalaRepositoryInterface::class)->find($id);
        $requisicionSala?->loadMissing(['requisicion_sala_articulos.articulos']);
        $totales = \App\Support\Sala\RequisicionSalaTotalesCabecera::desdeModelo($requisicionSala);
        $estadoTrasAprobar = null;
        if ($modo === 'aprobacion') {
            $estadoTrasAprobar = $this->estadoTrasAprobarSegunMovimientoRequisicionSala($requisicionSala, $mov);
        }
        $generaTm = \App\Support\Sala\RequisicionSalaLineasLaboratorioSupport::generaraTransferenciaLaboratorioAlAprobar(
            $requisicionSala,
            $estadoTrasAprobar
        );
        $preflightTm = \App\Support\Sala\RequisicionSalaTransferenciaLaboratorioPreflightSupport::evaluar(
            $requisicionSala,
            $estadoTrasAprobar
        );

        return [
            'requisicion_sala' => $requisicionSala,
            'movimiento' => $mov,
            'estado_tras_aprobar' => $estadoTrasAprobar,
            'monto_items' => (float) ($totales['monto'] ?? 0),
            'genera_transferencia_laboratorio' => $generaTm,
            'deposito_laboratorio' => $generaTm
                ? \App\Support\Sala\RequisicionSalaLineasLaboratorioSupport::etiquetaDepositoLaboratorio()
                : '',
            'transferencia_laboratorio_preflight' => $preflightTm,
            'ai_contexto_arbol' => $requisicionSala
                ? $this->panelIaContextoArbol('RS', $requisicionSala, $mov, $estadoTrasAprobar)
                : null,
        ];
    }

    public function estadoTrasAprobarSegunMovimientoRequisicionSala(
        \App\Models\Sala\RequisicionSala $req,
        Arbolaprobacion_Movimiento $mov
    ): ?string {
        $arbol = $this->arbolaprobacionRepository->find($mov->arbolaprobacion_id);
        $totales = \App\Support\Sala\RequisicionSalaTotalesCabecera::desdeModelo($req);
        $nivelCfg = $this->encuentraNivelCoincidente(
            $arbol,
            (int) $req->centrocosto_id,
            $mov->nivel,
            $req->fecha,
            $totales['monto'],
            $totales['moneda_id']
        );
        if ($nivelCfg === null) {
            return null;
        }
        $s = trim((string) ($nivelCfg->documento_estado_al_aprobar ?? ''));

        return $s !== '' ? $s : null;
    }
}
