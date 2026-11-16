<?php

namespace App\Services\Contable;

use App\Models\Caja\Cuentacaja;
use App\Services\Caja\InterbankingMovimientoPersistenciaService;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaCamtMt940ParserSupport;
use App\Support\Contable\ConciliacionBancaria\ConciliacionBancariaExtractoSupport;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Importa extractos bancarios a interbanking_movimiento.
 * Soporta: Excel Contaduría (EXTRACTO), CSV genérico, OFX básico.
 */
class ConciliacionBancariaExtractoArchivoImportService
{
    public function __construct(
        private readonly ConciliacionBancariaExtractoExcelImportService $excelImport,
        private readonly InterbankingMovimientoPersistenciaService $persistencia,
    ) {}

    /**
     * @return array{
     *   ok: bool,
     *   formato: string,
     *   filas: int,
     *   account_number: string,
     *   cuentacaja_id: int,
     *   cuenta_interbanking_asignada: bool,
     *   archivo: string,
     *   error: ?string
     * }
     */
    public function importar(
        string $ruta,
        int $empresaId,
        int $cuentacajaId,
        bool $asignarCuentaSiVacio = true,
    ): array {
        if (! is_file($ruta)) {
            throw new RuntimeException('No se encontró el archivo de extracto.');
        }

        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        if (in_array($ext, ['xlsx', 'xls'], true)) {
            $res = $this->excelImport->importar(
                $ruta,
                $empresaId,
                $cuentacajaId,
                null,
                $asignarCuentaSiVacio,
                true,
            );

            return [
                'ok' => (bool) ($res['ok'] ?? false),
                'formato' => 'excel_contaduria',
                'filas' => (int) ($res['filas'] ?? 0),
                'account_number' => (string) ($res['account_number'] ?? ''),
                'cuentacaja_id' => (int) ($res['cuentacaja_id'] ?? $cuentacajaId),
                'cuenta_interbanking_asignada' => (bool) ($res['cuenta_interbanking_asignada'] ?? false),
                'archivo' => (string) ($res['archivo'] ?? basename($ruta)),
                'extracto_id' => isset($res['extracto_id']) ? (int) $res['extracto_id'] : null,
                'extracto_duplicado' => (bool) ($res['extracto_duplicado'] ?? false),
                'error' => $res['error'] ?? null,
            ];
        }

        if ($ext === 'ofx' || $ext === 'qfx') {
            return $this->importarOfx($ruta, $empresaId, $cuentacajaId, $asignarCuentaSiVacio);
        }

        if ($ext === 'csv' || $ext === 'txt') {
            return $this->importarCsv($ruta, $empresaId, $cuentacajaId, $asignarCuentaSiVacio);
        }

        if ($ext === 'xml' || $ext === 'camt') {
            return $this->importarCamt($ruta, $empresaId, $cuentacajaId, $asignarCuentaSiVacio);
        }

        if (in_array($ext, ['sta', 'mt940', '940', 'swi'], true)
            || str_contains(strtolower(basename($ruta)), 'mt940')
        ) {
            return $this->importarMt940($ruta, $empresaId, $cuentacajaId, $asignarCuentaSiVacio);
        }

        throw new RuntimeException('Formato no soportado. Usá Excel, CSV, OFX, CAMT.053 (.xml) o MT940 (.sta).');
    }

