<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Contable\Centrocosto;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Sueldos\Configuracion_Indumentaria_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class Indumentaria_ConfiguracionController extends Controller
{
    public function editar()
    {
        can('ver-configuracion-indumentaria');

        $config = Configuracion_Indumentaria_Sueldos::actual();

        $depositoId = (int) old('deposito_id', $config->deposito_id);
        $tipoId = (int) old('tipotransaccion_stock_id', $config->tipotransaccion_stock_id);
        $centrocostoId = (int) old('centrocosto_id', $config->centrocosto_id);

        return view('sueldos.indumentaria.configuracion', [
            'config' => $config,
            'deposito' => $depositoId > 0
                ? Depmae::query()->find($depositoId)
                : null,
            'tipo' => $tipoId > 0
                ? Tipotransaccion_Stock::query()->find($tipoId)
                : null,
            'centrocosto' => $centrocostoId > 0
                ? Centrocosto::query()->find($centrocostoId)
                : null,
        ]);
    }

    public function actualizar(Request $request)
    {
        can('editar-configuracion-indumentaria');

        $data = $request->validate([
            'deposito_id' => ['required', 'integer', 'exists:depmae,id'],
            'tipotransaccion_stock_id' => ['required', 'integer', 'exists:tipotransaccion_stock,id'],
            'centrocosto_id' => ['nullable', 'integer', 'exists:centrocosto,id'],
        ], [], [
            'deposito_id' => 'depósito de origen',
            'tipotransaccion_stock_id' => 'tipo de transacción de stock',
            'centrocosto_id' => 'centro de costo',
        ]);

        $tipo = Tipotransaccion_Stock::query()->find((int) $data['tipotransaccion_stock_id']);
        if ($tipo === null || $tipo->operacion !== 'S' || $tipo->estado !== 'A') {
            throw ValidationException::withMessages([
                'tipotransaccion_stock_id' => 'Debe elegir un tipo de transacción de salida (S) activo.',
            ]);
        }

        $config = Configuracion_Indumentaria_Sueldos::actual();
        $config->fill([
            'deposito_id' => (int) $data['deposito_id'],
            'tipotransaccion_stock_id' => (int) $data['tipotransaccion_stock_id'],
            'centrocosto_id' => $data['centrocosto_id'] !== null && $data['centrocosto_id'] !== ''
                ? (int) $data['centrocosto_id'] : null,
        ])->save();

        return redirect()->route('config_indumentaria')->with('mensaje', 'Configuración de indumentaria actualizada.');
    }
}
