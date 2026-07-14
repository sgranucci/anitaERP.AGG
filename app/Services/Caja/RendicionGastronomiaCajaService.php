<?php

namespace App\Services\Caja;

use App\Models\Caja\RendicionGastronomiaCaja;
use App\Models\Caja\RendicionGastronomiaMovimientoCaja;
use App\Models\Ventas\CierreTotemJornadaGastronomia;
use App\Models\Ventas\JornadaGastronomia;
use App\Models\Ventas\TurnoOperativoGastronomia;
use App\Support\Caja\RendicionGastronomiaCajaListadoFiltros;
use App\Support\Configuracion\EmpresaLogoArchivo;
use App\Support\Ventas\GastronomiaCuentacajaEfectivo;
use App\Support\Ventas\GastronomiaJornadaComprobantePermiso;
use App\Support\Ventas\GastronomiaTurnoMediosContadoCierreSupport;
use App\Support\Ventas\GastronomiaJornadaNumeracionComprobanteSupport;
use App\Support\Ventas\GastronomiaTurnoObservacionHabilitacionSupport;
use App\Services\Ventas\Gastronomia\GastronomiaCierreTotemInformeZService;
use App\Support\Ventas\GastronomiaTurnoOperativoTotalesSupport;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RendicionGastronomiaCajaService
{
    private const TOLERANCIA = 0.02;

    public function __construct(
        private readonly RendicionGastronomiaAnitaSyncService $anitaSyncService,
        private readonly RendicionGastronomiaJornadaPresentacionService $jornadaPresentacionService,
        private readonly GastronomiaCierreTotemInformeZService $informeZService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function listar(array $filtros, bool $paginar = true): LengthAwarePaginator|Collection
    {
        $q = RendicionGastronomiaCaja::query()
            ->with([
                'empresa:id,nombre',
                'caja:id,nombre',
                'puntoventaCae:id,codigo,nombre',
                'puntoventaCaea:id,codigo,nombre',
                'turnoOperativo.turno:id,nombre',
                'turnoOperativo.jornada:id,fecha_jornada',
                'jornada:id,fecha_jornada,estado',
                'jornada.cierreTotem:id,jornada_gastronomia_id',
                'cierreTotemJornada:id,jornada_gastronomia_id',
                'creousuario:id,nombre',
            ])
            ->orderByDesc('fecharendicion')
            ->orderByDesc('id');

        RendicionGastronomiaCajaListadoFiltros::aplicarScopeEmpresasAsignadas($q, $filtros);

        if (RendicionGastronomiaCajaListadoFiltros::tieneCriteriosAplicados($filtros)) {
            RendicionGastronomiaCajaListadoFiltros::aplicar($q, $filtros);
        }

        return $paginar ? $q->paginate(10) : $q->get();
    }

    /**
     * Turnos cerrados aún no rendidos en caja.
     *
     * @return Collection<int, TurnoOperativoGastronomia>
     */
    public function turnosPendientes(?int $exceptoRendicionId = null, ?int $empresaId = null): Collection
    {
        $rendidos = RendicionGastronomiaCaja::query()
            ->whereNotNull('turno_operativo_gastronomia_id')
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('turno_operativo_gastronomia_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return TurnoOperativoGastronomia::query()
            ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'empresa:id,nombre'])
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->whereNotNull('cierre_en')
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotIn('id', $rendidos)
            ->orderByDesc('cierre_en')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{data: string}
     */
    /**
     * Jornadas cerradas aún no presentadas en caja.
     *
     * @return Collection<int, JornadaGastronomia>
     */
    public function jornadasPendientes(?int $exceptoRendicionId = null, ?int $empresaId = null): Collection
    {
        $rendidas = RendicionGastronomiaCaja::query()
            ->where('tipo', RendicionGastronomiaCaja::TIPO_JORNADA)
            ->whereNotNull('jornada_gastronomia_id')
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->pluck('jornada_gastronomia_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->values()
            ->all();

        return JornadaGastronomia::query()
            ->with(['empresa:id,nombre', 'usuarioCierre:id,nombre', 'cierreTotem'])
            ->where('estado', JornadaGastronomia::ESTADO_CERRADA)
            ->whereNotNull('cierre_en')
            ->when($empresaId > 0, fn ($q) => $q->where('empresa_id', $empresaId))
            ->whereNotIn('id', $rendidas)
            ->orderByDesc('cierre_en')
            ->limit(200)
            ->get();
    }

    /**
     * @return array{data: string}
     */
    public function consultaCierresJornada(
        string $consulta,
        int $empresaId,
        ?int $exceptoRendicionId = null,
        bool $puedeVerComprobante = false,
    ): array {
        if ($empresaId <= 0) {
            return ['data' => '<tr><td colspan="6">Seleccione una empresa.</td></tr>'];
        }

        $consulta = trim($consulta);

        if ($consulta !== '' && ctype_digit($consulta)) {
            try {
                $encontrado = $this->findJornadaPendientePorNumero((int) $consulta, $empresaId, $exceptoRendicionId);
                $jornada = JornadaGastronomia::query()
                    ->with(['empresa:id,nombre', 'usuarioCierre:id,nombre', 'cierreTotem'])
                    ->find($encontrado['id']);

                if ($jornada === null) {
                    return ['data' => '<tr><td colspan="6">Jornada no encontrada.</td></tr>'];
                }

                return ['data' => $this->renderJornadasPendientesHtml(collect([$jornada]), $puedeVerComprobante)];
            } catch (InvalidArgumentException $e) {
                return ['data' => '<tr><td colspan="6">'.e($e->getMessage()).'</td></tr>'];
            }
        }

        $jornadas = $this->jornadasPendientes($exceptoRendicionId, $empresaId);

        if ($consulta !== '') {
            $needle = mb_strtoupper($consulta);
            $jornadas = $jornadas->filter(function (JornadaGastronomia $j) use ($needle) {
                $haystack = mb_strtoupper(implode(' ', [
                    (string) $j->id,
                    (string) ($j->fecha_jornada?->format('d/m/Y') ?? ''),
                    (string) ($j->cierre_en?->format('d/m/Y H:i') ?? ''),
                    (string) ($j->empresa?->nombre ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $jornadas = $jornadas->take(200);

        if ($jornadas->isEmpty()) {
            return ['data' => '<tr><td colspan="6">Sin jornadas listas para rendir en caja. '
                .'Rendición de turnos pendientes primero o la jornada ya fue presentada.</td></tr>'];
        }

        return ['data' => $this->renderJornadasPendientesHtml($jornadas, $puedeVerComprobante)];
    }

    /**
     * @param  Collection<int, JornadaGastronomia>  $jornadas
     */
    private function renderJornadasPendientesHtml(Collection $jornadas, bool $puedeVerComprobante = false): string
    {
        $html = '';
        foreach ($jornadas as $j) {
            $errores = $this->jornadaPresentacionService->erroresAntesDeRendir($j, null);
            $bloqueada = $errores !== [];
            $waitryHasta = (int) ($j->cierreTotem?->waitry_order_id_hasta ?? 0);
            $html .= '<tr>';
            $html .= '<td class="id">'.e((string) $j->id).'</td>';
            $html .= '<td class="fecha_jornada">'.e((string) ($j->fecha_jornada?->format('d/m/Y') ?? '')).'</td>';
            $html .= '<td class="cierre_en">'.e((string) ($j->cierre_en?->format('d/m/Y H:i') ?? '')).'</td>';
            $html .= '<td class="waitry_hasta">'.e($waitryHasta > 0 ? (string) $waitryHasta : '—').'</td>';
            $html .= '<td class="usuario_cierre">'.e((string) ($j->usuarioCierre?->nombre ?? '')).'</td>';
            $html .= '<td class="text-nowrap">';
            if ($puedeVerComprobante && $j->cierreTotem !== null) {
                $urlPdf = route('gastronomia_jornada_comprobante_cierre_totem', ['jornadaId' => $j->id, 'inline' => 1]);
                $tituloPdf = 'Jornada #'.$j->id.' — '.$j->fecha_jornada?->format('d/m/Y');
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm js-ver-comprobante-cierre-modal mr-1" ';
                $html .= 'data-url="'.e($urlPdf).'" data-titulo="'.e($tituloPdf).'" title="Comprobante cierre tótem">';
                $html .= '<i class="fa fa-file-pdf-o"></i></button>';
            }
            if (! $bloqueada) {
                $html .= '<a class="btn btn-warning btn-sm eligeconsultacierrejornada">Elegir</a>';
            } else {
                $titulo = e(implode(' ', $errores));
                $html .= '<span class="btn btn-secondary btn-sm disabled" title="'.$titulo.'">Bloqueada</span>';
                $html .= '<div class="small text-danger mt-1" style="max-width: 280px; white-space: normal;">'
                    .e($errores[0] ?? 'No disponible para rendir.')
                    .'</div>';
            }
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * @return array{id:int, etiqueta:string}
     *
     * @throws InvalidArgumentException
     */
    public function findJornadaPendientePorNumero(int $numero, int $empresaId, ?int $exceptoRendicionId = null): array
    {
        if ($numero <= 0 || $empresaId <= 0) {
            throw new InvalidArgumentException('Indique un número de jornada válido.');
        }

        $jornada = JornadaGastronomia::query()
            ->with(['empresa:id,nombre', 'cierreTotem'])
            ->where('id', $numero)
            ->first();

        if ($jornada === null) {
            throw new InvalidArgumentException('No existe la jornada #'.$numero.'.');
        }

        if ((int) $jornada->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('La jornada #'.$numero.' pertenece a otra empresa.');
        }

        if ($jornada->estado !== JornadaGastronomia::ESTADO_CERRADA || $jornada->cierre_en === null) {
            throw new InvalidArgumentException('La jornada #'.$numero.' no está cerrada.');
        }

        if ($this->jornadaPresentacionService->jornadaYaRendida($numero, $exceptoRendicionId)) {
            throw new InvalidArgumentException('La jornada #'.$numero.' ya fue rendida en caja.');
        }

        $errores = $this->jornadaPresentacionService->erroresAntesDeRendir($jornada, $exceptoRendicionId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        return [
            'id' => (int) $jornada->id,
            'etiqueta' => $this->etiquetaJornadaPendiente($jornada),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeJornada(int $jornadaId, ?int $exceptoRendicionId = null): array
    {
        if ($this->jornadaPresentacionService->jornadaYaRendida($jornadaId, $exceptoRendicionId)) {
            throw new InvalidArgumentException('La jornada #'.$jornadaId.' ya tiene una rendición registrada en caja.');
        }

        $jornada = JornadaGastronomia::query()
            ->with(['empresa', 'usuarioApertura', 'usuarioCierre', 'cierreTotem'])
            ->where('estado', JornadaGastronomia::ESTADO_CERRADA)
            ->findOrFail($jornadaId);

        $errores = $this->jornadaPresentacionService->erroresAntesDeRendir($jornada, $exceptoRendicionId);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcularPorJornada($jornada);
        $movimientos = $this->movimientosDesdeTotales($totales);
        $auditoriaJornada = $this->jornadaPresentacionService->datosAuditoriaJornadaParaCaja($jornada);
        $marcadores = $auditoriaJornada;
        $numeracion = $marcadores['numeracion_comprobantes_json'] ?? [];

        $pvPrincipal = $this->resolverPuntoventaPrincipalEmpresa((int) $jornada->empresa_id);

        return [
            'tipo' => RendicionGastronomiaCaja::TIPO_JORNADA,
            'jornada_gastronomia_id' => (int) $jornada->id,
            'empresa_id' => (int) $jornada->empresa_id,
            'empresa_nombre' => (string) ($jornada->empresa?->nombre ?? ''),
            'puntoventa_cae_id' => $pvPrincipal['cae_id'],
            'puntoventa_caea_id' => $pvPrincipal['caea_id'],
            'puntoventa_cae_label' => $pvPrincipal['cae_label'],
            'puntoventa_caea_label' => $pvPrincipal['caea_label'],
            'fecha_jornada' => $jornada->fecha_jornada?->format('d/m/Y') ?? '',
            'apertura_en' => $jornada->apertura_en?->format('d/m/Y H:i') ?? '',
            'cierre_en' => $jornada->cierre_en?->format('d/m/Y H:i') ?? '',
            'usuario_apertura' => (string) ($jornada->usuarioApertura?->nombre ?? ''),
            'usuario_cierre' => (string) ($jornada->usuarioCierre?->nombre ?? ''),
            'iniciodelfondo' => 0.0,
            'totalfactura' => round((float) ($totales['total_ventas'] ?? 0), 2),
            'totalcobrado' => round((float) ($totales['total_cobrado'] ?? 0), 2),
            'totalinvitacion' => round((float) ($totales['total_invitaciones'] ?? 0), 2),
            'totalnotacredito' => round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2),
            'totalredondeo' => 0.0,
            'totalredondeoinvitacion' => round((float) ($totales['redondeo_invitaciones_sugerido'] ?? 0), 2),
            'sobrantefaltante' => 0.0,
            'observacion_jornada' => (string) ($jornada->observacion_cierre ?? ''),
            'waitry_order_id_hasta' => (int) ($marcadores['waitry_order_id_hasta'] ?? 0),
            'waitry_order_id_anterior' => (int) ($marcadores['waitry_order_id_anterior'] ?? 0),
            'waitry_order_id_desde' => $marcadores['waitry_order_id_desde'] ?? null,
            'waitry_rango_etiqueta' => (string) ($marcadores['waitry_rango_etiqueta'] ?? ''),
            'proximo_waitry_order_id' => (int) ($marcadores['proximo_waitry_order_id'] ?? 0),
            'cierre_totem_habilitado' => (bool) ($marcadores['cierre_totem_habilitado'] ?? false),
            'informe_z_cargado' => (bool) ($marcadores['informe_z_cargado'] ?? false),
            'informe_z_en' => $marcadores['informe_z_en'] ?? null,
            'usuario_informe_z' => $marcadores['usuario_informe_z'] ?? null,
            'informe_z_plantilla' => $marcadores['informe_z_plantilla'] ?? null,
            'informe_z_precarga_automatica' => (bool) ($marcadores['informe_z_precarga_automatica'] ?? false),
            'informe_z_ajustado_en_caja' => (bool) ($marcadores['informe_z_ajustado_en_caja'] ?? false),
            'informe_z_ajuste_caja_en' => $marcadores['informe_z_ajuste_caja_en'] ?? null,
            'informe_z_ajuste_caja_usuario' => $marcadores['informe_z_ajuste_caja_usuario'] ?? null,
            'conciliacion_informe_z' => $marcadores['conciliacion_informe_z'] ?? null,
            'tolerancia_informe_z' => (float) ($marcadores['tolerancia_informe_z'] ?? 0.02),
            'transmision_faltante_z' => $marcadores['transmision_faltante_z'] ?? null,
            'sin_cierre_totem_jornada' => (bool) ($marcadores['sin_cierre_totem_jornada'] ?? false),
            'aviso_cierre_totem' => $marcadores['aviso_cierre_totem'] ?? null,
            'numeracion_comprobantes' => $numeracion,
            'numeracion_resumen' => (string) ($marcadores['numeracion_resumen'] ?? $numeracion['resumen_numeracion'] ?? ''),
            'numeracion_por_puntoventa' => is_array($numeracion['por_puntoventa'] ?? null)
                ? $numeracion['por_puntoventa']
                : [],
            'totales_dia' => $totales,
            'totales_turno' => null,
            'movimientos' => $movimientos,
            'url_comprobante_cierre' => ($jornada->cierreTotem !== null
                && GastronomiaJornadaComprobantePermiso::puedeVerComprobanteCierreTotem())
                ? route('gastronomia_jornada_comprobante_cierre_totem', ['jornadaId' => $jornada->id, 'inline' => 1])
                : '',
            'codigo_propuesto' => $this->jornadaPresentacionService->proponerCodigoInterno(
                (int) $jornada->empresa_id,
                (int) $jornada->id,
            ),
            'resumen_diferencias' => $this->armarResumenDiferencias(
                round((float) ($totales['total_cobrado'] ?? 0), 2),
                $movimientos,
                0.0,
                round((float) ($totales['redondeo_invitaciones_sugerido'] ?? 0), 2),
                0.0,
            ),
            'cuentacaja_efectivo_id' => (int) (GastronomiaCuentacajaEfectivo::idParaEmpresa((int) $jornada->empresa_id) ?? 0),
        ];
    }

    /**
     * @return array{cae_id:int, caea_id:int, cae_label:string, caea_label:string}
     */
    private function resolverPuntoventaPrincipalEmpresa(int $empresaId): array
    {
        $cfg = \App\Models\Ventas\ConfiguracionPuntoventaGastronomia::query()
            ->with(['puntoventaCae', 'puntoventaCaea'])
            ->where('empresa_id', $empresaId)
            ->orderBy('id')
            ->first();

        $caeId = (int) ($cfg?->puntoventa_cae_id ?? 0);
        $caeaId = (int) ($cfg?->puntoventa_caea_id ?? $caeId);

        return [
            'cae_id' => $caeId,
            'caea_id' => $caeaId > 0 ? $caeaId : $caeId,
            'cae_label' => $this->etiquetaPuntoventa($cfg?->puntoventaCae),
            'caea_label' => $this->etiquetaPuntoventa($cfg?->puntoventaCaea ?? $cfg?->puntoventaCae),
        ];
    }

    private function etiquetaJornadaPendiente(JornadaGastronomia $jornada): string
    {
        $waitry = (int) ($jornada->cierreTotem?->waitry_order_id_hasta ?? 0);
        $ref = 'Jornada #'.$jornada->id.' — '.($jornada->fecha_jornada?->format('d/m/Y') ?? '')
            .' — cierre '.($jornada->cierre_en?->format('d/m/Y H:i') ?? '');

        if ($waitry > 0) {
            $ref .= ' — Waitry hasta '.$waitry;
        }

        return $ref;
    }

    public function consultaCierresTurno(
        string $consulta,
        int $empresaId,
        ?int $exceptoRendicionId = null,
        bool $puedeVerComprobante = false,
        bool $puedeVerDetalleCierre = false,
    ): array {
        if ($empresaId <= 0) {
            return ['data' => '<tr><td colspan="6">Seleccione una empresa.</td></tr>'];
        }

        $consulta = trim($consulta);

        if ($consulta !== '' && ctype_digit($consulta)) {
            try {
                $encontrado = $this->findTurnoPendientePorNumero((int) $consulta, $empresaId, $exceptoRendicionId);
                $turno = TurnoOperativoGastronomia::query()
                    ->with(['turno:id,nombre', 'jornada:id,fecha_jornada', 'empresa:id,nombre'])
                    ->find($encontrado['id']);

                if ($turno === null) {
                    return ['data' => '<tr><td colspan="6">Cierre no encontrado.</td></tr>'];
                }

                return ['data' => $this->renderTurnosPendientesHtml(collect([$turno]), $puedeVerComprobante, $puedeVerDetalleCierre)];
            } catch (InvalidArgumentException $e) {
                return ['data' => '<tr><td colspan="6">'.e($e->getMessage()).'</td></tr>'];
            }
        }

        $turnos = $this->turnosPendientes($exceptoRendicionId, $empresaId);

        if ($consulta !== '') {
            $needle = mb_strtoupper($consulta);
            $turnos = $turnos->filter(function (TurnoOperativoGastronomia $t) use ($needle) {
                $haystack = mb_strtoupper(implode(' ', [
                    (string) $t->id,
                    (string) ($t->turno?->nombre ?? ''),
                    (string) ($t->identificador_pc ?? ''),
                    (string) ($t->cierre_en?->format('d/m/Y H:i') ?? ''),
                    (string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? ''),
                ]));

                return str_contains($haystack, $needle);
            })->values();
        }

        $turnos = $turnos->take(200);

        if ($turnos->isEmpty()) {
            return ['data' => '<tr><td colspan="6">Sin cierres pendientes de rendir.</td></tr>'];
        }

        return ['data' => $this->renderTurnosPendientesHtml($turnos, $puedeVerComprobante, $puedeVerDetalleCierre)];
    }

    /**
     * @param  Collection<int, TurnoOperativoGastronomia>  $turnos
     */
    private function renderTurnosPendientesHtml(
        Collection $turnos,
        bool $puedeVerComprobante = false,
        bool $puedeVerDetalleCierre = false,
    ): string {
        $html = '';
        foreach ($turnos as $t) {
            $html .= '<tr>';
            $html .= '<td class="id">'.e((string) $t->id).'</td>';
            $html .= '<td class="turno_nombre">'.e((string) ($t->turno?->nombre ?? '')).'</td>';
            $html .= '<td class="identificador_pc">'.e((string) ($t->identificador_pc ?? '')).'</td>';
            $html .= '<td class="cierre_en">'.e((string) ($t->cierre_en?->format('d/m/Y H:i') ?? '')).'</td>';
            $html .= '<td class="fecha_jornada">'.e((string) ($t->jornada?->fecha_jornada?->format('d/m/Y') ?? '')).'</td>';
            $html .= '<td class="text-nowrap">';
            if ($puedeVerDetalleCierre) {
                $urlVer = route('gastronomia_cierre_turno_ver', [
                    'id' => $t->id,
                    'vista' => 'consulta',
                    'origen' => 'modal_consulta',
                ]);
                $html .= '<button type="button" class="btn btn-outline-info btn-sm js-ver-cierre-turno-detalle mr-1" ';
                $html .= 'data-url="'.e($urlVer).'" title="Visualizar cierre (modo consulta)">';
                $html .= '<i class="fa fa-eye"></i></button>';
            }
            if ($puedeVerComprobante) {
                $urlPdf = route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $t->id, 'inline' => 1]);
                $tituloPdf = 'Op. #'.$t->id.' — '.($t->turno?->nombre ?? '');
                $html .= '<button type="button" class="btn btn-outline-danger btn-sm js-ver-comprobante-cierre-modal mr-1" ';
                $html .= 'data-url="'.e($urlPdf).'" data-titulo="'.e($tituloPdf).'" title="Ver comprobante (solapa Comprobante)">';
                $html .= '<i class="fa fa-file-pdf-o"></i></button>';
            }
            $html .= '<a class="btn btn-warning btn-sm eligeconsultacierre">Elegir</a>';
            $html .= '</td>';
            $html .= '</tr>';
        }

        return $html;
    }

    /**
     * @return array{id:int, etiqueta:string}
     *
     * @throws InvalidArgumentException
     */
    public function findTurnoPendientePorNumero(int $numero, int $empresaId, ?int $exceptoRendicionId = null): array
    {
        if ($numero <= 0 || $empresaId <= 0) {
            throw new InvalidArgumentException('Indique un número de cierre de turno válido.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with(['turno:id,nombre', 'jornada:id,fecha_jornada'])
            ->where('id', $numero)
            ->first();

        if ($turno === null) {
            throw new InvalidArgumentException('No existe el turno operativo #'.$numero.'.');
        }

        if ((int) $turno->empresa_id !== $empresaId) {
            throw new InvalidArgumentException('El turno #'.$numero.' pertenece a otra empresa.');
        }

        if ($turno->estado !== TurnoOperativoGastronomia::ESTADO_CERRADO || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno #'.$numero.' no tiene cierre definitivo.');
        }

        if ($this->turnoYaRendido($numero, $exceptoRendicionId)) {
            throw new InvalidArgumentException('El turno #'.$numero.' ya fue rendido en caja.');
        }

        return [
            'id' => (int) $turno->id,
            'etiqueta' => $this->etiquetaTurnoPendiente($turno),
        ];
    }

    public function proponerCodigoAnita(int $empresaId): string
    {
        return $this->proponerNumeracionAnita($empresaId)['codigo'];
    }

    /**
     * @return array{
     *   codigo: string,
     *   nro_oper: int,
     *   fuente: string,
     *   ultimo_anita: int,
     *   ultimo_erp: int,
     *   consulta_anita_ok: bool
     * }
     */
    public function proponerNumeracionAnita(int $empresaId): array
    {
        return $this->anitaSyncService->proponerSiguienteNroOper($empresaId);
    }

    /**
     * @return array<string, mixed>
     */
    public function datosDesdeTurno(int $turnoId, ?int $exceptoRendicionId = null): array
    {
        if ($this->turnoYaRendido($turnoId, $exceptoRendicionId)) {
            throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya tiene una rendición registrada en caja.');
        }

        $turno = TurnoOperativoGastronomia::query()
            ->with([
                'turno',
                'jornada',
                'empresa',
                'configuracionPuntoventa.puntoventaCae',
                'configuracionPuntoventa.puntoventaCaea',
                'usuarioCierre',
                'usuarioHabilitacion',
                'usuarioHabilitado',
            ])
            ->where('estado', TurnoOperativoGastronomia::ESTADO_CERRADO)
            ->findOrFail($turnoId);

        if ($turno->habilitacion_en === null || $turno->cierre_en === null) {
            throw new InvalidArgumentException('El turno no tiene fechas de habilitación o cierre.');
        }

        $cfg = $turno->configuracionPuntoventa;
        if ($cfg === null) {
            throw new InvalidArgumentException('El turno no tiene configuración de punto de venta.');
        }

        $fechaJornada = $turno->jornada?->fecha_jornada?->format('Y-m-d')
            ?? $turno->cierre_en->format('Y-m-d');

        $totales = GastronomiaTurnoOperativoTotalesSupport::calcular(
            (string) $turno->identificador_pc,
            (int) $turno->empresa_id,
            $fechaJornada,
            Carbon::parse($turno->habilitacion_en),
            Carbon::parse($turno->cierre_en),
        );

        $mediosContado = GastronomiaTurnoMediosContadoCierreSupport::desdeAlmacenado(
            $turno->medios_contado_cierre_json,
        );
        $totales = GastronomiaTurnoMediosContadoCierreSupport::enriquecerTotalesConContado(
            $totales,
            $mediosContado,
        );

        $movimientos = $this->movimientosDesdeTotales($totales);

        return [
            'turno_operativo_gastronomia_id' => (int) $turno->id,
            'empresa_id' => (int) $turno->empresa_id,
            'empresa_nombre' => (string) ($turno->empresa?->nombre ?? ''),
            'puntoventa_cae_id' => (int) $cfg->puntoventa_cae_id,
            'puntoventa_caea_id' => (int) $cfg->puntoventa_caea_id,
            'puntoventa_cae_label' => $this->etiquetaPuntoventa($cfg->puntoventaCae),
            'puntoventa_caea_label' => $this->etiquetaPuntoventa($cfg->puntoventaCaea),
            'identificador_pc' => (string) $turno->identificador_pc,
            'turno_nombre' => (string) ($turno->turno?->nombre ?? ''),
            'fecha_jornada' => $turno->jornada?->fecha_jornada?->format('d/m/Y') ?? '',
            'habilitacion_en' => $turno->habilitacion_en->format('d/m/Y H:i'),
            'cierre_en' => $turno->cierre_en->format('d/m/Y H:i'),
            'usuario_habilita' => (string) ($turno->usuarioHabilitacion?->nombre ?? ''),
            'usuario_habilitado' => (string) ($turno->usuarioHabilitado?->nombre ?? ''),
            'usuario_cierre' => (string) ($turno->usuarioCierre?->nombre ?? ''),
            'monto_habilitacion' => round((float) $turno->monto_habilitacion, 2),
            'iniciodelfondo' => round((float) $turno->monto_habilitacion, 2),
            'totalfactura' => round((float) ($totales['total_ventas'] ?? 0), 2),
            'totalcobrado' => round((float) ($totales['total_cobrado'] ?? 0), 2),
            'totalinvitacion' => round((float) ($totales['total_invitaciones'] ?? 0), 2),
            'totalnotacredito' => round(abs((float) ($totales['total_notas_credito'] ?? 0)), 2),
            'totalredondeo' => round((float) ($turno->redondeo_turno ?? 0), 2),
            'totalredondeoinvitacion' => round((float) ($turno->redondeo_invitaciones ?? 0), 2),
            'sobrantefaltante' => round((float) ($turno->sobrante_faltante ?? 0), 2),
            'observacion_turno' => (string) ($turno->observacion_cierre ?? ''),
            'totales_turno' => $totales,
            'medios_contado_cierre' => $mediosContado,
            'movimientos' => $movimientos,
            'movimientos_desde_contado_cierre' => $mediosContado !== null,
            'turno_sin_actividad' => ! $this->tieneMovimientosMedioPago($movimientos)
                && round((float) ($totales['total_cobrado'] ?? 0), 2) <= self::TOLERANCIA,
            'url_comprobante_cierre' => can('ver-comprobante-cierre-turno-gastronomia', false)
                ? route('gastronomia_cierre_turno_comprobante_cierre', ['id' => $turno->id, 'inline' => 1])
                : '',
            'resumen_diferencias' => $this->armarResumenDiferencias(
                round((float) ($totales['total_cobrado'] ?? 0), 2),
                $movimientos,
                round((float) ($turno->redondeo_turno ?? 0), 2),
                round((float) ($turno->redondeo_invitaciones ?? 0), 2),
                round((float) ($turno->sobrante_faltante ?? 0), 2),
            ),
            'cuentacaja_efectivo_id' => (int) (GastronomiaCuentacajaEfectivo::idParaEmpresa((int) $turno->empresa_id) ?? 0),
        ];
    }

    public function findConDetalle(int $id): RendicionGastronomiaCaja
    {
        return RendicionGastronomiaCaja::query()
            ->with([
                'empresa',
                'caja',
                'puntoventaCae',
                'puntoventaCaea',
                'turnoOperativo.turno',
                'turnoOperativo.jornada',
                'turnoOperativo.usuarioCierre',
                'turnoOperativo.usuarioHabilitacion',
                'turnoOperativo.usuarioHabilitado',
                'jornada.usuarioApertura',
                'jornada.usuarioCierre',
                'jornada.cierreTotem',
                'cierreTotemJornada',
                'movimientos.cuentacaja',
                'creousuario',
            ])
            ->findOrFail($id);
    }

    /**
     * @return array{datos: array<string, mixed>}
     */
    public function datosParaImpresion(int $id): array
    {
        $data = $this->findConDetalle($id);
        $data->loadMissing([
            'turnoOperativo.usuarioHabilitacion',
            'turnoOperativo.usuarioHabilitado',
        ]);

        $totalesTurno = null;
        $totalesDia = null;
        if ($data->esRendicionJornada()) {
            try {
                $datosJornada = $this->datosDesdeJornada((int) $data->jornada_gastronomia_id, $id);
                $totalesDia = $datosJornada['totales_dia'] ?? null;
            } catch (InvalidArgumentException) {
                // Totales persistidos en cabecera.
            }
        } else {
            try {
                $datosTurno = $this->datosDesdeTurno((int) $data->turno_operativo_gastronomia_id, $id);
                $totalesTurno = $datosTurno['totales_turno'] ?? null;
            } catch (InvalidArgumentException) {
                // Se usan totales persistidos en la rendición.
            }
        }

        $lineas = $this->lineasMediosParaImpresion($data, $totalesTurno ?? $totalesDia);

        $resumen = $this->armarResumenDiferencias(
            round((float) $data->totalcobrado, 2),
            array_map(fn (array $l) => ['monto' => $l['monto']], $lineas),
            round((float) $data->totalredondeo, 2),
            round((float) $data->totalredondeoinvitacion, 2),
            round((float) $data->sobrantefaltante, 2),
        );

        return [
            'datos' => $this->armarDatosComprobanteRendicion($data, $totalesTurno, $lineas, $resumen, $totalesDia),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totalesTurno
     * @param  list<array{nombre: string, codigo: string, monto: float, cotizacion: float, es_nota_credito: bool}>  $lineas
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function armarDatosComprobanteRendicion(
        RendicionGastronomiaCaja $r,
        ?array $totalesTurno,
        array $lineas,
        array $resumen,
        ?array $totalesDia = null,
    ): array {
        $turno = $r->turnoOperativo;
        $jornada = $r->jornada;
        $empresaNombre = (string) ($r->empresa?->nombre ?? '');
        $esJornada = $r->esRendicionJornada();

        if ($esJornada) {
            $totalesDia = is_array($totalesDia) && $totalesDia !== []
                ? $totalesDia
                : $this->totalesTurnoDesdeCabeceraRendicion($r, $resumen);
            $totalesTurno = null;
        } else {
            $totalesTurno = is_array($totalesTurno) && $totalesTurno !== []
                ? $totalesTurno
                : $this->totalesTurnoDesdeCabeceraRendicion($r, $resumen);
            $totalesDia = null;
        }

        $fechaJornadaStr = $esJornada
            ? ($jornada?->fecha_jornada?->format('d/m/Y') ?? '')
            : ($turno?->jornada?->fecha_jornada?->format('d/m/Y') ?? '');
        $fechaJornadaIso = $esJornada
            ? ($jornada?->fecha_jornada?->format('Y-m-d') ?? '')
            : ($turno?->jornada?->fecha_jornada?->format('Y-m-d') ?? '');
        $fechaRegistroCaja = $r->fecharendicion;
        $fechasMismoDia = $fechaJornadaIso !== ''
            && $fechaRegistroCaja !== null
            && $fechaJornadaIso === $fechaRegistroCaja->format('Y-m-d');
        $obsHabilitacion = GastronomiaTurnoObservacionHabilitacionSupport::parse($turno?->observacion_habilitacion);

        $subtitulo = $esJornada
            ? 'Ticket '.$r->codigo.' — Jornada #'.$r->jornada_gastronomia_id
                .' · Waitry hasta '.((int) $r->waitry_order_id_hasta > 0 ? (string) $r->waitry_order_id_hasta : '—')
            : 'Ticket '.$r->codigo.' — Turno operativo #'.$r->turno_operativo_gastronomia_id;

        return [
            'tipo' => 'rendicion',
            'tipo_rendicion' => $esJornada ? RendicionGastronomiaCaja::TIPO_JORNADA : RendicionGastronomiaCaja::TIPO_TURNO,
            'titulo' => $esJornada ? 'Rendición gastronomía — jornada (caja)' : 'Rendición gastronomía — caja',
            'subtitulo' => $subtitulo,
            'logo' => EmpresaLogoArchivo::dataUriDesdeNombre($empresaNombre),
            'empresa_nombre' => $empresaNombre,
            'identificador_pc' => $esJornada ? 'Todas las terminales' : (string) ($turno?->identificador_pc ?? ''),
            'turno_catalogo' => $esJornada ? 'Cierre de jornada' : (string) ($turno?->turno?->nombre ?? ''),
            'turno_horario' => $esJornada ? '' : ($turno?->turno?->etiquetaHorario() ?? ''),
            'fecha_jornada' => $fechaJornadaStr,
            'fecha_jornada_iso' => $fechaJornadaIso,
            'fecha_registro_caja' => $fechaRegistroCaja?->format('d/m/Y H:i') ?? '',
            'fecha_registro_caja_solo_fecha' => $fechaRegistroCaja?->format('d/m/Y') ?? '',
            'fechas_mismo_dia' => $fechasMismoDia,
            'habilitacion_en' => $esJornada
                ? ($jornada?->apertura_en?->format('d/m/Y H:i') ?? '')
                : ($turno?->habilitacion_en?->format('d/m/Y H:i') ?? ''),
            'cierre_en' => $esJornada
                ? ($jornada?->cierre_en?->format('d/m/Y H:i') ?? '')
                : ($turno?->cierre_en?->format('d/m/Y H:i') ?? ''),
            'usuario_habilita' => (string) ($turno?->usuarioHabilitacion?->nombre ?? ''),
            'usuario_habilitado' => (string) ($turno?->usuarioHabilitado?->nombre ?? ''),
            'usuario_registro' => (string) ($r->creousuario?->nombre ?? ''),
            'usuario_cierre' => (string) ($turno?->usuarioCierre?->nombre ?? ''),
            'fecha_emision_comprobante' => now()->format('d/m/Y H:i'),
            'monto_habilitacion' => (float) ($turno?->monto_habilitacion ?? 0),
            'observacion_habilitacion' => $obsHabilitacion['texto_habilitacion'],
            'anulaciones_cierre' => $obsHabilitacion['anulaciones'],
            'totales_turno' => $totalesTurno,
            'totales_dia' => $totalesDia,
            'waitry_order_id_hasta' => (int) ($r->waitry_order_id_hasta ?? 0),
            'numeracion_comprobantes' => is_array($r->numeracion_comprobantes_json) ? $r->numeracion_comprobantes_json : [],
            'rendicion_id' => (int) $r->id,
            'turno_operativo_id' => (int) ($r->turno_operativo_gastronomia_id ?? 0),
            'jornada_gastronomia_id' => (int) ($r->jornada_gastronomia_id ?? 0),
            'codigo_anita' => (string) $r->codigo,
            'caja_nombre' => (string) ($r->caja?->nombre ?? ''),
            'fecha_rendicion' => $fechaRegistroCaja?->format('d/m/Y H:i') ?? '',
            'pv_cae_label' => $this->etiquetaPuntoventa($r->puntoventaCae),
            'pv_caea_label' => $this->etiquetaPuntoventa($r->puntoventaCaea),
            'iniciodelfondo' => (float) $r->iniciodelfondo,
            'lineas_medios' => $lineas,
            'resumen_rendicion' => $resumen,
            'redondeo_rendicion' => (float) $r->totalredondeo,
            'redondeo_invitaciones' => (float) $r->totalredondeoinvitacion,
            'sobrante_faltante' => (float) $r->sobrantefaltante,
            'observacion' => (string) ($r->observacion ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $resumen
     * @return array<string, mixed>
     */
    private function totalesTurnoDesdeCabeceraRendicion(RendicionGastronomiaCaja $r, array $resumen): array
    {
        $nc = round((float) $r->totalnotacredito, 2);

        return [
            'total_ventas' => round((float) $r->totalfactura, 2),
            'total_cobrado' => round((float) $r->totalcobrado, 2),
            'total_invitaciones' => round((float) $r->totalinvitacion, 2),
            'total_notas_credito' => $nc > 0 ? -$nc : 0.0,
            'cantidad_notas_credito' => 0,
            'conciliacion_ok' => ! empty($resumen['cuadra']),
            'diferencia_cobranza' => (float) ($resumen['diferencia'] ?? 0),
            'por_mozo' => [],
            'por_medio_pago' => [],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $totalesTurno
     * @return list<array{nombre: string, codigo: string, monto: float, cotizacion: float, es_nota_credito: bool}>
     */
    private function lineasMediosParaImpresion(RendicionGastronomiaCaja $data, ?array $totalesTurno): array
    {
        $lineas = [];
        foreach ($data->movimientos as $m) {
            $lineas[] = [
                'nombre' => (string) ($m->cuentacaja?->nombre ?? ''),
                'codigo' => (string) ($m->cuentacaja?->codigo ?? ''),
                'monto' => round((float) $m->monto, 2),
                'cotizacion' => round((float) $m->cotizacion, 2),
                'es_nota_credito' => false,
            ];
        }

        $totalesTurno = is_array($totalesTurno) ? $totalesTurno : [];
        $ncCant = (int) ($totalesTurno['cantidad_notas_credito'] ?? 0);
        $ncMonto = array_key_exists('total_notas_credito', $totalesTurno)
            ? round((float) $totalesTurno['total_notas_credito'], 2)
            : (round((float) $data->totalnotacredito, 2) > 0
                ? -round((float) $data->totalnotacredito, 2)
                : 0.0);

        $yaTieneNc = false;
        foreach ($lineas as $l) {
            if (str_contains(mb_strtolower($l['nombre']), 'notas de crédito')) {
                $yaTieneNc = true;
                break;
            }
        }

        $sumMovimientos = 0.0;
        foreach ($data->movimientos as $m) {
            $sumMovimientos += round((float) $m->monto, 2);
        }
        $sumMovimientos = round($sumMovimientos, 2);
        $totalCobrado = round((float) $data->totalcobrado, 2);
        $movimientosYaNetos = abs($sumMovimientos - $totalCobrado) <= self::TOLERANCIA;

        if (! $yaTieneNc && ! $movimientosYaNetos && ($ncCant > 0 || abs($ncMonto) >= 0.005)) {
            $lineas[] = [
                'nombre' => 'Notas de crédito ('.$ncCant.' comp.)',
                'codigo' => '',
                'monto' => $ncMonto,
                'cotizacion' => 1.0,
                'es_nota_credito' => true,
            ];
        }

        return $lineas;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    public function guardar(array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        return DB::transaction(function () use ($cabecera, $movimientos) {
            $tipo = (string) ($cabecera['tipo'] ?? RendicionGastronomiaCaja::TIPO_TURNO);

            if ($tipo === RendicionGastronomiaCaja::TIPO_JORNADA) {
                return $this->guardarRendicionJornada($cabecera, $movimientos);
            }

            $turnoId = (int) ($cabecera['turno_operativo_gastronomia_id'] ?? 0);
            if ($turnoId <= 0) {
                throw new InvalidArgumentException('Debe seleccionar un cierre de turno.');
            }
            if ($this->turnoYaRendido($turnoId)) {
                throw new InvalidArgumentException('El turno operativo #'.$turnoId.' ya fue rendido.');
            }

            $this->exigirMovimientosSiHayCobranzasEnTurno($cabecera, $movimientos);

            $this->exigirRendicionTurnoModificablePorTurnoId($turnoId);

            $empresaId = (int) $cabecera['empresa_id'];
            $fuenteNro = null;

            if (trim((string) ($cabecera['codigo'] ?? '')) === '') {
                $propuesta = $this->proponerNumeracionAnita($empresaId);
                $cabecera['codigo'] = $propuesta['codigo'];
                $fuenteNro = $propuesta['fuente'];
            }

            $cabecera['tipo'] = RendicionGastronomiaCaja::TIPO_TURNO;
            $cabecera = $this->anitaSyncService->enriquecerCabeceraConTracking($cabecera, $fuenteNro);

            $cabecera['creousuario_id'] = (int) Auth::id();
            $rendicion = RendicionGastronomiaCaja::create($cabecera);
            $this->persistirMovimientos($rendicion, $movimientos);
            $rendicion = $rendicion->fresh(['movimientos.cuentacaja']);
            $this->encolarSincronizacionAnitaTrasCommit($rendicion->id);

            return $rendicion;
        });
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function guardarRendicionJornada(array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        $jornadaId = (int) ($cabecera['jornada_gastronomia_id'] ?? 0);
        if ($jornadaId <= 0) {
            throw new InvalidArgumentException('Debe seleccionar una jornada cerrada.');
        }

        $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
        $errores = $this->jornadaPresentacionService->erroresAntesDeRendir($jornada);
        if ($errores !== []) {
            throw new InvalidArgumentException(implode(' ', $errores));
        }

        if ($this->jornadaPresentacionService->jornadaYaRendida($jornadaId)) {
            throw new InvalidArgumentException('La jornada #'.$jornadaId.' ya fue rendida en caja.');
        }

        $marcadores = $this->jornadaPresentacionService->resolverMarcadoresAuditoria($jornada);

        $empresaId = (int) $cabecera['empresa_id'];
        if (trim((string) ($cabecera['codigo'] ?? '')) === '') {
            $cabecera['codigo'] = $this->jornadaPresentacionService->proponerCodigoInterno($empresaId, $jornadaId);
        }

        $cabecera['tipo'] = RendicionGastronomiaCaja::TIPO_JORNADA;
        $cabecera['turno_operativo_gastronomia_id'] = null;
        $cabecera['jornada_gastronomia_id'] = $jornadaId;
        $cabecera['waitry_order_id_hasta'] = (int) ($marcadores['waitry_order_id_hasta'] ?? 0);
        $cabecera['cierre_totem_jornada_gastronomia_id'] = $marcadores['cierre_totem_jornada_gastronomia_id'] ?? null;
        $cabecera['numeracion_comprobantes_json'] = $marcadores['numeracion_comprobantes_json'] ?? null;
        $cabecera['nro_oper_anita'] = null;
        $cabecera['fuente_nro_oper'] = null;
        $cabecera['anita_sincronizado_en'] = null;
        $cabecera['creousuario_id'] = (int) Auth::id();

        $rendicion = RendicionGastronomiaCaja::create($cabecera);
        $this->persistirMovimientos($rendicion, $movimientos);
        $this->persistirInformeZAjustadoDesdeCaja($jornadaId, $cabecera['informe_z_totems'] ?? null);

        DB::afterCommit(function () use ($jornadaId) {
            $this->anitaSyncService->reaplicarTotalZPorPcEnJornada($jornadaId);
        });

        return $rendicion->fresh(['movimientos.cuentacaja', 'jornada', 'cierreTotemJornada']);
    }

    /**
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    public function actualizar(int $id, array $cabecera, array $movimientos): RendicionGastronomiaCaja
    {
        return DB::transaction(function () use ($id, $cabecera, $movimientos) {
            $rendicion = RendicionGastronomiaCaja::query()
                ->with('turnoOperativo')
                ->findOrFail($id);
            $tipo = (string) ($cabecera['tipo'] ?? $rendicion->tipo ?? RendicionGastronomiaCaja::TIPO_TURNO);

            if ($tipo === RendicionGastronomiaCaja::TIPO_JORNADA) {
                $jornadaId = (int) ($cabecera['jornada_gastronomia_id'] ?? 0);
                if ($jornadaId <= 0) {
                    throw new InvalidArgumentException('Debe seleccionar una jornada.');
                }
                if ($this->jornadaPresentacionService->jornadaYaRendida($jornadaId, $id)) {
                    throw new InvalidArgumentException('Otra rendición ya utiliza la jornada #'.$jornadaId.'.');
                }
                $jornada = JornadaGastronomia::query()->findOrFail($jornadaId);
                $marcadores = $this->jornadaPresentacionService->resolverMarcadoresAuditoria($jornada);
                $cabecera['waitry_order_id_hasta'] = (int) ($marcadores['waitry_order_id_hasta'] ?? 0);
                $cabecera['cierre_totem_jornada_gastronomia_id'] = $marcadores['cierre_totem_jornada_gastronomia_id'] ?? null;
                $cabecera['numeracion_comprobantes_json'] = $marcadores['numeracion_comprobantes_json'] ?? null;
                $cabecera['turno_operativo_gastronomia_id'] = null;
            } else {
                $this->exigirRendicionTurnoModificablePorTurnoId(
                    (int) ($rendicion->turno_operativo_gastronomia_id ?? 0),
                );

                $turnoId = (int) ($cabecera['turno_operativo_gastronomia_id'] ?? 0);
                if ($turnoId <= 0) {
                    throw new InvalidArgumentException('Debe seleccionar un cierre de turno.');
                }
                if ($this->turnoYaRendido($turnoId, $id)) {
                    throw new InvalidArgumentException('Otra rendición ya utiliza el turno operativo #'.$turnoId.'.');
                }

                $this->exigirMovimientosSiHayCobranzasEnTurno($cabecera, $movimientos);

                $this->exigirRendicionTurnoModificablePorTurnoId($turnoId);
            }

            $rendicion->update($cabecera);
            RendicionGastronomiaMovimientoCaja::query()
                ->where('rendicion_gastronomia_caja_id', $rendicion->id)
                ->delete();
            $this->persistirMovimientos($rendicion, $movimientos);
            $rendicion = $rendicion->fresh(['movimientos.cuentacaja']);

            if ($tipo === RendicionGastronomiaCaja::TIPO_JORNADA) {
                $jornadaId = (int) ($cabecera['jornada_gastronomia_id'] ?? $rendicion->jornada_gastronomia_id ?? 0);
                $this->persistirInformeZAjustadoDesdeCaja($jornadaId, $cabecera['informe_z_totems'] ?? null);
                DB::afterCommit(function () use ($jornadaId) {
                    $this->anitaSyncService->reaplicarTotalZPorPcEnJornada($jornadaId);
                });
            } else {
                $this->encolarSincronizacionAnitaTrasCommit($rendicion->id);
            }

            return $rendicion;
        });
    }

    public function eliminar(int $id): void
    {
        DB::transaction(function () use ($id) {
            $rendicion = RendicionGastronomiaCaja::query()
                ->with(['movimientos.cuentacaja', 'puntoventaCae', 'puntoventaCaea', 'turnoOperativo.turno', 'turnoOperativo.jornada'])
                ->findOrFail($id);

            $jornadaId = (int) (
                $rendicion->jornada_gastronomia_id
                ?? $rendicion->turnoOperativo?->jornada_gastronomia_id
                ?? 0
            );
            $esPresentacionJornada = $rendicion->esRendicionJornada();

            if (! $esPresentacionJornada) {
                $this->exigirRendicionTurnoModificablePorTurnoId(
                    (int) ($rendicion->turno_operativo_gastronomia_id ?? 0),
                );
            }

            $this->anitaSyncService->sincronizarDespuesDeEliminar($rendicion);

            RendicionGastronomiaMovimientoCaja::query()
                ->where('rendicion_gastronomia_caja_id', $rendicion->id)
                ->delete();
            $rendicion->delete();

            if ($jornadaId <= 0) {
                return;
            }

            if ($esPresentacionJornada) {
                $this->anitaSyncService->resetTotalZPorPcEnJornada($jornadaId);
            }
        });
    }

    /**
     * Sincroniza con Informix solo después del commit MySQL para no dejar rendgastro/rendvalor
     * huérfanos si la transacción ERP revierte tras un fallo del bridge.
     */
    private function encolarSincronizacionAnitaTrasCommit(int $rendicionId): void
    {
        DB::afterCommit(function () use ($rendicionId) {
            $rendicion = RendicionGastronomiaCaja::query()
                ->with([
                    'movimientos.cuentacaja',
                    'puntoventaCae',
                    'puntoventaCaea',
                    'turnoOperativo.turno',
                    'turnoOperativo.jornada',
                ])
                ->find($rendicionId);

            if ($rendicion === null) {
                return;
            }

            $this->anitaSyncService->sincronizarDespuesDeGuardar($rendicion);
        });
    }

    private function exigirRendicionTurnoModificablePorTurnoId(int $turnoOperativoId): void
    {
        if ($turnoOperativoId <= 0) {
            return;
        }

        $jornadaId = (int) TurnoOperativoGastronomia::query()
            ->whereKey($turnoOperativoId)
            ->value('jornada_gastronomia_id');

        $this->jornadaPresentacionService->exigirRendicionTurnoModificable($jornadaId);
    }

    /**
     * @param  list<array{cuentacaja_id?:int, monto?:float, cotizacion?:float}>  $raw
     * @return list<array{cuentacaja_id:int, monto:float, cotizacion:float}>
     */
    public function normalizarMovimientosRequest(array $raw): array
    {
        $out = [];
        foreach ($raw as $row) {
            $cuentaId = (int) ($row['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $monto = round((float) ($row['monto'] ?? 0), 2);
            $cotizacion = round((float) ($row['cotizacion'] ?? 1), 2);
            if ($cotizacion <= 0) {
                $cotizacion = 1.0;
            }
            $out[] = [
                'cuentacaja_id' => $cuentaId,
                'monto' => $monto,
                'cotizacion' => $cotizacion,
            ];
        }

        return $out;
    }

    /**
     * Fecha/hora de presentación en caja: se fija al crear (now) y no se modifica en edición.
     */
    public function resolverFecharendicionInmutable(?int $rendicionId = null): string
    {
        if ($rendicionId !== null && $rendicionId > 0) {
            $existente = RendicionGastronomiaCaja::query()->find($rendicionId);
            if ($existente?->fecharendicion !== null) {
                return $existente->fecharendicion->format('Y-m-d H:i:s');
            }
        }

        return now()->format('Y-m-d H:i:s');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function cabeceraDesdeRequest(array $validated, ?int $rendicionId = null): array
    {
        $tipo = (string) ($validated['tipo'] ?? RendicionGastronomiaCaja::TIPO_TURNO);

        $cabecera = [
            'tipo' => $tipo,
            'codigo' => trim((string) ($validated['codigo'] ?? '')),
            'empresa_id' => (int) $validated['empresa_id'],
            'puntoventa_cae_id' => (int) $validated['puntoventa_cae_id'],
            'puntoventa_caea_id' => (int) $validated['puntoventa_caea_id'],
            'caja_id' => (int) $validated['caja_id'],
            'fecharendicion' => $this->resolverFecharendicionInmutable($rendicionId),
            'iniciodelfondo' => round((float) $validated['iniciodelfondo'], 2),
            'totalfactura' => round((float) $validated['totalfactura'], 2),
            'totalcobrado' => round((float) $validated['totalcobrado'], 2),
            'totalinvitacion' => round((float) $validated['totalinvitacion'], 2),
            'totalnotacredito' => round((float) $validated['totalnotacredito'], 2),
            'totalredondeo' => round((float) $validated['totalredondeo'], 2),
            'totalredondeoinvitacion' => round((float) $validated['totalredondeoinvitacion'], 2),
            'sobrantefaltante' => round((float) $validated['sobrantefaltante'], 2),
            'observacion' => isset($validated['observacion']) ? trim((string) $validated['observacion']) : null,
        ];

        if ($tipo === RendicionGastronomiaCaja::TIPO_JORNADA) {
            $cabecera['jornada_gastronomia_id'] = (int) ($validated['jornada_gastronomia_id'] ?? 0);
            $cabecera['turno_operativo_gastronomia_id'] = null;
            if (! empty($validated['informe_z_totems']) && is_array($validated['informe_z_totems'])) {
                $cabecera['informe_z_totems'] = $validated['informe_z_totems'];
            }
        } else {
            $cabecera['turno_operativo_gastronomia_id'] = (int) ($validated['turno_operativo_gastronomia_id'] ?? 0);
            $cabecera['jornada_gastronomia_id'] = null;
        }

        return $cabecera;
    }

    /**
     * @param  list<array<string, mixed>>|null  $totemsPayload
     */
    private function persistirInformeZAjustadoDesdeCaja(int $jornadaId, ?array $totemsPayload): void
    {
        if ($jornadaId <= 0 || $totemsPayload === null || $totemsPayload === []) {
            return;
        }

        $this->informeZService->guardarInformeZAjustadoDesdeCaja($jornadaId, ['totems' => $totemsPayload]);
    }

    /**
     * @return list<string>
     */
    public function erroresAntesDeEliminar(RendicionGastronomiaCaja $rendicion): array
    {
        return [];
    }

    private function turnoYaRendido(int $turnoId, ?int $exceptoRendicionId = null): bool
    {
        return RendicionGastronomiaCaja::query()
            ->where('turno_operativo_gastronomia_id', $turnoId)
            ->when($exceptoRendicionId, fn ($q) => $q->where('id', '!=', $exceptoRendicionId))
            ->exists();
    }

    /**
     * Medios de cobro netos del turno (NC descontadas en su cuenta de caja).
     *
     * @param  array<string, mixed>  $totales  Resultado de GastronomiaTurnoOperativoTotalesSupport::calcular()
     * @return list<array{cuentacaja_id:int, codigo:string, nombre:string, monto:float, esperado?:float, cotizacion:float, es_nota_credito?:bool, desde_contado_cierre?:bool}>
     */
    private function movimientosDesdeTotales(array $totales): array
    {
        $movimientos = [];
        foreach ($totales['por_medio_pago'] ?? [] as $p) {
            $cuentaId = (int) ($p['cuentacaja_id'] ?? 0);
            if ($cuentaId <= 0) {
                continue;
            }
            $esperado = round((float) ($p['esperado'] ?? $p['total'] ?? 0), 2);
            $desdeContadoCierre = array_key_exists('contado', $p);
            $monto = $desdeContadoCierre
                ? round((float) $p['contado'], 2)
                : $esperado;
            $movimientos[] = [
                'cuentacaja_id' => $cuentaId,
                'codigo' => (string) ($p['codigo'] ?? ''),
                'nombre' => (string) ($p['nombre'] ?? $p['codigo'] ?? ''),
                'monto' => $monto,
                'esperado' => $esperado,
                'cotizacion' => 1.0,
                'desde_contado_cierre' => $desdeContadoCierre,
            ];
        }

        return $movimientos;
    }

    /**
     * @param  list<array{monto:float, cotizacion?:float}>  $movimientos
     * @return array{total_grilla: float, total_ajustado: float, diferencia: float, cuadra: bool, mensaje: string}
     */
    private function armarResumenDiferencias(
        float $totalCobradoTurno,
        array $movimientos,
        float $redondeo,
        float $redondeoInvitacion,
        float $sobranteFaltante,
    ): array {
        $totalGrilla = 0.0;
        foreach ($movimientos as $m) {
            $totalGrilla += round((float) ($m['monto'] ?? 0), 2);
        }
        $totalGrilla = round($totalGrilla, 2);
        $totalAjustado = round($totalGrilla + $redondeo + $redondeoInvitacion + $sobranteFaltante, 2);
        $diferencia = round($totalAjustado - $totalCobradoTurno, 2);
        $cuadra = abs($diferencia) <= self::TOLERANCIA;

        $mensaje = $cuadra
            ? 'La rendición cuadra con el total cobrado del cierre de turno.'
            : ($diferencia > 0
                ? 'Sobra $'.number_format(abs($diferencia), 2, ',', '.').' respecto al cobrado del turno.'
                : 'Falta $'.number_format(abs($diferencia), 2, ',', '.').' respecto al cobrado del turno.');

        return [
            'total_grilla' => $totalGrilla,
            'total_ajustado' => $totalAjustado,
            'diferencia' => $diferencia,
            'cuadra' => $cuadra,
            'mensaje' => $mensaje,
        ];
    }

    private function etiquetaTurnoPendiente(TurnoOperativoGastronomia $turno): string
    {
        $n = (int) ($turno->numero_cierre ?? 0);
        $ref = $n > 0 ? 'Cierre #'.$n : 'Op. #'.$turno->id;

        return $ref
            .' — '.($turno->turno?->nombre ?? '')
            .' — '.($turno->identificador_pc ?? '')
            .' — cierre '.($turno->cierre_en?->format('d/m/Y H:i') ?? '');
    }

    private function etiquetaPuntoventa($pv): string
    {
        if ($pv === null) {
            return '—';
        }

        $codigo = trim((string) ($pv->codigo ?? ''));
        $nombre = trim((string) ($pv->nombre ?? ''));

        if ($codigo !== '' && $nombre !== '') {
            return $codigo.' — '.$nombre;
        }

        return $codigo !== '' ? $codigo : ($nombre !== '' ? $nombre : '—');
    }

    /**
     * @param  list<array{cuentacaja_id:int, monto:float, cotizacion:float}>  $movimientos
     */
    private function persistirMovimientos(RendicionGastronomiaCaja $rendicion, array $movimientos): void
    {
        foreach ($movimientos as $row) {
            RendicionGastronomiaMovimientoCaja::create([
                'rendicion_gastronomia_caja_id' => $rendicion->id,
                'cuentacaja_id' => $row['cuentacaja_id'],
                'monto' => $row['monto'],
                'cotizacion' => $row['cotizacion'],
            ]);
        }
    }

    /**
     * Turnos sin cobranzas (p. ej. solo invitaciones $0,01, habilitación sin ventas o solo NC) pueden rendirse sin filas de medio.
     *
     * @param  array<string, mixed>  $cabecera
     * @param  list<array{cuentacaja_id?:int, es_nota_credito?:bool, monto?:float, cotizacion?:float}>  $movimientos
     */
    private function exigirMovimientosSiHayCobranzasEnTurno(array $cabecera, array $movimientos): void
    {
        if ($this->tieneMovimientosMedioPago($movimientos)) {
            return;
        }

        $totalCobrado = round((float) ($cabecera['totalcobrado'] ?? 0), 2);

        if ($totalCobrado > self::TOLERANCIA) {
            throw new InvalidArgumentException(
                'El cierre de turno tiene cobranzas registradas pero no hay medios de pago en la rendición. '
                .'Vuelva a cargar el cierre (Consultar) o revise los importes.'
            );
        }
    }

    /**
     * Filas reales de medio de cobro (excluye la fila virtual de notas de crédito).
     *
     * @param  list<array{cuentacaja_id?:int, es_nota_credito?:bool}>  $movimientos
     */
    private function tieneMovimientosMedioPago(array $movimientos): bool
    {
        foreach ($movimientos as $row) {
            if (! empty($row['es_nota_credito'])) {
                continue;
            }
            if ((int) ($row['cuentacaja_id'] ?? 0) > 0) {
                return true;
            }
        }

        return false;
    }
}
