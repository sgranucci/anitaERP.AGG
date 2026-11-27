<?php

declare(strict_types=1);

namespace App\Mail\Ventas;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FacturacionHuecoArcaMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @var list<string> Textos de «Acción requerida», uno por clase de comprobante recuperado. */
    public readonly array $accionesRequeridas;

    /**
     * @param  array{cargados: list<array<string, mixed>>, errores: list<array<string, mixed>>, omitidos: list<array<string, mixed>>}  $informe
     */
    public function __construct(
        public readonly array $informe,
    ) {
        $cargados = count($informe['cargados'] ?? []);
        $errores = count($informe['errores'] ?? []);
        $fecha = now()->format('d/m/Y');
        if ($cargados > 0 && $errores === 0) {
            $asunto = 'Comprobantes de ARCA recuperados en el ERP — '.$fecha;
        } elseif ($cargados > 0) {
            $asunto = 'Comprobantes de ARCA recuperados, con errores — '.$fecha;
        } else {
            $asunto = 'No se pudieron cargar comprobantes autorizados en ARCA — '.$fecha;
        }

        $this->accionesRequeridas = self::accionesRequeridas($informe['cargados'] ?? []);
        $this->subject($asunto);
    }

    public function build(): self
    {
        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('mails.ventas.facturacion_hueco_arca');
    }

    /**
     * Clase del comprobante según el código AFIP: factura, nota de crédito, nota de débito u otro.
     * Los códigos 200–249 (FCE/NCE/NDE) usan la misma base que los tipos comunes.
     */
    public static function claseComprobante(int $codigoAfip): string
    {
        $base = $codigoAfip >= 200 && $codigoAfip < 250 ? $codigoAfip - 200 : $codigoAfip;

        return match (true) {
            in_array($base, [1, 6, 11, 51], true) => 'factura',
            in_array($base, [3, 8, 13, 53], true) => 'nc',
            in_array($base, [2, 7, 12, 52], true) => 'nd',
            default => 'otro',
        };
    }

    /**
     * Un texto de acción por cada clase de comprobante presente entre los cargados.
     *
     * @param  list<array<string, mixed>>  $cargados
     * @return list<string>
     */
    public static function accionesRequeridas(array $cargados): array
    {
        $presentes = [];
        foreach ($cargados as $fila) {
            $presentes[self::claseComprobante((int) ($fila['codigo_afip'] ?? 0))] = true;
        }

        $salida = [];
        foreach (['factura', 'nc', 'nd', 'otro'] as $clase) {
            if (isset($presentes[$clase])) {
                $salida[] = self::textoAccion($clase);
            }
        }

        return $salida;
    }

    public static function textoAccion(string $clase): string
    {
        return match ($clase) {
            'factura' => 'si esa misma venta se reemitió con otro número (o el CAE quedó huérfano porque falló el cobro), '
                .'hay que emitir la nota de crédito contra el comprobante recuperado en ARCA y en el ERP, para anularlo. '
                .'No es una nota de débito.',
            'nc' => 'esta es una nota de crédito, así que no se anula con otra nota de crédito. Revisá la factura que indica '
                .'la columna Asociado. Si esa factura ya quedó anulada con otra nota de crédito, esta es duplicada: hay que '
                .'emitir una nota de débito contra ella en ARCA y en el ERP para revertirla. Si no es duplicada, no hay que '
                .'hacer nada: ya quedó registrada en el ERP.',
            'nd' => 'si esta nota de débito es duplicada, hay que emitir una nota de crédito contra ella en ARCA y en el ERP '
                .'para anularla. Si no lo es, no hay que hacer nada.',
            default => 'revisá el comprobante con facturación antes de emitir nada. Si está duplicado, se anula con el '
                .'comprobante opuesto: nota de crédito para facturas y notas de débito, nota de débito para notas de crédito.',
        };
    }
}