    /**
     * CSV esperado (cabecera flexible): fecha, importe, debito_credito|D/C, concepto, comprobante
     * Importe negativo = débito si no hay columna D/C.
     *
     * @return array<string, mixed>
     */
    private function importarCsv(string $ruta, int $empresaId, int $cuentacajaId, bool $asignar): array
    {
        $cuenta = $this->resolverCuenta($empresaId, $cuentacajaId);
        $accountNumber = $this->resolverAccountNumber($cuenta, $asignar);

        $fh = fopen($ruta, 'rb');
        if ($fh === false) {
            throw new RuntimeException('No se pudo abrir el CSV.');
        }

        $primera = fgets($fh);
        if ($primera === false) {
            fclose($fh);
            throw new RuntimeException('El CSV está vacío.');
        }
        $delim = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        rewind($fh);

        $header = fgetcsv($fh, 0, $delim);
        if (! is_array($header) || $header === []) {
            fclose($fh);
            throw new RuntimeException('El CSV no tiene cabecera.');
        }
        $map = $this->mapaColumnasCsv($header);
        if (! isset($map['fecha']) || ! isset($map['importe'])) {
            fclose($fh);
            throw new RuntimeException('El CSV necesita columnas fecha e importe (o monto/amount).');
        }

        $movimientos = [];
        while (($row = fgetcsv($fh, 0, $delim)) !== false) {
            if (! is_array($row) || $this->filaCsvVacia($row)) {
                continue;
            }
            $fecha = $this->normalizarFechaCsv((string) ($row[$map['fecha']] ?? ''));
            if ($fecha === null) {
                continue;
            }
            $importeRaw = $this->parseImporteCsv((string) ($row[$map['importe']] ?? ''));
            if ($importeRaw === null) {
                continue;
            }
            $dc = isset($map['dc']) ? strtoupper(substr(trim((string) ($row[$map['dc']] ?? '')), 0, 1)) : '';
            if ($dc !== 'D' && $dc !== 'C') {
                $dc = $importeRaw < 0 ? 'D' : 'C';
            }
            $concepto = isset($map['concepto']) ? trim((string) ($row[$map['concepto']] ?? '')) : '';
            $voucher = isset($map['comprobante']) ? trim((string) ($row[$map['comprobante']] ?? '')) : '';

            $movimientos[] = [
                'process_date' => $fecha,
                'debit_credit_type' => $dc,
                'amount' => abs($importeRaw),
                'code_description_ib' => $concepto !== '' ? mb_substr($concepto, 0, 80) : 'CSV',
                'code_description_bank' => $concepto !== '' ? mb_substr($concepto, 0, 120) : 'Import CSV',
                'voucher_number' => $voucher !== '' ? mb_substr($voucher, 0, 40) : null,
                'operation_code_ib' => 'CSV',
                'grouping_code_ib' => null,
            ];
        }
        fclose($fh);

        return $this->persistirYRegistrarExtracto(
            $ruta,
            $empresaId,
            (int) $cuenta->id,
            $accountNumber,
            'csv',
            'csv_banco',
            $movimientos,
            $asignar && trim((string) ($cuenta->cuenta_interbanking ?? '')) === $accountNumber,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function importarOfx(string $ruta, int $empresaId, int $cuentacajaId, bool $asignar): array
    {
        $cuenta = $this->resolverCuenta($empresaId, $cuentacajaId);
        $accountNumber = $this->resolverAccountNumber($cuenta, $asignar);
        $xml = @file_get_contents($ruta);
        if ($xml === false || trim($xml) === '') {
            throw new RuntimeException('No se pudo leer el OFX.');
        }

        // OFX suelto: envolver tags SGML → XML aproximado.
        $xmlNorm = preg_replace('/&(?!amp;|lt;|gt;|quot;|apos;)/', '&amp;', $xml) ?? $xml;
        $xmlNorm = preg_replace('/<(\w+)>([^<]*)\r?\n/', '<$1>$2</$1>'."\n", $xmlNorm) ?? $xmlNorm;
        $prev = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xmlNorm);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if ($doc === false) {
            throw new RuntimeException('OFX inválido o no soportado. Probá exportar CSV del banco.');
        }

        $movimientos = [];
        $nodos = $doc->xpath('//STMTTRN') ?: $doc->xpath('//*[local-name()="STMTTRN"]') ?: [];
        foreach ($nodos as $trn) {
            $fechaRaw = (string) ($trn->DTPOSTED ?? $trn->DTUSER ?? '');
            $fecha = $this->normalizarFechaOfx($fechaRaw);
            if ($fecha === null) {
                continue;
            }
            $importe = (float) str_replace(',', '.', (string) ($trn->TRNAMT ?? '0'));
            if (abs($importe) < 0.00001) {
                continue;
            }
            $memo = trim((string) ($trn->MEMO ?? $trn->NAME ?? 'OFX'));
            $fitid = trim((string) ($trn->FITID ?? $trn->CHECKNUM ?? ''));
            $movimientos[] = [
                'process_date' => $fecha,
                'debit_credit_type' => $importe < 0 ? 'D' : 'C',
                'amount' => abs($importe),
                'code_description_ib' => mb_substr($memo, 0, 80),
                'code_description_bank' => mb_substr($memo, 0, 120),
                'voucher_number' => $fitid !== '' ? mb_substr($fitid, 0, 40) : null,
                'operation_code_ib' => 'OFX',
                'grouping_code_ib' => null,
            ];
        }

        if ($movimientos === []) {
            throw new RuntimeException('El OFX no tiene movimientos STMTTRN legibles.');
        }

        return $this->persistirYRegistrarExtracto(
            $ruta,
            $empresaId,
            (int) $cuenta->id,
            $accountNumber,
            'ofx',
            'ofx_banco',
            $movimientos,
            false,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return array<string, mixed>
     */
    private function persistirYRegistrarExtracto(
        string $ruta,
        int $empresaId,
        int $cuentacajaId,
        string $accountNumber,
        string $formatoUi,
        string $formatoPersist,
        array $movimientos,
        bool $cuentaAsignada,
    ): array {
        $reg = ConciliacionBancariaExtractoSupport::registrarDesdeImportacion(
            $empresaId,
            $cuentacajaId,
            $accountNumber,
            $formatoUi,
            basename($ruta),
            $ruta,
            $movimientos,
            Auth::id() ? (int) Auth::id() : null,
        );

        if ($reg['omitido_duplicado']) {
            return [
                'ok' => true,
                'formato' => $formatoUi,
                'filas' => 0,
                'account_number' => $accountNumber,
                'cuentacaja_id' => $cuentacajaId,
                'cuenta_interbanking_asignada' => $cuentaAsignada,
                'archivo' => basename($ruta),
                'extracto_id' => (int) $reg['extracto']->id,
                'extracto_duplicado' => true,
                'error' => null,
            ];
        }

        $filas = $this->persistencia->persistirLote(
            $empresaId,
            '0',
            $accountNumber,
            'CC',
            'ARS',
            $formatoPersist,
            $movimientos,
            now(),
            true,
        );

        return [
            'ok' => true,
            'formato' => $formatoUi,
            'filas' => $filas,
            'account_number' => $accountNumber,
            'cuentacaja_id' => $cuentacajaId,
            'cuenta_interbanking_asignada' => $cuentaAsignada,
            'archivo' => basename($ruta),
            'extracto_id' => (int) $reg['extracto']->id,
            'extracto_duplicado' => false,
            'error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function importarCamt(string $ruta, int $empresaId, int $cuentacajaId, bool $asignar): array
    {
        $cuenta = $this->resolverCuenta($empresaId, $cuentacajaId);
        $accountNumber = $this->resolverAccountNumber($cuenta, $asignar);
        $movimientos = ConciliacionBancariaCamtMt940ParserSupport::parseCamt053($ruta);

        return $this->persistirYRegistrarExtracto(
            $ruta,
            $empresaId,
            (int) $cuenta->id,
            $accountNumber,
            'camt053',
            'camt053_banco',
            $movimientos,
            false,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function importarMt940(string $ruta, int $empresaId, int $cuentacajaId, bool $asignar): array
    {
        $cuenta = $this->resolverCuenta($empresaId, $cuentacajaId);
        $accountNumber = $this->resolverAccountNumber($cuenta, $asignar);
        $movimientos = ConciliacionBancariaCamtMt940ParserSupport::parseMt940($ruta);

        return $this->persistirYRegistrarExtracto(
            $ruta,
            $empresaId,
            (int) $cuenta->id,
            $accountNumber,
            'mt940',
            'mt940_banco',
            $movimientos,
            false,
        );
    }

    private function resolverCuenta(int $empresaId, int $cuentacajaId): Cuentacaja
    {
        $cuenta = Cuentacaja::query()->find($cuentacajaId);
        if (! $cuenta || ! $cuenta->perteneceAEmpresa($empresaId)) {
            throw new RuntimeException('La cuenta de caja no pertenece a la empresa indicada.');
        }

        return $cuenta;
    }

    private function resolverAccountNumber(Cuentacaja $cuenta, bool $asignar): string
    {
        $ib = trim((string) ($cuenta->cuenta_interbanking ?? ''));
        if ($ib !== '') {
            return $ib;
        }
        $sintetica = ConciliacionBancariaService::cuentaExtractoExterno((string) ($cuenta->codigo ?? ''));
        if ($asignar) {
            $cuenta->cuenta_interbanking = $sintetica;
            $cuenta->save();
        }

        return $sintetica;
    }

    /**
     * @param  list<string|null>  $header
     * @return array{fecha?: int, importe?: int, dc?: int, concepto?: int, comprobante?: int}
     */
    private function mapaColumnasCsv(array $header): array
    {
        $map = [];
        foreach ($header as $i => $col) {
            $n = $this->normHeader((string) $col);
            if ($n === '') {
                continue;
            }
            if (! isset($map['fecha']) && preg_match('/fecha|date|f\.?valor|process/', $n)) {
                $map['fecha'] = (int) $i;
            } elseif (! isset($map['importe']) && preg_match('/importe|monto|amount|valor|importe/', $n)) {
                $map['importe'] = (int) $i;
            } elseif (! isset($map['dc']) && preg_match('/^(d\/?c|debito|crédito|credito|tipo|drcr)$/', $n)) {
                $map['dc'] = (int) $i;
            } elseif (! isset($map['concepto']) && preg_match('/concepto|detalle|descrip|memo|narration|referencia/', $n)) {
                $map['concepto'] = (int) $i;
            } elseif (! isset($map['comprobante']) && preg_match('/comprob|voucher|nro|numero|cheque|fitid/', $n)) {
                $map['comprobante'] = (int) $i;
            }
        }

        return $map;
    }

    private function normHeader(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/', '', $s) ?? $s;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function filaCsvVacia(array $row): bool
    {
        foreach ($row as $v) {
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizarFechaCsv(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $raw, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{2,4})$/', $raw, $m)) {
            $y = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return sprintf('%04d-%02d-%02d', (int) $y, (int) $m[2], (int) $m[1]);
        }
        try {
            return \Carbon\Carbon::parse($raw)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizarFechaOfx(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $raw, $m)) {
            return $m[1].'-'.$m[2].'-'.$m[3];
        }

        return $this->normalizarFechaCsv($raw);
    }

    private function parseImporteCsv(string $raw): ?float
    {
        $s = trim(str_replace(['$', ' '], '', $raw));
        if ($s === '') {
            return null;
        }
        $neg = str_starts_with($s, '-') || str_starts_with($s, '(');
        $s = str_replace(['(', ')'], '', $s);
        if (str_starts_with($s, '+') || str_starts_with($s, '-')) {
            $s = substr($s, 1);
        }
        $coma = strrpos($s, ',');
        $punto = strrpos($s, '.');
        if ($coma !== false && $punto !== false) {
            $s = $coma > $punto ? str_replace('.', '', $s) : str_replace(',', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($coma !== false) {
            $s = str_replace(',', '.', $s);
        }
        if (! is_numeric($s)) {
            return null;
        }
        $n = (float) $s;

        return $neg ? -abs($n) : $n;
    }
}
