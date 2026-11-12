<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownCasinoOnline;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownResultadoOnlineCalculo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TesoreriaCrownCasinoOnlineController extends Controller
{
    public function index(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-casino-online');

        $fechaDesde = $request->input('fecha_desde', now()->startOfMonth()->toDateString());
        $fechaHasta = $request->input('fecha_hasta', now()->toDateString());
        $filas = TesoreriaCrownCasinoOnline::query()
            ->whereDate('fecha', '>=', $fechaDesde)
            ->whereDate('fecha', '<=', $fechaHasta)
            ->orderByDesc('fecha')
            ->paginate(20)
            ->appends($request->query());

        return view('caja.tesoreria_crown.casino_online.index', [
            'filas' => $filas,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
        ]);
    }

    public function crear(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('crear-tesoreria-crown-casino-online');

        $fecha = (string) $request->input('fecha', now()->toDateString());
        $existente = TesoreriaCrownCasinoOnline::deFecha($fecha);
        if ($existente) {
            return redirect()->route('editar_tesoreria_crown_casino_online', ['id' => $existente->id])
                ->with('mensaje', 'Ya hay carga para esa fecha; se abre la edición.');
        }

        return view('caja.tesoreria_crown.casino_online.form', [
            'fila' => new TesoreriaCrownCasinoOnline(['fecha' => $fecha]),
            'edicion' => false,
        ]);
    }

    public function editar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('editar-tesoreria-crown-casino-online');
        $fila = $this->fila((int) $request->input('id'));

        return view('caja.tesoreria_crown.casino_online.form', [
            'fila' => $fila,
            'edicion' => true,
        ]);
    }

    public function guardar(Request $request)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        $id = (int) $request->input('id', 0);
        can($id > 0 ? 'actualizar-tesoreria-crown-casino-online' : 'crear-tesoreria-crown-casino-online');

        $request->validate([
            'fecha' => [
                'required',
                'date',
                Rule::unique('tesoreria_crown_casino_online', 'fecha')->ignore($id),
            ],
        ]);

        $datos = $this->datos($request);
        if ($id > 0) {
            $fila = $this->fila($id);
            $fila->update($datos);
        } else {
            TesoreriaCrownCasinoOnline::query()->create($datos);
        }

        return redirect()
            ->route('tesoreria_crown_casino_online', [
                'fecha_desde' => $datos['fecha'],
                'fecha_hasta' => $datos['fecha'],
            ])
            ->with('mensaje', 'Carga online diaria guardada.');
    }

    public function eliminar($id)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('eliminar-tesoreria-crown-casino-online');
        $fila = $this->fila((int) $id);
        $fecha = $fila->fecha?->format('d/m/Y');
        $fila->delete();

        return redirect()
            ->route('tesoreria_crown_casino_online')
            ->with('mensaje', 'Se eliminó la carga del '.$fecha.'.');
    }

    private function fila(int $id): TesoreriaCrownCasinoOnline
    {
        $fila = TesoreriaCrownCasinoOnline::query()->findOrFail($id);

        return $fila;
    }

    /**
     * @return array<string, mixed>
     */
    private function datos(Request $request): array
    {
        $delRio = TesoreriaCrownResultadoOnlineCalculo::desdeApostadoPagado(
            $this->importe($request->input('apostado')),
            $this->importe($request->input('pagado'))
        );
        $betsur = TesoreriaCrownResultadoOnlineCalculo::desdeApostadoPagado(
            $this->importe($request->input('betsur_apostado')),
            $this->importe($request->input('betsur_pagado'))
        );
        $ad = TesoreriaCrownResultadoOnlineCalculo::desdeApostadoPagado(
            $this->importe($request->input('ad_apostado')),
            $this->importe($request->input('ad_pagado'))
        );

        return [
            'empresa_id' => null,
            'fecha' => $request->input('fecha'),
            'attendance' => (int) $request->input('attendance', 0),
            'apostado' => $this->importe($request->input('apostado')),
            'pagado' => $this->importe($request->input('pagado')),
            'drop' => $this->importe($request->input('drop')),
            'resultado' => $delRio['resultado'],
            'porcentaje' => $delRio['porcentaje'],
            'poder_publico' => $this->importe($request->input('poder_publico')),
            'betsur_attendance' => (int) $request->input('betsur_attendance', 0),
            'betsur_apostado' => $this->importe($request->input('betsur_apostado')),
            'betsur_pagado' => $this->importe($request->input('betsur_pagado')),
            'betsur_drop' => $this->importe($request->input('betsur_drop')),
            'betsur_resultado' => $betsur['resultado'],
            'betsur_porcentaje' => $betsur['porcentaje'],
            'betsur_poder_publico' => $this->importe($request->input('betsur_poder_publico')),
            'ad_attendance' => (int) $request->input('ad_attendance', 0),
            'ad_apostado' => $this->importe($request->input('ad_apostado')),
            'ad_pagado' => $this->importe($request->input('ad_pagado')),
            'ad_drop' => $this->importe($request->input('ad_drop')),
            'ad_resultado' => $ad['resultado'],
            'ad_porcentaje' => $ad['porcentaje'],
            'ad_poder_publico' => $this->importe($request->input('ad_poder_publico')),
            'usuario_id' => auth()->id() ? (int) auth()->id() : null,
        ];
    }

    private function importe(mixed $valor): float
    {
        $texto = trim((string) $valor);
        if (str_contains($texto, ',') && str_contains($texto, '.')) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (str_contains($texto, ',')) {
            $texto = str_replace(',', '.', $texto);
        }

        return round((float) $texto, 2);
    }
}
