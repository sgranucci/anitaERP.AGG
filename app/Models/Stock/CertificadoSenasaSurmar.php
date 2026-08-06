<?php

namespace App\Models\Stock;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Camion;
use App\Models\Ventas\Cliente;
use App\Models\Ventas\Transporte;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class CertificadoSenasaSurmar extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    public const ESTADO_BORRADOR = 'BORRADOR';
    public const ESTADO_CONFIRMADO = 'CONFIRMADO';
    public const ESTADO_ANULADO = 'ANULADO';

    protected $table = 'certificado_senasa_surmar';

    protected $fillable = [
        'empresa_id',
        'numero',
        'serie',
        'fecha',
        'estado',
        'camion_id',
        'transporte_id',
        'cliente_id',
        'precinto',
        'origen',
        'procedencia',
        'opcion',
        'cantidad_bulto',
        'cantidad_caja',
        'cantidad_precinto',
        'ptr',
        'certif_sanitario',
        'establecimiento_nro',
        'nro_cert_interno',
        'nro_cert_patagonico',
        'establecimiento_destino',
        'temperatura',
        'abre_por_localidad',
        'genera_web',
        'genera_remito',
        'xml_path',
        'observacion',
        'punto_emision',
        'id_req',
        'cod_remito',
        'cod_autorizacion',
        'estado_afip',
        'resultado_afip',
        'fecha_emision_afip',
        'fecha_vto_afip',
        'qr_path',
        'tipo_movimiento',
        'categoria_emisor',
        'tipo_receptor',
        'categoria_receptor',
        'cuit_titular',
        'cuit_receptor',
        'cuit_depositario',
        'cuit_transportista',
        'cuit_conductor',
        'dominio_vehiculo',
        'dominio_acoplado',
        'cod_dom_origen',
        'cod_dom_destino',
        'distancia_km',
        'mensaje_afip',
        'usuario_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'temperatura' => 'float',
        'abre_por_localidad' => 'boolean',
        'genera_web' => 'boolean',
        'genera_remito' => 'boolean',
        'fecha_emision_afip' => 'date',
        'fecha_vto_afip' => 'date',
        'distancia_km' => 'float',
    ];

    public function camion(): BelongsTo
    {
        return $this->belongsTo(Camion::class, 'camion_id');
    }

    public function transporte(): BelongsTo
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function articulos(): HasMany
    {
        return $this->hasMany(CertificadoSenasaSurmarArticulo::class, 'certificado_senasa_surmar_id')->orderBy('linea');
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(CertificadoSenasaSurmarCliente::class, 'certificado_senasa_surmar_id')->orderBy('linea');
    }

    public function destinos(): HasMany
    {
        return $this->hasMany(CertificadoSenasaSurmarDestino::class, 'certificado_senasa_surmar_id')->orderBy('linea');
    }

    public function etiquetas(): HasMany
    {
        return $this->hasMany(CertificadoSenasaSurmarEtiqueta::class, 'certificado_senasa_surmar_id');
    }

    public function getEtiquetaAttribute(): string
    {
        return ($this->serie ?? 'A').'-'.str_pad((string) $this->numero, 6, '0', STR_PAD_LEFT);
    }

    public function esEditable(): bool
    {
        return $this->estado === self::ESTADO_BORRADOR;
    }
}
