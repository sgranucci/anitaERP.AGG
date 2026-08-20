<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CotSesionEnvio extends Model
{
    protected $table = 'cot_sesion_envio';

    protected $fillable = [
        'fecha_facturas',
        'fecha_envio',
        'ambiente',
        'nombre_archivo',
        'numero_comprobante_arba',
        'cuit_empresa',
        'codigo_integridad',
        'ok',
        'error_general',
        'cantidad_remitos',
        'cantidad_ok',
        'cantidad_error',
        'repartos_json',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_facturas' => 'date',
        'fecha_envio' => 'datetime',
        'ok' => 'boolean',
        'repartos_json' => 'array',
        'cantidad_remitos' => 'integer',
        'cantidad_ok' => 'integer',
        'cantidad_error' => 'integer',
    ];

    public function remitos(): HasMany
    {
        return $this->hasMany(CotRemitoEnvio::class, 'cot_sesion_envio_id');
    }

    public function usuarios(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    /**
     * Reparto(s) de la sesión: código + nombre desde repartos_json.
     */
    public function etiquetaRepartos(): string
    {
        $repartos = $this->repartos_json;
        if (! is_array($repartos) || $repartos === []) {
            return '';
        }

        $etiquetas = [];
        foreach ($repartos as $reparto) {
            if (! is_array($reparto)) {
                continue;
            }

            $codigo = trim((string) ($reparto['codigo'] ?? ''));
            $nombre = trim((string) ($reparto['nombre'] ?? ''));
            if ($codigo !== '' && $nombre !== '') {
                $etiquetas[] = $codigo.' '.$nombre;
            } elseif ($codigo !== '') {
                $etiquetas[] = $codigo;
            } elseif ($nombre !== '') {
                $etiquetas[] = $nombre;
            }
        }

        return implode(', ', array_values(array_unique($etiquetas)));
    }
}
