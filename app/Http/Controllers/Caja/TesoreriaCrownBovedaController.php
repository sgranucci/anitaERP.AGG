<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownBoveda;
use App\Models\Caja\TesoreriaCrownBovedaLinea;
use App\Models\Caja\TesoreriaCrownBovedaTipo;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Ventas\Cliente;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownEscritorioNav;
use App\Support\Caja\TesoreriaCrownBovedaListadoColumnas;
use App\Support\Caja\TesoreriaCrownBovedaListadoFiltros;
use App\Support\Caja\TesoreriaCrownBovedaPredepositoSupport;
use App\Support\Caja\TesoreriaCrownBovedaSupport;
use App\Support\Caja\TesoreriaCrownKardexValoresSupport;
use App\Support\Caja\TesoreriaCrownCierreCalculo;
use App\Support\Caja\TesoreriaCrownComprobanteSupport;
use App\Support\Caja\TesoreriaCrownFirmaSupport;
use App\Support\Caja\TesoreriaCrownJornadaConsultaSupport;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownNumeradorSupport;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoVistaSupport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class TesoreriaCrownBovedaController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $fechaPedida = trim((string) $request->input('fecha', ''));
        $jornada = TesoreriaCrownJornadaConsultaSupport::pedida($request, $sala ? (int) $sala->id : 0);
        $lugares = $sala ? TesoreriaCrownBovedaSupport::lugares((int) $sala->id) : collect();
        $depositoId = (int) $request->input('deposito_id', 0);
        $lugar = $lugares->firstWhere('id', $depositoId) ?? $lugares->first(
            fn ($fila) => (string) ($fila->tipoDeposito->codigo ?? '') === 'BOVEDA'
        ) ?? $lugares->first();
        $armado = [];
        $movimientos = collect();
        $saldo = [];
        $inicio = [];
        $apertura = null;
        if ($jornada && $lugar && $sala) {
            $armado = $this->workbench($request, (int) $sala->id, (int) $jornada->id, (int) $lugar->id);
            if ($armado instanceof \Illuminate\Http\RedirectResponse) {
                return $armado;
            }
            $movimientos = TesoreriaCrownBovedaListadoFiltros::consulta(
                (int) $jornada->id,
                (int) $lugar->id,
                $armado['filtros']
            )->get();
            $fecha = $jornada->fecha?->format('Y-m-d');
            if ($fecha) {
                $saldo = TesoreriaCrownBovedaSupport::saldoAgrupado(
                    (int) $sala->id,
                    TesoreriaCrownBovedaSupport::saldoCantidades((int) $lugar->id, $fecha)
                );
            }
            $apertura = TesoreriaCrownBoveda::query()
                ->with(['tipo', 'lineas.valor.tipoValor'])
                ->where('jornada_id', $jornada->id)
                ->where('deposito_id', $lugar->id)
                ->where('estado', '<>', TesoreriaCrownBoveda::ANULADO)
                ->whereNull('revierte_id')
                ->whereHas('tipo', fn ($q) => $q->where('abreviatura', 'APER'))
                ->orderByDesc('numero')
                ->first();
            if ($apertura) {
                $cantidadesInicio = [];
                foreach ($apertura->lineas as $linea) {
                    $valorId = (int) $linea->valor_id;
                    $cantidadesInicio[$valorId] = ($cantidadesInicio[$valorId] ?? 0.0) + (float) $linea->cantidad;
                }
                $inicio = TesoreriaCrownBovedaSupport::saldoAgrupado((int) $sala->id, $cantidadesInicio);
            }
        }

        $totalesMonedas = ['ARS' => 0.0, 'USD' => 0.0, 'EUR' => 0.0];
        foreach ($movimientos as $mov) {
            foreach (TesoreriaCrownBovedaListadoColumnas::montosPorMoneda($mov) as $codigo => $monto) {
                $totalesMonedas[$codigo] = round($totalesMonedas[$codigo] + $monto, 2);
            }
        }

        return view('caja.tesoreria_crown.boveda.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'jornada' => $jornada,
            'fechaPedida' => $fechaPedida,
            'lugares' => $lugares,
            'lugar' => $lugar,
            'saldo' => $saldo,
            'inicio' => $inicio,
            'apertura' => $apertura,
            'movimientos' => $movimientos,
            'totalesMonedas' => $totalesMonedas,
            'tieneCriterios' => $armado !== []
                ? TesoreriaCrownBovedaListadoFiltros::tieneCriteriosAplicados($armado['filtros'])
                : false,
        ]));
    }

    public function listar(Request $request, $formato = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $jornadaId = (int) $request->input('jornada_id');
        $depositoId = (int) $request->input('deposito_id');
        if ($formato === null || $formato === '') {
            return redirect()->route('tesoreria_crown_boveda', $request->query());
        }
        $jornada = $this->jornadaEnAlcance($jornadaId);
        $lugar = $this->lugarDeSala((int) $jornada->sala_id, $depositoId);
        if ($lugar === null) {
            return redirect()->route('tesoreria_crown_boveda', [
                'sala_id' => $jornada->sala_id,
                'jornada_id' => $jornada->id,
            ])->with('mensaje-error', 'Elegí el lugar de la bóveda.');
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $filtros = $this->filtrosDe($request, (int) $jornada->sala_id, (int) $jornada->id, (int) $lugar->id);
        $filas = TesoreriaCrownBovedaListadoFiltros::consulta((int) $jornada->id, (int) $lugar->id, $filtros)->get();
        $catalogo = TesoreriaCrownBovedaListadoColumnas::COLUMNAS;
        $pedidas = array_values(array_filter(explode(',', (string) $request->query('columnas', ''))));
        $claves = array_values(array_intersect($pedidas, array_keys($catalogo)));
        if ($claves === []) {
            foreach ($catalogo as $clave => $meta) {
                if (! empty($meta['default'])) {
                    $claves[] = $clave;
                }
            }
        }
        $jornada->loadMissing('empresa');
        $nombreEmpresa = (string) ($jornada->empresa->nombre ?? '');
        foreach ($filas as $fila) {
            $fila->nombreempresa = $nombreEmpresa;
        }
        $columnas = [];
        $totales = ['ARS' => 0.0, 'USD' => 0.0, 'EUR' => 0.0];
        $filaTotales = [];
        foreach ($claves as $clave) {
            if ($clave === 'valor') {
                foreach (['ARS' => 'ARS', 'USD' => 'USD', 'EUR' => 'EUR'] as $codigo => $label) {
                    $columnas[] = [
                        'label' => $label,
                        'ancho' => 14,
                        'numero' => true,
                        'clave_moneda' => $codigo,
                    ];
                }

                continue;
            }
            $columnas[] = [
                'label' => $catalogo[$clave]['label'],
                'ancho' => match ($clave) {
                    'comentario' => 40,
                    'tipo' => 22,
                    'comprobante' => 16,
                    'numero' => 12,
                    default => 14,
                },
                'wrap' => in_array($clave, ['comentario', 'tipo'], true),
                'texto' => in_array($clave, ['numero', 'comprobante', 'tipo', 'efecto', 'estado', 'comentario'], true),
                'clave' => $clave,
            ];
        }
        $filasExport = $filas->map(function (TesoreriaCrownBoveda $fila) use ($claves, &$totales) {
            $celda = [];
            $montos = TesoreriaCrownBovedaListadoColumnas::montosPorMoneda($fila);
            foreach ($montos as $codigo => $monto) {
                $totales[$codigo] = round($totales[$codigo] + $monto, 2);
            }
            foreach ($claves as $clave) {
                if ($clave === 'valor') {
                    foreach (['ARS', 'USD', 'EUR'] as $codigo) {
                        $celda[] = abs($montos[$codigo]) < 0.0001 ? null : $montos[$codigo];
                    }

                    continue;
                }
                $celda[] = TesoreriaCrownBovedaListadoColumnas::valorCelda($fila, $clave);
            }

            return $celda;
        })->all();
        foreach ($columnas as $columna) {
            if (! empty($columna['clave_moneda'])) {
                $monto = $totales[$columna['clave_moneda']];
                $filaTotales[] = abs($monto) < 0.0001 ? null : $monto;
            } elseif (($columna['clave'] ?? '') === 'estado') {
                $filaTotales[] = 'Totales';
            } else {
                $filaTotales[] = '';
            }
        }
        $armado = [
            'titulo' => 'Bóveda '.$jornada->fecha->format('d/m/Y'),
            'subtitulo' => trim($lugar->codigo.' '.$lugar->nombre).' · '.$jornada->fecha->format('d/m/Y'),
            'archivo' => 'boveda_tesoreria_'.$jornada->fecha->format('Ymd'),
            'titulo_corto' => 'Bóveda',
            'columnas' => $columnas,
            'filas' => $filasExport,
            'totales' => $filaTotales,
            'coleccion' => $filas,
        ];
        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_boveda', TesoreriaCrownBovedaListadoFiltros::paraQueryString($filtros));
    }

    public function composicionBillete(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $lugar = $this->lugarDeSala((int) $jornada->sala_id, (int) $request->input('deposito_id'));
        $valor = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $jornada->sala_id)
            ->find((int) $request->input('valor_id'));
        if ($lugar === null || $valor === null) {
            abort(404);
        }
        $fecha = $jornada->fecha?->format('Y-m-d') ?? '';
        $composicion = TesoreriaCrownBovedaSupport::composicionBillete((int) $lugar->id, $fecha, (int) $valor->id);
        $denominacion = (float) $valor->denominacion;
        $denominacionTexto = abs($denominacion - round($denominacion)) < 0.001
            ? number_format($denominacion, 0, ',', '.')
            : number_format($denominacion, 2, ',', '.');

        return response()->json([
            'titulo' => trim((string) ($valor->tipoValor->nombre ?? '').' '.$denominacionTexto),
            'hasta' => $jornada->fecha?->format('d/m/Y') ?? '',
            'saldo' => $composicion['saldo'],
            'filas' => array_map(function (array $fila) {
                $fila['url'] = route('editar_tesoreria_crown_boveda', $fila['id']).'?'.http_build_query([
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);

                return $fila;
            }, $composicion['filas']),
        ]);
    }

    public function formulario(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $lugares = TesoreriaCrownBovedaSupport::lugares((int) $jornada->sala_id);
        $depositoId = (int) $request->input('deposito_id', 0);
        $lugar = $lugares->firstWhere('id', $depositoId) ?? $lugares->first(
            fn ($fila) => (string) ($fila->tipoDeposito->codigo ?? '') === 'BOVEDA'
        ) ?? $lugares->first();
        if ($lugar === null) {
            return redirect()->route('tesoreria_crown_boveda', [
                'sala_id' => $jornada->sala_id,
                'jornada_id' => $jornada->id,
            ])->with('mensaje-error', 'Esta sala no tiene un depósito de bóveda.');
        }
        if ($error = $this->errorJornada($jornada)) {
            return redirect()->route('tesoreria_crown_boveda', [
                'sala_id' => $jornada->sala_id,
                'jornada_id' => $jornada->id,
                'deposito_id' => $lugar->id,
            ])->with('mensaje-error', $error);
        }

        return view('caja.tesoreria_crown.boveda.crear', array_merge(
            $this->datosCarga((int) $jornada->sala_id, null, (string) $request->input('tipo_abrev', ''), $jornada->fecha->format('d/m/Y')),
            [
                'jornada' => $jornada,
                'lugar' => $lugar,
                'escritorio' => TesoreriaCrownEscritorioNav::armar($jornada),
                'editable' => true,
                'puedeConfirmar' => can('editar-tesoreria-crown-boveda', false),
                'ofrecerEmitir' => can('imprimir-tesoreria-crown-boveda', false),
            ]
        ));
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');
        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $depositoId = (int) $request->input('deposito_id');

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            TesoreriaCrownBovedaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $jornada->sala_id, (int) $jornada->id, $depositoId),
            TesoreriaCrownBovedaListadoColumnas::RECURSO,
            fn (array $filtros) => TesoreriaCrownBovedaListadoFiltros::consulta((int) $jornada->id, $depositoId, $filtros),
            [TesoreriaCrownBovedaListadoColumnas::class, 'valorCelda'],
            [TesoreriaCrownBovedaListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');
        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $depositoId = (int) $request->input('deposito_id');

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            TesoreriaCrownBovedaListadoColumnas::RECURSO,
            'tesoreria_crown_boveda',
            TesoreriaCrownBovedaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $jornada->sala_id, (int) $jornada->id, $depositoId),
            [TesoreriaCrownBovedaListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function eliminarVistaListado(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');
        $ok = ListadoVistaSupport::eliminar($id, TesoreriaCrownBovedaListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('tesoreria_crown_boveda', [
            'sala_id' => (int) $request->input('sala_id'),
            'jornada_id' => (int) $request->input('jornada_id'),
            'deposito_id' => (int) $request->input('deposito_id'),
            'vista_estandar' => 1,
        ])->with($ok ? 'mensaje' : 'mensaje-error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');
        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $depositoId = (int) $request->input('deposito_id');

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            TesoreriaCrownBovedaListadoColumnas::RECURSO,
            'tesoreria_crown_boveda',
            TesoreriaCrownBovedaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $jornada->sala_id, (int) $jornada->id, $depositoId),
            [TesoreriaCrownBovedaListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');
        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            TesoreriaCrownBovedaListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(TesoreriaCrownBovedaListadoColumnas::catalogoActivo())
        );

        return redirect()->route('tesoreria_crown_boveda', [
            'sala_id' => $jornada->sala_id,
            'jornada_id' => $jornada->id,
            'deposito_id' => (int) $request->input('deposito_id'),
        ])->with('mensaje', 'Etiquetas actualizadas.');
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $jornada = $this->jornadaEnAlcance((int) $request->input('jornada_id'));
        if ($error = $this->errorJornada($jornada)) {
            return back()->with('mensaje-error', $error);
        }
        $lugar = $this->lugarDeSala((int) $jornada->sala_id, (int) $request->input('deposito_id'));
        $tipo = $this->tipoPorId((int) $jornada->sala_id, (int) $request->input('tipo_id'));
        if ($lugar === null) {
            return back()->withInput()->with('mensaje-error', 'Elegí el lugar de la bóveda.');
        }
        if (is_string($tipo)) {
            return back()->withInput()->with('mensaje-error', $tipo);
        }
        $puedeConfirmar = can('editar-tesoreria-crown-boveda', false);

        try {
            $movimiento = DB::transaction(function () use ($jornada, $lugar, $tipo, $request, $puedeConfirmar) {
                $movimiento = TesoreriaCrownBoveda::query()->create([
                    'sala_id' => $jornada->sala_id,
                    'empresa_id' => $jornada->empresa_id,
                    'jornada_id' => $jornada->id,
                    'deposito_id' => $lugar->id,
                    'tipo_id' => $tipo->id,
                    'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $jornada->sala_id, 'V'),
                    'estado' => TesoreriaCrownBoveda::BORRADOR,
                    'comentario' => null,
                ]);
                if ($error = $this->persistirBorrador($request, $movimiento)) {
                    throw new \RuntimeException($error);
                }
                $movimiento->load(['tipo', 'lineas', 'deposito.tipoDeposito', 'jornada']);
                if ($puedeConfirmar) {
                    if ($error = TesoreriaCrownBovedaPredepositoSupport::validarRdep($movimiento)) {
                        throw new \RuntimeException($error);
                    }
                    if ($error = $this->aplicarConfirmacion($request, $movimiento)) {
                        throw new \RuntimeException($error);
                    }
                    $movimiento->refresh()->load(['tipo', 'lineas', 'deposito.tipoDeposito', 'jornada']);
                    if ($error = TesoreriaCrownBovedaPredepositoSupport::asentarEspejoPdep($movimiento)) {
                        throw new \RuntimeException($error);
                    }
                    if ($error = TesoreriaCrownBovedaPredepositoSupport::asentarBajaPredepositoRdep($movimiento)) {
                        throw new \RuntimeException($error);
                    }
                }

                return $movimiento;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('mensaje-error', $e->getMessage());
        }

        $redirect = redirect()
            ->route('tesoreria_crown_boveda', [
                'sala_id' => $movimiento->sala_id,
                'jornada_id' => $movimiento->jornada_id,
                'deposito_id' => $movimiento->deposito_id,
            ])
            ->with('mensaje', $puedeConfirmar ? 'Movimiento grabado.' : 'Borrador guardado.');
        if ($request->boolean('emitir') && can('imprimir-tesoreria-crown-boveda', false)) {
            $redirect->with('emitir_boveda', $movimiento->id);
        }

        return $redirect;
    }

    public function imprimir(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('imprimir-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        $empleado = $this->empleadoPorLegajo((int) $movimiento->legajo);
        $cliente = $movimiento->cliente_id
            ? Cliente::query()->find($movimiento->cliente_id)
            : null;
        $fechaJornada = $movimiento->jornada?->fecha?->format('Y-m-d');
        $pdf = Pdf::loadView('caja.tesoreria_crown.boveda.comprobante', [
            'movimiento' => $movimiento,
            'empleado' => $empleado,
            'cliente' => $cliente,
            'grupos' => $this->gruposComprobante($movimiento),
            'efectivoPesos' => $this->pesosDelMovimiento($movimiento),
            'cambiosDia' => $fechaJornada
                ? TesoreriaCrownCierreCalculo::cambiosDelDia((int) $movimiento->sala_id, $fechaJornada)
                : [],
            'fechaJornada' => $fechaJornada,
        ])->setPaper('a4', 'portrait');
        $nombre = 'Boveda '.TesoreriaCrownNumeradorSupport::visible((int) $movimiento->numero);

        return $pdf->stream($nombre.'.pdf');
    }

    public function editar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);

        return view('caja.tesoreria_crown.boveda.editar', array_merge(
            $this->datosCarga((int) $movimiento->sala_id, $movimiento),
            [
                'movimiento' => $movimiento,
                'editable' => $movimiento->esBorrador() && $movimiento->jornada->estaAbierta(),
                'puedeConfirmar' => can('editar-tesoreria-crown-boveda', false),
                'escritorio' => TesoreriaCrownEscritorioNav::armar($movimiento->jornada),
            ]
        ));
    }

    public function guardar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Este movimiento ya no se puede modificar.');
        }
        if ($error = $this->persistirBorrador($request, $movimiento)) {
            return back()->withInput()->with('mensaje-error', $error);
        }

        return back()->with('mensaje', 'Borrador guardado.');
    }

    public function confirmar(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if ($error = $this->errorJornada($movimiento->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if (! $movimiento->esBorrador()) {
            return back()->with('mensaje-error', 'Este movimiento ya no está en borrador.');
        }
        if ($request->exists('cantidad')) {
            if ($error = $this->persistirBorrador($request, $movimiento)) {
                return back()->withInput()->with('mensaje-error', $error);
            }
            $movimiento->unsetRelation('lineas');
        }
        $movimiento->load(['tipo', 'lineas', 'deposito.tipoDeposito', 'jornada']);
        if ($error = TesoreriaCrownBovedaPredepositoSupport::validarRdep($movimiento)) {
            return back()->withInput()->with('mensaje-error', $error);
        }
        if ($error = $this->aplicarConfirmacion($request, $movimiento)) {
            return back()->withInput()->with('mensaje-error', $error);
        }
        $movimiento->refresh()->load(['tipo', 'lineas', 'deposito.tipoDeposito', 'jornada']);
        if ($error = TesoreriaCrownBovedaPredepositoSupport::asentarEspejoPdep($movimiento)) {
            return back()->with('mensaje-error', $error);
        }
        if ($error = TesoreriaCrownBovedaPredepositoSupport::asentarBajaPredepositoRdep($movimiento)) {
            return back()->with('mensaje-error', $error);
        }

        return back()->with('mensaje', 'Movimiento grabado. El saldo de '.$movimiento->deposito->nombre.' quedó actualizado.');
    }

    public function revertir(Request $request, int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if ($error = $this->errorJornada($movimiento->jornada)) {
            return back()->with('mensaje-error', $error);
        }
        if ($movimiento->comprobante_id !== null) {
            return back()->with('mensaje-error', 'Este movimiento sale de una habilitación o una rendición. Revertí ese comprobante.');
        }
        if (! $movimiento->puedeRevertir()) {
            return back()->with('mensaje-error', 'Solo se revierte un movimiento confirmado, y una sola vez.');
        }
        $motivo = trim((string) $request->input('motivo'));
        if ($motivo === '' || mb_strlen($motivo) > 500) {
            return back()->with('mensaje-error', 'Escribí el motivo de la reversa.');
        }
        $limite = 'tc-boveda:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return back()->with('mensaje-error', 'Demasiados intentos. Esperá un minuto y volvé a probar.');
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownBovedaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return back()->with('mensaje-error', 'Usuario o clave de tesorería incorrectos.');
        }
        RateLimiter::clear($limite);

        $lineas = $movimiento->lineas->filter(fn ($linea) => abs((float) $linea->cantidad) >= 0.0001);
        if ($lineas->isEmpty()) {
            return back()->with('mensaje-error', 'Este movimiento no tiene cantidades para revertir.');
        }

        try {
            $nuevo = DB::transaction(function () use ($movimiento, $usuario, $motivo, $lineas) {
                $original = TesoreriaCrownBoveda::query()->whereKey($movimiento->id)->lockForUpdate()->first();
                if ($original === null || $original->estado !== TesoreriaCrownBoveda::CONFIRMADO || $original->revierte_id !== null) {
                    throw new \RuntimeException('Solo se revierte un movimiento confirmado, y una sola vez.');
                }
                if (TesoreriaCrownBoveda::query()->where('revierte_id', $original->id)->exists()) {
                    throw new \RuntimeException('Este movimiento ya fue revertido.');
                }

                $ahora = now();
                $nuevo = TesoreriaCrownBoveda::query()->create([
                    'sala_id' => $original->sala_id,
                    'empresa_id' => $original->empresa_id,
                    'jornada_id' => $original->jornada_id,
                    'deposito_id' => $original->deposito_id,
                    'tipo_id' => $original->tipo_id,
                    'numero' => TesoreriaCrownNumeradorSupport::siguiente((int) $original->sala_id, 'V'),
                    'estado' => TesoreriaCrownBoveda::CONFIRMADO,
                    'comentario' => 'Reversa de '.TesoreriaCrownNumeradorSupport::visible((int) $original->numero),
                    'motivo' => $motivo,
                    'hora' => $ahora->format('H:i'),
                    'legajo' => $original->legajo,
                    'cliente_id' => $original->cliente_id,
                    'revierte_id' => $original->id,
                    'usuario_id' => $usuario->id,
                    'confirmado_at' => $ahora,
                ]);
                foreach ($lineas as $linea) {
                    TesoreriaCrownBovedaLinea::query()->create([
                        'boveda_id' => $nuevo->id,
                        'valor_id' => $linea->valor_id,
                        'cantidad' => round((float) $linea->cantidad * -1, 2),
                    ]);
                }

                return $nuevo;
            });
        } catch (\RuntimeException $e) {
            return back()->with('mensaje-error', $e->getMessage());
        }

        return back()->with(
            'mensaje',
            'Reversa '.TesoreriaCrownNumeradorSupport::visible((int) $nuevo->numero).' grabada. El movimiento original sigue y este lo compensa en el saldo.'
        );
    }

    public function eliminar(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-boveda');

        $movimiento = $this->movimientoEnAlcance($id);
        if (! $movimiento->esBorrador() || ! $movimiento->jornada->estaAbierta()) {
            return back()->with('mensaje-error', 'Solo se borra un borrador de una jornada abierta.');
        }
        $jornadaId = (int) $movimiento->jornada_id;
        DB::transaction(function () use ($movimiento) {
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            $movimiento->delete();
        });

        return redirect()
            ->route('tesoreria_crown_boveda', ['jornada_id' => $jornadaId])
            ->with('mensaje', 'Borrador eliminado.');
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function workbench(Request $request, int $salaId, int $jornadaId, int $depositoId): array|\Illuminate\Http\RedirectResponse
    {
        return TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            TesoreriaCrownBovedaListadoColumnas::RECURSO,
            'tesoreria_crown_boveda',
            TesoreriaCrownBovedaListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId, $jornadaId, $depositoId),
            [TesoreriaCrownBovedaListadoFiltros::class, 'camposOrdenables'],
            [TesoreriaCrownBovedaListadoFiltros::class, 'camposQbe'],
            [TesoreriaCrownBovedaListadoFiltros::class, 'normalizarOperador'],
            [TesoreriaCrownBovedaListadoFiltros::class, 'paraQueryString'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDe(Request $request, int $salaId, int $jornadaId, int $depositoId): array
    {
        return TesoreriaCrownBovedaListadoFiltros::resolverDesdeRequest($request, $salaId, $jornadaId, $depositoId);
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->find($id);
        if ($jornada === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }

    private function movimientoEnAlcance(int $id): TesoreriaCrownBoveda
    {
        $movimiento = TesoreriaCrownBoveda::query()
            ->with(['jornada', 'deposito.tipoDeposito', 'tipo', 'lineas.valor.tipoValor', 'comprobante', 'usuario', 'revierte', 'reversa'])
            ->find($id);
        if ($movimiento === null) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $movimiento->sala_id);

        return $movimiento;
    }

    /**
     * @return array<string, array{titulo: string, filas: list<TesoreriaCrownBovedaLinea>}>
     */
    private function gruposComprobante(TesoreriaCrownBoveda $movimiento): array
    {
        $orden = ['ARS' => 0, 'USD' => 1, 'EUR' => 2];
        $grupos = [];
        foreach ($movimiento->lineas as $linea) {
            if ((float) $linea->cantidad == 0.0) {
                continue;
            }
            $codigo = (string) ($linea->valor->tipoValor->codigo ?? '');
            if (! isset($grupos[$codigo])) {
                $grupos[$codigo] = [
                    'titulo' => (string) ($linea->valor->tipoValor->nombre ?? $codigo),
                    'filas' => [],
                ];
            }
            $grupos[$codigo]['filas'][] = $linea;
        }
        uksort($grupos, function (string $a, string $b) use ($orden): int {
            return ($orden[$a] ?? 9) <=> ($orden[$b] ?? 9);
        });
        foreach ($grupos as $codigo => $grupo) {
            usort($grupos[$codigo]['filas'], function (TesoreriaCrownBovedaLinea $a, TesoreriaCrownBovedaLinea $b): int {
                return ((float) $b->valor->denominacion) <=> ((float) $a->valor->denominacion);
            });
        }

        return $grupos;
    }

    private function lugarDeSala(int $salaId, int $depositoId): ?\App\Models\Caja\TesoreriaCrownDeposito
    {
        return TesoreriaCrownBovedaSupport::lugares($salaId)->firstWhere('id', $depositoId);
    }

    private function errorJornada(TesoreriaCrownJornada $jornada): ?string
    {
        return $jornada->estaAbierta() ? null : 'La jornada está cerrada. El movimiento queda en consulta.';
    }

    private function tipoPorAbreviatura(int $salaId, string $abreviatura): ?TesoreriaCrownBovedaTipo
    {
        $abreviatura = strtoupper(trim($abreviatura));
        if ($abreviatura === '') {
            return null;
        }

        return TesoreriaCrownBovedaTipo::query()
            ->where('sala_id', $salaId)
            ->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$abreviatura])
            ->first();
    }

    private function comprobantePorNumero(TesoreriaCrownJornada $jornada, string $tipo, string $numero): ?TesoreriaCrownComprobante
    {
        $tipo = strtoupper(trim($tipo));
        $numero = (int) preg_replace('/\D/', '', $numero);
        if ($numero <= 0 || ! isset(TesoreriaCrownComprobanteSupport::TIPOS[$tipo])) {
            return null;
        }

        return $this->comprobantesConformes($jornada)
            ->first(fn (TesoreriaCrownComprobante $doc) => $doc->tipo === $tipo && (int) $doc->numero === $numero);
    }

    /**
     * @return \Illuminate\Support\Collection<int, TesoreriaCrownComprobante>
     */
    private function comprobantesConformes(TesoreriaCrownJornada $jornada)
    {
        return TesoreriaCrownComprobante::query()
            ->with(['lineas', 'asignacion.deposito', 'asignacion.cajero'])
            ->where('jornada_id', $jornada->id)
            ->where('estado', TesoreriaCrownComprobanteSupport::CONFORME)
            ->orderBy('numero')
            ->get();
    }

    private function copiarLineas(TesoreriaCrownBoveda $movimiento, TesoreriaCrownComprobante $comprobante): void
    {
        foreach ($comprobante->lineas as $linea) {
            $cantidad = (float) $linea->cantidad;
            if ($cantidad <= 0) {
                continue;
            }
            TesoreriaCrownBovedaLinea::query()->create([
                'boveda_id' => $movimiento->id,
                'valor_id' => $linea->valor_id,
                'cantidad' => $cantidad,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function datosCarga(int $salaId, ?TesoreriaCrownBoveda $movimiento, string $tipoPedido = '', ?string $fecha = null): array
    {
        $tipos = TesoreriaCrownBovedaSupport::tiposParaCarga($salaId);
        $tipo = $movimiento?->tipo;
        $tipoIdOld = (int) old('tipo_id');
        if ($tipoIdOld > 0) {
            $tipo = TesoreriaCrownBovedaTipo::query()->where('sala_id', $salaId)->whereKey($tipoIdOld)->first() ?? $tipo;
        }
        if (! $tipo instanceof TesoreriaCrownBovedaTipo && $tipoPedido !== '') {
            $pedido = strtoupper(trim($tipoPedido));
            $tipo = $tipos->first(function (TesoreriaCrownBovedaTipo $fila) use ($pedido) {
                return strtoupper(trim((string) $fila->abreviatura)) === $pedido
                    || (string) $fila->codigo === $pedido;
            });
        }
        $legajo = $movimiento?->legajo;
        $empleado = $this->empleadoPorLegajo((int) $legajo);
        $empresaLegajoId = ParametroSistemaSupport::empresaTesoreriaCrownId();
        $cliente = $movimiento?->cliente_id
            ? Cliente::query()->find($movimiento->cliente_id)
            : null;
        $grupos = $this->gruposEfectivo($salaId);
        $cantidades = $movimiento ? $movimiento->lineas->pluck('cantidad', 'valor_id') : collect();
        $fechaJornada = $movimiento?->jornada?->fecha?->format('Y-m-d');
        if ($fechaJornada === null && is_string($fecha) && $fecha !== '') {
            $parseada = \DateTime::createFromFormat('!d/m/Y', $fecha);
            $fechaJornada = $parseada ? $parseada->format('Y-m-d') : null;
        }
        $efectivoPesos = $this->pesosDesdeGrupos($salaId, $fechaJornada, $grupos, $cantidades);
        $monedasPesos = [];
        foreach ($efectivoPesos['monedas'] ?? [] as $monedaPesos) {
            $monedasPesos[$monedaPesos['codigo']] = $monedaPesos;
        }

        return [
            'numero' => $movimiento ? TesoreriaCrownNumeradorSupport::visible((int) $movimiento->numero) : null,
            'fecha' => $movimiento?->jornada?->fecha?->format('d/m/Y') ?? $fecha,
            'fechaJornada' => $fechaJornada,
            'efectivoPesos' => $efectivoPesos,
            'monedasPesos' => $monedasPesos,
            'cambiosDia' => $fechaJornada
                ? TesoreriaCrownCierreCalculo::cambiosDelDia($salaId, $fechaJornada)
                : [],
            'tipoId' => $tipo?->id ?? '',
            'tipoCodigo' => $tipo?->codigo ?? '',
            'tipoNombre' => $tipo ? trim($tipo->abreviatura.' — '.$tipo->nombre) : '',
            'efecto' => $tipo ? TesoreriaCrownBovedaSupport::efectoTexto((string) $tipo->signo) : '',
            'grupos' => $grupos,
            'cantidades' => $cantidades,
            'comentario' => $movimiento?->comentario,
            'tipos' => $tipos,
            'monedas' => $tipo ? TesoreriaCrownBovedaSupport::monedasDelTipo($tipo) : ['ARS', 'USD', 'EUR'],
            'referencia' => $tipo ? (TesoreriaCrownBovedaSupport::referenciaDelTipo($tipo) ?? '') : '',
            'legajo' => $legajo ?: '',
            'empresaLegajoId' => $empresaLegajoId,
            'empleadoId' => $empleado?->id ?? '',
            'empleadoNombre' => $empleado?->nombre ?? '',
            'clienteId' => $cliente?->id ?? '',
            'clienteCodigo' => $cliente?->codigo ?? '',
            'clienteNombre' => $cliente?->nombre ?? '',
            'subtipoId' => old('subtipo_id', $movimiento?->subtipo_id ?? ''),
            'subtipoTexto' => old('subtipo_texto', $movimiento?->subtipo_texto ?? ''),
            'subtipos' => $tipo
                ? TesoreriaCrownKardexValoresSupport::subtiposDeTipo((int) $tipo->id)
                : collect(),
        ];
    }

    private function resolverTipo(int $salaId, string $texto): TesoreriaCrownBovedaTipo|string
    {
        $texto = strtoupper(trim($texto));
        if ($texto === '' || $texto === '0') {
            return 'Elegí el código de transacción.';
        }
        $tipo = ctype_digit($texto)
            ? TesoreriaCrownBovedaTipo::query()->where('sala_id', $salaId)->where('codigo', (int) $texto)->first()
            : TesoreriaCrownBovedaTipo::query()->where('sala_id', $salaId)->whereRaw('UPPER(TRIM(abreviatura)) = ?', [$texto])->first();
        if ($tipo === null) {
            return 'Código de transacción inexistente.';
        }

        return $this->tipoGuardable($tipo);
    }

    private function tipoPorId(int $salaId, int $id): TesoreriaCrownBovedaTipo|string
    {
        if ($id <= 0) {
            return 'Elegí el código de transacción.';
        }
        $tipo = TesoreriaCrownBovedaTipo::query()->where('sala_id', $salaId)->whereKey($id)->first();
        if ($tipo === null) {
            return 'Código de transacción inexistente.';
        }

        return $this->tipoGuardable($tipo);
    }

    private function tipoGuardable(TesoreriaCrownBovedaTipo $tipo): TesoreriaCrownBovedaTipo|string
    {
        $abreviatura = strtoupper(trim((string) $tipo->abreviatura));
        if (in_array($abreviatura, ['HAB', 'REN'], true)) {
            return 'La habilitación y la rendición entran solas cuando el cajero firma.';
        }
        if (! $tipo->activo && $abreviatura !== 'APER') {
            return 'Transacción suspendida.';
        }
        if (stripos((string) $tipo->nombre, 'NO USAR') === 0) {
            return 'Operación no permitida.';
        }

        return $tipo;
    }

    /**
     * @return array<string, mixed>
     */
    private function tipoJson(TesoreriaCrownBovedaTipo $tipo): array
    {
        return [
            'id' => (int) $tipo->id,
            'codigo' => (int) $tipo->codigo,
            'abreviatura' => (string) $tipo->abreviatura,
            'nombre' => trim($tipo->abreviatura.' — '.$tipo->nombre),
            'efecto' => TesoreriaCrownBovedaSupport::efectoTexto((string) $tipo->signo),
            'monedas' => TesoreriaCrownBovedaSupport::monedasDelTipo($tipo),
            'referencia' => TesoreriaCrownBovedaSupport::referenciaDelTipo($tipo) ?? '',
        ];
    }

    /**
     * @param  list<array{codigo?: string, titulo?: string, filas?: mixed}>  $grupos
     * @param  iterable<int|string, mixed>  $cantidades
     * @return array{total: float, completo: bool, monedas: list<array<string, mixed>>}|null
     */
    private function pesosDesdeGrupos(int $salaId, ?string $fechaJornada, array $grupos, iterable $cantidades): ?array
    {
        if ($fechaJornada === null || $fechaJornada === '') {
            return null;
        }
        $paraCalculo = [];
        foreach ($grupos as $grupo) {
            $grupo['filas'] = collect($grupo['filas'] ?? []);
            $paraCalculo[] = $grupo;
        }

        return TesoreriaCrownCierreCalculo::efectivoEnPesos($salaId, $fechaJornada, $paraCalculo, $cantidades);
    }

    private function pesosDelMovimiento(TesoreriaCrownBoveda $movimiento): ?array
    {
        $grupos = [];
        $cantidades = [];
        foreach ($movimiento->lineas as $linea) {
            $valor = $linea->valor;
            if ($valor === null || (float) $linea->cantidad == 0.0) {
                continue;
            }
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            $grupos[$codigo]['codigo'] = $codigo;
            $grupos[$codigo]['titulo'] = (string) ($valor->tipoValor->nombre ?? $codigo);
            $grupos[$codigo]['filas'][$valor->id] = $valor;
            $cantidades[$valor->id] = ($cantidades[$valor->id] ?? 0) + (float) $linea->cantidad;
        }

        return $this->pesosDesdeGrupos(
            (int) $movimiento->sala_id,
            $movimiento->jornada?->fecha?->format('Y-m-d'),
            array_values($grupos),
            $cantidades,
        );
    }

    /**
     * @return list<array{codigo: string, titulo: string, filas: list<mixed>}>
     */
    private function gruposEfectivo(int $salaId): array
    {
        $grupos = [];
        foreach (TesoreriaCrownBovedaSupport::filasEfectivo($salaId) as $valor) {
            $codigo = (string) ($valor->tipoValor->codigo ?? '');
            $grupos[$codigo]['codigo'] = $codigo;
            $grupos[$codigo]['titulo'] = (string) ($valor->tipoValor->nombre ?? $codigo);
            $grupos[$codigo]['filas'][] = $valor;
        }

        return array_values($grupos);
    }

    public function consultaTipo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        if (
            ! can('listar-tesoreria-crown-boveda', false)
            && ! can('crear-tesoreria-crown-boveda', false)
            && ! can('listar-tesoreria-crown-kardex-valores', false)
        ) {
            abort(403);
        }
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $texto = mb_strtoupper(trim((string) $request->input('consulta')));
        $tipos = TesoreriaCrownBovedaSupport::tiposParaCarga($salaId)
            ->filter(function (TesoreriaCrownBovedaTipo $tipo) use ($texto) {
                if ($texto === '') {
                    return true;
                }
                $blob = mb_strtoupper($tipo->codigo.' '.$tipo->abreviatura.' '.$tipo->nombre);

                return str_contains($blob, $texto);
            })
            ->map(fn (TesoreriaCrownBovedaTipo $tipo) => $this->tipoJson($tipo))
            ->values();

        return response()->json(['data' => $tipos]);
    }

    public function resolverTipoCodigo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        if (
            ! can('listar-tesoreria-crown-boveda', false)
            && ! can('crear-tesoreria-crown-boveda', false)
            && ! can('listar-tesoreria-crown-kardex-valores', false)
        ) {
            abort(403);
        }
        $salaId = (int) $request->input('sala_id');
        TesoreriaCrownSalaSupport::exigida($salaId);
        $tipo = $this->resolverTipo($salaId, (string) $request->input('codigo'));
        if (is_string($tipo)) {
            return response()->json(['ok' => false]);
        }

        return response()->json(['ok' => true, 'tipo' => $this->tipoJson($tipo)]);
    }

    private function aplicarConfirmacion(Request $request, TesoreriaCrownBoveda $movimiento): ?string
    {
        if ($movimiento->lineas()->doesntExist()) {
            return 'No se puede grabar una operación en cero.';
        }
        if (trim((string) $request->input('firma_usuario')) === '' || trim((string) $request->input('firma_password')) === '') {
            return 'Completá el usuario y la clave de tesorería.';
        }
        $limite = 'tc-boveda:'.$movimiento->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($limite, 8)) {
            return 'Demasiados intentos. Esperá un minuto y volvé a probar.';
        }
        $usuario = TesoreriaCrownFirmaSupport::usuarioPorClave(
            (string) $request->input('firma_usuario'),
            (string) $request->input('firma_password')
        );
        if ($usuario === null || ! TesoreriaCrownBovedaSupport::puedeConfirmar($usuario)) {
            RateLimiter::hit($limite, 60);

            return 'Usuario o clave de tesorería incorrectos.';
        }
        RateLimiter::clear($limite);
        $movimiento->estado = TesoreriaCrownBoveda::CONFIRMADO;
        $movimiento->usuario_id = $usuario->id;
        $movimiento->confirmado_at = now();
        $movimiento->save();
        $movimiento->loadMissing('jornada');
        if ($movimiento->jornada) {
            TesoreriaCrownAnexo8Calculo::invalidarCache($movimiento->jornada);
        }

        return null;
    }

    private function empleadoPorLegajo(int $legajo): ?Empleado_Sueldos
    {
        if ($legajo <= 0) {
            return null;
        }

        $query = Empleado_Sueldos::query()->where('legajo', $legajo);
        $empresaId = ParametroSistemaSupport::empresaTesoreriaCrownId();
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        return $query->orderBy('id')->first();
    }

    private function persistirBorrador(Request $request, TesoreriaCrownBoveda $movimiento): ?string
    {
        $datos = $request->validate([
            'comentario' => ['required', 'string', 'max:80'],
            'tipo_id' => ['required', 'integer', 'min:1'],
            'subtipo_id' => ['nullable', 'integer', 'min:0'],
            'subtipo_texto' => ['nullable', 'string', 'max:80'],
            'legajo' => ['nullable', 'integer', 'min:0'],
            'cliente_id' => ['nullable', 'integer', 'min:0'],
            'comprobante_tipo' => ['nullable', 'string', 'max:1'],
            'comprobante_numero' => ['nullable', 'string', 'max:12'],
            'cantidad' => ['nullable', 'array'],
            'cantidad.*' => ['nullable', 'integer', 'min:0'],
        ]);
        if ($this->comentario($datos['comentario']) === null) {
            return 'No se puede grabar una operación sin observaciones.';
        }
        $tipo = $this->tipoPorId((int) $movimiento->sala_id, (int) $datos['tipo_id']);
        if (is_string($tipo)) {
            return $tipo;
        }
        $subtipoId = (int) ($datos['subtipo_id'] ?? 0);
        $subtipoTexto = trim((string) ($datos['subtipo_texto'] ?? ''));
        $subtiposTipo = TesoreriaCrownKardexValoresSupport::subtiposDeTipo((int) $tipo->id);
        if ($subtiposTipo->isNotEmpty()) {
            if ($subtipoId <= 0 || ! $subtiposTipo->contains('id', $subtipoId)) {
                return 'Elegí el subtipo del movimiento.';
            }
            $subtipo = $subtiposTipo->firstWhere('id', $subtipoId);
            if ($subtipo && strtoupper((string) $subtipo->codigo) === 'OTR' && $subtipoTexto === '') {
                return 'Completá el detalle del subtipo «Otro».';
            }
        } else {
            $subtipoId = 0;
            $subtipoTexto = '';
        }
        $referencia = TesoreriaCrownBovedaSupport::referenciaDelTipo($tipo);
        $legajo = null;
        $clienteId = null;
        if ($referencia === 'legajo') {
            $legajo = (int) ($datos['legajo'] ?? 0);
            $empleado = $this->empleadoPorLegajo($legajo);
            if ($empleado === null) {
                return 'Ingresá un legajo que exista.';
            }
        }
        if ($referencia === 'cliente') {
            $clienteId = (int) ($datos['cliente_id'] ?? 0);
            if ($clienteId <= 0 || Cliente::query()->whereKey($clienteId)->doesntExist()) {
                return 'Ingresá un cliente que exista.';
            }
        }
        $monedas = TesoreriaCrownBovedaSupport::monedasDelTipo($tipo);
        $valores = TesoreriaCrownValor::query()
            ->with('tipoValor')
            ->where('sala_id', $movimiento->sala_id)
            ->where('activo', true)
            ->get()
            ->filter(fn (TesoreriaCrownValor $valor) => in_array((string) ($valor->tipoValor->codigo ?? ''), $monedas, true))
            ->pluck('id')
            ->all();
        $lineas = [];
        foreach ((array) ($datos['cantidad'] ?? []) as $valorId => $cantidad) {
            $cantidad = (int) $cantidad;
            if ($cantidad <= 0 || ! in_array((int) $valorId, $valores, true)) {
                continue;
            }
            $lineas[(int) $valorId] = $cantidad;
        }
        if ($lineas === []) {
            return 'No se puede grabar una operación en cero.';
        }
        $comprobanteId = $movimiento->comprobante_id;
        if ($request->exists('comprobante_numero') || $request->exists('comprobante_tipo')) {
            $comprobante = $this->comprobantePorNumero(
                $movimiento->jornada,
                (string) ($datos['comprobante_tipo'] ?? ''),
                (string) ($datos['comprobante_numero'] ?? '')
            );
            if (trim((string) ($datos['comprobante_numero'] ?? '')) !== '' && $comprobante === null) {
                return 'Ese comprobante no es una habilitación o rendición conforme de esta jornada.';
            }
            $comprobanteId = $comprobante?->id;
        }

        DB::transaction(function () use ($movimiento, $datos, $tipo, $legajo, $clienteId, $lineas, $comprobanteId, $subtipoId, $subtipoTexto) {
            $movimiento->tipo_id = $tipo->id;
            $movimiento->subtipo_id = $subtipoId > 0 ? $subtipoId : null;
            $movimiento->subtipo_texto = $subtipoTexto !== '' ? mb_substr($subtipoTexto, 0, 80) : null;
            $movimiento->comentario = $this->comentario($datos['comentario']);
            if (trim((string) $movimiento->hora) === '') {
                $movimiento->hora = ($movimiento->created_at ?? now())->format('H:i');
            }
            $movimiento->legajo = $legajo;
            $movimiento->cliente_id = $clienteId;
            $movimiento->comprobante_id = $comprobanteId;
            $movimiento->save();
            EloquentAuditDeleteSupport::each($movimiento->lineas()->getQuery());
            foreach ($lineas as $valorId => $cantidad) {
                TesoreriaCrownBovedaLinea::query()->create([
                    'boveda_id' => $movimiento->id,
                    'valor_id' => $valorId,
                    'cantidad' => $cantidad,
                ]);
            }
        });

        return null;
    }

    private function comentario(mixed $texto): ?string
    {
        $texto = trim((string) $texto);

        return $texto === '' ? null : mb_substr($texto, 0, 80);
    }
}
