<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('depmae', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable()->after('codigo');
        });

        DB::table('depmae')->update(['empresa_id' => 1]);

        Schema::table('depmae', function (Blueprint $table) {
            $table->unsignedBigInteger('empresa_id')->nullable(false)->change();
            $table->foreign('empresa_id', 'fk_depmae_empresa')
                ->references('id')
                ->on('empresa')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('depmae', function (Blueprint $table) {
            $table->dropForeign('fk_depmae_empresa');
            $table->dropColumn('empresa_id');
        });
    }
};
