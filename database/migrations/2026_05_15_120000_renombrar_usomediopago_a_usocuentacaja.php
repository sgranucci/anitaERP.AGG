<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mediopago_usomediopago')) {
            Schema::drop('mediopago_usomediopago');
        }

        if (Schema::hasTable('usomediopago')) {
            Schema::rename('usomediopago', 'usocuentacaja');
        }

        if (Schema::hasTable('cuentacaja_usomediopago')) {
            Schema::table('cuentacaja_usomediopago', function (Blueprint $table) {
                $table->dropForeign('fk_ccu_usomediopago');
            });

            DB::statement('ALTER TABLE cuentacaja_usomediopago CHANGE usomediopago_id usocuentacaja_id BIGINT UNSIGNED NOT NULL');

            Schema::rename('cuentacaja_usomediopago', 'cuentacaja_usocuentacaja');

            Schema::table('cuentacaja_usocuentacaja', function (Blueprint $table) {
                $table->foreign('usocuentacaja_id', 'fk_ccu_usocuentacaja')
                    ->references('id')->on('usocuentacaja')->onDelete('cascade')->onUpdate('restrict');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cuentacaja_usocuentacaja')) {
            Schema::table('cuentacaja_usocuentacaja', function (Blueprint $table) {
                $table->dropForeign('fk_ccu_usocuentacaja');
            });

            Schema::rename('cuentacaja_usocuentacaja', 'cuentacaja_usomediopago');

            DB::statement('ALTER TABLE cuentacaja_usomediopago CHANGE usocuentacaja_id usomediopago_id BIGINT UNSIGNED NOT NULL');

            Schema::table('cuentacaja_usomediopago', function (Blueprint $table) {
                $table->foreign('usomediopago_id', 'fk_ccu_usomediopago')
                    ->references('id')->on('usomediopago')->onDelete('cascade')->onUpdate('restrict');
            });
        }

        if (Schema::hasTable('usocuentacaja')) {
            Schema::rename('usocuentacaja', 'usomediopago');
        }

        if (! Schema::hasTable('mediopago_usomediopago') && Schema::hasTable('usomediopago') && Schema::hasTable('mediopago')) {
            Schema::create('mediopago_usomediopago', function (Blueprint $table) {
                $table->unsignedBigInteger('mediopago_id');
                $table->unsignedBigInteger('usomediopago_id');
                $table->primary(['mediopago_id', 'usomediopago_id'], 'pk_mediopago_usomediopago');
                $table->foreign('mediopago_id', 'fk_mmp_mediopago')
                    ->references('id')->on('mediopago')->onDelete('cascade')->onUpdate('restrict');
                $table->foreign('usomediopago_id', 'fk_mmp_usomediopago')
                    ->references('id')->on('usomediopago')->onDelete('cascade')->onUpdate('restrict');
            });
        }
    }
};
