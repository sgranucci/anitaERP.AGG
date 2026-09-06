<?php

namespace App\Services\Configuracion;

use App\Models\Contable\Asiento;
use App\Models\Seguridad\IngresoProveedor;
use App\Models\Stock\Prestamo;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Models\Sueldos\Solicitud_Prenda_Sueldos;
use App\Services\Contable\AsientoAprobacionService;
use App\Services\Stock\PrestamoService;
use App\Services\Stock\TransferenciaMercaderiaService;
use App\Services\Sueldos\SolicitudPrendaService;
use App\Support\Seguridad\IngresoProveedorEstados;
use App\Support\Stock\TransferenciaBienUsoSupport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Agrega pendientes de varias fuentes en Mis aprobaciones.
 * No persiste user_task: solo lee y normaliza DTOs; las acciones siguen en cada dominio.
 *
 * Fuente articulo: entra vía tipocomprobante AR en movimientos de árbol
 * (MisAprobacionesArbolService), opt-in ARTICULO_APROBACION_ALTA.
 * Canales de seguridad: config('arbolaprobacion.canales').
 */
class UserTaskBandejaService
{
    public const FUENTE_ARBOL = 'arbol';

    public const FUENTE_INDUMENTARIA = 'indumentaria';

    public const FUENTE_SALIDA_BIENES = 'salida_bienes';

    public const FUENTE_ASIENTO = 'asiento';

    public const FUENTE_TRANSFERENCIA = 'transferencia';

    public const FUENTE_INGRESO_PROVEEDOR = 'ingreso_proveedor';

    private const CACHE_COUNT_TTL_SECONDS = 20;

    /** @var array<string, string> */
    public const FUENTES = [
        self::FUENTE_ARBOL => 'Árbol de aprobación',
        self::FUENTE_INDUMENTARIA => 'Indumentaria',
        self::FUENTE_SALIDA_BIENES => 'Salida de bienes',
        self::FUENTE_ASIENTO => 'Asientos contables',
        self::FUENTE_TRANSFERENCIA => 'Transferencias',
        self::FUENTE_INGRESO_PROVEEDOR => 'Ingreso proveedor',
    ];

    /** @var list<string>|null */
    private ?array $permisosOverride = null;

    public function __construct(
        private MisAprobacionesArbolService $arbolBandeja,
        private SolicitudPrendaService $solicitudPrendaService,
        private PrestamoService $prestamoService,
        private AsientoAprobacionService $asientoAprobacionService,
        private TransferenciaMercaderiaService $transferenciaService,
    ) {}

    public function puedeAcceder(): bool
    {
        return $this->fuentesDisponibles() !== [];
    }

    /**
     * @return list<string>
     */
    public function fuentesDisponibles(): array
    {
        return $this->fuentesDesdePermisos(null);
    }

    /**
     * Lista como si el usuario estuviera logueado (para digest / jobs).
     *
     * @param  array{fuente?: string|null, tipo?: string|null}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listarPendientesParaUsuario(int $usuarioId, array $filtros = []): Collection
    {
        $this->permisosOverride = $this->cargarPermisosUsuario($usuarioId);
        try {
            return $this->listarPendientes($usuarioId, $filtros);
        } finally {
            $this->permisosOverride = null;
        }
    }

    public function contarPendientesParaUsuario(int $usuarioId): int
    {
        return $this->listarPendientesParaUsuario($usuarioId)->count();
    }

    /**
     * @return list<int>
     */
    public function usuarioIdsCandidatosDigest(): array
    {
        $ids = collect();

        $nombrePendiente = \App\Models\Configuracion\Arbolaprobacion_Movimiento::$enumEstado[
            array_search('P', array_column(\App\Models\Configuracion\Arbolaprobacion_Movimiento::$enumEstado, 'valor'))
        ]['nombre'];

        $ids = $ids->merge(
            \App\Models\Configuracion\Arbolaprobacion_Movimiento::query()
                ->where('estado', $nombrePendiente)
                ->whereNotNull('destinatariousuario_id')
                ->distinct()
                ->pluck('destinatariousuario_id')
        );

        $ids = $ids->merge(
            Transferencia_Mercaderia::query()
                ->where('estado', \App\Support\Stock\TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
                ->whereNotNull('usuario_destino_id')
                ->distinct()
                ->pluck('usuario_destino_id')
        );

        $ids = $ids->merge(
            Prestamo::query()
                ->where('estado', Prestamo::ESTADO_PENDIENTE_APROBACION)
                ->whereNotNull('destinatario_usuario_id')
                ->distinct()
                ->pluck('destinatario_usuario_id')
        );

        if (Solicitud_Prenda_Sueldos::query()->where('estado', Solicitud_Prenda_Sueldos::PENDIENTE)->exists()) {
            $ids = $ids->merge($this->usuarioIdsConPermiso('aprobar-solicitud-indumentaria'));
        }
        if (Asiento::query()->where('estado_aprobacion', Asiento::ESTADO_APROBACION_PENDIENTE)->exists()) {
            $ids = $ids->merge($this->usuarioIdsConPermiso('listar-aprobacion-asiento'));
        }
        if (IngresoProveedor::query()->where('estado', IngresoProveedorEstados::PENDIENTE)->exists()) {
            $ids = $ids->merge($this->usuarioIdsConPermiso('autorizar-ingreso-proveedor'));
        }
        if (Transferencia_Mercaderia::query()
            ->where('estado', \App\Support\Stock\TransferenciaMercaderiaEstados::PENDIENTE_RECEPCION)
            ->exists()
        ) {
            $ids = $ids->merge($this->usuarioIdsConPermiso('aprobar-transferencia-mercaderia'));
            $ids = $ids->merge($this->usuarioIdsConPermiso('listar-transferencias-pendientes'));
        }
        if (Prestamo::query()->where('estado', Prestamo::ESTADO_PENDIENTE_APROBACION)->exists()) {
            $ids = $ids->merge($this->usuarioIdsConPermiso('aprobar-recepcion-salida-bienes'));
        }

        return $ids->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0)->unique()->values()->all();
    }

