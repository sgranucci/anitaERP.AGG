<?php

namespace App\Support\Contable\ConciliacionBancaria;

use Carbon\Carbon;

/**
 * Arma la solapa Pendientes (CHP) desde cpromae y el subconjunto de carátula
 * (vencimiento en el mes de corte = “emitidos/entregados no acreditados”).
 */
final class ConciliacionBancariaPendientesCpromaeSupport
{
    public function __construct(
        private readonly ConciliacionBancariaCpromaeBridgeReader $bridge = new ConciliacionBancariaCpromaeBridgeReader(),
    ) {
    }

    /**
     * @param  list<string>  $numerosCheque  Números a resolver en cpromae (Excel Contaduría o Ch: mayor)
     * @param  array<string, array{tip?: string, importe?: float, fecha_emision?: string|null, fecha_cheque?: string|null, detalle?: string}>  $semillaPorNumero
     * @return array{
     *   pendientes: list<array<string,mixed>>,
     *   caratula: list<array<string,mixed>>,
     *   suma_pendientes: float,
     *   suma_caratula: float,
     *   fuente: string
     * }
     */
    public function armar(
        string $codigoCuentacaja,
        int $empresaId,
        Carbon $fechaCorte,
        array $numerosCheque = [],
        array $semillaPorNumero = [],
        bool $excluirAnulados = true,
    ): array {
        $corteYmd = (int) $fechaCorte->format('Ymd');
        $numeros = array_values(array_unique(array_filter(array_map(
            static fn ($n) => ltrim((string) preg_replace('/\D/', '', (string) $n), '0'),
            $numerosCheque !== [] ? $numerosCheque : array_keys($semillaPorNumero),
        ), static fn (string $n) => $n !== '' && $n !== '0')));

        if ($numeros !== []) {
            $rows = $this->bridge->listarPorNumeros($codigoCuentacaja, $numeros, $empresaId);
            $fuente = $semillaPorNumero !== [] ? 'cpromae_semilla_excel' : 'cpromae_por_numeros';
        } else {
            // Fallback volumoso: solo cuando no hay semilla ni Ch:.
            $rows = $this->bridge->listarPorCuenta($codigoCuentacaja, $empresaId);
            $fuente = 'cpromae_cuenta';
        }

        $byNumero = [];
        foreach ($rows as $row) {
            $mapped = $this->mapearFila($row);
            if ($mapped === null) {
                continue;
            }
            if ($excluirAnulados && $this->estaAnulado($mapped) && abs((float) $mapped['importe']) < 0.005) {
                // Anulado con importe 0: si hay semilla Excel, se reinyecta abajo.
                continue;
            }
            if ($excluirAnulados && $this->estaAnulado($mapped) && $semillaPorNumero === []) {
                continue;
            }
            $nro = $mapped['numero_cheque'];
            if (! isset($byNumero[$nro]) || trim((string) $mapped['estado']) === '') {
                $byNumero[$nro] = $mapped;
            }
        }

        // Completa / sobreescribe con semilla Contaduría (importes y filas faltantes).
        foreach ($semillaPorNumero as $nroRaw => $seed) {
            $nro = ltrim((string) preg_replace('/\D/', '', (string) $nroRaw), '0');
            if ($nro === '' || $nro === '0') {
                continue;
            }
            $base = $byNumero[$nro] ?? [
                'tip' => (string) ($seed['tip'] ?? 'CHP'),
                'numero_cheque' => $nro,
                'fecha_emision' => $seed['fecha_emision'] ?? null,
                'fecha_cheque' => $seed['fecha_cheque'] ?? null,
                'fecha_entrega' => null,
                'fecha_conciliacion' => null,
                'importe' => 0.0,
                'estado' => '',
                'estado_banco' => '',
                'entregado_a' => '',
                'proveedor_codigo' => '',
                'nro_op' => '',
                'para_dep' => '',
                'incluye_caratula' => false,
                'origen_json' => ['semilla' => true],
            ];
            if (! empty($seed['fecha_emision'])) {
                $base['fecha_emision'] = $seed['fecha_emision'];
            }
            if (! empty($seed['fecha_cheque'])) {
                $base['fecha_cheque'] = $seed['fecha_cheque'];
            }
            if (isset($seed['tip']) && $seed['tip'] !== '') {
                $base['tip'] = (string) $seed['tip'];
            }
            $impSeed = isset($seed['importe']) ? round((float) $seed['importe'], 2) : null;
            if ($impSeed !== null && (abs((float) $base['importe']) < 0.005 || abs($impSeed - (float) $base['importe']) > 0.02)) {
                $base['importe'] = abs($impSeed);
                $base['origen_json'] = array_merge(
                    is_array($base['origen_json'] ?? null) ? $base['origen_json'] : [],
                    ['importe_desde_excel' => true],
                );
            }
            if (! empty($seed['detalle'])) {
                $base['entregado_a'] = $base['entregado_a'] !== ''
                    ? $base['entregado_a']
                    : (string) $seed['detalle'];
            }
            $byNumero[$nro] = $base;
        }

        $pendientes = array_values($byNumero);
        usort($pendientes, static function (array $a, array $b): int {
            return [$a['fecha_emision'] ?? '', $a['numero_cheque']]
                <=> [$b['fecha_emision'] ?? '', $b['numero_cheque']];
        });

        $caratula = [];
        foreach ($pendientes as &$p) {
            $p['incluye_caratula'] = $this->incluyeEnCaratula($p, $corteYmd);
            if ($p['incluye_caratula']) {
                $caratula[] = $p;
            }
        }
        unset($p);

        $sumaPend = round(array_sum(array_map(static fn (array $p) => (float) $p['importe'], $pendientes)), 2);
        $sumaCar = round(array_sum(array_map(static fn (array $p) => (float) $p['importe'], $caratula)), 2);

        return [
            'pendientes' => $pendientes,
            'caratula' => $caratula,
            'suma_pendientes' => $sumaPend,
            'suma_caratula' => $sumaCar,
            'fuente' => $fuente,
        ];
    }

