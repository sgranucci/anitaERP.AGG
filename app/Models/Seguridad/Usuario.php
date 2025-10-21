<?php

namespace App\Models\Seguridad;

use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\Admin\Rol;
use App\Models\Seguridad\Usuario_Empresa;
use App\Models\Contable\Usuario_Cuentacontable;
use App\Models\Contable\Cuentacontable;
use App\Models\Contable\Centrocosto;
use App\Models\Configuracion\Empresa;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use OwenIt\Auditing\Contracts\Auditable;

class Usuario extends Authenticatable implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use Notifiable;
    protected $remember_token = false;
    protected $table = 'usuario';
    protected $fillable = ['usuario', 'nombre', 'email', 'password', 'foto', 'centrocosto_id'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol');
    }
	
    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function usuario_empresas()
    {
        return $this->belongsToMany(Empresa::class, 'usuario_empresa');
    }

    public function usuario_cuentacontables()
	{
    	return $this->hasMany(Usuario_Cuentacontable::class)->with('cuentacontables');
	}

    public function setSession($roles, $empresas)
    {
        Session::put([
            'usuario' => $this->usuario,
            'usuario_id' => $this->id,
            'nombre_usuario' => $this->nombre,
            'centrocosto' => $this->centrocosto,
            'foto_usuario' => $this->foto
        ]);
        if (count($roles) == 1) {
            Session::put(
                [
                    'rol_id' => $roles[0]['id'],
                    'rol_nombre' => $roles[0]['nombre'],
                ]
            );
        } else {
            Session::put('roles', $roles);
        }
        Session::put('usuario_empresas', $empresas);
    }

    public static function setFoto($foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_usuarios/$actual");
            }
            $imageName = Str::random(20) . '.jpg';
            $imagen = Image::make($foto)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put("imagenes/fotos_usuarios/$imageName", $imagen->stream());
            return $imageName;
        } else {
            return false;
        }
    }

    public function setPasswordAttribute($pass)
    {
        $this->attributes['password'] = Hash::make($pass);
    }
}
