curl -s https://www.bna.com.ar/Personas | grep -A 2 'Dolar U.S.A' | head -3  | tail  -1 | sed  's/<//g' | sed 's/>//g' | sed 's/td//g' | sed 's/,/./g' |  sed 's/ //g' | rev| cut -b 3-|rev
