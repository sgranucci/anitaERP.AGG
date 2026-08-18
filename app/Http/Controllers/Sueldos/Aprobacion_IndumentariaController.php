<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Aprobacion_Indumentaria_Nivel_Sueldos;
use App\Repositories\Admin\UsuarioRepositoryInterface;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;

/**
 * ABM de niveles de aprobación de solicitudes de indumentaria (por empresa y,
 * opcionalmente, por agrupamiento). Sin filas = aprobación deshabilitada.
 */
class Aprobacion_IndumentariaController extends Controller
{
    public function __construct(
        private EmpresaRepositoryInterface $empresaRepository,
        private UsuarioRepositoryInterface $usuarioRepository,
    ) {}

    public function index(Request $request)
    {
        can('ver-aprobacion-indumentaria');

        $empresas = $this->empresaRepository->allFiltrado();
        $empresaId = (int) ($request->input('empresa_id') ?: optional($empresas->first())->id ?: 0);

        $niveles = Aprobacion_Indumentaria_Nivel_Sueldos::query()
            ->with('usuario:id,nombre,usuario')
            ->where('empresa_id', $empresaId)
            ->orderByRaw('(agrupamiento_id IS NULL) DESC')
            ->orderBy('agrupamiento_id')
            ->orderBy('nivel')->orderBy('orden')
            ->get();

        $agrupNombres = Agrupamiento_Sueldos::query()->pluck('descripcion', 'id')->all();

        return view('sueldos.aprobacion_indumentaria.index', [
            'empresas' => $empresas,
            'empresaId' => $empresaId,
            'niveles' => $niveles,
            'agrupamientos' => Agrupamiento_Sueldos::query()->orderBy('descripcion')->get(['id', 'descripcion']),
            'agrupNombres' => $agrupNombres,
            'usuarios' => $this->usuarioRepository->listadoOperativoParaSelector($empresaId, null, ['id', 'nombre', 'usuario']),
        ]);
    }

    public function guardar(Request $request)
    {
        can('editar-aprobacion-indumentaria');

        $data = $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'agrupamiento_id' => 'nullable|integer|exists:agrupamiento_sueldos,id',
            'nivel' => 'required|integer|min:1|max:20',
            'usuario_id' => 'required|integer|exists:usuario,id',
        ]);

        Aprobacion_Indumentaria_Nivel_Sueldos::firstOrCreate(
            [
                'empresa_id' => (int) $data['empresa_id'],
                'agrupamiento_id' => $data['agrupamiento_id'] ? (int) $data['agrupamiento_id'] : null,
                'nivel' => (int) $data['nivel'],
                'usuario_id' => (int) $data['usuario_id'],
            ],
            ['orden' => 0],
        );

        return redirect()
            ->route('aprobacion_indumentaria', ['empresa_id' => (int) $data['empresa_id']])
            ->with('mensaje', 'Aprobador agregado al nivel '.$data['nivel'].'.');
    }

    public function eliminar(Request $request, $id)
    {
        can('editar-aprobacion-indumentaria');

        $fila = Aprobacion_Indumentaria_Nivel_Sueldos::findOrFail($id);
        $empresaId = (int) $fila->empresa_id;
        $fila->delete();

        return redirect()
            ->route('aprobacion_indumentaria', ['empresa_id' => $empresaId])
            ->with('mensaje', 'Aprobador quitado.');
    }
}
