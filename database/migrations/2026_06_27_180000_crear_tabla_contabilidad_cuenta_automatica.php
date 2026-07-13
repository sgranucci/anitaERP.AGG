<?php

use App\Services\Contable\ContabilidadCuentaAutomaticaSeedService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contabilidad_cuenta_automatica', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->foreign('empresa_id', 'fk_contab_cuenta_auto_empresa')
                ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
            $table->string('clave', 80);
            $table->unsignedBigInteger('cuentacontable_id')->nullable();
            $table->foreign('cuentacontable_id', 'fk_contab_cuenta_auto_cuenta')
                ->references('id')->on('cuentacontable')->onDelete('set null')->onUpdate('restrict');
            $table->timestamps();
            $table->unique(['empresa_id', 'clave'], 'uk_contab_cuenta_auto_empresa_clave');
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_spanish_ci';
        });

        // Seed vía servicio: valida IDs existentes y no castea maps de config (códigos) a int.
        if (Schema::hasTable('empresa')) {
            $empresaIds = DB::table('empresa')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->values()
                ->all();

            app(ContabilidadCuentaAutomaticaSeedService::class)->asegurarCatalogoEmpresas($empresaIds);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contabilidad_cuenta_automatica');
    }
};
