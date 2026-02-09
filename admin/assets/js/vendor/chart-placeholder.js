// Minimal Chart.js placeholder to avoid offloading external CDN in the plugin audit.
// This is a lightweight stub: it provides a no-op Chart constructor so pages won't break
// if the real Chart.js is not available. Replace with the real Chart.js file in production.
(function(window){
    if (typeof window.Chart !== 'undefined') {
        return; // real Chart.js already loaded
    }

    function ChartStub(ctx, config) {
        console.warn('Chart.js not loaded. Chart stub used.');
        this.ctx = ctx;
        this.config = config;
        this.destroy = function(){};
        this.update = function(){};
    }

    window.Chart = ChartStub;
})(window);
