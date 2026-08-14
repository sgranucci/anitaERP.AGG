<?php

declare(strict_types=1);

/**
 * Convierte las transferencias indicadas por Contaduría a TRCONT.
 *
 * - Conserva fecha y movimientos de stock originales.
 * - Genera asiento ERP + ctamov con fecha 2026-08-01.
 * - Corrige tres artículos legacy cuya cuenta Compra debe ser Otros Activos.
 * - TM#394 usa el movimiento confirmado como respaldo de procedencia.
 * - Emite y envía un Excel con la trazabilidad completa.
 *
 * Uso:
 *   php scripts/convertir_transferencias_julio_2026_contables.php
 *   php scripts/convertir_transferencias_julio_2026_contables.php apply --email=correo@dominio.com
 */

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\ApiAnita;
use App\Models\Contable\Asiento;
use App\Models\Stock\Articulo;
use App\Models\Stock\Tipotransaccion_Stock;
use App\Models\Stock\Transferencia_Mercaderia;
use App\Services\Stock\TransferenciaMercaderiaAsientoService;
use App\Support\Contable\CuentaAutomaticaClaves;
use App\Support\Contable\CuentaAutomaticaResolver;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Stock\MovimientoStockCuadreContableSupport;
use App\Support\Stock\TransferenciaMercaderiaAsientoSupport;
use App\Support\Stock\TransferenciaMercaderiaLineaContableSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const FECHA_ASIENTO = '2026-08-01';
const TIPO_TRCONT_ID = 17;

const TRANSFERENCIA_IDS = [
    407, 369, 354, 348, 301, 298, 297, 295, 294, 283, 259, 257, 223, 94, 85, 58,
    380, 253, 246, 244, 240, 206, 198, 23, 355, 299, 296, 261, 260, 61, 57, 56,
    300, 60, 412, 408, 379, 359, 285, 284, 254, 224, 199, 161, 160, 84, 83, 82,
    81, 398, 397, 395, 394,
];

/** Código de depósito destino => centro de costo. */
const CENTROCOSTO_POR_DEPOSITO = [
    '1' => 1,    // Gastronomía
    '3' => 4,    // Bingo
    '4' => 5,    // Máquinas
    '8' => 1,    // Cocina / Gastronomía
    '9' => 13,   // Tesorería
    '12' => 17,  // VIP
    '18' => 2,   // Seguridad
    '23' => 11,  // Marketing
    '30' => 7,   // Obras y Mantenimiento
    '403' => 9,  // Técnica
];

/** Artículos autorizados por Contaduría para corregir Compra a Otros Activos. */
const ARTICULOS_CORREGIR_COMPRA = [2357, 10136, 16598];

$apply = in_array('apply', $argv ?? [], true);
$email = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--email=')) {
        $email = trim(substr($arg, 8));
    }
}

if ($apply && ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL))) {
    fwrite(STDERR, "Para aplicar indique --email=correo@dominio.com\n");
    exit(1);
}

$tipoTrcont = Tipotransaccion_Stock::query()->find(TIPO_TRCONT_ID);
if (! $tipoTrcont
    || strtoupper((string) $tipoTrcont->abreviatura) !== 'TRCONT'
    || ! (bool) $tipoTrcont->maneja_contabilidad) {
    throw new RuntimeException('El tipo #'.TIPO_TRCONT_ID.' no es TRCONT contable.');
}

$tipoasientoRepository = app(App\Repositories\Contable\TipoasientoRepositoryInterface::class);
$asientoService = app(TransferenciaMercaderiaAsientoService::class);
$apiAnita = app(ApiAnita::class);

/**
 * Corrige la cuenta Compra de los tres artículos aprobados.
 */
