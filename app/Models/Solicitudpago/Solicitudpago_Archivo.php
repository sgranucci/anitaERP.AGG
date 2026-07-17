<?php

namespace App\Models\Solicitudpago;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Solicitudpago_Archivo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'solicitudpago_archivo';

    protected $fillable = [
        'solicitudpago_id',
        'nro_linea',
        'archivo',
        'nombre_original',
        'usuario_id',
        'fecha',
        'hora',
    ];

    protected $casts = [
        'solicitudpago_id' => 'integer',
        'nro_linea' => 'integer',
        'usuario_id' => 'integer',
        'fecha' => 'date',
    ];

    public function solicitudpagos()
    {
        return $this->belongsTo(Solicitudpago::class, 'solicitudpago_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
