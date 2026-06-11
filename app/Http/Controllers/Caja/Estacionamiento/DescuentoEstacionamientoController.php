<?php

namespace App\Http\Controllers\Caja\Estacionamiento;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionEstacionamientoDescuento;
use App\Models\Caja\Estacionamiento\DescuentoEstacionamiento;
use App\Repositories\Caja\Estacionamiento\DescuentoEstacionamientoRepositoryInterface;
use Illuminate\Http\Request;

class DescuentoEstacionamientoController extends Controller
{
    public function __construct(
        private DescuentoEstacionamientoRepositoryInterface $repository,
    ) {
    }

    public function index()
    {
        can('listar-descuento-estacionamiento');

        $datas = $this->repository->all();
        $tiposValor = DescuentoEstacionamiento::tiposValor();

        return view('caja.estacionamiento.descuento.index', compact('datas', 'tiposValor'));
    }

    public function crear()
    {
        can('crear-descuento-estacionamiento');
        $data = new DescuentoEstacionamiento();
        $tiposValor = DescuentoEstacionamiento::tiposValor();

        return view('caja.estacionamiento.descuento.crear', compact('data', 'tiposValor'));
    }

    public function guardar(ValidacionEstacionamientoDescuento $request)
    {
        can('crear-descuento-estacionamiento');
        $this->repository->create($request->all());

        return redirect('caja/estacionamiento/descuento')->with('mensaje', 'Descuento creado con éxito');
    }

    public function editar($id)
    {
        can('editar-descuento-estacionamiento');
        $data = DescuentoEstacionamiento::query()->with('cliente')->findOrFail($id);
        $tiposValor = DescuentoEstacionamiento::tiposValor();

        return view('caja.estacionamiento.descuento.editar', compact('data', 'tiposValor'));
    }

    public function actualizar(ValidacionEstacionamientoDescuento $request, $id)
    {
        can('actualizar-descuento-estacionamiento');
        $this->repository->update($request->all(), $id);

        return redirect('caja/estacionamiento/descuento')->with('mensaje', 'Descuento actualizado con éxito');
    }

    public function consultaDescuento(Request $request)
    {
        can('usar-proceso-facturacion-estacionamiento');

        return $this->repository->consultaDescuento((string) ($request->get('consulta') ?? ''));
    }

    public function leeUnDescuentoPorCodigo(string $codigo)
    {
        can('usar-proceso-facturacion-estacionamiento');

        $descuento = $this->repository->findPorCodigo($codigo);
        if (! $descuento) {
            return response()->json(['error' => 'Descuento no encontrado'], 404);
        }

        $cli = $descuento->cliente;

        return response()->json([
            'id' => $descuento->id,
            'codigo' => $descuento->codigo,
            'nombre' => $descuento->nombre,
            'tipovalor' => $descuento->tipovalor,
            'valor' => (float) $descuento->valor,
            'cliente_id' => $descuento->cliente_id,
            'cliente' => $cli ? [
                'id' => $cli->id,
                'codigo' => $cli->codigo,
                'nombre' => $cli->nombre,
            ] : null,
        ]);
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-descuento-estacionamiento');

        if ($request->ajax()) {
            if ($this->repository->delete($id)) {
                return response()->json(['mensaje' => 'ok']);
            }

            return response()->json(['mensaje' => 'ng']);
        }

        abort(404);
    }
}