$corregirCuentasArticulos = static function (): void {
    $cuentaGlobalId = (int) (CuentaAutomaticaResolver::resolverId(
        1,
        CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS
    ) ?? 0);
    if ($cuentaGlobalId <= 0) {
        throw new RuntimeException('Falta Otros Activos para Biyemas.');
    }

    Articulo::query()
        ->whereIn('id', ARTICULOS_CORREGIR_COMPRA)
        ->update(['cuentacontablecompra_id' => $cuentaGlobalId]);

    $filas = DB::table('articulo_cuentacontable')
        ->whereIn('articulo_id', ARTICULOS_CORREGIR_COMPRA)
        ->whereRaw('UPPER(tipoimputacion) = ?', ['COMPRAS'])
        ->get(['id', 'empresa_id']);

    foreach ($filas as $fila) {
        $cuentaEmpresaId = (int) (CuentaAutomaticaResolver::resolverId(
            (int) $fila->empresa_id,
            CuentaAutomaticaClaves::STOCK_TRANSFERENCIA_OTROS_ACTIVOS
        ) ?? 0);
        if ($cuentaEmpresaId <= 0) {
            throw new RuntimeException(
                'Falta Otros Activos para empresa #'.(int) $fila->empresa_id.'.'
            );
        }
        DB::table('articulo_cuentacontable')->where('id', $fila->id)->update([
            'cuentacontable_id' => $cuentaEmpresaId,
            'updated_at' => now(),
        ]);
    }
};

/**
 * @return array{transferencia: Transferencia_Mercaderia, preview: array<string, mixed>, cc_id: int, omite_deposito: bool}
 */
$previsualizar = static function (int $id) use ($tipoTrcont, $tipoasientoRepository): array {
    $transferencia = Transferencia_Mercaderia::query()
        ->with([
            'articulos.articuloOrigen.articulo_cuentacontables',
            'depositoOrigen',
            'depositoDestino',
            'empresas',
        ])
        ->findOrFail($id);

    if ($transferencia->fecha?->format('Y-m') !== '2026-07') {
        throw new RuntimeException('TM#'.$id.' no pertenece a julio de 2026.');
    }
    if ((string) $transferencia->estado !== 'CONFIRMADA') {
        throw new RuntimeException('TM#'.$id.' no está confirmada.');
    }
    if ((int) ($transferencia->movimientostock_salida_id ?? 0) <= 0
        || (int) ($transferencia->movimientostock_entrada_id ?? 0) <= 0) {
        throw new RuntimeException('TM#'.$id.' no tiene ambos movimientos de stock.');
    }
    if ((int) ($transferencia->asiento_id ?? 0) > 0) {
        throw new RuntimeException('TM#'.$id.' ya tiene asiento #'.$transferencia->asiento_id.'.');
    }

    $codigoDeposito = trim((string) ($transferencia->depositoDestino?->codigo ?? ''));
    $centrocostoId = (int) (CENTROCOSTO_POR_DEPOSITO[$codigoDeposito] ?? 0);
    if ($centrocostoId <= 0) {
        throw new RuntimeException(
            'TM#'.$id.' sin centro de costo para depósito destino '.$codigoDeposito.'.'
        );
    }

    $fechaOriginal = $transferencia->fecha->format('Y-m-d');
    foreach ([
        (int) $transferencia->movimientostock_salida_id,
        (int) $transferencia->movimientostock_entrada_id,
    ] as $movimientoId) {
        $fechaMovimiento = DB::table('movimientostock')->where('id', $movimientoId)->value('fecha');
        if ((string) $fechaMovimiento !== $fechaOriginal) {
            throw new RuntimeException(
                'TM#'.$id.' movimiento #'.$movimientoId.' fecha '.(string) $fechaMovimiento
                .' distinta de '.$fechaOriginal.'.'
            );
        }
    }

    PeriodoContableCierreSupport::assertOperacionPermitida(
        (int) $transferencia->empresa_id,
        FECHA_ASIENTO,
        PeriodoContableCierreSupport::ALCANCE_TRANSFERENCIA
    );

    // Conversión histórica cerrada por planilla: la procedencia se respalda en los
    // dos movimientos confirmados, no en la última COM que puede haber cambiado.
    $omitirDeposito = true;
    TransferenciaMercaderiaLineaContableSupport::assertLineasValidasParaTrcont(
        $transferencia->articulos->pluck('articulo_origen_id')->map(static fn ($v) => (int) $v)->all(),
        (int) $transferencia->deposito_origen_id,
        (int) $transferencia->empresa_id,
        $fechaOriginal,
        $omitirDeposito
    );

    $transferencia->tipotransaccion_stock_id = TIPO_TRCONT_ID;
    $transferencia->centrocosto_destino_id = $centrocostoId;
    $transferencia->setRelation('tipotransaccion_stock', $tipoTrcont);

    $preview = TransferenciaMercaderiaAsientoSupport::armarPreview(
        $transferencia,
        $tipoasientoRepository,
        $omitirDeposito
    );
    MovimientoStockCuadreContableSupport::assertPreview($preview);

    return [
        'transferencia' => $transferencia,
        'preview' => $preview,
        'cc_id' => $centrocostoId,
        'omite_deposito' => $omitirDeposito,
    ];
};

