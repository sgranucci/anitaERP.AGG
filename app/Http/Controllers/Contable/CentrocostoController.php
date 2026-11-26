<?php

namespace App\Http\Controllers\Contable;

use App\Support\Configuracion\AnitaSyncIndexSupport;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\ValidacionCentrocosto;
use App\Repositories\Contable\CentrocostoRepositoryInterface;

class CentrocostoController extends Controller
{
	private $repository;

    public function __construct(CentrocostoRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        can('listar-centro-costo');
        if (! Centrocosto::query()->exists() && AnitaSyncIndexSupport::autoImportHabilitado()) {
            $this->repository->sincronizarConAnita();
        }
		$datas = $this->repository->all();

        return view('contable.centrocosto.index', compact('datas'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function crear()
    {
        can('crear-centro-costo');
        $tipoiva_enum = Centrocosto::$enumTipoIva;

        return view('contable.centrocosto.crear', compact('tipoiva_enum'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function guardar(ValidacionCentrocosto $request)
    {
		$this->repository->create($request->all());

        return redirect('contable/centrocosto')->with('mensaje', 'Centro de costo creado con éxito');
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function editar($id)
    {
        $consulta = request()->query('origen') === 'modal_consulta'
            || request()->query('vista') === 'consulta';
        if ($consulta) {
            if (! can('listar-centro-costo', false)
                && ! can('editar-centro-costo', false)
                && ! can('actualizar-centro-costo', false)) {
                can('listar-centro-costo');
            }
        } else {
            can('editar-centro-costo');
        }
        $data = $this->repository->findOrFail($id);
        $tipoiva_enum = Centrocosto::$enumTipoIva;

        return view('contable.centrocosto.editar', compact('data', 'tipoiva_enum'));
    }

    /**
     * Updote the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function actualizar(ValidacionCentrocosto $request, $id)
    {
        can('actualizar-centro-costo');

        $this->repository->update($request->all(), $id);

        return redirect('contable/centrocosto')->with('mensaje', 'Centro de costo actualizado con éxito');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function eliminar(Request $request, $id)
    {
        can('borrar-centro-costo');

        if ($request->ajax()) {
        	if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            } else {
                return response()->json(['mensaje' => 'ng']);
            }
        } else {
            abort(404);
        }
    }

    public function consultaCentrocosto(Request $request)
    {
        if (! $this->puedeConsultarCentrocosto()) {
            abort(403);
        }

        $consulta = trim((string) ($request->input('consulta') ?? ''));
        $query = Centrocosto::query()->select('id', 'codigo', 'nombre', 'abreviatura');

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('codigo', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('abreviatura', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderByRaw(SqlDialectSupport::ordenCodigoAsc('codigo'))->orderBy('nombre')->limit(200)->get();
        $puedeAbrirAbm = can('editar-centro-costo', false) || can('listar-centro-costo', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="codigo">'.e($row->codigo).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="abreviatura">'.e($row->abreviatura ?? '').'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultacentrocosto">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $url = route('editar_centrocosto', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($url).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function resolverCentrocosto(Request $request)
    {
        if (! $this->puedeConsultarCentrocosto()) {
            abort(403);
        }

        $valor = trim((string) $request->query('valor', ''));
        if ($valor === '') {
            return response()->json(['ok' => false]);
        }

        $cc = Centrocosto::query()
            ->where('codigo', $valor)
            ->first(['id', 'codigo', 'nombre']);

        if (! $cc) {
            return response()->json(['ok' => false, 'mensaje' => 'Centro de costo no encontrado']);
        }

        return response()->json([
            'ok' => true,
            'id' => (int) $cc->id,
            'codigo' => (string) $cc->codigo,
            'nombre' => (string) $cc->nombre,
        ]);
    }

    /**
     * Modal de grupos de centros de costo (los grupos no tienen maestro propio:
     * salen de centrocosto.grupo_codigo / grupo_nombre).
     */
    public function consultaGrupoCentrocosto(Request $request)
    {
        if (! $this->puedeConsultarCentrocosto()) {
            abort(403);
        }

        $consulta = mb_strtolower(trim((string) ($request->input('consulta') ?? '')));
        $grupos = $this->gruposCentrocosto();

        if ($consulta !== '') {
            $grupos = array_filter($grupos, static function (array $g) use ($consulta): bool {
                return str_contains(mb_strtolower($g['codigo']), $consulta)
                    || str_contains(mb_strtolower($g['nombre']), $consulta);
            });
        }

        $output = ['data' => ''];
        if ($grupos === []) {
            $output['data'] = '<tr><td colspan="4">Sin resultados</td></tr>';
        } else {
            foreach (array_slice(array_values($grupos), 0, 200) as $g) {
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="codigo">'.e($g['codigo']).'</td>';
                $output['data'] .= '<td class="nombre">'.e($g['nombre']).'</td>';
                $output['data'] .= '<td class="cantidad text-right">'.e((string) $g['cantidad']).'</td>';
                $output['data'] .= '<td class="text-nowrap"><a class="btn btn-warning btn-sm eligeconsultagrupocentrocosto">Elegir</a></td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function resolverGrupoCentrocosto(Request $request)
    {
        if (! $this->puedeConsultarCentrocosto()) {
            abort(403);
        }

        $valor = \App\Support\Contable\MovCcostoListadoFiltros::normalizarGrupo((string) $request->query('valor', ''));
        if (trim((string) $request->query('valor', '')) === '') {
            return response()->json(['ok' => false]);
        }

        foreach ($this->gruposCentrocosto() as $g) {
            if ($g['codigo'] === $valor) {
                return response()->json([
                    'ok' => true,
                    'codigo' => $g['codigo'],
                    'nombre' => $g['nombre'],
                ]);
            }
        }

        return response()->json(['ok' => false, 'mensaje' => 'Grupo de centro de costo no encontrado']);
    }

    /**
     * @return list<array{codigo: string, nombre: string, cantidad: int}>
     */
    private function gruposCentrocosto(): array
    {
        $grupos = [];
        foreach (Centrocosto::query()->get(['grupo_codigo', 'grupo_nombre']) as $cc) {
            $codigo = \App\Support\Contable\MovCcostoListadoFiltros::normalizarGrupo($cc->grupo_codigo);
            $grupos[$codigo] ??= ['codigo' => $codigo, 'nombre' => '', 'cantidad' => 0];
            $grupos[$codigo]['cantidad']++;
            if ($grupos[$codigo]['nombre'] === '' && trim((string) $cc->grupo_nombre) !== '') {
                $grupos[$codigo]['nombre'] = trim((string) $cc->grupo_nombre);
            }
        }
        foreach ($grupos as &$g) {
            if ($g['nombre'] === '') {
                $g['nombre'] = $g['codigo'] === '0' ? 'Sin grupo' : 'Grupo '.$g['codigo'];
            }
        }
        unset($g);

        $lista = array_values($grupos);
        usort($lista, static fn (array $a, array $b): int => strnatcasecmp($a['codigo'], $b['codigo']));

        return $lista;
    }

    private function puedeConsultarCentrocosto(): bool
    {
        // ABM usuarios: middleware superadmin (administrador / Enc-sistemas)
        $rol = (string) session()->get('rol_nombre');
        if ($rol === 'Enc-sistemas') {
            return true;
        }

        foreach ([
            'listar-centro-costo',
            'editar-centro-costo',
            'listar-requisicion',
            'listar-reporte-requisicion-compras',
            'crear-requisicion',
            'editar-requisicion',
            'crear-transferencia-mercaderia',
            'listar-transferencias-pendientes',
            'crear-movimientos-de-stock',
            'editar-movimientos-de-stock',
            'listar-movimientos-de-stock',
            'listar-mayor-plano-cuenta',
            'listar-movccosto',
            'ver-configuracion-indumentaria',
            'editar-configuracion-indumentaria',
            'crear-perdida-personal',
            'editar-perdida-personal',
            'actualizar-perdida-personal',
            'listar-perdida-personal',
            'crear-conceptos-venta',
            'editar-conceptos-venta',
            'actualizar-conceptos-venta',
            'listar-conceptos-venta',
            'crear-logistica-solicitud',
            'gestionar-logistica-solicitud',
            'editar-configuracion-logistica',
            'actualizar-configuracion-logistica',
            'listar-tipo-transaccion-compra',
            'crear-tipo-transaccion-compra',
            'editar-tipo-transaccion-compra',
            'actualizar-tipo-transaccion-compra',
            'crear-usuarios',
            'editar-usuarios',
            'actualizar-usuarios',
            'listar-usuarios',
            'importar-usuarios',
        ] as $permiso) {
            if (can($permiso, false)) {
                return true;
            }
        }

        return false;
    }
}
