<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Models\Compras\Configuracion_ComprobanteProveedor;
use App\Models\Compras\Configuracion_ComprobanteProveedorTolerancia;
use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use App\Repositories\Contable\CentrocostoRepositoryInterface;
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
        ]);

        Configuracion_ComprobanteProveedor::updateOrCreate(
            ['empresa_id' => (int) $data['empresa_id']],
            ['activo' => $request->boolean('activo', true)],
        );

        return redirect(self::RUTA_INDEX.'?empresa_id='.$data['empresa_id'])
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

        Configuracion_ComprobanteProveedor::updateOrCreate(
            ['empresa_id' => $empresaId],
            ['activo' => true],
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
