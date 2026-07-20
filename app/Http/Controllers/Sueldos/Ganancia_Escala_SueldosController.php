<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Sueldos\Ganancia_Escala_Tramo_Sueldos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Ganancia_Escala_SueldosController extends Controller
{
    public function index()
    {
        can('listar-ganancia-escala-sueldos');

        $periodos = Ganancia_Escala_Tramo_Sueldos::query()
            ->select('anio', 'mes')
            ->selectRaw('COUNT(*) as tramos')
            ->groupBy('anio', 'mes')
            ->orderByDesc('anio')
            ->orderByDesc('mes')
            ->get();

        return view('sueldos.ganancia_escala.index', compact('periodos'));
    }

    public function editar(int $anio, int $mes)
    {
        can('editar-ganancia-escala-sueldos');

        $tramos = Ganancia_Escala_Tramo_Sueldos::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->orderBy('nro_tramo')
            ->get();

        return view('sueldos.ganancia_escala.editar', compact('anio', 'mes', 'tramos'));
    }

    public function actualizar(Request $request, int $anio, int $mes)
    {
        can('actualizar-ganancia-escala-sueldos');

        $validated = $request->validate([
            'tramos' => 'required|array|min:1',
            'tramos.*.desde' => 'required|numeric|min:0',
            'tramos.*.hasta' => 'nullable|numeric|min:0',
            'tramos.*.fijo' => 'required|numeric|min:0',
            'tramos.*.alicuota' => 'required|numeric|min:0',
            'tramos.*.excedente' => 'required|numeric|min:0',
        ], [], [
            'tramos' => 'tramos',
            'tramos.*.desde' => 'desde',
            'tramos.*.hasta' => 'hasta',
            'tramos.*.fijo' => 'fijo',
            'tramos.*.alicuota' => 'alícuota',
            'tramos.*.excedente' => 'excedente',
        ]);

        DB::transaction(function () use ($anio, $mes, $validated) {
            Ganancia_Escala_Tramo_Sueldos::query()
                ->where('anio', $anio)
                ->where('mes', $mes)
                ->delete();

            $nro = 0;
            foreach ($validated['tramos'] as $tramo) {
                $nro++;
                $hasta = $tramo['hasta'] ?? null;
                if ($hasta === '' || $hasta === null) {
                    $hasta = null;
                } else {
                    $hasta = round((float) $hasta, 2);
                }

                Ganancia_Escala_Tramo_Sueldos::create([
                    'anio' => $anio,
                    'mes' => $mes,
                    'desde' => round((float) $tramo['desde'], 2),
                    'hasta' => $hasta,
                    'fijo' => round((float) $tramo['fijo'], 2),
                    'alicuota' => round((float) $tramo['alicuota'], 4),
                    'excedente' => round((float) $tramo['excedente'], 2),
                    'nro_tramo' => $nro,
                ]);
            }
        });

        return redirect()->route('consultar_ganancia_escala_sueldos')
            ->with('mensaje', "Escala Art. 94 {$anio}/{$mes} actualizada con éxito");
    }
}