    /**
     * Detalle para el drawer de la bandeja.
     *
     * @return array<string, mixed>
     */
    public function detalle(string $fuente, int $id, int $usuarioId): array
    {
        $item = $this->listarPendientes($usuarioId, ['fuente' => $fuente])
            ->first(function (array $row) use ($id, $fuente) {
                if ($fuente === self::FUENTE_ARBOL) {
                    return (int) ($row['movimiento_id'] ?? 0) === $id;
                }

                return (int) ($row['comprobante_id'] ?? 0) === $id;
            });

        if (! $item) {
            throw new \RuntimeException('Pendiente no encontrado o no te corresponde.');
        }

        $campos = [
            ['label' => 'Fuente', 'valor' => (string) ($item['fuente_label'] ?? '')],
            ['label' => 'Tipo', 'valor' => trim(($item['tipo'] ?? '').' · '.($item['etiqueta_tipo'] ?? ''))],
            ['label' => 'Documento', 'valor' => (string) ($item['numero'] ?? '')],
            ['label' => 'Referencia', 'valor' => '#'.(int) ($item['comprobante_id'] ?? 0)],
        ];
        if (! empty($item['subtitulo'])) {
            $campos[] = ['label' => 'Detalle', 'valor' => (string) $item['subtitulo']];
        }
        if (($item['monto'] ?? 0) > 0) {
            $campos[] = [
                'label' => 'Monto',
                'valor' => number_format((float) $item['monto'], 2, ',', '.')
                    .' '.($item['moneda_abrev'] ?? ''),
            ];
        }
        if (($item['nivel'] ?? 0) > 0) {
            $campos[] = ['label' => 'Nivel', 'valor' => (string) $item['nivel']];
        }
        if (! empty($item['fecha_envio'])) {
            $campos[] = [
                'label' => 'Desde',
                'valor' => Carbon::parse($item['fecha_envio'])->format('d/m/Y H:i'),
            ];
        }
        if (($item['dias_pendiente'] ?? 0) > 0) {
            $campos[] = ['label' => 'Antigüedad', 'valor' => (int) $item['dias_pendiente'].' día(s)'];
        }
        if (! empty($item['sla_label'])) {
            $campos[] = ['label' => 'SLA', 'valor' => (string) $item['sla_label']];
        }
        if (! empty($item['reemplazo_de'])) {
            $campos[] = ['label' => 'Delegación', 'valor' => 'Actuás en reemplazo de '.$item['reemplazo_de']];
        }

        $historial = $this->historialFuente($fuente, (int) ($item['comprobante_id'] ?? $id), $item);

        return [
            'item' => $item,
            'campos' => $campos,
            'historial' => $historial,
            'banner_reemplazo' => ! empty($item['reemplazo_de'])
                ? 'Estás actuando por reemplazo de '.$item['reemplazo_de']
                : null,
            'sla_label' => $item['sla_label'] ?? null,
            'sla_estado' => $item['sla_estado'] ?? null,
            'url_ver' => $item['url_ver'] ?? null,
            'url_aprobar' => $item['url_aprobar'] ?? null,
            'url_rechazar' => $item['url_rechazar'] ?? null,
            'puede_aprobar' => ! empty($item['puede_aprobar']),
        ];
    }

    /**
     * @param  list<string>|null  $slugs
     * @return list<string>
     */
    private function fuentesDesdePermisos(?array $slugs): array
    {
        $out = [];
        if ($this->puedeSlug('aprobar-mis-aprobaciones-arbol', $slugs)) {
            $out[] = self::FUENTE_ARBOL;
        }
        if ($this->puedeSlug('aprobar-solicitud-indumentaria', $slugs)) {
            $out[] = self::FUENTE_INDUMENTARIA;
        }
        if ($this->puedeSlug('aprobar-recepcion-salida-bienes', $slugs)) {
            $out[] = self::FUENTE_SALIDA_BIENES;
        }
        if ($this->puedeSlug('listar-aprobacion-asiento', $slugs)) {
            $out[] = self::FUENTE_ASIENTO;
        }
        if ($this->puedeSlug('aprobar-transferencia-mercaderia', $slugs)
            || $this->puedeSlug('listar-transferencias-pendientes', $slugs)
        ) {
            $out[] = self::FUENTE_TRANSFERENCIA;
        }
        if ($this->puedeSlug('autorizar-ingreso-proveedor', $slugs)) {
            $out[] = self::FUENTE_INGRESO_PROVEEDOR;
        }

        return $out;
    }

