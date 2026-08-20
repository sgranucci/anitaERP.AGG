<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Model;

class CotRemitoEnvio extends Model
{
    protected $table = 'cot_remito_envio';

    protected $fillable = [
        'cot_sesion_envio_id',
        'tipo',
        'letra',
        'sucursal',
        'numero_remito',
        'fecha_remito',
        'venta_id',
        'transporte_id',
        'cliente_id',
        'cliente_nombre',
        'procesado',
        'nro_unico',
        'cot',
        'numero_comprobante_arba',
        'nombre_archivo',
        'error',
        'usuario_id',
    ];

    protected $casts = [
        'fecha_remito' => 'date',
        'numero_remito' => 'integer',
        'sucursal' => 'integer',
    ];

    public function cotSesionEnvio()
    {
        return $this->belongsTo(CotSesionEnvio::class, 'cot_sesion_envio_id');
    }

    public function ventas()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function transportes()
    {
        return $this->belongsTo(Transporte::class, 'transporte_id');
    }

    public function clientes()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function usuarios()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }

    public function fueEmitido(): bool
    {
        $procesado = strtoupper(trim((string) $this->procesado));
        $cot = trim((string) $this->cot);

        return $procesado === 'SI' || $cot !== '';
    }

    /**
     * Identidad persistida del remito para el COT: tipo + letra + número.
     * La sucursal no entra (factura Anita = 1, remito físico = 99).
     */
    public function claveLogica(): string
    {
        return self::armarClaveLogica($this->tipo, $this->letra, (int) $this->numero_remito);
    }

    public static function armarClaveLogica(?string $tipo, ?string $letra, int $numero): string
    {
        if ($numero <= 0) {
            return '';
        }

        $tipoNorm = trim((string) $tipo) ?: 'REM';
        $letraNorm = trim((string) $letra) ?: 'R';

        return implode('|', [$tipoNorm, $letraNorm, $numero]);
    }

    /**
     * Último COT exitoso por identidad de remito.
     *
     * @param  list<int>  $numeros
     * @return \Illuminate\Support\Collection<string, self>
     */
    public static function ultimosExitososPorClave(array $numeros)
    {
        $numeros = array_values(array_unique(array_filter(
            array_map('intval', $numeros),
            static fn (int $n) => $n > 0
        )));

        if ($numeros === []) {
            return collect();
        }

        return static::query()
            ->whereIn('numero_remito', $numeros)
            ->orderByDesc('id')
            ->get()
            ->filter(static fn (self $envio) => $envio->fueEmitido())
            ->unique(static fn (self $envio) => $envio->claveLogica())
            ->keyBy(static fn (self $envio) => $envio->claveLogica());
    }
}
