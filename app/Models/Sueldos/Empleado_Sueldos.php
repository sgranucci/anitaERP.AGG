<?php

namespace App\Models\Sueldos;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Localidad;
use App\Models\Configuracion\Pais;
use App\Models\Configuracion\Provincia;
use App\Models\Contable\Centrocosto;
use App\Models\Seguridad\Usuario;
use App\Support\Sueldos\EmpleadoEstados;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Empleado_Sueldos extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empleado_sueldos';

    protected $fillable = [
        'empresa_id',
        'legajo',
        'nombre',
        'domicilio',
        'entre_calles',
        'localidad',
        'codigo_postal',
        'provincia',
        'pais_id',
        'provincia_id',
        'localidad_id',
        'telefono',
        'telefono_emergencia',
        'email',
        'nacionalidad',
        'pais_nacimiento_id',
        'documento',
        'fecha_nacimiento',
        'cuil',
        'sexo',
        'estado_civil',
        'estado',
        'confidencial',
        'fecha_ingreso',
        'fecha_egreso',
        'motivoegreso_id',
        'comentario_baja',
        'categoria_id',
        'agrupamiento_id',
        'lugartrabajo_id',
        'centrocosto_id',
        'obrasocial_id',
        'afiliacion_os',
        'sindicato_id',
        'vacacion_id',
        'art_id',
        'sueldo_basico',
        'jornal_dia',
        'jornal_hora',
        'codigo_liquidacion',
        'antiguedad_anterior',
        'cbu',
        'cuenta_bancaria',
        'banco_codigo',
        'mano_obra',
        'personal_contratado',
        'codigo_afjp',
        'situacion_sijp',
        'condicion_sijp',
        'modalidad_sijp',
        'siniestrado_sijp',
        'marca_reduccion_sijp',
        'tipo_empresa_sijp',
        'regimen_sijp',
        'a_cargo_de',
        'puesto_jefe',
        'clave_alta_temprana',
        'foto',
        'usuario_alta_id',
        'usuario_autoriza_id',
        'autorizado_at',
    ];

    protected $casts = [
        'empresa_id' => 'integer',
        'legajo' => 'integer',
        'categoria_id' => 'integer',
        'centrocosto_id' => 'integer',
        'estado_civil' => 'integer',
        'confidencial' => 'boolean',
        'sueldo_basico' => 'decimal:4',
        'jornal_dia' => 'decimal:4',
        'jornal_hora' => 'decimal:4',
        'fecha_nacimiento' => 'date',
        'fecha_ingreso' => 'date',
        'fecha_egreso' => 'date',
        'autorizado_at' => 'datetime',
    ];

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function paisNacimiento()
    {
        return $this->belongsTo(Pais::class, 'pais_nacimiento_id');
    }

    public function pais()
    {
        return $this->belongsTo(Pais::class, 'pais_id');
    }

    public function provinciaDomicilio()
    {
        return $this->belongsTo(Provincia::class, 'provincia_id');
    }

    public function localidadDomicilio()
    {
        return $this->belongsTo(Localidad::class, 'localidad_id');
    }

    public function categoria()
    {
        return $this->belongsTo(Categoria_Sueldos::class, 'categoria_id');
    }

    public function agrupamiento()
    {
        return $this->belongsTo(Agrupamiento_Sueldos::class, 'agrupamiento_id');
    }

    public function lugartrabajo()
    {
        return $this->belongsTo(Lugartrabajo_Sueldos::class, 'lugartrabajo_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function obrasocial()
    {
        return $this->belongsTo(Obrasocial_Sueldos::class, 'obrasocial_id');
    }

    public function sindicato()
    {
        return $this->belongsTo(Sindicato_Sueldos::class, 'sindicato_id');
    }

    public function vacacion()
    {
        return $this->belongsTo(Vacacion_Sueldos::class, 'vacacion_id');
    }

    public function art()
    {
        return $this->belongsTo(Art_Sueldos::class, 'art_id');
    }

    public function motivoegreso()
    {
        return $this->belongsTo(Motivoegreso_Sueldos::class, 'motivoegreso_id');
    }

    public function usuarioAlta()
    {
        return $this->belongsTo(Usuario::class, 'usuario_alta_id');
    }

    public function usuarioAutoriza()
    {
        return $this->belongsTo(Usuario::class, 'usuario_autoriza_id');
    }

    public function bases()
    {
        return $this->hasMany(Empleado_Base_Sueldos::class, 'empleado_id');
    }

    public function leyendas()
    {
        return $this->hasMany(Empleado_Leyenda_Sueldos::class, 'empleado_id')->orderBy('linea');
    }

    public function ingresos()
    {
        return $this->hasMany(Empleado_Ingreso_Sueldos::class, 'empleado_id')
            ->orderByDesc('fecha_ingreso')->orderByDesc('id');
    }

    public function archivos()
    {
        return $this->hasMany(Empleado_Archivo_Sueldos::class, 'empleado_id');
    }

    public function ausencias()
    {
        return $this->hasMany(Empleado_Ausencia_Sueldos::class, 'empleado_id')
            ->orderByDesc('fecha_desde')->orderByDesc('id');
    }

    public function familiares()
    {
        return $this->hasMany(Empleado_Familiar_Sueldos::class, 'empleado_id')
            ->orderBy('tipo')->orderBy('apellido');
    }

    public function cuotaMovimientos()
    {
        return $this->hasMany(Empleado_Cuota_Movimiento_Sueldos::class, 'empleado_id')
            ->orderBy('anio_periodo')->orderBy('fecha');
    }

    public function estaProvisorio(): bool
    {
        return EmpleadoEstados::esProvisorio($this->estado);
    }

    public function estaActivo(): bool
    {
        return EmpleadoEstados::esActivo($this->estado);
    }

    public function estaDeBaja(): bool
    {
        return EmpleadoEstados::esBaja($this->estado);
    }
}
