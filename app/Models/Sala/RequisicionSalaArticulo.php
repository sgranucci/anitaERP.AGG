<?php

namespace App\Models\Sala;

use App\Models\Stock\Articulo;
use App\Models\Stock\Depmae;
use App\Traits\Sala\RequisicionSalaArticuloDestinoTrait;
use App\Traits\Sala\RequisicionSalaArticuloEstadoParcialTrait;
use App\Traits\Sala\RequisicionSalaArticuloEstadoTrait;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class RequisicionSalaArticulo extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use RequisicionSalaArticuloDestinoTrait;
    use RequisicionSalaArticuloEstadoParcialTrait;
    use RequisicionSalaArticuloEstadoTrait;

    protected $table = 'requisicion_sala_articulo';

    protected $fillable = [
        'requisicion_sala_id', 'articulo_id', 'cantidad', 'cantidadentregada', 'precio', 'detalle',
        'fueradeservicio', 'uid', 'destino', 'estado', 'estadoparcial', 'fecha_entrega',
        'numeroremito', 'nombreresponsable', 'tecnico_laboratorio_id', 'deposito_origen_id', 'numeroparte',
        'cantidadjuego', 'descripcionjuego', 'cantidadso', 'descripcionso',
        'cantidadmemoria', 'descripcionmemoria', 'cantidaddongle', 'descripciondongle',
        'cantidadsim', 'descripcionsim',
    ];

    public function requisicion_salas()
    {
        return $this->belongsTo(RequisicionSala::class, 'requisicion_sala_id');
    }

    public function articulos()
    {
        return $this->belongsTo(Articulo::class, 'articulo_id');
    }

    public function tecnico_laboratorio()
    {
        return $this->belongsTo(TecnicoLaboratorio::class, 'tecnico_laboratorio_id');
    }

    public function deposito_origen()
    {
        return $this->belongsTo(Depmae::class, 'deposito_origen_id');
    }

    public function descripcionArticulo(): string
    {
        $art = $this->articulos;
        $texto = trim((string) ($art?->descripcion ?? ''));
        if ($texto !== '') {
            return $texto;
        }
        $detalleLinea = trim((string) ($this->detalle ?? ''));
        if ($detalleLinea !== '') {
            return $detalleLinea;
        }

        return trim((string) ($art?->detalle ?? ''));
    }
}
