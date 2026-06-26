<?php

namespace App\Http\Controllers\Seguridad;

use App\Http\Controllers\Controller;
use App\Models\Seguridad\Usuario;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/';

    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function index()
    {
        return view('seguridad.index');
    }

    protected function attemptLogin(Request $request)
    {
        $usuario = Usuario::where($this->username(), $request->{$this->username()})->first();

        if ($usuario !== null && $usuario->suspendido) {
            throw ValidationException::withMessages([
                $this->username() => ['Su cuenta está suspendida. Contacte al administrador del sistema.'],
            ]);
        }

        return $this->guard()->attempt(
            $this->credentials($request),
            $request->boolean('remember')
        );
    }

    protected function authenticated(Request $request, $user)
    {
        $roles = $user->roles()->get();
        $empresas = $user->usuario_empresas()->get();
        if ($roles->isNotEmpty()) {
            $user->loadMissing(['centrocostos', 'sectorLegajocompra', 'depositosAutorizados', 'tipotransaccionesStockAutorizadas']);
            $user->setSession($roles->toArray(), $empresas->toArray());
        } else {
            $this->guard()->logout();
            $request->session()->invalidate();

            return redirect('seguridad/login')->withErrors(['error' => 'Este usuario no tiene un rol activo']);
        }
    }

    public function username()
    {
        return 'usuario';
    }
}
