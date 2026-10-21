<?php

namespace App\Services\Compras;

use App\Models\Caja\Tipotransaccion_Caja;
use App\Models\Compras\Pagoproveedor;
use App\Models\Compras\Pagoproveedor_Estado;
use App\Models\Compras\Proveedor;
use App\Models\Contable\Asiento;
use App\Models\Seguridad\Usuario;
use App\Support\Compras\AnitaImport\ComprobanteProveedorAnitaImportClaveSupport;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImportEmpresaSupport;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImpresionAuxpagMapper;
use App\Support\Compras\PagosSabanaAnitaArmadoSupport;
use App\Support\Compras\PagosSabanaAnitaBridgeReader;
use App\Support\Stock\RecepcionProveedorAnitaImportSupport;
use Illuminate\Support\Facades\DB;

/**
 * Importa OPP/OPA/OPV desde Anita che_ban.pago como documento.
 * No escribe proveedor_cuentacorriente, aplicaciones ni movimientos de caja.
 */
class PagoproveedorImportarDesdeAnitaService
{
    /** Código Anita de OPV sin proveedor (pag_pro 000000). */
    private const CODIGO_PROVEEDOR_VARIOS = '000000';
    /** @var array<string, int|null> */
    private array $cacheTipoCaja = [];

    /** @var array<string, int|null> */
    private array $cacheProveedor = [];

    private ?int $idProveedorVariosCache = null;

    private bool $proveedorVariosBuscado = false;

    public function __construct(
        private readonly PagosSabanaAnitaBridgeReader $reader = new PagosSabanaAnitaBridgeReader,
    ) {}

    /**
     * @return array{
     *   en_anita: int,
     *   a_crear: int,
     *   creados: int,
     *   omitidos: int,
     *   sin_proveedor: int,
     *   sin_empresa: int,
     *   con_asiento: int,
     *   sin_asiento: int,
     *   con_auxpag: int,
     *   sin_auxpag: int,
     *   proveedor_varios: int,
     *   cuenta_corriente: int,
     *   errores: list<string>,
     *   errores_bridge: list<string>
     * }
     */
    public function importar(
        string $desdeIso,
        string $hastaIso,
        bool $dryRun = true,
        ?int $usuarioId = null,
        ?array $tipos = null,
    ): array {
        $tipos = self::normalizarTipos($tipos);
        $stats = [
            'en_anita' => 0,
            'a_crear' => 0,
            'creados' => 0,
            'omitidos' => 0,
            'sin_proveedor' => 0,
            'sin_empresa' => 0,
            'con_asiento' => 0,
            'sin_asiento' => 0,
            'con_auxpag' => 0,
            'sin_auxpag' => 0,
            'proveedor_varios' => 0,
            'cuenta_corriente' => 0,
            'errores' => [],
            'errores_bridge' => [],
        ];

        $uid = $usuarioId ?? (int) (Usuario::query()->orderBy('id')->value('id') ?? 1);
        $desde = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($desdeIso);
        $hasta = ComprobanteProveedorAnitaImportClaveSupport::fechaAnitaDesdeIso($hastaIso);
        if ($desde <= 0 || $hasta <= 0) {
            $stats['errores'][] = 'Rango de fechas inválido';

            return $stats;
        }

        $empresasAnita = PagoproveedorAnitaImportEmpresaSupport::empresasAnitaImport();
        $pagosAnita = $this->reader->listarPagos($empresasAnita, $desde, $hasta, $stats['errores_bridge'], $tipos);
        $stats['en_anita'] = count($pagosAnita);

        $auxpags = $this->reader->listarAuxpag($empresasAnita, $desde, $hasta, $stats['errores_bridge'], $tipos);
        $auxPorClave = [];
        $cuentasBanco = [];
        foreach ($auxpags as $axp) {
            $claveAux = PagoproveedorAnitaImpresionAuxpagMapper::claveOp(
                (int) ($axp->axp_empresa ?? 0),
                (string) ($axp->axp_tipo ?? ''),
                (int) ($axp->axp_rec ?? 0),
            );
            $auxPorClave[$claveAux][] = $axp;
            $banco = strtoupper(trim((string) ($axp->axp_banco ?? '')));
            if ($banco !== '') {
                $cuentasBanco[$banco] = true;
            }
        }
        $tesmae = $this->reader->mapaTesmae(array_keys($cuentasBanco), $stats['errores_bridge']);

        $subPorNro = [];
        $codigosCuenta = [];
        foreach ($tipos as $tipoSub) {
            foreach ($this->reader->listarSubdiarioTipo($tipoSub, $desde, $hasta, $stats['errores_bridge']) as $linea) {
                $nroSub = (int) ($linea->subd_nro ?? 0);
                if ($nroSub <= 0) {
                    continue;
                }
                $subPorNro[$tipoSub.'|'.$nroSub][] = $linea;
                $codigo = trim((string) ($linea->subd_cuenta ?? ''));
                if ($codigo !== '' && $codigo !== '0') {
                    $codigosCuenta[$codigo] = true;
                }
            }
        }
        $nombresCuenta = $this->nombresCuentaPorCodigo(array_keys($codigosCuenta));
        $asientosPorOp = $this->indexarAsientosSubdiario($desdeIso, $hastaIso, $tipos);

        $existentes = $this->indexarPagosExistentes($desdeIso, $hastaIso, $tipos);

        foreach ($pagosAnita as $pago) {
            $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) ($pago->pag_tipo ?? ''));
            if (! in_array($tipo, $tipos, true)) {
                continue;
            }
            // Crown: pag_empresa=0 → empresa ERP configurada (default 1).
            $empresaId = PagoproveedorAnitaImportEmpresaSupport::empresaErpDesdeAnita((int) ($pago->pag_empresa ?? 0));
            $sucursal = (int) ($pago->pag_sucursal ?? 0);
            $numero = (int) ($pago->pag_rec ?? 0);
            if ($numero <= 0) {
                continue;
            }

