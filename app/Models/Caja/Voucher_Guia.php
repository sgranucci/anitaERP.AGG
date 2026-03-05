<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Receptivo\Guia;

class Voucher_Guia extends Model
{
    use SoftDeletes;
    protected $fillable = [
							'voucher_id', 'guia_id', 'tipocomision',  
							'porcentajecomision', 'montocomision', 'ordenservicio_id'
							];
    protected $table = 'voucher_guia';

    public function vouchers()
	{
    	return $this->belongsTo(Voucher::class, 'voucher_id');
	}

    public function guias()
	{
    	return $this->belongsTo(Guia::class, 'guia_id');
	}

}