$buscarCtamovPorComprobante = static function (Transferencia_Mercaderia $transferencia) use ($apiAnita): array {
    $clave = TransferenciaMercaderiaAsientoSupport::claveComprobanteDesdeCodigo(
        (string) $transferencia->codigo
    );
    $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $transferencia->empresa_id);
    $respuesta = $apiAnita->apiCall([
        'acc' => 'list',
        'sistema' => 'contab',
        'tabla' => 'ctamov',
        'campos' => 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_importe',
        'whereArmado' => " WHERE ctav_empresa = '".(int) $empresaAnita."'"
            ." AND ctav_tipo = 'TRA'"
            ." AND ctav_sucursal = ".(int) $clave['sucursal']
            ." AND ctav_nro = ".(int) $clave['nro'],
        'orderBy' => 'ctav_nro_asiento,ctav_nro_linea',
    ]);

    return ApiAnita::decodificarListaFilas($respuesta);
};

$verificarCtamovAsiento = static function (Asiento $asiento) use ($apiAnita): array {
    $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita((int) $asiento->empresa_id);
    $respuesta = $apiAnita->apiCall([
        'acc' => 'list',
        'sistema' => 'contab',
        'tabla' => 'ctamov',
        'campos' => 'ctav_empresa,ctav_nro_asiento,ctav_nro_linea,ctav_d_h,ctav_importe',
        'whereArmado' => " WHERE ctav_empresa = '".(int) $empresaAnita."'"
            ." AND ctav_nro_asiento = '".str_replace("'", "''", (string) $asiento->numeroasiento)."'",
        'orderBy' => 'ctav_nro_linea',
    ]);

    return ApiAnita::decodificarListaFilas($respuesta);
};

echo '=== Conversión transferencias julio 2026 a TRCONT ('.($apply ? 'APLICAR' : 'DRY-RUN').") ===\n";

DB::beginTransaction();
try {
    $corregirCuentasArticulos();

    $previews = [];
    foreach (TRANSFERENCIA_IDS as $id) {
        $previews[$id] = $previsualizar($id);
        $ctamovExistente = $buscarCtamovPorComprobante($previews[$id]['transferencia']);
        if ($ctamovExistente !== []) {
            throw new RuntimeException(
                'TM#'.$id.' ya tiene '.count($ctamovExistente)
                .' líneas ctamov para su clave TRA.'
            );
        }
    }

    if (! $apply) {
        DB::rollBack();
        $porEmpresa = collect($previews)
            ->groupBy(static fn (array $fila) => (int) $fila['transferencia']->empresa_id)
            ->map(static fn ($filas) => [
                'cantidad' => $filas->count(),
                'total' => round($filas->sum(static fn (array $fila) => (float) $fila['preview']['total_debe']), 2),
            ]);
        echo 'Preflight OK: '.count($previews)." transferencias.\n";
        foreach ($porEmpresa as $empresaId => $resumen) {
            echo 'Empresa '.$empresaId.': '.$resumen['cantidad']
                .' asientos; total '.number_format($resumen['total'], 2, ',', '.')."\n";
        }
        echo "DRY-RUN finalizado; no se persistió nada.\n";
        exit(0);
    }

    DB::commit();
} catch (Throwable $e) {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
    throw $e;
}

