<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Repositories\Compras\Ordencompra_ArchivoRepositoryInterface;
use App\Repositories\Compras\Ordencompra_EstadoRepositoryInterface;
use App\Repositories\Compras\OrdencompraRepositoryInterface;
use App\Services\Configuracion\ArbolaprobacionService;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Compras\OrdencompraEstados;
use App\Support\Compras\SuscripcionListadoFiltros;
use App\Support\Compras\SuscripcionPresupuestoSupport;
use App\Support\Compras\SuscripcionSupport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Módulo Suscripciones: procesa altas sobre OC contrato (sin documento paralelo).
 * Circuito de aprobación: árbol tipo Suscripciones (SU), no el de Órdenes de compra.
 */
class SuscripcionService
{
    public function __construct(
        private OrdencompraRepositoryInterface $ordencompraRepository,
        private Ordencompra_EstadoRepositoryInterface $ordencompraEstadoRepository,
        private Ordencompra_ArchivoRepositoryInterface $ordencompraArchivoRepository,
        private OrdencompraGestionService $ordencompraGestionService,
        private ArbolaprobacionService $arbolaprobacionService,
        private SuscripcionConciliacionService $suscripcionConciliacionService,
        private SuscripcionAprobadorService $suscripcionAprobadorService,
    ) {}

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, Ordencompra>
     */
    public function listar(array $filtros = []): Collection
    {
        $q = Ordencompra::query()
            ->select('ordencompra.*')
            ->with([
                'proveedores', 'centrocostos', 'empresas', 'contrato_monedas',
                'contrato_cuentacontables', 'suscripcion_tarjetas', 'suscripcion_owners', 'usuarios',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('usuario as owner_usuario', 'owner_usuario.id', '=', 'ordencompra.suscripcion_owner_usuario_id')
            ->leftJoin('suscripcion_tarjeta', 'suscripcion_tarjeta.id', '=', 'ordencompra.suscripcion_tarjeta_id')
            ->where('ordencompra.es_suscripcion', true)
            ->orderByDesc('ordencompra.id');

        // Compat: reportes / conciliación pueden seguir mandando filtros puntuales.
        if (! empty($filtros['centrocosto_id'])) {
            $q->where('ordencompra.centrocosto_id', (int) $filtros['centrocosto_id']);
        }
        if (! empty($filtros['area'])) {
            $q->where('ordencompra.suscripcion_area', $filtros['area']);
        }
        if (! empty($filtros['suscripcion_tarjeta_id'])) {
            $q->where('ordencompra.suscripcion_tarjeta_id', (int) $filtros['suscripcion_tarjeta_id']);
        }
        if (! empty($filtros['cuentacontable_id'])) {
            $q->where('ordencompra.contrato_cuentacontable_id', (int) $filtros['cuentacontable_id']);
        }

        // Compat con pantallas que aún mandan `q` (p. ej. reportes).
        if (! empty($filtros['q']) && ! SuscripcionListadoFiltros::tieneCriteriosTexto($filtros)) {
            $filtros['valor'] = trim((string) $filtros['q']);
            $filtros['modo'] = SuscripcionListadoFiltros::MODO_TODOS;
            $filtros['operador'] = 'contiene';
        }

        SuscripcionListadoFiltros::aplicar($q, $filtros);

        $rows = $q->get();

        if (! empty($filtros['estado'])) {
            $estado = (string) $filtros['estado'];
            $rows = $rows->filter(
                fn (Ordencompra $oc) => SuscripcionSupport::estadoNegocio($oc) === $estado
            )->values();
        }

        return $rows;
    }

    /**
     * Franja de indicadores del listado. El total se mensualiza para poder sumar
     * suscripciones anuales y mensuales en un mismo número.
     *
     * @param  Collection<int, Ordencompra>  $filas
     * @return array<string, float|int>
     */
    public function kpis(Collection $filas): array
    {
        $porEstado = fn (string $estado) => $filas->filter(
            fn (Ordencompra $oc) => SuscripcionSupport::estadoNegocio($oc) === $estado
        );

        $mensualizado = $filas
            ->filter(fn (Ordencompra $oc) => in_array(
                SuscripcionSupport::estadoNegocio($oc),
                [SuscripcionSupport::ESTADO_VIGENTE, SuscripcionSupport::ESTADO_DESVIO],
                true
            ))
            ->sum(fn (Ordencompra $oc) => SuscripcionSupport::montoMensualizado(
                (float) $oc->suscripcion_monto_periodo,
                $oc->suscripcion_periodicidad
            ));

        return [
            'total' => $filas->count(),
            'vigentes' => $porEstado(SuscripcionSupport::ESTADO_VIGENTE)->count(),
            'pendientes' => $porEstado(SuscripcionSupport::ESTADO_PENDIENTE)->count(),
            'vencidas' => $porEstado(SuscripcionSupport::ESTADO_VENCIDA)->count(),
            'desvios' => $porEstado(SuscripcionSupport::ESTADO_DESVIO)->count(),
            'borradores' => $porEstado(SuscripcionSupport::ESTADO_BORRADOR)->count(),
            'sin_dueno' => $filas->filter(fn (Ordencompra $oc) => empty($oc->suscripcion_owner_usuario_id))->count(),
            'mensualizado' => round((float) $mensualizado, 2),
        ];
    }

    /**
     * Historia de aprobación de la suscripción, para la ficha.
     *
     * @return Collection<int, Arbolaprobacion_Movimiento>
     */
    public function historiaAprobacion(int $ordencompraId): Collection
    {
        return Arbolaprobacion_Movimiento::query()
            ->with(['destinatariousuarios', 'enviousuarios'])
            ->where('ordencompra_id', $ordencompraId)
            ->whereHas('arbolaprobaciones', fn ($q) => $q->where('tipoarbol', 'Suscripciones'))
            ->orderBy('nivel')
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?Ordencompra
    {
        $oc = Ordencompra::query()
            ->with([
                'proveedores',
                'centrocostos',
                'empresas',
                'contrato_monedas',
                'contrato_cuentacontables',
                'contrato_responsables',
                'ordencompra_historias',
                'ordencompra_estados',
                'ordencompra_archivos',
                'suscripcion_tarjetas',
                'suscripcion_owners',
                'suscripcion_cargos.suscripcion_conciliaciones',
            ])
            ->find($id);

        if (! $oc || ! (bool) ($oc->es_suscripcion ?? false)) {
            return null;
        }

        return $oc;
    }

    /**
     * @return array{mensaje: string, errores?: string, id?: int}
     */
    public function guardar(Request $request, bool $enviarAprobacion): array
    {
        $v = Validator::make($request->all(), $this->reglasFormulario());
        if ($v->fails()) {
            return ['mensaje' => 'error', 'errores' => $v->errors()->first()];
        }
        $data = $v->validated();

        try {
            $this->validarCuentaEmpresa(
                (int) $data['contrato_cuentacontable_id'],
                (int) $data['empresa_id']
            );
        } catch (ValidationException $e) {
            return ['mensaje' => 'error', 'errores' => $e->validator->errors()->first()];
        } catch (\InvalidArgumentException $e) {
            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        $monto = (float) $data['suscripcion_monto_periodo'];
        $tol = isset($data['suscripcion_tolerancia_pct']) && $data['suscripcion_tolerancia_pct'] !== ''
            ? (float) $data['suscripcion_tolerancia_pct']
            : SuscripcionSupport::TOLERANCIA_DEFAULT_PCT;
        $tope = SuscripcionSupport::topeAutorizado($monto, $tol);
        $periodicidad = SuscripcionSupport::normalizarPeriodicidad($data['suscripcion_periodicidad'] ?? null);
        $nombre = trim((string) $data['suscripcion_nombre']);
        $renovacion = Carbon::parse($data['proxima_renovacion'])->toDateString();
        $hoy = Carbon::today()->toDateString();
        $autoRenovable = filter_var($data['contrato_auto_renovable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $tarjeta = substr(preg_replace('/\D/', '', (string) $data['suscripcion_tarjeta_ult4']), -4);
        $uid = (int) Auth::id();
        $sectorId = $this->ordencompraGestionService->idSectorCompras();

        $comentarioParts = [
            'SUSCRIPCIÓN',
            'Tarjeta ••'.$tarjeta,
            SuscripcionSupport::etiquetaPeriodicidad($periodicidad),
            'Tol. '.$tol.'%',
        ];
        if (! empty($data['suscripcion_solicitante'])) {
            $comentarioParts[] = 'Solicita: '.$data['suscripcion_solicitante'];
        }

        $cab = [
            'fecha' => $hoy,
            'fechaentrega' => $renovacion,
            'empresa_id' => (int) $data['empresa_id'],
            'centrocosto_id' => (int) $data['centrocosto_id'],
            'proveedor_id' => (int) $data['proveedor_id'],
            'detalle' => $nombre,
            'comentario' => implode(' · ', $comentarioParts),
            'tratamiento' => 'NO ANTICIPADA',
            'estadoordencompra' => OrdencompraEstados::PENDIENTE,
            'sector_legajocompra_id' => $sectorId,
            'creousuario_id' => $uid,
            'es_contrato' => true,
            'contrato_vigencia_desde' => $hoy,
            'contrato_vigencia_hasta' => $renovacion,
            'contrato_monto_tope' => $tope,
            'contrato_moneda_id' => (int) $data['contrato_moneda_id'],
            'contrato_auto_renovable' => $autoRenovable,
            'contrato_dias_preaviso' => $autoRenovable
                ? (int) ($data['contrato_dias_preaviso'] ?? 15)
                : null,
            'contrato_dias_aviso' => SuscripcionSupport::AVISO_DIAS_DEFAULT,
            'contrato_responsable_id' => ! empty($data['contrato_responsable_id'])
                ? (int) $data['contrato_responsable_id']
                : $uid,
            'contrato_requiere_recepcion' => false,
            'contrato_imputacion_contable' => OrdencompraContratoRutaFacturaSupport::IMPUTACION_MANUAL,
            'contrato_cuentacontable_id' => (int) $data['contrato_cuentacontable_id'],
            'es_suscripcion' => true,
            'suscripcion_nombre' => $nombre,
            'suscripcion_periodicidad' => $periodicidad,
            'suscripcion_monto_periodo' => $monto,
            'suscripcion_tolerancia_pct' => $tol,
            'suscripcion_tarjeta_ult4' => $tarjeta,
            'suscripcion_tarjeta_id' => ! empty($data['suscripcion_tarjeta_id'])
                ? (int) $data['suscripcion_tarjeta_id']
                : null,
            'suscripcion_area' => trim((string) ($data['suscripcion_area'] ?? '')),
            'suscripcion_solicitante' => trim((string) ($data['suscripcion_solicitante'] ?? '')),
            'suscripcion_owner_usuario_id' => (int) $data['suscripcion_owner_usuario_id'],
            'suscripcion_borrador' => ! $enviarAprobacion,
            'suscripcion_desvio_abierto' => false,
        ];

        $oc = null;
        DB::beginTransaction();
        try {
            $oc = $this->ordencompraRepository->create($cab);

            $this->ordencompraEstadoRepository->create([
                'fechas' => [Carbon::now()->toDateTimeString()],
                'estados' => [OrdencompraEstados::PENDIENTE],
                'usuario_ids' => [$uid],
                'observacionestados' => [
                    $enviarAprobacion
                        ? 'Alta de suscripción — enviada a aprobación'
                        : 'Alta de suscripción — borrador',
                ],
            ], $oc->id);

            if ($sectorId) {
                Ordencompra_Historia::create([
                    'ordencompra_id' => $oc->id,
                    'sector_legajocompra_id' => $sectorId,
                    'fecha' => Carbon::now(),
                    'observacion' => 'Alta de OC abierta desde módulo de Suscripciones',
                    'leyenda' => $enviarAprobacion ? 'Enviada a aprobación' : 'Borrador',
                    'creousuario_id' => $uid,
                ]);
            }

            $this->ordencompraArchivoRepository->create($request, $oc->id);

            if ($enviarAprobacion) {
                $this->dispararArbolSuscripcion(
                    (int) $oc->id,
                    'Alta de suscripción enviada a aprobación (árbol Suscripciones)'
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        // La réplica en Anita va después del commit: si el bridge falla, la suscripción
        // ya quedó grabada y aprobable, y la sincronización se reintenta desde la OC.
        $fallaAnita = $this->ordencompraGestionService->sincronizarAltaEnAnita((int) $oc->id);

        $resultado = [
            'mensaje' => 'ok',
            'id' => (int) $oc->id,
            'ordencompra_id' => (int) $oc->id,
            'numeroordencompra' => $oc->fresh()->numeroordencompra,
        ];
        if ($fallaAnita !== null) {
            $resultado['advertencia'] = 'La suscripción se grabó, pero no se pudo replicar en Anita: '.$fallaAnita;
        }

        return $resultado;
    }

    /**
     * @return array{mensaje: string, errores?: string}
     */
    public function enviarBorradorAAprobacion(int $id): array
    {
        $oc = $this->find($id);
        if (! $oc) {
            return ['mensaje' => 'error', 'errores' => 'Suscripción no encontrada.'];
        }
        if (! (bool) ($oc->suscripcion_borrador ?? false)) {
            return ['mensaje' => 'error', 'errores' => 'La suscripción ya fue enviada a aprobación.'];
        }

        DB::beginTransaction();
        try {
            DB::table('ordencompra')->where('id', $id)->update([
                'suscripcion_borrador' => false,
                'updated_at' => now(),
            ]);

            $this->dispararArbolSuscripcion(
                $id,
                'Borrador de suscripción enviado a aprobación (árbol Suscripciones)'
            );

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return ['mensaje' => 'error', 'errores' => $e->getMessage()];
        }

        return ['mensaje' => 'ok'];
    }

    /**
     * @return Collection<int, Arbolaprobacion_Movimiento>
     */
    public function pendientesAprobacionParaUsuario(int $usuarioId): Collection
    {
        $nombrePendiente = $this->nombreEstadoMovimientoPendiente();

        return Arbolaprobacion_Movimiento::query()
            ->with([
                'ordencompras.proveedores',
                'ordencompras.centrocostos',
                'ordencompras.contrato_monedas',
                'ordencompras.contrato_cuentacontables',
                'ordencompras.suscripcion_owners',
            ])
            ->where('destinatariousuario_id', $usuarioId)
            ->where('estado', $nombrePendiente)
            ->whereNotNull('ordencompra_id')
            ->whereHas('arbolaprobaciones', fn ($q) => $q->where('tipoarbol', 'Suscripciones'))
            ->whereHas('ordencompras', fn ($q) => $q
                ->where('es_suscripcion', true)
                ->where('suscripcion_borrador', false))
            ->orderByDesc('fechaenvio')
            ->get();
    }

    /**
     * Qué le hace esta suscripción al presupuesto de su cuenta.
     *
     * Se informa el peso propio y el acumulado con las suscripciones ya vigentes de la misma
     * cuenta y centro de costo: autorizar una licencia de 50 dólares es distinto si el área
     * ya tiene el 90% del año comprometido.
     *
     * @return array<string, mixed>|null null si no hay partida presupuestaria para esa cuenta
     */
    public function impactoPresupuestario(?Ordencompra $oc): ?array
    {
        if (! $oc || (int) $oc->centrocosto_id <= 0 || (int) $oc->contrato_cuentacontable_id <= 0) {
            return null;
        }

        $propio = SuscripcionSupport::montoMensualizado(
            (float) $oc->suscripcion_monto_periodo,
            $oc->suscripcion_periodicidad
        );

        $vigentes = Ordencompra::query()
            ->where('es_suscripcion', true)
            ->where('suscripcion_borrador', false)
            ->where('empresa_id', $oc->empresa_id)
            ->where('centrocosto_id', $oc->centrocosto_id)
            ->where('contrato_cuentacontable_id', $oc->contrato_cuentacontable_id)
            ->where('estadoordencompra', OrdencompraEstados::APROBADA)
            ->where('id', '!=', $oc->id)
            ->get(['suscripcion_monto_periodo', 'suscripcion_periodicidad']);

        $yaComprometido = (float) $vigentes->sum(fn ($v) => SuscripcionSupport::montoMensualizado(
            (float) $v->suscripcion_monto_periodo,
            $v->suscripcion_periodicidad
        ));

        $impacto = SuscripcionPresupuestoSupport::impacto(
            (int) $oc->empresa_id,
            (int) $oc->centrocosto_id,
            (int) $oc->contrato_cuentacontable_id,
            $yaComprometido + $propio,
            (int) ($oc->contrato_moneda_id ?: 0) ?: null
        );

        if ($impacto === null) {
            return null;
        }

        return $impacto + [
            'propio_mensual' => round($propio, 2),
            'propio_pct' => $impacto['presupuesto_anual'] > 0
                ? round((($propio * 12) / $impacto['presupuesto_anual']) * 100, 1)
                : 0.0,
            'ya_comprometido_mensual' => round($yaComprometido, 2),
            'suscripciones_vigentes' => $vigentes->count(),
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function aprobar(int $movimientoId, int $usuarioId, ?string $observacion = null): array
    {
        $mov = Arbolaprobacion_Movimiento::query()->find($movimientoId);
        if (! $mov || ! $mov->ordencompra_id) {
            return ['ok' => false, 'mensaje' => 'Movimiento de aprobación no encontrado.'];
        }
        if (! $this->find((int) $mov->ordencompra_id)) {
            return ['ok' => false, 'mensaje' => 'La OC no es una suscripción.'];
        }

        try {
            $this->arbolaprobacionService->aprobar(
                'SU',
                (int) $mov->ordencompra_id,
                $movimientoId,
                $usuarioId,
                $observacion ?: 'Autorización de suscripción'
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        // La misma autorización cierra los desvíos que la mandaron de vuelta al gerente.
        $desvios = $this->suscripcionConciliacionService->resolverDesviosAprobados(
            (int) $mov->ordencompra_id,
            $usuarioId
        );

        return [
            'ok' => true,
            'mensaje' => $desvios > 0
                ? "Suscripción autorizada; se revalidaron {$desvios} cargos en desvío."
                : 'Suscripción autorizada.',
        ];
    }

    /**
     * @return array{ok: bool, mensaje: string}
     */
    public function rechazar(int $movimientoId, int $usuarioId, string $observacion): array
    {
        $obs = trim($observacion);
        if ($obs === '') {
            return ['ok' => false, 'mensaje' => 'El comentario es obligatorio para rechazar.'];
        }

        $mov = Arbolaprobacion_Movimiento::query()->find($movimientoId);
        if (! $mov || ! $mov->ordencompra_id) {
            return ['ok' => false, 'mensaje' => 'Movimiento de aprobación no encontrado.'];
        }
        if (! $this->find((int) $mov->ordencompra_id)) {
            return ['ok' => false, 'mensaje' => 'La OC no es una suscripción.'];
        }

        try {
            $this->arbolaprobacionService->rechazar(
                'SU',
                (int) $mov->ordencompra_id,
                $movimientoId,
                $usuarioId,
                $obs
            );
        } catch (\Throwable $e) {
            return ['ok' => false, 'mensaje' => $e->getMessage()];
        }

        return ['ok' => true, 'mensaje' => 'Suscripción rechazada.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function reglasFormulario(): array
    {
        return [
            'suscripcion_nombre' => 'required|string|max:180',
            'proveedor_id' => 'required|integer|exists:proveedor,id',
            'empresa_id' => 'required|integer|exists:empresa,id',
            'centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'contrato_cuentacontable_id' => 'required|integer|exists:cuentacontable,id',
            'contrato_moneda_id' => 'required|integer|exists:moneda,id',
            'suscripcion_area' => 'required|string|max:80',
            'suscripcion_solicitante' => 'nullable|string|max:120',
            'suscripcion_owner_usuario_id' => 'required|integer|exists:usuario,id',
            'suscripcion_tarjeta_id' => 'nullable|integer|exists:suscripcion_tarjeta,id',
            'suscripcion_tarjeta_ult4' => ['required', 'string', 'regex:/^\d{4}$/'],
            'suscripcion_monto_periodo' => 'required|numeric|min:0.01',
            'suscripcion_periodicidad' => 'required|string|in:mensual,anual',
            'proxima_renovacion' => 'required|date|after_or_equal:today',
            'suscripcion_tolerancia_pct' => 'nullable|numeric|min:0|max:100',
            'contrato_auto_renovable' => 'nullable|boolean',
            'contrato_dias_preaviso' => 'nullable|integer|min:0|max:365',
            'contrato_responsable_id' => 'nullable|integer|exists:usuario,id',
        ];
    }

    private function validarCuentaEmpresa(int $cuentaId, int $empresaId): void
    {
        $cuenta = DB::table('cuentacontable')->where('id', $cuentaId)->first();
        if (! $cuenta) {
            throw new \InvalidArgumentException('La cuenta contable no existe.');
        }
        if ($empresaId > 0 && (int) ($cuenta->empresa_id ?? 0) !== $empresaId) {
            throw new \InvalidArgumentException(
                'La cuenta contable debe pertenecer a la misma empresa de la suscripción.'
            );
        }
    }

    private function dispararArbolSuscripcion(int $ordencompraId, ?string $observacionEnvio = null): void
    {
        $opciones = [];
        $obs = trim((string) ($observacionEnvio ?? ''));
        if ($obs !== '') {
            $opciones['observacion_envio'] = $obs;
        }

        $resultado = $this->arbolaprobacionService->procesaArbolaprobacion('SU', $ordencompraId, 'insert', $opciones);
        if ((int) $resultado === 0) {
            throw new \RuntimeException($this->mensajeArbolSinDestino($ordencompraId));
        }
    }

    /**
     * Traduce el "no hay a quién enviarlo" a una instrucción concreta: sin árbol de la
     * empresa o sin gerente cargado para ese centro de costo.
     */
    private function mensajeArbolSinDestino(int $ordencompraId): string
    {
        $oc = $this->ordencompraRepository->find($ordencompraId);
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $centrocostoId = (int) ($oc->centrocosto_id ?? 0);

        if (! $this->suscripcionAprobadorService->arbolDeEmpresa($empresaId)) {
            return 'La empresa no tiene árbol de aprobación de Suscripciones. '
                .'Creá los aprobadores desde Compras › Suscripciones › Aprobadores.';
        }

        if (! $this->suscripcionAprobadorService->gerenteDe($empresaId, $centrocostoId)) {
            $cc = trim((string) (optional($oc->centrocostos)->codigo.' '.optional($oc->centrocostos)->nombre));

            return 'No hay gerente configurado para el centro de costo '.($cc ?: '(sin centro de costo)')
                .'. Cargalo en Compras › Suscripciones › Aprobadores y volvé a enviar.';
        }

        return 'El árbol de Suscripciones no devolvió un nivel aplicable para esta suscripción. '
            .'Revisá los aprobadores del área en Compras › Suscripciones › Aprobadores.';
    }

    private function nombreEstadoMovimientoPendiente(): string
    {
        $enum = Arbolaprobacion_Movimiento::$enumEstado ?? [];
        foreach ($enum as $item) {
            if (($item['valor'] ?? '') === 'P') {
                return (string) $item['nombre'];
            }
        }

        return 'Pendiente';
    }
}
