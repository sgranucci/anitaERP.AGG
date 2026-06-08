<?php

namespace App\Models\Ventas;

use App\Models\Configuracion\Empresa;
use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable;

class TotemWaitryGastronomia extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'empresa_id',
        'ubicacion_id',
        'waitry_layout_id',
        'waitry_layout_ids_adicionales',
        'waitry_table_id',
        'waitry_table_ids_adicionales',
        'detalle',
        'informe_z_habilitado',
    ];

    protected $casts = [
        'waitry_layout_id' => 'integer',
        'waitry_table_id' => 'integer',
        'informe_z_habilitado' => 'boolean',
    ];

    protected $table = 'totem_waitry_gastronomia';

    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ubicacion()
    {
        return $this->belongsTo(UbicacionGastronomia::class, 'ubicacion_id');
    }

    /**
     * Punto de acceso Waitry ({@code table.layout.id}) — prioridad al mapear comandas al tótem.
     */
    public function waitryLayoutId(): int
    {
        return max(0, (int) ($this->waitry_layout_id ?? 0));
    }

    /**
     * IDs de layout Waitry (punto de acceso) que agrupan comandas de este tótem físico.
     *
     * @return list<int>
     */
    public function waitryLayoutIds(): array
    {
        $ids = [];
        $principal = $this->waitryLayoutId();
        if ($principal > 0) {
            $ids[] = $principal;
        }

        $raw = trim((string) ($this->waitry_layout_ids_adicionales ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $parte) {
                $id = (int) $parte;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    public function tieneConfiguracionMatchWaitry(): bool
    {
        return $this->waitryLayoutIds() !== [] || $this->waitryTableIds() !== [];
    }

    /**
     * IDs de mesa Waitry (tableId) que agrupan comandas de este tótem físico.
     *
     * @return list<int>
     */
    public function waitryTableIds(): array
    {
        $ids = [];
        $principal = (int) ($this->waitry_table_id ?? 0);
        if ($principal > 0) {
            $ids[] = $principal;
        }

        $raw = trim((string) ($this->waitry_table_ids_adicionales ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $parte) {
                $id = (int) $parte;
                if ($id > 0) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Si participa en plantilla / conciliación Informe Z (Posnet kiosco Waitry).
     */
    public function participaInformeZ(): bool
    {
        return (bool) ($this->informe_z_habilitado ?? true);
    }
}
