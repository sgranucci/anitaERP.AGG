<?php

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Services\Compras\RequisicionImportarFaltantesDesdeAnitaService;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaEsquemaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class RequisicionImportarFaltantesAnitaCommand extends Command
{
    protected $signature = 'requisicion:importar-faltantes-anita
                            {--anio= : Año completo de reqm_fecha (compat AGG; ignorado si hay --desde)}
                            {--desde= : Fecha desde Y-m-d o Ymd}
                            {--hasta= : Fecha hasta Y-m-d o Ymd (default hoy)}
                            {--usuario= : ID usuario ERP para creousuario_id}
                            {--dry-run : Solo informa, no escribe}
                            {--anita-todas : Reescribe penmp/penvp_requisicion en Anita para todas las OC vinculadas}
                            {--oc=* : Números de OC a reescribir en Anita (default 222441 222442 222443)}';

    protected $description = 'Importa requisiciones Anita faltantes en lote (2–3 lecturas) y vincula OC sin requisición';

    public function handle(RequisicionImportarFaltantesDesdeAnitaService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $usuarioOpt = $this->option('usuario');
        $usuarioId = ($usuarioOpt !== null && $usuarioOpt !== '')
            ? (int) $usuarioOpt
            : (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error("No se pudo autenticar usuario id {$usuarioId}.");

            return self::FAILURE;
        }

        [$desdeYmd, $hastaYmd, $anio] = $this->resolverRango();

        $numerosOcAnita = null;
        if (! RequisicionAnitaEsquemaSupport::escribeVinculosOcEnAnita()) {
            $numerosOcAnita = [];
        } elseif (! $this->option('anita-todas')) {
            $ocOpt = $this->option('oc');
            $numerosOcAnita = is_array($ocOpt) && $ocOpt !== []
                ? array_values(array_filter(array_map('intval', $ocOpt)))
                : [222441, 222442, 222443];
        }

        $this->info(sprintf(
            'Rango %d–%d | usuario %d | %s | Crown=%s | Anita OC: %s',
            $desdeYmd,
            $hastaYmd,
            $usuarioId,
            $dryRun ? 'SIMULACIÓN' : 'escritura',
            RequisicionAnitaEsquemaSupport::esCrown() ? 'sí' : 'no',
            $numerosOcAnita === null ? 'todas las vinculadas' : ($numerosOcAnita === [] ? 'omitido' : implode(', ', $numerosOcAnita))
        ));

        try {
            $stats = $service->ejecutar($anio, $usuarioId, $dryRun, $numerosOcAnita, $desdeYmd, $hastaYmd);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['Desde Ymd', (string) ($stats['desde_ymd'] ?? $desdeYmd)],
            ['Hasta Ymd', (string) ($stats['hasta_ymd'] ?? $hastaYmd)],
            ['Lecturas bridge', (string) $stats['lecturas_bridge']],
            ['Cabeceras Anita', (string) $stats['anita_cabeceras']],
            ['Líneas Anita', (string) $stats['anita_lineas']],
            ['Faltantes en ERP', (string) $stats['faltantes']],
            ['Importadas', (string) $stats['importadas']],
            ['Req. con líneas completadas', (string) ($stats['lineas_completadas'] ?? 0)],
            ['Alineadas (Anita)', (string) ($stats['alineadas'] ?? 0)],
            ['ERP nativas omitidas', (string) ($stats['omitidas_erp_nativas'] ?? 0)],
            ['OC vinculadas ERP', (string) $stats['oc_vinculadas']],
            ['OC escritas en Anita', (string) $stats['oc_anita']],
            ['Escrituras bridge', (string) $stats['escrituras_bridge']],
            ['Errores import', (string) count($stats['errores_import'])],
            ['Errores OC', (string) count($stats['errores_oc'])],
        ]);

        foreach (array_slice($stats['errores_import'], 0, 20) as $err) {
            $this->warn($err);
        }
        foreach (array_slice($stats['errores_oc'], 0, 20) as $err) {
            $this->warn($err);
        }

        return ($stats['errores_import'] !== [] || $stats['errores_oc'] !== [])
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function resolverRango(): array
    {
        $desdeOpt = trim((string) $this->option('desde'));
        $hastaOpt = trim((string) $this->option('hasta'));
        $anioOpt = $this->option('anio');

        if ($desdeOpt !== '') {
            $desdeYmd = $this->parseFechaYmd($desdeOpt);
            $hastaYmd = $hastaOpt !== ''
                ? $this->parseFechaYmd($hastaOpt)
                : (int) Carbon::today()->format('Ymd');
            $anio = intdiv($desdeYmd, 10000);

            return [$desdeYmd, $hastaYmd, $anio];
        }

        if (RequisicionAnitaEsquemaSupport::esCrown() && ($anioOpt === null || $anioOpt === '')) {
            $desdeYmd = 20250101;
            $hastaYmd = (int) Carbon::today()->format('Ymd');

            return [$desdeYmd, $hastaYmd, 2025];
        }

        $anio = (int) ($anioOpt !== null && $anioOpt !== '' ? $anioOpt : 2026);

        return [$anio * 10000 + 101, $anio * 10000 + 1231, $anio];
    }

    private function parseFechaYmd(string $valor): int
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?? '';
        if (strlen($digitos) === 8) {
            return (int) $digitos;
        }

        try {
            return (int) Carbon::parse($valor)->format('Ymd');
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException("Fecha inválida: {$valor}");
        }
    }
}
