<?php

namespace App\Models\Caja;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Traits\Caja\TalonariovoucherTrait;

class Talonariovoucher extends Model
{
    use TalonariovoucherTrait;

    protected $fillable = ['nombre', 'serie', 'origenvoucher_id', 'desdenumero', 'hastanumero',
                            'fechainicio', 'fechacierre', 'estado'];
    protected $table = 'talonariovoucher';

    public function origenesvoucher()
	{
    	return $this->belongsTo(Origenvoucher::class, 'origenvoucher_id', 'id');
	}

}



