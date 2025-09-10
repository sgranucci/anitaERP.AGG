<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use App\Models\Seguridad\Usuario;
use App\Models\Admin\Rol;
use App\Http\Requests\ValidacionUsuario;

class UsuarioController extends Controller
{

    public function index()
    {
        $datas = Usuario::with('roles:id,nombre')->orderBy('id')->get();
        return view('admin.usuario.index', compact('datas'));
    }

    public function crear()
    {
        $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        return view('admin.usuario.crear', compact('rols'));
    }


    public function guardar(ValidacionUsuario $request)
    {
        if ($foto = Usuario::setFoto($request->foto_up))
            $request->request->add(['foto' => $foto]);

        $usuario = Usuario::create($request->all());
        $usuario->roles()->sync($request->rol_id);
        return redirect('admin/usuario')->with('mensaje', 'Usuario creado con exito');
    }


    public function editar($id)
    {
        $rols = Rol::orderBy('id')->pluck('nombre', 'id')->toArray();
        $data = Usuario::with('roles')->findOrFail($id);
        return view('admin.usuario.editar', compact('data', 'rols'));
    }


    public function actualizar(ValidacionUsuario $request, $id)
    {
        $usuario = Usuario::findOrFail($id);
        if ($foto = Usuario::setFoto($request->foto_up, $usuario->foto))
            $request->request->add(['foto' => $foto]);
        $usuario->update(array_filter($request->all()));
        $usuario->roles()->sync($request->rol_id);
        return redirect('admin/usuario')->with('mensaje', 'Usuario actualizado con exito');
    }

    public function eliminar(Request $request, $id)
    {
        if ($request->ajax()) {
            $usuario = Usuario::findOrFail($id);
            $usuario->roles()->detach();
            $usuario->delete();
            Storage::disk('public')->delete("imagenes/fotos_usuarios/$usuario->foto");
            return response()->json(['mensaje' => 'ok']);
         } else {
            abort(404);
        }
    }

    public function crearUsuarioRemoto(Request $request)
    {
        $separado = explode(" ", $request->nombre);

        $primerLetra = substr($separado[0], 0, 1);

        if (count($separado) > 1)
        {
            $apellido = $separado[count($separado)-1];
            $login = strtolower($primerLetra.$apellido);
        }
        else
            $login = strtolower($separado[0]);

        // Verifica que no exista
        $data = Usuario::where('usuario', $login)->first();
        if (!$data)
        {
            $password = config('ticket.passwordNuevoUsuario');
            $passwordHash = Hash::make($password);

            // Busca el rol
            $rolId = 1;
            foreach(config('ticket.rolTecnico') as $areadestino)
            {
                if ($areadestino['areadestino_id'] == $request->areadestino_id)
                    $rolId = $areadestino['rol_id'];
            }
            $dataUsuario = ['usuario' => $login,
                            'password' => $passwordHash,
                            'nombre' => $request->nombre,
                            'email' => $login.config('ticket.dominioEmail')
                            ];

            $usuario = Usuario::create($dataUsuario);
            $usuario->roles()->sync($rolId);
        }
    }

    public function leerUsuario()
    {
        return Usuario::with('roles:id,nombre')->orderBy('id')->get();
    }
}
