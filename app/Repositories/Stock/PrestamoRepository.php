<?php

namespace App\Repositories\Stock;

use App\Models\Stock\Configuracion_Prestamo;
use App\Models\Stock\Prestamo;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PrestamoRepository implements PrestamoRepositoryInterface
{
    public function all()
    {
        return Prestamo::query()
            ->with([
                'depositoOrigen:id,nombre',
                'depositoDestino:id,nombre',
                'solicitante:id,nombre,email',
                'aprobador:id,nombre,email',
                'items',
            ])
            ->orderByDesc('id')
            ->get();
    }

    public function find(int $id)
    {
        $prestamo = Prestamo::find($id);
        if (! $prestamo) {
            throw new ModelNotFoundException('Préstamo no encontrado');
        }

        return $prestamo;
    }

    public function findConRelaciones(int $id)
    {
        $prestamo = Prestamo::query()
            ->with([
                'depositoOrigen',
                'depositoDestino',
                'solicitante:id,nombre,email',
                'aprobador:id,nombre,email',
                'items.articulos:id,sku,descripcion',
                'estados.usuarios:id,nombre',
                'tokens',
                'movimientoSalida',
                'movimientoIngreso',
            ])
            ->find($id);

        if (! $prestamo) {
            throw new ModelNotFoundException('Préstamo no encontrado');
        }

        return $prestamo;
    }

    public function create(array $data)
    {
        return Prestamo::create($data);
    }

    public function update(int $id, array $data)
    {
        $prestamo = $this->find($id);
        $prestamo->fill($data)->save();

        return $prestamo;
    }

    public function delete(int $id): bool
    {
        $prestamo = Prestamo::find($id);
        if (! $prestamo) {
            return false;
        }

        return (bool) $prestamo->delete();
    }

    public function pendientesParaRecordar()
    {
        $config = Configuracion_Prestamo::vigente();
        $diasAviso = (int) ($config->dias_antes_devolucion_aviso ?? 2);
        $diasRepeticion = (int) ($config->dias_repeticion_vencido ?? 3);

        $hoy = now()->startOfDay();
        $limiteAviso = (clone $hoy)->addDays($diasAviso);

        return Prestamo::query()
            ->with([
                'depositoOrigen:id,nombre',
                'depositoDestino:id,nombre',
                'solicitante:id,nombre,email',
                'aprobador:id,nombre,email',
                'items.articulos:id,sku,descripcion',
            ])
            ->whereIn('estado', [
                Prestamo::ESTADO_APROBADO,
                Prestamo::ESTADO_DEVUELTO_PARCIAL,
            ])
            ->where('fecha_devolucion_prometida', '<=', $limiteAviso)
            ->where(function ($q) use ($hoy, $diasRepeticion) {
                $q->whereNull('ultimo_recordatorio_enviado_el')
                    ->orWhere('ultimo_recordatorio_enviado_el', '<=', (clone $hoy)->subDays($diasRepeticion));
            })
            ->orderBy('fecha_devolucion_prometida')
            ->get();
    }
}