    /**
     * Carátula Contaduría: cheques de Pendientes con F.Dev (cpro_fecha_cheque) en el mes de corte.
     * En mayo/26 eso replica exactamente -95.778.606,73 sobre la solapa Pendientes del Excel.
     *
     * @param  array<string, mixed>  $cheque
     */
    public function incluyeEnCaratula(array $cheque, int $fechaCorteYmd): bool
    {
        $venc = $this->fechaYmd($cheque['fecha_cheque'] ?? null);
        if ($venc <= 0) {
            return false;
        }

        return intdiv($venc, 100) === intdiv($fechaCorteYmd, 100);
    }

    /**
     * @param  list<array{tip: string, numero: string, importe: float, detalle?: string, fecha_emision?: string|null, fecha_cheque?: string|null}>  $chequesExcel
     * @return array<string, array{tip: string, importe: float, fecha_emision?: string|null, fecha_cheque?: string|null, detalle?: string}>
     */
    public static function semillaDesdeExcelDetalle(array $chequesExcel): array
    {
        $out = [];
        foreach ($chequesExcel as $ch) {
            $n = ltrim((string) preg_replace('/\D/', '', (string) ($ch['numero'] ?? '')), '0');
            if ($n === '' || $n === '0') {
                continue;
            }
            $out[$n] = [
                'tip' => (string) ($ch['tip'] ?? 'CHP'),
                'importe' => abs((float) ($ch['importe'] ?? 0)),
                'fecha_emision' => $ch['fecha_emision'] ?? null,
                'fecha_cheque' => $ch['fecha_cheque'] ?? null,
                'detalle' => (string) ($ch['detalle'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  object|array<string,mixed>  $row
     * @return array<string,mixed>|null
     */
    public function mapearFila(object|array $row): ?array
    {
        $r = is_array($row) ? (object) $row : $row;
        $nro = ltrim((string) preg_replace('/\D/', '', (string) ($r->cpro_nro_cheque ?? '')), '0');
        if ($nro === '' || $nro === '0') {
            return null;
        }

        $fechaEntrega = $this->parseFechaAnita($r->cpro_fecha_entrega ?? null);

        return [
            'tip' => 'CHP',
            'numero_cheque' => $nro,
            'fecha_emision' => $this->parseFechaAnita($r->cpro_fecha_emision ?? null)?->toDateString(),
            'fecha_cheque' => $this->parseFechaAnita($r->cpro_fecha_cheque ?? null)?->toDateString(),
            'fecha_entrega' => $fechaEntrega?->toDateString(),
            'fecha_conciliacion' => null,
            'importe' => round((float) ($r->cpro_importe ?? 0), 2),
            'estado' => (string) ($r->cpro_estado ?? ''),
            'estado_banco' => (string) ($r->cpro_estado_banco ?? ''),
            'entregado_a' => trim((string) ($r->cpro_entregado_a ?? '')),
            'proveedor_codigo' => ltrim((string) ($r->cpro_proveedor ?? ''), '0'),
            'nro_op' => (string) ($r->cpro_nro_op ?? ''),
            'para_dep' => (string) ($r->cpro_para_dep ?? ''),
            'incluye_caratula' => false,
            'origen_json' => [
                'cpro_cuenta' => (string) ($r->cpro_cuenta ?? ''),
                'cpro_empresa' => (string) ($r->cpro_empresa ?? ''),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $cheque
     */
    private function estaAnulado(array $cheque): bool
    {
        return strtoupper(trim((string) ($cheque['estado'] ?? ''))) === 'A';
    }

    private function parseFechaAnita(mixed $valor): ?Carbon
    {
        if ($valor === null || $valor === '' || $valor === '0' || $valor === 0) {
            return null;
        }
        $s = preg_replace('/\D/', '', (string) $valor) ?? '';
        if (strlen($s) !== 8) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Ymd', $s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function fechaYmd(mixed $valor): int
    {
        if ($valor === null || $valor === '') {
            return 0;
        }
        try {
            return (int) Carbon::parse((string) $valor)->format('Ymd');
        } catch (\Throwable) {
            return 0;
        }
    }
}
