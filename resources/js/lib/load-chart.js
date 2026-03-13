let chartLoaderPromise = null;

export async function loadChartJs() {
    if (window.Chart) {
        return window.Chart;
    }

    if (!chartLoaderPromise) {
        chartLoaderPromise = new Promise((resolve, reject) => {
            const existingScript = document.querySelector('script[data-chartjs-loader]');

            if (existingScript) {
                existingScript.addEventListener('load', () => resolve(window.Chart), { once: true });
                existingScript.addEventListener('error', () => reject(new Error('Falha ao carregar Chart.js.')), { once: true });
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js';
            script.async = true;
            script.dataset.chartjsLoader = 'true';
            script.onload = () => resolve(window.Chart);
            script.onerror = () => reject(new Error('Falha ao carregar Chart.js.'));

            document.head.appendChild(script);
        });
    }

    return chartLoaderPromise;
}
