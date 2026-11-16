<?php

use App\Support\ConsultaInteligente\ConsultaInteligenteNlDiccionarioSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menú Configuración por módulo → Dirección (alertas + diccionario NL)
 * y seeds iniciales del diccionario NL editable.
 */
return new class extends Migration
{
    private const GRUPO_URL = '#configuracion-por-modulo';

    private const MODULO_URL = '#config-modulo-direccion';

    private const URL_ALERTAS = 'direccion/consulta-inteligente/configuracion';

    private const URL_DICCIONARIO = 'direccion/consulta-inteligente/diccionario';

    private const PERMISO_CONFIG = 'configurar-consulta-inteligente';

    /** @var list<array{frase: string, entidad: string, prioridad: int}> */
    private const SEEDS_NL = [
        ['frase' => 'valores en cartera', 'entidad' => 'cheque', 'prioridad' => 10],
        ['frase' => 'cheques a depositar', 'entidad' => 'cheque', 'prioridad' => 10],
        ['frase' => 'fc proveedor', 'entidad' => 'comprobante_proveedor', 'prioridad' => 20],
        ['frase' => 'ops del mes', 'entidad' => 'pagoproveedor', 'prioridad' => 20],
        ['frase' => 'carpetas de importacion', 'entidad' => 'importacion', 'prioridad' => 20],
        ['frase' => 'solicitudes de pago', 'entidad' => 'solicitudpago', 'prioridad' => 20],
        ['frase' => 'legajos activos', 'entidad' => 'empleado_sueldos', 'prioridad' => 20],
        ['frase' => 'mesa de ayuda', 'entidad' => 'ticket', 'prioridad' => 20],
        ['frase' => 'mayor analitico', 'entidad' => 'mayor_plano_cuenta', 'prioridad' => 15],
    ];

    public function up(): void
    {
        $moduloId = $this->asegurarModuloDireccion();
        if ($moduloId > 0) {
            $menuAlertas = $this->upsertMenu(self::URL_ALERTAS, 'Alertas Consulta Inteligente', 'fa-bell', $moduloId, 10);
            $menuDic = $this->upsertMenu(self::URL_DICCIONARIO, 'Diccionario NL Consulta Inteligente', 'fa-book', $moduloId, 20);

            $permisoId = (int) (DB::table('permiso')->where('slug', self::PERMISO_CONFIG)->value('id') ?? 0);
            $rolIds = $permisoId > 0
                ? DB::table('permiso_rol')->where('permiso_id', $permisoId)->pluck('rol_id')->map(fn ($id) => (int) $id)->all()
                : [];

            foreach ([$menuAlertas, $menuDic, $moduloId] as $menuId) {
                foreach ($rolIds as $rolId) {
                    if ($rolId < 1) {
                        continue;
                    }
                    if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                        DB::table('menu_rol')->insert(['menu_id' => $menuId, 'rol_id' => $rolId]);
                    }
                }
                // Ancestros
                $padre = (int) (DB::table('menu')->where('id', $menuId)->value('menu_id') ?? 0);
                while ($padre > 0) {
                    foreach ($rolIds as $rolId) {
                        if ($rolId < 1) {
                            continue;
                        }
                        if (! DB::table('menu_rol')->where('menu_id', $padre)->where('rol_id', $rolId)->exists()) {
                            DB::table('menu_rol')->insert(['menu_id' => $padre, 'rol_id' => $rolId]);
                        }
                    }
                    $padre = (int) (DB::table('menu')->where('id', $padre)->value('menu_id') ?? 0);
                }
            }
        }

        if (Schema::hasTable('consulta_inteligente_nl_sinonimo')) {
            foreach (self::SEEDS_NL as $seed) {
                $exists = DB::table('consulta_inteligente_nl_sinonimo')
                    ->where('frase', $seed['frase'])
                    ->where('entidad', $seed['entidad'])
                    ->exists();
                if ($exists) {
                    continue;
                }
                DB::table('consulta_inteligente_nl_sinonimo')->insert([
                    'frase' => $seed['frase'],
                    'entidad' => $seed['entidad'],
                    'prioridad' => $seed['prioridad'],
                    'activo' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            ConsultaInteligenteNlDiccionarioSupport::olvidarCache();
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        foreach ([self::URL_ALERTAS, self::URL_DICCIONARIO] as $url) {
            $ids = DB::table('menu')->where('url', $url)->pluck('id');
            if ($ids->isNotEmpty()) {
                DB::table('menu_rol')->whereIn('menu_id', $ids)->delete();
                DB::table('menu')->whereIn('id', $ids)->delete();
            }
        }
        // No borra seeds NL ni el nodo #config-modulo-direccion (puede tener otros ítems).
        SuitecrmPermiso::flushCachePermisos();
    }

    private function asegurarModuloDireccion(): int
    {
        $id = (int) (DB::table('menu')->where('url', self::MODULO_URL)->value('id') ?? 0);
        if ($id > 0) {
            return $id;
        }

        $grupoId = (int) (DB::table('menu')->where('url', self::GRUPO_URL)->value('id') ?? 0);
        if ($grupoId === 0) {
            return 0;
        }

        return (int) DB::table('menu')->insertGetId([
            'nombre' => 'Dirección',
            'url' => self::MODULO_URL,
            'menu_id' => $grupoId,
            'orden' => (int) (DB::table('menu')->where('menu_id', $grupoId)->max('orden') ?? 0) + 1,
            'icono' => 'fa-compass',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function upsertMenu(string $url, string $nombre, string $icono, int $padreId, int $orden): int
    {
        $id = (int) (DB::table('menu')->where('url', $url)->value('id') ?? 0);
        $payload = [
            'nombre' => $nombre,
            'url' => $url,
            'menu_id' => $padreId,
            'orden' => $orden,
            'icono' => $icono,
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('menu')->where('id', $id)->update($payload);

            return $id;
        }

        return (int) DB::table('menu')->insertGetId(array_merge($payload, ['created_at' => now()]));
    }
};
