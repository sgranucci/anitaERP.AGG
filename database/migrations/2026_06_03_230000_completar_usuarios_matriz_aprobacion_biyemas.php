<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Completa datos de usuarios creados para la matriz de aprobación Abril 2026.
     * Nombres tomados del histórico de requisiciones Anita (Creo Usuario) y tickets internos.
     */
    public function up(): void
    {
        $empresaBiyemas = (int) DB::table('empresa')
            ->where('nombre', 'like', 'BIYEMAS%')
            ->value('id');

        $ccSeguridad = (int) DB::table('centrocosto')->where('codigo', '86')->value('id');
        $ccSlots = (int) DB::table('centrocosto')->where('codigo', '102')->value('id');

        $actualizaciones = [
            'stoscano' => [
                'usuario' => 'ftoscano',
                'nombre' => 'TOSCANO FERNANDO',
                'email' => 'ftoscano@grupoagg.com',
                'centrocosto_id' => null,
                'roles' => [],
            ],
            'acastellani' => [
                'nombre' => 'CASTELLANI PAOLA',
                'email' => 'acastellani@grupoagg.com',
                'centrocosto_id' => $ccSeguridad ?: null,
                'roles' => ['enc-SEGURIDAD'],
            ],
            'aacuna' => [
                'nombre' => 'ACUNIA MAXIMILIANO',
                'email' => 'aacuna@grupoagg.com',
                'centrocosto_id' => $ccSeguridad ?: null,
                'roles' => ['enc-SEGURIDAD'],
            ],
            'mrodriguez' => [
                'nombre' => 'RODRIGUEZ MELISA',
                'email' => 'mrodriguez@grupoagg.com',
                'centrocosto_id' => $ccSlots ?: null,
                'roles' => ['enc-Analisis de Slots'],
            ],
        ];

        foreach ($actualizaciones as $loginActual => $datos) {
            $usuario = DB::table('usuario')->where('usuario', $loginActual)->first();
            if (! $usuario) {
                continue;
            }

            $roles = $datos['roles'] ?? [];
            $nuevoLogin = $datos['usuario'] ?? $loginActual;
            unset($datos['usuario'], $datos['roles']);

            DB::table('usuario')->where('id', $usuario->id)->update(array_merge($datos, [
                'usuario' => $nuevoLogin,
                'updated_at' => now(),
            ]));

            if ($empresaBiyemas > 0) {
                $yaAsignado = DB::table('usuario_empresa')
                    ->where('usuario_id', $usuario->id)
                    ->where('empresa_id', $empresaBiyemas)
                    ->exists();

                if (! $yaAsignado) {
                    DB::table('usuario_empresa')->insert([
                        'usuario_id' => $usuario->id,
                        'empresa_id' => $empresaBiyemas,
                    ]);
                }
            }

            foreach ($roles as $nombreRol) {
                $rolId = DB::table('rol')->where('nombre', $nombreRol)->value('id');
                if (! $rolId) {
                    continue;
                }

                $yaRol = DB::table('usuario_rol')
                    ->where('usuario_id', $usuario->id)
                    ->where('rol_id', $rolId)
                    ->exists();

                if (! $yaRol) {
                    DB::table('usuario_rol')->insert([
                        'usuario_id' => $usuario->id,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Reversión manual si hiciera falta.
    }
};
