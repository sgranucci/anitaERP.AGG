INFORMIXSERVER=afncadmin
LD_LIBRARY_PATH=$LD_LIBRARY_PATH:$INFORMIXDIR/lib:$INFORMIXDIR/lib/esql
export LD_ASSUME_KERNEL=2.4.19

/home/informix/bin/isql -s $*

