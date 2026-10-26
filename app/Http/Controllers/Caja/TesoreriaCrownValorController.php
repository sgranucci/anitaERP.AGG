<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTesoreriaCrownTipoValor;
use App\Http\Requests\ValidacionTesoreriaCrownValor;
use App\Models\Caja\TesoreriaCrownTipoValor;
use App\Models\Caja\TesoreriaCrownValor;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use App\Support\Caja\TesoreriaCrownValorListadoFiltros;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TesoreriaCrownValorController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-valor');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownValorListadoFiltros::resolverDesdeRequest($request);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $filtrosQuery = TesoreriaCrownValorListadoFiltros::paraQueryString($filtros);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $datas = $this->consulta($filtros)->paginate(15)->appends($filtrosQuery);

        return view('caja.tesoreria_crown.valor.index', [
            'datas' => $datas,
            'tipos' => $tipos,
            'salas' => $salas,
            'sala' => $sala,
            'filtros' => $filtros,
            'filtrosQuery' => $filtrosQuery,
            'tieneCriterios' => TesoreriaCrownValorListadoFiltros::tieneCriteriosAplicados($filtros),
            'subtitulo' => $this->subtitulo($filtros, $tipos, $sala),
        ]);
    }

    public function listar(Request $request, $formato = null, $busqueda = null)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-valor');

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);
        $filtros = TesoreriaCrownValorListadoFiltros::resolverDesdeRequest($request, $busqueda);
        $filtros['sala_id'] = (int) ($sala->id ?? 0);
        $tipos = $this->tiposDeSala((int) ($sala->id ?? 0));
        $coleccion = $this->consulta($filtros)->get();
        $armado = [
            'titulo' => 'Valores de tesorería',
            'subtitulo' => $this->subtitulo($filtros, $tipos, $sala),
            'archivo' => 'valores_tesoreria_crown',
            'titulo_corto' => 'Valores tesorería',
            'columnas' => [
                ['label' => 'Tipo', 'ancho' => 32, 'texto' => true],
                ['label' => 'Serie', 'ancho' => 18, 'texto' => true],
                ['label' => 'Descripción', 'ancho' => 36, 'wrap' => true],
                ['label' => 'Denominación', 'ancho' => 18, 'numero' => true],
                ['label' => 'Controla stock', 'ancho' => 18],
                ['label' => 'Activo', 'ancho' => 12],
            ],
            'filas' => $coleccion->map(function (TesoreriaCrownValor $valor) {
                return [
                    $valor->tipoValor?->etiqueta() ?? '',
                    $valor->serie,
                    $valor->descripcion,
                    (float) $valor->denominacion,
                    ($valor->tipoValor && $valor->tipoValor->controla_stock) ? 'Sí' : 'No',
                    $valor->activo ? 'Sí' : 'No',
                ];
            })->all(),
            'coleccion' => $coleccion,
        ];

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $armado);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('tesoreria_crown_valor', TesoreriaCrownValorListadoFiltros::paraQueryString($filtros));
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-valor');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        $valor = new TesoreriaCrownValor([
            'sala_id' => $sala->id,
            'activo' => true,
            'orden' => 0,
            'denominacion' => 0,
        ]);

        return view('caja.tesoreria_crown.valor.form', [
            'valor' => $valor,
            'sala' => $sala,
            'tipos' => $this->tiposActivos((int) $sala->id),
            'accion' => route('guardar_tesoreria_crown_valor'),
            'metodo' => 'POST',
        ]);
    }

    public function guardar(ValidacionTesoreriaCrownValor $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-valor');

        $datos = $this->datosValor($request);
        TesoreriaCrownValor::create($datos);

        return redirect()->route('tesoreria_crown_valor', ['sala_id' => $datos['sala_id']])->with('mensaje', 'Valor creado.');
    }

    public function editar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-valor');

        $valor = TesoreriaCrownValor::query()->findOrFail($id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $valor->sala_id);
        $tipos = $this->tiposActivos((int) $valor->sala_id);
        if ($valor->tipo_valor_id && ! $tipos->contains('id', $valor->tipo_valor_id)) {
            $actual = TesoreriaCrownTipoValor::query()->find($valor->tipo_valor_id);
            if ($actual) {
                $tipos->push($actual);
            }
        }

        return view('caja.tesoreria_crown.valor.form', [
            'valor' => $valor,
            'sala' => $sala,
            'tipos' => $tipos,
            'accion' => route('actualizar_tesoreria_crown_valor', $id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizar(ValidacionTesoreriaCrownValor $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-valor');

        $valor = TesoreriaCrownValor::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $valor->sala_id);
        $datos = $this->datosValor($request);
        $datos['sala_id'] = (int) $valor->sala_id;
        $valor->update($datos);

        return redirect()->route('tesoreria_crown_valor', ['sala_id' => $valor->sala_id])->with('mensaje', 'Valor actualizado.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-valor');

        $valor = TesoreriaCrownValor::query()->findOrFail($id);
        $salaId = (int) $valor->sala_id;
        TesoreriaCrownSalaSupport::exigida($salaId);
        $valor->delete();

        return redirect()->route('tesoreria_crown_valor', ['sala_id' => $salaId])->with('mensaje', 'Valor eliminado.');
    }

    public function indexTipos(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-valor');

        $salas = TesoreriaCrownSalaSupport::salas();
        $sala = TesoreriaCrownSalaSupport::pedida($request, $salas);

        return view('caja.tesoreria_crown.tipo_valor.index', [
            'salas' => $salas,
            'sala' => $sala,
            'tipos' => $this->tiposDeSala((int) ($sala->id ?? 0)),
        ]);
    }

    public function crearTipo(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-valor');

        $sala = TesoreriaCrownSalaSupport::pedida($request);
        if (! $sala) {
            abort(404);
        }

        return view('caja.tesoreria_crown.tipo_valor.form', [
            'tipo' => new TesoreriaCrownTipoValor([
                'sala_id' => $sala->id,
                'activo' => true,
                'orden' => 0,
                'controla_stock' => true,
            ]),
            'sala' => $sala,
            'accion' => route('guardar_tesoreria_crown_tipo_valor'),
            'metodo' => 'POST',
        ]);
    }

    public function guardarTipo(ValidacionTesoreriaCrownTipoValor $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-valor');

        $salaId = 0;
        DB::transaction(function () use ($request, &$salaId) {
            $datos = $this->datosTipo($request);
            $salaId = (int) $datos['sala_id'];
            $this->dejarUnaMonedaReferencia($datos['es_moneda_referencia'], $salaId, null);
            TesoreriaCrownTipoValor::create($datos);
        });

        return redirect()->route('tesoreria_crown_tipo_valor', ['sala_id' => $salaId])->with('mensaje', 'Tipo de valor creado.');
    }

    public function editarTipo($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-valor');

        $tipo = TesoreriaCrownTipoValor::query()->findOrFail($id);
        $sala = TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);

        return view('caja.tesoreria_crown.tipo_valor.form', [
            'tipo' => $tipo,
            'sala' => $sala,
            'accion' => route('actualizar_tesoreria_crown_tipo_valor', $id),
            'metodo' => 'PUT',
        ]);
    }

    public function actualizarTipo(ValidacionTesoreriaCrownTipoValor $request, $id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('actualizar-tesoreria-crown-valor');

        $tipo = TesoreriaCrownTipoValor::query()->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $tipo->sala_id);
        DB::transaction(function () use ($request, $tipo) {
            $datos = $this->datosTipo($request, $tipo->codigo);
            $datos['sala_id'] = (int) $tipo->sala_id;
            $this->dejarUnaMonedaReferencia($datos['es_moneda_referencia'], (int) $tipo->sala_id, (int) $tipo->id);
            $tipo->update($datos);
        });

        return redirect()->route('tesoreria_crown_tipo_valor', ['sala_id' => $tipo->sala_id])->with('mensaje', 'Tipo de valor actualizado.');
    }

    public function eliminarTipo($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-valor');

        $tipo = TesoreriaCrownTipoValor::query()->findOrFail($id);
        $salaId = (int) $tipo->sala_id;
        TesoreriaCrownSalaSupport::exigida($salaId);
        if ($tipo->valores()->exists() || $tipo->cotizaciones()->exists()) {
            return redirect()->route('tesoreria_crown_tipo_valor', ['sala_id' => $salaId])->with(
                'mensaje-error',
                'El tipo tiene valores o cotizaciones. No se puede eliminar.'
            );
        }
        $tipo->delete();

        return redirect()->route('tesoreria_crown_tipo_valor', ['sala_id' => $salaId])->with('mensaje', 'Tipo de valor eliminado.');
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function consulta(array $filtros)
    {
        $query = TesoreriaCrownValor::query()->with('tipoValor');
        TesoreriaCrownValorListadoFiltros::aplicar($query, $filtros);

        return $query
            ->orderBy(
                TesoreriaCrownTipoValor::query()
                    ->select('orden')
                    ->whereColumn('tesoreria_crown_tipo_valor.id', 'tesoreria_crown_valor.tipo_valor_id')
            )
            ->orderBy('tesoreria_crown_valor.orden')
            ->orderBy('tesoreria_crown_valor.serie');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, TesoreriaCrownTipoValor>  $tipos
     */
    private function subtitulo(array $filtros, $tipos, $sala): string
    {
        $partes = [];
        if ($sala) {
            $partes[] = trim($sala->codigo.' '.$sala->nombre);
        }
        $tipoId = (int) ($filtros['tipo_valor_id'] ?? 0);
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

    /**
     * @return array<string, mixed>
     */
    private function datosValor(ValidacionTesoreriaCrownValor $request): array
    {
        $datos = $request->validated();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $datos['sala_id']);

        return [
            'sala_id' => (int) $sala->id,
            'tipo_valor_id' => (int) $datos['tipo_valor_id'],
            'serie' => trim((string) $datos['serie']),
            'descripcion' => trim((string) $datos['descripcion']),
            'denominacion' => $datos['denominacion'],
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function datosTipo(ValidacionTesoreriaCrownTipoValor $request, ?string $codigoFijo = null): array
    {
        $datos = $request->validated();
        $sala = TesoreriaCrownSalaSupport::exigida((int) $datos['sala_id']);
        $codigo = $codigoFijo ?? strtoupper(trim((string) $datos['codigo']));

        return [
            'sala_id' => (int) $sala->id,
            'codigo' => $codigo,
            'nombre' => trim((string) $datos['nombre']),
            'es_moneda_referencia' => TesoreriaCrownAcceso::marcado($datos['es_moneda_referencia']),
            'controla_stock' => TesoreriaCrownAcceso::marcado($datos['controla_stock']),
            'cotizacion_fija' => TesoreriaCrownAcceso::marcado($datos['cotizacion_fija']),
            'orden' => (int) ($datos['orden'] ?? 0),
            'activo' => TesoreriaCrownAcceso::marcado($datos['activo']),
        ];
    }

    private function dejarUnaMonedaReferencia(bool $esReferencia, int $salaId, ?int $exceptoId): void
    {
        if (! $esReferencia) {
            return;
        }

        $query = TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->where('es_moneda_referencia', true);
        if ($exceptoId) {
            $query->where('id', '!=', $exceptoId);
        }
        $query->get()->each(function (TesoreriaCrownTipoValor $otro) {
            $otro->es_moneda_referencia = false;
            $otro->save();
        });
    }

    private function tiposActivos(int $salaId)
    {
        return TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }

    private function tiposDeSala(int $salaId)
    {
        return TesoreriaCrownTipoValor::query()
            ->where('sala_id', $salaId)
            ->orderBy('orden')
            ->orderBy('codigo')
            ->get();
    }
}
