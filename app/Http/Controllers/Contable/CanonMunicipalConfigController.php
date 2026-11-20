<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Canon_Municipal_Config;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
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
        $sectores_sp = $this->sectoresParaConsulta();
        $formas_pago_sp = $this->formasPagoParaConsulta();

        return view('contable.canon_municipal_config.crear', compact(
            'empresa_query',
            'periodicidad_enum',
            'plantilla_enum',
            'sectores_sp',
            'formas_pago_sp',
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
        $data->load([
            'proveedor:id,codigo,nombre',
            'sectorSolicitudpago:id,codigo,nombre',
            'conceptoSolicitudpago:id,codigo,nombre',
            'formapagosol:id,codigo,nombre',
            'moneda:id,codigo,nombre',
            'centrocosto:id,codigo,nombre',
            'cuentacontableDebe:id,codigo,nombre',
            'cuentacontableHaber:id,codigo,nombre',
            'empresa:id,nombre',
        ]);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $periodicidad_enum = Canon_Municipal_Config::$enumPeriodicidad;
        $plantilla_enum = Canon_Municipal_Config::$enumPlantilla;
        $sectores_sp = $this->sectoresParaConsulta();
        $formas_pago_sp = $this->formasPagoParaConsulta();

        return view('contable.canon_municipal_config.editar', compact(
            'data',
            'empresa_query',
            'periodicidad_enum',
            'plantilla_enum',
            'sectores_sp',
            'formas_pago_sp',
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
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedor,id'],
            'sector_solicitudpago_id' => ['nullable', 'integer', 'exists:sector_solicitudpago,id'],
            'concepto_solicitudpago_id' => ['nullable', 'integer', 'exists:concepto_solicitudpago,id'],
            'formapagosol_id' => ['nullable', 'integer', 'exists:formapagosol,id'],
            'moneda_id' => ['nullable', 'integer', 'exists:moneda,id'],
            'centrocosto_id' => ['nullable', 'integer', 'exists:centrocosto,id'],
            'cuentacontable_debe_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'cuentacontable_haber_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'plantilla_detalle' => ['nullable', 'string', 'max:180'],
        ], [], [
            'empresa_id' => 'empresa',
            'pie_razon_social' => 'razón social al pie',
            'direccion_extra' => 'dirección extra',
            'proveedor_id' => 'proveedor',
            'sector_solicitudpago_id' => 'sector',
            'concepto_solicitudpago_id' => 'concepto',
            'formapagosol_id' => 'forma de pago',
            'moneda_id' => 'moneda',
            'centrocosto_id' => 'centro de costo',
            'cuentacontable_debe_id' => 'cuenta Debe',
            'cuentacontable_haber_id' => 'cuenta Haber',
            'plantilla_detalle' => 'plantilla detalle',
        ]);

        $validated['activo'] = $request->boolean('activo', true);
        $validated['plantilla_detalle'] = trim((string) ($validated['plantilla_detalle'] ?? ''))
            ?: Canon_Municipal_Config::PLANTILLA_DETALLE_DEFAULT;

        foreach ([
            'proveedor_id',
            'sector_solicitudpago_id',
            'concepto_solicitudpago_id',
            'formapagosol_id',
            'moneda_id',
            'centrocosto_id',
            'cuentacontable_debe_id',
            'cuentacontable_haber_id',
        ] as $fk) {
            if (empty($validated[$fk])) {
                $validated[$fk] = null;
            }
        }

        return $validated;
    }

    /**
     * @return list<array{id: int, codigo: int|string, nombre: string}>
     */
    private function sectoresParaConsulta(): array
    {
        return Sector_Solicitudpago::query()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(static fn ($r) => [
                'id' => (int) $r->id,
                'codigo' => $r->codigo,
                'nombre' => (string) $r->nombre,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, codigo: int|string, nombre: string}>
     */
    private function formasPagoParaConsulta(): array
    {
        return Formapagosol::query()
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre'])
            ->map(static fn ($r) => [
                'id' => (int) $r->id,
                'codigo' => $r->codigo,
                'nombre' => (string) $r->nombre,
            ])
            ->values()
            ->all();
    }
}
