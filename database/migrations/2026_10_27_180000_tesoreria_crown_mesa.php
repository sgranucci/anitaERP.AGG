<?php

use App\ApiAnita;
use App\Models\Admin\Menu;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\SuitecrmPermiso;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mesas de juego: banca vigente por fecha y movimientos de fichas y drop.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'listar-tesoreria-crown-mesa' => 'Listar mesas de tesorería Crown',
        'crear-tesoreria-crown-mesa' => 'Crear movimientos de mesa de tesorería Crown',
        'editar-tesoreria-crown-mesa' => 'Confirmar y anular movimientos de mesa de tesorería Crown',
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('tesoreria_crown_banca')) {
            Schema::create('tesoreria_crown_banca', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->unsignedInteger('codigo');
                $table->string('nombre', 40);
                $table->date('vigencia_desde');
                $table->timestamps();
                $table->unique(['sala_id', 'codigo', 'vigencia_desde'], 'tc_banca_vigencia_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_banca_linea')) {
            Schema::create('tesoreria_crown_banca_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('banca_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2);
                $table->timestamps();
                $table->unique(['banca_id', 'valor_id'], 'tc_banca_linea_valor_uq');
                $table->foreign('banca_id', 'tc_banca_linea_fk')->references('id')->on('tesoreria_crown_banca');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_mesa_juego')) {
            Schema::create('tesoreria_crown_mesa_juego', function (Blueprint $table) {
                $table->id();
                $table->foreignId('deposito_id')->constrained('tesoreria_crown_deposito');
                $table->unsignedInteger('banca_codigo');
                $table->timestamps();
                $table->unique('deposito_id', 'tc_mesa_juego_deposito_uq');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_mesa')) {
            Schema::create('tesoreria_crown_mesa', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sala_id')->constrained('sala');
                $table->foreignId('empresa_id')->constrained('empresa');
                $table->foreignId('jornada_id')->constrained('tesoreria_crown_jornada');
                $table->foreignId('deposito_id')->constrained('tesoreria_crown_deposito');
                $table->unsignedBigInteger('cajero_crown_id')->nullable();
                $table->unsignedBigInteger('banca_id')->nullable();
                $table->char('tipo', 1);
                $table->unsignedInteger('numero');
                $table->char('estado', 1);
                $table->string('comentario', 80)->nullable();
                $table->unsignedBigInteger('usuario_id')->nullable();
                $table->timestamp('confirmado_at')->nullable();
                $table->timestamps();
                $table->unique(['sala_id', 'numero'], 'tc_mesa_numero_uq');
                $table->index(['jornada_id', 'deposito_id'], 'tc_mesa_jornada_dep_idx');
                $table->foreign('cajero_crown_id', 'tc_mesa_cajero_fk')->references('id')->on('cajero_crown');
                $table->foreign('banca_id', 'tc_mesa_banca_fk')->references('id')->on('tesoreria_crown_banca');
            });
        }

        if (! Schema::hasTable('tesoreria_crown_mesa_linea')) {
            Schema::create('tesoreria_crown_mesa_linea', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('mesa_id');
                $table->foreignId('valor_id')->constrained('tesoreria_crown_valor');
                $table->decimal('cantidad', 14, 2);
                $table->timestamps();
                $table->unique(['mesa_id', 'valor_id'], 'tc_mesa_linea_valor_uq');
                $table->foreign('mesa_id', 'tc_mesa_linea_fk')->references('id')->on('tesoreria_crown_mesa');
            });
        }

        $this->sembrarCipolletti();
        $this->crearMenu();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        Schema::dropIfExists('tesoreria_crown_mesa_linea');
        Schema::dropIfExists('tesoreria_crown_mesa');
        Schema::dropIfExists('tesoreria_crown_mesa_juego');
        Schema::dropIfExists('tesoreria_crown_banca_linea');
        Schema::dropIfExists('tesoreria_crown_banca');

        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/mesa')->first();
        if ($menu) {
            DB::table('menu_rol')->where('menu_id', $menu->id)->delete();
            $menu->delete();
        }
        $permisoIds = DB::table('permiso')->whereIn('slug', array_keys(self::PERMISOS))->pluck('id');
        if ($permisoIds->isNotEmpty()) {
            DB::table('permiso_rol')->whereIn('permiso_id', $permisoIds)->delete();
            DB::table('permiso')->whereIn('id', $permisoIds)->delete();
        }
        SuitecrmPermiso::flushCachePermisos();
    }

    private function sembrarCipolletti(): void
    {
        $salaId = (int) DB::table('sala')->whereRaw('UPPER(codigo) = ?', ['CIP'])->value('id');
        if ($salaId <= 0 || DB::table('tesoreria_crown_banca')->where('sala_id', $salaId)->exists()) {
            return;
        }

        $valores = DB::table('tesoreria_crown_valor')->where('sala_id', $salaId)->pluck('id', 'serie');
        $seriePorFicha = [];
        foreach ($this->anita('fichm_codigo,fichm_serie', 'SELECT fichm_codigo, fichm_serie FROM fichamae') as $fila) {
            $serie = strtoupper(trim((string) ($fila->fichm_serie ?? '')));
            if ($serie !== '') {
                $seriePorFicha[(int) $fila->fichm_codigo] = $serie;
            }
        }

        $ahora = now();
        $bancaId = [];
        foreach ($this->anita('tipoj_tipojuego,tipoj_desc', 'SELECT tipoj_tipojuego, tipoj_desc FROM tipojuego') as $fila) {
            $codigo = (int) ($fila->tipoj_tipojuego ?? 0);
            if ($codigo <= 0) {
                continue;
            }
            $bancaId[$codigo] = DB::table('tesoreria_crown_banca')->insertGetId([
                'sala_id' => $salaId,
                'codigo' => $codigo,
                'nombre' => mb_substr(trim((string) ($fila->tipoj_desc ?? '')) ?: ('Tipo '.$codigo), 0, 40),
                'vigencia_desde' => '2003-10-26',
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach ($this->anita('bant_tipojuego,bant_codigo,bant_cantidad', 'SELECT bant_tipojuego, bant_codigo, bant_cantidad FROM bancatipo') as $fila) {
            $tipo = (int) ($fila->bant_tipojuego ?? 0);
            $cantidad = (float) ($fila->bant_cantidad ?? 0);
            $serie = $seriePorFicha[(int) ($fila->bant_codigo ?? 0)] ?? '';
            $valorId = (int) ($valores[$serie] ?? 0);
            if ($tipo <= 0 || $cantidad <= 0 || $valorId <= 0 || ! isset($bancaId[$tipo])) {
                continue;
            }
            $existe = DB::table('tesoreria_crown_banca_linea')
                ->where('banca_id', $bancaId[$tipo])
                ->where('valor_id', $valorId)
                ->first();
            if ($existe) {
                DB::table('tesoreria_crown_banca_linea')->where('id', $existe->id)->update([
                    'cantidad' => (float) $existe->cantidad + $cantidad,
                    'updated_at' => $ahora,
                ]);

                continue;
            }
            DB::table('tesoreria_crown_banca_linea')->insert([
                'banca_id' => $bancaId[$tipo],
                'valor_id' => $valorId,
                'cantidad' => $cantidad,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }

        foreach ($this->anita('mesaj_mesajuego,mesaj_tipoj_banca', 'SELECT mesaj_mesajuego, mesaj_tipoj_banca FROM mesajuego') as $fila) {
            $numero = (int) ($fila->mesaj_mesajuego ?? 0);
            $bancaCodigo = (int) ($fila->mesaj_tipoj_banca ?? 0);
            if ($numero <= 0 || $bancaCodigo <= 0) {
                continue;
            }
            $depositoId = (int) DB::table('tesoreria_crown_deposito')
                ->where('sala_id', $salaId)
                ->where('codigo', 'M'.$numero)
                ->value('id');
            if ($depositoId <= 0) {
                continue;
            }
            DB::table('tesoreria_crown_mesa_juego')->insert([
                'deposito_id' => $depositoId,
                'banca_codigo' => $bancaCodigo,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    /**
     * @return list<object>
     */
    private function anita(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => config('caja_anita.sistema'),
            'ifx_server' => config('caja_anita.ifx_server'),
            'path_sistema' => config('caja_anita.path_sistema'),
        ];
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $filas = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($filas) ? $filas : [];
    }

    private function crearMenu(): void
    {
        $modulo = Menu::query()->where('url', '#tesoreria-crown')->first();
        if (! $modulo) {
            return;
        }
        $ahora = now();
        $permisoIds = [];
        foreach (self::PERMISOS as $slug => $nombre) {
            $id = DB::table('permiso')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permiso')->insertGetId([
                    'nombre' => $nombre,
                    'slug' => $slug,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ]);
            }
            $permisoIds[] = (int) $id;
        }
        $menu = Menu::query()->where('url', 'caja/tesoreria-crown/mesa')->first();
        if (! $menu) {
            $menu = new Menu;
            $menu->nombre = 'Mesas';
            $menu->url = 'caja/tesoreria-crown/mesa';
            $menu->icono = 'fa-th';
            $menu->menu_id = $modulo->id;
            $menu->orden = 5;
            $menu->save();
        }
        $roles = DB::table('menu_rol')->where('menu_id', $modulo->id)->pluck('rol_id');
        foreach ($roles as $rolId) {
            if (! DB::table('menu_rol')->where('menu_id', $menu->id)->where('rol_id', $rolId)->exists()) {
                DB::table('menu_rol')->insert(['menu_id' => $menu->id, 'rol_id' => $rolId]);
            }
            foreach ($permisoIds as $permisoId) {
                if (! DB::table('permiso_rol')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->exists()) {
                    DB::table('permiso_rol')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
                }
            }
        }
        SuitecrmPermiso::flushCachePermisos();
    }
};
