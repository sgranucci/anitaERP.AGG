<?php

namespace App\Models\Configuracion;

use App\Models\Contable\Centrocosto;
use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuloAvisoDestinatario extends Model
{
    protected $table = 'modulo_aviso_destinatario';

    protected $fillable = [
        'modulo_aviso_tipo_id', 'email', 'usuario_id',
        'empresa_id', 'centrocosto_id', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(ModuloAvisoTipo::class, 'modulo_aviso_tipo_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function centrocostos(): BelongsTo
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    public function emailResuelto(): ?string
    {
        $emailUsuario = trim((string) optional($this->usuarios)->email);
        if ($emailUsuario !== '') {
            return $emailUsuario;
        }

        $email = trim((string) $this->email);

        return $email !== '' ? $email : null;
    }
}
