<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\IibbSolicitudpagoConfigRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Parámetros por empresa de la liquidación y la solicitud de pago ARBA (retenciones IIBB).
 */
class IibbArbaConfigController extends Controller
{
    public function __construct(
        private readonly IibbSolicitudpagoConfigRepositoryInterface $configRepository,
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-iibb-arba-config');

        $datas = $this->configRepository->all();

        return view('contable.iibb_arba_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-iibb-arba-config');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $sectores_sp = $this->sectoresParaConsulta();
        $formas_pago_sp = $this->formasPagoParaConsulta();

        return view('contable.iibb_arba_config.crear', compact(
            'empresa_query',
            'sectores_sp',
            'formas_pago_sp',
        ));
    }

    public function guardar(Request $request)
    {
        can('crear-iibb-arba-config');

        $data = $this->validar($request);
        $this->configRepository->create($data);

        return redirect()->route('iibb_arba_config')
            ->with('mensaje', 'Configuración ARBA creada con éxito');
    }

    public function editar(int $id)
    {
        can('editar-iibb-arba-config');

        $data = $this->configRepository->findOrFail($id);
        $empresa_query = $this->empresaRepository->allFiltrado();
        $sectores_sp = $this->sectoresParaConsulta();
        $formas_pago_sp = $this->formasPagoParaConsulta();

        return view('contable.iibb_arba_config.editar', compact(
            'data',
            'empresa_query',
            'sectores_sp',
            'formas_pago_sp',
        ));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-iibb-arba-config');

        $data = $this->validar($request, $id);
        $this->configRepository->update($data, $id);

        return redirect()->route('iibb_arba_config')
            ->with('mensaje', 'Configuración ARBA actualizada con éxito');
    }

    public function eliminar(int $id)
    {
        can('eliminar-iibb-arba-config');

        $this->configRepository->delete($id);

        return redirect()->route('iibb_arba_config')
            ->with('mensaje', 'Configuración ARBA eliminada');
    }

    /**
     * Las columnas NOT NULL de la tabla son obligatorias: una configuración a medio cargar
     * bloquearía la liquidación con un mensaje menos claro que pedirlo al guardar.
     *
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'empresa_id' => [
                'required',
                'integer',
                'exists:empresa,id',
                Rule::unique('iibb_solicitudpago_config', 'empresa_id')->ignore($id),
            ],
            'proveedor_id' => ['required', 'integer', 'exists:proveedor,id'],
            'sector_solicitudpago_id' => ['required', 'integer', 'exists:sector_solicitudpago,id'],
            'concepto_solicitudpago_id' => ['required', 'integer', 'exists:concepto_solicitudpago,id'],
            'formapagosol_id' => ['required', 'integer', 'exists:formapagosol,id'],
            'moneda_id' => ['required', 'integer', 'exists:moneda,id'],
            'centrocosto_id' => ['required', 'integer', 'exists:centrocosto,id'],
            'cuentacontable_debe_id' => ['required', 'integer', 'exists:cuentacontable,id'],
            'cuentacontable_banco_id' => ['required', 'integer', 'exists:cuentacontable,id'],
            'plantilla_detalle' => ['required', 'string', 'max:160'],
            'dias_duplicado' => ['required', 'integer', 'min:0', 'max:365'],
            'dias_aviso_pendiente' => ['required', 'integer', 'min:0', 'max:365'],
        ], [], [
            'empresa_id' => 'empresa',
            'proveedor_id' => 'proveedor',
            'sector_solicitudpago_id' => 'sector',
            'concepto_solicitudpago_id' => 'concepto',
            'formapagosol_id' => 'forma de pago',
            'moneda_id' => 'moneda',
            'centrocosto_id' => 'centro de costo',
            'cuentacontable_debe_id' => 'cuenta Debe',
            'cuentacontable_banco_id' => 'cuenta Banco (Haber)',
            'plantilla_detalle' => 'plantilla detalle',
            'dias_duplicado' => 'días de duplicado',
            'dias_aviso_pendiente' => 'días de aviso de pendientes',
        ]);

        // Checkbox destildado = no viene en el request: debe quedar en false.
        $validated['activo'] = $request->boolean('activo');
        $validated['plantilla_detalle'] = trim((string) $validated['plantilla_detalle']);

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
