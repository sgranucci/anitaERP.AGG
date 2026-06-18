<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AjaxController extends Controller
{
    public function setSession(Request $request)
    {
        if (! $request->ajax()) {
            abort(404);
        }

        $rolId = (int) $request->input('rol_id');
        $roles = $request->session()->get('roles', []);
        $rolesPermitidos = collect($roles)->pluck('id')->map(fn ($id) => (int) $id);

        if ($rolId <= 0 || ($rolesPermitidos->isNotEmpty() && ! $rolesPermitidos->contains($rolId))) {
            return response()->json(['mensaje' => 'Rol inválido para este usuario.'], 422);
        }

        $request->session()->put([
            'rol_id' => $rolId,
            'rol_nombre' => $request->input('rol_nombre'),
        ]);

        return response()->json(['mensaje' => 'ok']);
    }
}
