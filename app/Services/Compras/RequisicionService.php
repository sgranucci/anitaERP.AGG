<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Compras\Ordencompra;
use App\Models\Stock\Articulo;
use App\Queries\Compras\ProveedorQueryInterface;
use App\Queries\Stock\ArticuloQueryInterface;
use App\Repositories\Compras\Requisicion_ArchivoRepositoryInterface;
use App\Repositories\Compras\Requisicion_ArticuloRepositoryInterface;
use App\Repositories\Compras\Requisicion_EstadoRepositoryInterface;
use App\Repositories\Compras\RequisicionRepositoryInterface;
use App\Repositories\Configuracion\MonedaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Repositories\Presupuesto\CapexRepositoryInterface;
use App\Repositories\Presupuesto\PartidagastoRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\RequisicionAnitaColisionSupport;
use App\Support\Compras\RequisicionAnitaSyncEstado;
use App\Support\Compras\RequisicionProvisorioSupport;
use App\Support\Compras\ValidacionPresupuestoPartidaCapexLineas;
use Auth;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Log;

class RequisicionService
{
    private $proveedorQuery;

    private $requisicionRepository;

    private $requisicion_estadoRepository;

    private $requisicion_articuloRepository;

    private $requisicion_archivoRepository;

    private $arbolaprobacionService;

    private $partidagastoRepository;

    private $capexRepository;

    private $centrocostoRepository;

    private $articuloQuery;

    private $monedaRepository;

    private $requisicionAnitaSyncService;

    public function __construct(
        ProveedorQueryInterface $proveedorQuery,
        RequisicionRepositoryInterface $requisicionRepository,
        Requisicion_EstadoRepositoryInterface $requisicion_estadoRepository,
        Requisicion_ArticuloRepositoryInterface $requisicion_articuloRepository,
        Requisicion_ArchivoRepositoryInterface $requisicion_archivoRepository,
        ArbolaprobacionService $arbolaprobacionService,
        PartidagastoRepositoryInterface $partidagastoRepository,
        CapexRepositoryInterface $capexRepository,
        CentrocostoRepositoryInterface $centrocostoRepository,
        ArticuloQueryInterface $articuloQuery,
        MonedaRepositoryInterface $monedaRepository,
        RequisicionAnitaSyncService $requisicionAnitaSyncService,
    ) {
        $this->proveedorQuery = $proveedorQuery;
        $this->requisicionRepository = $requisicionRepository;
        $this->requisicion_estadoRepository = $requisicion_estadoRepository;
        $this->requisicion_articuloRepository = $requisicion_articuloRepository;
        $this->requisicion_archivoRepository = $requisicion_archivoRepository;
        $this->arbolaprobacionService = $arbolaprobacionService;
        $this->partidagastoRepository = $partidagastoRepository;
        $this->capexRepository = $capexRepository;
        $this->centrocostoRepository = $centrocostoRepository;
        $this->articuloQuery = $articuloQuery;
        $this->monedaRepository = $monedaRepository;
        $this->requisicionAnitaSyncService = $requisicionAnitaSyncService;
    }

