<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Support\Configuracion\ModuloAvisoProgramadoDespachoSupport;
use App\Support\Configuracion\UsuarioAvisoPreferenciaSupport;
use Illuminate\Http\Request;

class MisPreferenciasAvisoController extends Controller
{
    public function editar()
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        if ($usuarioId <= 0) {
            abort(403);
        }

        $tipos = ModuloAvisoProgramadoDespachoSupport::filtrarTiposVisiblesEnCatalogo(
            ModuloAvisoTipo::query()
                ->where('activo', true)
                ->orderBy('modulo')
                ->orderBy('nombre')
                ->get()
        );

        return view('configuracion.mis_preferencias_aviso.editar', [
            'preferencias' => UsuarioAvisoPreferenciaSupport::mapaParaUsuario($usuarioId),
            'preferencias_tipo' => UsuarioAvisoPreferenciaSupport::mapaPorTipoParaUsuario($usuarioId),
            'tipos' => $tipos,
            'canales' => [
                UsuarioAvisoPreferenciaSupport::CANAL_MAIL => 'Correo',
                UsuarioAvisoPreferenciaSupport::CANAL_IN_APP => 'Campanita',
                UsuarioAvisoPreferenciaSupport::CANAL_WHATSAPP => 'WhatsApp',
                UsuarioAvisoPreferenciaSupport::CANAL_DIGEST => 'Digest diario Mis aprobaciones',
            ],
            'canales_tipo' => [
                UsuarioAvisoPreferenciaSupport::CANAL_MAIL => 'Correo',
                UsuarioAvisoPreferenciaSupport::CANAL_IN_APP => 'Campanita',
                UsuarioAvisoPreferenciaSupport::CANAL_WHATSAPP => 'WhatsApp',
            ],
        ]);
    }

    public function actualizar(Request $request)
    {
        $usuarioId = (int) (auth()->id() ?? 0);
        if ($usuarioId <= 0) {
            abort(403);
        }

        $prefs = [];
        foreach (UsuarioAvisoPreferenciaSupport::CANALES as $canal) {
            $prefs[$canal] = $request->boolean('canal_'.$canal);
        }
        UsuarioAvisoPreferenciaSupport::guardar($usuarioId, $prefs);

        $porTipo = [];
        $raw = $request->input('tipo', []);
        if (is_array($raw)) {
            foreach ($raw as $tipoId => $canales) {
                if (! is_array($canales)) {
                    continue;
                }
                $porTipo[(int) $tipoId] = [];
                foreach (UsuarioAvisoPreferenciaSupport::CANALES_POR_TIPO as $canal) {
                    if (array_key_exists($canal, $canales)) {
                        $porTipo[(int) $tipoId][$canal] = filter_var($canales[$canal], FILTER_VALIDATE_BOOLEAN);
                    }
                }
            }
        }
        UsuarioAvisoPreferenciaSupport::guardarPorTipo($usuarioId, $porTipo);

        return redirect()
            ->route('editar_mis_preferencias_aviso')
            ->with('mensaje', 'Preferencias de avisos guardadas.');
    }
}
