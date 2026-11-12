<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownCajero;
use App\Models\Caja\Caja;
use App\Models\Caja\CajeroCrown;
use App\Models\Caja\CajeroCrownExterno;
use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownMesa;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\MozoGastronomia;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Support\Caja\CajeroCrownListadoColumnas;
use App\Support\Caja\CajeroCrownListadoFiltros;
use App\Support\Caja\CajeroCrownSistemaExternoSupport;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownTareaCatalogo;
use App\Support\Caja\TesoreriaCrownWorkbenchSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use App\Support\Listado\ListadoColumnaEtiquetaSupport;
use App\Support\Listado\ListadoCortesSupport;
use App\Support\Listado\ListadoVistaSupport;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownCajeroController extends Controller
{
    public function __construct(
        private readonly UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $armado = $this->workbench($request, $sala ? (int) $sala->id : 0);
        if ($armado instanceof \Illuminate\Http\RedirectResponse) {
            return $armado;
        }
        $consulta = $this->consulta($armado['filtros']);
        $cortes = ListadoCortesSupport::calcular(
            clone $consulta,
            (array) ($armado['filtros']['agrupar'] ?? []),
            CajeroCrownListadoFiltros::camposOrdenables(),
            'cajero_crown.id',
            [CajeroCrownListadoColumnas::class, 'valorCelda'],
            null,
            $armado['etiquetasColumnas'] ?? []
        );
        $filas = (clone $consulta)->paginate(15)->appends($armado['filtrosQuery']);
        $filas->getCollection()->load(['usuario', 'caja', 'mozoGastronomia', 'externos']);

        return view('caja.tesoreria_crown.cajero.index', array_merge($armado, [
            'salas' => $salas,
            'sala' => $sala,
            'filas' => $filas,
            'cortes' => $cortes,
            'tieneCriterios' => CajeroCrownListadoFiltros::tieneCriteriosAplicados($armado['filtros']),
        ]));
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $vista = $this->workbench($request, (int) ($sala->id ?? 0));
        if ($vista instanceof \Illuminate\Http\RedirectResponse) {
            return $vista;
        }
        $filtros = $vista['filtros'];
        if (is_string($busqueda) && $busqueda !== '') {
            $filtros['valor'] = $busqueda;
        }
        $coleccion = $this->consulta($filtros)->get();
        $coleccion->load(['usuario', 'caja', 'mozoGastronomia', 'externos', 'empresa']);
        foreach ($coleccion as $fila) {
            $fila->nombreempresa = $fila->empresa->nombre ?? '';
        }
        $claves = $vista['columnasVisibles'] ?? [];
        if ($claves === []) {
            $claves = array_keys(array_filter(
                CajeroCrownListadoColumnas::COLUMNAS,
                fn (array $meta) => ! empty($meta['default'])
            ));
        }
        $etiquetas = $vista['etiquetasColumnas'] ?? [];
        $columnas = [];
        foreach ($claves as $clave) {
            $columnas[] = [
                'label' => $etiquetas[$clave] ?? CajeroCrownListadoColumnas::COLUMNAS[$clave]['label'],
                'ancho' => match ($clave) {
                    'nombre' => 32,
                    'externos' => 22,
                    'codigo', 'legajo', 'activo' => 12,
                    default => 20,
                },
                'texto' => in_array($clave, ['codigo', 'legajo'], true),
                'wrap' => in_array($clave, ['nombre', 'externos', 'usuario', 'caja'], true),
            ];
        }
        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, [
            'titulo' => 'Cajeros de tesorería',
            'subtitulo' => $this->subtitulo($filtros, $sala),
            'archivo' => 'cajeros_tesoreria_crown',
            'titulo_corto' => 'Cajeros',
            'columnas' => $columnas,
            'filas' => $coleccion->map(function (CajeroCrown $fila) use ($claves) {
                $celda = [];
                foreach ($claves as $clave) {
                    $celda[] = CajeroCrownListadoColumnas::valorCelda($fila, $clave);
                }

                return $celda;
            })->all(),
            'coleccion' => $coleccion,
        ]);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_cajero', CajeroCrownListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-cajero');
        $sala = $this->salaPedida($request);

        return view('caja.tesoreria_crown.cajero.form', [
            'sala' => $sala,
            'cajero' => new CajeroCrown([
                'sala_id' => $sala->id,
                'empresa_id' => $sala->empresa_id,
                'tipo_tarea' => 'T',
                'permite_rendir_sin_habilitacion' => false,
                'activo' => true,
            ]),
            'externos' => collect(),
            'sistemas' => CajeroCrownSistemaExternoSupport::sistemas(),
            'tareas' => TesoreriaCrownTareaCatalogo::TAREAS,
            'edicion' => false,
            'accion' => route('guardar_tesoreria_crown_cajero'),
            'metodo' => 'POST',
        ]);
    }

    public function editar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-cajero');
        $cajero = $this->cajeroEnAlcance((int) $id);
        $cajero->load(['usuario', 'caja', 'mozoGastronomia', 'externos.mozoGastronomia']);

        return view('caja.tesoreria_crown.cajero.form', [
            'sala' => $cajero->sala,
            'cajero' => $cajero,
            'externos' => $cajero->externos,
            'sistemas' => CajeroCrownSistemaExternoSupport::sistemas(),
            'tareas' => TesoreriaCrownTareaCatalogo::TAREAS,
            'edicion' => true,
            'accion' => route('actualizar_tesoreria_crown_cajero', $cajero->id),
            'metodo' => 'PUT',
        ]);
    }

    public function guardar(ValidacionTesoreriaCrownCajero $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-cajero');
        $sala = $this->salaPedida($request);
        $datos = $this->datosCabecera($request, $sala, null);
        $externos = $this->datosExternos($request, $sala);

        $cajero = DB::transaction(function () use ($datos, $externos) {
            $cajero = CajeroCrown::query()->create($datos);
            $this->sincronizarExternos($cajero, $externos);

            return $cajero;
        });

        return redirect()
            ->route('tesoreria_crown_cajero', ['sala_id' => $cajero->sala_id])
            ->with('mensaje', 'Cajero '.$cajero->codigo.' creado.');
    }

    public function actualizar(ValidacionTesoreriaCrownCajero $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-cajero');
        $cajero = $this->cajeroEnAlcance((int) $id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $cajero->sala_id);
        $datos = $this->datosCabecera($request, $sala, $cajero);
        $externos = $this->datosExternos($request, $sala);

        DB::transaction(function () use ($cajero, $datos, $externos) {
            $cajero->update($datos);
            $this->sincronizarExternos($cajero, $externos);
        });

        return redirect()
            ->route('tesoreria_crown_cajero', ['sala_id' => $cajero->sala_id])
            ->with('mensaje', 'Cajero '.$cajero->codigo.' actualizado.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-cajero');
        $cajero = $this->cajeroEnAlcance((int) $id);
        $salaId = (int) $cajero->sala_id;
        if (TesoreriaCrownAsignacion::query()->where('cajero_crown_id', $cajero->id)->exists()
            || TesoreriaCrownMesa::query()->where('cajero_crown_id', $cajero->id)->exists()) {
            return redirect()
                ->route('tesoreria_crown_cajero', ['sala_id' => $salaId])
                ->with('mensaje-error', 'El cajero tiene movimientos o asignaciones. No se puede eliminar.');
        }
        $codigo = $cajero->codigo;
        DB::transaction(function () use ($cajero) {
            EloquentAuditDeleteSupport::each($cajero->externos()->getQuery());
            $cajero->delete();
        });

        return redirect()
            ->route('tesoreria_crown_cajero', ['sala_id' => $salaId])
            ->with('mensaje', 'Cajero '.$codigo.' eliminado.');
    }

    public function previewWorkbench(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());

        return TesoreriaCrownWorkbenchSupport::preview(
            $request,
            CajeroCrownListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $sala ? (int) $sala->id : 0),
            CajeroCrownListadoColumnas::RECURSO,
            fn (array $filtros) => $this->consulta($filtros),
            [CajeroCrownListadoColumnas::class, 'valorCelda'],
            [CajeroCrownListadoFiltros::class, 'camposOrdenables'],
        );
    }

    public function guardarVistaListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');

        return TesoreriaCrownWorkbenchSupport::guardarVista(
            $request,
            CajeroCrownListadoColumnas::RECURSO,
            'tesoreria_crown_cajero',
            CajeroCrownListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $request->input('sala_id', 0)),
            [CajeroCrownListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function eliminarVistaListado(int $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');
        $ok = ListadoVistaSupport::eliminar($id, CajeroCrownListadoColumnas::RECURSO, (int) auth()->id());

        return redirect()->route('tesoreria_crown_cajero', ['vista_estandar' => 1])
            ->with($ok ? 'mensaje' : 'mensaje-error', $ok ? 'Vista eliminada.' : 'No se pudo eliminar la vista.');
    }

    public function guardarColumnasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');

        return TesoreriaCrownWorkbenchSupport::guardarColumnas(
            $request,
            CajeroCrownListadoColumnas::RECURSO,
            'tesoreria_crown_cajero',
            CajeroCrownListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, (int) $request->input('sala_id', 0)),
            [CajeroCrownListadoFiltros::class, 'paraQueryString'],
        );
    }

    public function guardarEtiquetasListado(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-cajero');
        $etiquetas = $request->input('etiquetas', []);
        ListadoColumnaEtiquetaSupport::guardar(
            CajeroCrownListadoColumnas::RECURSO,
            is_array($etiquetas) ? $etiquetas : [],
            array_keys(CajeroCrownListadoColumnas::catalogoActivo())
        );

        return redirect()->route('tesoreria_crown_cajero', $request->query())
            ->with('mensaje', 'Etiquetas actualizadas.');
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function workbench(Request $request, int $salaId): array|\Illuminate\Http\RedirectResponse
    {
        return TesoreriaCrownWorkbenchSupport::preparar(
            $request,
            CajeroCrownListadoColumnas::RECURSO,
            'tesoreria_crown_cajero',
            CajeroCrownListadoColumnas::catalogoActivo(),
            fn (Request $req) => $this->filtrosDe($req, $salaId),
            [CajeroCrownListadoFiltros::class, 'camposOrdenables'],
            [CajeroCrownListadoFiltros::class, 'camposQbe'],
            [CajeroCrownListadoFiltros::class, 'normalizarOperador'],
            [CajeroCrownListadoFiltros::class, 'paraQueryString'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function filtrosDe(Request $request, int $salaId): array
    {
        $filtros = CajeroCrownListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = $salaId;

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<CajeroCrown>
     */
    private function consulta(array $filtros): Builder
    {
        $query = CajeroCrown::query()->with(['usuario', 'caja', 'mozoGastronomia', 'externos']);
        CajeroCrownListadoFiltros::aplicar($query, $filtros);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function subtitulo(array $filtros, ?Sala $sala): string
    {
        $partes = [];
        if ($sala) {
            $partes[] = $sala->codigo.' — '.$sala->nombre;
        }
        $estado = (string) ($filtros['filtro_estado'] ?? CajeroCrownListadoFiltros::FILTRO_ACTIVOS);
        $partes[] = match ($estado) {
            CajeroCrownListadoFiltros::FILTRO_INACTIVOS => 'Inactivos',
            CajeroCrownListadoFiltros::FILTRO_TODOS => 'Todos',
            default => 'Activos',
        };

        return implode(' · ', $partes);
    }

    private function salaPedida(Request $request): Sala
    {
        $sala = TesoreriaCrownSalaSupport::pedida($request, TesoreriaCrownSalaSupport::salas());
        if (! $sala) {
            abort(404);
        }

        return $sala;
    }

    private function cajeroEnAlcance(int $id): CajeroCrown
    {
        $cajero = CajeroCrown::query()->with('sala')->find($id);
        if (! $cajero || ! $cajero->sala) {
            abort(404);
        }
        TesoreriaCrownSalaSupport::exigida((int) $cajero->sala_id);

        return $cajero;
    }

    /**
     * @return array<string, mixed>
     */
    private function datosCabecera(ValidacionTesoreriaCrownCajero $request, Sala $sala, ?CajeroCrown $existente): array
    {
        $datos = $request->validated();
        $usuarioId = (int) ($datos['usuario_id'] ?? 0);
        $usuario = null;
        if ($usuarioId > 0) {
            $usuario = $this->usuarioRepository->findOperativo($usuarioId);
            if (! $usuario || ! UsuarioOperativoSupport::esOperativo($usuario)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'usuario_id' => 'El usuario no es operativo.',
                ]);
            }
        }
        $cajaId = $this->resolverCajaId($sala, trim((string) ($datos['caja_codigo'] ?? '')));
        $mozoId = $this->resolverMozoId($sala, trim((string) ($datos['mozo_codigo'] ?? '')));
        $tipoTarea = (string) ($datos['tipo_tarea'] ?? 'T');
        $permiteSinHab = (bool) ($datos['permite_rendir_sin_habilitacion'] ?? false);
        if (! $request->has('permite_rendir_sin_habilitacion')
            && TesoreriaCrownTareaCatalogo::sugiereRendirSinHabilitacion($tipoTarea)) {
            $permiteSinHab = true;
        }
        $out = [
            'empresa_id' => (int) $sala->empresa_id,
            'sala_id' => (int) $sala->id,
            'codigo' => (int) $datos['codigo'],
            'nombre' => trim((string) $datos['nombre']),
            'legajo' => (($datos['legajo'] ?? '') === '' || $datos['legajo'] === null) ? null : (int) $datos['legajo'],
            'usuario_id' => $usuario?->id,
            'caja_id' => $cajaId,
            'mozo_gastronomia_id' => $mozoId,
            'tipo_tarea' => $tipoTarea,
            'permite_rendir_sin_habilitacion' => $permiteSinHab,
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo'] ?? 1),
        ];
        $clave = trim((string) ($datos['clave'] ?? ''));
        if ($clave !== '') {
            $out['clave'] = $clave;
        } elseif ($existente === null) {
            $out['clave'] = (string) $out['codigo'];
        }

        return $out;
    }

    /**
     * @return list<array{sistema: string, codigo_externo: ?string, mozo_gastronomia_id: ?int}>
     */
    private function datosExternos(ValidacionTesoreriaCrownCajero $request, Sala $sala): array
    {
        $filas = (array) $request->input('externos', []);
        $out = [];
        $vistos = [];
        foreach ($filas as $fila) {
            if (! is_array($fila)) {
                continue;
            }
            $sistema = CajeroCrownSistemaExternoSupport::normalizar((string) ($fila['sistema'] ?? ''));
            if (! CajeroCrownSistemaExternoSupport::esValido($sistema) || isset($vistos[$sistema])) {
                continue;
            }
            $codigoExterno = trim((string) ($fila['codigo_externo'] ?? ''));
            $mozoCodigo = trim((string) ($fila['mozo_codigo'] ?? ''));
            $mozoId = null;
            if (CajeroCrownSistemaExternoSupport::usaMozo($sistema)) {
                $mozoId = $this->resolverMozoId($sala, $mozoCodigo);
                if ($mozoId === null && $mozoCodigo === '') {
                    continue;
                }
                $codigoExterno = null;
            } else {
                if ($codigoExterno === '') {
                    continue;
                }
            }
            $vistos[$sistema] = true;
            $out[] = [
                'sistema' => $sistema,
                'codigo_externo' => $codigoExterno !== '' ? mb_substr($codigoExterno, 0, 30) : null,
                'mozo_gastronomia_id' => $mozoId,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{sistema: string, codigo_externo: ?string, mozo_gastronomia_id: ?int}>  $externos
     */
    private function sincronizarExternos(CajeroCrown $cajero, array $externos): void
    {
        $sistemas = array_column($externos, 'sistema');
        EloquentAuditDeleteSupport::each(
            CajeroCrownExterno::query()
                ->where('cajero_crown_id', $cajero->id)
                ->when($sistemas !== [], fn ($q) => $q->whereNotIn('sistema', $sistemas), fn ($q) => $q)
        );
        foreach ($externos as $fila) {
            CajeroCrownExterno::query()->updateOrCreate(
                [
                    'cajero_crown_id' => $cajero->id,
                    'sistema' => $fila['sistema'],
                ],
                [
                    'codigo_externo' => $fila['codigo_externo'],
                    'mozo_gastronomia_id' => $fila['mozo_gastronomia_id'],
                ]
            );
        }
    }

    private function resolverCajaId(Sala $sala, string $codigo): ?int
    {
        if ($codigo === '') {
            return null;
        }
        $caja = Caja::query()
            ->where('empresa_id', $sala->empresa_id)
            ->where(function ($q) use ($sala) {
                $q->where('sala_id', $sala->id)->orWhereNull('sala_id');
            })
            ->where('codigo', $codigo)
            ->first();

        return $caja?->id;
    }

    private function resolverMozoId(Sala $sala, string $codigo): ?int
    {
        if ($codigo === '') {
            return null;
        }
        $mozo = MozoGastronomia::query()
            ->where('empresa_id', $sala->empresa_id)
            ->where(function ($q) use ($sala) {
                $q->where('sala_id', $sala->id)->orWhereNull('sala_id');
            })
            ->where('codigo', $codigo)
            ->first();

        return $mozo?->id;
    }
}
