<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;
use App\Models\Configuracion\Empresa;
use App\Models\Receptivo\Guia;
use App\Models\Receptivo\Movil;
use App\Models\Seguridad\Usuario;
use Auth;

class Rendicionreceptivo extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = ['fecha', 'empresa_id', 'caja_id',
							'numerotalonario', 'guia_id', 'movil_id', 'ordenservicio_id', 'desdekm',
                            'hastakm', 'observacion', 'usuario_id'];
    protected $table = 'rendicionreceptivo';

    public function guias()
	{
    	return $this->belongsTo(Guia::class, 'guia_id');
	}

    public function moviles()
	{
    	return $this->belongsTo(Movil::class, 'movil_id');
	}
	
	public function cajas()
	{
    	return $this->belongsTo(Caja::class, 'caja_id');
	}
	
	public function empresas()
	{
    	return $this->belongsTo(Empresa::class, 'empresa_id');
	}
	
    public function rendicionreceptivo_caja_movimientos()
	{
    	return $this->hasMany(Rendicionreceptivo_Caja_Movimiento::class, 'rendicionreceptivo_id')
				->with('caja_movimientos');
	}

    public function rendicionreceptivo_vouchers()
	{
    	return $this->hasMany(Rendicionreceptivo_Voucher::class, 'rendicionreceptivo_id')
				->with('vouchers');
	}

	public function rendicionreceptivo_formapagos()
	{
    	return $this->hasMany(Rendicionreceptivo_Formapago::class, 'rendicionreceptivo_id');
	}

	public function rendicionreceptivo_comisiones()
	{
    	return $this->hasMany(Rendicionreceptivo_Comision::class, 'rendicionreceptivo_id')
				->with('monedas')->with('vouchers')->with('cuentacajas');
	}

	public function rendicionreceptivo_adelantos()
	{
    	return $this->hasMany(Rendicionreceptivo_Adelanto::class, 'rendicionreceptivo_id')
				->with('caja_movimientos');
	}

	public function caja_movimientos()
	{
    	return $this->hasMany(Caja_Movimiento::class, 'rendicionreceptivo_id')->with('conceptogastos')->with('caja_movimiento_cuentacajas');
	}

	public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

	protected static function boot()
	{
		parent::boot();

		static::deleting(function ($rendicionreceptivo) {
			$rendicionreceptivo->rendicionreceptivo_caja_movimientos()->delete();
			$rendicionreceptivo->rendicionreceptivo_vouchers()->delete();
			$rendicionreceptivo->rendicionreceptivo_formapagos()->delete();
			$rendicionreceptivo->rendicionreceptivo_comisiones()->delete();
			$rendicionreceptivo->rendicionreceptivo_adelantos()->delete();
		});
	}
}



