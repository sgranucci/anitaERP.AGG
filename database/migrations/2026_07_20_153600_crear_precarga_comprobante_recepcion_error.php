<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Support\SuitecrmPermiso;

return new class extends Migration
{
    private const MENU_URL = 'compras/precarga_comprobante_recepcion_error';

    private const MENU_PADRE_URL = 'compras/precarga_comprobante_proveedor';

    public function up(): void
    {
        Schema::create('precarga_comprobante_recepcion_error', function (Blueprint $table) {
            $table->id();
            $table->string('origen', 20)->default('API'); // API | PDF_IA
            $table->string('fase', 40)->nullable();
            $table->string('evento', 120)->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('mensaje');
            $table->string('trace_id', 64)->nullable()->index();
            $table->string('numero_oc', 20)->nullable()->index();
            $table->string('cuit_proveedor', 20)->nullable()->index();
            $table->string('cuit_empresa', 20)->nullable();
            $table->string('tipo_comprobante', 20)->nullable();
            $table->string('archivo_nombre', 255)->nullable();
            $table->unsignedBigInteger('usuario_id')->nullable()->index();
            $table->unsignedBigInteger('precarga_id')->nullable()->index();
            $table->string('ip', 45)->nullable();
            $table->mediumText('contexto_json')->nullable();
            $table->timestamps();

            $table->index(['origen', 'created_at'], 'pc_rec_err_origen_created_idx');
            $table->index(['http_status', 'created_at'], 'pc_rec_err_http_created_idx');
        });

        $padreId = (int) (DB::table('menu')->where('url', self::MENU_PADRE_URL)->value('menu_id') ?? 0);
        if ($padreId <= 0) {
            $padreId = (int) (DB::table('menu')->where('nombre', 'Módulo de Compras')->where('url', '#')->value('id') ?? 0);
        }

        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId <= 0 && $padreId > 0) {
            $orden = (int) (DB::table('menu')->where('menu_id', $padreId)->max('orden') ?? 0) + 1;
            $menuId = (int) DB::table('menu')->insertGetId([
                'menu_id' => $padreId,
                'nombre' => 'Errores recepción precarga',
                'url' => self::MENU_URL,
                'orden' => $orden,
                'icono' => 'fa-exclamation-triangle',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if ($menuId > 0) {
            $rolIds = DB::table('menu_rol')
                ->where('menu_id', (int) (DB::table('menu')->where('url', self::MENU_PADRE_URL)->value('id') ?? 0))
                ->pluck('rol_id')
                ->unique()
                ->all();

            foreach ($rolIds as $rolId) {
                $rolId = (int) $rolId;
                if (! DB::table('menu_rol')->where('menu_id', $menuId)->where('rol_id', $rolId)->exists()) {
                    DB::table('menu_rol')->insert([
                        'menu_id' => $menuId,
                        'rol_id' => $rolId,
                    ]);
                }
            }
        }

        SuitecrmPermiso::flushCachePermisos();
    }

    public function down(): void
    {
        $menuId = (int) (DB::table('menu')->where('url', self::MENU_URL)->value('id') ?? 0);
        if ($menuId > 0) {
            DB::table('menu_rol')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }

        Schema::dropIfExists('precarga_comprobante_recepcion_error');
        SuitecrmPermiso::flushCachePermisos();
    }
};
