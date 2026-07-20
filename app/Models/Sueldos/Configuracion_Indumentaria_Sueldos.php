<?php

namespace App\Models\Sueldos;

use App\Models\Contable\Centrocosto;
use App\Models\Stock\Depmae;
use App\Models\Stock\Tipotransaccion_Stock;
use Illuminate\Database\Eloquent\Model;

class Configuracion_Indumentaria_Sueldos extends Model
{
    protected $table = 'configuracion_indumentaria_sueldos';

    protected $fillable = [
        'deposito_id',
        'tipotransaccion_stock_id',
        'centrocosto_id',
    ];

    protected $casts = [
        'deposito_id' => 'integer',
        'tipotransaccion_stock_id' => 'integer',
        'centrocosto_id' => 'integer',
    ];

    public function deposito()
    {
        return $this->belongsTo(Depmae::class, 'deposito_id');
    }

    public function tipotransaccion()
    {
        return $this->belongsTo(Tipotransaccion_Stock::class, 'tipotransaccion_stock_id');
    }

    public function centrocosto()
    {
        return $this->belongsTo(Centrocosto::class, 'centrocosto_id');
    }

    /** Fila única de configuración (se crea vacía si no existe). */
    public static function actual(): self
    {
        return static::query()->first() ?? static::create([]);
    }

    public function estaCompleta(): bool
    {
        return (int) $this->deposito_id > 0 && (int) $this->tipotransaccion_stock_id > 0;
    }
}
