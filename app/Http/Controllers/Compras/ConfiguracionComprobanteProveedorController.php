<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Configuracion_ComprobanteProveedor;
use App\Models\Compras\Configuracion_ComprobanteProveedorTolerancia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorComContabilidadSupport;
use Illuminate\Http\Request;

class ConfiguracionComprobanteProveedorController extends Controller
{
    private const RUTA_INDEX = 'compras/configuracion-comprobante-proveedor';

    public function __construct(
        private readonly EmpresaRepositoryInterface $empresaRepository,
        private readonly CentrocostoRepositoryInterface $centrocostoRepository,
    ) {}

    public function index(Request $request)
    {
        can('editar-configuracion-comprobante-proveedor');

        $empresa_query = $this->empresaRepository->allFiltrado();
        $empresa_id = (int) ($request->input('empresa_id') ?: old('empresa_id') ?: ($empresa_query->first()->id ?? 0));
        $config = Configuracion_ComprobanteProveedor::query()
            ->where('empresa_id', $empresa_id)
            ->first();
        $comGeneraContabilidad = ComprobanteProveedorComContabilidadSupport::generaAsientoCom($empresa_id);
        $centrocosto_query = $this->centrocostoRepository->all();
        $filasTolerancia = Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresa_id)
            ->with('centrocostos')
            ->orderByRaw('(CASE WHEN centrocosto_id IS NULL THEN 0 ELSE 1 END)')
            ->orderBy('centrocosto_id')
            ->get();

        return view('compras.configuracion_comprobante_proveedor.editar', compact(
            'empresa_query',
            'empresa_id',
            'config',
            'comGeneraContabilidad',
            'centrocosto_query',
            'filasTolerancia',
        ));
    }

    public function actualizar(Request $request)
    {
        can('actualizar-configuracion-comprobante-proveedor');

        $data = $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'activo' => 'nullable|boolean',
            'exige_flujo_oc_com_fac' => 'nullable|boolean',
            'com_genera_contabilidad' => 'nullable|boolean',
            'controla_sku_vs_com' => 'nullable|boolean',
            'controla_precio_unitario' => 'nullable|boolean',
            'tolerancia_precio_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $empresaId = (int) $data['empresa_id'];
        $comGeneraContabilidad = $request->input('com_genera_contabilidad') === '1'
            || $request->boolean('com_genera_contabilidad');

        Configuracion_ComprobanteProveedor::updateOrCreate(
            ['empresa_id' => $empresaId],
            [
                'activo' => $request->input('activo') === '1' || $request->boolean('activo'),
                // Hidden 0/1 del selector gráfico de flujo.
                'exige_flujo_oc_com_fac' => $request->input('exige_flujo_oc_com_fac') === '1'
                    || $request->boolean('exige_flujo_oc_com_fac'),
                'controla_sku_vs_com' => $request->input('controla_sku_vs_com') === '1'
                    || $request->boolean('controla_sku_vs_com'),
                'controla_precio_unitario' => $request->input('controla_precio_unitario') === '1'
                    || $request->boolean('controla_precio_unitario'),
                'tolerancia_precio_pct' => (float) ($data['tolerancia_precio_pct'] ?? 0),
            ],
        );

        // Misma bandera que Configuración recepción proveedores (asiento al confirmar COM).
        ComprobanteProveedorComContabilidadSupport::persistir($empresaId, $comGeneraContabilidad);

        return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
            ->with('mensaje', 'Configuración guardada.');
    }

    public function guardarTolerancias(Request $request)
    {
        can('actualizar-configuracion-comprobante-proveedor');

        $data = $request->validate([
            'empresa_id' => 'required|integer|exists:empresa,id',
            'tolerancias' => 'nullable|array',
            'tolerancias.*.centrocosto_id' => 'nullable|integer|exists:centrocosto,id',
            'tolerancias.*.es_default' => 'nullable|boolean',
            'tolerancias.*.tolerancia_importe_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $empresaId = (int) $data['empresa_id'];
        $centrosUsados = [];
        $keptIds = [];
        $tieneDefault = false;

        foreach ($data['tolerancias'] ?? [] as $fila) {
            $esDefault = ! empty($fila['es_default']) || empty($fila['centrocosto_id']);
            $centrocostoId = $esDefault ? null : (int) ($fila['centrocosto_id'] ?? 0);
            if (! $esDefault && $centrocostoId <= 0) {
                continue;
            }

            $clave = $esDefault ? 'default' : (string) $centrocostoId;
            if (isset($centrosUsados[$clave])) {
                return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
                    ->with('errores', ['No puede repetir el mismo centro de costo (ni el default) en la grilla.']);
            }
            $centrosUsados[$clave] = true;
            if ($esDefault) {
                $tieneDefault = true;
            }

            $registro = $this->upsertTolerancia(
                $empresaId,
                $centrocostoId,
                (float) ($fila['tolerancia_importe_pct'] ?? 0),
            );
            $keptIds[] = $registro->id;
        }

        if (! $tieneDefault) {
            $default = $this->upsertTolerancia($empresaId, null, 0.0);
            $keptIds[] = $default->id;
        }

        Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresaId)
            ->whereNotIn('id', $keptIds)
            ->delete();

        Configuracion_ComprobanteProveedor::firstOrCreate(
            ['empresa_id' => $empresaId],
            [
                'activo' => true,
                'exige_flujo_oc_com_fac' => false,
                'controla_sku_vs_com' => false,
                'controla_precio_unitario' => false,
                'tolerancia_precio_pct' => 0,
            ],
        );

        return redirect(self::RUTA_INDEX.'?empresa_id='.$empresaId)
            ->with('mensaje', 'Tolerancias guardadas.');
    }

    private function upsertTolerancia(int $empresaId, ?int $centrocostoId, float $pct): Configuracion_ComprobanteProveedorTolerancia
    {
        $query = Configuracion_ComprobanteProveedorTolerancia::query()
            ->where('empresa_id', $empresaId);
        if ($centrocostoId === null) {
            $query->whereNull('centrocosto_id');
        } else {
            $query->where('centrocosto_id', $centrocostoId);
        }

        $registro = $query->first();
        if ($registro) {
            $registro->update([
                'tolerancia_importe_pct' => $pct,
                'activo' => true,
            ]);

            return $registro->fresh();
        }

        return Configuracion_ComprobanteProveedorTolerancia::query()->create([
            'empresa_id' => $empresaId,
            'centrocosto_id' => $centrocostoId,
            'tolerancia_importe_pct' => $pct,
            'activo' => true,
        ]);
    }
}
