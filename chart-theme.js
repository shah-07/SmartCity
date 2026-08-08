/* ============================================================================
   SMART CITY — CHART.JS PRESENTATION DEFAULTS
   ----------------------------------------------------------------------------
   Sets global Chart.js defaults so charts read correctly on the dark glass
   surfaces. Existing chart configurations are not touched: every option here
   is a default that any explicit per-chart setting still overrides.
   Load after chart.umd.min.js and before the page script.
   ========================================================================== */
(function () {
  if (typeof Chart === 'undefined') return;

  var TEXT   = '#a9bbd4';
  var TEXT_2 = '#6d829f';
  var GRID   = 'rgba(255, 255, 255, 0.055)';
  var FONT   = '"Segoe UI Variable Text", -apple-system, BlinkMacSystemFont, ' +
               '"Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';

  /* Base */
  Chart.defaults.color = TEXT;
  Chart.defaults.borderColor = GRID;
  Chart.defaults.font.family = FONT;
  Chart.defaults.font.size = 12;

  /* Legend */
  Chart.defaults.plugins.legend.labels.color = TEXT;
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
  Chart.defaults.plugins.legend.labels.boxWidth = 8;
  Chart.defaults.plugins.legend.labels.boxHeight = 8;
  Chart.defaults.plugins.legend.labels.padding = 16;
  Chart.defaults.plugins.legend.labels.font = { size: 12, weight: '500' };

  /* Tooltip */
  Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(9, 16, 30, 0.94)';
  Chart.defaults.plugins.tooltip.titleColor = '#eef4ff';
  Chart.defaults.plugins.tooltip.bodyColor = TEXT;
  Chart.defaults.plugins.tooltip.borderColor = 'rgba(56, 220, 240, 0.30)';
  Chart.defaults.plugins.tooltip.borderWidth = 1;
  Chart.defaults.plugins.tooltip.cornerRadius = 10;
  Chart.defaults.plugins.tooltip.padding = 12;
  Chart.defaults.plugins.tooltip.displayColors = true;
  Chart.defaults.plugins.tooltip.boxPadding = 6;
  Chart.defaults.plugins.tooltip.titleFont = { size: 12, weight: '600' };

  /* Title */
  Chart.defaults.plugins.title.color = '#eef4ff';

  /* Cartesian scales */
  if (Chart.defaults.scales && Chart.defaults.scales.linear) {
    ['linear', 'category', 'logarithmic', 'time', 'timeseries'].forEach(function (key) {
      var scale = Chart.defaults.scales[key];
      if (!scale) return;
      scale.grid = Object.assign({}, scale.grid, {
        color: GRID,
        drawTicks: false,
        tickLength: 0
      });
      scale.border = Object.assign({}, scale.border, {
        color: 'rgba(255, 255, 255, 0.09)',
        display: true
      });
      scale.ticks = Object.assign({}, scale.ticks, {
        color: TEXT_2,
        padding: 10,
        font: { size: 11 }
      });
      scale.title = Object.assign({}, scale.title, {
        color: TEXT_2,
        font: { size: 11, weight: '600' }
      });
    });
  }

  /* Elements */
  Chart.defaults.elements.point.radius = 2.5;
  Chart.defaults.elements.point.hoverRadius = 6;
  Chart.defaults.elements.point.hoverBorderWidth = 2;
  Chart.defaults.elements.point.borderWidth = 0;

  Chart.defaults.elements.line.borderWidth = 2;
  Chart.defaults.elements.line.borderCapStyle = 'round';
  Chart.defaults.elements.line.borderJoinStyle = 'round';

  Chart.defaults.elements.bar.borderRadius = 6;
  Chart.defaults.elements.bar.borderSkipped = 'bottom';

  /* Doughnut / pie segment separation reads as glass edges */
  Chart.defaults.elements.arc.borderColor = 'rgba(8, 14, 26, 0.85)';
  Chart.defaults.elements.arc.borderWidth = 2;
  Chart.defaults.elements.arc.hoverOffset = 6;

  Chart.defaults.datasets.doughnut.cutout = '64%';

  /* ---------------------------------------------------------------------
     Palette mapping
     ---------------------------------------------------------------------
     The pages set their series colours inline. Rather than edit those
     scripts, this plugin translates the original palette into the Smart
     City palette as the chart updates. Any colour that isn't a known
     original value passes through untouched, and the mapping is
     idempotent because no target colour is also a key.
     ------------------------------------------------------------------- */
  var PALETTE = {
    /* Blue series -> electric cyan */
    '#3498db':                 '#38dcf0',
    '#2980b9':                 '#17b8d4',
    'rgba(52,152,219,1)':      '#38dcf0',
    'rgba(52,152,219,0.2)':    'rgba(56,220,240,0.20)',
    'rgba(52,152,219,0.1)':    'rgba(56,220,240,0.14)',
    /* Red series -> controlled coral */
    '#e74c3c':                 '#f87171',
    '#c0392b':                 '#e05a5a',
    'rgba(231,76,60,0.1)':     'rgba(248,113,113,0.14)',
    /* Green series -> healthy mint */
    '#2ecc71':                 '#34d399',
    '#27ae60':                 '#17b184',
    /* Yellow -> warning amber */
    '#f1c40f':                 '#f5c451',
    /* Purple -> subtle violet */
    '#9b59b6':                 '#7c6cff'
  };

  var COLOR_KEYS = [
    'backgroundColor', 'borderColor',
    'hoverBackgroundColor', 'hoverBorderColor',
    'pointBackgroundColor', 'pointBorderColor',
    'pointHoverBackgroundColor', 'pointHoverBorderColor'
  ];

  function normalise(value) {
    return String(value).toLowerCase().replace(/\s+/g, '');
  }

  function mapColor(value) {
    if (typeof value !== 'string') return value;
    var hit = PALETTE[normalise(value)];
    return hit || value;
  }

  /* Fills read as depth rather than a flat wash */
  function verticalFade(chart, rgba) {
    var area = chart.chartArea;
    if (!area || !area.bottom) return rgba;
    var match = rgba.match(/^rgba?\((\d+),(\d+),(\d+)/);
    if (!match) return rgba;
    var rgb = match[1] + ',' + match[2] + ',' + match[3];
    try {
      var g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
      g.addColorStop(0, 'rgba(' + rgb + ',0.34)');
      g.addColorStop(0.55, 'rgba(' + rgb + ',0.10)');
      g.addColorStop(1, 'rgba(' + rgb + ',0.01)');
      return g;
    } catch (e) {
      return rgba;
    }
  }

  Chart.register({
    id: 'smartCityPalette',
    beforeUpdate: function (chart) {
      (chart.data.datasets || []).forEach(function (ds) {
        COLOR_KEYS.forEach(function (key) {
          var value = ds[key];
          if (Array.isArray(value)) {
            ds[key] = value.map(mapColor);
          } else if (typeof value === 'string') {
            ds[key] = mapColor(value);
          }
        });
      });
    },
    afterLayout: function (chart) {
      (chart.data.datasets || []).forEach(function (ds) {
        if (!ds.fill) return;
        if (typeof ds.backgroundColor !== 'string') return;
        if (ds.backgroundColor.indexOf('rgba') !== 0) return;
        ds.backgroundColor = verticalFade(chart, ds.backgroundColor);
      });
    }
  });
})();
