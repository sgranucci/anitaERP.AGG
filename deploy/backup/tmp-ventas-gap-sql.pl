#!/usr/bin/env perl
use strict;
use warnings;

print "CREATE TEMPORARY TABLE IF NOT EXISTS tmp_ventas_gap (
  venta_id BIGINT PRIMARY KEY,
  fecha VARCHAR(20),
  fechajornada VARCHAR(20),
  puntoventa_id BIGINT,
  numerocomprobante BIGINT,
  total DECIMAL(20,6),
  usuario_id BIGINT,
  leyenda VARCHAR(255)
);\n";
print "TRUNCATE tmp_ventas_gap;\n";

my $header = <>;
while (my $line = <>) {
    chomp $line;
    my @f = split /\t/, $line, -1;
    next unless @f >= 8;
    my ($id, $fecha, $fj, $pv, $num, $tot, $usr, $ley) = @f[0..7];
    $ley =~ s/'/\\'/g;
    print "INSERT INTO tmp_ventas_gap VALUES ($id,'$fecha','$fj',$pv,$num,$tot," . ($usr eq '' ? 'NULL' : $usr) . ",'$ley');\n";
}

print "SELECT COUNT(*) total_gap FROM tmp_ventas_gap;\n";
print "SELECT COUNT(*) faltan_por_id FROM tmp_ventas_gap g LEFT JOIN venta v ON v.id=g.venta_id WHERE v.id IS NULL;\n";
print "SELECT COUNT(*) conflicto_mismo_id FROM tmp_ventas_gap g JOIN venta v ON v.id=g.venta_id WHERE v.numerocomprobante<>g.numerocomprobante OR v.puntoventa_id<>g.puntoventa_id OR ABS(v.total-g.total)>0.01;\n";
print "SELECT COUNT(*) recuperables_por_clave FROM tmp_ventas_gap g LEFT JOIN venta v ON v.fecha=REPLACE(g.fecha,':','-') AND v.puntoventa_id=g.puntoventa_id AND v.numerocomprobante=g.numerocomprobante WHERE v.id IS NULL;\n";
