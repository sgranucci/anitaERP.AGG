<?php

use App\Models\Configuracion\Arbolaprobacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crea el árbol tipo Suscripciones por empresa operativa.
 *
 * Los niveles (un nivel por centro de costo apuntando al gerente del sector) se
 * configuran desde Compras › Suscripciones › Aprobadores, no acá: la migración
 * solo deja el contenedor listo para que el módulo tenga a dónde disparar.
 */
return new class extends Migration
{
    private const TIPO = 'Suscripciones';

    /** Empresas operativas del grupo. */
    private const EMPRESAS = [1, 2, 3];

    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        $estado = $this->estadoActivoDeArbolesExistentes();

        foreach (self::EMPRESAS as $empresaId) {
            if (! DB::table('empresa')->where('id', $empresaId)->exists()) {
                continue;
            }

            $yaExiste = Arbolaprobacion::query()
                ->where('tipoarbol', self::TIPO)
                ->where('empresa_id', $empresaId)
                ->exists();
            if ($yaExiste) {
                continue;
            }

            $nombreEmpresa = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');

            Arbolaprobacion::query()->create([
                'nombre' => trim('Suscripciones — '.$nombreEmpresa),
                'tipoarbol' => self::TIPO,
                'empresa_id' => $empresaId,
                'recordatorio' => 'N',
                'diasinrespuesta' => 0,
                'diavencimientorecordatorio' => 0,
                'estado' => $estado,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('arbolaprobacion')) {
            return;
        }

        $ids = Arbolaprobacion::query()
            ->where('tipoarbol', self::TIPO)
            ->whereIn('empresa_id', self::EMPRESAS)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        if (Schema::hasTable('arbolaprobacion_nivel')) {
            DB::table('arbolaprobacion_nivel')->whereIn('arbolaprobacion_id', $ids)->delete();
        }

        Arbolaprobacion::query()->whereIn('id', $ids)->delete();
    }

    /**
     * Reusa el literal de estado que ya usan los árboles vigentes (evita adivinar el enum).
     */
    private function estadoActivoDeArbolesExistentes(): string
    {
        $estado = DB::table('arbolaprobacion')
            ->where('tipoarbol', 'Ordenes de compra')
            ->value('estado');

        if (! $estado) {
            $estado = DB::table('arbolaprobacion')->value('estado');
        }

        return (string) ($estado ?: 'Activo');
    }
};
