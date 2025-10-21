<?php

namespace App\Http\Controllers\Seguridad;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use Hash;

class HomeController extends Controller
{
    public function cambiaPassword()
    {
        return view('seguridad.cambiapassword');
    }

    public function grabaPassword(Request $request)
    {
        # Validation
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required|confirmed',
        ]);

        #Match The Old Password
        if(!Hash::check($request->old_password, auth()->user()->password)){
            return back()->with("error", "Password Actual no coincide!");
        }

        #Update the new Password
        Usuario::whereId(auth()->user()->id)->update([
            'password' => Hash::make($request->new_password)
        ]);

        return back()->with("status", "Password cambiada correctamente!");
    }
}
