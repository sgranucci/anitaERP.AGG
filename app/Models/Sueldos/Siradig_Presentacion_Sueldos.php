<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\SiradigTablas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

/**
 * Cabecera de una presentación F572 Web (SiRADIG - ARCA), sección A o B.
 */
class Siradig_Presentacion_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'siradig_presentacion_sueldos';

    protected $fillable = [
        'empresa_id',
        'empleado_id',
        'seccion',
        'version',
        'periodo',
        'nro_presentacion',
        'fecha_presentacion',
        'empleado_cuit',
        'empleado_tipo_doc',
        'empleado_apellido',
        'empleado_nombre',
        'dom_provincia',
        'dom_cp',
        'dom_localidad',
        'dom_calle',
        'dom_nro',
        'dom_piso',
        'dom_dpto',
        'agente_retencion_cuit',
        'agente_retencion_denominacion',
        'es_agente_retencion',
        'vigente',
        'archivo_nombre',
        'archivo_hash',
        'xml_crudo',
        'importado_por_id',
        'importado_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'empleado_id' => 'integer',
        'periodo' => 'integer',
        'nro_presentacion' => 'integer',
        'empleado_tipo_doc' => 'integer',
        'dom_provincia' => 'integer',
        'es_agente_retencion' => 'boolean',
        'vigente' => 'boolean',
        'fecha_presentacion' => 'date',
        'importado_at' => 'datetime',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function empleado(): BelongsTo
    {
        return $this->belongsTo(Empleado_Sueldos::class, 'empleado_id');
    }

    public function importadoPor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'importado_por_id');
    }

    public function cargasFamilia(): HasMany
    {
        return $this->hasMany(Siradig_Carga_Familia_Sueldos::class, 'presentacion_id');
    }

    public function otrosEmpleadores(): HasMany
    {
        return $this->hasMany(Siradig_Otro_Empleador_Sueldos::class, 'presentacion_id');
    }

    public function conceptos(): HasMany
    {
        return $this->hasMany(Siradig_Concepto_Sueldos::class, 'presentacion_id');
    }

    public function deducciones(): HasMany
    {
        return $this->conceptos()->where('grupo', SiradigTablas::GRUPO_DEDUCCION);
    }

    public function retenciones(): HasMany
    {
        return $this->conceptos()->where('grupo', SiradigTablas::GRUPO_RETENCION);
    }

    public function ajustes(): HasMany
    {
        return $this->conceptos()->where('grupo', SiradigTablas::GRUPO_AJUSTE);
    }

    public function datosAdicionales(): HasMany
    {
        return $this->hasMany(Siradig_Dato_Adicional_Sueldos::class, 'presentacion_id');
    }
}
