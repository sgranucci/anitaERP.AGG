<?php

declare(strict_types=1);

namespace App\Services\Contable;

use App\Support\Contable\CuentaAutomaticaClaves;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Manual de procedimiento de cuentas y centros de costo de BIYEMAS (entorno AGG).
 * El contenido sale del plan y de las asignaciones vigentes en la base.
 */
final class ManualCuentasProcedimientoService
{
    private const EMPRESA_NOMBRE = 'BIYEMAS S.A.';

    private const CACHE_SEGUNDOS = 1200;

    public function meta(): array
    {
        $empresaId = $this->empresaId();
        $secciones = Cache::remember(
            'manual.cuentas.procedimiento.biyemas.'.$empresaId,
            self::CACHE_SEGUNDOS,
            fn (): array => $this->secciones($empresaId),
        );

        $base = rtrim((string) config('app.url', ''), '/').(env('APP_CARPETA', '') ?: '');

        return [
            'titulo' => 'Manual de procedimiento',
            'subtitulo' => 'Cuentas contables y centros de costo — BIYEMAS S.A.',
            'version' => '1.0',
            'empresa' => 'BIYEMAS S.A. · entorno AGG',
            'fecha' => now()->locale('es')->translatedFormat('d \d\e F \d\e Y'),
            'url_base' => $base,
            'url_login' => $base.'/seguridad/login',
            'secciones' => $secciones,
        ];
    }