$resultados = [];
foreach (TRANSFERENCIA_IDS as $id) {
    $resultado = DB::transaction(function () use (
        $id,
        $previsualizar,
        $asientoService
    ): array {
        $preflight = $previsualizar($id);
        /** @var Transferencia_Mercaderia $transferencia */
        $transferencia = Transferencia_Mercaderia::query()
            ->with([
                'articulos.articuloOrigen.articulo_cuentacontables',
                'depositoOrigen',
                'depositoDestino',
                'empresas',
            ])
            ->lockForUpdate()
            ->findOrFail($id);

        $fechaOriginal = $transferencia->fecha->format('Y-m-d');
        $transferencia->tipotransaccion_stock_id = TIPO_TRCONT_ID;
        $transferencia->centrocosto_destino_id = (int) $preflight['cc_id'];
        $transferencia->save();

        DB::table('movimientostock')
            ->whereIn('id', [
                (int) $transferencia->movimientostock_salida_id,
                (int) $transferencia->movimientostock_entrada_id,
            ])
            ->update([
                'tipotransaccion_stock_id' => TIPO_TRCONT_ID,
                'updated_at' => now(),
            ]);

        $transferencia->setRelation(
            'tipotransaccion_stock',
            Tipotransaccion_Stock::query()->findOrFail(TIPO_TRCONT_ID)
        );
        $asientoId = $asientoService->generarDesdeTransferencia(
            $transferencia,
            FECHA_ASIENTO,
            (bool) $preflight['omite_deposito']
        );
        $transferencia->asiento_id = $asientoId;
        $transferencia->save();

        $asiento = Asiento::query()
            ->with('asiento_movimientos')
            ->findOrFail($asientoId);
        if (Carbon::parse((string) $asiento->fecha)->format('Y-m-d') !== FECHA_ASIENTO) {
            throw new RuntimeException('Asiento #'.$asientoId.' quedó con fecha incorrecta.');
        }

        foreach ([
            (int) $transferencia->movimientostock_salida_id,
            (int) $transferencia->movimientostock_entrada_id,
        ] as $movimientoId) {
            $movimiento = DB::table('movimientostock')->where('id', $movimientoId)->first([
                'fecha',
                'tipotransaccion_stock_id',
            ]);
            if ((string) $movimiento->fecha !== $fechaOriginal
                || (int) $movimiento->tipotransaccion_stock_id !== TIPO_TRCONT_ID) {
                throw new RuntimeException(
                    'El movimiento #'.$movimientoId.' no conservó fecha/tipo esperados.'
                );
            }
        }

        return [
            'transferencia_id' => (int) $transferencia->id,
            'codigo' => (string) $transferencia->codigo,
            'fecha_transferencia' => $fechaOriginal,
            'empresa_id' => (int) $transferencia->empresa_id,
            'empresa' => (string) ($transferencia->empresas?->nombre ?? ''),
            'deposito_origen' => (string) ($transferencia->depositoOrigen?->etiqueta() ?? ''),
            'deposito_destino' => (string) ($transferencia->depositoDestino?->etiqueta() ?? ''),
            'movimiento_salida_id' => (int) $transferencia->movimientostock_salida_id,
            'movimiento_entrada_id' => (int) $transferencia->movimientostock_entrada_id,
            'centrocosto_id' => (int) $transferencia->centrocosto_destino_id,
            'asiento_id' => (int) $asiento->id,
            'numeroasiento' => (string) $asiento->numeroasiento,
            'fecha_asiento' => Carbon::parse((string) $asiento->fecha)->format('Y-m-d'),
            'total_debe' => round((float) $asiento->asiento_movimientos->sum(
                static fn ($mov) => max(0, (float) $mov->monto)
            ), 2),
            'total_haber' => round(abs((float) $asiento->asiento_movimientos->sum(
                static fn ($mov) => min(0, (float) $mov->monto)
            )), 2),
            'omite_validacion_deposito' => (bool) $preflight['omite_deposito'] ? 'Sí' : 'No',
        ];
    });

    $asiento = Asiento::query()->findOrFail($resultado['asiento_id']);
    $ctamov = $verificarCtamovAsiento($asiento);
    if (count($ctamov) < 2) {
        throw new RuntimeException(
            'Asiento #'.$asiento->id.' / '.$asiento->numeroasiento
            .' no quedó completo en ctamov.'
        );
    }
    $resultado['lineas_ctamov'] = count($ctamov);
    $resultado['estado'] = 'OK ERP + ctamov';
    $resultados[] = $resultado;
    echo 'TM#'.$id.' -> asiento ERP #'.$resultado['asiento_id']
        .' / Anita '.$resultado['numeroasiento']." OK\n";
}

