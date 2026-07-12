<?php

namespace App\Http\Controllers\Caja\Bingo;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionPuntoventaBingo;
use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Repositories\Caja\Bingo\ConfiguracionPuntoventaBingoRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use Illuminate\Http\Request;

class ConfiguracionPuntoventaBingoController extends Controller
{
    public function __construct(
        private readonly ConfiguracionPuntoventaBingoRepositoryInterface $repository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {}

    public function index()
    {
        can('listar-configuracion-puntoventa-bingo');

        return view('caja.bingo.configuracion_puntoventa.index', [
            'datas' => $this->repository->all(),
        ]);
    }

    public function crear(Request $request)
    {
        can('crear-configuracion-puntoventa-bingo');

        $data = new ConfiguracionPuntoventaBingo([
            'identificador_pc' => BingoIdentificadorPc::sugerirEnFormularioAlta($request),
        ]);

        return view('caja.bingo.configuracion_puntoventa.crear', [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function guardar(ValidacionConfiguracionPuntoventaBingo $request)
    {
        can('crear-configuracion-puntoventa-bingo');
        $this->repository->create($request->all());

        return redirect()->route('bingo_configuracion_puntoventa')
            ->with('mensaje', 'Terminal configurada con éxito');
    }

    public function editar($id)
    {
        can('editar-configuracion-puntoventa-bingo');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);

        return view('caja.bingo.configuracion_puntoventa.editar', [
            'data' => $data,
            'empresa_query' => $this->empresaRepository->allFiltrado(),
        ]);
    }

    public function actualizar(ValidacionConfiguracionPuntoventaBingo $request, $id)
    {
        can('actualizar-configuracion-puntoventa-bingo');
        $data = $this->repository->findOrFail($id);
        $this->assertEmpresaPermitida((int) $data->empresa_id);
        $this->repository->update($request->all(), $id);

        return redirect()->route('bingo_configuracion_puntoventa')
            ->with('mensaje', 'Terminal actualizada con éxito');
    }

    public function eliminar(Request $request, $id)
    {
        can('borrar-configuracion-puntoventa-bingo');

        if ($request->ajax()) {
            return response()->json([
                'mensaje' => $this->repository->delete($id) ? 'ok' : 'ng',
            ]);
        }

        abort(404);
    }

    private function assertEmpresaPermitida(int $empresaId): void
    {
        if (! $this->empresaRepository->empresaIdPermitida($empresaId)) {
            abort(403);
        }
    }
}
