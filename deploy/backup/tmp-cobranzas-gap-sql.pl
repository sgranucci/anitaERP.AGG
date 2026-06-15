#!/usr/bin/env perl
use strict;
use warnings;
print "CREATE TEMPORARY TABLE tmp_cob_gap (cobranza_id BIGINT PRIMARY KEY, fecha VARCHAR(20), monto DECIMAL(22,4), venta_id BIGINT, cliente_id BIGINT, detalle VARCHAR(255));\n";
print "TRUNCATE tmp_cob_gap;\n";
my $h = <>;
while (<>) {
    chomp; my @f = split /\t/; next unless @f>=6;
    my ($id,$fecha,$monto,$vid,$cli,$det)=@f[0..5];
    $det =~ s/'/\\'/g;
    print "INSERT INTO tmp_cob_gap VALUES ($id,'$fecha',$monto," . ($vid eq ''?'NULL':$vid) . "," . ($cli eq ''?'NULL':$cli) . ",'$det');\n";
}
print "SELECT COUNT(*) total_gap FROM tmp_cob_gap;\n";
print "SELECT COUNT(*) faltan FROM tmp_cob_gap g LEFT JOIN cobranza c ON c.id=g.cobranza_id WHERE c.id IS NULL;\n";
print "SELECT COUNT(*) difieren FROM tmp_cob_gap g JOIN cobranza c ON c.id=g.cobranza_id WHERE ABS(c.monto-g.monto)>0.01 OR IFNULL(c.venta_id,0)<>IFNULL(g.venta_id,0);\n";
