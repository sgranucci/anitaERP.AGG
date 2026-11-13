<?php

namespace App\Console\Commands;

use App\Support\Ventas\ClienteExclusionPivaAnitaSyncSupport;
use Illuminate\Console\Command;

/**
 * Alinea exclusión percepción IVA solapa → legacy → Anita.
 * Default: dry-run. --ejecutar persiste ERP y Anita.
 */
class ClienteSincronizarExclusionPivaAnitaCommand extends Command
{
    protected $signature = 'cliente:sincronizar-exclusion-piva-anita
                            {--codigo= : Limitar a un código de cliente}
                            {--ejecutar : Persiste columnas legacy y clim_dfexcl/hfexcl_piva en Anita}';

    protected $description = 'El Bierzo: alinea fechas de exclusión percepción IVA (solapa ERP → cliente legacy → Anita). Dry-run por defecto.';

    public function handle(): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        $codigo = is_string($this->option('codigo')) ? trim($this->option('codigo')) : '';

        $this->comment($ejecutar
            ? 'PERSISTIR: se alinean columnas legacy y se actualizan clim_dfexcl_piva / clim_hfexcl_piva en Anita.'
            : 'Dry-run: no se graba nada.');

        if ($codigo !== '') {
            $this->info("Filtro código={$codigo}");
        }

        try {
            $analisis = ClienteExclusionPivaAnitaSyncSupport::analizar($codigo !== '' ? $codigo : null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Métrica', 'Cantidad'],
            [
                ['Clientes con exclusión IVA (solapa)', $analisis['total']],
                ['A alinear columnas legacy', $analisis['a_alinear_legacy']],
                ['A actualizar Anita', $analisis['a_actualizar_anita']],
                ['Ya OK (legacy+Anita)', $analisis['ya_ok']],
                ['Sin fila en Anita', $analisis['sin_anita']],
                ['Filas con diferencia', count($analisis['filas'])],
            ]
        );

        if ($analisis['filas'] !== []) {
            $this->newLine();
            $this->comment('Diferencias');
            $this->table(
                ['Código', 'Nombre', 'Solapa', 'Legacy', 'Anita', 'Legacy?', 'Anita?'],
                array_map(static function (array $r): array {
                    return [
                        $r['codigo'],
                        mb_substr((string) $r['nombre'], 0, 28),
                        ($r['solapa_desde'] ?: '—').' → '.($r['solapa_hasta'] ?: '—'),
                        ($r['legacy_desde'] ?: '—').' → '.($r['legacy_hasta'] ?: '—'),
                        $r['anita_existe']
                            ? (($r['anita_desde'] === '00000000' ? '—' : $r['anita_desde']).' → '.($r['anita_hasta'] === '00000000' ? '—' : $r['anita_hasta']))
                            : '(sin climae)',
                        $r['cambia_legacy'] ? 'sí' : '',
                        $r['cambia_anita'] ? 'sí' : '',
                    ];
                }, $analisis['filas'])
            );
        }

        if (! $ejecutar) {
            $this->newLine();
            $this->warn('Nada persistido. Para grabar: php artisan cliente:sincronizar-exclusion-piva-anita --ejecutar');

            return self::SUCCESS;
        }

        try {
            $resultado = ClienteExclusionPivaAnitaSyncSupport::aplicar($codigo !== '' ? $codigo : null);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("Legacy actualizados: {$resultado['legacy']}; Anita actualizados: {$resultado['anita']}.");
        foreach ($resultado['errores'] as $err) {
            $this->warn($err);
        }

        return $resultado['errores'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