            // Letra Anita: hace falta para enganchar asiento (planAsientoPagos).
            $letra = ComprobanteProveedorAnitaImportClaveSupport::letra((string) ($pago->pag_letra ?? ' '));
            $clave = self::claveOp($empresaId, $tipo, $sucursal, $numero);
            if (isset($existentes[$clave])) {
                $stats['omitidos']++;

                continue;
            }

            $provCodigo = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita((string) ($pago->pag_pro ?? ''));
            $esVarios = self::esProveedorVarios($provCodigo);
            $proveedorId = $esVarios
                ? $this->idProveedorVarios(! $dryRun, $stats)
                : $this->resolverProveedorId($provCodigo);
            if (! $proveedorId) {
                if ($esVarios && $dryRun) {
                    $stats['proveedor_varios'] = 1;
                } else {
                    $stats['sin_proveedor']++;
                    if (count($stats['errores']) < 20) {
                        $stats['errores'][] = "Proveedor {$provCodigo} no está en ERP ({$tipo} {$sucursal}-{$numero})";
                    }

                    continue;
                }
            }

            $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($pago->pag_fecha ?? '');
            if ($fecha === '') {
                $stats['errores'][] = "Fecha inválida {$tipo} {$numero}";

                continue;
            }

            $empresaAnita = (int) ($pago->pag_empresa ?? 0);
            $claveAux = PagoproveedorAnitaImpresionAuxpagMapper::claveOp($empresaAnita, $tipo, $numero);
            $lineasAux = $auxPorClave[$claveAux] ?? [];
            if ($lineasAux === []) {
                $stats['sin_auxpag']++;
            } else {
                $stats['con_auxpag']++;
            }
            $asientoId = (int) ($asientosPorOp[$tipo.'|'.$numero] ?? 0);
            if ($asientoId > 0) {
                $stats['con_asiento']++;
            } else {
                $stats['sin_asiento']++;
            }

            $stats['a_crear']++;
            if ($dryRun || ! $proveedorId) {
                continue;
            }

