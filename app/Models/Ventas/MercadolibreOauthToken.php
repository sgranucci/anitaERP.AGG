<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;

class MercadolibreOauthToken extends Model
{
    protected $table = 'mercadolibre_oauth_token';

    protected $fillable = [
        'seller_id',
        'access_token',
        'refresh_token',
        'token_type',
        'expires_in',
        'expires_at',
        'scope',
        'payload_json',
    ];

    protected $casts = [
        'expires_in' => 'integer',
        'expires_at' => 'datetime',
        'payload_json' => 'array',
    ];

    public function estaVigente(int $margenSegundos = 120): bool
    {
        $token = trim((string) $this->access_token);
        if ($token === '') {
            return false;
        }
        if ($this->expires_at === null) {
            return true;
        }

        return $this->expires_at->greaterThan(now()->addSeconds($margenSegundos));
    }
}
