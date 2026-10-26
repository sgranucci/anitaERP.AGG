<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownDeposito;
use App\Http\Requests\ValidacionTesoreriaCrownTipoDeposito;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownTipoDeposito;
use App\Models\Configuracion\Sala;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownDepositoListadoFiltros;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TesoreriaCrownDepositoController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-deposito');

        $empresas = $this->empresas();
        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = $this->filtros($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $filtrosQuery = TesoreriaCrownDepositoListadoFiltros::paraQueryString($filtros);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $datas = $this->consulta($filtros)->paginate(15)->appends($filtrosQuery);

        return view('caja.tesoreria_crown.deposito.index', [
            'datas' => $datas,
            'tipos' => $tipos,
            'salas' => $salas,
            'sala' => $sala,
            'empresa_query' => $empresas,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tieneCriterios' => TesoreriaCrownDepositoListadoFiltros::tieneCriteriosAplicados($filtros),
            'subtitulo' => $this->subtitulo($filtros, $salas, $tipos, $empresas),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-deposito');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $empresas = $this->empresas();
        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = $this->filtros($request, $busqueda);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $coleccion = $this->consulta($filtros)->get();
        foreach ($coleccion as $deposito) {
            $deposito->nombreempresa = $deposito->empresa->nombre ?? '';
        }

        $armado = [
            'titulo' => 'Depósitos de tesorería',
            'subtitulo' => $this->subtitulo($filtros, $salas, $tipos, $empresas),
            'archivo' => 'depositos_tesoreria_crown',
            'titulo_corto' => 'Depósitos tesorería',
            'columnas' => [
                ['label' => 'Código', 'ancho' => 16, 'texto' => true],
                ['label' => 'Nombre', 'ancho' => 32, 'wrap' => true],
                ['label' => 'Tipo', 'ancho' => 28],
                ['label' => 'Sala', 'ancho' => 22],
                ['label' => 'Empresa', 'ancho' => 28, 'wrap' => true],
                ['label' => 'Activo', 'ancho' => 12],
            ],
            'filas' => $coleccion->map(function (TesoreriaCrownDeposito $deposito) {
                $sala = trim(($deposito->sala->codigo ?? '').' '.($deposito->sala->nombre ?? ''));

                return [
                    $deposito->codigo,
                    $deposito->nombre,
                    $deposito->tipoDeposito?->etiqueta() ?? '',
                    $sala,
                    $deposito->empresa->nombre ?? '',
                    $deposito->activo ? 'Sí' : 'No',
                ];
            })->all(),
            'coleccion' => $coleccion,
        ];

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route(
            'tesoreria_crown_deposito',
            TesoreriaCrownDepositoListadoFiltros::paraQueryString($filtros)
        );
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-deposito');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        return view('caja.tesoreria_crown.deposito.form', [
            'deposito' => new TesoreriaCrownDeposito([
                'sala_id' => $sala->id,
                'empresa_id' => $sala->empresa_id,
                'activo' => true,
                'orden' => 0,
            ]),
            'sala' => $sala,
            'tipos' => $this->tiposActivos((int) $sala->id),
            'accion' => route('guardar_tesoreria_crown_deposito'),
            'metodo' => 'POST',
        ]);
    }

    public function guardar(ValidacionTesoreriaCrownDeposito $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-deposito');

        $error = $this->validarEmpresaSala($request);
        if ($error) {
            return $error;
        }

        $datos = $this->datos($request);
        TesoreriaCrownDeposito::create($datos);

        return redirect()->route('tesoreria_crown_deposito', ['sala_id' => $datos['sala_id']])->with('mensaje', 'Depósito creado.');
    }

    public function editar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-deposito');

        $empresas = $this->empresas();
        $deposito = $this->depositoEnAlcance($id, $empresas);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $deposito->sala_id);
        $tipos = $this->tiposActivos((int) $sala->id);
        if ($deposito->tipo_deposito_id && ! $tipos->contains('id', $deposito->tipo_deposito_id)) {
            $actual = TesoreriaCrownTipoDeposito::query()->find($deposito->tipo_deposito_id);
            if ($actual) {
                $tipos->push($actual);
            }
        }

        return view('caja.tesoreria_crown.deposito.form', [
            'deposito' => $deposito,
            'sala' => $sala,
            'tipos' => $tipos,
            'accion' => route('actualizar_tesoreria_crown_deposito', $deposito->id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizar(ValidacionTesoreriaCrownDeposito $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-deposito');

        $deposito = $this->depositoEnAlcance($id, $this->empresas());
        if ((int) $request->input('sala_id') !== (int) $deposito->sala_id) {
            return back()->withErrors(['sala_id' => 'La sala del depósito no se cambia desde acá.'])->withInput();
        }
        $error = $this->validarEmpresaSala($request);
        if ($error) {
            return $error;
        }
        $datos = $this->datos($request);
        $datos['sala_id'] = (int) $deposito->sala_id;
        $datos['empresa_id'] = (int) $deposito->empresa_id;
        $deposito->update($datos);

        return redirect()->route('tesoreria_crown_deposito', ['sala_id' => $deposito->sala_id])->with('mensaje', 'Depósito actualizado.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-deposito');

        $deposito = $this->depositoEnAlcance($id, $this->empresas());
        $salaId = (int) $deposito->sala_id;
        $deposito->delete();

        return redirect()->route('tesoreria_crown_deposito', ['sala_id' => $salaId])->with('mensaje', 'Depósito eliminado.');
    }

    public function indexTipos(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-deposito');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);

        return view('caja.tesoreria_crown.tipo_deposito.index', [
            'salas' => $salas,
            'sala' => $sala,
            'tipos' => $this->tiposDeSala((int) ($sala->id ?? 0)),
        ]);
    }

    public function crearTipo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-deposito');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        return view('caja.tesoreria_crown.tipo_deposito.form', [
            'tipo' => new TesoreriaCrownTipoDeposito([
                'sala_id' => $sala->id,
                'activo' => true,
                'orden' => 0,
            ]),
            'sala' => $sala,
            'accion' => route('guardar_tesoreria_crown_tipo_deposito'),
            'metodo' => 'POST',
        ]);
    }

    public function guardarTipo(ValidacionTesoreriaCrownTipoDeposito $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-deposito');

        $datos = $this->datosTipo($request);
        TesoreriaCrownTipoDeposito::create($datos);

        return redirect()->route('tesoreria_crown_tipo_deposito', ['sala_id' => $datos['sala_id']])->with('mensaje', 'Tipo de depósito creado.');
    }

    public function editarTipo($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-deposito');

        $tipo = TesoreriaCrownTipoDeposito::query()->findOrFail($id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);

        return view('caja.tesoreria_crown.tipo_deposito.form', [
            'tipo' => $tipo,
            'sala' => $sala,
            'accion' => route('actualizar_tesoreria_crown_tipo_deposito', $id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizarTipo(ValidacionTesoreriaCrownTipoDeposito $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-deposito');

        $tipo = TesoreriaCrownTipoDeposito::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);
        $datos = $this->datosTipo($request);
        $datos['codigo'] = $tipo->codigo;
        $datos['sala_id'] = (int) $tipo->sala_id;
        $tipo->update($datos);

        return redirect()->route('tesoreria_crown_tipo_deposito', ['sala_id' => $tipo->sala_id])->with('mensaje', 'Tipo de depósito actualizado.');
    }

    public function eliminarTipo($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-deposito');

        $tipo = TesoreriaCrownTipoDeposito::query()->findOrFail($id);
        $salaId = (int) $tipo->sala_id;
        TesoreriaCrownSalaSupport::exigida($salaId);
        if ($tipo->depositos()->exists()) {
            return redirect()->route('tesoreria_crown_tipo_deposito', ['sala_id' => $salaId])->with(
                'mensaje-error',
                'El tipo tiene depósitos. No se puede eliminar.'
            );
        }
        $tipo->delete();

        return redirect()->route('tesoreria_crown_tipo_deposito', ['sala_id' => $salaId])->with('mensaje', 'Tipo de depósito eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function filtros(Request $request, ?string $busqueda = null): array
    {
        $filtros = TesoreriaCrownDepositoListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['empresa_ids'] = $this->empresas()->pluck('id')->map(fn ($id) => (int) $id)->all();

        return $filtros;
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function consulta(array $filtros)
    {
        $query = TesoreriaCrownDeposito::query()->with(['empresa', 'sala', 'tipoDeposito']);
        TesoreriaCrownDepositoListadoFiltros::aplicar($query, $filtros);

        return $query
            ->orderBy(
                Sala::query()->select('codigo')->whereColumn('sala.id', 'tesoreria_crown_deposito.sala_id')
            )
            ->orderBy('tesoreria_crown_deposito.orden')
            ->orderBy('tesoreria_crown_deposito.codigo');
    }

    private function empresas(): Collection
    {
        return collect($this->empresaRepository->allFiltrado());
    }

    private function tiposActivos(int $salaId): Collection
    {
        return TesoreriaCrownTipoDeposito::query()
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    private function tiposDeSala(int $salaId): Collection
    {
        return TesoreriaCrownTipoDeposito::query()
            ->where('sala_id', $salaId)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    private function depositoEnAlcance($id, Collection $empresas): TesoreriaCrownDeposito
    {
        $ids = $empresas->pluck('id')->map(fn ($empresaId) => (int) $empresaId)->all();

        return TesoreriaCrownDeposito::query()
            ->whereIn('empresa_id', $ids === [] ? [0] : $ids)
            ->findOrFail($id);
    }

    private function validarEmpresaSala(ValidacionTesoreriaCrownDeposito $request)
    {
        $empresaId = (int) $request->input('empresa_id');
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            return back()->withErrors(['empresa_id' => 'La empresa no está asignada.'])->withInput();
        }

        $sala = Sala::query()->find((int) $request->input('sala_id'));
        if (! $sala || (int) $sala->empresa_id !== $empresaId) {
            return back()->withErrors(['sala_id' => 'La sala no pertenece a la empresa.'])->withInput();
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(ValidacionTesoreriaCrownDeposito $request): array
    {
        $datos = $request->validated();

        return [
            'empresa_id' => (int) $datos['empresa_id'],
            'sala_id' => (int) $datos['sala_id'],
            'tipo_deposito_id' => (int) $datos['tipo_deposito_id'],
            'codigo' => strtoupper(trim((string) $datos['codigo'])),
            'nombre' => trim((string) $datos['nombre']),
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosTipo(ValidacionTesoreriaCrownTipoDeposito $request): array
    {
        $datos = $request->validated();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $datos['sala_id']);

        return [
            'sala_id' => (int) $sala->id,
            'codigo' => strtoupper(trim((string) $datos['codigo'])),
            'nombre' => trim((string) $datos['nombre']),
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
        ];
    }

    /**
     * @param  Collection<int, Sala>  $salas
     * @param  Collection<int, TesoreriaCrownTipoDeposito>  $tipos
     * @param  Collection<int, mixed>  $empresas
     */
    private function subtitulo(array $filtros, Collection $salas, Collection $tipos, Collection $empresas): string
    {
        $partes = [];
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId > 0) {
            $empresa = $empresas->firstWhere('id', $empresaId);
            if ($empresa) {
                $partes[] = 'Empresa '.$empresa->nombre;
            }
        }
        $salaId = (int) ($filtros['sala_id'] ?? 0);
        if ($salaId > 0) {
            $sala = $salas->firstWhere('id', $salaId);
            if ($sala) {
                $partes[] = 'Sala '.$sala->codigo;
            }
        }
        $tipoId = (int) ($filtros['tipo_deposito_id'] ?? 0);
        if ($tipoId > 0) {
            $tipo = $tipos->firstWhere('id', $tipoId);
            if ($tipo) {
                $partes[] = 'Tipo '.$tipo->etiqueta();
            }
        }
        $texto = trim((string) ($filtros['valor'] ?? ''));
        if ($texto !== '') {
            $partes[] = 'Texto '.$texto;
        }

        return implode(' · ', $partes);
    }
}
