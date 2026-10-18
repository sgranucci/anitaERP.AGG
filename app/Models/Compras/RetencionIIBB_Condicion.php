<?php

namespace App\Models\Compras;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use App\Models\Configuracion\CondicionIIBB;
use OwenIt\Auditing\Contracts\Auditable;

class RetencionIIBB_Condicion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = [
                            'retencionIIBB_id', 'condicionIIBB_id', 'minimoretencion', 'minimoimponible', 
                            'porcentajeretencion'
                        ];
    protected $table = 'retencionIIBB_condicion';

    public function retencionesIIBB()
    {
        return $this->belongsTo(RetencionIIBB::class, 'retencionIIBB_id');
    }

    public function condicionesIIBB()
    {
        return $this->belongsTo(CondicionIIBB::class, 'condicionIIBB_id');
    }

}

