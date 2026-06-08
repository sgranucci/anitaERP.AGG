<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\ValidacionConfiguracionRecepcionProveedor;
use App\Models\Stock\Configuracion_RecepcionProveedor;
use App\Models\Stock\Configuracion_RecepcionProveedorTolerancia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;

class ConfiguracionRecepcionProveedorController extends Controller
{
    private const RUTA_INDEX = 'configuracion/recepcion-proveedor';

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
    ) {}

    public function index()
    {
        can('editar-configuracion-recepcion-proveedor');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) (request('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $config = Configuracion_RecepcionProveedor::query()
            ->where('empresa_id', $empresa_id)
            ->with([
                'cuentacontable_provision_facturas',
                'cuentacontable_factura_anticipada',
                'cuentacontable_anticipo_bienes_uso',
                'cuentacontable_proveedores_intangible',
            ])
            ->first();
        $centrocosto_query = $this->centrocostoRepository->all();
        $filasTolerancia = Configuracion_RecepcionProveedorTolerancia::query()
            ->where('empresa_id', $empresa_id)
            ->whereNotNull('centrocosto_id')
            ->with('centrocostos')
            ->orderBy('centrocosto_id')
            ->get();

        return view('configuracion.recepcion_proveedor.editar', compact(
            'empresa_query', 'empresa_id', 'config', 'centrocosto_query', 'filasTolerancia'
        ));
    }

    public function guardarTolerancias()
    {
        can('actualizar-configuracion-recepcion-proveedor');

        $data = request()->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'tolerancias' => 'nullable|array',
            'tolerancias.*.centrocosto_id' => 'required|integer|exists:centrocosto,id',
            'tolerancias.*.tolerancia_cantidad_pct' => 'nullable|numeric|min:0|max:100',
            'tolerancias.*.tolerancia_precio_pct' => 'nullable|numeric|min:0|max:100',
            'tolerancias.*.tolerancia_precio_absoluto' => 'nullable|numeric|min:0',
        ]);

        $empresaId = (int) $data['empresa_id'];
        $centrosUsados = [];
        $keptIds = [];

        foreach ($data['tolerancias'] ?? [] as $fila) {
            $centrocostoId = (int) ($fila['centrocosto_id'] ?? 0);
            if ($centrocostoId <= 0) {
                continue;
            }

            if (isset($centrosUsados[$centrocostoId])) {
                return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
                    ->with('errores', ['No puede repetir el mismo centro de costo en la grilla.']);
            }
            $centrosUsados[$centrocostoId] = true;

            $registro = Configuracion_RecepcionProveedorTolerancia::updateOrCreate(
                [
                    'empresa_id' => $empresaId,
                    'centrocosto_id' => $centrocostoId,
                ],
                [
                    'tolerancia_cantidad_pct' => $fila['tolerancia_cantidad_pct'] ?? 0,
                    'tolerancia_precio_pct' => $fila['tolerancia_precio_pct'] ?? 0,
                    'tolerancia_precio_absoluto' => $fila['tolerancia_precio_absoluto'] ?? 0,
                    'activo' => true,
                ]
            );
            $keptIds[] = $registro->id;
        }

        Configuracion_RecepcionProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotIn('id', $keptIds)
            ->delete();

        return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
            ->with('mensaje', 'Tolerancias guardadas.');
    }

    public function actualizar(ValidacionConfiguracionRecepcionProveedor $request)
    {
        can('actualizar-configuracion-recepcion-proveedor');

        $data = $request->validated();
        $data['activa_contabilidad'] = $request->boolean('activa_contabilidad');

        Configuracion_RecepcionProveedor::updateOrCreate(
            ['empresa_id' => $data['empresa_id']],
            $data
        );

        return redirect(self::RUTA_INDEX.'?empresa_id='.$data['empresa_id'])
            ->with('mensaje', 'Configuración guardada.');
    }
}
