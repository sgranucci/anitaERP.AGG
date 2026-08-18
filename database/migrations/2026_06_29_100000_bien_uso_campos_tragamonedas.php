<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bien_uso', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('id');
            $table->string('uid', 20)->nullable()->after('codigo_inventario');
            $table->string('vendor', 255)->nullable()->after('modelo');
            $table->string('tema', 255)->nullable()->after('vendor');
        });

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->string('hostname', 255)->nullable()->change();
        });

        if ($this->tieneIndice('bien_uso', 'uq_bien_uso_codigo_inventario')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropUnique('uq_bien_uso_codigo_inventario');
            });
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->unique('uid', 'uq_bien_uso_uid');
            $table->unique(['empresa_id', 'codigo_inventario'], 'uq_bien_uso_empresa_codigo_inventario');
            $table->foreign('empresa_id', 'fk_bien_uso_empresa')
                ->references('id')->on('empresa')
                ->restrictOnDelete()->restrictOnUpdate();
            $table->index('empresa_id');
            $table->index('vendor');
            $table->index('tema');
        });
    }

    public function down(): void
    {
        if ($this->tieneForeignKey('bien_uso', 'fk_bien_uso_empresa')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropForeign('fk_bien_uso_empresa');
            });
        }

        if ($this->tieneIndice('bien_uso', 'uq_bien_uso_uid')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropUnique('uq_bien_uso_uid');
            });
        }

        if ($this->tieneIndice('bien_uso', 'uq_bien_uso_empresa_codigo_inventario')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropUnique('uq_bien_uso_empresa_codigo_inventario');
            });
        }

        if ($this->tieneIndice('bien_uso', 'bien_uso_empresa_id_index')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['empresa_id']);
            });
        }

        if ($this->tieneIndice('bien_uso', 'bien_uso_vendor_index')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['vendor']);
            });
        }

        if ($this->tieneIndice('bien_uso', 'bien_uso_tema_index')) {
            Schema::table('bien_uso', function (Blueprint $table) {
                $table->dropIndex(['tema']);
            });
        }

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->dropColumn(['empresa_id', 'uid', 'vendor', 'tema']);
        });

        Schema::table('bien_uso', function (Blueprint $table) {
            $table->string('hostname', 255)->nullable(false)->change();
            $table->unique('codigo_inventario', 'uq_bien_uso_codigo_inventario');
        });
    }

    private function tieneIndice(string $tabla, string $nombreIndice): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneIndice($tabla, $nombreIndice);
    }

    private function tieneForeignKey(string $tabla, string $nombreFk): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneForeignKey($tabla, $nombreFk);
    }
};
