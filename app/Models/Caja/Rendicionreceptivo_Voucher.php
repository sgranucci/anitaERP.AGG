<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Auth;

class Rendicionreceptivo_Voucher extends Model implements Auditable
{
    use SoftDeletes;
	use \OwenIt\Auditing\Auditable;

    protected $fillable = [
							'rendicionreceptivo_id', 'voucher_id'
						];
    protected $table = 'rendicionreceptivo_voucher';

    public function rendicionreceptivos()
	{
    	return $this->belongsTo(Rendicionreceptivo::class, 'rendicionreceptivo_id', 'id');
	}

	public function vouchers()
	{
    	return $this->belongsTo(Voucher::class, 'voucher_id', 'id')->with('voucher_formapagos');
	}

}



