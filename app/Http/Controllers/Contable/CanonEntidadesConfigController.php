<?php

declare(strict_types=1);

namespace App\Http\Controllers\Contable;

use App\Http\Controllers\Controller;
use App\Models\Contable\Canon_Entidades_Config;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CanonEntidadesConfigController extends Controller
{
    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
    ) {
        $this->middleware('auth');
    }

    public function index()
    {
        can('listar-canon-entidades-config');

        $datas = Canon_Entidades_Config::query()
            ->with('empresa:id,nombre')
            ->orderBy('empresa_id')
            ->get();

        return view('contable.canon_entidades_config.index', compact('datas'));
    }

    public function crear()
    {
        can('crear-canon-entidades-config');

        return view('contable.canon_entidades_config.crear', $this->datosFormulario());
    }

    public function guardar(Request $request)
    {
        can('crear-canon-entidades-config');

        Canon_Entidades_Config::query()->create($this->validar($request));

        return redirect()->route('canon_entidades_config')
            ->with('mensaje', 'Configuración de canon entidades creada con éxito');
    }

    public function editar(int $id)
    {
        can('editar-canon-entidades-config');

        $data = Canon_Entidades_Config::query()
            ->with([
                'empresa:id,nombre',
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoMaquinas:id,codigo,nombre',
                'conceptoBingo:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontableDebe:id,codigo,nombre',
                'cuentacontableHaber:id,codigo,nombre',
            ])
            ->findOrFail($id);

        return view('contable.canon_entidades_config.editar', array_merge(
            $this->datosFormulario(),
            ['data' => $data],
        ));
    }

    public function actualizar(Request $request, int $id)
    {
        can('actualizar-canon-entidades-config');

        $registro = Canon_Entidades_Config::query()->findOrFail($id);
        $registro->update($this->validar($request, $id));

        return redirect()->route('canon_entidades_config')
            ->with('mensaje', 'Configuración de canon entidades actualizada con éxito');
    }

    public function eliminar(int $id)
    {
        can('eliminar-canon-entidades-config');

        Canon_Entidades_Config::query()->findOrFail($id)->delete();

        return redirect()->route('canon_entidades_config')
            ->with('mensaje', 'Configuración eliminada');
    }

    /**
     * @return array<string, mixed>
     */
    private function datosFormulario(): array
    {
        return [
            'empresa_query' => $this->empresaRepository->allFiltrado(),
            'sectores_sp' => $this->sectoresParaConsulta(),
            'formas_pago_sp' => $this->formasPagoParaConsulta(),
            'conceptos_sp' => $this->conceptosParaConsulta(),
        ];
    }

    /**
     * @return list<array{id: int, codigo: int|string, nombre: string}>
     */
    private function conceptosParaConsulta(): array
    {
        return Concepto_Solicitudpago::query()
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
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $id = null): array
    {
        $validated = $request->validate([
            'empresa_id' => [
                'required',
                'integer',
                'exists:empresa,id',
                Rule::unique('canon_entidades_config', 'empresa_id')->ignore($id),
            ],
            'proveedor_id' => ['nullable', 'integer', 'exists:proveedor,id'],
            'sector_solicitudpago_id' => ['nullable', 'integer', 'exists:sector_solicitudpago,id'],
            'concepto_maquinas_solicitudpago_id' => ['nullable', 'integer', 'exists:concepto_solicitudpago,id'],
            'concepto_bingo_solicitudpago_id' => ['nullable', 'integer', 'exists:concepto_solicitudpago,id'],
            'formapagosol_id' => ['nullable', 'integer', 'exists:formapagosol,id'],
            'moneda_id' => ['nullable', 'integer', 'exists:moneda,id'],
            'centrocosto_id' => ['nullable', 'integer', 'exists:centrocosto,id'],
            'cuentacontable_debe_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'cuentacontable_haber_id' => ['nullable', 'integer', 'exists:cuentacontable,id'],
            'plantilla_maquinas' => ['nullable', 'string', 'max:180'],
            'plantilla_bingo' => ['nullable', 'string', 'max:180'],
        ], [], [
            'empresa_id' => 'empresa',
            'proveedor_id' => 'proveedor',
            'sector_solicitudpago_id' => 'sector',
            'concepto_maquinas_solicitudpago_id' => 'concepto máquinas',
            'concepto_bingo_solicitudpago_id' => 'concepto bingo',
            'formapagosol_id' => 'forma de pago',
            'moneda_id' => 'moneda',
            'centrocosto_id' => 'centro de costo',
            'cuentacontable_debe_id' => 'cuenta Debe',
            'cuentacontable_haber_id' => 'cuenta Haber',
            'plantilla_maquinas' => 'plantilla detalle máquinas',
            'plantilla_bingo' => 'plantilla detalle bingo',
        ]);

        $validated['activo'] = $request->boolean('activo', true);
        $validated['plantilla_maquinas'] = trim((string) ($validated['plantilla_maquinas'] ?? ''))
            ?: Canon_Entidades_Config::PLANTILLA_MAQUINAS_DEFAULT;
        $validated['plantilla_bingo'] = trim((string) ($validated['plantilla_bingo'] ?? ''))
            ?: Canon_Entidades_Config::PLANTILLA_BINGO_DEFAULT;

        foreach ([
            'proveedor_id',
            'sector_solicitudpago_id',
            'concepto_maquinas_solicitudpago_id',
            'concepto_bingo_solicitudpago_id',
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
