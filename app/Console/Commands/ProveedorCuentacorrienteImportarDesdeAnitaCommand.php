<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Compras\ProveedorCuentacorrienteImportarDesdeAnitaService;
use App\Support\Compras\AnitaImport\ProveedorCuentacorrienteAnitaImportFormatoSupport;
use Illuminate\Console\Command;

class ProveedorCuentacorrienteImportarDesdeAnitaCommand extends Command
{
    protected $signature = 'proveedor-cuentacorriente:importar-desde-anita
                            {--proveedor= : Código proveedor Anita/ERP (opcional)}
                            {--empresa= : ID empresa ERP (AGG: filtra prov_empresa/com_empresa)}
                            {--desde= : Fecha ISO desde (prov_fecha)}
                            {--hasta= : Fecha ISO hasta}
                            {--limite= : Máximo de cuotas/deuda a procesar}
                            {--usuario-id=1 : usuario_id de auditoría}
                            {--dry-run : Solo analiza (default si no hay --ejecutar)}
                            {--ejecutar : Persiste en ERP (no escribe Anita)}';

    protected $description = 'Alinea deuda Anita→ERP (no toca CC/apps de OP ERP; nativas solo apps; OPA pendientes)';

    public function handle(ProveedorCuentacorrienteImportarDesdeAnitaService $service): int
    {
        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar && (bool) $this->option('dry-run')) {
            $this->error('No combine --ejecutar con --dry-run.');

            return self::FAILURE;
        }
        $dryRun = ! $ejecutar;
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $proveedor = trim((string) $this->option('proveedor'));
        $empresaOpt = $this->option('empresa');
        $empresaId = ($empresaOpt !== null && $empresaOpt !== '') ? (int) $empresaOpt : null;
        $desde = $this->option('desde') ? (string) $this->option('desde') : null;
        $hasta = $this->option('hasta') ? (string) $this->option('hasta') : null;
        $limiteOpt = $this->option('limite');
        $limite = ($limiteOpt !== null && $limiteOpt !== '') ? (int) $limiteOpt : null;
        $usuarioId = max(1, (int) $this->option('usuario-id'));

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Entorno %s | empresa_col=%s | empresa_id=%s | %s → %s | proveedor %s | %s',
            $perfil['entorno'],
            $perfil['tiene_empresa'] ? 'sí' : 'no',
            $empresaId !== null && $empresaId > 0 ? (string) $empresaId : 'todas',
            $desde ?: 'sin desde',
            $hasta ?: 'sin hasta',
            $proveedor !== '' ? $proveedor : 'todos',
            $dryRun ? 'DRY-RUN' : 'EJECUTAR',
        ));
        $this->line('Filtro: promov pendiente + nativas ERP con apps Anita; OPA; no pisa CP nativos.');

        try {
            $stats = $service->importar(
                $dryRun,
                $proveedor !== '' ? $proveedor : null,
                $desde,
                $hasta,
                $usuarioId,
                $limite,
                $empresaId,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Métrica', 'Cantidad'], [
            ['promov pendiente Anita', $stats['anita_promov']],
            ['compra Anita (match)', $stats['anita_compra']],
            ['OPA / crédito sin compra', $stats['credito_sin_compra_anita']],
            ['Tipo no deuda / OP', $stats['omitidas_tipo_no_deuda']],
            ['Saldadas Anita (precisión)', $stats['omitidas_saldadas_anita']],
            ['Sin compra Anita', $stats['omitidas_sin_compra']],
            ['Sin proveedor ERP', $stats['omitidas_sin_proveedor']],
            ['Sin tipo', $stats['omitidas_sin_tipo']],
            ['Empresa fuera de alcance', $stats['omitidas_empresa'] ?? 0],
            ['Ya al día', $stats['omitidas_al_dia']],
            ['Nativas saldadas Anita (candidatas)', $stats['nativas_saldadas_anita'] ?? 0],
            ['Nativas solo apps', $stats['nativas_solo_apps'] ?? 0],
            ['A procesar', $stats['a_procesar']],
            ['CP a crear / creados', $stats['a_crear_cp'].' / '.$stats['cp_creados']],
            ['OPA a crear / creados', $stats['a_crear_opa'].' / '.$stats['opa_creados']],
            ['CC a crear / creadas', $stats['a_crear_cc'].' / '.$stats['cc_creadas']],
            ['Alinear ANITA_IMPORT', $stats['a_alinear_anita_import'] ?? 0],
            ['aplmovp Anita', $stats['anita_aplmovp']],
            ['Aplicaciones Anita (pares)', $stats['aplicaciones_anita']],
            ['Docs con apps a importar', $stats['a_actualizar_aplicaciones'] ?? 0],
            ['Aplicaciones planificadas', $stats['aplicaciones_planificadas']],
            ['Aplicaciones creadas', $stats['aplicaciones_creadas']],
            ['Aplicaciones omitidas (ya en ERP)', $stats['aplicaciones_omitidas'] ?? 0],
            ['Pagos sintéticos CC', $stats['pagos_sinteticos'] ?? 0],
        ]);

        if ($stats['muestra'] !== []) {
            $this->line('Muestra (hasta 25):');
            $this->table(
                ['Comprobante', 'Prov', 'Emp', 'Fecha', 'Total', 'Pag.Anita', 'Apl.ERP', 'CP', 'CC', 'Apl', 'Nativa'],
                array_map(static fn (array $r) => [
                    $r['etiqueta'],
                    $r['proveedor'],
                    $r['empresa_anita'] ?? '',
                    $r['fecha'],
                    number_format((float) $r['total'], 2, ',', '.'),
                    number_format((float) $r['pagado_anita'], 2, ',', '.'),
                    number_format((float) $r['aplicado_erp'], 2, ',', '.'),
                    $r['accion_cp'],
                    $r['accion_cc'],
                    $r['accion_apl'],
                    ! empty($r['es_nativo']) ? 'sí' : '',
                ], $stats['muestra'])
            );
        }

        foreach (array_slice($stats['errores'], 0, 40) as $error) {
            $this->warn((string) $error);
        }

        if ($dryRun) {
            $this->comment('Dry-run: no se grabó nada. Para persistir (incluye seed de tipotransaccion_compra si falta): --ejecutar.');
        }

        return self::SUCCESS;
    }
}
