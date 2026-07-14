<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CertificadoSanitario extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'certificado_sanitario';

    protected $fillable = [
        'numero',
        'serie',
        'fecha',
        'camion_id',
        'precinto',
        'origen',
        'opcion',
        'cantidad_bulto',
        'cantidad_caja',
        'cantidad_precinto',
        'procedencia',
        'ptr',
        'certif_sanitario',
        'establecimiento_nro',
        'transporte_id',
        'nro_cert_interno',
        'nro_cert_patagonico',
        'establecimiento_destino',
        'temperatura',
        'nro_remito',
        'abre_por_localidad',
        'genera_web',
        'xml_frio',
        'xml_sin_frio',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'temperatura' => 'float',
        'abre_por_localidad' => 'boolean',
        'genera_web' => 'boolean',
    ];

    public function camion(): BelongsTo
    {
        return $this->belongsTo(Camion::class, 'camion_id');
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(CertificadoSanitarioArticulo::class, 'certificado_sanitario_id')->orderBy('linea');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(CertificadoSanitarioCliente::class, 'certificado_sanitario_id')->orderBy('linea');
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(CertificadoSanitarioDestino::class, 'certificado_sanitario_id')->orderBy('linea');
    }

    public function getEtiquetaAttribute(): string
    {
        return ($this->serie ?? 'A').'-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }
}
