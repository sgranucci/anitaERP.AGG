<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionModuloAvisoTipo;
use App\Models\Configuracion\ModuloAvisoDestinatario;
use App\Models\Configuracion\ModuloAvisoProgramadoLog;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Models\Listado\ListadoEnvioProgramado;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Support\Configuracion\ModuloAvisoImpactoPreviewSupport;
use App\Support\Configuracion\ModuloAvisoProgramacionSupport;
use App\Support\Configuracion\ModuloAvisoProgramadoDespachoSupport;
use App\Support\Configuracion\ModuloAvisoProgramadoEjecucionSupport;
use App\Support\Configuracion\ModuloAvisoProgramadoSuscripcionesSupport;
use App\Support\Configuracion\ModuloAvisoProgramadorCockpitSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ModuloAvisoController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private CentrocostoRepositoryInterface $centrocostoRepository,
    ) {
    }

    public function index(Request $request)
    {
        can('listar-modulo-aviso');

        $soloProgramables = $request->boolean('solo_programables');
        $mapa = config('modulo_aviso.programados', []);
        $clavesProgramables = is_array($mapa) ? array_keys($mapa) : [];

        $query = ModuloAvisoTipo::query()
            ->withCount(['destinatarios as destinatarios_activos_count' => function ($q) {
                $q->where('activo', true);
            }])
            ->orderBy('modulo')
            ->orderBy('nombre');

        if ($soloProgramables && $clavesProgramables !== []) {
            $query->where(function ($q) use ($clavesProgramables) {
                foreach ($clavesProgramables as $clave) {
                    [$modulo, $codigo] = array_pad(explode('.', (string) $clave, 2), 2, null);
                    if ($modulo && $codigo) {
                        $q->orWhere(function ($qq) use ($modulo, $codigo) {
                            $qq->where('modulo', $modulo)->where('codigo', $codigo);
                        });
                    }
                }
            });
        }

        $tipos = $query->get()->groupBy('modulo');
        $calModo = $request->input('cal_modo') === 'semana' ? 'semana' : 'mes';
        $calRef = trim((string) $request->input('cal_ref', ''));
        $cockpit = $soloProgramables
            ? ModuloAvisoProgramadorCockpitSupport::armar(
                null,
                $calModo,
                $calRef !== '' ? $calRef : null
            )
            : null;

        return view('configuracion.modulo_aviso.index', [
            'tipos' => $tipos,
            'solo_programables' => $soloProgramables,
            'claves_programables' => $clavesProgramables,
            'cockpit' => $cockpit,
            'checklist_operativo' => $soloProgramables
                ? []
                : \App\Support\Configuracion\ModuloAvisoOperativoEstadoSupport::checklist(),
        ]);
    }

    public function historial(Request $request): View
    {
        can('listar-modulo-aviso');

        $clave = trim((string) $request->input('clave', ''));
        $estado = trim((string) $request->input('estado', ''));
        $logs = ModuloAvisoProgramadorCockpitSupport::queryHistorial(
            $clave !== '' ? $clave : null,
            in_array($estado, ['ok', 'error'], true) ? $estado : null
        )->paginate(30)->appends($request->query());

        return view('configuracion.modulo_aviso.historial', [
            'logs' => $logs,
            'filtro_clave' => $clave,
            'filtro_estado' => $estado,
        ]);
    }

    public function impacto(int $id): View|RedirectResponse
    {
        can('listar-modulo-aviso');

        $tipo = ModuloAvisoTipo::findOrFail($id);
        if (! $this->esProgramable($tipo)) {
            return redirect()->route('editar_modulo_aviso', $id)
                ->with('error', 'Este aviso no es programable.');
        }

        return view('configuracion.modulo_aviso.impacto', [
            'tipo' => $tipo,
            'impacto' => ModuloAvisoImpactoPreviewSupport::paraTipo($tipo),
            'puede_ejecutar' => can('ejecutar-modulo-aviso-programado', false)
                || can('actualizar-modulo-aviso', false),
        ]);
    }

    public function impactoJson(int $id): JsonResponse
    {
        can('listar-modulo-aviso');

        $tipo = ModuloAvisoTipo::findOrFail($id);
        if (! $this->esProgramable($tipo)) {
            return response()->json(['ok' => false, 'error' => 'No programable'], 422);
        }

        return response()->json([
            'ok' => true,
            'tipo_id' => $tipo->id,
            'impacto' => ModuloAvisoImpactoPreviewSupport::paraTipo($tipo),
            'puede_ejecutar' => can('ejecutar-modulo-aviso-programado', false)
                || can('actualizar-modulo-aviso', false),
            'ejecutar_url' => route('ejecutar_ahora_modulo_aviso', $tipo->id),
        ]);
    }

    public function dryRunSchedule(int $id): View|RedirectResponse
    {
        can('listar-modulo-aviso');

        $tipo = ModuloAvisoTipo::findOrFail($id);
        if (! $this->esProgramable($tipo)) {
            return redirect()->route('editar_modulo_aviso', $id)
                ->with('error', 'Este aviso no es programable.');
        }

        $ahora = now()->timezone('America/Argentina/Buenos_Aires');
        $feriados = ModuloAvisoProgramacionSupport::feriadosYmdEntre(
            $ahora->toDateString(),
            $ahora->copy()->addDays(30)->toDateString()
        );

        return view('configuracion.modulo_aviso.dry_run', [
            'tipo' => $tipo,
            'fechas' => ModuloAvisoProgramacionSupport::simularProximosDias($tipo, $ahora, 30, $feriados),
            'etiqueta' => ModuloAvisoProgramacionSupport::etiquetaDesdeTipo($tipo),
        ]);
    }

    /**
     * Pausar / reactivar envío de listado o CI desde el Programador.
     */
    public function toggleHermano(int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $envio = ListadoEnvioProgramado::query()->findOrFail($id);
        $envio->activo = ! (bool) $envio->activo;
        $envio->save();

        $estado = $envio->activo ? 'reactivado' : 'pausado';

        return redirect()
            ->route('consultar_modulo_aviso', ['solo_programables' => 1])
            ->with('mensaje', 'Envío '.$estado.': '.$envio->recurso.' → '.$envio->email);
    }

    /**
     * Pausar / reactivar suscripción Flash o definible desde el Programador.
     */
    public function toggleSuscripcionHermana(string $familia, int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $ok = ModuloAvisoProgramadoSuscripcionesSupport::toggle($familia, $id);
        if ($ok === null) {
            return redirect()
                ->route('consultar_modulo_aviso', ['solo_programables' => 1])
                ->with('error', 'No se encontró la suscripción.');
        }

        $estado = $ok['activo'] ? 'reactivada' : 'pausada';

        return redirect()
            ->back()
            ->with('mensaje', 'Suscripción '.$estado.': '.$ok['nombre']);
    }

    public function detalleSuscripcionHermana(string $familia, int $id): \Illuminate\Http\JsonResponse
    {
        can('listar-modulo-aviso');

        $detalle = ModuloAvisoProgramadoSuscripcionesSupport::detalle($familia, $id);
        if ($detalle === null) {
            return response()->json(['ok' => false, 'error' => 'No encontrada'], 404);
        }

        return response()->json(['ok' => true, 'suscripcion' => $detalle]);
    }

    public function actualizarSuscripcionHermana(Request $request, string $familia, int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        try {
            $ok = ModuloAvisoProgramadoSuscripcionesSupport::actualizar($familia, $id, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->back()
            ->with('mensaje', 'Suscripción actualizada: '.$ok['nombre']);
    }

    public function opcionesAltaSuscripcionHermana(): \Illuminate\Http\JsonResponse
    {
        can('listar-modulo-aviso');

        return response()->json([
            'ok' => true,
            'opciones' => ModuloAvisoProgramadoSuscripcionesSupport::opcionesAlta(),
        ]);
    }

    public function crearSuscripcionHermana(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $familia = trim((string) $request->input('familia', ''));
        try {
            $ok = ModuloAvisoProgramadoSuscripcionesSupport::crear($familia, $request->all());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->back()
            ->with('mensaje', 'Suscripción creada: '.$ok['nombre']);
    }

    /**
     * Cambiar frecuencia diario/semanal de un hermano (listado/CI).
     */
    public function frecuenciaHermano(Request $request, int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $frecuencia = $request->input('frecuencia') === 'semanal' ? 'semanal' : 'diario';
        $envio = ListadoEnvioProgramado::query()->findOrFail($id);
        $envio->frecuencia = $frecuencia;
        $envio->save();

        return redirect()
            ->route('consultar_modulo_aviso', ['solo_programables' => 1])
            ->with('mensaje', 'Frecuencia actualizada a '.$frecuencia.' ('.$envio->recurso.').');
    }

    public function editar(int $id)
    {
        can('editar-modulo-aviso');

        $tipo = ModuloAvisoTipo::with(['destinatarios.usuarios', 'destinatarios.empresas', 'destinatarios.centrocostos'])
            ->findOrFail($id);

        $programable = $this->esProgramable($tipo);
        $logsRecientes = collect();
        if ($programable && Schema::hasTable('modulo_aviso_programado_log')) {
            $logsRecientes = ModuloAvisoProgramadoLog::query()
                ->where('modulo_aviso_tipo_id', $tipo->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get();
        }

        $clavesDep = array_keys(config('modulo_aviso.programados', []));

        return view('configuracion.modulo_aviso.editar', [
            'tipo' => $tipo,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'centrocosto_query' => $this->centrocostoRepository->all(),
            'placeholders_ayuda' => $this->placeholdersAyuda($tipo->modulo, $tipo->codigo),
            'programable' => $programable,
            'logs_recientes' => $logsRecientes,
            'usa_parametro_dias' => $tipo->modulo === 'caja' && $tipo->codigo === 'cheques_aging',
            'destinatarios_dinamicos' => $this->textoDestinatariosDinamicos($tipo),
            'claves_dependencia' => array_values(array_filter(
                $clavesDep,
                static fn ($c) => $c !== $tipo->claveCompleta()
            )),
            'puede_ejecutar' => can('ejecutar-modulo-aviso-programado', false)
                || can('actualizar-modulo-aviso', false),
            'impacto_resumen' => $programable
                ? ModuloAvisoImpactoPreviewSupport::paraTipo($tipo)
                : null,
            'suscripciones_envio' => $programable
                ? ModuloAvisoProgramadoSuscripcionesSupport::listarParaClave($tipo->claveCompleta(), 100)
                : [],
            'tab_activa' => in_array($requestTab = request()->input('tab'), ['acciones', 'rango', 'destinatarios', 'ejecucion', 'suscripciones'], true)
                ? $requestTab
                : ($programable ? 'rango' : 'acciones'),
            'fechas_dry_run' => $programable
                ? ModuloAvisoProgramacionSupport::simularProximosDias(
                    $tipo,
                    now()->timezone('America/Argentina/Buenos_Aires'),
                    14,
                    ModuloAvisoProgramacionSupport::feriadosYmdEntre(
                        now()->timezone('America/Argentina/Buenos_Aires')->toDateString(),
                        now()->timezone('America/Argentina/Buenos_Aires')->addDays(20)->toDateString()
                    )
                )
                : [],
        ]);
    }

    public function actualizar(ValidacionModuloAvisoTipo $request, int $id)
    {
        can('actualizar-modulo-aviso');

        $tipo = ModuloAvisoTipo::findOrFail($id);

        try {
            DB::transaction(function () use ($request, $tipo) {
            $datos = [
                'activo' => $request->boolean('activo'),
                'mail_asunto' => $request->input('mail_asunto'),
                'mail_texto' => $request->input('mail_texto'),
                'mail_remitente' => $request->input('mail_remitente') ?: null,
                'adjuntar_pdf' => $request->boolean('adjuntar_pdf'),
                'incluir_link_consulta' => $request->boolean('incluir_link_consulta'),
                'notificar_in_app' => $request->boolean('notificar_in_app'),
                'notificar_whatsapp' => $request->boolean('notificar_whatsapp'),
                'sla_horas' => ($request->filled('sla_horas') && (int) $request->input('sla_horas') > 0)
                    ? min(8760, (int) $request->input('sla_horas'))
                    : null,
            ];
            if ($this->esProgramable($tipo)) {
                $datos['programacion_habilitada'] = $request->boolean('programacion_habilitada');
                $datos['programacion_hora'] = ModuloAvisoProgramacionSupport::normalizarHora(
                    $request->input('programacion_hora')
                );
                $horaHasta = ModuloAvisoProgramacionSupport::normalizarHora(
                    $request->input('programacion_hora_hasta')
                );
                if ($horaHasta !== null && $datos['programacion_hora'] !== null && $horaHasta < $datos['programacion_hora']) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'programacion_hora_hasta' => 'La hora hasta debe ser ≥ hora desde.',
                    ]);
                }
                $datos['programacion_hora_hasta'] = $horaHasta;
                $diaMes = $request->filled('programacion_dia_mes')
                    ? max(1, min(31, (int) $request->input('programacion_dia_mes')))
                    : null;
                $datos['programacion_dia_mes'] = $diaMes;
                $datos['programacion_dias'] = $diaMes !== null
                    ? null
                    : ModuloAvisoProgramacionSupport::diasTexto($request->input('programacion_dias', []));
                $datos['programacion_cada_n'] = max(1, min(52, (int) $request->input('programacion_cada_n', 1)));
                $hasta = trim((string) $request->input('programacion_hasta', ''));
                $datos['programacion_hasta'] = $hasta !== '' ? $hasta : null;
                $datos['programacion_skip_feriados'] = $request->boolean('programacion_skip_feriados');
                $datos['programacion_pausada'] = $request->boolean('programacion_pausada');
                $datos['programacion_reintentos_max'] = max(0, min(96, (int) $request->input('programacion_reintentos_max', 0)));
                $dep = trim((string) $request->input('programacion_depende_de', ''));
                $mapa = config('modulo_aviso.programados', []);
                $datos['programacion_depende_de'] = ($dep !== '' && is_array($mapa) && isset($mapa[$dep]) && $dep !== $tipo->claveCompleta())
                    ? $dep
                    : null;
                $modoDep = trim((string) $request->input('programacion_depende_modo', 'ok_hoy'));
                $datos['programacion_depende_modo'] = ($datos['programacion_depende_de'] && isset(ModuloAvisoProgramacionSupport::modosDependencia()[$modoDep]))
                    ? $modoDep
                    : null;
                if ($datos['programacion_habilitada'] && ! $tipo->programacion_ancla) {
                    $datos['programacion_ancla'] = now()->timezone('America/Argentina/Buenos_Aires')->toDateString();
                }
                $datos['disparador'] = 'cron';

                if ($this->programacionCambia($tipo, $datos) && ! $request->boolean('confirmar_cambio_programacion')) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'confirmar_cambio_programacion' => 'Marcá «Confirmo el cambio de programación» para guardar horario/días/pausa del batch.',
                    ]);
                }
            }
            if ($tipo->modulo === 'caja' && $tipo->codigo === 'cheques_aging') {
                $diasProx = max(0, (int) $request->input('parametro_dias', 0));
                $datos['parametro_dias'] = $diasProx;
                $cond = is_array($tipo->condiciones) ? $tipo->condiciones : [];
                $cond['dias_proximos'] = $diasProx;
                $datos['condiciones'] = $cond;
            }
            $tipo->update($datos);

            $this->sincronizarDestinatarios($tipo, $request->input('destinatarios', []));
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('editar_modulo_aviso', ['id' => $id, 'tab' => 'rango'])
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect('configuracion/modulo-aviso')
            ->with('mensaje', 'Configuración de aviso actualizada.');
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function programacionCambia(ModuloAvisoTipo $tipo, array $datos): bool
    {
        $campos = [
            'programacion_habilitada',
            'programacion_hora',
            'programacion_hora_hasta',
            'programacion_dias',
            'programacion_dia_mes',
            'programacion_cada_n',
            'programacion_hasta',
            'programacion_skip_feriados',
            'programacion_pausada',
            'programacion_reintentos_max',
            'programacion_depende_de',
            'programacion_depende_modo',
        ];
        foreach ($campos as $campo) {
            if (! array_key_exists($campo, $datos)) {
                continue;
            }
            $nuevo = $datos[$campo];
            $viejo = $tipo->{$campo};
            if ($campo === 'programacion_hasta' || $campo === 'programacion_hora' || $campo === 'programacion_hora_hasta') {
                $viejo = $viejo ? (string) (is_object($viejo) ? $viejo->format('Y-m-d') : $viejo) : null;
                if ($campo === 'programacion_hora' || $campo === 'programacion_hora_hasta') {
                    $viejo = $tipo->{$campo} ? substr((string) $tipo->{$campo}, 0, 5) : null;
                    $nuevo = $nuevo ? substr((string) $nuevo, 0, 5) : null;
                }
            }
            if ($campo === 'programacion_habilitada' || $campo === 'programacion_skip_feriados' || $campo === 'programacion_pausada') {
                $viejo = (bool) $viejo;
                $nuevo = (bool) $nuevo;
            }
            if ((string) ($viejo ?? '') !== (string) ($nuevo ?? '')) {
                return true;
            }
        }

        return false;
    }

    public function ejecutarAhora(int $id): RedirectResponse
    {
        if (! can('ejecutar-modulo-aviso-programado', false) && ! can('actualizar-modulo-aviso', false)) {
            can('ejecutar-modulo-aviso-programado');
        }

        $tipo = ModuloAvisoTipo::findOrFail($id);
        if (! $this->esProgramable($tipo)) {
            return redirect()->route('editar_modulo_aviso', $id)
                ->with('error', 'Este aviso no tiene comando programable.');
        }

        $mapa = config('modulo_aviso.programados', []);
        $comando = is_array($mapa) ? ($mapa[$tipo->claveCompleta()] ?? null) : null;
        if (! is_string($comando) || $comando === '') {
            return redirect()->route('editar_modulo_aviso', $id)
                ->with('error', 'No hay comando asociado a este aviso.');
        }

        $clave = $tipo->claveCompleta();
        $resultado = ModuloAvisoProgramadoEjecucionSupport::ejecutar(
            $tipo,
            $comando,
            'manual',
            ModuloAvisoProgramadoDespachoSupport::opcionesArtisan($clave),
            false
        );
        $volver = request()->input('volver');
        $redirect = $volver === 'historial'
            ? redirect()->route('historial_modulo_aviso_programado')
            : redirect()->route('editar_modulo_aviso', ['id' => $id, 'tab' => 'ejecucion']);

        if ($resultado['exit'] === 0) {
            if (! ModuloAvisoProgramadoDespachoSupport::esCadaPasada($tipo)) {
                $tipo->programacion_ultima_en = now()->timezone('America/Argentina/Buenos_Aires')->toDateString();
                $tipo->save();
            }

            return $redirect->with('mensaje', 'Aviso ejecutado correctamente. Revisá la bitácora.');
        }

        return $redirect->with('error', 'La ejecución terminó con error'
            .($resultado['error'] ? ': '.$resultado['error'] : ' (código '.$resultado['exit'].').'));
    }

    /**
     * @param  array<int, array<string, mixed>>  $filas
     */
    private function sincronizarDestinatarios(ModuloAvisoTipo $tipo, array $filas): void
    {
        $idsConservados = [];

        foreach ($filas as $fila) {
            $email = trim((string) ($fila['email'] ?? ''));
            $usuarioId = ! empty($fila['usuario_id']) ? (int) $fila['usuario_id'] : null;
            if ($email === '' && ! $usuarioId) {
                continue;
            }

            $payload = [
                'modulo_aviso_tipo_id' => $tipo->id,
                'email' => $email !== '' ? $email : null,
                'usuario_id' => $usuarioId,
                'empresa_id' => ! empty($fila['empresa_id']) ? (int) $fila['empresa_id'] : null,
                'centrocosto_id' => ! empty($fila['centrocosto_id']) ? (int) $fila['centrocosto_id'] : null,
                'activo' => ! empty($fila['activo']),
            ];

            $destId = ! empty($fila['id']) ? (int) $fila['id'] : 0;
            if ($destId > 0) {
                $dest = ModuloAvisoDestinatario::where('modulo_aviso_tipo_id', $tipo->id)->find($destId);
                if ($dest) {
                    $dest->update($payload);
                    $idsConservados[] = $dest->id;

                    continue;
                }
            }

            $nuevo = ModuloAvisoDestinatario::create($payload);
            $idsConservados[] = $nuevo->id;
        }

        EloquentAuditDeleteSupport::exceptIds(
            ModuloAvisoDestinatario::query()->where('modulo_aviso_tipo_id', $tipo->id),
            $idsConservados
        );
    }

    /**
     * @return list<string>
     */
    private function placeholdersAyuda(string $modulo, string $codigo): array
    {
        $comunes = ['{link_consulta}'];

        if ($modulo === 'sala' && $codigo === 'requisicion_sala_creacion') {
            return array_merge($comunes, [
                '{numero}', '{solicitante}', '{empresa}', '{centro_costo}',
                '{fecha}', '{estado}', '{deposito}', '{zona_sala}', '{prioridad}',
            ]);
        }

        if ($modulo === 'stock' && str_starts_with($codigo, 'prestamo_')) {
            return array_merge($comunes, [
                '{codigo}', '{numero}', '{solicitante}', '{deposito_origen}', '{deposito_destino}',
                '{fecha_prestamo}', '{fecha_devolucion}', '{estado}',
            ]);
        }

        if ($modulo === 'ventas' && $codigo === 'pedido_produccion_alarma') {
            return array_merge($comunes, [
                '{numero}', '{cliente}', '{vendedor}', '{fecha}', '{fecha_entrega}',
                '{estado}', '{usuario}', '{articulos_alarma}',
            ]);
        }

        if ($modulo === 'stock' && str_starts_with($codigo, 'recepcion_proveedor_')) {
            return array_merge($comunes, [
                '{numero_recepcion}', '{numero_oc}', '{proveedor}', '{fecha}', '{estado}', '{com_anita}',
                '{comentario_precio}', '{resumen_diferencias}', '{resumen_rechazos}',
                '{usuario_recepcion}', '{detalle_lineas}',
            ]);
        }

        if ($modulo === 'uif' && $codigo === 'cliente_alta') {
            return array_merge($comunes, [
                '{id}', '{nombre}', '{numerodocumento}', '{tipodocumento}',
                '{cuit}', '{usuario_alta}', '{fecha}',
            ]);
        }

        if ($modulo === 'ticket' && $codigo === 'alta_tecnologia') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{titulo}', '{sala}', '{sector}',
                '{categoria}', '{subcategoria}', '{usuario}', '{cargado_por}',
                '{comentario}', '{fecha}', '{estado}', '{area}',
            ]);
        }

        if ($modulo === 'ticket' && $codigo === 'asignacion_tecnico') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{titulo}', '{tarea}', '{tecnico}', '{turno}',
                '{asignado_por}', '{usuario}', '{sala}', '{sector}',
                '{categoria}', '{subcategoria}', '{comentario}', '{fecha}',
                '{fechaprogramacion}', '{estado}', '{area}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'ordencompra_alertas_abiertas') {
            return array_merge($comunes, [
                '{fecha}', '{dias_sin_recepcion}',
                '{cantidad_sin_recepcion}', '{oc_sin_recepcion}',
                '{cantidad_parciales}', '{oc_parcialmente_recibidas}',
                '{cantidad_vencidas}', '{oc_vencidas}',
                '{cantidad_saldos_pendientes}', '{saldos_pendientes}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'entrega_dia_sin_recepcion') {
            return array_merge($comunes, [
                '{fecha}', '{cantidad}', '{articulos}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'ordencompra_contrato_sin_com') {
            return array_merge($comunes, [
                '{id}', '{numero}', '{empresa}', '{proveedor}', '{centrocosto}',
                '{detalle}', '{tratamiento}', '{imputacion}', '{cuenta_contable}',
                '{responsable}', '{vigencia_desde}', '{vigencia_hasta}',
                '{usuario_cambio}', '{fecha_cambio}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'contrato_validacion_abono_pendiente') {
            return array_merge($comunes, [
                '{numero_oc}', '{proveedor}', '{detalle}', '{periodo}',
                '{origen_etiqueta}', '{origen_numero}', '{estado}',
            ]);
        }

        if ($modulo === 'compras' && $codigo === 'comprobante_proveedor_borrador_pendiente') {
            return array_merge($comunes, [
                '{fecha}', '{cantidad}', '{facturas}',
            ]);
        }

        if ($modulo === 'sueldos' && $codigo === 'libreta_sanitaria_pendiente') {
            return array_merge($comunes, [
                '{fecha}', '{cantidad}', '{empleados}',
            ]);
        }

        if ($modulo === 'caja' && $codigo === 'cheques_aging') {
            return array_merge($comunes, [
                '{fecha}', '{cantidad}', '{cheques}', '{vencidos}', '{proximos}',
            ]);
        }

        if ($modulo === 'seguridad' && str_starts_with($codigo, 'ingreso_proveedor_')) {
            return array_merge($comunes, [
                '{id}', '{numero}', '{titulo}', '{proveedor}', '{motivo}',
                '{punto}', '{sector}', '{area}', '{usuario}', '{comentario}',
                '{fecha}', '{estado}', '{empresa}',
            ]);
        }

        return array_merge($comunes, ['{numero}', '{solicitante}', '{empresa}', '{centro_costo}', '{fecha}']);
    }

    private function esProgramable(ModuloAvisoTipo $tipo): bool
    {
        $mapa = config('modulo_aviso.programados', []);

        return is_array($mapa) && isset($mapa[$tipo->claveCompleta()]);
    }

    private function textoDestinatariosDinamicos(ModuloAvisoTipo $tipo): ?string
    {
        return match ($tipo->modulo.'.'.$tipo->codigo) {
            'compras.suscripcion_comprobante_faltante' => 'Además del catálogo, el mail principal va al encargado (dueño) de cada suscripción con comprobantes pendientes.',
            'compras.suscripcion_comprobante_escalamiento' => 'El escalamiento usa los destinatarios de este catálogo; el digest al dueño es el aviso «faltante».',
            'stock.prestamo_recordatorio', 'stock.prestamo_solicitud',
            'stock.prestamo_aprobado_solicitante', 'stock.prestamo_rechazado_solicitante'
                => 'Los destinatarios operativos salen del préstamo (solicitante / destinatario / admins del depósito). Las filas de esta tabla son copia adicional.',
            'compras.ordencompra_contrato_vencimiento', 'compras.ordencompra_contrato_vencido'
                => 'Además del catálogo (p. ej. ablanco), siempre recibe el responsable nominado del contrato (y el dueño si es suscripción).',
            default => null,
        };
    }
}
