<?php

namespace App\Models\Ventas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Arr;
use App\Traits\Ventas\TipotransaccionTrait;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tipotransaccion extends Model
{
    use SoftDeletes;
	use TipotransaccionTrait;

    protected $fillable = ['nombre', 'operacion', 'operacionstock', 'abreviatura', 'codigo', 'signo', 'estado', 'iva_ventas', 'concepto_venta_id'];
    protected $table = 'tipotransaccion';

    protected $casts = [
        'iva_ventas' => 'boolean',
    ];

    public function setSignoAttribute($signo)
    {
        switch(TipotransaccionTrait::$enumSigno[$signo])
        {
        case 'Suma':
            $this->attributes['signo'] = 1;
            break;
        case 'Resta':
            $this->attributes['signo'] = -1;
            break;
        }
    }

    public function getSignoAttribute($signo)
    {
        switch($signo)
        {
        case 1:
            $retSigno = 'S';
            break;
        case -1:
            $retSigno = 'R';
            break;
        }
        return $retSigno;
    }

    public function getDescOperacionstockAttribute()
    {
        return Arr::get(TipotransaccionTrait::$enumOperacionStock, $this->operacionstock);
    }

    public function conceptoVenta()
    {
        return $this->belongsTo(Concepto_Venta::class, 'concepto_venta_id');
    }

    public function esNotaCredito(): bool
    {
        return ($this->operacion ?? '') === 'C';
    }

    public function esNotaDebito(): bool
    {
        $abrev = strtoupper(trim((string) ($this->abreviatura ?? '')));
        if (preg_match('/^ND/', $abrev) === 1) {
            return true;
        }

        $codigo = (int) preg_replace('/\D+/', '', (string) ($this->codigo ?? ''));

        return in_array($codigo, [2, 7, 12, 52, 202, 207], true);
    }

    /**
     * El facturador muestra el concepto de cabecera si el tipo tiene uno asignado
     * (NC/ND de texto, o una FAC/FAU/etc. con default).
     */
    public function usaConceptoVentaEnFacturador(): bool
    {
        return (int) ($this->concepto_venta_id ?? 0) > 0;
    }

    public function vaAlIvaVentas(): bool
    {
        return \App\Support\Ventas\TipotransaccionIvaVentasSupport::vaAlIvaVentas($this);
    }

    /**
     * Remito ERP / Anita solo con factura de mercadería (FAC / FCE).
     * NC y ND no generan ni heredan remito.
     */
    public function correspondeRemito(): bool
    {
        if ($this->esNotaCredito()) {
            return false;
        }

        $abrev = strtoupper(trim((string) ($this->abreviatura ?? '')));

        return in_array($abrev, ['FAC', 'FCE'], true);
    }
}

