<?php

namespace App\Models\Logistica;

use App\Models\Admin\Rol;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Articulo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OwenIt\Auditing\Contracts\Auditable;

class LogisticaHabilitacion extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'logistica_habilitacion';

    protected $fillable = [
        'nivel', 'alcance', 'rol_id', 'usuario_id', 'tipo_solicitud_id',
        'catalogo_categoria_id', 'articulo_id', 'habilitado',
    ];

    protected $casts = [
        'rol_id' => 'integer',
        'usuario_id' => 'integer',
        'tipo_solicitud_id' => 'integer',
        'catalogo_categoria_id' => 'integer',
        'articulo_id' => 'integer',
        'habilitado' => 'boolean',
    ];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function tipoSolicitud(): BelongsTo
    {
        return $this->belongsTo(LogisticaTipoSolicitud::class, 'tipo_solicitud_id');
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(LogisticaCatalogoCategoria::class, 'catalogo_categoria_id');
    }

    public function articulo(): BelongsTo
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }
}
