<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Canon_Municipal_Config;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\Canon_Municipal_ConfigRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CanonMunicipalConfigController extends Controller
{
    public function __construct(
        private readonly Canon_Municipal_ConfigRepositoryInterface $configRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-canon-municipal-config');

        $datas = $this->configRepository->all();

        return view('contable.canon_municipal_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-canon-municipal-config');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $periodicidad_enum = Canon_Municipal_Config::$enumPeriodicidad;
        $plantilla_enum = Canon_Municipal_Config::$enumPlantilla;

        return view('contable.canon_municipal_config.crear', compact(
            'empresa_query',
            'periodicidad_enum',
            'plantilla_enum',
        ));
    }

    public function guardar(Request $request)
    {
        can('crear-canon-municipal-config');

        $data = $this->validar($request);
        $this->configRepository->create($data);

        return redirect()->route('canon_municipal_config')
            ->with('mensaje', 'Configuración de canon municipal creada con éxito');
    }

    public function editar(int $id)
    {
        can('editar-canon-municipal-config');

        $data = $this->configRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $periodicidad_enum = Canon_Municipal_Config::$enumPeriodicidad;
        $plantilla_enum = Canon_Municipal_Config::$enumPlantilla;

        return view('contable.canon_municipal_config.editar', compact(
            'data',
            'empresa_query',
            'periodicidad_enum',
            'plantilla_enum',
        ));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-canon-municipal-config');

        $data = $this->validar($request, $id);
        $this->configRepository->update($data, $id);

        return redirect()->route('canon_municipal_config')
            ->with('mensaje', 'Configuración de canon municipal actualizada con éxito');
    }

    public function eliminar(int $id)
    {
        can('eliminar-canon-municipal-config');

        $this->configRepository->delete($id);

        return redirect()->route('canon_municipal_config')
            ->with('mensaje', 'Configuración eliminada');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'empresa_id' => [
                'required',
                'integer',
                'exists:empresa,id',
                Rule::unique('canon_municipal_config', 'empresa_id')->ignore($id),
            ],
            'municipio' => ['required', 'string', 'max:120'],
            'legajo' => ['required', 'string', 'max:40'],
            'periodicidad' => ['required', Rule::in(array_keys(Canon_Municipal_Config::$enumPeriodicidad))],
            'plantilla' => ['required', Rule::in(array_keys(Canon_Municipal_Config::$enumPlantilla))],
            'alicuota' => ['required', 'numeric', 'min:0.0001', 'max:1'],
            'firmante_nombre' => ['required', 'string', 'max:120'],
            'firmante_cargo' => ['required', 'string', 'max:80'],
            'pie_razon_social' => ['nullable', 'string', 'max:120'],
            'direccion_extra' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:80'],
            'activo' => ['nullable', 'boolean'],
        ], [], [
            'empresa_id' => 'empresa',
            'pie_razon_social' => 'razón social al pie',
            'direccion_extra' => 'dirección extra',
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        return $validated;
    }
}
