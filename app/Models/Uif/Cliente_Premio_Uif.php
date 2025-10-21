<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Configuracion\Sala;
use App\Models\Ventas\Formapago;
use App\Traits\Uif\Cliente_Premio_UifTrait;
use Auth;

class Cliente_Premio_Uif extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;
	use Cliente_Premio_UifTrait;

    protected $fillable = [
							'cliente_uif_id', 'sala_id', 'juego_uif_id',  
							'fechaentrega', 'detalle', 'monto', 'moneda_id',
							'posicion', 'numerotito', 'fechatito', 'formapago_id',
							'piderecibopago', 'foto', 'creousuario_id'
						];
    protected $table = 'cliente_premio_uif';

	protected $casts = [
        'fechaentrega' => 'datetime',  
    ];
	
    public function clientes_uif()
	{
    	return $this->belongsTo(Cliente_Uif::class, 'cliente_uif_id');
	}

    public function cliente_premio_archivos_uif()
	{
    	return $this->hasMany(Cliente_Premio_Archivo_Uif::class, 'cliente_premio_uif_id');
	}

	public function salas()
	{
    	return $this->belongsTo(Sala::class, 'sala_id');
	}

	public function juegos_uif()
	{
    	return $this->belongsTo(Juego_Uif::class, 'juego_uif_id');
	}

	public function monedas()
	{
    	return $this->belongsTo(Moneda::class, 'moneda_id');
	}

	public function formapagos()
	{
    	return $this->belongsTo(Formapago::class, 'formapago_id');
	}

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'creousuario_id');
    }

    public static function setFoto($foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_uif/$actual");
            }
            $imageName = Str::random(20) . '.jpg';
            $imagen = Image::make($foto)->encode('jpg', 75);
            $imagen->resize(300, 300, function ($constraint) {
                $constraint->upsize();
            });
            Storage::disk('public')->put("imagenes/fotos_uif/$imageName", $imagen->stream());
            return $imageName;
        } else {
            return false;
        }
    }

}