            try {
                DB::transaction(function () use ($pago, $tipo, $sucursal, $numero, $empresaId, $proveedorId, $fecha, $uid, $letra, $lineasAux, $tesmae, $subPorNro, $nombresCuenta, $asientoId) {
                    $monedaAnita = (int) ($pago->pag_cod_mon_me ?? 0);
                    $monedaId = RecepcionProveedorAnitaImportSupport::monedaIdDesdeCodigoAnita($monedaAnita > 0 ? $monedaAnita : 1);
                    $cotizacion = (float) ($pago->pag_cotizacion ?? 1);
                    if ($cotizacion <= 0) {
                        $cotizacion = 1.0;
                    }
                    $monto = abs((float) ($pago->pag_trec ?? 0));
                    $detalle = trim((string) ($pago->pag_leyenda ?? ''));
                    if ($detalle === '') {
                        $detalle = trim((string) ($pago->pag_entregado_a ?? ''));
                    }
                    if ($detalle === '') {
                        $detalle = 'Importado desde Anita — '.$tipo.' documento (sin cuenta corriente)';
                    }
                    $cuentaContable = $this->textoCuentas(
                        $subPorNro[$tipo.'|'.$numero] ?? [],
                        $nombresCuenta,
                    );
                    $desglose = PagosSabanaAnitaArmadoSupport::desgloseMedios($lineasAux, $tesmae);
                    $snapshotPartes = PagoproveedorAnitaImpresionAuxpagMapper::aSnapshot($lineasAux, $tesmae);

                    $row = Pagoproveedor::query()->create([
                        'empresa_id' => $empresaId,
                        'tipotransaccion_caja_id' => $this->resolverTipoCajaId($tipo),
                        'tipocomprobante' => $tipo,
                        'letra' => $letra !== '' ? $letra : (string) config('pagoproveedor.letra_default', ' '),
                        'sucursal' => $sucursal,
                        'numerotransaccion' => (string) $numero,
                        'fecha' => $fecha,
                        'proveedor_id' => $proveedorId,
                        'detalle' => $detalle,
                        'anita_impresion_json' => [
                            'version' => 1,
                            'sincronizado_at' => now()->toIso8601String(),
                            'origen' => 'anita_import_sin_cc',
                            'aplicaciones' => $snapshotPartes['aplicaciones'],
                            'medios_caja' => $snapshotPartes['medios_caja'],
                            'cheques' => $snapshotPartes['cheques'],
                            'desglose' => self::desglosePlano($desglose),
                            'cuenta_contable' => $cuentaContable,
                        ],
                        'estado' => 'CONFIRMADA',
                        'asiento_id' => $asientoId > 0 ? $asientoId : null,
                        'monto' => round($monto, 4),
                        'cotizacion' => $cotizacion,
                        'moneda_id' => $monedaId,
                        'modo_cotizacion' => 'dia',
                        'usuario_id' => $uid,
                    ]);

                    Pagoproveedor_Estado::query()->create([
                        'pagoproveedor_id' => $row->id,
                        'fecha' => now(),
                        'estado' => 'CONFIRMADA',
                        'usuario_id' => $uid,
                        'observacion' => 'Importado desde Anita ('.$tipo.' sin cuenta corriente)',
                    ]);
                });
                $stats['creados']++;
                $existentes[$clave] = true;
            } catch (\Throwable $e) {
                $stats['errores'][] = "{$tipo} {$sucursal}-{$numero}: ".$e->getMessage();
            }
        }

        $importaOppOpa = array_intersect($tipos, ['OPP', 'OPA']) !== [];
        if (! $dryRun && $stats['creados'] > 0 && $importaOppOpa) {
            $imp = app(PagoproveedorAnitaImpresionBackfillService::class)
                ->backfill($desdeIso, $hastaIso, false);
            $stats['impresion_actualizadas'] = $imp['actualizadas'] ?? 0;
            $stats['impresion_retenciones'] = $imp['retenciones_creadas'] ?? 0;
            foreach (array_slice($imp['errores'] ?? [], 0, 10) as $e) {
                $stats['errores'][] = 'impresión: '.$e;
            }
            foreach (array_slice($imp['errores_bridge'] ?? [], 0, 5) as $e) {
                $stats['errores_bridge'][] = $e;
            }
        }

        return $stats;
    }

    /**
     * @return array<string, true>
     */
    /**
     * @param  list<string>  $tipos
     * @return array<string, true>
     */
    private function indexarPagosExistentes(string $desdeIso, string $hastaIso, array $tipos): array
    {
        $out = [];
        foreach (Pagoproveedor::query()
            ->whereBetween('fecha', [$desdeIso, $hastaIso])
            ->whereIn('tipocomprobante', $tipos)
            ->get(['empresa_id', 'tipocomprobante', 'sucursal', 'numerotransaccion']) as $p) {
            $out[self::claveOp(
                (int) $p->empresa_id,
                (string) $p->tipocomprobante,
                (int) $p->sucursal,
                (int) $p->numerotransaccion,
            )] = true;
        }

        return $out;
    }

    private static function claveOp(int $empresaId, string $tipo, int $sucursal, int $numero): string
    {
        return $empresaId.'|'
            .ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo).'|'
            .$sucursal.'|'
            .$numero;
    }

    private function resolverProveedorId(string $codigoAnita): ?int
    {
        if ($codigoAnita === '') {
            return null;
        }
        if (array_key_exists($codigoAnita, $this->cacheProveedor)) {
            return $this->cacheProveedor[$codigoAnita];
        }
        $norm = ltrim($codigoAnita, '0');
        $id = (int) (Proveedor::query()
            ->where('codigo', $codigoAnita)
            ->orWhere('codigo', $norm)
            ->orWhere('codigo', str_pad($norm !== '' ? $norm : '0', 6, '0', STR_PAD_LEFT))
            ->value('id') ?: 0);

        return $this->cacheProveedor[$codigoAnita] = ($id > 0 ? $id : null);
    }

    private function resolverTipoCajaId(string $abrev): ?int
    {
        $abrev = ComprobanteProveedorAnitaImportClaveSupport::tipo($abrev);
        if ($abrev === '') {
            return null;
        }
        if (! array_key_exists($abrev, $this->cacheTipoCaja)) {
            $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', $abrev)->value('id') ?: 0);
            if ($id <= 0 && $abrev !== 'OPP') {
                $id = (int) (Tipotransaccion_Caja::query()->where('abreviatura', 'OPP')->value('id') ?: 0);
            }
            $this->cacheTipoCaja[$abrev] = $id > 0 ? $id : null;
        }

        return $this->cacheTipoCaja[$abrev];
    }

    /**
     * @param  list<string>|null  $tipos
     * @return list<string>
     */
    private static function normalizarTipos(?array $tipos): array
    {
        $base = $tipos === null || $tipos === [] ? ['OPP', 'OPA'] : $tipos;
        $ok = [];
        foreach ($base as $tipo) {
            $t = ComprobanteProveedorAnitaImportClaveSupport::tipo((string) $tipo);
            if (in_array($t, ['OPP', 'OPA', 'OPV', 'AOP'], true)) {
                $ok[$t] = $t;
            }
        }

        return array_values($ok === [] ? ['OPP' => 'OPP', 'OPA' => 'OPA'] : $ok);
    }

    private static function esProveedorVarios(string $codigo): bool
    {
        $codigo = trim($codigo);

        return $codigo === '' || ltrim($codigo, '0') === '' || $codigo === self::CODIGO_PROVEEDOR_VARIOS;
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function idProveedorVarios(bool $crear, array &$stats): ?int
    {
        if ($this->proveedorVariosBuscado) {
            return $this->idProveedorVariosCache;
        }
        $this->proveedorVariosBuscado = true;

        $id = (int) (Proveedor::query()->where('codigo', self::CODIGO_PROVEEDOR_VARIOS)->value('id') ?: 0);
        if ($id > 0) {
            return $this->idProveedorVariosCache = $id;
        }

        $stats['proveedor_varios'] = 1;
        if (! $crear) {
            return null;
        }

        return $this->idProveedorVariosCache = $this->crearProveedorVarios();
    }

    private function crearProveedorVarios(): int
    {
        $cuentaId = (int) (DB::table('cuentacontable')
            ->where('codigo', '211100001')
            ->orderBy('id')
            ->value('id') ?: 0);
        if ($cuentaId <= 0) {
            throw new \RuntimeException('No hay cuenta 211100001 para el proveedor de OPV sin código.');
        }

        $row = Proveedor::query()->create([
            'codigo' => self::CODIGO_PROVEEDOR_VARIOS,
            'nombre' => 'OPV SIN PROVEEDOR',
            'domicilio' => '-',
            'pais_id' => (int) DB::table('pais')->orderBy('id')->value('id'),
            'nroinscripcion' => '00-00000000-0',
            'condicioniva_id' => (int) DB::table('condicioniva')->orderBy('id')->value('id'),
            'agentepercepcioniva' => 'N',
            'retieneiva' => 'N',
            'retieneganancia' => 'N',
            'condicionganancia' => 'N',
            'retienesuss' => 'N',
            'condicionIIBB_id' => (int) DB::table('condicionIIBB')->orderBy('id')->value('id'),
            'agentepercepcionIIBB' => 'N',
            'cuentacontable_id' => $cuentaId,
            'estado' => 'Activo',
            'tipoalta' => 'D',
            'regimenfacturacion' => 'RG 3419',
        ]);

        return (int) $row->id;
    }

    /**
     * @param  list<string>  $tipos
     * @return array<string, int> tipo|numero => asiento_id
     */
    private function indexarAsientosSubdiario(string $desdeIso, string $hastaIso, array $tipos): array
    {
        $out = [];
        $query = Asiento::query()
            ->where('anita_origen', 'subdiario')
            ->whereBetween('fecha', [$desdeIso, $hastaIso]);
        $query->where(function ($q) use ($tipos) {
            foreach ($tipos as $tipo) {
                $q->orWhere('observacion', 'like', '[SUBD] T '.$tipo.' %');
            }
        });

        foreach ($query->get(['id', 'observacion']) as $asiento) {
            $obs = (string) $asiento->observacion;
            if (! preg_match('/\[SUBD\] T (OPP|OPA|OPV|AOP) (\d+)/', $obs, $m)) {
                continue;
            }
            if (! in_array($m[1], $tipos, true)) {
                continue;
            }
            $out[$m[1].'|'.(int) $m[2]] = (int) $asiento->id;
        }

        return $out;
    }

    /**
     * @param  list<string>  $codigos
     * @return array<string, string>
     */
    private function nombresCuentaPorCodigo(array $codigos): array
    {
        if ($codigos === []) {
            return [];
        }
        $out = [];
        foreach (DB::table('cuentacontable')->whereIn('codigo', $codigos)->get(['codigo', 'nombre']) as $row) {
            $out[(string) $row->codigo] = trim((string) $row->nombre);
        }

        return $out;
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<string, string>  $nombres
     */
    private function textoCuentas(array $lineas, array $nombres): string
    {
        $cods = [];
        foreach ($lineas as $linea) {
            $codigo = trim((string) ($linea->subd_cuenta ?? ''));
            if ($codigo !== '' && $codigo !== '0') {
                $cods[$codigo] = true;
            }
        }
        $partes = [];
        foreach (array_keys($cods) as $codigo) {
            $nombre = $nombres[$codigo] ?? '';
            $partes[] = trim($codigo.($nombre !== '' ? ' '.$nombre : ''));
        }

        return implode(' | ', $partes);
    }

    /**
     * @param  array<string, mixed>  $desglose
     * @return array<string, mixed>
     */
    private static function desglosePlano(array $desglose): array
    {
        $plano = [];
        foreach ([
            'efectivo', 'transferencia', 'ch_propios', 'ch_terceros', 'doc_propios', 'doc_terceros',
            'creditos', 'adelantos', 'varios', 'intercompany',
            'retencion_iva', 'retencion_gan', 'retencion_ibr', 'retencion_suss',
        ] as $clave) {
            $plano[$clave] = round((float) ($desglose[$clave] ?? 0), 2);
        }
        $plano['comprobantes'] = implode(' | ', array_values($desglose['comprobantes'] ?? []));
        $plano['ch_prop_emi'] = implode(' ', array_values($desglose['ch_prop_emi'] ?? []));
        $plano['ch_terc_ent'] = implode(' ', array_values($desglose['ch_terc_ent'] ?? []));
        $plano['doc_prop_emit'] = implode(' ', array_values($desglose['doc_prop_emit'] ?? []));
        $plano['doc_terc_entr'] = implode(' ', array_values($desglose['doc_terc_entr'] ?? []));
        $plano['fecha_cheque'] = (string) ($desglose['fecha_cheque'] ?? '');
        $plano['banco'] = implode(' | ', array_values($desglose['bancos'] ?? []));

        return $plano;
    }
}
