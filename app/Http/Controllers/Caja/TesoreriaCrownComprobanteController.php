<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownComprobanteLinea;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\RateLimiter;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownMovimientoListadoColumnas;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownRendicionCampos;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Support\Caja\TesoreriaCrownMovimientoListadoFiltros;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
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
                $fila->asignacion->cajero->nombre ?? '',
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

        $asignacion = $this->asignacionEnAlcance((int) $request->input('asignacion_id'));
        $tipo = (string) $request->input('tipo');
        if (! isset(TesoreriaCrownComprobanteSupport::TIPOS[$tipo])) {
            abort(404);
        }
        if ($error = $this->errorJornada($asignacion->jornada) ?? $this->errorAlta($asignacion, $tipo)) {
            return redirect()->route('ver_tesoreria_crown_jornada', $asignacion->jornada_id)->with('mensaje-error', $error);
        }

        $comprobante = DB::transaction(function () use ($asignacion, $tipo) {
            return TesoreriaCrownComprobante::query()->create([
                'sala_id' => $asignacion->sala_id,
                'empresa_id' => $asignacion->jornada->empresa_id,
                'jornada_id' => $asignacion->jornada_id,
                'asignacion_id' => $asignacion->id,
                'tipo' => $tipo,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $asignacion->sala_id, $tipo),
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
            'editable' => $comprobante->esBorrador() && $comprobante->jornada->estaAbierta(),
        ]);
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
            'cantidad.*' => ['nullable', 'numeric', 'min:0'],
        ];
        $camposMedio = $comprobante->tipo === TesoreriaCrownComprobanteSupport::RENDICION
            ? TesoreriaCrownRendicionCampos::camposDe($comprobante->asignacion->tipo_tarea ?? '')
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
        $datos = $request->validate($reglas);
        $idsSala = TesoreriaCrownValor::query()
            ->where('sala_id', $comprobante->sala_id)
            ->pluck('id')
            ->map(fn ($valorId) => (int) $valorId)
            ->all();

        $pedirFirmas = $request->input('accion') === 'pedir-firmas';

        DB::transaction(function () use ($comprobante, $datos, $idsSala, $camposMedio) {
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
                        $comprobante->{$clave} = ($crudo === null || $crudo === '') ? null : (int) $crudo;
                    } else {
                        $comprobante->{$clave} = round((float) $crudo, 2);
                    }
                }
                $comprobante->save();
            }
        });

        if ($pedirFirmas) {
            if (! $comprobante->lineas()->exists()) {
                return redirect()
                    ->route('editar_tesoreria_crown_movimiento', $comprobante->id)
                    ->with('mensaje-error', 'No hay ninguna cantidad cargada. Completá al menos una denominación: Pedir firmas guarda lo que está en pantalla.');
            }
            TesoreriaCrownFirmaSupport::publicarToken($comprobante);

            return redirect()
                ->route('editar_tesoreria_crown_movimiento', $comprobante->id)
                ->with('mensaje', 'Se guardaron las cantidades. Quedó pendiente: el cajero firma con el QR y tesorería firma en esta pantalla.');
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
        if (! $comprobante->lineas()->exists()) {
            return back()->with('mensaje-error', 'Cargá al menos un valor antes de pedir la firma.');
        }
        TesoreriaCrownFirmaSupport::publicarToken($comprobante);

        return back()->with('mensaje', 'Quedó pendiente: el cajero firma con el QR y tesorería firma en esta pantalla.');
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
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null) {
            return back()->with('mensaje-error', 'Usuario o clave incorrectos.');
        }
        if ($error = $this->errorFirmante($comprobante, $lado, $usuario, true)) {
            return back()->with('mensaje-error', $error);
        }
        TesoreriaCrownFirmaSupport::aplicar($comprobante, $lado, $usuario);

        return back()->with('mensaje', $comprobante->estado === TesoreriaCrownComprobanteSupport::CONFORME
            ? 'Firmado por las dos partes. Quedó conforme.'
            : 'Firma de tesorería registrada. Falta la firma del cajero.');
    }

    public function firmarQr(Request $request, string $token)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        $comprobante = $this->comprobantePorToken($token);
        $vista = $this->datosQr($comprobante);
        if ($vista['bloqueado'] !== null) {
            return response()->view('caja.tesoreria_crown.movimiento.firma_qr', $vista);
        }
        $clave = 'tc-firma:'.$token.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($clave, 8)) {
            return response()->view('caja.tesoreria_crown.movimiento.firma_qr', $vista + [
                'error' => 'Demasiados intentos. Esperá un minuto y volvé a probar.',
            ]);
        }
        $lado = TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo);
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null) {
            RateLimiter::hit($clave, 60);

            return response()->view('caja.tesoreria_crown.movimiento.firma_qr', $vista + [
                'error' => 'Usuario o clave incorrectos.',
            ]);
        }
        if ($error = $this->errorFirmante($comprobante, $lado, $usuario, false)) {
            return response()->view('caja.tesoreria_crown.movimiento.firma_qr', $vista + ['error' => $error]);
        }
        TesoreriaCrownFirmaSupport::aplicar($comprobante, $lado, $usuario);
        $comprobante->refresh();

        return response()->view('caja.tesoreria_crown.movimiento.firma_qr', $this->datosQr($comprobante) + [
            'aviso' => 'Firma registrada.',
        ]);
    }

    public function verQr(string $token)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();

        return response()->view(
            'caja.tesoreria_crown.movimiento.firma_qr',
            $this->datosQr($this->comprobantePorToken($token))
        );
    }

    public function volverABorrador(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-movimiento');
        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if ($comprobante->estado !== TesoreriaCrownComprobanteSupport::PENDIENTE) {
            return back()->with('mensaje-error', 'Solo un pendiente de firma vuelve a borrador.');
        }
        TesoreriaCrownFirmaSupport::volverABorrador($comprobante);

        return back()->with('mensaje', 'Volvió a borrador. Las firmas y el QR anteriores quedaron sin efecto.');
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
        $cajero = $comprobante->asignacion->cajero;
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
        if ($error = $this->errorJornada($comprobante->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if (! in_array($comprobante->estado, [TesoreriaCrownComprobanteSupport::BORRADOR, TesoreriaCrownComprobanteSupport::PENDIENTE], true)) {
            return back()->with('mensaje-error', 'Este comprobante ya no se puede marcar no conforme.');
        }
        $datos = $request->validate([
            'observacion' => ['required', 'string', 'max:500'],
        ]);
        $comprobante->estado = TesoreriaCrownComprobanteSupport::AUDITORIA;
        $comprobante->observacion = $datos['observacion'];
        $comprobante->save();

        return back()->with('mensaje', 'No conforme. Pasó a auditoría.');
    }

    public function revertir(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-movimiento');

        $comprobante = $this->comprobanteEnAlcance($id);
        if ($error = $this->errorJornada($comprobante->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if ($comprobante->estado !== TesoreriaCrownComprobanteSupport::CONFORME || $comprobante->revierte_id !== null) {
            return back()->with('mensaje-error', 'Solo se revierte un comprobante conforme, y una sola vez.');
        }
        if (TesoreriaCrownComprobante::query()->where('revierte_id', $comprobante->id)->exists()) {
            return back()->with('mensaje-error', 'Este comprobante ya fue revertido.');
        }

        $inverso = DB::transaction(function () use ($comprobante) {
            $nuevo = TesoreriaCrownComprobante::query()->create([
                'sala_id' => $comprobante->sala_id,
                'empresa_id' => $comprobante->empresa_id,
                'jornada_id' => $comprobante->jornada_id,
                'asignacion_id' => $comprobante->asignacion_id,
                'tipo' => $comprobante->tipo,
                'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $comprobante->sala_id, $comprobante->tipo),
                'estado' => TesoreriaCrownComprobanteSupport::CONFORME,
                'observacion' => 'Reversa del comprobante '.$comprobante->id,
                'entrega_usuario_id' => (int) auth()->id(),
                'entrega_at' => now(),
                'recibe_usuario_id' => (int) auth()->id(),
                'recibe_at' => now(),
                'revierte_id' => $comprobante->id,
            ]);
            foreach (TesoreriaCrownRendicionCampos::campos() as $clave => $meta) {
                if ($meta['tipo'] === 'importe') {
                    $nuevo->{$clave} = round((float) $comprobante->{$clave} * -1, 2);
                } else {
                    $nuevo->{$clave} = $comprobante->{$clave};
                }
            }
            $nuevo->save();
            foreach ($comprobante->lineas as $linea) {
                TesoreriaCrownComprobanteLinea::query()->create([
                    'comprobante_id' => $nuevo->id,
                    'valor_id' => $linea->valor_id,
                    'cantidad' => round((float) $linea->cantidad * -1, 2),
                ]);
            }

            return $nuevo;
        });

        return redirect()->route('editar_tesoreria_crown_movimiento', $inverso->id)
            ->with('mensaje', 'Reversa registrada. No se puede revertir de nuevo.');
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
            ->with(['jornada', 'sala', 'empresa', 'asignacion.deposito', 'asignacion.cajero.usuario', 'lineas.valor.tipoValor', 'entregaUsuario', 'recibeUsuario'])
            ->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $comprobante->sala_id);

        return $comprobante;
    }

    private function errorJornada(TesoreriaCrownJornada $jornada): ?string
    {
        return $jornada->estaAbierta() ? null : 'La jornada de esta caja ya está cerrada.';
    }

    private function errorBorradorAbierto(TesoreriaCrownComprobante $comprobante): ?string
    {
        return $this->errorJornada($comprobante->jornada)
            ?? ($comprobante->esBorrador() ? null : 'Las cantidades solo se editan en borrador.');
    }

    private function errorAlta(TesoreriaCrownAsignacion $asignacion, string $tipo): ?string
    {
        if ($tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
            return null;
        }
        $hayConforme = TesoreriaCrownComprobante::query()
            ->where('asignacion_id', $asignacion->id)
            ->where('tipo', TesoreriaCrownComprobanteSupport::HABILITACION)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->whereNull('revierte_id')
            ->whereNotExists(function ($q) {
                $q->selectRaw('1')
                    ->from('tesoreria_crown_comprobante as rev')
                    ->whereColumn('rev.revierte_id', 'tesoreria_crown_comprobante.id');
            })
            ->exists();
        if (! $hayConforme) {
            return 'La rendición necesita una habilitación conforme.';
        }

        return null;
    }

    private function errorFirmaPendiente(TesoreriaCrownComprobante $comprobante): ?string
    {
        if ($error = $this->errorJornada($comprobante->jornada)) {
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
        $cajero = $comprobante->asignacion->cajero;
        $cajeroUsuarioId = (int) ($cajero->usuario_id ?? 0);
        if ($tesoreria) {
            if (! TesoreriaCrownFirmaSupport::puedeFirmarTesoreria($usuario)) {
                return 'Ese usuario no puede firmar por tesorería.';
            }
            if ($cajeroUsuarioId > 0 && $cajeroUsuarioId === (int) $usuario->id) {
                return 'El cajero firma su lado con el QR. Acá firma otra persona de tesorería.';
            }

            return null;
        }
        if ($cajero === null) {
            return 'Esta caja no tiene cajero.';
        }
        if ($cajeroUsuarioId === 0) {
            return 'El cajero '.$cajero->nombre.' no tiene un usuario asignado. Sin eso no puede firmar desde el teléfono.';
        }
        if ($cajeroUsuarioId !== (int) $usuario->id) {
            return 'Esta firma le corresponde a '.$cajero->nombre.'.';
        }

        return null;
    }

    private function comprobantePorToken(string $token): TesoreriaCrownComprobante
    {
        $comprobante = TesoreriaCrownComprobante::query()
            ->with(['jornada', 'sala', 'empresa', 'asignacion.deposito', 'asignacion.cajero.usuario', 'lineas.valor.tipoValor', 'entregaUsuario', 'recibeUsuario'])
            ->where('qr_token', $token)
            ->first();
        if ($comprobante === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $comprobante->sala_id);

        return $comprobante;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosQr(TesoreriaCrownComprobante $comprobante): array
    {
        $lado = TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo);
        $bloqueado = $this->errorFirmaPendiente($comprobante);
        if ($bloqueado === null && $this->ladoYaFirmado($comprobante, $lado)) {
            $bloqueado = 'Este lado ya está firmado.';
        }
        $cantidades = $comprobante->lineas->pluck('cantidad', 'valor_id');

        return [
            'comprobante' => $comprobante,
            'lado' => $lado,
            'etiqueta' => TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, $lado),
            'grupos' => TesoreriaCrownComprobanteSupport::gruposEdicion($comprobante->sala_id, $comprobante->tipo),
            'cantidades' => $cantidades,
            'bloqueado' => $bloqueado,
            'error' => null,
            'aviso' => null,
        ];
    }
}
