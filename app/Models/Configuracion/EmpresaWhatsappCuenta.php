<?php

namespace App\Models\Configuracion;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use OwenIt\Auditing\Contracts\Auditable;

class EmpresaWhatsappCuenta extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $table = 'empresa_whatsapp_cuenta';

    protected $fillable = [
        'empresa_id', 'proveedor', 'nombre', 'phone_number_id', 'waba_id',
        'access_token', 'api_version', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public function empresas(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['access_token'] = null;

            return;
        }
        // Si ya viene cifrado (al re-guardar sin cambio), no re-cifrar.
        if (str_starts_with($value, 'eyJpdiI6')) {
            $this->attributes['access_token'] = $value;

            return;
        }
        $this->attributes['access_token'] = Crypt::encryptString($value);
    }

    public function accessTokenPlano(): ?string
    {
        $raw = $this->attributes['access_token'] ?? null;
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    public function estaOperativa(): bool
    {
        if (! $this->activo) {
            return false;
        }
        if (trim((string) $this->phone_number_id) === '') {
            return false;
        }

        return $this->accessTokenPlano() !== null && $this->accessTokenPlano() !== '';
    }
}
