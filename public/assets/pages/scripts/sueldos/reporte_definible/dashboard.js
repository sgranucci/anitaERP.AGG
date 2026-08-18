(function () {
    'use strict';

    var chart = null;

    function esperarPivot(cfg, uuid, callback) {
        var url = String(cfg.pivotEstadoUrl || '').replace('__UUID__', uuid);
        if (!url) return;
        window.setTimeout(function () {
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) {
                    return r.json().then(function (json) {
                        if (r.status === 202) {
                            esperarPivot(cfg, uuid, callback);
                            return;
                        }
                        if (!r.ok || json.estado === 'error') {
                            throw new Error(json.mensaje || 'No se pudo calcular el pivot.');
                        }
                        callback(json.data || {});
                    });
                })
                .catch(function (e) { window.alert(e.message); });
        }, 1500);
    }

    function renderTabla(pivot) {
        var thead = document.querySelector('#rsd-pivot-table thead');
        var tbody = document.querySelector('#rsd-pivot-table tbody');
        if (!thead || !tbody) return;
        thead.innerHTML = '<tr>' + (pivot.headers || []).map(function (h) {
            return '<th>' + String(h) + '</th>';
        }).join('') + '</tr>';
        tbody.innerHTML = (pivot.rows || []).map(function (row) {
            return '<tr>' + row.map(function (c) {
                return '<td>' + (typeof c === 'number' ? c.toLocaleString('es-AR') : String(c)) + '</td>';
            }).join('') + '</tr>';
        }).join('');
    }

    function renderChart(pivot, tipo) {
        var canvas = document.getElementById('rsd-pivot-chart');
        if (!canvas || !window.Chart) return;
        var labels = (pivot.rows || []).map(function (r) { return String(r[0] || ''); });
        var values = (pivot.rows || []).map(function (r) { return Number(r[r.length - 1] || 0); });
        if (chart) chart.destroy();
        chart = new Chart(canvas.getContext('2d'), {
            type: tipo === 'pie' ? 'pie' : (tipo === 'line' ? 'line' : 'bar'),
            data: {
                labels: labels,
                datasets: [{
                    label: 'Medida',
                    data: values,
                    backgroundColor: 'rgba(133, 193, 233, 0.7)',
                    borderColor: '#2874A6',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                legend: { display: tipo === 'pie' },
                scales: tipo === 'pie' ? {} : {
                    yAxes: [{ ticks: { beginAtZero: true } }]
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var form = document.getElementById('rsd-pivot-form');
        var cfg = window.rsdDashboardConfig || {};
        if (!form || !cfg.pivotUrl) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(form);
            var spec = {
                filas: [fd.get('dim_fila') || 'grupo_label'],
                columnas: fd.get('dim_columna') ? [fd.get('dim_columna')] : [],
                medidas: [{
                    campo: fd.get('medida') || 'c1',
                    agregacion: fd.get('agregacion') || 'sum'
                }]
            };
            fetch(cfg.pivotUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': cfg.csrf,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    dataset_id: fd.get('dataset_id') || null,
                    pivot_spec: spec
                })
            })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (json.async && json.job_uuid) {
                        esperarPivot(cfg, json.job_uuid, function (pivot) {
                            renderTabla(pivot);
                            renderChart(pivot, fd.get('chart_tipo') || 'bar');
                        });
                        return;
                    }
                    var pivot = json.data || {};
                    renderTabla(pivot);
                    renderChart(pivot, fd.get('chart_tipo') || 'bar');
                })
                .catch(function () {
                    window.alert('No se pudo calcular el pivot.');
                });
        });
    });
})();
