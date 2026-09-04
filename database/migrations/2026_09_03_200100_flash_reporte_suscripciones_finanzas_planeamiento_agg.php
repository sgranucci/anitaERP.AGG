<?php

use App\Support\Caja\Flash\FlashReporteAggPerfilVistaSupport;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AGG: suma destinatarios de Planeamiento a la vista completa
 * y crea el envío diario con vista Finanzas (pedido Betania).
 *
 * Planeamiento (completa): reirin@, dpicerno@
 * Finanzas (acotada): mvallejos@, lromero@, lbertani@, ygonzalez@
 */
return new class extends Migration
{
    private const NOMBRE_FINANZAS = 'Flash AGG Finanzas';

    /** @var list<string> */
    private const MAILS_PLANEAMIENTO = [
        'reirin@grupoagg.com',
        'dpicerno@grupoagg.com',
    ];

    /** Destinatarios vista finanzas (Yanina: en usuario figura ygonzalez@grupoagg sin .com). */
    private const DEST_FINANZAS = 'mvallejos@grupoagg.com,lromero@grupoagg.com,lbertani@grupoagg.com,ygonzalez@grupoagg.com';

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('flash_reporte_suscripcion')) {
            return;
        }
        if (! Schema::hasColumn('flash_reporte_suscripcion', 'perfil_vista')) {
            return;
        }

        $ahora = now();
        $completa = DB::table('flash_reporte_suscripcion')
            ->where('nombre', 'Flash AGG')
            ->orderBy('id')
            ->first();

        if ($completa !== null) {
            $dest = $this->fusionarMails((string) ($completa->destinatarios ?? ''), self::MAILS_PLANEAMIENTO);
            DB::table('flash_reporte_suscripcion')
                ->where('id', $completa->id)
                ->update([
                    'destinatarios' => $dest,
                    'perfil_vista' => FlashReporteAggPerfilVistaSupport::COMPLETA,
                    'updated_at' => $ahora,
                ]);
        }

        $existeFinanzas = DB::table('flash_reporte_suscripcion')
            ->where('nombre', self::NOMBRE_FINANZAS)
            ->exists();
        if (! $existeFinanzas) {
            DB::table('flash_reporte_suscripcion')->insert([
                'nombre' => self::NOMBRE_FINANZAS,
                'activo' => true,
                'periodicidad' => 'diaria',
                'dia_mes' => 5,
                'dia_semana' => 1,
                'hora' => '16:00',
                'periodo_relativo' => 'mes_actual',
                'mes_fijo' => null,
                'perfil_vista' => FlashReporteAggPerfilVistaSupport::FINANZAS,
                'destinatarios' => self::DEST_FINANZAS,
                'mensaje' => 'Vista acotada Finanzas: coin in, drop, win online, win financiero, ventas bingo, parking y gastronomía.',
                'ultima_ejecucion' => null,
                'ultimo_estado' => null,
                'ultimo_mensaje' => null,
                'usuario_id' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esAgg()) {
            return;
        }
        if (! Schema::hasTable('flash_reporte_suscripcion')) {
            return;
        }

        DB::table('flash_reporte_suscripcion')
            ->where('nombre', self::NOMBRE_FINANZAS)
            ->delete();

        $completa = DB::table('flash_reporte_suscripcion')
            ->where('nombre', 'Flash AGG')
            ->orderBy('id')
            ->first();
        if ($completa === null) {
            return;
        }

        $mails = preg_split('/[;,\s]+/', (string) ($completa->destinatarios ?? '')) ?: [];
        $quitar = array_fill_keys(array_map('strtolower', self::MAILS_PLANEAMIENTO), true);
        $restantes = [];
        foreach ($mails as $mail) {
            $mail = trim($mail);
            if ($mail === '' || isset($quitar[strtolower($mail)])) {
                continue;
            }
            $restantes[] = $mail;
        }

        DB::table('flash_reporte_suscripcion')
            ->where('id', $completa->id)
            ->update([
                'destinatarios' => implode(',', $restantes),
                'updated_at' => now(),
            ]);
    }

    /**
     * @param  list<string>  $agregar
     */
    private function fusionarMails(string $actual, array $agregar): string
    {
        $vistos = [];
        $orden = [];
        foreach (preg_split('/[;,\s]+/', $actual) ?: [] as $mail) {
            $mail = trim($mail);
            if ($mail === '') {
                continue;
            }
            $key = strtolower($mail);
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            $orden[] = $mail;
        }
        foreach ($agregar as $mail) {
            $mail = trim($mail);
            if ($mail === '') {
                continue;
            }
            $key = strtolower($mail);
            if (isset($vistos[$key])) {
                continue;
            }
            $vistos[$key] = true;
            $orden[] = $mail;
        }

        return implode(',', $orden);
    }
};
