<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OwenIt\Auditing\Contracts\Auditable;

class ModuloAvisoTipo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'modulo_aviso_tipo';

    protected $fillable = [
        'modulo', 'codigo', 'nombre', 'descripcion', 'activo',
        'mail_asunto', 'mail_texto', 'mail_remitente',
        'adjuntar_pdf', 'incluir_link_consulta',
        'programacion_habilitada', 'programacion_hora', 'programacion_hora_hasta', 'programacion_dias', 'programacion_ultima_en',
        'programacion_cada_n', 'programacion_hasta', 'programacion_ancla',
        'programacion_dia_mes', 'programacion_skip_feriados', 'programacion_pausada',
        'programacion_reintentos_max', 'programacion_depende_de', 'programacion_depende_modo',
        'parametro_dias', 'sla_horas', 'notificar_in_app', 'notificar_whatsapp', 'condiciones', 'disparador',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'adjuntar_pdf' => 'boolean',
        'incluir_link_consulta' => 'boolean',
        'programacion_habilitada' => 'boolean',
        'programacion_ultima_en' => 'date',
        'programacion_hasta' => 'date',
        'programacion_ancla' => 'date',
        'programacion_cada_n' => 'integer',
        'programacion_dia_mes' => 'integer',
        'programacion_skip_feriados' => 'boolean',
        'programacion_pausada' => 'boolean',
        'programacion_reintentos_max' => 'integer',
        'parametro_dias' => 'integer',
        'sla_horas' => 'integer',
        'notificar_in_app' => 'boolean',
        'notificar_whatsapp' => 'boolean',
        'condiciones' => 'array',
    ];

    public function condicion(string $clave, mixed $default = null): mixed
    {
        $cond = $this->condiciones;
        if (! is_array($cond) || ! array_key_exists($clave, $cond)) {
            return $default;
        }

        return $cond[$clave];
    }

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ModuloAvisoDestinatario::class, 'modulo_aviso_tipo_id');
    }

    public function programadoLogs(): HasMany
    {
        return $this->hasMany(ModuloAvisoProgramadoLog::class, 'modulo_aviso_tipo_id');
    }

    public function claveCompleta(): string
    {
        return $this->modulo.'.'.$this->codigo;
    }

    public static function porModuloYCodigo(string $modulo, string $codigo): ?self
    {
        return self::query()
            ->where('modulo', $modulo)
            ->where('codigo', $codigo)
            ->first();
    }
}
