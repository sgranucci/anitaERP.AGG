<?php

namespace App\Models\Ventas;

use App\Models\Seguridad\Usuario;
use App\Support\Ventas\CotFacturaEmitidaSupport;
use App\Support\Ventas\CotGuiaFacturaIdentidadSupport;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use OwenIt\Auditing\Contracts\Auditable;

class CotRemitoEnvio extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    protected $table = 'cot_remito_envio';

    protected $fillable = [
        'cot_sesion_envio_id',
        'tipo',
        'letra',
        'sucursal',
        'numero_remito',
        'fecha_remito',
        'venta_id',
        'factura_tipo',
        'factura_letra',
        'factura_sucursal',
        'factura_numero',
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
        'factura_sucursal' => 'integer',
        'factura_numero' => 'integer',
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
     * @return Collection<string, self>
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

    /**
     * @return array{tipo: string, letra: string, sucursal: int, numero: int}|null
     */
    public function identidadFactura(): ?array
    {
        $numero = (int) ($this->factura_numero ?? 0);
        if ($numero > 0) {
            $norm = CotGuiaFacturaIdentidadSupport::normalizar(
                (string) ($this->factura_tipo ?? ''),
                (string) ($this->factura_letra ?? ''),
                (int) ($this->factura_sucursal ?? 0),
                $numero,
            );
            if (CotFacturaEmitidaSupport::clave($norm) !== '') {
                return $norm;
            }
        }

        $venta = $this->relationLoaded('ventas') ? $this->ventas : null;
        if ($venta === null && (int) ($this->venta_id ?? 0) > 0) {
            $venta = $this->ventas()->with(['tipotransacciones', 'puntoventas'])->first();
        }
        if ($venta === null) {
            return null;
        }

        return CotGuiaFacturaIdentidadSupport::desdeVenta($venta);
    }

    public function claveFactura(): string
    {
        return CotFacturaEmitidaSupport::clave($this->identidadFactura());
    }

    /**
     * Último COT exitoso por venta_id.
     *
     * @param  list<int>  $ventaIds
     * @return Collection<int, self>
     */
    public static function ultimosExitososPorVentaId(array $ventaIds): Collection
    {
        $ventaIds = array_values(array_unique(array_filter(
            array_map('intval', $ventaIds),
            static fn (int $id) => $id > 0
        )));

        if ($ventaIds === []) {
            return collect();
        }

        return static::query()
            ->whereIn('venta_id', $ventaIds)
            ->orderByDesc('id')
            ->get()
            ->filter(static fn (self $envio) => $envio->fueEmitido())
            ->unique(static fn (self $envio) => (int) $envio->venta_id)
            ->keyBy(static fn (self $envio) => (int) $envio->venta_id);
    }

    /**
     * Último COT exitoso por identidad de factura (columnas o venta vinculada).
     *
     * @param  list<string>  $clavesFactura  tipo|letra|sucursal|numero
     * @return Collection<string, self>
     */
    public static function ultimosExitososPorClaveFactura(array $clavesFactura): Collection
    {
        $clavesFactura = array_values(array_unique(array_filter(
            array_map('strval', $clavesFactura),
            static fn (string $c) => $c !== ''
        )));

        if ($clavesFactura === []) {
            return collect();
        }

        $partes = [];
        foreach ($clavesFactura as $clave) {
            $bits = explode('|', $clave);
            if (count($bits) !== 4) {
                continue;
            }
            $partes[] = [
                'tipo' => $bits[0],
                'letra' => $bits[1],
                'sucursal' => (int) $bits[2],
                'numero' => (int) $bits[3],
            ];
        }

        if ($partes === []) {
            return collect();
        }

        $numeros = array_values(array_unique(array_map(
            static fn (array $p) => $p['numero'],
            $partes
        )));

        $candidatos = static::query()
            ->where(function ($q) use ($numeros) {
                $q->whereIn('factura_numero', $numeros)
                    ->orWhereHas('ventas', function ($vq) use ($numeros) {
                        $vq->whereIn('numerocomprobante', $numeros);
                    });
            })
            ->with(['ventas.tipotransacciones', 'ventas.puntoventas'])
            ->orderByDesc('id')
            ->get()
            ->filter(static fn (self $envio) => $envio->fueEmitido());

        $porClave = collect();
        $buscadas = array_fill_keys($clavesFactura, true);
        foreach ($candidatos as $envio) {
            $clave = $envio->claveFactura();
            if ($clave === '' || ! isset($buscadas[$clave]) || $porClave->has($clave)) {
                continue;
            }
            $porClave->put($clave, $envio);
        }

        return $porClave;
    }
}