    /**
     * @param  list<string>|null  $slugs
     */
    private function puedeSlug(string $slug, ?array $slugs = null): bool
    {
        $pool = $slugs ?? $this->permisosOverride;
        if ($pool !== null) {
            return in_array($slug, $pool, true);
        }

        return can($slug, false);
    }

    /**
     * @return list<string>
     */
    private function cargarPermisosUsuario(int $usuarioId): array
    {
        $usuario = \App\Models\Seguridad\Usuario::query()->with('roles:id,nombre')->find($usuarioId);
        if (! $usuario) {
            return [];
        }
        foreach ($usuario->roles as $rol) {
            if (strtolower((string) $rol->nombre) === 'administrador') {
                return array_values(array_unique(array_merge(
                    [
                        'aprobar-mis-aprobaciones-arbol',
                        'aprobar-solicitud-indumentaria',
                        'aprobar-recepcion-salida-bienes',
                        'rechazar-recepcion-salida-bienes',
                        'listar-aprobacion-asiento',
                        'aprobar-asiento-pendiente',
                        'rechazar-asiento-pendiente',
                        'aprobar-transferencia-mercaderia',
                        'listar-transferencias-pendientes',
                        'autorizar-ingreso-proveedor',
                    ],
                    []
                )));
            }
        }

        $slugs = [];
        foreach ($usuario->roles as $rol) {
            $rolId = (int) $rol->id;
            $slugs = array_merge($slugs, \App\Support\Cache\PermisoCacheSupport::rememberSlugsPorRol(
                $rolId,
                function () use ($rolId) {
                    return \App\Models\Admin\Permiso::whereHas('roles', function ($query) use ($rolId) {
                        $query->where('rol.id', $rolId);
                    })->get()->pluck('slug')->toArray();
                }
            ));
        }

        return array_values(array_unique($slugs));
    }