    private function empresaId(): int
    {
        $id = (int) DB::table('empresa')->where('nombre', self::EMPRESA_NOMBRE)->value('id');
        if ($id <= 0) {
            abort(404, 'No está cargada la empresa BIYEMAS S.A.');
        }

        return $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function secciones(int $empresaId): array
    {
        $cuentas = DB::table('cuentacontable')
            ->where('empresa_id', $empresaId)
            ->orderBy('codigo')
            ->get();

        $porId = [];
        foreach ($cuentas as $cuenta) {
            $porId[(int) $cuenta->id] = $cuenta;
        }

        $centros = DB::table('centrocosto')->orderBy('codigo')->get();
        $centrosPorId = [];
        foreach ($centros as $centro) {
            $centrosPorId[(int) $centro->id] = $centro;
        }

        $vinculos = $this->centrosPorCuenta($centrosPorId);
        $usos = $this->usos($empresaId, $porId);
        $ejemplos = $this->ejemplos($empresaId, $porId, $centrosPorId);

        return array_merge(
            $this->seccionesMarco($cuentas, $centros, $usos),
            $this->seccionesPlan($cuentas, $vinculos, $usos, $ejemplos),
        );
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $cuentas
     * @param  \Illuminate\Support\Collection<int, object>  $centros
     * @param  array<int, list<string>>  $usos
     * @return array<int, array<string, mixed>>
     */
    private function seccionesMarco($cuentas, $centros, array $usos): array
    {
        $imputables = $cuentas->where('tipocuenta', '1')->count();
        $conCosto = $cuentas->where('manejaccosto', 'S')->count();

        $patas = [];
        foreach ($usos as $cuentaId => $textos) {
            foreach ($textos as $texto) {
                if (str_starts_with($texto, 'Pata de proceso')) {
                    $cuenta = $cuentas->firstWhere('id', $cuentaId);
                    if ($cuenta) {
                        $patas[] = [
                            (string) $cuenta->codigo,
                            (string) $cuenta->nombre,
                            preg_replace('/^Pata de proceso · /', '', $texto) ?? $texto,
                        ];
                    }
                }
            }
        }
        usort($patas, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

        $filasCentros = [];
        foreach ($centros as $centro) {
            $filasCentros[] = [
                (string) $centro->codigo,
                trim((string) ($centro->abreviatura ?? '')) !== '' ? (string) $centro->abreviatura : '—',
                (string) $centro->nombre,
                trim((string) ($centro->tipoiva ?? '')) !== '' ? (string) $centro->tipoiva : 'Sin tipo de IVA',
            ];
        }

        return [
            [
                'titulo' => '1. Para qué es este manual',
                'parrafos' => [
                    'Este manual describe el plan de cuentas de BIYEMAS S.A. en el entorno AGG y el papel de cada cuenta dentro de Anita ERP. Cada rubro del plan tiene un apartado con las cuentas que lo componen, los procesos que las usan y un asiento de ejemplo tomado del último movimiento real de esa cuenta.',
                    'Los datos se leen de la configuración vigente: plan de cuentas, maestros (proveedor, cliente, artículo, conceptos, impuestos, sueldos, caja) y patas fijas de proceso. Hay '.$cuentas->count().' cuentas en el plan, de las cuales '.$imputables.' son imputables. '.$conCosto.' cuentas exigen centro de costo en la línea del asiento.',
                    'Una cuenta título agrupa el rubro y una totalizadora resume el saldo. Ninguna de las dos recibe imputaciones. La cuenta imputable es la que aparece en el asiento.',
                    'El buscador de esta página encuentra por código o por nombre dentro del rubro.',
                ],
            ],
            [
                'titulo' => '2. Centros de costo de BIYEMAS',
                'parrafos' => [
                    'El centro de costo es una dimensión del movimiento. El asiento sigue siendo uno solo: el centro parte el importe para control de gestión, mayor, presupuesto y aprobaciones. No reemplaza a la cuenta contable.',
                    'En la cuenta, el indicador «maneja centro de costo» obliga a informar un centro en cada línea con importe. Si además la cuenta tiene centros asignados, solo valen esos. Si maneja centro y la lista está vacía, se acepta cualquier centro del maestro. Si la cuenta no maneja centro, la línea puede grabarse sin él.',
                    'El tipo de IVA del centro (directo, indirecto o no computable) clasifica el gasto y, en compras, participa de la abreviatura del comprobante de proveedor según el centro de la orden de compra.',
                ],
                'tabla' => [
                    'caption' => 'Maestro de centros de costo',
                    'headers' => ['Código', 'Abreviatura', 'Nombre', 'Tipo de IVA'],
                    'rows' => $filasCentros,
                ],
            ],
            [
                'titulo' => '3. Dónde interviene el centro de costo',
                'parrafos' => [
                    'Compras. La requisición y la orden de compra llevan el centro. El árbol de aprobación se resuelve por centro y por monto. En la factura de proveedor, cada concepto y cada línea de gasto arrastran el centro hasta el renglón del asiento. El tipo de factura de la precarga se arma con el primer centro de la orden.',
                    'Stock y recepción. La recepción de proveedores hereda el centro de la orden. En las transferencias contables el centro viaja con la cuenta que maneja costo.',
                    'Caja, solicitudes de pago y gastos. El ingreso, el egreso y la solicitud de pago imputan el centro en la línea cuando la cuenta lo exige. La apertura de gastos y la partida de presupuesto también quedan asociadas a un centro.',
                    'Ventas y gastronomía. El concepto de venta puede fijar cuenta y centro. Los cierres de jornada y de rendición (máquinas, bingo, estacionamiento, vending) graban el centro configurado para esa pata.',
                    'Sueldos. El legajo tiene un centro. El asiento de liquidación abre el gasto por ese centro cuando la cuenta de sueldos maneja costo.',
                    'Logística. La solicitud de logística se carga a un centro, que además puede tener un tope mensual.',
                    'Contabilidad. El mayor y los reportes definibles se pueden abrir por centro. El saldo mensual se acumula por empresa, cuenta, centro, período y moneda. En el asiento manual, una línea con importe de una cuenta que maneja centro no se graba si el centro está vacío o si no está en la lista permitida de esa cuenta.',
                ],
            ],
            [
                'titulo' => '4. Patas fijas de proceso',
                'parrafos' => [
                    'Estas cuentas están asignadas en BIYEMAS como pata automática de un proceso. El circuito las toma por ese rol, con independencia del código. El asiento de ejemplo de cada una está en el rubro del plan, más abajo.',
                    'El resto de las cuentas imputables entra por un maestro: la cuenta del proveedor o del cliente, la de venta o compra del artículo, el concepto de IVA compras, el concepto de gasto, el impuesto, la retención, el concepto de sueldos, la cuenta de caja o la partida de presupuesto. Si no está en ningún maestro y tiene movimientos, se usó en un asiento manual o en un circuito que eligió la cuenta en el momento.',
                ],
                'tabla' => [
                    'caption' => 'Cuentas automáticas asignadas en BIYEMAS',
                    'headers' => ['Código', 'Cuenta', 'Proceso'],
                    'rows' => $patas !== [] ? $patas : [['—', 'Sin patas asignadas', '—']],
                ],
            ],
            [
                'titulo' => '5. Cómo se arma el asiento en cada circuito',
                'parrafos' => [
                    'Factura de proveedor. El debe sale del concepto de IVA compra (gasto, IVA crédito u otro concepto) y de las líneas de gasto del comprobante, con el centro de cada línea. El haber es la cuenta contable del proveedor. Una nota de crédito invierte el sentido.',
                    'Recepción de mercadería. El haber de la provisión es la cuenta automática de provisión de facturas a recibir. En una orden anticipada intervienen las cuentas de factura anticipada, anticipo de bienes de uso o proveedores de intangible, según el caso configurado.',
                    'Pago a proveedor. El debe cancela la cuenta del proveedor, o la de anticipos si la orden de pago es a cuenta y esa pata está asignada. El haber es la cuenta contable de la caja o el banco que paga, más las retenciones (Ganancias, IVA, SUSS e IIBB) cuando corresponden.',
                    'Venta. El haber del neto es la cuenta de venta del artículo, del concepto de venta o, en locales (POS, Tiendanube, Facturante), la pata de facturación local. El IVA débito fiscal es la pata de ventas. El debe es la cuenta del cliente o la del medio de cobro.',
                    'Caja. Cada cuenta de caja o banco tiene su cuenta contable. Los cheques propios posdatados y los cheques de terceros usan las patas de cheques diferidos y de valores a depositar.',
                    'Sueldos. Cada concepto de liquidación tiene cuenta de debe (gasto) y de haber (pasivo). Si el concepto no trae cuenta, se usa la pata genérica de sueldos a pagar, gasto remunerativo, no remunerativo, contribuciones o retenciones. El centro es el del legajo.',
                    'Cierres de rendición y de jornada. Cada importe del cierre (ventas, premios, cánones, diferencias de caja, fondos fijos) pega en la pata automática de ese concepto, con el centro previsto para el cierre.',
                ],
                'items' => [
                    'En el plan, la columna «Asiento de ejemplo» muestra el último asiento de BIYEMAS que movió esa cuenta: fecha, número, origen, debe o haber, importe y centro.',
                    'Si ese asiento tiene hasta ocho líneas, se listan todas. Si tiene más, se muestra la línea de la cuenta y las tres contrapartidas de mayor importe.',
                ],
            ],
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $cuentas
     * @param  array<int, string>  $vinculos
     * @param  array<int, list<string>>  $usos
     * @param  array<int, string>  $ejemplos
     * @return array<int, array<string, mixed>>
     */
    private function seccionesPlan($cuentas, array $vinculos, array $usos, array $ejemplos): array
    {
        $lista = $cuentas->values()->all();
        $capitulos = [];
        $actual = null;
        $grupoMayor = '';
        $grupoRubro = '';
        $orden = [];

        $abrir = static function (?array &$actual, array &$capitulos, string $grupo, object $cuenta): void {
            if ($actual !== null) {
                $capitulos[] = $actual;
            }
            $actual = [
                'grupo' => $grupo,
                'codigo' => (string) $cuenta->codigo,
                'nombre' => (string) $cuenta->nombre,
                'cuentas' => [$cuenta],
            ];
        };

        foreach ($lista as $i => $cuenta) {
            $nivel = (int) $cuenta->nivel;
            $tipo = (string) $cuenta->tipocuenta;

            if ($nivel === 0) {
                $orden[] = $cuenta;
                continue;
            }
            if ($nivel === 1 && $tipo === '2') {
                $grupoMayor = (string) $cuenta->nombre;
                continue;
            }
            if ($nivel === 2 && $tipo === '2') {
                $grupoRubro = (string) $cuenta->nombre;
                continue;
            }
            if ($nivel === 2 && $tipo === '1') {
                $abrir($actual, $capitulos, $grupoMayor, $cuenta);
                continue;
            }

            $esGrupoHoja = false;
            if ($tipo === '2' && $nivel >= 3) {
                $esGrupoHoja = true;
                for ($j = $i + 1, $tope = count($lista); $j < $tope; $j++) {
                    $sigue = $lista[$j];
                    if ((string) $sigue->tipocuenta !== '2') {
                        continue;
                    }
                    if ((int) $sigue->nivel <= $nivel) {
                        break;
                    }
                    $esGrupoHoja = false;
                    break;
                }
            }
            if ($tipo === '2' && ! $esGrupoHoja) {
                continue;
            }
            if ($esGrupoHoja) {
                $abrir($actual, $capitulos, trim($grupoMayor.' · '.$grupoRubro, ' ·'), $cuenta);
                continue;
            }
            if ($actual !== null) {
                $actual['cuentas'][] = $cuenta;
            }
        }
        if ($actual !== null) {
            $capitulos[] = $actual;
        }

        $secciones = [];
        $n = 6;
        foreach ($capitulos as $cap) {
            $secciones[] = $this->seccionRubro($n, $cap, $vinculos, $usos, $ejemplos);
            $n++;
        }
        if ($orden !== []) {
            $secciones[] = $this->seccionRubro($n, [
                'grupo' => 'Cuentas de orden',
                'codigo' => '',
                'nombre' => 'Documentos en garantía',
                'cuentas' => $orden,
            ], $vinculos, $usos, $ejemplos);
        }

        return $secciones;
    }

    /**
     * @param  array{grupo: string, codigo: string, nombre: string, cuentas: list<object>}  $cap
     * @param  array<int, string>  $vinculos
     * @param  array<int, list<string>>  $usos
     * @param  array<int, string>  $ejemplos
     * @return array<string, mixed>
     */
    private function seccionRubro(int $n, array $cap, array $vinculos, array $usos, array $ejemplos): array
    {
        $tituloCodigo = $cap['codigo'] !== '' ? $cap['codigo'].' ' : '';
        $rows = [];
        foreach ($cap['cuentas'] as $cuenta) {
            $id = (int) $cuenta->id;
            $tipo = (string) $cuenta->tipocuenta;
            $rows[] = [
                (string) $cuenta->codigo,
                (string) $cuenta->nombre,
                $this->etiquetaTipo($tipo),
                $vinculos[$id] ?? $this->textoCentro((string) $cuenta->manejaccosto, ''),
                $this->textoProceso($tipo, $usos[$id] ?? [], isset($ejemplos[$id])),
                $tipo === '1' ? ($ejemplos[$id] ?? 'Sin movimientos en BIYEMAS.') : '—',
            ];
        }

        return [
            'titulo' => $n.'. '.$cap['grupo'].' — '.$tituloCodigo.$cap['nombre'],
            'parrafos' => [
                'Cuentas de este rubro en BIYEMAS. El asiento de ejemplo es el último movimiento de la cuenta en esta empresa.',
            ],
            'tabla' => [
                'caption' => $tituloCodigo.$cap['nombre'],
                'headers' => ['Código', 'Cuenta', 'Tipo', 'Centro de costo', 'Dónde interviene', 'Asiento de ejemplo'],
                'rows' => $rows,
            ],
        ];
    }

    private function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            '1' => 'Imputable',
            '2' => 'Título',
            '3' => 'Totalizadora',
            default => $tipo,
        };
    }

    /**
     * @param  list<string>  $usos
     */
    private function textoProceso(string $tipo, array $usos, bool $tieneAsiento): string
    {
        if ($tipo === '2') {
            return 'Agrupa el rubro. No recibe imputaciones.';
        }
        if ($tipo === '3') {
            return 'Resume el saldo del rubro. No recibe imputaciones.';
        }
        if ($usos !== []) {
            return implode(' · ', $usos);
        }
        if ($tieneAsiento) {
            return 'Tiene movimientos en BIYEMAS y no está asignada como cuenta fija de un maestro.';
        }

        return 'Sin asignación en maestros y sin movimientos en BIYEMAS.';
    }

    /**
     * @param  array<int, object>  $centrosPorId
     * @return array<int, string>
     */
    private function centrosPorCuenta(array $centrosPorId): array
    {
        if (! Schema::hasTable('cuentacontable_centrocosto')) {
            return [];
        }

        $filas = DB::table('cuentacontable_centrocosto')->orderBy('centrocosto_id')->get();
        $codigos = [];
        foreach ($filas as $fila) {
            $centro = $centrosPorId[(int) $fila->centrocosto_id] ?? null;
            if ($centro === null) {
                continue;
            }
            $codigos[(int) $fila->cuentacontable_id][] = (string) $centro->codigo.' '.trim((string) $centro->nombre);
        }

        $out = [];
        $cuentas = DB::table('cuentacontable')->where('empresa_id', $this->empresaIdCache())->get(['id', 'manejaccosto']);
        foreach ($cuentas as $cuenta) {
            $lista = $codigos[(int) $cuenta->id] ?? [];
            $out[(int) $cuenta->id] = $this->textoCentro((string) $cuenta->manejaccosto, implode(', ', $lista));
        }

        return $out;
    }

    private function empresaIdCache(): int
    {
        static $id = null;
        if ($id === null) {
            $id = $this->empresaId();
        }

        return $id;
    }

    private function textoCentro(string $maneja, string $lista): string
    {
        if (! in_array($maneja, ['S', 's', '1'], true)) {
            return 'No pide centro';
        }
        if ($lista === '') {
            return 'Obligatorio, cualquier centro';
        }
        $partes = explode(', ', $lista);
        if (count($partes) <= 4) {
            return 'Obligatorio: '.$lista;
        }

        return 'Obligatorio: '.implode(', ', array_slice($partes, 0, 4)).' y '.(count($partes) - 4).' más';
    }

    /**
     * @param  array<int, object>  $porId
     * @return array<int, list<string>>
     */
    private function usos(int $empresaId, array $porId): array
    {
        $usos = [];
        $this->patasAutomaticas($empresaId, $usos);
        $this->contarMaestro($usos, 'proveedor', 'cuentacontable_id', $empresaId, 'Cuenta del proveedor');
        $this->contarMaestro($usos, 'proveedor', 'cuentacontablecompra_id', $empresaId, 'Cuenta de compra del proveedor');
        $this->contarMaestro($usos, 'proveedor', 'cuentacontableme_id', $empresaId, 'Cuenta en moneda extranjera del proveedor');
        $this->contarMaestro($usos, 'cliente', 'cuentacontable_id', null, 'Cuenta del cliente', $porId);
        $this->nombresMaestro($usos, 'cuentacaja', 'cuentacontable_id', 'nombre', $empresaId, 'Cuenta de caja o banco');
        $this->contarMaestro($usos, 'articulo', 'cuentacontableventa_id', $empresaId, 'Cuenta de venta del artículo');
        $this->contarMaestro($usos, 'articulo', 'cuentacontablecompra_id', $empresaId, 'Cuenta de compra del artículo');
        $this->contarMaestro($usos, 'articulo', 'cuentacontableimpinterno_id', $empresaId, 'Impuesto interno del artículo');
        $this->contarMaestro($usos, 'articulo_cuentacontable', 'cuentacontable_id', $empresaId, 'Imputación contable del artículo');
        $this->nombresMaestro($usos, 'concepto_ivacompra', 'cuentacontabledebe_id', 'nombre', $empresaId, 'Debe del concepto de IVA compras');
        $this->nombresMaestro($usos, 'concepto_ivacompra', 'cuentacontablehaber_id', 'nombre', $empresaId, 'Haber del concepto de IVA compras');
        $this->nombresJoin(
            $usos,
            'conceptogasto_cuentacontable',
            'cuentacontable_id',
            'conceptogasto',
            'conceptogasto_id',
            'nombre',
            null,
            'Concepto de gasto',
            $porId,
        );
        $this->contarMaestro($usos, 'concepto_venta_cuentacontable', 'cuentacontable_id', $empresaId, 'Concepto de venta');
        $this->contarMaestro($usos, 'impuesto_cuentacontable', 'cuentacontable_id', $empresaId, 'Cuenta de impuesto');
        $this->nombresMaestro($usos, 'retencionIIBB', 'cuentacontable_id', 'nombre', null, 'Retención de IIBB', $porId);
        $this->contarMaestro($usos, 'concepto_imputacion_sueldos', 'cuenta_debe_id', $empresaId, 'Debe de conceptos de sueldos');
        $this->contarMaestro($usos, 'concepto_imputacion_sueldos', 'cuenta_haber_id', $empresaId, 'Haber de conceptos de sueldos');
        $this->contarMaestro($usos, 'concepto_solicitudpago_cuenta', 'cuentacontable_id', $empresaId, 'Concepto de solicitud de pago');
        $this->nombresMaestro($usos, 'local_venta', 'cuentacontable_venta_id', 'nombre', $empresaId, 'Venta del local');
        $this->contarMaestro($usos, 'partidagasto', 'cuentacontable_id', $empresaId, 'Partida de presupuesto');
        $this->contarMaestro($usos, 'apertura_gasto_empresa', 'cuentacontable_id', $empresaId, 'Apertura de gasto');
        $this->contarMaestro($usos, 'apertura_gasto_empresa', 'cuentacontable_contrapartida_id', $empresaId, 'Contrapartida de apertura de gasto');
        $this->contarMaestro($usos, 'imputacion_perdida_empresa', 'cuentacontable_id', $empresaId, 'Imputación de pérdida de caja');
        $this->contarMaestro($usos, 'ordencompra', 'contrato_cuentacontable_id', $empresaId, 'Cuenta de contrato o suscripción');
        $this->contarMaestro($usos, 'concepto_cuentacontable_ordenventa', 'cuentacontable_id', $empresaId, 'Concepto de orden de venta');
        $this->contarMaestro($usos, 'provincia_cuentacontableiibb', 'cuentacontable_id', $empresaId, 'IIBB por provincia');
        $this->contarMaestro($usos, 'retencion_cobranza_cuentacontable', 'cuentacontable_id', $empresaId, 'Retención de cobranza');
        $this->contarMaestro($usos, 'regimen_percepcion_cuentacontable', 'cuentacontable_id', $empresaId, 'Percepción');
        $this->contarMaestro($usos, 'reporte_contable_cuenta', 'cuentacontable_id', $empresaId, 'Renglón de reporte contable');
        $this->contarMaestro($usos, 'usuario_cuentacontable', 'cuentacontable_id', null, 'Habilitada para usuarios asignados', $porId);

        foreach ($usos as $id => $lista) {
            $usos[$id] = array_values(array_unique($lista));
        }

        return $usos;
    }

    /**
     * @param  array<int, list<string>>  $usos
     */
    private function patasAutomaticas(int $empresaId, array &$usos): void
    {
        $catalogo = CuentaAutomaticaClaves::catalogo();
        if (Schema::hasTable('contabilidad_cuenta_automatica')) {
            $filas = DB::table('contabilidad_cuenta_automatica')
                ->where('empresa_id', $empresaId)
                ->whereNotNull('cuentacontable_id')
                ->get();
            foreach ($filas as $fila) {
                $meta = $catalogo[(string) $fila->clave] ?? null;
                if ($meta === null) {
                    continue;
                }
                $this->agregar($usos, (int) $fila->cuentacontable_id, 'Pata de proceso · '.$meta['grupo'].': '.$meta['descripcion']);
            }
        }
        if (Schema::hasTable('contabilidad_cuenta_automatica_detalle')) {
            $filas = DB::table('contabilidad_cuenta_automatica_detalle')
                ->where('empresa_id', $empresaId)
                ->whereNotNull('cuentacontable_id')
                ->get();
            foreach ($filas as $fila) {
                $meta = $catalogo[(string) $fila->clave] ?? null;
                if ($meta === null) {
                    continue;
                }
                $this->agregar($usos, (int) $fila->cuentacontable_id, 'Pata de proceso · '.$meta['grupo'].': '.$meta['descripcion']);
            }
        }
    }

    /**
     * @param  array<int, list<string>>  $usos
     * @param  array<int, object>|null  $porId
     */
    private function contarMaestro(
        array &$usos,
        string $tabla,
        string $columna,
        ?int $empresaId,
        string $etiqueta,
        ?array $porId = null,
    ): void {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna)) {
            return;
        }
        $q = DB::table($tabla)->whereNotNull($columna)->where($columna, '>', 0);
        if ($empresaId !== null && Schema::hasColumn($tabla, 'empresa_id')) {
            $q->where('empresa_id', $empresaId);
        }
        $filas = $q->groupBy($columna)->selectRaw($columna.' as cuenta_id, COUNT(*) as n')->get();
        foreach ($filas as $fila) {
            $id = (int) $fila->cuenta_id;
            if ($porId !== null && ! isset($porId[$id])) {
                continue;
            }
            $this->agregar($usos, $id, $etiqueta.' ('.(int) $fila->n.')');
        }
    }

    /**
     * @param  array<int, list<string>>  $usos
     * @param  array<int, object>|null  $porId
     */
    private function nombresMaestro(
        array &$usos,
        string $tabla,
        string $columna,
        string $columnaNombre,
        ?int $empresaId,
        string $etiqueta,
        ?array $porId = null,
    ): void {
        if (! Schema::hasTable($tabla) || ! Schema::hasColumn($tabla, $columna) || ! Schema::hasColumn($tabla, $columnaNombre)) {
            return;
        }
        $q = DB::table($tabla)->whereNotNull($columna)->where($columna, '>', 0);
        if ($empresaId !== null && Schema::hasColumn($tabla, 'empresa_id')) {
            $q->where('empresa_id', $empresaId);
        }
        $filas = $q->groupBy($columna)
            ->selectRaw($columna.' as cuenta_id, COUNT(*) as n, SUBSTRING(GROUP_CONCAT(DISTINCT '.$columnaNombre.' ORDER BY '.$columnaNombre.' SEPARATOR ", "), 1, 160) as nombres')
            ->get();
        foreach ($filas as $fila) {
            $id = (int) $fila->cuenta_id;
            if ($porId !== null && ! isset($porId[$id])) {
                continue;
            }
            $nombres = trim((string) $fila->nombres);
            $texto = $etiqueta.' ('.(int) $fila->n.')';
            if ($nombres !== '' && (int) $fila->n <= 4) {
                $texto .= ': '.$nombres;
            }
            $this->agregar($usos, $id, $texto);
        }
    }

    /**
     * @param  array<int, list<string>>  $usos
     * @param  array<int, object>  $porId
     */
    private function nombresJoin(
        array &$usos,
        string $tabla,
        string $columnaCuenta,
        string $tablaNombre,
        string $columnaFk,
        string $columnaNombre,
        ?int $empresaId,
        string $etiqueta,
        array $porId,
    ): void {
        if (! Schema::hasTable($tabla) || ! Schema::hasTable($tablaNombre)) {
            return;
        }
        $q = DB::table($tabla.' as x')
            ->join($tablaNombre.' as n', 'n.id', '=', 'x.'.$columnaFk)
            ->whereNotNull('x.'.$columnaCuenta);
        if ($empresaId !== null && Schema::hasColumn($tabla, 'empresa_id')) {
            $q->where('x.empresa_id', $empresaId);
        }
        $filas = $q->groupBy('x.'.$columnaCuenta)
            ->selectRaw('x.'.$columnaCuenta.' as cuenta_id, COUNT(*) as n, SUBSTRING(GROUP_CONCAT(DISTINCT n.'.$columnaNombre.' ORDER BY n.'.$columnaNombre.' SEPARATOR ", "), 1, 160) as nombres')
            ->get();
        foreach ($filas as $fila) {
            $id = (int) $fila->cuenta_id;
            if (! isset($porId[$id])) {
                continue;
            }
            $nombres = trim((string) $fila->nombres);
            $texto = $etiqueta.' ('.(int) $fila->n.')';
            if ($nombres !== '' && (int) $fila->n <= 4) {
                $texto .= ': '.$nombres;
            }
            $this->agregar($usos, $id, $texto);
        }
    }

    /**
     * @param  array<int, list<string>>  $usos
     */
    private function agregar(array &$usos, int $cuentaId, string $texto): void
    {
        if ($cuentaId <= 0 || $texto === '') {
            return;
        }
        $usos[$cuentaId][] = $texto;
    }

    /**
     * @param  array<int, object>  $porId
     * @param  array<int, object>  $centrosPorId
     * @return array<int, string>
     */
    private function ejemplos(int $empresaId, array $porId, array $centrosPorId): array
    {
        $ultimos = DB::select(
            'SELECT am.cuentacontable_id, MAX(am.asiento_id) as asiento_id
             FROM asiento_movimiento am
             INNER JOIN asiento a ON a.id = am.asiento_id AND a.empresa_id = ?
             GROUP BY am.cuentacontable_id',
            [$empresaId],
        );
        if ($ultimos === []) {
            return [];
        }

        $asientoPorCuenta = [];
        $asientoIds = [];
        foreach ($ultimos as $fila) {
            $cuentaId = (int) $fila->cuentacontable_id;
            if (! isset($porId[$cuentaId])) {
                continue;
            }
            $asientoPorCuenta[$cuentaId] = (int) $fila->asiento_id;
            $asientoIds[(int) $fila->asiento_id] = true;
        }
        $ids = array_keys($asientoIds);
        $headers = DB::table('asiento as a')
            ->leftJoin('tipoasiento as t', 't.id', '=', 'a.tipoasiento_id')
            ->whereIn('a.id', $ids)
            ->get([
                'a.id', 'a.numeroasiento', 'a.fecha', 'a.observacion',
                'a.venta_id', 'a.compra_id', 'a.comprobante_proveedor_id', 'a.pagoproveedor_id',
                'a.caja_movimiento_id', 'a.cobranza_id', 'a.recepcionproveedor_id',
                'a.liquidacion_sueldos_id', 'a.jornada_gastronomia_id', 'a.rendicion_estacionamiento_caja_id',
                'a.solicitudpago_id', 'a.ordencompra_id', 'a.transferencia_mercaderia_id',
                'a.movimientostock_id', 'a.remesa_id', 't.nombre as tipo_nombre',
            ])
            ->keyBy('id');

        $lineas = DB::table('asiento_movimiento')
            ->whereIn('asiento_id', $ids)
            ->get(['asiento_id', 'cuentacontable_id', 'centrocosto_id', 'monto']);
        $porAsiento = [];
        foreach ($lineas as $linea) {
            $porAsiento[(int) $linea->asiento_id][] = $linea;
        }

        $out = [];
        foreach ($asientoPorCuenta as $cuentaId => $asientoId) {
            $header = $headers->get($asientoId);
            $lineasAsiento = $porAsiento[$asientoId] ?? [];
            if ($header === null || $lineasAsiento === []) {
                continue;
            }
            $out[$cuentaId] = $this->textoEjemplo($header, $lineasAsiento, $cuentaId, $porId, $centrosPorId);
        }

        return $out;
    }

    /**
     * @param  list<object>  $lineas
     * @param  array<int, object>  $porId
     * @param  array<int, object>  $centrosPorId
     */
    private function textoEjemplo(object $header, array $lineas, int $cuentaId, array $porId, array $centrosPorId): string
    {
        $propias = array_values(array_filter(
            $lineas,
            static fn (object $l): bool => (int) $l->cuentacontable_id === $cuentaId,
        ));
        $monto = 0.0;
        $centroId = 0;
        foreach ($propias as $linea) {
            $monto += (float) $linea->monto;
            if ($centroId === 0 && (int) $linea->centrocosto_id > 0) {
                $centroId = (int) $linea->centrocosto_id;
            }
        }

        $fecha = $header->fecha ? date('d/m/Y', strtotime((string) $header->fecha)) : 'sin fecha';
        $numero = (string) ($header->numeroasiento ?? $header->id);
        $texto = $fecha.' · asiento '.$numero.' · '.$this->origen($header).'. ';
        $texto .= 'Esta cuenta al '.$this->lado($monto).' $ '.$this->importe(abs($monto));
        $texto .= $this->textoCentroLinea($centroId, $centrosPorId).'.';

        if (count($lineas) <= 8) {
            $detalle = [];
            foreach ($lineas as $linea) {
                $cuenta = $porId[(int) $linea->cuentacontable_id] ?? null;
                $codigo = $cuenta ? (string) $cuenta->codigo : (string) $linea->cuentacontable_id;
                $detalle[] = $this->lado((float) $linea->monto).' '.$codigo.' $ '.$this->importe(abs((float) $linea->monto));
            }
            $texto .= ' Líneas: '.implode('; ', $detalle).'.';

            return $texto;
        }

        $otras = array_values(array_filter(
            $lineas,
            static fn (object $l): bool => (int) $l->cuentacontable_id !== $cuentaId,
        ));
        usort($otras, static fn (object $a, object $b): int => abs((float) $b->monto) <=> abs((float) $a->monto));
        $detalle = [];
        foreach (array_slice($otras, 0, 3) as $linea) {
            $cuenta = $porId[(int) $linea->cuentacontable_id] ?? null;
            $codigo = $cuenta ? (string) $cuenta->codigo : (string) $linea->cuentacontable_id;
            $detalle[] = $this->lado((float) $linea->monto).' '.$codigo.' $ '.$this->importe(abs((float) $linea->monto));
        }
        $texto .= ' El asiento tiene '.count($lineas).' líneas.';
        if ($detalle !== []) {
            $texto .= ' Contrapartidas de mayor importe: '.implode('; ', $detalle).'.';
        }

        return $texto;
    }

    private function origen(object $header): string
    {
        $mapa = [
            'comprobante_proveedor_id' => 'Factura de proveedor',
            'pagoproveedor_id' => 'Pago a proveedor',
            'recepcionproveedor_id' => 'Recepción de proveedores',
            'ordencompra_id' => 'Orden de compra',
            'compra_id' => 'Compras',
            'venta_id' => 'Facturación',
            'cobranza_id' => 'Cobranza',
            'caja_movimiento_id' => 'Caja',
            'remesa_id' => 'Remesa',
            'liquidacion_sueldos_id' => 'Liquidación de sueldos',
            'jornada_gastronomia_id' => 'Cierre de jornada',
            'rendicion_estacionamiento_caja_id' => 'Cierre de estacionamiento',
            'solicitudpago_id' => 'Solicitud de pago',
            'transferencia_mercaderia_id' => 'Transferencia de stock',
            'movimientostock_id' => 'Movimiento de stock',
        ];
        foreach ($mapa as $campo => $nombre) {
            if ((int) ($header->{$campo} ?? 0) > 0) {
                return $nombre;
            }
        }
        $tipo = trim((string) ($header->tipo_nombre ?? ''));

        return $tipo !== '' ? 'Asiento '.$tipo : 'Asiento manual';
    }

    private function lado(float $monto): string
    {
        return $monto < 0 ? 'Haber' : 'Debe';
    }

    private function importe(float $monto): string
    {
        return number_format($monto, 2, ',', '.');
    }

    /**
     * @param  array<int, object>  $centrosPorId
     */
    private function textoCentroLinea(int $centroId, array $centrosPorId): string
    {
        if ($centroId <= 0) {
            return '';
        }
        $centro = $centrosPorId[$centroId] ?? null;
        if ($centro === null) {
            return ', centro '.$centroId;
        }

        return ', centro '.(string) $centro->codigo.' '.trim((string) $centro->nombre);
    }
}
