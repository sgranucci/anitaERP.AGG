<?php

namespace App\Models\Contable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use OwenIt\Auditing\Contracts\Auditable;

class Retencionimpositiva_Arca extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $fillable = ['id', 'empresa_id', 'cuit', 'nombre', 'impuesto', 'descripcionimpuesto', 'regimen', 'descripcionregimen',
                            'fecharetencion', 'numerocertificado', 'descripcionoperacion', 'montoretencion', 'numerocomprobante',
                            'fechacomprobante', 'descripcioncomprobante', 'fecharegistracion'];
    protected $table = 'retencionimpositiva_arca';

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

}