    public function guardaRequisicion($request)
    {
        $data = $request->all();
        $modoProvisorio = RequisicionProvisorioSupport::usuarioUsaModoProvisorio();
        $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $estadoAlta = $modoProvisorio
            ? RequisicionProvisorioSupport::nombreEstadoProvisorio()
            : $pendiente;

        $data['fechas'][] = Carbon::now()->toDateTimeString();
        $data['estados'][] = $estadoAlta;
        $data['usuario_ids'][] = Auth::user()->id;
        $data['observacionestados'][] = $modoProvisorio ? 'Alta en provisorio' : 'Alta de requisición';

        $data['creousuario_id'] = Auth::user()->id;
        $data['estado'] = $estadoAlta;

        try {
            $data['oficinacompra_id'] = $this->validaYCalculaOficinaCompraIdDesdeArticulos($data);
            if (! $modoProvisorio) {
                $this->arbolaprobacionService->validaRequisicionRequestContraArbol($data);
            }
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        } catch (\Exception $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        if (! $modoProvisorio) {
            try {
                ValidacionPresupuestoPartidaCapexLineas::validar($data);
            } catch (\InvalidArgumentException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        $cabecera = self::armaCabecera($data);
        $syncAnitaActivo = config('requisicion.anita.sync_activo', true) && ! $modoProvisorio;

        DB::beginTransaction();
        $anitaIntentada = false;
        $numerorequisicion = null;

        try {
            $requisicion = $this->requisicionRepository->create($cabecera);
            $numerorequisicion = (int) $requisicion->numerorequisicion;

            $this->requisicion_estadoRepository->create($data, $requisicion->id);

            $payloadArticulos = array_merge($data, ['fecha' => $cabecera['fecha']]);
            $this->requisicion_articuloRepository->syncFromRequest($payloadArticulos, $requisicion->id);

            $this->requisicion_archivoRepository->create($request, $requisicion->id);

            if (! $modoProvisorio) {
                $this->arbolaprobacionService->procesaArbolaprobacion('RE', $requisicion->id, 'insert');
            }

            if ($syncAnitaActivo) {
                $anitaIntentada = true;
                $requisicion = $this->requisicionRepository->find($requisicion->id);
                if (! $requisicion) {
                    throw new \RuntimeException('No se pudo recargar la requisición para sincronizar con Anita.');
                }
                $this->requisicionAnitaSyncService->escribirCreacion($requisicion);
                $this->requisicionAnitaSyncService->marcarSyncOk($requisicion);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($anitaIntentada && $numerorequisicion > 0) {
                $this->compensarRollbackAnitaCreacion($numerorequisicion);
            }

            return ['mensaje' => 'error', 'errores' => $this->mensajeErrorTransaccion($e, $anitaIntentada)];
        }

        return [
            'mensaje' => 'ok',
            'requisicion_id' => $requisicion->id,
            'modo_provisorio' => $modoProvisorio,
        ];
    }

    /**
     * Confirma una requisición en PROVISORIO: validaciones completas, árbol, Anita y estado PENDIENTE.
     *
     * @return array{mensaje: string, errores?: string}
     */
    public function confirmarRequisicion(int $id): array
    {
        $existente = $this->requisicionRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Requisición no encontrada.'];
        }
        if (! RequisicionProvisorioSupport::esEstadoProvisorio($existente->estado)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede confirmar una requisición en PROVISORIO.'];
        }
        if ($existente->requisicion_articulos->isEmpty()) {
            return ['mensaje' => 'error', 'errores' => 'La requisición no tiene artículos.'];
        }

        $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];

        try {
            $this->arbolaprobacionService->validaRequisicionModeloContraArbol($existente);
            $payloadValidacion = $this->payloadValidacionLineasDesdeModelo($existente);
            ValidacionPresupuestoPartidaCapexLineas::validar($payloadValidacion);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $syncAnitaActivo = config('requisicion.anita.sync_activo', true);
        $numerorequisicion = (int) $existente->numerorequisicion;

        DB::beginTransaction();
        $anitaIntentada = false;

        try {
            $this->requisicionRepository->renumerarProvisorioSiColisionaGlobal($id);
            $existente = $this->requisicionRepository->find($id);
            $numerorequisicion = (int) $existente->numerorequisicion;

            $this->requisicion_estadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $pendiente,
                Auth::user()->id,
                'Confirmación desde provisorio'
            );
            $this->requisicionRepository->update(['estado' => $pendiente], $id);

            $this->arbolaprobacionService->procesaArbolaprobacion('RE', $id, 'insert');

            if ($syncAnitaActivo) {
                $anitaIntentada = true;
                $requisicion = $this->requisicionRepository->find($id);
                if (! $requisicion) {
                    throw new \RuntimeException('No se pudo recargar la requisición para sincronizar con Anita.');
                }
                $this->requisicionAnitaSyncService->escribirCreacion($requisicion);
                $this->requisicionAnitaSyncService->marcarSyncOk($requisicion);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($anitaIntentada && $numerorequisicion > 0) {
                $this->compensarRollbackAnitaCreacion($numerorequisicion);
            }

            return ['mensaje' => 'error', 'errores' => $this->mensajeErrorTransaccion($e, $anitaIntentada)];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * Elimina solo requisiciones en PROVISORIO (sin impacto en Anita).
     *
     * @return array{mensaje: string, errores?: string}
     */
    public function eliminarProvisorio(int $id): array
    {
        $existente = $this->requisicionRepository->find($id);
        if (! RequisicionProvisorioSupport::esEstadoProvisorio($existente->estado)) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede eliminar una requisición en PROVISORIO desde esta acción.'];
        }

        if ($this->tieneOrdencompraAsociada($id)) {
            return ['mensaje' => 'error', 'errores' => 'No se puede eliminar: la requisición tiene órdenes de compra asociadas.'];
        }

        if ($this->requisicionRepository->delete($id)) {
            return ['mensaje' => 'ok'];
        }

        return ['mensaje' => 'error', 'errores' => 'No se pudo eliminar la requisición.'];
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function eliminarRequisicion(int $id): array
    {
        if ($this->tieneOrdencompraAsociada($id)) {
            return ['mensaje' => 'error', 'errores' => 'No se puede eliminar: la requisición tiene órdenes de compra asociadas.'];
        }

        if (RequisicionProvisorioSupport::esEstadoProvisorio($this->requisicionRepository->find($id)->estado ?? '')) {
            return ['mensaje' => 'error', 'errores' => 'Use eliminar provisorio para requisiciones en PROVISORIO.'];
        }

        if ($this->requisicionRepository->delete($id)) {
            return ['mensaje' => 'ok'];
        }

        return ['mensaje' => 'error', 'errores' => 'No se pudo eliminar la requisición.'];
    }

    public function tieneOrdencompraAsociada(int $requisicionId): bool
    {
        if ($requisicionId <= 0) {
            return false;
        }

        return Ordencompra::query()->where('requisicion_id', $requisicionId)->exists();
    }

    /**
     * En EN_COMPRAS solo puede intervenir un usuario cuya oficina de compra coincida con la de la requisición,
     * cuando config('requisicion.filtro_oficina_compras_activo') es true.
     * Si la requisición no tiene oficinacompra_id, se asume la oficina 1 para la comparación.
     */
    public function usuarioPuedeEditarRequisicionEnCompras($requisicion): bool
    {
        if (! $requisicion) {
            return false;
        }
        $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        if ($requisicion->estado !== $nombreEnCompras) {
            return true;
        }
        if (! config('requisicion.filtro_oficina_compras_activo', false)) {
            return true;
        }
        $oficinaRequisicion = (int) ($requisicion->oficinacompra_id ?? 1);
        $oficinaUsuario = Auth::user()->oficinacompra_id;
        if ($oficinaUsuario === null) {
            return false;
        }

        return (int) $oficinaUsuario === $oficinaRequisicion;
    }

    /**
     * Reanuda el envío al siguiente nivel del árbol cuando la requisición quedó en EN_COMPRAS
     * (el circuito se detuvo hasta esta acción explícita).
     */
    /**
     * @return array{mensaje: string, errores?: string, nivel?: int, firmantes?: list<array<string, mixed>>}
     */
    public function firmantesRetomeArbol(int $id): array
    {
        $req = $this->requisicionRepository->find($id);
        if (! $req) {
            return ['mensaje' => 'error', 'errores' => 'Requisición no encontrada.'];
        }
        if (! $this->usuarioPuedeEditarRequisicionEnCompras($req)) {
            return ['mensaje' => 'error', 'errores' => 'No puede actuar sobre esta requisición en compras: su oficina de compra no coincide con la de la requisición.'];
        }

        try {
            $preview = $this->arbolaprobacionService->firmantesRetomeArbolRequisicion($req);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return array_merge(['mensaje' => 'ok'], $preview);
    }

    public function enviarArbolAprobacionDesdeEnCompras(int $id, ?int $destinatarioUsuarioId = null): array
    {
        $req = $this->requisicionRepository->find($id);
        if (! $req) {
            return ['mensaje' => 'error', 'errores' => 'Requisición no encontrada.'];
        }
        $nombreEnCompras = Requisicion_Estado::$enumEstado[array_search('K', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        if ($req->estado !== $nombreEnCompras) {
            return ['mensaje' => 'error', 'errores' => 'Solo se puede enviar al árbol cuando la requisición está en estado EN_COMPRAS.'];
        }
        if (! $this->usuarioPuedeEditarRequisicionEnCompras($req)) {
            return ['mensaje' => 'error', 'errores' => 'No puede actuar sobre esta requisición en compras: su oficina de compra no coincide con la de la requisición.'];
        }

        try {
            $preview = $this->arbolaprobacionService->firmantesRetomeArbolRequisicion($req);
        } catch (\RuntimeException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        if ($preview['requiere_seleccion']) {
            if ($destinatarioUsuarioId === null || $destinatarioUsuarioId <= 0) {
                return [
                    'mensaje' => 'seleccionar_firmante',
                    'nivel' => $preview['nivel'],
                    'firmantes' => $preview['firmantes'],
                ];
            }
            $idsValidos = array_column($preview['firmantes'], 'id');
            if (! in_array($destinatarioUsuarioId, $idsValidos, true)) {
                return ['mensaje' => 'error', 'errores' => 'El firmante seleccionado no es válido para este nivel del árbol.'];
            }
        }

        $opcionesArbol = ['nivel_retome' => (int) $preview['nivel']];
        if ($preview['requiere_seleccion'] && $destinatarioUsuarioId > 0) {
            $opcionesArbol['destinatario_usuario_id'] = $destinatarioUsuarioId;
        }

        DB::beginTransaction();
        try {
            $nombreEnArbol = Requisicion_Estado::$enumEstado[array_search('R', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
            $this->requisicion_estadoRepository->creaEstado(
                $id,
                Carbon::now()->toDateTimeString(),
                $nombreEnArbol,
                Auth::user()->id,
                'Enviada al árbol de aprobación (desde EN_COMPRAS)'
            );
            $this->requisicionRepository->update(['estado' => $nombreEnArbol], $id);

            $this->arbolaprobacionService->procesaArbolaprobacion('RE', $id, 'resume', $opcionesArbol);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function actualizaRequisicion($request, $id)
    {
        $existente = $this->requisicionRepository->find($id);
        if (! $existente) {
            return ['mensaje' => 'error', 'errores' => 'Requisición no encontrada.'];
        }
        if (! $this->usuarioPuedeEditarRequisicionEnCompras($existente)) {
            return ['mensaje' => 'error', 'errores' => 'No puede modificar esta requisición en compras: su oficina de compra no coincide con la de la requisición.'];
        }

        if ($this->esEstadoRequisicionAprobada($existente->estado ?? '')) {
            return $this->actualizaProveedorSugeridoRequisicionAprobada($request, (int) $id, $existente);
        }

        $data = $request->all();
        $data['oficinacompra_id'] = $this->validaYCalculaOficinaCompraIdDesdeArticulos($data);

        $pendiente = Requisicion_Estado::$enumEstado[array_search('P', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
        $esProvisorio = RequisicionProvisorioSupport::esEstadoProvisorio($existente->estado);

        if ($esProvisorio) {
            $data['estado'] = RequisicionProvisorioSupport::nombreEstadoProvisorio();
        }

        if (($data['estado'] ?? '') == $pendiente && ! $esProvisorio) {
            $data['requisicion_id'] = $id;
            try {
                $this->arbolaprobacionService->validaRequisicionRequestContraArbol($data);
            } catch (\RuntimeException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        if (! $esProvisorio) {
            try {
                ValidacionPresupuestoPartidaCapexLineas::validar($data);
            } catch (\InvalidArgumentException $e) {
                return ['mensaje' => 'error', 'errores' => $e->getMessage()];
            }
        }

        $syncAnitaActivo = config('requisicion.anita.sync_activo', true) && ! $esProvisorio;
        $habiaEnAnita = $syncAnitaActivo
            && RequisicionAnitaColisionSupport::existeNroEnReqmae((int) $existente->numerorequisicion);
        $numerorequisicion = (int) $existente->numerorequisicion;

        DB::beginTransaction();
        $anitaIntentada = false;

        try {
            $cabecera = self::armaCabecera($data);
            $cabecera['estado'] = $data['estado'] ?? null;
            unset($cabecera['creousuario_id']);

            $this->requisicionRepository->update($cabecera, $id);

            $payloadArticulos = array_merge($data, ['fecha' => $cabecera['fecha']]);
            $this->requisicion_articuloRepository->syncFromRequest($payloadArticulos, $id);

            $this->requisicion_archivoRepository->update($request, $id);

            if (($data['estado'] ?? '') == $pendiente && ! $esProvisorio) {
                $this->arbolaprobacionService->procesaArbolaprobacion('RE', $id, 'insert');
            }

            if ($syncAnitaActivo) {
                $anitaIntentada = true;
                $requisicion = $this->requisicionRepository->find($id);
                if (! $requisicion) {
                    throw new \RuntimeException('No se pudo recargar la requisición para sincronizar con Anita.');
                }
                $this->requisicionAnitaSyncService->escribirActualizacion($requisicion);
                $this->requisicionAnitaSyncService->marcarSyncOk($requisicion);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($anitaIntentada) {
                $this->compensarRollbackAnitaActualizacion((int) $id, $numerorequisicion, $habiaEnAnita);
            }

            return ['mensaje' => 'error', 'errores' => $this->mensajeErrorTransaccion($e, $anitaIntentada)];
        }

        return ['mensaje' => 'ok', 'modo_provisorio' => $esProvisorio];
    }

    /**
     * Requisición APROBADA: solo actualiza proveedor sugerido (cabecera), sin tocar líneas ni estado.
     *
     * @return array{mensaje: string, errores?: string, solo_proveedor_aprobada?: bool}
     */
    private function actualizaProveedorSugeridoRequisicionAprobada($request, int $id, Requisicion $existente): array
    {
        if (! $this->esEstadoRequisicionAprobada($existente->estado ?? '')) {
            return ['mensaje' => 'error', 'errores' => 'La requisición no está en estado APROBADA.'];
        }

        $proveedorId = $request->input('proveedor_id');
        $proveedorId = ($proveedorId !== null && $proveedorId !== '') ? (int) $proveedorId : null;

        $syncAnitaActivo = config('requisicion.anita.sync_activo', true);
        $habiaEnAnita = $syncAnitaActivo
            && RequisicionAnitaColisionSupport::existeNroEnReqmae((int) $existente->numerorequisicion);
        $numerorequisicion = (int) $existente->numerorequisicion;

        DB::beginTransaction();
        $anitaIntentada = false;

        try {
            $this->requisicionRepository->update(['proveedor_id' => $proveedorId], $id);

            if ($syncAnitaActivo) {
                $anitaIntentada = true;
                $requisicion = $this->requisicionRepository->find($id);
                if (! $requisicion) {
                    throw new \RuntimeException('No se pudo recargar la requisición para sincronizar con Anita.');
                }
                $this->requisicionAnitaSyncService->escribirActualizacion($requisicion);
                $this->requisicionAnitaSyncService->marcarSyncOk($requisicion);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($anitaIntentada) {
                $this->compensarRollbackAnitaActualizacion($id, $numerorequisicion, $habiaEnAnita);
            }

            return ['mensaje' => 'error', 'errores' => $this->mensajeErrorTransaccion($e, $anitaIntentada)];
        }

        return ['mensaje' => 'ok', 'solo_proveedor_aprobada' => true];
    }

    private function esEstadoRequisicionAprobada(string $estado): bool
    {
        $nombreAprobada = Requisicion_Estado::$enumEstado[array_search('A', array_column(Requisicion_Estado::$enumEstado, 'valor'), true)]['nombre'];

        return $estado === $nombreAprobada;
    }

    public function actualizaSoloRequisicion($estado, $id)
    {
        DB::beginTransaction();
        try {
            if (isset($estado['estadorequisicion'])) {
                $nombreEstado = $estado['estadorequisicion'];
                $this->requisicionRepository->update(['estado' => $nombreEstado], $id);

                $this->requisicion_estadoRepository->creaEstado(
                    $id,
                    Carbon::now()->toDateTimeString(),
                    $nombreEstado,
                    Auth::user()->id,
                    $estado['observacion'] ?? 'Cambio de estado'
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollback();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    public function leeHistoriaRequisicion($requisicion_id)
    {
        return $this->requisicion_estadoRepository->leeHistoriaRequisicion($requisicion_id);
    }

    /** @return array<string, mixed> */
    private function payloadValidacionLineasDesdeModelo(Requisicion $requisicion): array
    {
        $payload = [
            'empresa_id' => $requisicion->empresa_id,
            'centrocosto_id' => $requisicion->centrocosto_id,
            'fecha' => $requisicion->fecha,
            'requisicion_id' => $requisicion->id,
            'articulo_ids' => [],
            'cantidades' => [],
            'centrocostodestino_ids' => [],
            'partidagasto_ids' => [],
            'capex_ids' => [],
        ];

        foreach ($requisicion->requisicion_articulos as $linea) {
            $payload['articulo_ids'][] = $linea->articulo_id;
            $payload['cantidades'][] = $linea->cantidad;
            $payload['centrocostodestino_ids'][] = $linea->centrocosto_destino_id;
            $payload['partidagasto_ids'][] = $linea->partidagasto_id;
            $payload['capex_ids'][] = $linea->capex_id;
        }

        return $payload;
    }

    private static function armaCabecera(array $data)
    {
        return [
            'fecha' => $data['fecha'] ?? null,
            'fechaentrega' => $data['fechaentrega'] ?? null,
            'empresa_id' => $data['empresa_id'] ?? null,
            'centrocosto_id' => $data['centrocosto_id'] ?? null,
            'oficinacompra_id' => $data['oficinacompra_id'] ?? null,
            'comentario' => $data['comentario'] ?? '',
            'detalle' => $data['detalle'] ?? '',
            'tratamiento' => $data['tratamiento'] ?? '',
            'motivotratamiento' => $data['motivotratamiento'] ?? '',
            'contrataciondirecta' => $data['contrataciondirecta'] ?? '',
            'proveedor_id' => ! empty($data['proveedor_id']) ? $data['proveedor_id'] : null,
            'nroinscripcion' => $data['nroinscripcion'] ?? null,
            'formapago_id' => ! empty($data['formapago_id']) ? $data['formapago_id'] : null,
            'estado' => $data['estado'] ?? null,
            'ordencompra_id' => ! empty($data['ordencompra_id']) ? $data['ordencompra_id'] : null,
            'creousuario_id' => $data['creousuario_id'] ?? Auth::user()->id,
        ];
    }

    /**
     * Setea la oficina de compra según el primer artículo y valida que todos los artículos
     * pertenezcan a la misma oficina de compra.
     */
    private function validaYCalculaOficinaCompraIdDesdeArticulos(array $data): ?int
    {
        $ids = $data['articulo_ids'] ?? null;
        if (! is_array($ids)) {
            return null;
        }

        $ids = array_values(array_filter($ids, fn ($v) => $v !== null && $v !== ''));
        if (count($ids) === 0) {
            return null;
        }

        $oficinas = Articulo::query()
            ->whereIn('id', $ids)
            ->pluck('oficinacompra_id', 'id')
            ->toArray();

        $primeroId = (int) $ids[0];
        $oficinaBase = $oficinas[$primeroId] ?? null;
        if (empty($oficinaBase)) {
            throw new \Exception('El primer artículo no tiene oficina de compra definida.');
        }

        foreach ($ids as $articuloId) {
            $articuloId = (int) $articuloId;
            $oficina = $oficinas[$articuloId] ?? null;
            if (empty($oficina)) {
                throw new \Exception('Hay artículos sin oficina de compra definida.');
            }
            if ((int) $oficina !== (int) $oficinaBase) {
                throw new \Exception('No se permiten artículos de diferentes oficinas de compra en una requisición.');
            }
        }

        return (int) $oficinaBase;
    }

    /**
     * Listado histórico desde sistema Anita (proveedor).
     */
    public function leeRequisicionPorProveedor($busqueda, $proveedor_id)
    {
        $proveedor = $this->proveedorQuery->traeProveedorporId($proveedor_id);

        if ($proveedor) {
            $apiAnita = new ApiAnita;
            $leeAnita = [
                'acc' => 'list',
                'sistema' => 'compras',
                'tabla' => 'reqmae, promae, emprmae',
                'campos' => '
					reqm_nro as id,
					reqm_fecha as fecha,
					reqm_ccosto as ccorigen,
					reqm_ccosto_dest as ccdestino,
					prom_nombre as nombreproveedor,
					prom_cuit as cuit,
					reqm_empresa as empresa_id,
					empm_nombre as nombreempresa,
					reqm_es_urgente as esurgente,
					reqm_cond_pago as condicionpago,
					reqm_cod_mon as codigomoneda,
					reqm_estado as estado
				',
                'whereArmado' => " WHERE
					reqm_proveedor='".str_pad($proveedor->codigo, 6, '0', STR_PAD_LEFT)."' and
					reqm_proveedor=prom_proveedor and
					reqm_empresa=empm_empresa",
            ];
            $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

            if ($dataAnita) {
                $requisicion = $dataAnita;

                $apiAnita = new ApiAnita;
                $leeAnita = [
                    'acc' => 'list',
                    'sistema' => 'compras',
                    'tabla' => 'reqmov,stkmae',
                    'campos' => '
						reqv_nro as id,
						reqv_articulo as sku,
						reqv_cantidad as cantidad,
						reqv_precio as precio,
						stkm_tipo_articulo as tipo_articulo,
						stkm_agrupacion as codigoagrupacion,
						stkm_desc as descarticulo
					',
                    'whereArmado' => " WHERE
						reqv_proveedor='".str_pad($proveedor->codigo, 6, '0', STR_PAD_LEFT)."' and
						reqv_articulo=stkm_articulo",
                ];
                $dataAnita = json_decode($apiAnita->apiCall($leeAnita));

                $itemRequisicion = $dataAnita;

                return ['requisicion' => $requisicion, 'item' => $itemRequisicion];
            }
        }

        return ['Error' => 'Sin informacion'];
    }

    public function sincronizarConAnita()
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $apiAnita = new ApiAnita;
        $data = ['acc' => 'list',
            'campos' => 'reqm_nro',
            'tabla' => 'reqmae',
            'sistema' => 'compras',
            'whereArmado' => ' WHERE reqm_fecha >= 20250100'];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $off = 0;
        foreach ($dataAnita as $value) {
            $off++;
            if ($off > 14700) {
                $this->traerRegistroDeAnita($value->reqm_nro);
            }
        }
    }

    public function traerRegistroDeAnita($key)
    {

        $apiAnita = new ApiAnita;
        $data = [
            'acc' => 'list',
            'tabla' => 'reqmae, outer usuario',
            'sistema' => 'compras',
            'campos' => '
                    reqm_nro,
                    reqm_fecha,
                    reqm_fecha_ent,
                    reqm_deposito,
                    reqm_emp_sueldos,
                    reqm_legajo,
                    reqm_ccosto,
                    reqm_fecha_ing,
                    reqm_hora_ing,
                    reqm_usuario,
                    reqm_estado,
                    reqm_leyenda ,
                    reqm_deposito_alfa ,
                    reqm_empresa,
                    reqm_proveedor,
                    reqm_cod_mon,
                    reqm_ccosto_dest,
                    reqm_fecha_alfa,
                    reqm_cond_pago,
                    reqm_es_urgente,
                    reqm_mot_urgencia,
                    reqm_cont_directa,
                    usu_nombre as nombreusuario',
            'whereArmado' => " WHERE reqm_nro='".$key."' AND reqm_usuario=usu_usuario",
        ];
        $dataAnita = json_decode($apiAnita->apiCall($data));

        $usuario_id = Auth::user()->id;

        if (count($dataAnita) > 0) {
            $dataRequisicion = $dataAnita[0];

            // Lee el proveedor
            $proveedor = $this->proveedorQuery->traeProveedorporCodigo(ltrim($dataRequisicion->reqm_proveedor, '0'));

            $proveedor_id = null;
            if ($proveedor) {
                $proveedor_id = $proveedor->id;
            }

            // Lee el centro de costo
            $centrocosto = $this->centrocostoRepository->findPorCodigo($dataRequisicion->reqm_ccosto);

            if ($centrocosto) {
                $centrocosto_id = $centrocosto->id;
            } else {
                $centrocosto_id = null;
            }

            $moneda = $this->monedaRepository->findPorCodigo($dataRequisicion->reqm_cod_mon);
            if ($moneda) {
                $moneda_id = $moneda->id;
            } else {
                $moneda_id = null;
            }

            // Asume forma de pago TRANSFERENCIA
            $formapago_id = 2;

            switch ($dataRequisicion->reqm_estado) {
                case '0':
                    $estado = 'PENDIENTE';
                    break;
                case '1':
                    $estado = Requisicion_Estado::$enumEstado[array_search('O', array_column(Requisicion_Estado::$enumEstado, 'valor'))]['nombre'];
                    break;
                case '2':
                    $estado = 'PARCIAL';
                    break;
                case '3':
                    $estado = 'CUMPLIDA';
                    break;
                case '4':
                    $estado = 'SUSPENDIDA';
                    break;
                case '5':
                    $estado = 'EN_COMPRAS';
                    break;
                case '6':
                    $estado = 'A_AUTORIZAR';
                    break;
                case 'T':
                    $estado = 'TRANSFERIDA';
                    break;
                case 'E':
                    $estado = 'AUT_ESPECIAL';
                    break;
                case 'A':
                    $estado = 'EN_ARBOL_APROBACION';
                    break;
                default:
                    $estado = 'PENDIENTE';
                    break;
            }
            // Suponiendo que $request->fecha_string es "20260501"
            $fechaObjeto = Carbon::createFromFormat('Ymd', $dataRequisicion->reqm_fecha);
            $fecha = $fechaObjeto->toDateString(); // "2026-05-01"
            $fechaEntrega = Carbon::createFromFormat('Ymd', $dataRequisicion->reqm_fecha_ent);
            $fechaEntrega = $fechaEntrega->toDateString(); // "2026-05-01"

            $arrayCampos = [
                'empresa_id' => $dataRequisicion->reqm_empresa,
                'centrocosto_id' => $centrocosto_id,
                'fecha' => $fecha,
                'fechaentrega' => $fechaEntrega,
                'numerorequisicion' => $dataRequisicion->reqm_nro,
                'detalle' => $dataRequisicion->reqm_leyenda,
                'comentario' => 'Creo Usuario: '.$dataRequisicion->nombreusuario,
                'tratamiento' => $dataRequisicion->reqm_es_urgente == 'S' ? 'Urgente' : 'Normal',
                'motivotratamiento' => $dataRequisicion->reqm_mot_urgencia,
                'contrataciondirecta' => $dataRequisicion->reqm_cont_directa == 'S' ? 'Si' : 'No',
                'proveedor_id' => $proveedor_id,
                'formapago_id' => $formapago_id,
                'estado' => $estado,
                'creousuario_id' => $usuario_id,
                'oficinacompra_id' => null,
            ];

            $requisicion = $this->requisicionRepository->createDesdeAnita($arrayCampos);

            // Arma movimientos
            $apiAnita = new ApiAnita;
            $data = [
                'acc' => 'list',
                'tabla' => 'reqmov',
                'sistema' => 'compras',
                'campos' => '
                        reqv_nro,             
                        reqv_nro_orden,       
                        reqv_articulo,        
                        reqv_desc,           
                        reqv_marca,           
                        reqv_linea,           
                        reqv_agrupacion,      
                        reqv_unidad_medida,   
                        reqv_cantidad,        
                        reqv_cantentr,        
                        reqv_precio,          
                        reqv_deposito,        
                        reqv_tipo_iva,        
                        reqv_fecha,          
                        reqv_fecha_ent,       
                        reqv_emp_sueldos,    
                        reqv_legajo,          
                        reqv_usuario,         
                        reqv_ccosto,          
                        reqv_cod_umd_comp,      
                        reqv_cant_unid,            
                        reqv_cod_umd_stock,       
                        reqv_unidad_xenv,     
                        reqv_genero_oc,       
                        reqv_empresa,         
                        reqv_proveedor,       
                        reqv_cantidad_oc,     
                        reqv_nro_interno,    
                        reqv_precio_ori,     
                        reqv_motivo_ahorro',
                'whereArmado' => " WHERE reqv_nro='".$key."' ",
            ];
            $dataAnita = json_decode($apiAnita->apiCall($data));

            // Lee reqmref
            $apiAnita = new ApiAnita;
            $data = [
                'acc' => 'list',
                'tabla' => 'reqmref',
                'sistema' => 'compras',
                'campos' => ' 
                    reqr_nro_requi,
                    reqr_fecha,
                    reqr_partida,
                    reqr_presupuesto,
                    reqr_escenario,
                    reqr_proyecto,
                    reqr_mes,
                    reqr_cod_proyecto,
                    reqr_empresa,
                    reqr_usuario_autor,
                    reqr_fecha_ing,
                    reqr_hora_ing,
                    reqr_usuario_carga,
                    reqr_leyenda,
                    reqr_concepto,
                    reqr_cta_contable,
                    reqr_ccosto,
                    reqr_importe',
                'whereArmado' => " WHERE reqr_nro_requi='".$key."' ",
            ];
            $dataAnitaReqmref = json_decode($apiAnita->apiCall($data));

            if (isset($dataAnitaReqmref[0])) {
                $dataReqmref = $dataAnitaReqmref[0];
            } else {
                $dataReqmref = null;
            }

            if ($dataAnita && count($dataAnita) > 0) {
                foreach ($dataAnita as $data) {
                    $fechaObjeto = Carbon::createFromFormat('Ymd', $data->reqv_fecha_ent);
                    $fecha = $fechaObjeto->toDateString(); // "2026-05-01"

                    $articulo = $this->articuloQuery->traeArticuloPorSku(ltrim($data->reqv_articulo, '0'));
                    if ($articulo) {
                        $articulo_id = $articulo->id;
                    } else {
                        $articulo_id = null;
                    }

                    $centrocostodestino = $this->centrocostoRepository->findPorCodigo($data->reqv_ccosto);
                    if ($centrocostodestino) {
                        $centrocostodestino_id = $centrocostodestino->id;
                    } else {
                        $centrocostodestino_id = null;
                    }

                    $partidagasto_id = null;
                    $capex_id = null;
                    if ($dataReqmref) {
                        $partidagasto = $this->partidagastoRepository->findPorCodigo($dataReqmref->reqr_partida);
                        if ($partidagasto) {
                            $partidagasto_id = $partidagasto->id;
                        }

                        $capex = $this->capexRepository->findPorCodigo($dataReqmref->reqr_proyecto);
                        if ($capex) {
                            $capex_id = $capex->id;
                        }
                    }

                    $arrayCampos = [
                        'requisicion_id' => $requisicion->id,
                        'fechaentrega' => $fecha,
                        'articulo_id' => $articulo_id,
                        'cantidad' => $data->reqv_cantidad,
                        'precio' => $data->reqv_precio,
                        'moneda_id' => $moneda_id,
                        'cantidadalternativa' => $data->reqv_cant_unid,
                        'detalle' => $data->reqv_desc,
                        'centrocostodestino_id' => $centrocostodestino_id,
                        'preciooriginal' => $data->reqv_precio_ori ?? 0,
                        'motivoahorro' => $data->reqv_motivo_ahorro ?? '',
                        'partidagasto_id' => $partidagasto_id,
                        'capex_id' => $capex_id,
                    ];

                    $requisicion_articulo = $this->requisicion_articuloRepository->createUnique($arrayCampos);
                }
            }
            // Lee los archivos asociados
            $apiAnita = new ApiAnita;
            $data = [
                'acc' => 'list',
                'tabla' => 'reqarch',
                'sistema' => 'compras',
                'campos' => 'reqa_nro_req, reqa_nro_linea, reqa_archivo, reqa_usuario, reqa_fecha_act, reqa_hora_act',
                'whereArmado' => " WHERE reqa_nro_req='".$key."' ",
            ];
            $dataAnita = json_decode($apiAnita->apiCall($data));

            foreach ($dataAnita as $dataArchivo) {
                $data = [
                    'requisicion_id' => $requisicion->id,
                    'nombrearchivo' => $dataArchivo->reqa_archivo,
                ];

                $requisicion_archivo = $this->requisicion_archivoRepository->createDesdeAnita($data);
            }

            // Crea estado
            $data = [];
            $data['fechas'][] = Carbon::now();
            $data['estados'][] = $estado;
            $data['usuario_ids'][] = Auth::user()->id;
            $data['observacionestados'][] = 'Alta de requisición desde Anita';

            $data['creousuario_id'] = Auth::user()->id;

            $requisicion_estado = $this->requisicion_estadoRepository->create($data, $requisicion->id);
        }
    }

    private function compensarRollbackAnitaCreacion(int $numerorequisicion): void
    {
        try {
            $this->requisicionAnitaSyncService->rollbackAnita($numerorequisicion);
        } catch (\Throwable $rollbackError) {
            Log::error('RequisicionService: rollback Anita tras fallo ERP incompleto (posible huérfano en Anita)', [
                'numerorequisicion' => $numerorequisicion,
                'error' => $rollbackError->getMessage(),
            ]);
        }
    }

    private function compensarRollbackAnitaActualizacion(int $requisicionId, int $numerorequisicion, bool $habiaEnAnita): void
    {
        try {
            $requisicion = $this->requisicionRepository->find($requisicionId);
            if ($requisicion === null) {
                $this->requisicionAnitaSyncService->rollbackAnita($numerorequisicion);

                return;
            }

            if ($habiaEnAnita) {
                $this->requisicionAnitaSyncService->restaurarDesdeErp($requisicion);
                $this->requisicionAnitaSyncService->marcarSyncOk($requisicion);
            } else {
                $this->requisicionAnitaSyncService->rollbackAnita($numerorequisicion);
                $requisicion->forceFill([
                    'anita_sync_estado' => RequisicionAnitaSyncEstado::SYNC_OK,
                    'anita_sync_error' => null,
                    'anita_sync_at' => now(),
                ])->save();
            }
        } catch (\Throwable $rollbackError) {
            Log::error('RequisicionService: compensación Anita tras fallo ERP en edición (revisar coherencia)', [
                'requisicion_id' => $requisicionId,
                'numerorequisicion' => $numerorequisicion,
                'habia_en_anita' => $habiaEnAnita,
                'error' => $rollbackError->getMessage(),
            ]);

            $requisicion = $this->requisicionRepository->find($requisicionId);
            if ($requisicion) {
                $this->requisicionAnitaSyncService->marcarSyncError($requisicion, $rollbackError);
            }
        }
    }

    private function mensajeErrorTransaccion(\Throwable $e, bool $anitaInvolucrada): string
    {
        $mensaje = $e->getMessage();
        if ($anitaInvolucrada) {
            $mensaje .= ' Se intentó compensar los datos en Anita; si el problema persiste ejecute '
                .'php artisan requisicion:reintentar-sync-anita.';
        }

        return $mensaje;
    }
}
