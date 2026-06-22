<?php

namespace App\Http\Controllers\Stock;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionTipotransaccion_Stock;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Repositories\Stock\Tipotransaccion_StockRepositoryInterface;
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
        $data['maneja_contabilidad'] = $request->boolean('maneja_contabilidad');
        $data['destino_bien_uso'] = $request->boolean('destino_bien_uso');
        $data['origen_bien_uso'] = $request->boolean('origen_bien_uso');
        if ($data['origen_bien_uso'] && $data['destino_bien_uso']) {
            $data['destino_bien_uso'] = false;
        }

        return $data;
    }
}
