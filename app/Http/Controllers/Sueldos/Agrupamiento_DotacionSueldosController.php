<?php

namespace App\Http\Controllers\Sueldos;

use App\Http\Controllers\Controller;
use App\Models\Stock\Color;
use App\Models\Sueldos\Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Agrupamiento_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Services\Sueldos\DotacionAgrupamientoAnitaSync;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Agrupamiento_DotacionSueldosController extends Controller
{
    /** Panel HTML de la solapa (dotación por sexo + form). */
    public function panel($agrupamientoId)
    {
        can('editar-agrupamiento-sueldos');
        $agrupamiento = Agrupamiento_Sueldos::findOrFail($agrupamientoId);

        return $this->responderPanel($agrupamiento);
    }

    public function guardar(Request $request, $agrupamientoId)
    {
        can('actualizar-agrupamiento-sueldos');
        $agrupamiento = Agrupamiento_Sueldos::findOrFail($agrupamientoId);

        $datos = $this->validar($request, $agrupamiento->id, null);
        Prenda_Agrupamiento_Sueldos::create($datos);

        return $this->responderPanel($agrupamiento, 'Prenda agregada a la dotación');
    }

    public function actualizar(Request $request, $id)
    {
        can('actualizar-agrupamiento-sueldos');
        $fila = Prenda_Agrupamiento_Sueldos::findOrFail($id);
        $agrupamiento = Agrupamiento_Sueldos::findOrFail($fila->agrupamiento_id);

        $fila->update($this->validar($request, $agrupamiento->id, $fila->id));

        return $this->responderPanel($agrupamiento, 'Dotación actualizada');
    }

    public function eliminar(Request $request, $id)
    {
        can('actualizar-agrupamiento-sueldos');
        $fila = Prenda_Agrupamiento_Sueldos::findOrFail($id);
        $agrupamiento = Agrupamiento_Sueldos::findOrFail($fila->agrupamiento_id);
        $fila->delete();

        return $this->responderPanel($agrupamiento, 'Prenda quitada de la dotación');
    }

    public function sincronizarAnita(Request $request, DotacionAgrupamientoAnitaSync $sync)
    {
        can('actualizar-agrupamiento-sueldos');
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $r = $sync->sincronizar();

        if (! empty($r['errores'])) {
            return redirect('sueldos/agrupamiento')
                ->with('error', 'No se pudo sincronizar la dotación desde Anita: '.implode(' | ', $r['errores']));
        }

        return redirect('sueldos/agrupamiento')->with(
            'mensaje',
            'Dotación sincronizada desde Anita: '.$r['importados'].' nuevas, '.$r['omitidos']
                .' existentes, '.$r['sin_mapeo'].' sin mapeo (de '.$r['en_anita'].' en Anita).'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, int $agrupamientoId, ?int $id): array
    {
        $validado = $request->validate([
            'sexo' => ['required', Rule::in(array_keys(Prenda_Agrupamiento_Sueldos::SEXOS))],
            'prenda_id' => ['required', 'integer', 'exists:prenda_sueldos,id'],
            'color_id' => ['nullable', 'integer', 'exists:color,id'],
            'limite_anual' => ['required', 'numeric', 'min:0', 'max:999999'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        return [
            'agrupamiento_id' => $agrupamientoId,
            'sexo' => $validado['sexo'],
            'prenda_id' => (int) $validado['prenda_id'],
            'color_id' => ! empty($validado['color_id']) ? (int) $validado['color_id'] : null,
            'limite_anual' => (float) $validado['limite_anual'],
            'orden' => (int) ($validado['orden'] ?? 0),
        ];
    }

    private function responderPanel(Agrupamiento_Sueldos $agrupamiento, ?string $mensaje = null)
    {
        $dotacion = Prenda_Agrupamiento_Sueldos::query()
            ->with(['prenda:id,codigo,descripcion', 'color:id,codigo,nombre'])
            ->where('agrupamiento_id', $agrupamiento->id)
            ->orderBy('sexo')->orderBy('orden')->orderBy('id')
            ->get()
            ->groupBy('sexo');

        $prendas = Prenda_Sueldos::query()->where('activo', true)
            ->orderBy('orden')->orderBy('descripcion')->get(['id', 'codigo', 'descripcion']);
        $colores = Color::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        $html = view('sueldos.agrupamiento.partials.dotacion', [
            'agrupamiento' => $agrupamiento,
            'dotacion' => $dotacion,
            'prendas' => $prendas,
            'colores' => $colores,
            'sexos' => Prenda_Agrupamiento_Sueldos::SEXOS,
            'puedeEditar' => can('actualizar-agrupamiento-sueldos', false),
        ])->render();

        return response()->json(['html' => $html, 'mensaje' => $mensaje]);
    }
}