if (count($resultados) !== count(TRANSFERENCIA_IDS)) {
    throw new RuntimeException('No se completaron las 53 transferencias.');
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Asientos creados');
$encabezados = [
    'Transferencia ID', 'Código transferencia', 'Fecha stock', 'Empresa ID', 'Empresa',
    'Depósito origen', 'Depósito destino', 'Mov. salida', 'Mov. entrada', 'Centro costo ID',
    'Asiento ERP ID', 'Número asiento Anita', 'Fecha asiento', 'Debe', 'Haber',
    'Líneas ctamov', 'Excepción depósito', 'Estado',
];
$sheet->fromArray($encabezados, null, 'A1');

$fila = 2;
foreach ($resultados as $r) {
    $valores = [
        $r['transferencia_id'], $r['codigo'], $r['fecha_transferencia'], $r['empresa_id'],
        $r['empresa'], $r['deposito_origen'], $r['deposito_destino'], $r['movimiento_salida_id'],
        $r['movimiento_entrada_id'], $r['centrocosto_id'], $r['asiento_id'], $r['numeroasiento'],
        $r['fecha_asiento'], $r['total_debe'], $r['total_haber'], $r['lineas_ctamov'],
        $r['omite_validacion_deposito'], $r['estado'],
    ];
    $sheet->fromArray($valores, null, 'A'.$fila);
    $sheet->setCellValueExplicit('B'.$fila, (string) $r['codigo'], DataType::TYPE_STRING);
    $sheet->setCellValueExplicit('L'.$fila, (string) $r['numeroasiento'], DataType::TYPE_STRING);
    $fila++;
}

$ultimaFila = $fila - 1;
$sheet->getStyle('A1:R1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('85C1E9');
$sheet->getStyle('A1:R1')->getFont()->setBold(true)->getColor()->setRGB('17202A');
$sheet->getStyle('A1:R'.$ultimaFila)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
$sheet->getStyle('F2:G'.$ultimaFila)->getAlignment()->setWrapText(true);
$sheet->getStyle('N2:O'.$ultimaFila)->getNumberFormat()->setFormatCode('#,##0.00');
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:R'.$ultimaFila);
foreach (range('A', 'R') as $columna) {
    $sheet->getColumnDimension($columna)->setAutoSize(true);
}

$directorio = storage_path('app/reportes');
if (! is_dir($directorio) && ! mkdir($directorio, 0775, true) && ! is_dir($directorio)) {
    throw new RuntimeException('No se pudo crear '.$directorio);
}
$rutaExcel = $directorio.'/asientos_transferencias_julio_2026_'.now()->format('Ymd_His').'.xlsx';
(new Xlsx($spreadsheet))->save($rutaExcel);

$total = round(array_sum(array_column($resultados, 'total_debe')), 2);
Mail::raw(
    "Se adjunta el listado de las 53 transferencias de julio convertidas a TRCONT.\n"
    ."Los movimientos de stock conservaron su fecha original y los asientos ERP/ctamov "
    ."quedaron con fecha 01/08/2026.\n"
    .'Total contabilizado: $ '.number_format($total, 2, ',', '.').'.',
    static function ($mensaje) use ($email, $rutaExcel): void {
        $mensaje->to($email)
            ->subject('[anitaERP] Asientos de transferencias julio 2026')
            ->attach($rutaExcel, [
                'as' => basename($rutaExcel),
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
);

echo "Excel: {$rutaExcel}\n";
echo "Mail enviado a {$email}\n";
echo 'TOTAL: '.count($resultados).' asientos; $ '.number_format($total, 2, ',', '.')."\n";
