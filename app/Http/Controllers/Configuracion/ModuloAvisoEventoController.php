<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ModuloAvisoEventoSuscripcion;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Support\Configuracion\ModuloAvisoCoberturaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuloAvisoEventoController extends Controller
{
    public function index(): View
    {
        can('listar-modulo-aviso');

        $catalogo = config('modulo_aviso_eventos.catalogo', []);
        $subs = ModuloAvisoEventoSuscripcion::query()
            ->with('tipo')
            ->orderBy('evento')
            ->get();

        return view('configuracion.modulo_aviso_evento.index', [
            'catalogo' => is_array($catalogo) ? $catalogo : [],
            'subs' => $subs,
            'tipos' => ModuloAvisoTipo::query()->where('activo', true)->orderBy('modulo')->orderBy('nombre')->get(),
            'cobertura_eventos' => ModuloAvisoCoberturaSupport::matrizEventos(),
            'envios_directos' => ModuloAvisoCoberturaSupport::enviosDirectosDocumentados(),
            'resumen_tipos' => ModuloAvisoCoberturaSupport::resumenTiposConDestinatarios(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $datos = $request->validate([
            'evento' => 'required|string|max:120',
            'modulo_aviso_tipo_id' => 'required|integer|exists:modulo_aviso_tipo,id',
            'activo' => 'nullable|boolean',
            'condiciones_json' => 'nullable|string|max:4000',
        ]);

        $condiciones = null;
        $raw = trim((string) ($datos['condiciones_json'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return back()->withInput()->with('error', 'Condiciones: JSON inválido.');
            }
            $condiciones = $decoded;
        }

        ModuloAvisoEventoSuscripcion::query()->updateOrCreate(
            [
                'evento' => $datos['evento'],
                'modulo_aviso_tipo_id' => (int) $datos['modulo_aviso_tipo_id'],
            ],
            [
                'activo' => $request->boolean('activo', true),
                'condiciones' => $condiciones,
            ]
        );

        return redirect()->route('consultar_modulo_aviso_evento')
            ->with('mensaje', 'Suscripción guardada.');
    }

    public function eliminar(int $id): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        EloquentAuditDeleteSupport::each(
            ModuloAvisoEventoSuscripcion::query()->whereKey($id)
        );

        return redirect()->route('consultar_modulo_aviso_evento')
            ->with('mensaje', 'Suscripción eliminada.');
    }
}
