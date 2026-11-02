<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\TransferenciaCrown\TransferenciaCrownImputacionSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Crown: cuentas de p-tr-ven y p-tr-pxsol en el catálogo global,
 * copiadas de shared.impcont. No pisa una cuenta que ya tenía valor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (! Schema::hasTable('transferencia_crown_arca_tipo')) {
            Schema::create('transferencia_crown_arca_tipo', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('usuario_id');
                $table->unsignedSmallInteger('codigo_afip');
                $table->string('abreviatura', 10);
                $table->timestamps();
                $table->unique(['usuario_id', 'codigo_afip'], 'uk_transferencia_crown_arca_tipo');
                $table->charset = 'utf8mb4';
                $table->collation = 'utf8mb4_spanish_ci';
            });
        }

        if (! Schema::hasTable('contabilidad_cuenta_automatica')) {
            return;
        }

        TransferenciaCrownImputacionSupport::sembrarDesdeAnita();
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }

        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            DB::table('contabilidad_cuenta_automatica')
                ->where('clave', 'like', 'transferencia_crown.%')
                ->delete();
        }

        Schema::dropIfExists('transferencia_crown_arca_tipo');
    }
};
