<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Ganancia_Deduccion_Sueldos;
use App\Models\Sueldos\Ganancia_Deduccion_Valor_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ganancia_Deduccion_SueldosController extends Controller
{
    public function index()
    {
        can('listar-ganancia-deduccion-sueldos');

        $deducciones = Ganancia_Deduccion_Sueldos::query()
            ->orderBy('codigo')
            ->get();

        return view('sueldos.ganancia_deduccion.index', compact('deducciones'));
    }

    public function editar(string $codigo, Request $request)
    {
        can('editar-ganancia-deduccion-sueldos');

        $deduccion = Ganancia_Deduccion_Sueldos::query()
            ->where('codigo', $codigo)
            ->firstOrFail();

        $aniosDisponibles = Ganancia_Deduccion_Valor_Sueldos::query()
            ->where('codigo', $codigo)
            ->distinct()
            ->orderByDesc('anio')
            ->pluck('anio')
            ->map(fn ($a) => (int) $a)
            ->values();

        if ($aniosDisponibles->isEmpty()) {
            $aniosDisponibles = collect([(int) date('Y')]);
        }

        $anio = (int) $request->input('anio', $aniosDisponibles->first());
        if (! $aniosDisponibles->contains($anio)) {
            $aniosDisponibles = $aniosDisponibles->push($anio)->sortDesc()->values();
        }

        $valoresPorMes = [];
        for ($mes = 1; $mes <= 12; $mes++) {
            $valoresPorMes[$mes] = '0.00';
        }

        $existentes = Ganancia_Deduccion_Valor_Sueldos::query()
            ->where('codigo', $codigo)
            ->where('anio', $anio)
            ->get();

        foreach ($existentes as $row) {
            $valoresPorMes[(int) $row->mes] = number_format((float) $row->valor_acumulado, 2, '.', '');
        }

        return view('sueldos.ganancia_deduccion.editar', compact(
            'deduccion',
            'codigo',
            'anio',
            'aniosDisponibles',
            'valoresPorMes'
        ));
    }

    public function actualizar(Request $request, string $codigo)
    {
        can('actualizar-ganancia-deduccion-sueldos');

        Ganancia_Deduccion_Sueldos::query()
            ->where('codigo', $codigo)
            ->firstOrFail();

        $validated = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'valores' => 'required|array',
            'valores.*' => 'nullable|numeric|min:0',
        ], [], [
            'anio' => 'año',
            'valores' => 'valores mensuales',
            'valores.*' => 'valor acumulado',
        ]);

        $anio = (int) $validated['anio'];

        DB::transaction(function () use ($codigo, $anio, $validated) {
            Ganancia_Deduccion_Valor_Sueldos::query()
                ->where('codigo', $codigo)
                ->where('anio', $anio)
                ->delete();

            for ($mes = 1; $mes <= 12; $mes++) {
                $valor = round((float) ($validated['valores'][$mes] ?? 0), 2);
                Ganancia_Deduccion_Valor_Sueldos::create([
                    'codigo' => $codigo,
                    'anio' => $anio,
                    'mes' => $mes,
                    'valor_acumulado' => $valor,
                ]);
            }
        });

        return redirect()->route('editar_ganancia_deduccion_sueldos', [
            'codigo' => $codigo,
            'anio' => $anio,
        ])->with('mensaje', "Valores Art. 30 de {$codigo} ({$anio}) actualizados con éxito");
    }
}
