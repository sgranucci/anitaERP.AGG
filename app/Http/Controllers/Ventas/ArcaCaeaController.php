<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Jobs\Ventas\InformarArcaCaeaPeriodoJob;
use App\Models\Ventas\ArcaCaea;
use App\Services\Arca\ArcaCaeaAnitaSyncService;
use App\Services\Arca\ArcaCaeaPresentacionManualService;
use App\Services\Arca\ArcaCaeaPresentacionService;
use App\Services\Arca\ArcaCaeaQuincenalOrquestadorService;
use App\Support\Ventas\ArcaCaeaInformeColaSupport;
use App\Support\Ventas\ArcaCaeaInformeUiSupport;
use App\Support\Ventas\CaeaQuincenaSupport;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ArcaCaeaController extends Controller
{
    public function __construct(
        private ArcaCaeaQuincenalOrquestadorService $orquestador,
        private ArcaCaeaAnitaSyncService $anitaSync,
        private ArcaCaeaPresentacionService $presentacionService,
        private ArcaCaeaPresentacionManualService $presentacionManualService,
    ) {}

    public function index(Request $request)
    {
        can('listar-arca-caea');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $filtrosIndex = $this->resolverFiltrosIndex($request);
        $empresaId = $filtrosIndex['empresa_id'];
        $periodo = $filtrosIndex['periodo'];
        $estado = $filtrosIndex['estado'];
        // Solo reconsultar FECompUltimoAutorizado tras informar/actualizar contadores, no al filtrar.
        $forzarSyncArca = $request->boolean('sync_arca');

        $query = ArcaCaea::query()
            ->with(['empresa', 'solicitadoPor'])
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->orderByDesc('periodo')
            ->orderByDesc('orden')
            ->orderByDesc('id');

        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }
        if ($periodo > 0) {
            $query->where('periodo', $periodo);
        }
        if ($estado !== '') {
            $query->where('estado', $estado);
        }

        $registros = $query->paginate(30)->appends($this->filtrosIndexParaQuery($filtrosIndex));

        $filasMeta = [];
        /** @var array<int, list<ArcaCaea>> $porEmpresaSync */
        $porEmpresaSync = [];
        foreach ($registros as $registro) {
            if (! $registro->estaAutorizado()) {
                continue;
            }
            $resumen = is_array($registro->informe_resumen) ? $registro->informe_resumen : null;
            if ($forzarSyncArca && ($resumen === null || $this->periodoNecesitaSyncArca($resumen ?? []))) {
                // Solo la calculadora / post-informe: consultar ARCA.
                $porEmpresaSync[(int) $registro->empresa_id][] = $registro;
            }
        }

        /** @var array<int, array<string, mixed>> $resumenesSync */
        $resumenesSync = [];
        foreach ($porEmpresaSync as $regsEmpresa) {
            foreach ($this->presentacionService->actualizarResumenesEmpresa($regsEmpresa, null, true) as $id => $resumen) {
                $resumenesSync[(int) $id] = $resumen;
            }
        }

        foreach ($registros as $registro) {
            if (! $registro->estaAutorizado()) {
                continue;
            }
            $registro->loadMissing('empresa');
            $resumen = $resumenesSync[(int) $registro->id]
                ?? (is_array($registro->informe_resumen) ? $registro->informe_resumen : null);
            if ($resumen === null) {
                $resumen = [];
            }
            $procesoActivo = ArcaCaeaInformeColaSupport::estaActivo((int) $registro->id);
            $progresoActivo = $procesoActivo
                ? ArcaCaeaInformeColaSupport::progreso((int) $registro->id)
                : null;
            $puedePresentar = ! $procesoActivo
                && ArcaCaeaInformeUiSupport::puedePresentarAhora($resumen);
            $leyenda = $procesoActivo
                ? ArcaCaeaInformeColaSupport::leyendaProcesoActivo($progresoActivo)
                : ArcaCaeaInformeUiSupport::leyendaFaltante($resumen);
            $filasMeta[$registro->id] = [
                'resumen' => $resumen,
                'proceso_activo' => $procesoActivo,
                'progreso' => $progresoActivo,
                'puede_presentar' => $puedePresentar,
                'leyenda' => $leyenda,
                'titulo_overlay' => ArcaCaeaInformeUiSupport::tituloProcesando($registro),
                'badge' => $procesoActivo
                    ? 'procesando'
                    : ArcaCaeaInformeUiSupport::badgeInformeEstado($registro->informe_estado, $resumen),
            ];
        }

        $empresas = $user->usuario_empresas->sortBy('nombre');
        $quincenasVentana = CaeaQuincenaSupport::quincenasEnVentanaSolicitud();
        $puedeSolicitar = can('solicitar-arca-caea', false);
        $puedeInformar = can('informar-arca-caea', false);
        $puedeGrabarAnita = $puedeSolicitar && $this->anitaSync->estaHabilitado();
        $filtrosQuery = $this->filtrosIndexParaQuery($filtrosIndex);

        return view('ventas.arca_caea.index', compact(
            'registros',
            'empresas',
            'empresaId',
            'periodo',
            'estado',
            'filtrosQuery',
            'quincenasVentana',
            'puedeSolicitar',
            'puedeInformar',
            'puedeGrabarAnita',
            'filasMeta',
        ));
    }

    public function show(Request $request, int $id)
    {
        can('ver-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $registro->load(['empresa', 'solicitadoPor', 'informadoPor']);
        $puedeReintentar = can('solicitar-arca-caea', false) && ! $registro->estaAutorizado();
        $puedeGrabarAnita = can('solicitar-arca-caea', false)
            && $this->anitaSync->estaHabilitado()
            && $registro->estaAutorizado();
        $puedeInformar = can('informar-arca-caea', false) && $registro->estaAutorizado();
        $resumenInforme = is_array($registro->informe_resumen) ? $registro->informe_resumen : [];
        $erroresInforme = [];
        if ($registro->estaAutorizado()) {
            // Modal: pintar con resumen persistido (el recálculo local pega Anita y cuelga la UI).
            $erroresInforme = $this->presentacionService->listarErroresInforme($registro, 30);
        }
        $leyendaInforme = ArcaCaeaInformeUiSupport::leyendaFaltante(is_array($resumenInforme) ? $resumenInforme : null);
        $procesoActivo = $registro->estaAutorizado()
            && ArcaCaeaInformeColaSupport::estaActivo((int) $registro->id);
        $progresoActivo = $procesoActivo
            ? ArcaCaeaInformeColaSupport::progreso((int) $registro->id)
            : null;
        if ($procesoActivo) {
            $leyendaInforme = ArcaCaeaInformeColaSupport::leyendaProcesoActivo($progresoActivo);
        }
        $puedePresentar = ! $procesoActivo
            && ArcaCaeaInformeUiSupport::puedePresentarAhora(is_array($resumenInforme) ? $resumenInforme : null);
        $erroresAgrupados = $registro->estaAutorizado()
            ? $this->presentacionService->agruparErroresInforme($registro, 15)
            : [];

        if ($request->ajax()) {
            $filtrosQuery = $this->filtrosIndexParaQuery($this->resolverFiltrosIndex($request));

            return view('ventas.arca_caea.partials.detalle_contenido', compact(
                'registro',
                'puedeReintentar',
                'puedeGrabarAnita',
                'puedeInformar',
                'puedePresentar',
                'procesoActivo',
                'progresoActivo',
                'resumenInforme',
                'leyendaInforme',
                'erroresInforme',
                'erroresAgrupados',
                'filtrosQuery',
            ));
        }

        return redirect()->route('arca_caea', $this->filtrosIndexDesdeRequest($request));
    }

    public function solicitar(Request $request)
    {
        can('solicitar-arca-caea');

        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($id) => (int) $id)->all();

        $data = $request->validate([
            'empresa_id' => ['required', 'integer', Rule::in($empresaIdsPermitidas)],
            'periodo' => ['required', 'integer', 'min:200001', 'max:299912'],
            'orden' => ['required', 'integer', Rule::in([1, 2])],
            'solo_consultar' => ['nullable', 'boolean'],
        ]);

        $resultado = $this->orquestador->solicitarYGuardar(
            (int) $data['empresa_id'],
            (int) $data['periodo'],
            (int) $data['orden'],
            ArcaCaea::ORIGEN_MANUAL,
            (int) $user->id,
            (bool) ($data['solo_consultar'] ?? false),
        );

        $filtros = $this->filtrosIndexDesdeRequest($request);
        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea', $filtros)
            ->with('mensaje-error', $resultado['mensaje']);
    }

    public function reintentar(Request $request, int $id)
    {
        can('solicitar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);

        $resultado = $this->orquestador->solicitarYGuardar(
            (int) $registro->empresa_id,
            (int) $registro->periodo,
            (int) $registro->orden,
            ArcaCaea::ORIGEN_MANUAL,
            (int) Auth::id(),
            false,
        );

        $filtros = $this->filtrosIndexDesdeRequest($request);
        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea', $filtros)
            ->with('mensaje-error', $resultado['mensaje']);
    }

    public function estadoInforme(int $id)
    {
        can('listar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $resumen = is_array($registro->informe_resumen) ? $registro->informe_resumen : [];
        $puedeBase = $registro->estaAutorizado()
            && ArcaCaeaInformeUiSupport::puedePresentarAhora($resumen);
        $estado = ArcaCaeaInformeColaSupport::estadoUi((int) $registro->id, $puedeBase);

        return response()->json($estado);
    }

    public function informar(Request $request, int $id)
    {
        can('informar-arca-caea');

        Log::info('arca.caea.informe.post', [
            'arca_caea_id' => $id,
            'usuario_id' => (int) Auth::id(),
        ]);

        $registro = $this->resolverRegistroPermitido($id);
        $registro->loadMissing('empresa');
        $data = $request->validate([
            'solo_errores' => ['nullable', 'boolean'],
        ]);

        $filtros = $this->filtrosIndexDesdeRequest($request);
        $usuarioId = (int) Auth::id();
        $soloErrores = (bool) ($data['solo_errores'] ?? false);

        if (! $registro->estaAutorizado()) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje-error', 'El CAEA no está autorizado; no se puede informar comprobantes.');
        }

        $arcaCaeaId = (int) $registro->id;
        $yaEnCola = ArcaCaeaInformeColaSupport::hayJobPendienteEnCola($arcaCaeaId);
        if (ArcaCaeaInformeColaSupport::estaActivo($arcaCaeaId) && $yaEnCola) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje-error', 'Ya hay una presentación CAEA en segundo plano para esta quincena. Esperá a que termine (mail de aviso) antes de volver a encolar.');
        }
        if (ArcaCaeaInformeColaSupport::estaActivo($arcaCaeaId) && ! $yaEnCola) {
            ArcaCaeaInformeColaSupport::liberar($arcaCaeaId);
        }

        $usuario = Auth::user();
        $email = trim((string) ($usuario->email ?? ''));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje-error', 'Tu usuario no tiene un email válido; no se puede avisar el resultado del informe CAEA.');
        }

        try {
            ArcaCaeaInformeColaSupport::liberarLocksUnique($arcaCaeaId);
            ArcaCaeaInformeColaSupport::marcarActivo($arcaCaeaId, $usuarioId);
            // Permitir mail del nuevo proceso aunque un intento anterior (p. ej. 704) ya hubiera avisado.
            Cache::forget('arca-caea-informe-mail-'.$arcaCaeaId.'-'.$usuarioId);
            Bus::dispatch(new InformarArcaCaeaPeriodoJob($arcaCaeaId, $usuarioId, $soloErrores));
        } catch (\Throwable $e) {
            ArcaCaeaInformeColaSupport::liberar((int) $registro->id);
            Log::error('arca.caea.informe.dispatch_fallo', [
                'arca_caea_id' => $registro->id,
                'usuario_id' => $usuarioId,
                'msg' => $e->getMessage(),
            ]);

            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje-error', 'No se pudo encolar la presentación CAEA: '.$e->getMessage());
        }

        $quincena = ArcaCaeaInformeUiSupport::etiquetaQuincena($registro);
        $empresa = $registro->empresa->nombre ?? '';
        $mensaje = 'La presentación CAEA de '.$empresa.' ('.$quincena.') se encoló como un solo proceso en segundo plano '
            .'(todas las facturas pendientes de la quincena, no un job por comprobante). '
            .'Al terminar o frenarse recibirás un mail en '.$email.' indicando hasta dónde llegó y el motivo. '
            .'Podés seguir trabajando; usá el ícono de calculadora para refrescar contadores.';

        Log::info('arca.caea.informe.encolado', [
            'arca_caea_id' => $registro->id,
            'usuario_id' => $usuarioId,
            'solo_errores' => $soloErrores,
            'email' => $email,
        ]);

        return redirect()
            ->route('arca_caea', $filtros)
            ->with('mensaje', $mensaje);
    }

    public function actualizarResumen(Request $request, int $id)
    {
        can('informar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $filtros = $this->filtrosIndexDesdeRequest($request, true);
        if (! $registro->estaAutorizado()) {
            return redirect()
                ->route('arca_caea', $this->filtrosIndexDesdeRequest($request))
                ->with('mensaje-error', 'El CAEA no está autorizado.');
        }

        $this->presentacionService->actualizarResumenPeriodo($registro, (int) Auth::id());

        return redirect()
            ->route('arca_caea', $filtros)
            ->with('mensaje', 'Contadores actualizados consultando último autorizado en ARCA por tipo de comprobante.');
    }

    public function grabarAnita(Request $request, int $id)
    {
        can('solicitar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $filtros = $this->filtrosIndexDesdeRequest($request);

        try {
            $resultado = $this->anitaSync->grabarEnAnita($registro);
        } catch (\Throwable $e) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje-error', 'No se pudo grabar en Anita: '.$e->getMessage());
        }

        if ($resultado['ok']) {
            return redirect()
                ->route('arca_caea', $filtros)
                ->with('mensaje', $resultado['mensaje']);
        }

        return redirect()
            ->route('arca_caea', $filtros)
            ->with('mensaje-error', $resultado['mensaje']);
    }

    /**
     * Lista próximos comprobantes a presentar (último ARCA+1) con fuente ERP/Anita.
     */
    public function proximosManual(int $id)
    {
        can('informar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        if (! $registro->estaAutorizado()) {
            return response()->json(['ok' => false, 'mensaje' => 'El CAEA no está autorizado.'], 422);
        }

        try {
            $pendientes = $this->presentacionManualService->listarProximosPendientes($registro);
        } catch (\Throwable $e) {
            Log::warning('arca.caea.manual.proximos_fallo', [
                'arca_caea_id' => $id,
                'msg' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'pendientes' => $pendientes,
        ]);
    }

    /**
     * Previsualiza un comprobante a presentar (ERP o Anita).
     */
    public function previsualizarManual(Request $request, int $id)
    {
        can('informar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $data = $request->validate([
            'pto_vta' => ['required', 'integer', 'min:1'],
            'tipo_afip' => ['required', 'integer', 'min:1'],
            'numero' => ['required', 'integer', 'min:1'],
            'tipo_anita' => ['nullable', 'string', 'max:10'],
            'letra' => ['nullable', 'string', 'max:1'],
        ]);

        $resultado = $this->presentacionManualService->previsualizar(
            $registro,
            (int) $data['pto_vta'],
            (int) $data['tipo_afip'],
            (int) $data['numero'],
            $data['tipo_anita'] ?? null,
            $data['letra'] ?? null,
        );

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    /**
     * Presenta un comprobante CAEA (ERP primero, fallback Anita).
     */
    public function informarUnoManual(Request $request, int $id)
    {
        can('informar-arca-caea');

        $registro = $this->resolverRegistroPermitido($id);
        $data = $request->validate([
            'pto_vta' => ['required', 'integer', 'min:1'],
            'tipo_afip' => ['required', 'integer', 'min:1'],
            'numero' => ['required', 'integer', 'min:1'],
            'tipo_anita' => ['nullable', 'string', 'max:10'],
            'letra' => ['nullable', 'string', 'max:1'],
        ]);

        if (ArcaCaeaInformeColaSupport::estaActivo((int) $registro->id)) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'Hay una presentación por lote en segundo plano. Espere a que termine.',
            ], 422);
        }

        try {
            $resultado = $this->presentacionManualService->informarUno(
                $registro,
                (int) $data['pto_vta'],
                (int) $data['tipo_afip'],
                (int) $data['numero'],
                $data['tipo_anita'] ?? null,
                $data['letra'] ?? null,
            );
        } catch (\Throwable $e) {
            Log::error('arca.caea.manual.informar_fallo', [
                'arca_caea_id' => $id,
                'msg' => $e->getMessage(),
            ]);

            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }

        return response()->json($resultado, ($resultado['ok'] ?? false) ? 200 : 422);
    }

    private function resolverRegistroPermitido(int $id): ArcaCaea
    {
        $user = Auth::user();
        $user->loadMissing('usuario_empresas');
        $empresaIdsPermitidas = $user->usuario_empresas->pluck('id')->map(fn ($i) => (int) $i)->all();

        return ArcaCaea::query()
            ->whereIn('empresa_id', $empresaIdsPermitidas)
            ->findOrFail($id);
    }

    /**
     * @return array{empresa_id:int, periodo:int, estado:string}
     */
    private function resolverFiltrosIndex(Request $request): array
    {
        return [
            'empresa_id' => (int) $request->get('empresa_id', 0),
            'periodo' => (int) $request->get('periodo', 0),
            'estado' => trim((string) $request->get('estado', '')),
        ];
    }

    /**
     * Query string del index (sin sync_arca: ese flag es de un solo uso tras informar).
     *
     * @param  array{empresa_id:int, periodo:int, estado:string}  $filtros
     * @return array<string, int|string>
     */
    private function filtrosIndexParaQuery(array $filtros): array
    {
        $out = [];
        if ((int) ($filtros['empresa_id'] ?? 0) > 0) {
            $out['empresa_id'] = (int) $filtros['empresa_id'];
        }
        if ((int) ($filtros['periodo'] ?? 0) > 0) {
            $out['periodo'] = (int) $filtros['periodo'];
        }
        if (trim((string) ($filtros['estado'] ?? '')) !== '') {
            $out['estado'] = trim((string) $filtros['estado']);
        }

        return $out;
    }

    /**
     * Conserva filtros del index enviados como hidden en los forms de acción.
     *
     * @return array<string, int|string>
     */
    private function filtrosIndexDesdeRequest(Request $request, bool $conSyncArca = false): array
    {
        $out = $this->filtrosIndexParaQuery($this->resolverFiltrosIndex($request));
        if ($conSyncArca) {
            $out['sync_arca'] = 1;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $resumen
     */
    private function periodoNecesitaSyncArca(array $resumen): bool
    {
        return ArcaCaeaInformeUiSupport::tienePendienteInforme($resumen)
            || (int) ($resumen['bloqueados_hueco'] ?? 0) > 0;
    }

    /**
     * Resumen ya tiene contadores locales pero el estado guardado no refleja la realidad
     * (p. ej. quincena sin comprobantes marcada como pendiente).
     *
     * @param  array<string, mixed>  $resumen
     */
    private function periodoEstadoDesactualizado(ArcaCaea $registro, array $resumen): bool
    {
        $total = (int) ($resumen['total'] ?? -1);
        if ($total === 0 && $registro->informe_estado !== ArcaCaea::INFORME_ESTADO_OK) {
            return true;
        }

        if ($resumen === [] || ! array_key_exists('informables_ahora', $resumen)) {
            return true;
        }

        $esperable = ArcaCaeaInformeUiSupport::badgeInformeEstado($registro->informe_estado, $resumen);
        if ($esperable === 'ok' && $registro->informe_estado !== ArcaCaea::INFORME_ESTADO_OK
            && (int) ($resumen['pendientes'] ?? 0) === 0 && (int) ($resumen['errores'] ?? 0) === 0) {
            return true;
        }

        return false;
    }
}
