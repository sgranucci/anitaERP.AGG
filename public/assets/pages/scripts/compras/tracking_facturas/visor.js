/**
 * @deprecated El panel vive en includes/erp-workspace-panel.js
 * Se mantiene el archivo por si alguna vista vieja lo referencia.
 */
(function () {
    'use strict';
    if (!document.querySelector('script[src*="erp-workspace-panel.js"]')) {
        var s = document.createElement('script');
        s.src = (window.carpetaBase || '') + '/assets/pages/scripts/includes/erp-workspace-panel.js';
        document.head.appendChild(s);
    }
}());
