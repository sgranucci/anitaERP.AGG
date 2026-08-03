<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Configuracion\Moneda;
use App\Models\Configuracion\Sala;
use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Formapago;
use App\Services\Uif\ClientePremioUifFotoTesoreria;
use App\Support\Uif\ClienteUifArchivoStorage;
use App\Traits\Uif\Cliente_Premio_UifTrait;

class Cliente_Premio_Uif extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;
	use Cliente_Premio_UifTrait;

    protected $fillable = [
							'anita_inropremioid', 'cliente_uif_id', 'sala_id', 'juego_uif_id',
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
                ClientePremioUifFotoTesoreria::deletePublicFotoIfUnused((string) $actual);
            }
            $dir = ClienteUifArchivoStorage::dirFotosPremio();
            if (! ClienteUifArchivoStorage::ensureDir($dir)) {
                return false;
            }
            $imageName = Str::random(20) . '.jpg';
            $image = Image::decode($foto)
                ->resizeDown(300, 300);
            file_put_contents(
                $dir.DIRECTORY_SEPARATOR.$imageName,
                $image->encodeUsingFileExtension('jpg', quality: 75)
            );

            return $imageName;
        } else {
            return false;
        }
    }

}



