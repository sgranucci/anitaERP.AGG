<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\ApiAnita;
use App\Mail\Compras\OrdencompraAnitaAuditoriaDiaria;
use App\Models\Compras\Ordencompra;
use App\Models\Seguridad\Usuario;
use App\Models\Stock\Recepcion_Proveedor;
use App\Services\Stock\RecepcionProveedorAnitaBridgeService;
use App\Support\Compras\AnitaSync\Ordencompra\OrdencompraAnitaWhereSupport;
use App\Support\Stock\RecepcionProveedorAnitaReferenciaSupport;
use App\Support\Stock\RecepcionProveedorEstados;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Auditoría diaria OC ERP ↔ Anita: cabeceras faltantes, proveedor sin pad, aplicped faltante,
 * cobertura pendmovp por nro_interno (faltantes/duplicados). Auto-repara y notifica por mail.
 */
final class OrdencompraAnitaAuditoriaDiariaService
{
    public function __construct(
        private readonly OrdencompraAnitaBridgeService $ordencompraBridge,
        private readonly RecepcionProveedorAnitaBridgeService $recepcionBridge,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(
        ?string $fechaDesde = null,
        ?string $fechaHasta = null,
        bool $enviarMail = true,
        ?bool $autoReparar = null,
    ): array {
        $config = config('ordencompra_anita.auditoria_diaria', []);
        $autoReparar ??= filter_var($config['auto_reparar'] ?? true, FILTER_VALIDATE_BOOLEAN);

        $desde = $fechaDesde ?: Carbon::today()->subDays(max(1, (int) ($config['ventana_dias'] ?? 7)) - 1)->toDateString();
        $hasta = $fechaHasta ?: Carbon::today()->toDateString();

        $this->autenticarUsuarioSistema($config);

        $informe = [
            'fecha_calendario' => $desde.' → '.$hasta,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'auto_reparar' => $autoReparar,
            'total_oc' => 0,
            'ok' => 0,
            'reparadas' => 0,
            'pendmovp_cobertura_detectadas' => 0,
            'pendmovp_cobertura_reparadas' => 0,
            'discrepancias' => [],
            'filas_reparadas' => [],
            'errores' => [],
            'filas' => [],
            'proveedores_mal_pad_anita' => [],
            'requiere_alerta' => false,
        ];

        if (! $this->ordencompraBridge->habilitado()) {
            $informe['errores'][] = ['mensaje' => 'Escritura OC Anita deshabilitada.'];
            $informe['requiere_alerta'] = true;

            return $informe;
        }

        $ocs = Ordencompra::query()
            ->where('numeroordencompra', '>', 0)
            ->whereDate('fecha', '>=', $desde)
            ->whereDate('fecha', '<=', $hasta)
            ->orderBy('numeroordencompra')
            ->get();

        $informe['total_oc'] = $ocs->count();

        foreach ($ocs as $oc) {
            try {
                $fila = $this->auditarOc($oc, $autoReparar);
            } catch (\Throwable $e) {
                $informe['errores'][] = [
                    'numero' => (int) $oc->numeroordencompra,
                    'ordencompra_id' => (int) $oc->id,
                    'mensaje' => $e->getMessage(),
                ];
                Log::warning('OrdencompraAnitaAuditoria: error OC', [
                    'numero' => (int) $oc->numeroordencompra,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            $informe['filas'][] = $fila;
            if (! empty($fila['pendmovp_cobertura'])) {
                $informe['pendmovp_cobertura_detectadas']++;
                if (($fila['estado'] ?? '') === 'reparada') {
                    $informe['pendmovp_cobertura_reparadas']++;
                }
            }
            if (($fila['estado'] ?? '') === 'ok') {
                $informe['ok']++;
            } elseif (($fila['estado'] ?? '') === 'reparada') {
                $informe['reparadas']++;
                $informe['filas_reparadas'][] = $fila;
            } else {
                $informe['discrepancias'][] = $fila;
            }
        }

        try {
            $informe['proveedores_mal_pad_anita'] = $this->escanearProveedoresSinPadAnita($desde, $hasta, $autoReparar);
        } catch (\Throwable $e) {
            $informe['errores'][] = [
                'mensaje' => 'Escaneo proveedor Anita: '.$e->getMessage(),
            ];
        }

        foreach ($informe['proveedores_mal_pad_anita'] as $item) {
            if (($item['estado'] ?? '') === 'reparada') {
                $informe['reparadas']++;
                $informe['filas_reparadas'][] = [
                    'numero' => (int) ($item['numero'] ?? 0),
                    'problemas' => [$item['problema'] ?? 'Proveedor sin pad'],
                    'acciones' => $item['acciones'] ?? [],
                    'estado' => 'reparada',
                    'pendmovp_cobertura' => false,
                ];
            } elseif (($item['estado'] ?? '') === 'discrepancia') {
                $informe['discrepancias'][] = [
                    'numero' => (int) ($item['numero'] ?? 0),
                    'problemas' => [$item['problema'] ?? 'Proveedor sin pad'],
                    'acciones' => $item['acciones'] ?? [],
                    'estado' => 'discrepancia',
                    'pendmovp_cobertura' => false,
                ];
            }
        }

        $informe['requiere_alerta'] = $informe['discrepancias'] !== [] || $informe['errores'] !== [];
        $mailSiReparo = filter_var($config['mail_si_reparo'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $debeEnviarMail = $informe['requiere_alerta']
            || filter_var($config['mail_siempre'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ($mailSiReparo && $informe['reparadas'] > 0);

        if ($enviarMail && $debeEnviarMail) {
            $mail = $this->enviarMail($informe, $config);
            $informe = array_merge($informe, $mail);
        }

        return $informe;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditarOc(Ordencompra $oc, bool $autoReparar): array
    {
        $diag = $this->ordencompraBridge->diagnosticarSincronizacionAnita($oc);
        $pendmovpCobertura = $this->esProblemaCoberturaPendmovp($diag['problemas']);
        $fila = [
            'numero' => (int) $oc->numeroordencompra,
            'ordencompra_id' => (int) $oc->id,
            'fecha' => (string) $oc->fecha,
            'estado_erp' => (string) $oc->estadoordencompra,
            'problemas' => $diag['problemas'],
            'acciones' => [],
            'estado' => $diag['problemas'] === [] ? 'ok' : 'discrepancia',
            'pendmovp_cobertura' => $pendmovpCobertura,
        ];

        if ($autoReparar && $diag['problemas'] !== []) {
            $rep = $this->ordencompraBridge->repararSincronizacionAnita($oc);
            $fila['acciones'] = $rep['acciones'];
            $fila['problemas'] = $rep['problemas_restantes'];
            $fila['estado'] = $rep['problemas_restantes'] === [] ? 'reparada' : 'discrepancia';
            $fila['pendmovp_cobertura'] = $pendmovpCobertura
                || $this->esProblemaCoberturaPendmovp($rep['problemas_restantes'])
                || $this->accionFueCoberturaPendmovp($rep['acciones']);
        }

        $aplicped = $this->auditarAplicpedRecepciones($oc, $autoReparar);
        if ($aplicped['acciones'] !== []) {
            $fila['acciones'] = array_merge($fila['acciones'], $aplicped['acciones']);
            if ($fila['estado'] === 'ok' && $aplicped['reparo']) {
                $fila['estado'] = 'reparada';
            }
        }
        if ($aplicped['problemas'] !== []) {
            $fila['problemas'] = array_values(array_unique(array_merge($fila['problemas'], $aplicped['problemas'])));
            if ($fila['estado'] === 'ok') {
                $fila['estado'] = 'discrepancia';
            } elseif ($fila['estado'] === 'reparada' && $aplicped['problemas'] !== []) {
                $fila['estado'] = 'discrepancia';
            }
        }

        return $fila;
    }

    /**
     * @param  list<string>  $problemas
     */
    private function esProblemaCoberturaPendmovp(array $problemas): bool
    {
        foreach ($problemas as $problema) {
            $p = mb_strtolower((string) $problema);
            if (
                str_contains($p, 'pendmovp')
                || str_contains($p, 'nro_interno')
                || str_contains($p, 'duplicadas')
                || str_contains($p, 'sin líneas')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $acciones
     */
    private function accionFueCoberturaPendmovp(array $acciones): bool
    {
        foreach ($acciones as $accion) {
            $a = mb_strtolower((string) $accion);
            if (
                str_contains($a, 'pendmovp')
                || str_contains($a, 'regrabó detalle')
                || str_contains($a, 'duplicadas')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{acciones: list<string>, problemas: list<string>, reparo: bool}
     */
    private function auditarAplicpedRecepciones(Ordencompra $oc, bool $autoReparar): array
    {
        $acciones = [];
        $problemas = [];
        $reparo = false;

        $recepciones = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('estado', RecepcionProveedorEstados::CONFIRMADA)
            ->where('numerorecepcion', '>', 0)
            ->orderBy('id')
            ->get();

        foreach ($recepciones as $recepcion) {
            try {
                if ($autoReparar) {
                    $r = $this->recepcionBridge->repararAplicpedSiFalta($recepcion);
                    foreach ($r['acciones'] as $acc) {
                        if ($acc === 'insertó aplicped') {
                            $acciones[] = 'COM '.(int) $recepcion->numerorecepcion.': '.$acc;
                            $reparo = true;
                        }
                    }
                    foreach ($r['problemas'] as $p) {
                        $problemas[] = 'COM '.(int) $recepcion->numerorecepcion.': '.$p;
                    }
                } else {
                    $codigo = \App\Support\Stock\RecepcionProveedorAnitaWhereSupport::codigoProveedorAnita($recepcion);
                    $clave = \App\Support\Stock\RecepcionProveedorAnitaClaveSupport::resolver($recepcion);
                    if (! $this->recepcionBridge->existeAplicped($codigo, $clave)) {
                        $problemas[] = 'COM '.(int) $recepcion->numerorecepcion.': falta aplicped en Anita.';
                    }
                }
            } catch (\Throwable $e) {
                $problemas[] = 'COM '.(int) $recepcion->numerorecepcion.': '.$e->getMessage();
            }
        }

        return compact('acciones', 'problemas', 'reparo');
    }

    /**
     * Escaneo adicional por fecha Anita (penmp_fecha) por si hay cabeceras con pad incorrecto
     * no cubiertas por el universo ERP del rango (p. ej. sync parcial).
     *
     * @return list<array<string, mixed>>
     */
    private function escanearProveedoresSinPadAnita(string $desde, string $hasta, bool $autoReparar): array
    {
        $ymdDesde = (int) str_replace('-', '', $desde);
        $ymdHasta = (int) str_replace('-', '', $hasta);
        $api = new ApiAnita;
        $raw = $api->apiCallEscritura([
            'acc' => 'list',
            'sistema' => (string) config('ordencompra_anita.escritura.sistema_compras', 'compras'),
            'tabla' => config('ordencompra_anita.tablas.cabecera'),
            'campos' => 'penmp_nro,penmp_proveedor,penmp_fecha,penmp_estado',
            'whereArmado' => ' WHERE penmp_tipo=\'PEP\' AND penmp_letra=\'X\' AND penmp_sucursal=0'
                .' AND penmp_fecha BETWEEN '.$ymdDesde.' AND '.$ymdHasta,
            'limit' => 'FIRST 5000',
        ], 'auditoria oc proveedor scan');

        $rows = json_decode((string) $raw, true);
        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rawProv = trim((string) ($row['penmp_proveedor'] ?? ''));
            if ($rawProv !== '' && preg_match('/^\d{6}$/', $rawProv)) {
                continue;
            }

            $numero = (int) ($row['penmp_nro'] ?? 0);
            $item = [
                'numero' => $numero,
                'proveedor_anita' => $rawProv,
                'problema' => 'Proveedor Anita sin pad 6 dígitos: "'.$rawProv.'"',
                'acciones' => [],
                'estado' => 'discrepancia',
            ];

            if ($autoReparar && $numero > 0) {
                $oc = Ordencompra::query()->where('numeroordencompra', $numero)->first();
                if ($oc) {
                    try {
                        $rep = $this->ordencompraBridge->repararSincronizacionAnita($oc);
                        $item['acciones'] = $rep['acciones'];
                        $item['estado'] = $rep['problemas_restantes'] === [] ? 'reparada' : 'discrepancia';
                        if ($rep['problemas_restantes'] !== []) {
                            $item['problema'] .= ' | Restantes: '.implode('; ', $rep['problemas_restantes']);
                        }
                    } catch (\Throwable $e) {
                        $item['problema'] .= ' | Error reparación: '.$e->getMessage();
                    }
                } else {
                    // Sin ERP: pad directo con ceros a izquierda del valor actual.
                    $padded = RecepcionProveedorAnitaReferenciaSupport::proveedorAnita6($rawProv !== '' ? $rawProv : '0');
                    $clave = OrdencompraAnitaWhereSupport::claveDesdeConfig($numero);
                    $api->apiCallEscritura([
                        'acc' => 'update',
                        'sistema' => (string) config('ordencompra_anita.escritura.sistema_compras', 'compras'),
                        'tabla' => config('ordencompra_anita.tablas.cabecera'),
                        'valores' => \App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport::updateSet([
                            'penmp_proveedor' => \App\Support\Stock\RecepcionProveedorAnitaEscrituraSupport::proveedorSql($padded),
                        ]),
                        'whereArmado' => OrdencompraAnitaWhereSupport::pendmaep($clave),
                    ], 'auditoria oc proveedor pad sin erp');
                    $item['acciones'][] = 'pad directo a '.$padded.' (sin ERP)';
                    $item['estado'] = 'reparada';
                }
            }

            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $informe
     * @param  array<string, mixed>  $config
     * @return array{mail_enviado?: bool, mail_destino?: string, mail_error?: string}
     */
    private function enviarMail(array $informe, array $config): array
    {
        $email = trim((string) ($config['email'] ?? ''));
        if ($email === '') {
            return [];
        }

        try {
            Mail::to($email)->send(new OrdencompraAnitaAuditoriaDiaria($informe));

            return [
                'mail_enviado' => true,
                'mail_destino' => $email,
            ];
        } catch (\Throwable $e) {
            Log::error('OrdencompraAnitaAuditoria: mail falló', ['error' => $e->getMessage()]);

            return ['mail_error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function autenticarUsuarioSistema(array $config): void
    {
        if (Auth::check()) {
            return;
        }

        $usuarioId = (int) ($config['usuario_id'] ?? 0);
        if ($usuarioId <= 0) {
            $usuarioId = (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        }

        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            throw new \RuntimeException('No se pudo autenticar usuario de sistema para auditoría OC Anita.');
        }
    }
}
