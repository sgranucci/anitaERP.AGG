<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipotransaccion_Stock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
use App\Support\Stock\UsuarioTipotransaccionStockAutorizado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Tipotransaccion_StockController extends Controller
{
    public function __construct(
        private Tipotransaccion_StockRepositoryInterface $repository,
    ) {}

    public function index()
    {
        can('listar-tipos-transaccion-stock');

        $datas = $this->repository->all('*');
        $operacionEnum = Tipotransaccion_Stock::$enumOperacion;
        $signoEnum = Tipotransaccion_Stock::$enumSigno;
        $estadoEnum = Tipotransaccion_Stock::$enumEstado;

        return view('stock.tipotransaccion_stock.index', compact('datas', 'operacionEnum', 'signoEnum', 'estadoEnum'));
    }

    public function crear()
    {
        can('crear-tipos-transaccion-stock');

        return view('stock.tipotransaccion_stock.crear', $this->datosFormulario());
    }

    public function guardar(ValidacionTipotransaccion_Stock $request)
    {
        can('crear-tipos-transaccion-stock');

        $this->repository->create($this->datosNormalizados($request));

        return redirect('stock/tipotransaccion_stock')->with('mensaje', 'Tipo de transacción de stock creado con éxito');
    }

    public function editar($id)
    {
        can('editar-tipos-transaccion-stock');

        return view('stock.tipotransaccion_stock.editar', array_merge(
            $this->datosFormulario(),
            ['data' => $this->repository->findOrFail($id)]
        ));
    }

    public function actualizar(ValidacionTipotransaccion_Stock $request, $id)
    {
        can('actualizar-tipos-transaccion-stock');

        $this->repository->update($this->datosNormalizados($request), $id);

        return redirect('stock/tipotransaccion_stock')->with('mensaje', 'Tipo de transacción de stock actualizado con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-tipos-transaccion-stock');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }

    public function leer($id)
    {
        return $this->repository->find($id);
    }

    public function consultaTipotransaccionStock(Request $request)
    {
        if (! $this->puedeConsultarTipotransaccionStock()) {
            abort(403);
        }

        $consulta = strtoupper(trim((string) ($request->get('consulta') ?? '')));
        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');
        $operaciones = collect($request->input('operaciones', ['E', 'S', 'T']))
            ->map(fn ($op) => strtoupper(trim((string) $op)))
            ->filter(fn ($op) => in_array($op, ['E', 'S', 'T'], true))
            ->unique()
            ->values()
            ->all();

        if ($operaciones === []) {
            $operaciones = ['E', 'S', 'T'];
        }

        $query = Tipotransaccion_Stock::query()
            ->select('id', 'abreviatura', 'nombre', 'operacion', 'maneja_contabilidad', 'origen_bien_uso', 'destino_bien_uso', 'requiere_aprobacion', 'aviso_opcional', 'baja_npu', 'alta_npu')
            ->where('estado', 'A')
            ->whereIn('operacion', $operaciones);

        if (! $omitirFiltroUsuario) {
            UsuarioTipotransaccionStockAutorizado::aplicarFiltroQuery($query);
        }

        if ($consulta !== '') {
            $query->where(function ($q) use ($consulta) {
                $q->where('abreviatura', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('nombre', 'LIKE', '%'.$consulta.'%')
                    ->orWhere('operacion', 'LIKE', '%'.$consulta.'%');
            });
        }

        $data = $query->orderBy('nombre')->limit(200)->get();
        $operacionEnum = Tipotransaccion_Stock::$enumOperacion;
        $puedeAbrirAbm = can('editar-tipos-transaccion-stock', false) || can('listar-tipos-transaccion-stock', false);

        $output = ['data' => ''];
        if ($data->isEmpty()) {
            $output['data'] = '<tr><td colspan="5">Sin resultados</td></tr>';
        } else {
            foreach ($data as $row) {
                $operacionEtiqueta = $operacionEnum[$row->operacion] ?? $row->operacion;
                $output['data'] .= '<tr>';
                $output['data'] .= '<td class="id">'.e($row->id).'</td>';
                $output['data'] .= '<td class="abreviatura">'.e($row->abreviatura).'</td>';
                $output['data'] .= '<td class="nombre">'.e($row->nombre).'</td>';
                $output['data'] .= '<td class="operacion">'.e($operacionEtiqueta).'</td>';
                $output['data'] .= '<td class="maneja-contabilidad d-none">'.e($row->maneja_contabilidad ? '1' : '0').'</td>';
                $output['data'] .= '<td class="operacion-codigo d-none">'.e($row->operacion).'</td>';
                $output['data'] .= '<td class="origen-bien-uso d-none">'.e($row->origen_bien_uso ? '1' : '0').'</td>';
                $output['data'] .= '<td class="destino-bien-uso d-none">'.e($row->destino_bien_uso ? '1' : '0').'</td>';
                $output['data'] .= '<td class="requiere-aprobacion d-none">'.e($row->requiere_aprobacion ? '1' : '0').'</td>';
                $output['data'] .= '<td class="aviso-opcional d-none">'.e($row->aviso_opcional ? '1' : '0').'</td>';
                $output['data'] .= '<td class="baja-npu d-none">'.e($row->baja_npu ? '1' : '0').'</td>';
                $output['data'] .= '<td class="alta-npu d-none">'.e($row->alta_npu ? '1' : '0').'</td>';
                $output['data'] .= '<td class="text-nowrap">';
                $output['data'] .= '<a class="btn btn-warning btn-sm eligeconsultatipotransaccionstock">Elegir</a>';
                if ($puedeAbrirAbm) {
                    $urlConsulta = route('editar_tipotransaccion_stock', [
                        'id' => $row->id,
                        'origen' => 'modal_consulta',
                        'vista' => 'consulta',
                    ]);
                    $output['data'] .= ' <a class="btn btn-info btn-sm" href="'.e($urlConsulta).'" target="_blank" rel="noopener">Consultar</a>';
                }
                $output['data'] .= '</td>';
                $output['data'] .= '</tr>';
            }
        }

        return json_encode($output, JSON_UNESCAPED_UNICODE);
    }

    public function leeUnTipotransaccionPorAbreviatura(Request $request, string $abreviatura)
    {
        if (! $this->puedeConsultarTipotransaccionStock()) {
            abort(403);
        }

        $omitirFiltroUsuario = $request->boolean('omitir_filtro_usuario');
        $operaciones = collect($request->input('operaciones', ['E', 'S', 'T']))
            ->map(fn ($op) => strtoupper(trim((string) $op)))
            ->filter(fn ($op) => in_array($op, ['E', 'S', 'T'], true))
            ->unique()
            ->values()
            ->all();

        if ($operaciones === []) {
            $operaciones = ['E', 'S', 'T'];
        }

        $query = Tipotransaccion_Stock::query()
            ->where('abreviatura', trim($abreviatura))
            ->where('estado', 'A')
            ->whereIn('operacion', $operaciones);

        if (! $omitirFiltroUsuario) {
            UsuarioTipotransaccionStockAutorizado::aplicarFiltroQuery($query);
        }

        $tipo = $query->first();

        if (! $tipo) {
            return response()->json(['error' => 'Tipo de transacción no encontrado'], 404);
        }

        return response()->json([
            'id' => $tipo->id,
            'abreviatura' => $tipo->abreviatura,
            'nombre' => $tipo->nombre,
            'descripcion' => $tipo->nombre,
            'operacion' => $tipo->operacion,
            'operacion_etiqueta' => Tipotransaccion_Stock::$enumOperacion[$tipo->operacion] ?? $tipo->operacion,
            'maneja_contabilidad' => (bool) $tipo->maneja_contabilidad,
            'origen_bien_uso' => (bool) $tipo->origen_bien_uso,
            'destino_bien_uso' => (bool) $tipo->destino_bien_uso,
            'requiere_aprobacion' => (bool) $tipo->requiere_aprobacion,
            'aviso_opcional' => (bool) $tipo->aviso_opcional,
            'baja_npu' => (bool) $tipo->baja_npu,
            'alta_npu' => (bool) $tipo->alta_npu,
        ]);
    }

    private function puedeConsultarTipotransaccionStock(): bool
    {
        return can('listar-tipos-transaccion-stock', false)
            || can('editar-tipos-transaccion-stock', false)
            || can('crear-tipos-transaccion-stock', false)
            || can('actualizar-tipos-transaccion-stock', false)
            || can('editar-usuarios', false)
            || can('crear-usuarios', false)
            || can('actualizar-usuarios', false)
            || can('crear-movimientos-de-stock', false)
            || can('editar-movimientos-de-stock', false)
            || can('listar-movimientos-de-stock', false)
            || can('crear-movimiento-surmar', false)
            || can('editar-movimiento-surmar', false)
            || can('listar-movimiento-surmar', false)
            || can('actualizar-movimiento-surmar', false)
            || can('ver-configuracion-indumentaria', false)
            || can('editar-configuracion-indumentaria', false)
            || can('crear-transferencia-mercaderia', false)
            || can('listar-transferencias-pendientes', false);
    }

    private function datosFormulario(): array
    {
        return [
            'operacionEnum' => Tipotransaccion_Stock::$enumOperacion,
            'signoEnum' => Tipotransaccion_Stock::$enumSigno,
            'estadoEnum' => Tipotransaccion_Stock::$enumEstado,
        ];
    }

    /** @return array<string, mixed> */
    private function datosNormalizados(ValidacionTipotransaccion_Stock $request): array
    {
        $data = $request->validated();
        $data['requiere_aprobacion'] = $request->boolean('requiere_aprobacion');
        $data['aviso_opcional'] = $request->boolean('aviso_opcional');
        $data['maneja_contabilidad'] = $request->boolean('maneja_contabilidad');
        $data['destino_bien_uso'] = $request->boolean('destino_bien_uso');
        $data['origen_bien_uso'] = $request->boolean('origen_bien_uso');
        $data['baja_npu'] = $request->boolean('baja_npu');
        $data['alta_npu'] = $request->boolean('alta_npu');
        if ($data['origen_bien_uso'] && $data['destino_bien_uso']) {
            $data['destino_bien_uso'] = false;
        }

        return $data;
    }
}
