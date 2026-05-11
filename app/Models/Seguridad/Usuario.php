<?php

namespace App\Models\Seguridad;

use App\Models\Admin\Rol;
use App\Models\Compras\SectorLegajocompra;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Oficinacompra;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Usuario_Cuentacontable;
use App\Models\Ventas\Vendedor;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use OwenIt\Auditing\Contracts\Auditable;

class Usuario extends Authenticatable implements Auditable
{
    use Notifiable;
    use \OwenIt\Auditing\Auditable;

    protected $remember_token = false;

    protected $table = 'usuario';

    protected $fillable = ['usuario', 'nombre', 'email', 'password', 'foto', 'centrocosto_id', 'vendedor_id', 'oficinacompra_id', 'sector_legajocompra_id'];

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol');
    }

    public function centrocostos()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function vendedores()
    {
        return $this->belongsTo(Vendedor::class, 'vendedor_id');
    }

    public function oficinacompras()
    {
        return $this->belongsTo(Oficinacompra::class, 'oficinacompra_id');
    }

    public function sectorLegajocompra()
    {
        return $this->belongsTo(SectorLegajocompra::class, 'sector_legajocompra_id');
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
        $centro = $this->relationLoaded('centrocostos') ? $this->centrocostos : $this->centrocostos()->first();
        $sector = $this->relationLoaded('sectorLegajocompra') ? $this->sectorLegajocompra : $this->sectorLegajocompra()->first();

        Session::put([
            'usuario' => $this->usuario,
            'usuario_id' => $this->id,
            'nombre_usuario' => $this->nombre,
            'centrocosto' => $centro,
            'centrocosto_id' => $this->centrocosto_id,
            'sector_legajocompra_id' => $this->sector_legajocompra_id,
            'sector_legajocompra_nombre' => $sector?->nombre,
            'vendedor_id' => $this->vendedor_id,
            'oficinacompra_id' => $this->oficinacompra_id,
            'foto_usuario' => $this->foto,
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

    public static function setFoto($request, $actual = false)
    {
        if ($request->foto_up) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_usuarios/$actual");
            }

            $imageName = Str::random(20).'.jpg';

            $upload = $request->foto_up;
            $image = Image::decode($upload)
                ->resize(300, 300);

            Storage::disk('public')->put("imagenes/fotos_usuarios/$imageName",
                $image->encodeUsingFileExtension($upload->getClientOriginalExtension(), quality: 70)
            );

            // $imagen = Image::read($foto);
            // $imagen->encode('jpg', 75);
            // $imagen->resize(300, 300, function ($constraint) {
            //    $constraint->upsize();
            // });

            // Storage::disk('public')->put("imagenes/fotos_usuarios/$imageName", $imagen->stream());
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
