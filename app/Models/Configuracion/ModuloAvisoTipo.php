<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModuloAvisoTipo extends Model
{
    protected $table = 'modulo_aviso_tipo';

    protected $fillable = [
        'modulo', 'codigo', 'nombre', 'descripcion', 'activo',
        'mail_asunto', 'mail_texto', 'mail_remitente',
        'adjuntar_pdf', 'incluir_link_consulta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'adjuntar_pdf' => 'boolean',
        'incluir_link_consulta' => 'boolean',
    ];

    public function destinatarios(): HasMany
    {
        return $this->hasMany(ModuloAvisoDestinatario::class, 'modulo_aviso_tipo_id');
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
