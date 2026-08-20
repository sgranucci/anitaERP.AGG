<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CuentacontableParentId extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('cuentacontable') || Schema::hasColumn('cuentacontable', 'parent_id')) {
            return;
        }

        Schema::table('cuentacontable', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('rubrocontable_id');
            $table->foreign('parent_id', 'fk_cuentacontable_parent')
                ->references('id')->on('cuentacontable')->nullOnDelete();
            $table->index('parent_id', 'ix_cuentacontable_parent');
        });
    }

    public function down()
    {
        if (! Schema::hasTable('cuentacontable') || ! Schema::hasColumn('cuentacontable', 'parent_id')) {
            return;
        }

        Schema::table('cuentacontable', function (Blueprint $table) {
            $table->dropForeign('fk_cuentacontable_parent');
            $table->dropIndex('ix_cuentacontable_parent');
            $table->dropColumn('parent_id');
        });
    }
}
