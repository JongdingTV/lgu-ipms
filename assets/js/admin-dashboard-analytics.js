(function () {
    if (!location.pathname.endsWith('/admin/dashboard.php')) return;

    var chartHost = document.getElementById('budgetTrendChart');
    if (!chartHost) return;

    var points = [];
    try {
        points = JSON.parse(chartHost.getAttribute('data-points') || '[]');
    } catch (_) {
        points = [];
    }

    if (!Array.isArray(points) || points.length === 0) {
        chartHost.innerHTML = '<div class="dashboard-chart-empty">No budget trend data yet.</div>';
        return;
    }

    var values = points.map(function (p) { return Number(p.value || 0); });
    var max = Math.max.apply(null, values.concat([1]));
    var width = 680;
    var height = 260;
    var padding = 34;

    var coords = points.map(function (p, i) {
        var x = padding + (i * (width - (padding * 2)) / Math.max(1, points.length - 1));
        var y = height - padding - ((Number(p.value || 0) / max) * (height - (padding * 2)));
        return { x: x, y: y, label: p.label || '', value: Number(p.value || 0) };
    });

    var polyline = coords.map(function (c) { return c.x.toFixed(2) + ',' + c.y.toFixed(2); }).join(' ');
    var dots = coords.map(function (c) {
        return '<circle cx="' + c.x.toFixed(2) + '" cy="' + c.y.toFixed(2) + '" r="4" class="dashboard-trend-dot"><title>'
            + c.label + ': PHP ' + c.value.toLocaleString() + '</title></circle>';
    }).join('');

    var labels = coords.map(function (c) {
        return '<text x="' + c.x.toFixed(2) + '" y="' + (height - 10) + '" text-anchor="middle" class="dashboard-trend-label">' + c.label + '</text>';
    }).join('');

    chartHost.innerHTML = ''
        + '<svg viewBox="0 0 ' + width + ' ' + height + '" class="dashboard-trend-svg" role="img" aria-label="Budget trend line chart">'
        + '<line x1="' + padding + '" y1="' + (height - padding) + '" x2="' + (width - padding) + '" y2="' + (height - padding) + '" class="dashboard-trend-axis"></line>'
        + '<line x1="' + padding + '" y1="' + padding + '" x2="' + padding + '" y2="' + (height - padding) + '" class="dashboard-trend-axis"></line>'
        + '<polyline fill="none" points="' + polyline + '" class="dashboard-trend-line"></polyline>'
        + dots
        + labels
        + '</svg>';
})();
