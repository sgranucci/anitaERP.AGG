<?php

namespace App\Support\Ventas;

/**
 * Generador mínimo de bytes ESC/POS (UTF-8 / impresoras compatibles Epson).
 */
final class EscPosTicketWriter
{
    private string $buffer = '';

    /** @var list<string> */
    private array $lineasVistaPrevia = [];

    public function __construct(
        private readonly int $ancho = 42,
        private readonly string $codificacion = 'ISO-8859-1',
    ) {
    }

    public function bytes(): string
    {
        return $this->buffer;
    }

    /** Texto legible del ticket (sin bytes ESC/POS). */
    public function textoPlanoVistaPrevia(): string
    {
        return implode("\n", $this->lineasVistaPrevia);
    }

    public function iniciar(): self
    {
        $this->buffer .= "\x1b\x40";

        return $this;
    }

    public function alinearCentro(): self
    {
        $this->buffer .= "\x1b\x61\x01";

        return $this;
    }

    public function alinearIzquierda(): self
    {
        $this->buffer .= "\x1b\x61\x00";

        return $this;
    }

    public function alinearDerecha(): self
    {
        $this->buffer .= "\x1b\x61\x02";

        return $this;
    }

    public function negrita(bool $on = true): self
    {
        $this->buffer .= $on ? "\x1b\x45\x01" : "\x1b\x45\x00";

        return $this;
    }

    /** Doble alto y ancho (GS ! 0x11). */
    public function dobleTamano(bool $on = true): self
    {
        $this->buffer .= $on ? "\x1d\x21\x11" : "\x1d\x21\x00";

        return $this;
    }

    public function texto(string $linea): self
    {
        $this->buffer .= $this->codificar($linea);

        return $this;
    }

    public function linea(string $linea = ''): self
    {
        $this->lineasVistaPrevia[] = $linea;
        $this->buffer .= $this->codificar($linea)."\n";

        return $this;
    }

    public function separador(string $caracter = '-'): self
    {
        $car = $caracter !== '' ? $caracter[0] : '-';

        return $this->linea(str_repeat($car, $this->ancho));
    }

    public function separadorDoble(): self
    {
        return $this->linea(str_repeat('=', $this->ancho));
    }

    /** Título centrado: doble tamaño + negrita. */
    public function titulo(string $texto): self
    {
        $this->alinearCentro();
        $this->negrita(true);
        $this->dobleTamano(true);
        $this->linea($this->truncar($texto, (int) floor($this->ancho / 2)));
        $this->dobleTamano(false);
        $this->negrita(false);
        $this->alinearIzquierda();

        return $this;
    }

    public function textoCentrado(string $texto): self
    {
        $this->alinearCentro()->linea($this->truncar($texto, $this->ancho))->alinearIzquierda();

        return $this;
    }

    public function textoCentradoNegrita(string $texto): self
    {
        $this->alinearCentro();
        $this->negrita(true);
        $this->linea($this->truncar($texto, $this->ancho));
        $this->negrita(false);
        $this->alinearIzquierda();

        return $this;
    }

    /**
     * Código alfanumérico del papelito Waitry — visible para el cliente en el monitor.
     */
    public function bloquePapelitoMonitor(string $codigo): self
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return $this;
        }

        $this->separador();
        $this->alinearCentro();
        $this->negrita(true);
        $this->linea('SU PEDIDO EN EL MONITOR');
        $this->negrita(false);
        $this->feed(1);
        $this->negrita(true);
        $this->dobleTamano(true);
        $this->linea($this->truncar($codigo, (int) floor($this->ancho / 2)));
        $this->dobleTamano(false);
        $this->negrita(false);
        $this->feed(1);
        $this->linea('Presente este codigo');
        $this->alinearIzquierda();
        $this->separador();

        return $this;
    }

    public function lineaConImporte(string $izquierda, string $importe): self
    {
        $der = $this->truncar($importe, 14);
        $izq = $this->truncar($izquierda, $this->ancho - mb_strlen($der, 'UTF-8') - 1);
        $espacios = $this->ancho - mb_strlen($izq, 'UTF-8') - mb_strlen($der, 'UTF-8');
        if ($espacios < 1) {
            $this->linea($izq);
            $this->alinearDerecha();
            $this->linea($der);
            $this->alinearIzquierda();

            return $this;
        }

        return $this->linea($izq.str_repeat(' ', $espacios).$der);
    }

    public function feed(int $lineas = 1): self
    {
        $this->buffer .= str_repeat("\n", max(0, $lineas));

        return $this;
    }

    public function cortar(): self
    {
        $this->buffer .= "\x1d\x56\x00";

        return $this;
    }

    /**
     * QR modelo 2 (GS ( k). $tamano 1–8 típico en Epson.
     */
    public function qr(string $datos, int $tamano = 6, string $correccion = 'M'): self
    {
        $this->lineasVistaPrevia[] = '[QR] '.$datos;

        $payload = $this->codificar($datos);
        $len = strlen($payload);

        $eccMap = ['L' => 0x30, 'M' => 0x31, 'Q' => 0x32, 'H' => 0x33];
        $ecc = $eccMap[strtoupper($correccion)] ?? 0x31;

        $this->buffer .= "\x1d\x28\x6b\x04\x00\x31\x41\x32\x00";
        $this->buffer .= "\x1d\x28\x6b\x03\x00\x31\x43".chr(max(1, min(8, $tamano)));
        $this->buffer .= "\x1d\x28\x6b\x03\x00\x31\x45".chr($ecc);

        $storeLen = $len + 3;
        $pL = $storeLen % 256;
        $pH = (int) floor($storeLen / 256);
        $this->buffer .= "\x1d\x28\x6b".$this->chr($pL).$this->chr($pH)."\x31\x50\x30".$payload;
        $this->buffer .= "\x1d\x28\x6b\x03\x00\x31\x51\x30";

        return $this;
    }

    private function truncar(string $texto, int $max): string
    {
        $texto = trim($texto);
        if (mb_strlen($texto, 'UTF-8') <= $max) {
            return $texto;
        }

        return mb_substr($texto, 0, max(0, $max - 1), 'UTF-8').'…';
    }

    private function codificar(string $texto): string
    {
        if ($this->codificacion === 'UTF-8') {
            return $texto;
        }

        $converted = @iconv('UTF-8', $this->codificacion.'//TRANSLIT//IGNORE', $texto);
        if ($converted === false) {
            return $texto;
        }

        return $converted;
    }

    private function chr(int $byte): string
    {
        return chr($byte & 0xFF);
    }
}
