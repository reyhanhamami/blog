let charts = new Map();
let Apex;

export async function mountAnalyticsCharts() {
    for (const element of document.querySelectorAll('[data-analytics-chart]')) {
        if (charts.has(element)) continue;
        const state = { abort: new AbortController(), chart: null };
        charts.set(element, state);
        try {
            Apex ||= import('apexcharts').then(module => module.default);
            const ApexCharts = await Apex;
            if (!element.isConnected || charts.get(element) !== state) continue;
            const data = JSON.parse(element.dataset.series);
            const mini = element.dataset.mini === '1';
            const format = value => new Intl.NumberFormat('id-ID', { maximumFractionDigits: 0 }).format(value);
            const names = ['Page Views', 'Unique Visitors'];
            state.chart = new ApexCharts(element, {
                chart: { type: 'line', height: mini ? 180 : 300, toolbar: { show: false }, animations: { enabled: !matchMedia('(prefers-reduced-motion: reduce)').matches } },
                series: (mini ? names.slice(0,1) : names).map((name,index) => ({ name, data: data.map(day => day[index ? 'visitors' : 'views']) })),
                xaxis: { categories: data.map(day => day.date), labels: { rotate: -35, hideOverlappingLabels: true } },
                yaxis: { min: 0, forceNiceScale: true, labels: { formatter: format } },
                tooltip: { y: { formatter: format } },
                stroke: { width: 3, curve: 'straight' }, colors: ['#4f46e5', '#059669'],
                markers: { size: 3, hover: { size: 5 } },
                noData: { text: 'Belum ada periode tercatat' }, legend: { show: !mini },
            });
            await state.chart.render();
            element.closest('section')?.querySelector('[data-analytics-series]')?.addEventListener('change', event => {
                names.forEach((name,index) => state.chart[event.target.value === 'both' || event.target.value === (index ? 'visitors' : 'views') ? 'showSeries' : 'hideSeries'](name));
            }, { signal: state.abort.signal });
        } catch {
            if (element.isConnected) element.textContent = 'Grafik belum dapat dimuat. Gunakan tabel data atau CSV.';
        }
    }
}

export function destroyAnalyticsCharts() {
    for (const state of charts.values()) { state.abort.abort(); state.chart?.destroy(); }
    charts.clear();
}
