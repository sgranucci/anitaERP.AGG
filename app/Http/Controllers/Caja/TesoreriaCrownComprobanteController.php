<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Services\Caja\TesoreriaCrownAvisoGenerador;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownComprobanteLinea;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Models\Caja\TesoreriaCrownCierreFiscal;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownCierreFiscalReader;
use App\Support\Ventas\MozoGastronomiaAplicacionSupport;
use App\Models\Ventas\MozoGastronomia;
use App\Support\Caja\TesoreriaCrownCierreFiscalSync;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownDiaCajeroSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownMovimientoListadoColumnas;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownPxsolSupport;
use App\Support\Caja\TesoreriaCrownRendicionCampos;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Caja\TesoreriaCrownMovimientoListadoFiltros;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownComprobanteController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $salaId = $sala ? (int) $sala->id : 0;
        $armado = TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            TesoreriaCrownMovimientoListadoColumnas::RECURSO,
            'tesoreria_crown_movimiento',
            TesoreriaCrownMovimientoListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [TesoreriaCrownMovimientoListadoFiltros::class, 'camposOrdenables'],
            [TesoreriaCrownMovimientoListadoFiltros::class, 'camposQbe'],
            [TesoreriaCrownMovimientoListadoFiltros::class, 'normalizarOperadorPublico'],
            [TesoreriaCrownMovimientoListadoFiltros::class, 'paraQueryString'],
        );
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }
        $comprobantes = TesoreriaCrownMovimientoListadoFiltros::consulta($armado['filtros'])
            ->paginate(15)
            ->appends($armado['filtrosQuery']);

        return view('caja.tesoreria_crown.movimiento.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'comprobantes' => $comprobantes,
            'tieneCriterios' => TesoreriaCrownMovimientoListadoFiltros::tieneCriteriosAplicados($armado['filtros']),
        ]));
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());
        $salaId = $sala ? (int) $sala->id : 0;

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            TesoreriaCrownMovimientoListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            TesoreriaCrownMovimientoListadoColumnas::RECURSO,
            fn (array $filtros) => TesoreriaCrownMovimientoListadoFiltros::consulta($filtros),
            [TesoreriaCrownMovimientoListadoColumnas::class, 'valorCelda'],
            [TesoreriaCrownMovimientoListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            TesoreriaCrownMovimientoListadoColumnas::RECURSO,
            'tesoreria_crown_movimiento',
            TesoreriaCrownMovimientoListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $request->input('sala_id', 0)),
            [TesoreriaCrownMovimientoListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function eliminarVistaListado(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');
        $ok = ListadoVistaSupport::eliminar($id, TesoreriaCrownMovimientoListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('tesoreria_crown_movimiento', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'mensaje-error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            TesoreriaCrownMovimientoListadoColumnas::RECURSO,
            'tesoreria_crown_movimiento',
            TesoreriaCrownMovimientoListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $request->input('sala_id', 0)),
            [TesoreriaCrownMovimientoListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            TesoreriaCrownMovimientoListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(TesoreriaCrownMovimientoListadoColumnas::catalogoActivo())
        );

        return redirect()->route('tesoreria_crown_movimiento', $request->query())->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDe(Request $request, int $salaId): array
    {
        $filtros = TesoreriaCrownMovimientoListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = $salaId;

        return $filtros;
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownMovimientoListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $filas = TesoreriaCrownMovimientoListadoFiltros::consulta($filtros)->get();
        foreach ($filas as $fila) {
            $fila->nombreempresa = $fila->empresa->nombre ?? ($sala->nombre ?? '');
        }

        $tabla = [];
        foreach ($filas as $fila) {
            $tabla[] = [
                TesoreriaCrownNumeradorSupport::visible($fila->numero),
                $fila->jornada?->fecha?->format('d/m/Y') ?? '',
                TesoreriaCrownComprobanteSupport::tipo($fila->tipo).($fila->revierte_id ? ' (reversa)' : ''),
                trim(($fila->asignacion->deposito->codigo ?? '').' '.($fila->asignacion->deposito->nombre ?? '')),
                $fila->cajeroOperativo()?->nombre ?? '',
                TesoreriaCrownComprobanteSupport::estado($fila->estado),
                $fila->entrega_at?->format('d/m/Y H:i') ?? '',
                $fila->recibe_at?->format('d/m/Y H:i') ?? '',
            ];
        }

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Habilitaciones y rendiciones',
            'subtitulo' => $sala ? trim($sala->codigo.' — '.$sala->nombre) : '',
            'archivo' => 'habilitaciones_tesoreria_crown',
            'titulo_corto' => 'Habilitaciones',
            'columnas' => [
                ['label' => 'Número', 'ancho' => 14, 'texto' => true],
                ['label' => 'Fecha', 'ancho' => 14, 'texto' => true],
                ['label' => 'Tipo', 'ancho' => 22, 'texto' => true],
                ['label' => 'Caja', 'ancho' => 28, 'texto' => true],
                ['label' => 'Cajero', 'ancho' => 28, 'texto' => true],
                ['label' => 'Estado', 'ancho' => 22, 'texto' => true],
                ['label' => 'Entrega', 'ancho' => 18, 'texto' => true],
                ['label' => 'Recibe', 'ancho' => 18, 'texto' => true],
            ],
            'filas' => $tabla,
            'coleccion' => $filas,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route(
            'tesoreria_crown_movimiento',
            TesoreriaCrownMovimientoListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');

        $tipo = (string) $request->input('tipo');
        if (! isset(TesoreriaCrownComprobanteSupport::TIPOS[$tipo])) {
            abort(404);
        }

        $jornadaId = (int) $request->input('jornada_id');
        $cajeroId = (int) $request->input('cajero_crown_id');
        // Compat: formularios viejos todavía mandan asignacion_id.
        if ($cajeroId <= 0 && (int) $request->input('asignacion_id') > 0) {
            $asignacion = $this->asignacionEnAlcance((int) $request->input('asignacion_id'));
            $jornadaId = (int) $asignacion->jornada_id;
            $cajeroId = (int) $asignacion->cajero_crown_id;
        }
        $jornada = TesoreriaCrownJornada::query()->findOrFail($jornadaId);
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);
        $cajero = CajeroCrown::query()
            ->where('sala_id', $jornada->sala_id)
            ->where('activo', true)
            ->find($cajeroId);
        if (! $cajero) {
            return redirect()->route('ver_tesoreria_crown_jornada', $jornada->id)
                ->with('mensaje-error', 'El cajero no es de esta sala o está inactivo.');
        }
        if ($error = $this->errorJornada($jornada)
            ?? $this->errorCajeroCerrado($jornada, $cajero)
            ?? $this->errorAltaCajero($jornada, $cajero, $tipo)) {
            return redirect()->route('ver_tesoreria_crown_jornada', $jornada->id)->with('mensaje-error', $error);
        }

        $comprobante = DB::transaction(function () use ($jornada, $cajero, $tipo) {
            return TesoreriaCrownComprobante::query()->create([
                'sala_id' => $jornada->sala_id,
                'empresa_id' => $jornada->empresa_id,
                'jornada_id' => $jornada->id,
                'cajero_crown_id' => $cajero->id,
                'asignacion_id' => null,
                'tipo' => $tipo,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, $tipo),
                'estado' => TesoreriaCrownComprobanteSupport::BORRADOR,
            ]);
        });

        return redirect()->route('editar_tesoreria_crown_movimiento', $comprobante->id);
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        $cantidades = $comprobante->lineas->pluck('cantidad', 'valor_id');

        return view('caja.tesoreria_crown.movimiento.editar', [
            'comprobante' => $comprobante,
            'grupos' => TesoreriaCrownComprobanteSupport::gruposEdicion($comprobante->sala_id, $comprobante->tipo),
            'cantidades' => $cantidades,
            'cajaCerrada' => $comprobante->cierreOperativo() !== null,
            'editable' => $comprobante->esBorrador()
                && $comprobante->jornada->estaAbierta()
                && $comprobante->cierreOperativo() === null,
            'cierresFiscales' => $this->cierresFiscalesDe($comprobante),
            'pxsolCodigos' => TesoreriaCrownPxsolSupport::codigos($comprobante),
        ]);
    }

    public function traerPxsol(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');
        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorBorradorAbierto($comprobante)) {
            return response()->json(['ok' => false, 'mensaje' => $error], 422);
        }
        try {
            $lectura = TesoreriaCrownPxsolSupport::leer($comprobante);
        } catch (\RuntimeException $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true] + $lectura);
    }

    public function guardar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorBorradorAbierto($comprobante)) {
            return back()->with('mensaje-error', $error);
        }
        $reglas = [
            'cantidad' => ['array'],
            'cantidad.*' => ['nullable', 'integer', 'min:0'],
        ];
        if ($comprobante->tipo === TesoreriaCrownComprobanteSupport::HABILITACION
            && (int) ($comprobante->cajeroOperativo()->mozo_gastronomia_id ?? 0) > 0) {
            $reglas['mozo_aplicacion'] = ['nullable', Rule::in(array_keys(MozoGastronomiaAplicacionSupport::LABELS))];
        }
        $camposMedio = $comprobante->tipo === TesoreriaCrownComprobanteSupport::RENDICION
            ? TesoreriaCrownRendicionCampos::camposDe($comprobante->tipoTareaOperativa())
            : [];
        if ($camposMedio !== []) {
            foreach ($camposMedio as $clave => $meta) {
                $regla = $meta['tipo'] === 'texto'
                    ? ['nullable', 'string', 'max:20']
                    : ['nullable', 'numeric'];
                if ($meta['tipo'] !== 'texto' && ! $meta['negativo']) {
                    $regla[] = 'min:0';
                }
                $reglas['medio.'.$clave] = $regla;
            }
        }
        // Panel de cierres fiscales interactivo: solo hotel (gastro ya trae el X del cierre de mozo).
        if ($camposMedio !== [] && TesoreriaCrownRendicionCampos::esCajaHotel($comprobante->tipoTareaOperativa())) {
            $reglas['cierre_fiscal'] = ['array'];
            $reglas['cierre_fiscal.*'] = ['string', 'max:40'];
            $reglas['pxsol_aplicado'] = ['nullable', 'in:1'];
            $reglas['pxsol_iva_facturas'] = ['required_if:pxsol_aplicado,1', 'nullable', 'numeric'];
            $reglas['pxsol_iva_nc'] = ['required_if:pxsol_aplicado,1', 'nullable', 'numeric'];
        }
        $datos = $request->validate($reglas);
        $cierresElegidos = [];
        if (array_key_exists('cierre_fiscal', $reglas)) {
            try {
                $cierresElegidos = TesoreriaCrownCierreFiscalReader::filasPorClaves(
                    $comprobante,
                    array_values(array_filter((array) ($datos['cierre_fiscal'] ?? [])))
                );
            } catch (\RuntimeException $e) {
                return back()->with('mensaje-error', $e->getMessage())->withInput();
            }
        }
        $idsSala = TesoreriaCrownValor::query()
            ->where('sala_id', $comprobante->sala_id)
            ->pluck('id')
            ->map(fn ($valorId) => (int) $valorId)
            ->all();

        $pedirFirmas = $request->input('accion') === 'pedir-firmas';
        $sincronizaCierresFiscales = array_key_exists('cierre_fiscal', $reglas);

        try {
            DB::transaction(function () use ($comprobante, $datos, $idsSala, $camposMedio, $cierresElegidos, $sincronizaCierresFiscales) {
            if (array_key_exists('mozo_aplicacion', $datos)
                && $comprobante->tipo === TesoreriaCrownComprobanteSupport::HABILITACION) {
                $mozoId = (int) ($comprobante->cajeroOperativo()->mozo_gastronomia_id ?? 0);
                if ($mozoId > 0 && $datos['mozo_aplicacion'] !== null && $datos['mozo_aplicacion'] !== '') {
                    $aplicacion = MozoGastronomiaAplicacionSupport::normalizar((string) $datos['mozo_aplicacion']);
                    MozoGastronomia::query()->whereKey($mozoId)->update(['aplicacion' => $aplicacion]);
                }
            }
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownComprobanteLinea::query()->where('comprobante_id', $comprobante->id)
            );
            foreach ($datos['cantidad'] ?? [] as $valorId => $cantidad) {
                $cantidad = round((float) $cantidad, 2);
                $valorId = (int) $valorId;
                if ($cantidad <= 0 || ! in_array($valorId, $idsSala, true)) {
                    continue;
                }
                TesoreriaCrownComprobanteLinea::query()->create([
                    'comprobante_id' => $comprobante->id,
                    'valor_id' => $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
            if ($camposMedio !== []) {
                foreach ($camposMedio as $clave => $meta) {
                    $crudo = $datos['medio'][$clave] ?? null;
                    if ($meta['tipo'] === 'texto') {
                        $comprobante->{$clave} = trim((string) $crudo) !== '' ? trim((string) $crudo) : null;
                    } elseif ($meta['tipo'] === 'entero') {
                        $comprobante->{$clave} = ($crudo === null || $crudo === '') ? null : (int) preg_replace('/[^\d-]/', '', (string) $crudo);
                    } else {
                        $comprobante->{$clave} = self::parseImporteMiles($crudo);
                    }
                }
                if (! TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->tipoTareaOperativa())) {
                    $comprobante->recargo_fin = TesoreriaCrownCierreCalculo::recargoFinanciacion(
                        (float) $comprobante->tarjeta_credito,
                        $comprobante->jornada->fecha->format('Y-m-d'),
                    );
                }
                if (($datos['pxsol_aplicado'] ?? '') === '1') {
                    $comprobante->iva_facturas = round((float) $datos['pxsol_iva_facturas'], 2);
                    $comprobante->iva_nc = round((float) $datos['pxsol_iva_nc'], 2);
                }
                $comprobante->save();
            }
            // Solo hotel sincroniza el panel; gastro conserva el X creado con el cierre de mozo.
            if ($sincronizaCierresFiscales
                && ($cierresElegidos !== [] || $comprobante->cierresFiscales()->exists())) {
                $this->sincronizarCierresFiscales($comprobante, $cierresElegidos);
            }
            $comprobante->refresh();
            TesoreriaCrownBovedaSupport::asentarCajero($comprobante);
            });
        } catch (QueryException) {
            return back()->with('mensaje-error', 'Ese cierre de ventas ya fue tomado por otra rendición.')->withInput();
        }

        if ($pedirFirmas) {
            $tipoTarea = $comprobante->tipoTareaOperativa();
            $hayLineas = $comprobante->lineas()->exists();
            $hayMedios = TesoreriaCrownRendicionCampos::tieneMediosCargados($comprobante, $tipoTarea);
            if (! $hayLineas && ! $hayMedios) {
                return redirect()
                    ->route('editar_tesoreria_crown_movimiento', $comprobante->id)
                    ->with('mensaje-error', 'No hay nada cargado. Completá billetes, fichas o valores: Pedir firmas guarda lo que está en pantalla.');
            }
            TesoreriaCrownFirmaSupport::pasarAPendiente($comprobante);

            return redirect()
                ->route('editar_tesoreria_crown_movimiento', $comprobante->id)
                ->with('mensaje', 'Se guardaron las cantidades. Quedó pendiente: el cajero firma con su número y PIN, y tesorería con usuario y clave.');
        }

        return redirect()->route('editar_tesoreria_crown_movimiento', $comprobante->id)
            ->with('mensaje', 'Borrador guardado.');
    }

    public function pasarAFirma(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorBorradorAbierto($comprobante)) {
            return back()->with('mensaje-error', $error);
        }
        $tipoTarea = $comprobante->tipoTareaOperativa();
        $hayLineas = $comprobante->lineas()->exists();
        $hayMedios = TesoreriaCrownRendicionCampos::tieneMediosCargados($comprobante, $tipoTarea);
        if (! $hayLineas && ! $hayMedios) {
            return back()->with('mensaje-error', 'Cargá billetes, fichas o valores antes de pedir la firma.');
        }
        TesoreriaCrownFirmaSupport::pasarAPendiente($comprobante);

        return back()->with('mensaje', 'Quedó pendiente: el cajero firma con su número y PIN, y tesorería con usuario y clave.');
    }

    public function firmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorFirmaPendiente($comprobante)) {
            return back()->with('mensaje-error', $error);
        }
        $lado = TesoreriaCrownFirmaSupport::ladoTesoreria($comprobante->tipo);
        if ($this->ladoYaFirmado($comprobante, $lado)) {
            return back()->with('mensaje', 'Tesorería ya firmó este comprobante.');
        }
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password'),
            fn ($u) => TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario);
        }
        if ($error = $this->errorFirmante($comprobante, $lado, $usuario, true)) {
            return back()->with('mensaje-error', $error);
        }
        TesoreriaCrownFirmaSupport::aplicar($comprobante, $lado, $usuario);

        return back()->with('mensaje', $comprobante->estado === TesoreriaCrownComprobanteSupport::CONFORME
            ? 'Firmado por las dos partes. Quedó conforme.'
            : 'Firma de tesorería registrada. Falta la firma del cajero.');
    }

    public function blanquearPinCajero(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        $cajero = $comprobante->cajeroOperativo();
        if ($cajero === null) {
            return back()->with('mensaje-error', 'Esta caja no tiene cajero.');
        }
        $datos = $request->validate([
            'motivo_pin' => ['required', 'string', 'max:300'],
            'pin_firma_usuario' => ['required', 'string'],
            'pin_firma_password' => ['required', 'string'],
        ]);
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $datos['pin_firma_usuario'],
            $datos['pin_firma_password'],
            fn ($u) => TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario);
        }
        TesoreriaCrownFirmaSupport::blanquearPin($cajero);
        Log::notice('Tesorería Crown: PIN de cajero blanqueado', [
            'cajero_id' => $cajero->id,
            'legajo' => $cajero->legajo,
            'comprobante_id' => $comprobante->id,
            'usuario_id' => $usuario->id,
            'motivo' => trim($datos['motivo_pin']),
        ]);

        return back()->with('mensaje', 'PIN blanqueado. En la próxima firma '.($cajero->nombre ?: 'el cajero').' elige uno nuevo.');
    }

    public function cierreFiscalManual(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($comprobante->tipo !== TesoreriaCrownComprobanteSupport::RENDICION
            || ! TesoreriaCrownRendicionCampos::esCajaHotel($comprobante->tipoTareaOperativa())) {
            return back()->with('mensaje-error', 'Solo una rendición de hotel carga cierres de ventas a mano.');
        }
        if ($error = $this->errorBorradorAbierto($comprobante)) {
            return back()->with('mensaje-error', $error);
        }
        $datos = $request->validate([
            'sucursal' => ['required', 'integer', 'min:1'],
            'nombre' => ['nullable', 'string', 'max:80'],
            'hora' => ['required', 'string', 'max:8'],
            'tipo' => ['required', Rule::in(['X', 'Z'])],
            'numero' => ['required', 'integer', 'min:1'],
            'venta' => ['required', 'numeric'],
            'credito' => ['nullable', 'numeric'],
            'ult_ticket' => ['nullable', 'integer', 'min:0'],
        ]);
        $fecha = $comprobante->jornada->fecha->format('Y-m-d');
        $nroX = $datos['tipo'] === 'X' ? (int) $datos['numero'] : 0;
        $nroZ = $datos['tipo'] === 'Z' ? (int) $datos['numero'] : 0;
        $hora = trim($datos['hora']);
        $existe = TesoreriaCrownCierreFiscal::query()
            ->where('sucursal', (int) $datos['sucursal'])
            ->whereDate('fecha_jor', $fecha)
            ->where('hora', $hora)
            ->where('nro_x', $nroX)
            ->where('nro_z', $nroZ)
            ->exists();
        if ($existe) {
            return back()->with('mensaje-error', 'Ese cierre ya está cargado para el día.')->withInput();
        }
        TesoreriaCrownCierreFiscal::query()->create([
            'jornada_id' => (int) $comprobante->jornada_id,
            'comprobante_id' => null,
            'sucursal' => (int) $datos['sucursal'],
            'fecha_jor' => $fecha,
            'hora' => $hora,
            'nro_x' => $nroX,
            'nro_z' => $nroZ,
            'tipo' => $datos['tipo'],
            'nombre_sucursal' => trim((string) ($datos['nombre'] ?? '')) ?: null,
            'tot_venta' => round((float) $datos['venta'], 2),
            'tot_cred' => round((float) ($datos['credito'] ?? 0), 2),
            'tot_iva' => 0,
            'tot_iva_cred' => 0,
            'ult_fact_b' => (int) ($datos['ult_ticket'] ?? 0),
        ]);

        return back()->with('mensaje', 'Cierre '.$datos['tipo'].' '.$datos['numero'].' cargado. Tilgalo y aplicá a la rendición.');
    }

    public function firmarCajero(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorFirmaPendiente($comprobante)) {
            return back()->with('mensaje-error', $error);
        }
        $lado = TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo);
        if ($this->ladoYaFirmado($comprobante, $lado)) {
            return back()->with('mensaje', 'El cajero ya firmó este comprobante.');
        }
        $limite = 'tc-firma-cajero:'.$comprobante->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $asignado = $comprobante->cajeroOperativo();
        if ($asignado === null) {
            return back()->with('mensaje-error', 'Esta caja no tiene cajero.');
        }
        $cajero = TesoreriaCrownFirmaSupport::cajeroPorNumero($asignado, (string) $request->input('cajero_numero'));
        if (is_string($cajero)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', $cajero)->withInput();
        }
        $pin = TesoreriaCrownFirmaSupport::autenticarCajero(
            $cajero,
            (string) $request->input('cajero_pin'),
            $request->input('cajero_pin_nuevo'),
            $request->input('cajero_pin_repetir')
        );
        if (! $pin['ok']) {
            if (! $pin['pedir_nuevo']) {
                RateLimiter::hit($limite, 60);
            }

            return back()
                ->with('mensaje-error', $pin['error'])
                ->with('tc_pin_nuevo', $pin['pedir_nuevo'])
                ->withInput($request->only('cajero_numero'));
        }
        RateLimiter::clear($limite);
        TesoreriaCrownFirmaSupport::aplicarCajero($comprobante);

        return back()->with('mensaje', $comprobante->estado === TesoreriaCrownComprobanteSupport::CONFORME
            ? 'Firmado por las dos partes. Quedó conforme.'
            : 'Firma del cajero registrada. Falta la firma de tesorería.');
    }

    public function volverABorrador(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');
        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada) ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)) {
            return back()->with('mensaje-error', $error);
        }
        if ($comprobante->estado !== TesoreriaCrownComprobanteSupport::PENDIENTE) {
            return back()->with('mensaje-error', 'Solo un pendiente de firma vuelve a borrador.');
        }
        if ($comprobante->revierte_id !== null) {
            return back()->with('mensaje-error', 'Una reversa no se modifica. Si está mal, marcala no conforme y auditoría la anula.');
        }
        $habiaFirma = $comprobante->entrega_at !== null || $comprobante->recibe_at !== null;
        TesoreriaCrownFirmaSupport::volverABorrador($comprobante);

        return back()->with('mensaje', $habiaFirma
            ? 'Volvió a borrador. Las firmas quedaron sin efecto.'
            : 'Volvió a borrador. Podés modificar las cantidades.');
    }

    /**
     * Descarta un borrador (también el vacío que nace al abrir Hab/Rend).
     * No pide reversa: el borrador no quedó conforme.
     */
    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if (! $comprobante->esBorrador() || ! $comprobante->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se borra un borrador con la jornada abierta.');
        }
        if ($comprobante->cierreOperativo() !== null) {
            return back()->with('mensaje-error', 'Este cajero ya está cerrado.');
        }
        if ($comprobante->revierte_id !== null) {
            return back()->with('mensaje-error', 'Una reversa no se borra como borrador.');
        }

        $volver = [
            'sala_id' => (int) $comprobante->sala_id,
            'jornada_id' => (int) $comprobante->jornada_id,
        ];

        DB::transaction(function () use ($comprobante) {
            TesoreriaCrownBovedaSupport::anularAsientoCajero($comprobante);
            DB::table('tesoreria_crown_boveda')
                ->where('comprobante_id', $comprobante->id)
                ->update(['comprobante_id' => null, 'updated_at' => now()]);
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownComprobanteLinea::query()->where('comprobante_id', $comprobante->id)
            );
            EloquentAuditDeleteSupport::each(
                TesoreriaCrownCierreFiscal::query()->where('comprobante_id', $comprobante->id)
            );
            $comprobante->delete();
        });

        return redirect()
            ->route('ver_tesoreria_crown_jornada', $volver['jornada_id'])
            ->with('mensaje', 'Borrador eliminado.');
    }

    public function vincularCajero(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');
        $comprobante = $this->comprobanteEnAlcance($id);
        $usuarioId = (int) $request->input('usuario_id');
        $usuario = UsuarioOperativoSupport::query()->whereKey($usuarioId)->first();
        if ($usuario === null) {
            return back()->with('mensaje-error', 'Elegí un usuario activo del sistema.');
        }
        $cajero = $comprobante->cajeroOperativo();
        if ($cajero === null) {
            return back()->with('mensaje-error', 'Esta caja no tiene cajero.');
        }
        foreach (TesoreriaCrownFirmaSupport::cajerosDelMismoLegajo($cajero) as $row) {
            $row->usuario_id = $usuario->id;
            $row->save();
        }

        return back()->with('mensaje', 'El cajero '.$cajero->nombre.' firma con el usuario '.$usuario->usuario.'.');
    }

    public function imprimir(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-movimiento');
        $comprobante = $this->comprobanteEnAlcance($id);
        $pdf = Pdf::loadView('caja.tesoreria_crown.movimiento.comprobante', [
            'comprobante' => $comprobante,
            'grupos' => TesoreriaCrownComprobanteSupport::gruposEdicion($comprobante->sala_id, $comprobante->tipo),
            'cantidades' => $comprobante->lineas->pluck('cantidad', 'valor_id'),
        ])->setPaper('a4', 'portrait');

        $nombre = TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo).' '.TesoreriaCrownNumeradorSupport::visible($comprobante->numero);

        return $pdf->stream($nombre.'.pdf');
    }

    public function noConforme(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada) ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)) {
            return back()->with('mensaje-error', $error);
        }
        if (! in_array($comprobante->estado, [TesoreriaCrownComprobanteSupport::BORRADOR, TesoreriaCrownComprobanteSupport::PENDIENTE], true)) {
            return back()->with('mensaje-error', 'Este comprobante ya no se puede marcar no conforme.');
        }
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'max:500'],
        ]);
        $comprobante->estado = TesoreriaCrownComprobanteSupport::NO_CONFORME;
        $comprobante->qr_token = null;
        $comprobante->observacion = $this->conNota(
            $comprobante->observacion,
            'No conforme ('.(auth()->user()->usuario ?? '').' '.now()->format('d/m/Y H:i').'): '.trim($datos['observacion'])
        );
        $comprobante->save();
        TesoreriaCrownBovedaSupport::anularAsientoCajero($comprobante);

        TesoreriaCrownAvisoGenerador::alEvento((int) $comprobante->jornada_id);

        return back()->with('mensaje', 'Quedó no conforme. La caja no cierra hasta que auditoría lo corrija o lo anule.');
    }

    /**
     * Auditoría resuelve un no conforme: lo devuelve a borrador para corregirlo o lo anula.
     * Anular una reversa libera el comprobante original para revertirlo de nuevo.
     */
    public function resolverNoConforme(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada) ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)) {
            return back()->with('mensaje-error', $error);
        }
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['corregir', 'anular'])],
            'resolucion' => ['required', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
        ]);
        if ($datos['accion'] === 'corregir' && $comprobante->revierte_id !== null) {
            return back()->with('mensaje-error', 'Una reversa no se corrige: se anula y, si hace falta, se revierte de nuevo el original.');
        }
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $datos['firma_usuario'],
            $datos['firma_password'],
            fn ($u) => TesoreriaCrownFirmaSupport::puedeAuditarCaja($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario);
        }

        $resuelto = DB::transaction(function () use ($comprobante, $datos, $usuario) {
            $estado = DB::table('tesoreria_crown_comprobante')->where('id', $comprobante->id)->lockForUpdate()->value('estado');
            if (! in_array($estado, TesoreriaCrownComprobanteSupport::SIN_RESOLVER, true)) {
                return false;
            }
            $anula = $datos['accion'] === 'anular';
            $nota = ($anula ? 'Anulado' : 'Vuelve a borrador').' por auditoría ('.$usuario->usuario.' '.now()->format('d/m/Y H:i').'): '.trim($datos['resolucion']);
            if ($anula && $comprobante->revierte_id !== null) {
                $nota .= ' Era la reversa del comprobante '.TesoreriaCrownNumeradorSupport::visible((int) $comprobante->revierte?->numero).'.';
            }
            $comprobante->observacion = $this->conNota($comprobante->observacion, $nota);
            if ($anula) {
                $comprobante->estado = TesoreriaCrownComprobanteSupport::ANULADO;
                $comprobante->qr_token = null;
                $comprobante->revierte_id = null;
                $comprobante->save();
                $this->sincronizarCierresFiscales($comprobante, []);
                TesoreriaCrownBovedaSupport::anularAsientoCajero($comprobante);
            } else {
                TesoreriaCrownFirmaSupport::volverABorrador($comprobante);
                TesoreriaCrownBovedaSupport::asentarCajero($comprobante->fresh());
            }

            return true;
        });
        if (! $resuelto) {
            return back()->with('mensaje-error', 'Este comprobante ya no está no conforme.');
        }

        TesoreriaCrownAvisoGenerador::alEvento((int) $comprobante->jornada_id);

        return back()->with('mensaje', $datos['accion'] === 'anular'
            ? 'Comprobante anulado. No suma en la caja.'
            : 'Volvió a borrador: se corrige y se firma de nuevo.');
    }

    private function conNota(?string $observacion, string $nota): string
    {
        $previa = trim((string) $observacion);

        return mb_substr($previa !== '' ? $previa."\n".$nota : $nota, 0, 5000);
    }

    public function revertir(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada) ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)) {
            return back()->with('mensaje-error', $error);
        }
        if ($comprobante->estado !== TesoreriaCrownComprobanteSupport::CONFORME || $comprobante->revierte_id !== null) {
            return back()->with('mensaje-error', 'Solo se revierte un comprobante conforme, y una sola vez.');
        }
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:500'],
            'firma_usuario' => ['required', 'string'],
            'firma_password' => ['required', 'string'],
        ]);
        $usuario = TesoreriaCrownFirmaSupport::firmante(
            $datos['firma_usuario'],
            $datos['firma_password'],
            fn ($u) => TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($u)
        );
        if (is_string($usuario)) {
            return back()->with('mensaje-error', $usuario)->withInput($request->only('motivo'));
        }

        $usuarioId = $usuario->id;
        $motivo = trim($datos['motivo']);
        try {
            $inverso = DB::transaction(function () use ($comprobante, $usuarioId, $motivo) {
                $estado = DB::table('tesoreria_crown_comprobante')->where('id', $comprobante->id)->lockForUpdate()->value('estado');
                if ($estado !== TesoreriaCrownComprobanteSupport::CONFORME
                    || TesoreriaCrownComprobante::query()->where('revierte_id', $comprobante->id)->exists()) {
                    return null;
                }

                return $this->crearReversa($comprobante, $usuarioId, $motivo);
            });
        } catch (QueryException) {
            $inverso = null;
        }
        if ($inverso === null) {
            return back()->with('mensaje-error', 'Este comprobante ya fue revertido.');
        }

        TesoreriaCrownAvisoGenerador::alEvento((int) $comprobante->jornada_id);

        return redirect()->route('editar_tesoreria_crown_movimiento', $inverso->id)
            ->with('mensaje', 'Reversa pendiente de firma. El cajero firma con su número y PIN, y tesorería con usuario y clave.');
    }

    private function crearReversa(TesoreriaCrownComprobante $comprobante, int $usuarioId, string $motivo): TesoreriaCrownComprobante
    {
        $nuevo = TesoreriaCrownComprobante::query()->create([
            'sala_id' => $comprobante->sala_id,
            'empresa_id' => $comprobante->empresa_id,
            'jornada_id' => $comprobante->jornada_id,
            'cajero_crown_id' => $comprobante->cajero_crown_id,
            'asignacion_id' => $comprobante->asignacion_id,
            'tipo' => $comprobante->tipo,
            'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $comprobante->sala_id, $comprobante->tipo),
            'estado' => TesoreriaCrownComprobanteSupport::BORRADOR,
            'observacion' => 'Reversa del comprobante '.TesoreriaCrownNumeradorSupport::visible((int) $comprobante->numero).': '.$motivo,
            'revierte_id' => $comprobante->id,
            'creado_usuario_id' => $usuarioId,
            'cotiz_dolar' => $comprobante->cotiz_dolar,
            'cotiz_euro' => $comprobante->cotiz_euro,
        ]);
        foreach (TesoreriaCrownRendicionCampos::campos() as $clave => $meta) {
            if ($meta['tipo'] === 'importe') {
                $nuevo->{$clave} = round((float) $comprobante->{$clave} * -1, 2);
            } else {
                $nuevo->{$clave} = $comprobante->{$clave};
            }
        }
        $nuevo->pago_apuesta = round((float) $comprobante->pago_apuesta * -1, 2);
        $nuevo->save();
        foreach ($comprobante->lineas as $linea) {
            TesoreriaCrownComprobanteLinea::query()->create([
                'comprobante_id' => $nuevo->id,
                'valor_id' => $linea->valor_id,
                'cantidad' => (int) round((float) $linea->cantidad * -1),
            ]);
        }

        $nuevo->load('lineas');
        TesoreriaCrownFirmaSupport::pasarAPendiente($nuevo);

        return $nuevo;
    }

    /**
     * @return array{error: ?string, filas: list<array<string, mixed>>}
     */
    private function cierresFiscalesDe(TesoreriaCrownComprobante $comprobante): array
    {
        if ($comprobante->tipo !== TesoreriaCrownComprobanteSupport::RENDICION
            || ! TesoreriaCrownRendicionCampos::esCajaGastroHotel($comprobante->tipoTareaOperativa())) {
            return ['error' => null, 'filas' => []];
        }

        // Gastro: solo el X ya vinculado al crear la rendición (tarjetita informativa).
        if (TesoreriaCrownRendicionCampos::esCajaGastronomia($comprobante->tipoTareaOperativa())) {
            return TesoreriaCrownCierreFiscalReader::vinculadosDe($comprobante);
        }

        $jornada = $comprobante->jornada;
        if ($jornada !== null) {
            try {
                // Completa huecos Anita cierrefiscal→ERP (como a-transfefe / consulta_cierrefiscal).
                // No toca X ya generados por gastronomía ni regenera desde venta.
                TesoreriaCrownCierreFiscalSync::asegurarJornada($jornada);
            } catch (\Throwable) {
                // Reader solo ERP; sync Anita live desconectado (corte).
            }
        }

        return TesoreriaCrownCierreFiscalReader::paraRendicion($comprobante);
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function sincronizarCierresFiscales(TesoreriaCrownComprobante $comprobante, array $filas): void
    {
        $previos = TesoreriaCrownCierreFiscal::query()
            ->where('comprobante_id', $comprobante->id)
            ->get();
        foreach ($previos as $previo) {
            $previo->comprobante_id = null;
            $previo->save();
        }

        $jornadaId = (int) $comprobante->jornada_id;
        foreach ($filas as $fila) {
            $fecha = (string) $fila['fecha'];
            $existente = TesoreriaCrownCierreFiscal::query()
                ->where('sucursal', (int) $fila['sucursal'])
                ->whereDate('fecha_jor', $fecha)
                ->where('hora', (string) $fila['hora'])
                ->where('nro_x', (int) $fila['nro_x'])
                ->where('nro_z', (int) $fila['nro_z'])
                ->first();
            if ($existente === null && (int) $fila['nro_z'] > 0) {
                $existente = TesoreriaCrownCierreFiscal::query()
                    ->where('sucursal', (int) $fila['sucursal'])
                    ->whereDate('fecha_jor', $fecha)
                    ->where('nro_z', (int) $fila['nro_z'])
                    ->whereNull('comprobante_id')
                    ->first();
            }

            $attrs = [
                'jornada_id' => $jornadaId > 0 ? $jornadaId : null,
                'comprobante_id' => $comprobante->id,
                'sucursal' => (int) $fila['sucursal'],
                'fecha_jor' => $fecha,
                'hora' => (string) $fila['hora'],
                'nro_x' => (int) $fila['nro_x'],
                'nro_z' => (int) $fila['nro_z'],
                'tipo' => (string) $fila['tipo'],
                'nombre_sucursal' => $fila['nombre'] !== '' ? $fila['nombre'] : null,
                'tot_venta' => $fila['venta'],
                'tot_cred' => $fila['credito'],
                'tot_iva' => $fila['iva'],
                'tot_iva_cred' => $fila['iva_credito'],
                'ult_fact_b' => $fila['ult_ticket'],
            ];

            if ($existente !== null) {
                $existente->fill($attrs);
                $existente->save();
            } else {
                TesoreriaCrownCierreFiscal::query()->create($attrs);
            }
        }
    }

    private function asignacionEnAlcance(int $id): TesoreriaCrownAsignacion
    {
        $asignacion = TesoreriaCrownAsignacion::query()->with('jornada')->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $asignacion->sala_id);

        return $asignacion;
    }

    private function comprobanteEnAlcance(int $id): TesoreriaCrownComprobante
    {
        $comprobante = TesoreriaCrownComprobante::query()
            ->with([
                'jornada',
                'sala',
                'empresa',
                'cajero.usuario',
                'cajero.mozoGastronomia',
                'asignacion.deposito',
                'asignacion.cajero.usuario',
                'asignacion.cajero.mozoGastronomia',
                'asignacion.cierre',
                'lineas.valor.tipoValor',
                'entregaUsuario',
                'recibeUsuario',
                'creadoUsuario',
                'reversa.creadoUsuario',
                'revierte',
            ])
            ->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $comprobante->sala_id);

        return $comprobante;
    }

    private function errorJornada(TesoreriaCrownJornada $jornada): ?string
    {
        return $jornada->estaAbierta() ? null : 'La jornada de esta caja ya está cerrada.';
    }

    private function errorCaja(TesoreriaCrownAsignacion $asignacion): ?string
    {
        return $asignacion->cierre ? 'Este cajero ya está cerrado.' : null;
    }

    private function errorCajeroCerrado(TesoreriaCrownJornada $jornada, CajeroCrown $cajero): ?string
    {
        return TesoreriaCrownDiaCajeroSupport::cierreAbierto((int) $jornada->id, (int) $cajero->id)
            ? 'Este cajero ya está cerrado.'
            : null;
    }

    private function errorBorradorAbierto(TesoreriaCrownComprobante $comprobante): ?string
    {
        return $this->errorJornada($comprobante->jornada)
            ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)
            ?? ($comprobante->esBorrador() ? null : 'Las cantidades solo se editan en borrador.');
    }

    private function errorAlta(TesoreriaCrownAsignacion $asignacion, string $tipo): ?string
    {
        return $this->errorAltaCajero($asignacion->jornada, $asignacion->cajero, $tipo, (int) $asignacion->id);
    }

    private function errorAltaCajero(
        TesoreriaCrownJornada $jornada,
        ?CajeroCrown $cajero,
        string $tipo,
        ?int $asignacionId = null
    ): ?string {
        if ($tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
            return null;
        }
        if ($cajero && $cajero->permite_rendir_sin_habilitacion) {
            return null;
        }
        $query = TesoreriaCrownComprobante::query()
            ->where('jornada_id', $jornada->id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->whereNull('revierte_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('tesoreria_crown_comprobante as rev')
                    ->whereColumn('rev.revierte_id', 'tesoreria_crown_comprobante.id');
            });
        if ($cajero) {
            $query->where('cajero_crown_id', $cajero->id);
        } elseif ($asignacionId) {
            $query->where('asignacion_id', $asignacionId);
        }
        if (! $query->exists()) {
            return 'La rendición necesita una habilitación conforme.';
        }

        return null;
    }

    private function errorFirmaPendiente(TesoreriaCrownComprobante $comprobante): ?string
    {
        if ($error = $this->errorJornada($comprobante->jornada)
            ?? ($comprobante->cierreOperativo() ? 'Este cajero ya está cerrado.' : null)) {
            return $error;
        }
        if ($comprobante->estado !== TesoreriaCrownComprobanteSupport::PENDIENTE) {
            return 'Solo se firma un comprobante pendiente.';
        }

        return null;
    }

    private function ladoYaFirmado(TesoreriaCrownComprobante $comprobante, string $lado): bool
    {
        return $lado === 'entrega' ? $comprobante->entrega_at !== null : $comprobante->recibe_at !== null;
    }

    private function errorFirmante(TesoreriaCrownComprobante $comprobante, string $lado, \App\Models\Seguridad\Usuario $usuario, bool $tesoreria): ?string
    {
        $otroId = $lado === 'entrega' ? (int) $comprobante->recibe_usuario_id : (int) $comprobante->entrega_usuario_id;
        if ($otroId > 0 && $otroId === (int) $usuario->id) {
            return 'Quien entrega y quien recibe tienen que ser dos personas distintas.';
        }
        $cajero = $comprobante->cajeroOperativo();
        $cajeroUsuarioId = (int) ($cajero->usuario_id ?? 0);
        if ($tesoreria) {
            if (! TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($usuario)) {
                return TesoreriaCrownFirmaSupport::MENSAJE_FIRMANTE;
            }
            if ($cajeroUsuarioId > 0 && $cajeroUsuarioId === (int) $usuario->id) {
                return 'El cajero firma su lado con número y PIN. Acá firma otra persona de tesorería.';
            }

            return null;
        }
        if ($cajero === null) {
            return 'Esta caja no tiene cajero.';
        }
        if ($cajeroUsuarioId !== (int) $usuario->id) {
            return 'Esta firma le corresponde a '.$cajero->nombre.'.';
        }

        return null;
    }

    /** Parsea importes con separador de miles es-AR (1.234,56) o punto decimal. */
    private static function parseImporteMiles(mixed $crudo): float
    {
        if ($crudo === null || $crudo === '') {
            return 0.0;
        }
        if (is_numeric($crudo)) {
            return round((float) $crudo, 2);
        }
        $texto = trim((string) $crudo);
        $texto = str_replace([' ', "\u{00A0}"], '', $texto);
        if (str_contains($texto, ',')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        }

        return round((float) $texto, 2);
    }
}
