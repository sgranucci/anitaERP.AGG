<?php
// Constantes de arbol de aprobacion

switch(strtoupper(config('app.empresa')))
{
    case "AGG":
        return [
                'client_id' => 'ohLciTIWzAgaNui7XbRH1wznR50PqepBYfhp',
                'client_secret' => 'QCOOkdzAzwUgLB1esv5XmDCrlG7DSrjJVoMF',
                'customer_id'   => ['X36888A', 'X36688A', 'C25656A']
            ];
        break;
}
