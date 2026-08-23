<?php

namespace App\Models\Caja;

use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Cuentacontable;
use App\Traits\Caja\CuentacajaTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class Cuentacaja extends Model implements Auditable
{
    use CuentacajaTrait;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = ['nombre', 'descripcion_operaciones', 'orden', 'codigo', 'tipocuenta', 'banco_id',
        'empresa_id', 'cuentacontable_id', 'moneda_id', 'cbu', 'cuenta_interbanking'];

    protected $table = 'cuentacaja';

    protected $casts = [
        'orden' => 'integer',
    ];

    protected $attributes = [
        'orden' => 0,
    ];

    /**
     * Etiqueta corta para pantallas operativas (rendición máquinas, etc.).
     * Usa descripcion_operaciones si está cargada; si no, el nombre maestro.
     */
    public function etiquetaOperaciones(): string
    {
        $desc = trim((string) ($this->descripcion_operaciones ?? ''));

        return $desc !== '' ? $desc : trim((string) ($this->nombre ?? ''));
    }

    public function bancos()
    {
        return $this->belongsTo(Banco::class, 'banco_id');
    }

    public function monedas()
    {
        return $this->belongsTo(Moneda::class, 'moneda_id');
    }

    public function empresas()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function cuentacontables()
    {
        return $this->belongsTo(Cuentacontable::class, 'cuentacontable_id');
    }

    public function usocuentacajas()
    {
        return $this->belongsToMany(Usocuentacaja::class, 'cuentacaja_usocuentacaja', 'cuentacaja_id', 'usocuentacaja_id')
            ->withPivot('orden');
    }

    /**
     * Cuentas propias de la empresa o multiempresa (empresa_id null usable desde cualquier empresa).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<self>  $query
     * @return \Illuminate\Database\Eloquent\Builder<self>
     */
    public function scopeParaEmpresa($query, int $empresaId)
    {
        if ($empresaId <= 0) {
            return $query;
        }

        return $query->where(function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId)->orWhereNull('empresa_id');
        });
    }

    public static function existeParaEmpresa(int $cuentacajaId, int $empresaId): bool
    {
        if ($cuentacajaId <= 0) {
            return false;
        }

        $query = static::query()->whereKey($cuentacajaId);

        return $empresaId > 0
            ? $query->paraEmpresa($empresaId)->exists()
            : $query->exists();
    }

    public function perteneceAEmpresa(int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return true;
        }

        return $this->empresa_id === null || (int) $this->empresa_id === $empresaId;
    }
}