    /**
     * @return list<int>
     */
    private function usuarioIdsConPermiso(string $slug): array
    {
        $permisoId = (int) (\Illuminate\Support\Facades\DB::table('permiso')->where('slug', $slug)->value('id') ?? 0);
        if ($permisoId <= 0) {
            return [];
        }
        $rolIds = \Illuminate\Support\Facades\DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id');
        if ($rolIds->isEmpty()) {
            return [];
        }

        return \Illuminate\Support\Facades\DB::table('usuario_rol')
            ->whereIn('rol_id', $rolIds)
            ->distinct()
            ->pluck('usuario_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<array{fecha: string, texto: string, canal?: string|null, usuario?: string|null, estado?: string|null}>
     */
    private function historialFuente(string $fuente, int $comprobanteId, array $item): array
    {
        if ($fuente === self::FUENTE_INDUMENTARIA && $comprobanteId > 0) {
            return \App\Models\Sueldos\Solicitud_Prenda_Aprobacion_Sueldos::query()
                ->with('usuario:id,nombre')
                ->where('solicitud_id', $comprobanteId)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(function ($m) {
                    $quien = optional($m->usuario)->nombre ?? 'Usuario';
                    $accion = (string) ($m->accion ?? '');
                    $fecha = $m->fecha ? Carbon::parse($m->fecha)->format('d/m/Y H:i') : '';
                    $canal = $this->detectarCanalObservacion((string) ($m->observacion ?? ''));

                    return [
                        'fecha' => $fecha,
                        'usuario' => $quien,
                        'estado' => $accion,
                        'canal' => $canal,
                        'texto' => trim($accion.' · '.$quien
                            .($canal ? ' · '.$canal : '')
                            .($m->observacion ? ' — '.$this->limpiarTagCanal((string) $m->observacion) : '')),
                    ];
                })
                ->all();
        }

        if ($fuente === self::FUENTE_ARBOL && ! empty($item['movimiento_id'])) {
            $mov = \App\Models\Configuracion\Arbolaprobacion_Movimiento::query()->find((int) $item['movimiento_id']);
            if ($mov) {
                $ref = $this->arbolBandeja->referenciaMovimiento($mov);
                $q = \App\Models\Configuracion\Arbolaprobacion_Movimiento::query()
                    ->with('destinatariousuarios:id,nombre')
                    ->orderByDesc('id')
                    ->limit(12);
                if (($ref['tipo'] ?? '') === 'RE') {
                    $q->where('requisicion_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'OC') {
                    $q->where('ordencompra_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'SP') {
                    $q->where('solicitudpago_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'OV') {
                    $q->where('ordenventa_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'PE') {
                    $q->where('pedido_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'RS') {
                    $q->where('requisicion_sala_id', $ref['comprobante_id']);
                } elseif (($ref['tipo'] ?? '') === 'PP') {
                    $q->where('propuesta_pago_id', $ref['comprobante_id']);
                } else {
                    return [];
                }

                return $q->get()->map(function ($m) {
                    $cuando = $m->fechaproceso ?: $m->fechaenvio;
                    $fecha = $cuando ? Carbon::parse($cuando)->format('d/m/Y H:i') : '';
                    $quien = optional($m->destinatariousuarios)->nombre
                        ?? ('Usuario #'.(int) ($m->destinatariousuario_id ?? 0));
                    $estado = (string) ($m->estado ?? '');
                    $obs = (string) ($m->observacion ?? '');
                    $canal = $this->detectarCanalObservacion($obs);
                    $obsLimpia = $this->limpiarTagCanal($obs);
                    $partes = [
                        $estado,
                        'nivel '.(int) ($m->nro_nivel ?? $m->nivel ?? 0),
                        $quien,
                    ];
                    if ($canal) {
                        $partes[] = $canal;
                    }
                    if ($obsLimpia !== '' && ! str_starts_with($obsLimpia, 'Reasignado por reemplazo')) {
                        $partes[] = $obsLimpia;
                    }

                    return [
                        'fecha' => $fecha,
                        'usuario' => $quien,
                        'estado' => $estado,
                        'canal' => $canal,
                        'texto' => implode(' · ', array_filter($partes)),
                    ];
                })->all();
            }
        }

        return [];
    }

    private function detectarCanalObservacion(string $obs): ?string
    {
        if (str_contains($obs, '[vía bandeja]')) {
            return 'Bandeja';
        }
        if (str_contains($obs, '[vía enlace]') || str_contains($obs, '[vía mail]')) {
            return 'Mail / enlace';
        }
        if (str_contains($obs, '[vía sesión]')) {
            return 'Sesión';
        }

        return null;
    }

    private function limpiarTagCanal(string $obs): string
    {
        return trim(preg_replace('/\s*·?\s*\[vía [^\]]+\]/u', '', $obs) ?? $obs);
    }

    public function contarPendientes(int $usuarioId): int
    {
        if ($usuarioId <= 0) {
            return 0;
        }

        return (int) Cache::remember(
            $this->cacheKeyContador($usuarioId),
            self::CACHE_COUNT_TTL_SECONDS,
            fn () => $this->listarPendientes($usuarioId)->count()
        );
    }

    public function invalidarContador(?int $usuarioId = null): void
    {
        $usuarioId = $usuarioId ?? (int) (auth()->id() ?? 0);
        if ($usuarioId > 0) {
            Cache::forget($this->cacheKeyContador($usuarioId));
        }
    }

    /**
     * @param  array{fuente?: string|null, tipo?: string|null, q?: string|null, urgencia?: string|null, reemplazo?: bool|null, dias_min?: int|null, monto_min?: float|null}  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listarPendientes(int $usuarioId, array $filtros = []): Collection
    {
        if ($usuarioId <= 0) {
            return collect();
        }

        $fuenteFiltro = strtolower(trim((string) ($filtros['fuente'] ?? '')));
        $tipoFiltro = strtoupper(trim((string) ($filtros['tipo'] ?? '')));
        $q = mb_strtolower(trim((string) ($filtros['q'] ?? '')));
        $urgenciaFiltro = strtolower(trim((string) ($filtros['urgencia'] ?? '')));
        $soloReemplazo = ! empty($filtros['reemplazo']);
        $diasMin = max(0, (int) ($filtros['dias_min'] ?? 0));
        $montoMin = max(0.0, (float) ($filtros['monto_min'] ?? 0));
        $fuentes = $this->fuentesDesdePermisos($this->permisosOverride);

        $items = collect();

        if (in_array(self::FUENTE_ARBOL, $fuentes, true)
            && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_ARBOL)
        ) {
            $items = $items->merge(
                $this->arbolBandeja->listarPendientes($usuarioId, ['tipo' => $tipoFiltro !== '' ? $tipoFiltro : null])
                    ->map(fn (array $row) => $this->normalizarArbol($row))
            );
        }

        // tipo (RE/OC/…) solo aplica al árbol
        if ($tipoFiltro === '') {
            if (in_array(self::FUENTE_INDUMENTARIA, $fuentes, true)
                && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_INDUMENTARIA)
            ) {
                $items = $items->merge($this->listarIndumentaria($usuarioId));
            }

            if (in_array(self::FUENTE_SALIDA_BIENES, $fuentes, true)
                && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_SALIDA_BIENES)
            ) {
                $items = $items->merge($this->listarSalidaBienes($usuarioId));
            }

            if (in_array(self::FUENTE_ASIENTO, $fuentes, true)
                && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_ASIENTO)
            ) {
                $items = $items->merge($this->listarAsientos());
            }

            if (in_array(self::FUENTE_TRANSFERENCIA, $fuentes, true)
                && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_TRANSFERENCIA)
            ) {
                $items = $items->merge($this->listarTransferencias($usuarioId));
            }

            if (in_array(self::FUENTE_INGRESO_PROVEEDOR, $fuentes, true)
                && ($fuenteFiltro === '' || $fuenteFiltro === self::FUENTE_INGRESO_PROVEEDOR)
            ) {
                $items = $items->merge($this->listarIngresoProveedor());
            }
        }

        if ($q !== '') {
            $items = $items->filter(function (array $row) use ($q) {
                $haystack = mb_strtolower(trim(implode(' ', [
                    $row['numero'] ?? '',
                    $row['tipo'] ?? '',
                    $row['etiqueta_tipo'] ?? '',
                    $row['subtitulo'] ?? '',
                    $row['fuente_label'] ?? '',
                    $row['reemplazo_de'] ?? '',
                    (string) ($row['comprobante_id'] ?? ''),
                    (string) ($row['monto'] ?? ''),
                ])));

                return $haystack !== '' && str_contains($haystack, $q);
            })->values();
        }

        if ($urgenciaFiltro !== '') {
            $items = $items->filter(function (array $row) use ($urgenciaFiltro) {
                if ($urgenciaFiltro === 'vencido') {
                    return ($row['sla_estado'] ?? '') === 'vencido'
                        || ($row['urgencia'] ?? '') === 'urgente';
                }

                return ($row['urgencia'] ?? 'normal') === $urgenciaFiltro
                    || ($row['sla_estado'] ?? '') === $urgenciaFiltro;
            })->values();
        }

        if ($soloReemplazo) {
            $items = $items->filter(fn (array $row) => ! empty($row['es_reemplazo']))->values();
        }

        if ($diasMin > 0) {
            $items = $items->filter(fn (array $row) => (int) ($row['dias_pendiente'] ?? 0) >= $diasMin)->values();
        }

        if ($montoMin > 0) {
            $items = $items->filter(fn (array $row) => (float) ($row['monto'] ?? 0) >= $montoMin)->values();
        }

        return $items
            ->sortByDesc(fn (array $row) => (int) ($row['sort_ts'] ?? 0))
            ->values();
    }

    /**
     * Resumen liviano de la cola (analytics personal).
     *
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array{
     *   total: int,
     *   monto_total: float,
     *   reemplazos: int,
     *   aging: array{fresco: int, medio: int, viejo: int},
     *   por_fuente: list<array{codigo: string, nombre: string, total: int}>
     * }
     */
    public function resumirCola(Collection $items): array
    {
        $porFuente = [];
        foreach ($items as $row) {
            $codigo = (string) ($row['fuente'] ?? 'otro');
            if (! isset($porFuente[$codigo])) {
                $porFuente[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => (string) ($row['fuente_label'] ?? self::FUENTES[$codigo] ?? $codigo),
                    'total' => 0,
                ];
            }
            $porFuente[$codigo]['total']++;
        }

        usort($porFuente, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'total' => $items->count(),
            'monto_total' => round((float) $items->sum(fn (array $r) => (float) ($r['monto'] ?? 0)), 2),
            'reemplazos' => $items->where('es_reemplazo', true)->count(),
            'aging' => [
                'fresco' => $items->filter(fn (array $r) => (int) ($r['dias_pendiente'] ?? 0) <= 1)->count(),
                'medio' => $items->filter(function (array $r) {
                    $d = (int) ($r['dias_pendiente'] ?? 0);

                    return $d >= 2 && $d <= 4;
                })->count(),
                'viejo' => $items->filter(fn (array $r) => (int) ($r['dias_pendiente'] ?? 0) >= 5)->count(),
            ],
            'por_fuente' => array_values($porFuente),
        ];
    }

    private function cacheKeyContador(int $usuarioId): string
    {
        return 'mis_aprobaciones_count_'.$usuarioId;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizarArbol(array $row): array
    {
        $fecha = $row['fecha_envio'] ?? null;
        $sortTs = $fecha ? Carbon::parse($fecha)->getTimestamp() : (int) ($row['movimiento_id'] ?? 0);

        return array_merge($row, [
            'fuente' => self::FUENTE_ARBOL,
            'fuente_label' => self::FUENTES[self::FUENTE_ARBOL],
            'task_key' => 'arbol:'.(int) ($row['movimiento_id'] ?? 0),
            'sort_ts' => $sortTs,
            'acciones_inline' => true,
            'muestra_reenviar' => ! empty($row['puede_aprobar']),
            'muestra_descartar' => empty($row['documento_existe']),
            'url_detalle' => url('mis-aprobaciones/detalle/arbol/'.(int) ($row['movimiento_id'] ?? 0)),
            'es_reemplazo' => ! empty($row['reemplazo_de']),
            'sla_label' => $row['sla_label'] ?? null,
            'sla_estado' => $row['sla_estado'] ?? ($row['urgencia'] ?? 'normal'),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listarIndumentaria(int $usuarioId): Collection
    {
        return $this->solicitudPrendaService->bandejaPendientesDe($usuarioId)->map(function (Solicitud_Prenda_Sueldos $s) {
            $fecha = $s->fecha ? Carbon::parse($s->fecha)->startOfDay() : ($s->created_at ? Carbon::parse($s->created_at) : Carbon::now());
            $dias = max(0, $fecha->diffInDays(Carbon::now()->startOfDay()));
            $empleado = trim((string) (optional($s->empleado)->nombre ?? ''));
            $legajo = optional($s->empleado)->legajo;
            $sla = $this->slaGenerico($dias, $fecha);

            return [
                'fuente' => self::FUENTE_INDUMENTARIA,
                'fuente_label' => self::FUENTES[self::FUENTE_INDUMENTARIA],
                'task_key' => 'indumentaria:'.$s->id,
                'tipo' => 'IN',
                'etiqueta_tipo' => 'Indumentaria',
                'numero' => 'Solicitud #'.$s->id,
                'comprobante_id' => (int) $s->id,
                'nivel' => (int) $s->nivel_actual,
                'monto' => 0,
                'moneda_abrev' => '',
                'fecha_envio' => $fecha->toDateTimeString(),
                'dias_pendiente' => $dias,
                'urgencia' => $this->urgenciaPorDias($dias),
                'documento_existe' => true,
                'puede_aprobar' => true,
                'es_aviso_pago' => false,
                'reemplazo_de' => null,
                'es_reemplazo' => false,
                'sla_label' => $sla['sla_label'],
                'sla_estado' => $sla['sla_estado'],
                'sla_fecha_limite' => $sla['sla_fecha_limite'],
                'dias_para_vencer' => $sla['dias_para_vencer'],
                'url_ver' => route('reporte_solicitud_indumentaria'),
                'url_aprobar' => url('mis-aprobaciones/indumentaria/'.$s->id.'/aprobar'),
                'url_rechazar' => url('mis-aprobaciones/indumentaria/'.$s->id.'/rechazar'),
                'url_reenviar' => null,
                'url_descartar' => null,
                'url_detalle' => url('mis-aprobaciones/detalle/indumentaria/'.$s->id),
                'subtitulo' => trim(($legajo ? 'Legajo '.$legajo.' · ' : '').$empleado),
                'acciones_inline' => true,
                'muestra_reenviar' => false,
                'muestra_descartar' => false,
                'sort_ts' => $fecha->getTimestamp(),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listarSalidaBienes(int $usuarioId): Collection
    {
        $puedeAprobar = $this->puedeSlug('aprobar-recepcion-salida-bienes');
        $puedeRechazar = $this->puedeSlug('rechazar-recepcion-salida-bienes');

        return $this->prestamoService->listarPendientesAprobacionParaUsuario($usuarioId)->map(function (Prestamo $p) use ($puedeAprobar, $puedeRechazar) {
            $fecha = $p->updated_at ? Carbon::parse($p->updated_at) : ($p->created_at ? Carbon::parse($p->created_at) : Carbon::now());
            $dias = max(0, $fecha->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay()));
            $codigo = (string) ($p->codigo ?: ('SB-'.$p->id));
            $sla = $this->slaGenerico($dias, $fecha);

            return [
                'fuente' => self::FUENTE_SALIDA_BIENES,
                'fuente_label' => self::FUENTES[self::FUENTE_SALIDA_BIENES],
                'task_key' => 'salida:'.$p->id,
                'tipo' => 'SB',
                'etiqueta_tipo' => 'Salida de bienes',
                'numero' => $codigo,
                'comprobante_id' => (int) $p->id,
                'nivel' => 1,
                'monto' => 0,
                'moneda_abrev' => '',
                'fecha_envio' => $fecha->toDateTimeString(),
                'dias_pendiente' => $dias,
                'urgencia' => $this->urgenciaPorDias($dias),
                'documento_existe' => true,
                'puede_aprobar' => $puedeAprobar,
                'es_aviso_pago' => false,
                'reemplazo_de' => null,
                'es_reemplazo' => false,
                'sla_label' => $sla['sla_label'],
                'sla_estado' => $sla['sla_estado'],
                'sla_fecha_limite' => $sla['sla_fecha_limite'],
                'dias_para_vencer' => $sla['dias_para_vencer'],
                'url_ver' => route('ver_salida_bienes', ['id' => $p->id]),
                'url_aprobar' => $puedeAprobar ? url('mis-aprobaciones/salida-bienes/'.$p->id.'/aprobar') : null,
                'url_rechazar' => $puedeRechazar ? url('mis-aprobaciones/salida-bienes/'.$p->id.'/rechazar') : null,
                'url_reenviar' => null,
                'url_descartar' => null,
                'url_detalle' => url('mis-aprobaciones/detalle/salida_bienes/'.$p->id),
                'subtitulo' => $p->etiquetaTipo().' · '.$p->etiquetaDestinatario(),
                'acciones_inline' => true,
                'muestra_reenviar' => false,
                'muestra_descartar' => false,
                'sort_ts' => $fecha->getTimestamp(),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listarAsientos(): Collection
    {
        $puedeAprobar = $this->puedeSlug('aprobar-asiento-pendiente');
        $puedeRechazar = $this->puedeSlug('rechazar-asiento-pendiente');

        return $this->asientoAprobacionService->listarPendientes()->map(function (Asiento $a) use ($puedeAprobar, $puedeRechazar) {
            $fecha = $a->created_at ? Carbon::parse($a->created_at) : Carbon::now();
            $dias = max(0, $fecha->copy()->startOfDay()->diffInDays(Carbon::now()->startOfDay()));
            $empresa = optional($a->empresas)->nombre ?? '';
            $tipo = optional($a->tipoasientos)->nombre ?? 'Asiento';
            $sla = $this->slaGenerico($dias, $fecha);

            return [
                'fuente' => self::FUENTE_ASIENTO,
                'fuente_label' => self::FUENTES[self::FUENTE_ASIENTO],
                'task_key' => 'asiento:'.$a->id,
                'tipo' => 'AS',
                'etiqueta_tipo' => 'Asiento',
                'numero' => (string) ($a->numeroasiento ?: '#'.$a->id),
                'comprobante_id' => (int) $a->id,
                'nivel' => 1,
                'monto' => 0,
                'moneda_abrev' => '',
                'fecha_envio' => $fecha->toDateTimeString(),
                'dias_pendiente' => $dias,
                'urgencia' => $this->urgenciaPorDias($dias),
                'documento_existe' => true,
                'puede_aprobar' => $puedeAprobar,
                'es_aviso_pago' => false,
                'reemplazo_de' => null,
                'es_reemplazo' => false,
                'sla_label' => $sla['sla_label'],
                'sla_estado' => $sla['sla_estado'],
                'sla_fecha_limite' => $sla['sla_fecha_limite'],
                'dias_para_vencer' => $sla['dias_para_vencer'],
                'url_ver' => route('ver_aprobacion_asiento', ['id' => $a->id]),
                'url_aprobar' => $puedeAprobar ? url('mis-aprobaciones/asiento/'.$a->id.'/aprobar') : null,
                'url_rechazar' => $puedeRechazar ? url('mis-aprobaciones/asiento/'.$a->id.'/rechazar') : null,
                'url_reenviar' => null,
                'url_descartar' => null,
                'url_detalle' => url('mis-aprobaciones/detalle/asiento/'.$a->id),
                'subtitulo' => trim($tipo.($empresa !== '' ? ' · '.$empresa : '')),
                'acciones_inline' => $puedeAprobar || $puedeRechazar,
                'muestra_reenviar' => false,
                'muestra_descartar' => false,
                'sort_ts' => $fecha->getTimestamp(),
            ];
        });
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listarTransferencias(int $usuarioId): Collection
    {
        $puedeAprobar = $this->puedeSlug('aprobar-transferencia-mercaderia');

        return $this->transferenciaService->listarPendientesAprobacionParaUsuario($usuarioId)->map(function (Transferencia_Mercaderia $t) use ($puedeAprobar) {
            $fecha = $t->fecha
                ? Carbon::parse($t->fecha)->startOfDay()
                : ($t->created_at ? Carbon::parse($t->created_at) : Carbon::now());
            $dias = max(0, $fecha->diffInDays(Carbon::now()->startOfDay()));
            $codigo = (string) ($t->codigo ?: ('TM-'.$t->id));
            $origen = TransferenciaBienUsoSupport::etiquetaOrigenTransferencia($t);
            $destino = TransferenciaBienUsoSupport::etiquetaDestinoTransferencia($t);
            $sla = $this->slaGenerico($dias, $fecha);

            return [
                'fuente' => self::FUENTE_TRANSFERENCIA,
                'fuente_label' => self::FUENTES[self::FUENTE_TRANSFERENCIA],
                'task_key' => 'transferencia:'.$t->id,
                'tipo' => 'TM',
                'etiqueta_tipo' => 'Transferencia',
                'numero' => $codigo,
                'comprobante_id' => (int) $t->id,
                'nivel' => 1,
                'monto' => 0,
                'moneda_abrev' => '',
                'fecha_envio' => $fecha->toDateTimeString(),
                'dias_pendiente' => $dias,
                'urgencia' => $this->urgenciaPorDias($dias),
                'documento_existe' => true,
                'puede_aprobar' => $puedeAprobar,
                'es_aviso_pago' => false,
                'reemplazo_de' => null,
                'es_reemplazo' => false,
                'sla_label' => $sla['sla_label'],
                'sla_estado' => $sla['sla_estado'],
                'sla_fecha_limite' => $sla['sla_fecha_limite'],
                'dias_para_vencer' => $sla['dias_para_vencer'],
                'url_ver' => route('consultar_transferencia_movimientostock', ['id' => $t->id]),
                'url_aprobar' => $puedeAprobar ? url('mis-aprobaciones/transferencia/'.$t->id.'/aprobar') : null,
                'url_rechazar' => $puedeAprobar ? url('mis-aprobaciones/transferencia/'.$t->id.'/rechazar') : null,
                'url_reenviar' => null,
                'url_descartar' => null,
                'url_detalle' => url('mis-aprobaciones/detalle/transferencia/'.$t->id),
                'subtitulo' => $origen.' → '.$destino,
                'acciones_inline' => $puedeAprobar,
                'muestra_reenviar' => false,
                'muestra_descartar' => false,
                'sort_ts' => $fecha->getTimestamp(),
            ];
        });
    }

    /**
     * Cola Seguridad: todos los tickets PENDIENTE si el usuario puede autorizar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function listarIngresoProveedor(): Collection
    {
        $puede = $this->puedeSlug('autorizar-ingreso-proveedor');

        return IngresoProveedor::query()
            ->with(['proveedores:id,nombre', 'empresas:id,nombre'])
            ->where('estado', IngresoProveedorEstados::PENDIENTE)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(function (IngresoProveedor $t) use ($puede) {
                $fecha = $t->fecha
                    ? Carbon::parse($t->fecha)->startOfDay()
                    : ($t->created_at ? Carbon::parse($t->created_at) : Carbon::now());
                $dias = max(0, $fecha->diffInDays(Carbon::now()->startOfDay()));
                $proveedor = optional($t->proveedores)->nombre ?? '';
                $titulo = trim((string) ($t->titulo ?: $t->visitante_nombre ?: ('Ticket #'.$t->id)));
                $sla = $this->slaGenerico($dias, $fecha);

                return [
                    'fuente' => self::FUENTE_INGRESO_PROVEEDOR,
                    'fuente_label' => self::FUENTES[self::FUENTE_INGRESO_PROVEEDOR],
                    'task_key' => 'ingreso:'.$t->id,
                    'tipo' => 'IP',
                    'etiqueta_tipo' => 'Ingreso proveedor',
                    'numero' => $titulo,
                    'comprobante_id' => (int) $t->id,
                    'nivel' => 1,
                    'monto' => 0,
                    'moneda_abrev' => '',
                    'fecha_envio' => $fecha->toDateTimeString(),
                    'dias_pendiente' => $dias,
                    'urgencia' => $this->urgenciaPorDias($dias),
                    'documento_existe' => true,
                    'puede_aprobar' => $puede,
                    'es_aviso_pago' => false,
                    'reemplazo_de' => null,
                    'es_reemplazo' => false,
                    'sla_label' => $sla['sla_label'],
                    'sla_estado' => $sla['sla_estado'],
                    'sla_fecha_limite' => $sla['sla_fecha_limite'],
                    'dias_para_vencer' => $sla['dias_para_vencer'],
                    'url_ver' => route('consultar_ingreso_proveedor', ['id' => $t->id]),
                    'url_aprobar' => $puede ? url('mis-aprobaciones/ingreso-proveedor/'.$t->id.'/aprobar') : null,
                    'url_rechazar' => $puede ? url('mis-aprobaciones/ingreso-proveedor/'.$t->id.'/rechazar') : null,
                    'url_reenviar' => null,
                    'url_descartar' => null,
                    'url_detalle' => url('mis-aprobaciones/detalle/ingreso_proveedor/'.$t->id),
                    'subtitulo' => trim($proveedor.(optional($t->empresas)->nombre ? ' · '.optional($t->empresas)->nombre : '')),
                    'acciones_inline' => $puede,
                    'muestra_reenviar' => false,
                    'muestra_descartar' => false,
                    'sort_ts' => $fecha->getTimestamp(),
                ];
            });
    }

    private function urgenciaPorDias(int $dias): string
    {
        if ($dias >= 5) {
            return 'urgente';
        }
        if ($dias >= 2) {
            return 'atencion';
        }

        return 'normal';
    }

    /**
     * SLA genérico para fuentes sin ABM de árbol (umbral 5 días).
     *
     * @return array{sla_label: string, sla_estado: string, sla_fecha_limite: ?string, dias_para_vencer: ?int}
     */
    private function slaGenerico(int $diasPendiente, ?Carbon $fechaBase): array
    {
        $diasLimite = 5;
        if (! $fechaBase) {
            return [
                'sla_label' => $diasPendiente > 0 ? $diasPendiente.' día(s) en cola' : 'Reciente',
                'sla_estado' => $this->urgenciaPorDias($diasPendiente) === 'urgente' ? 'vencido' : 'ok',
                'sla_fecha_limite' => null,
                'dias_para_vencer' => null,
            ];
        }

        $limite = $fechaBase->copy()->startOfDay()->addDays($diasLimite);
        $hoy = Carbon::now()->startOfDay();
        $diasParaVencer = (int) $hoy->diffInDays($limite, false);

        if ($diasParaVencer < 0) {
            $atraso = abs($diasParaVencer);

            return [
                'sla_label' => $atraso === 1 ? 'Vencido hace 1 día' : 'Vencido hace '.$atraso.' días',
                'sla_estado' => 'vencido',
                'sla_fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => $diasParaVencer,
            ];
        }
        if ($diasParaVencer === 0) {
            return [
                'sla_label' => 'Vence hoy',
                'sla_estado' => 'urgente',
                'sla_fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => 0,
            ];
        }
        if ($diasParaVencer <= 2) {
            return [
                'sla_label' => 'Vence en '.$diasParaVencer.' día'.($diasParaVencer === 1 ? '' : 's'),
                'sla_estado' => 'atencion',
                'sla_fecha_limite' => $limite->format('Y-m-d'),
                'dias_para_vencer' => $diasParaVencer,
            ];
        }

        return [
            'sla_label' => 'Vence el '.$limite->format('d/m/Y'),
            'sla_estado' => 'ok',
            'sla_fecha_limite' => $limite->format('Y-m-d'),
            'dias_para_vencer' => $diasParaVencer,
        ];
    }
}
