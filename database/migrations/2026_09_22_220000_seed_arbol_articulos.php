<?php

use App\Models\Configuracion\Arbolaprobacion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Árboles tipo Artículos por empresa operativa (1 Biyemas, 2 Kandiko, 3 Rebisco).
 *
 * Gastronomía: Diego Dominguez (ddominguez) y después contabilidad.
 * Resto: directo a contabilidad.
 * Contabilidad: Ruben Fogliatti (rfogliatti) y Egoris Guevara (eguevara), mismo nivel
 * (avisa a los dos; firma uno y el otro queda sin efecto).
 *
 * No prende el flag articulo_aprobacion_alta.
 */
return new class extends Migration
{
    private const TIPO = 'Artículos';

    private const EMPRESAS = [1, 2, 3];

    private const USUARIO_GASTRO = 'ddominguez';

    private const USUARIOS_CONTABILIDAD = ['rfogliatti', 'eguevara'];

    public function up(): void
    {
        if (! Schema::hasTable('arbolaprobacion') || ! Schema::hasTable('arbolaprobacion_nivel')) {
            return;
        }

        $estado = (string) (Arbolaprobacion::$enumEstado[0]['nombre'] ?? 'Activo');
        $gastroId = $this->usuarioId(self::USUARIO_GASTRO);
        $contabilidadIds = [];
        foreach (self::USUARIOS_CONTABILIDAD as $usuario) {
            $id = $this->usuarioId($usuario);
            if ($id) {
                $contabilidadIds[] = $id;
            }
        }

        if (! $gastroId || count($contabilidadIds) < 2) {
            return;
        }

        foreach (self::EMPRESAS as $empresaId) {
            $nombreEmpresa = (string) (DB::table('empresa')->where('id', $empresaId)->value('nombre') ?? '');
            if ($nombreEmpresa === '') {
                continue;
            }

            $gastro = $this->asegurarArbol(
                'Artículos — Gastronomía — '.$nombreEmpresa,
                $empresaId,
                $estado
            );
            $this->asegurarNivel($gastro, 1, $gastroId);
            foreach ($contabilidadIds as $usuarioId) {
                $this->asegurarNivel($gastro, 2, $usuarioId);
            }

            $contabilidad = $this->asegurarArbol(
                'Artículos — Contaduría — '.$nombreEmpresa,
                $empresaId,
                $estado
            );
            foreach ($contabilidadIds as $usuarioId) {
                $this->asegurarNivel($contabilidad, 1, $usuarioId);
            }
        }

        if (Schema::hasTable('usoarticulo') && Schema::hasColumn('usoarticulo', 'aprobacion_modo')) {
            DB::table('usoarticulo')
                ->whereRaw('UPPER(nombre) = ?', ['GASTRONOMIA'])
                ->update([
                    'aprobacion_modo' => 'arbol',
                    'arbolaprobacion_id' => null,
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
            ->where(function ($q) {
                $q->where('nombre', 'like', 'Artículos — Gastronomía — %')
                    ->orWhere('nombre', 'like', 'Artículos — Contaduría — %');
            })
            ->pluck('id');

        if ($ids->isNotEmpty() && Schema::hasTable('arbolaprobacion_nivel')) {
            DB::table('arbolaprobacion_nivel')->whereIn('arbolaprobacion_id', $ids)->delete();
        }

        if ($ids->isNotEmpty()) {
            Arbolaprobacion::query()->whereIn('id', $ids)->delete();
        }

        if (Schema::hasTable('usoarticulo') && Schema::hasColumn('usoarticulo', 'aprobacion_modo')) {
            DB::table('usoarticulo')
                ->whereRaw('UPPER(nombre) = ?', ['GASTRONOMIA'])
                ->update([
                    'aprobacion_modo' => 'default',
                    'arbolaprobacion_id' => null,
                ]);
        }
    }

    private function usuarioId(string $usuario): ?int
    {
        $id = DB::table('usuario')->where('usuario', $usuario)->value('id');

        return $id ? (int) $id : null;
    }

    private function asegurarArbol(string $nombre, int $empresaId, string $estado): int
    {
        $existente = Arbolaprobacion::query()
            ->where('tipoarbol', self::TIPO)
            ->where('empresa_id', $empresaId)
            ->where('nombre', $nombre)
            ->value('id');

        if ($existente) {
            return (int) $existente;
        }

        $arbol = Arbolaprobacion::query()->create([
            'nombre' => $nombre,
            'tipoarbol' => self::TIPO,
            'empresa_id' => $empresaId,
            'recordatorio' => 'N',
            'diasinrespuesta' => 0,
            'diavencimientorecordatorio' => 0,
            'estado' => $estado,
        ]);

        return (int) $arbol->id;
    }

    private function asegurarNivel(int $arbolId, int $nivel, int $usuarioId): void
    {
        $ya = DB::table('arbolaprobacion_nivel')
            ->where('arbolaprobacion_id', $arbolId)
            ->where('nivel', $nivel)
            ->where('usuario_id', $usuarioId)
            ->exists();

        if ($ya) {
            return;
        }

        DB::table('arbolaprobacion_nivel')->insert([
            'arbolaprobacion_id' => $arbolId,
            'centrocosto_id' => null,
            'nivel' => $nivel,
            'usuario_id' => $usuarioId,
            'usuario_orig_id' => null,
            'desdemonto' => 0,
            'hastamonto' => 0,
            'moneda_id' => 1,
            'documento_estado_al_aprobar' => null,
            'doble_aprobacion' => 'N',
            'rama' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
