<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use App\Support\Configuracion\WhatsappCanalSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UsuariosCelularMasivoController extends Controller
{
    public function editar(): View
    {
        can('actualizar-modulo-aviso');

        return view('configuracion.usuarios_celular_masivo.editar');
    }

    public function actualizar(Request $request): RedirectResponse
    {
        can('actualizar-modulo-aviso');

        $datos = $request->validate([
            'lineas' => 'required|string|max:200000',
        ]);

        $lineas = preg_split('/\r\n|\r|\n/', (string) $datos['lineas']) ?: [];
        $ok = 0;
        $errores = [];

        foreach ($lineas as $nro => $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            // formato: login;celular  ó  login,celular  ó  email;celular
            $partes = preg_split('/[;,\t]+/', $linea) ?: [];
            if (count($partes) < 2) {
                $errores[] = 'Línea '.($nro + 1).': formato inválido (login;celular)';
                continue;
            }
            $clave = trim((string) $partes[0]);
            $celularRaw = trim((string) $partes[1]);
            $normalizado = WhatsappCanalSupport::normalizarCelular($celularRaw);
            if ($normalizado === null) {
                $errores[] = 'Línea '.($nro + 1).': celular inválido ('.$celularRaw.')';
                continue;
            }

            $usuario = Usuario::query()
                ->where(function ($q) use ($clave) {
                    $q->where('usuario', $clave)->orWhere('email', $clave);
                })
                ->first();
            if (! $usuario) {
                $errores[] = 'Línea '.($nro + 1).': usuario no encontrado ('.$clave.')';
                continue;
            }

            $usuario->celular = $normalizado;
            $usuario->save();
            $ok++;
        }

        $msg = "Celulares actualizados: {$ok}.";
        if ($errores !== []) {
            $msg .= ' Con observaciones: '.implode(' · ', array_slice($errores, 0, 8));
            if (count($errores) > 8) {
                $msg .= ' … (+'.(count($errores) - 8).')';
            }
        }

        return redirect()->route('editar_usuarios_celular_masivo')
            ->with($ok > 0 ? 'mensaje' : 'error', $msg);
    }
}
