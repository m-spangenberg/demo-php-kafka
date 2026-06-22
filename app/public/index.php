<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Domain\Zones;

$zones = Zones::definitions();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Fleet Command</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <style>
    :root {
      --bg-top: #eef6ed;
      --bg-bottom: #d5e7d0;
      --ink: #13221a;
      --muted: #4d6254;
      --panel: rgba(250, 252, 249, 0.95);
      --line: rgba(19, 34, 26, 0.1);
      --brand: #2f7d4b;
      --critical: #b84134;
      --warning: #a36c12;
      --nominal: #287248;
      --shadow: 0 20px 36px rgba(27, 44, 31, 0.12);
      --radius: 6px;
    }

    * {
      box-sizing: border-box;
    }

    html, body {
      margin: 0;
      min-height: 100%;
    }

    body {
      font-family: 'IBM Plex Sans', sans-serif;
      color: var(--ink);
      background:
        radial-gradient(circle at top left, rgba(47, 125, 75, 0.18), transparent 24%),
        radial-gradient(circle at right center, rgba(104, 153, 94, 0.14), transparent 26%),
        linear-gradient(180deg, var(--bg-top) 0%, var(--bg-bottom) 100%);
    }

    button,
    input,
    select {
      font: inherit;
    }

    button {
      appearance: none;
      border: 0;
      border-radius: 8px;
      cursor: pointer;
      transition: transform 120ms ease, opacity 120ms ease, background 120ms ease;
    }

    button:hover {
      transform: translateY(-1px);
    }

    .app-shell {
      min-height: 100vh;
      height: 100vh;
      padding: 14px;
      display: grid;
      grid-template-rows: auto 1fr;
      gap: 14px;
    }

    .topbar {
      background: linear-gradient(135deg, rgba(26, 61, 37, 0.98), rgba(39, 86, 49, 0.96));
      color: #f7fbf6;
      border-radius: 8px;
      box-shadow: var(--shadow);
      padding: 14px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .brand-cluster,
    .toolbar,
    .tab-cluster {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }

    .brand-title {
      margin: 0;
      font-family: 'Space Grotesk', sans-serif;
      font-size: 1.85rem;
      letter-spacing: -0.04em;
    }

    .metric-pill,
    .status-pill,
    .tab-button,
    .toolbar-button {
      border: 1px solid rgba(255, 255, 255, 0.1);
      background: rgba(255, 255, 255, 0.08);
      color: rgba(255, 255, 255, 0.92);
    }

    .metric-pill,
    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 999px;
    }

    .metric-pill strong,
    .status-pill strong {
      font-size: 1rem;
    }

    .metric-pill span,
    .status-pill span {
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255, 255, 255, 0.72);
    }

    .status-dot {
      width: 10px;
      height: 10px;
      border-radius: 999px;
      background: var(--warning);
      box-shadow: 0 0 0 6px rgba(199, 134, 36, 0.16);
    }

    .tab-button,
    .toolbar-button {
      padding: 10px 14px;
      font-weight: 600;
    }

    .tab-button.active {
      background: rgba(255, 255, 255, 0.18);
      color: #fff;
    }

    .toolbar-button.primary,
    .inline-button.primary {
      background: linear-gradient(135deg, #4aa55f, var(--brand));
      color: #fff;
    }

    .toolbar-button.danger,
    .inline-button.danger {
      background: #c34c3d;
      color: #fff4f2;
      border: 1px solid rgba(92, 17, 11, 0.26);
    }

    .toolbar-button.danger:hover,
    .inline-button.danger:hover {
      background: #a73a2d;
    }

    .panel-shell,
    .tab-panel,
    .live-layout,
    .builder-layout,
    .alert-layout,
    .ops-rail,
    .builder-rail,
    .list-section {
      min-height: 0;
    }

    .panel-shell {
      display: grid;
      min-height: 0;
    }

    .tab-panel {
      display: none;
    }

    .tab-panel.active {
      display: grid;
      min-height: 0;
    }

    .live-layout {
      display: grid;
      grid-template-columns: minmax(0, 3fr) minmax(320px, 1fr);
      gap: 14px;
    }

    .builder-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.7fr) minmax(340px, 1fr);
      gap: 14px;
    }

    .ops-rail,
    .builder-rail {
      display: grid;
      gap: 14px;
      align-content: start;
    }

    .card {
      background: var(--panel);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      overflow: hidden;
      backdrop-filter: blur(16px);
    }

    .map-card {
      display: grid;
      grid-template-rows: auto 1fr;
      min-height: 0;
      background: #dfe8db;
    }

    .card-head,
    .section-head {
      padding: 16px 18px 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
    }

    .section-head {
      display: block;
      padding-bottom: 0;
    }

    .card-head h2,
    .section-head h2 {
      margin: 0;
    }

    .label {
      font-size: 0.78rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      color: var(--muted);
      font-weight: 700;
    }

    .subtle,
    .tiny {
      color: var(--muted);
      margin: 0;
    }

    .tiny {
      font-size: 0.84rem;
    }

    .value {
      margin: 0;
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--ink);
    }

    #map,
    #zoneBuilderMap {
      display: block;
      height: clamp(320px, calc(100vh - 240px), 720px);
      background: #dfe8db;
    }

    .leaflet-container {
      background: #dfe8db;
    }

    .selected-vehicle,
    .builder-card {
      padding: 16px 18px;
      display: grid;
      gap: 12px;
    }

    .list-section {
      display: grid;
      grid-template-rows: auto 1fr;
    }

    .scroll-area {
      overflow: auto;
    }

    .vehicle-list,
    .alert-feed,
    .zone-list,
    .point-list {
      display: grid;
      gap: 10px;
      padding: 16px 18px 18px;
    }

    .vehicle-row,
    .alert-item,
    .zone-item,
    .point-item {
      border: 1px solid rgba(16, 33, 51, 0.08);
      border-radius: 6px;
      background: linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(242, 247, 242, 0.98));
      padding: 14px;
    }

    .vehicle-row {
      width: 100%;
      text-align: left;
    }

    .vehicle-row.active {
      border-color: rgba(47, 125, 75, 0.34);
      box-shadow: 0 0 0 3px rgba(47, 125, 75, 0.14);
    }

    .chip {
      display: inline-flex;
      align-items: center;
      border-radius: 999px;
      padding: 6px 10px;
      font-size: 0.75rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .chip.nominal {
      background: rgba(47, 143, 104, 0.12);
      color: var(--nominal);
    }

    .chip.warning {
      background: rgba(199, 134, 36, 0.12);
      color: var(--warning);
    }

    .chip.critical {
      background: rgba(210, 72, 63, 0.12);
      color: var(--critical);
    }

    .alert-item.critical,
    .zone-item.critical {
      border-color: rgba(210, 72, 63, 0.24);
    }

    .alert-item.warning,
    .zone-item.warning {
      border-color: rgba(199, 134, 36, 0.24);
    }

    .empty {
      padding: 18px;
      border: 1px dashed rgba(16, 33, 51, 0.16);
      border-radius: 6px;
      color: var(--muted);
      background: rgba(255, 255, 255, 0.6);
    }

    .form-grid,
    .filters {
      display: grid;
      gap: 12px;
    }

    .filters {
      grid-template-columns: repeat(4, minmax(0, 1fr));
      padding: 18px 20px 0;
    }

    .field {
      display: grid;
      gap: 6px;
    }

    .field label {
      font-size: 0.88rem;
      color: var(--muted);
      font-weight: 600;
    }

    .field input,
    .field select {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 6px;
      padding: 11px 12px;
      background: #fff;
      color: var(--ink);
    }

    .button-row {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .inline-button {
      padding: 10px 14px;
      font-weight: 600;
      background: rgba(47, 125, 75, 0.12);
      color: var(--brand);
    }

    .divider {
      height: 1px;
      background: rgba(16, 33, 51, 0.08);
    }

    .table-wrap {
      padding: 18px 20px 20px;
      overflow: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 820px;
    }

    thead th {
      text-align: left;
      font-size: 0.8rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.08em;
      padding: 0 0 12px;
      border-bottom: 1px solid rgba(16, 33, 51, 0.08);
    }

    tbody td {
      padding: 14px 0;
      border-bottom: 1px solid rgba(16, 33, 51, 0.06);
      vertical-align: top;
    }

    tbody tr:last-child td {
      border-bottom: 0;
    }

    .toast {
      position: fixed;
      right: 18px;
      bottom: 18px;
      background: rgba(26, 61, 37, 0.94);
      color: #fff;
      padding: 12px 14px;
      border-radius: 8px;
      box-shadow: var(--shadow);
      opacity: 0;
      transform: translateY(8px);
      pointer-events: none;
      transition: opacity 180ms ease, transform 180ms ease;
      max-width: 320px;
      z-index: 1000;
    }

    .toast.visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 1280px) {
      .live-layout,
      .builder-layout {
        grid-template-columns: 1fr;
      }

      #map,
      #zoneBuilderMap {
        height: clamp(300px, calc(100vh - 320px), 440px);
      }
    }

    @media (max-height: 760px) {
      .app-shell {
        padding: 10px;
        gap: 10px;
      }

      .topbar {
        padding: 12px 16px;
      }

      .card-head,
      .section-head,
      .selected-vehicle,
      .builder-card,
      .filters,
      .table-wrap {
        padding-left: 14px;
        padding-right: 14px;
      }

      #map,
      #zoneBuilderMap {
        height: clamp(260px, calc(100vh - 230px), 420px);
      }
    }

    @media (max-width: 980px) {
      .filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 720px) {
      .app-shell {
        padding: 12px;
      }

      .topbar {
        padding: 16px;
      }

      .filters {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <header class="topbar">
      <div class="brand-cluster">
        <div class="metric-pill"><strong id="metricVehicles">0</strong><span>Tracked</span></div>
        <div class="metric-pill"><strong id="metricAlerts">0</strong><span>Active Alerts</span></div>
        <div class="metric-pill"><strong id="metricCritical">0</strong><span>Critical</span></div>
      </div>

      <div class="toolbar">
        <div class="status-pill"><span class="status-dot" id="statusDot"></span><strong id="runningStatus">Idle</strong><span id="statusMeta">Waiting for simulation start</span></div>
        <div class="status-pill"><strong id="connectionState">Connecting</strong><span id="lastRefresh">Awaiting data</span></div>
        <button type="button" class="toolbar-button primary" id="startBtn">Start</button>
        <button type="button" class="toolbar-button" id="stopBtn">Pause</button>
        <button type="button" class="toolbar-button" id="refreshBtn">Refresh</button>
        <button type="button" class="toolbar-button danger" id="resetBtn">Reset</button>
      </div>

      <nav class="tab-cluster" aria-label="Primary views">
        <button type="button" class="tab-button active" data-tab="live">Live Ops</button>
        <button type="button" class="tab-button" data-tab="zones">Zone Builder</button>
        <button type="button" class="tab-button" data-tab="alerts">Alert Desk</button>
      </nav>
    </header>

    <div class="panel-shell">
      <section class="tab-panel active" data-panel="live">
        <div class="live-layout">
          <article class="card map-card">
            <header class="card-head">
              <div>
                <h2>Live Fleet Map</h2>
                <p class="subtle">Walvis Bay operations area telemetry.</p>
              </div>
              <span class="tiny">Data is simulated.</span>
            </header>
            <div id="map"></div>
          </article>

          <aside class="ops-rail">
            <section class="card selected-vehicle">
              <div class="label">Selected Vehicle</div>
              <p class="value" id="selectedVehicle">No selection</p>
              <p class="subtle" id="selectedVehicleMeta">Pick a marker or attention row to inspect live telemetry.</p>
            </section>

            <section class="card list-section">
              <div class="section-head">
                <div class="label">Vehicles Requiring Attention</div>
                <p class="subtle">Only vehicles with warning or critical state appear here.</p>
              </div>
              <div class="vehicle-list scroll-area" id="vehicleList"><div class="empty">No vehicles need attention yet.</div></div>
            </section>

            <section class="card list-section">
              <div class="section-head">
                <div class="label">Recent Alerts</div>
                <p class="subtle">Latest projector-derived incidents and zone events.</p>
              </div>
              <div class="alert-feed scroll-area" id="alertList"><div class="empty">Alerts will appear here once the simulation starts.</div></div>
            </section>
          </aside>
        </div>
      </section>

      <section class="tab-panel" data-panel="zones">
        <div class="builder-layout">
          <article class="card map-card">
            <header class="card-head">
              <div>
                <h2>Custom Zones</h2>
                <p class="subtle">Click the map to place polygon points, then save a zone for the simulator and dashboard.</p>
              </div>
              <span class="tiny" id="builderPointCount">0 points</span>
            </header>
            <div id="zoneBuilderMap"></div>
          </article>

          <aside class="builder-rail">
            <section class="card builder-card">
              <div class="label">Zone Builder</div>
              <div class="form-grid">
                <div class="field">
                  <label for="zoneName">Zone Name</label>
                  <input id="zoneName" type="text" placeholder="For example: Walvis Bay Airfield Buffer">
                </div>
                <div class="field">
                  <label for="zoneSeverity">Severity</label>
                  <select id="zoneSeverity">
                    <option value="warning">Warning</option>
                    <option value="critical">Critical</option>
                  </select>
                </div>
                <div class="button-row">
                  <button type="button" class="inline-button primary" id="saveZoneBtn">Save Zone</button>
                  <button type="button" class="inline-button" id="undoPointBtn">Undo Last Point</button>
                  <button type="button" class="inline-button danger" id="clearPointsBtn">Clear Draft</button>
                </div>
              </div>
              <div class="divider"></div>
              <div class="label">Draft Polygon</div>
              <div class="point-list" id="draftPointList"><div class="empty">Map clicks will add polygon points here.</div></div>
            </section>

            <section class="card builder-card">
              <div class="label">Existing Zones</div>
              <div class="zone-list scroll-area" id="zoneInventory"><div class="empty">Zones will appear once loaded.</div></div>
            </section>
          </aside>
        </div>
      </section>

      <section class="tab-panel" data-panel="alerts">
        <article class="card alert-layout">
          <header class="card-head">
            <div>
              <h2>Fleet Health Alerts</h2>
              <p class="subtle">Filter by severity, alert type, vehicle id, or free text.</p>
            </div>
          </header>

          <div class="filters">
            <div class="field">
              <label for="filterSeverity">Severity</label>
              <select id="filterSeverity">
                <option value="all">All severities</option>
                <option value="critical">Critical</option>
                <option value="warning">Warning</option>
              </select>
            </div>

            <div class="field">
              <label for="filterType">Alert Type</label>
              <select id="filterType">
                <option value="all">All types</option>
              </select>
            </div>

            <div class="field">
              <label for="filterVehicle">Vehicle</label>
              <input id="filterVehicle" type="text" placeholder="FLT-0143">
            </div>

            <div class="field">
              <label for="filterText">Search Message</label>
              <input id="filterText" type="text" placeholder="engine, tire, Etosha...">
            </div>
          </div>

          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th>Timestamp</th>
                  <th>Vehicle</th>
                  <th>Severity</th>
                  <th>Type</th>
                  <th>Message</th>
                </tr>
              </thead>
              <tbody id="alertTableBody">
                <tr><td colspan="5"><div class="empty">Alerts will populate once the simulation is running.</div></td></tr>
              </tbody>
            </table>
          </div>
        </article>
      </section>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    window.FLEET_ZONES = <?php echo json_encode($zones, JSON_THROW_ON_ERROR); ?>;
  </script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
  <script>
    const state = {
      vehicles: [],
      alerts: [],
      metrics: {
        vehicleCount: 0,
        activeAlerts: 0,
        criticalAlerts: 0,
        averageSpeed: 0,
        averageTemp: 0,
        averageTirePressure: 0,
        updatedAt: ''
      },
      simulation: {
        running: '0',
        targetVehicles: '0',
        startedAt: '',
        lastTickAt: '',
        generation: ''
      },
      zones: Array.isArray(window.FLEET_ZONES) ? window.FLEET_ZONES : [],
      markers: new Map(),
      selectedVehicleId: null,
      activeTab: 'live',
      liveMap: null,
      builderMap: null,
      liveZoneLayer: null,
      builderZoneLayer: null,
      builderDraftLayer: null,
      draftPoints: [],
      eventSource: null,
    };

    const els = {
      metricVehicles: document.getElementById('metricVehicles'),
      metricAlerts: document.getElementById('metricAlerts'),
      metricCritical: document.getElementById('metricCritical'),
      runningStatus: document.getElementById('runningStatus'),
      statusMeta: document.getElementById('statusMeta'),
      statusDot: document.getElementById('statusDot'),
      connectionState: document.getElementById('connectionState'),
      lastRefresh: document.getElementById('lastRefresh'),
      selectedVehicle: document.getElementById('selectedVehicle'),
      selectedVehicleMeta: document.getElementById('selectedVehicleMeta'),
      vehicleList: document.getElementById('vehicleList'),
      alertList: document.getElementById('alertList'),
      alertTableBody: document.getElementById('alertTableBody'),
      zoneInventory: document.getElementById('zoneInventory'),
      draftPointList: document.getElementById('draftPointList'),
      builderPointCount: document.getElementById('builderPointCount'),
      zoneName: document.getElementById('zoneName'),
      zoneSeverity: document.getElementById('zoneSeverity'),
      saveZoneBtn: document.getElementById('saveZoneBtn'),
      undoPointBtn: document.getElementById('undoPointBtn'),
      clearPointsBtn: document.getElementById('clearPointsBtn'),
      startBtn: document.getElementById('startBtn'),
      stopBtn: document.getElementById('stopBtn'),
      refreshBtn: document.getElementById('refreshBtn'),
      resetBtn: document.getElementById('resetBtn'),
      filterSeverity: document.getElementById('filterSeverity'),
      filterType: document.getElementById('filterType'),
      filterVehicle: document.getElementById('filterVehicle'),
      filterText: document.getElementById('filterText'),
      toast: document.getElementById('toast'),
      tabButtons: Array.from(document.querySelectorAll('[data-tab]')),
      tabPanels: Array.from(document.querySelectorAll('[data-panel]')),
    };

    function escapeHtml(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }

    function showToast(message, isError = false) {
      els.toast.textContent = message;
      els.toast.style.background = isError ? 'rgba(130, 25, 18, 0.94)' : 'rgba(26, 61, 37, 0.94)';
      els.toast.classList.add('visible');
      window.clearTimeout(showToast.timer);
      showToast.timer = window.setTimeout(() => els.toast.classList.remove('visible'), 2600);
    }

    function zoneColor(zone) {
      if (zone.custom) {
        return '#2f7d4b';
      }

      return zone.severity === 'critical' ? '#d2483f' : '#c78624';
    }

    function markerColor(status) {
      if (status === 'critical') {
        return '#d2483f';
      }

      if (status === 'warning') {
        return '#c78624';
      }

      return '#2f8f68';
    }

    function vehicleIcon(status) {
      return L.divIcon({
        className: '',
        html: '<div style="width:14px;height:14px;border-radius:999px;background:' + markerColor(status) + ';border:2px solid rgba(255,255,255,0.92);box-shadow:0 0 0 8px rgba(13,27,42,0.08)"></div>',
        iconSize: [18, 18],
        iconAnchor: [9, 9]
      });
    }

    function formatTime(value) {
      if (!value) {
        return 'Awaiting data';
      }

      const date = new Date(value);

      if (Number.isNaN(date.getTime())) {
        return value;
      }

      return date.toLocaleString();
    }

    function setTab(tabName) {
      state.activeTab = tabName;

      els.tabButtons.forEach((button) => {
        button.classList.toggle('active', button.dataset.tab === tabName);
      });

      els.tabPanels.forEach((panel) => {
        panel.classList.toggle('active', panel.dataset.panel === tabName);
      });

      if (tabName === 'live' && state.liveMap) {
        window.setTimeout(() => state.liveMap.invalidateSize(), 120);
      }

      if (tabName === 'zones' && state.builderMap) {
        window.setTimeout(() => state.builderMap.invalidateSize(), 120);
      }
    }

    function renderSummary() {
      els.metricVehicles.textContent = String(state.metrics.vehicleCount || state.vehicles.length || 0);
      els.metricAlerts.textContent = String(state.metrics.activeAlerts || state.alerts.length || 0);
      els.metricCritical.textContent = String(state.metrics.criticalAlerts || 0);

      const running = String(state.simulation.running) === '1';
      els.runningStatus.textContent = running ? 'Running' : 'Idle';
      els.statusMeta.textContent = running
        ? 'Target ' + String(state.simulation.targetVehicles || state.metrics.vehicleCount || 0) + ' vehicles'
        : 'Waiting for simulation start';
      els.statusDot.style.background = running ? 'var(--nominal)' : 'var(--warning)';
      els.statusDot.style.boxShadow = running
        ? '0 0 0 6px rgba(47, 143, 104, 0.16)'
        : '0 0 0 6px rgba(199, 134, 36, 0.16)';
      els.lastRefresh.textContent = formatTime(state.metrics.updatedAt || state.simulation.lastTickAt);
    }

    function renderVehicleMarkers() {
      if (!state.liveMap) {
        return;
      }

      const activeIds = new Set();

      state.vehicles.forEach((vehicle) => {
        activeIds.add(vehicle.vehicleId);

        const latLng = [Number(vehicle.lat), Number(vehicle.lng)];
        let marker = state.markers.get(vehicle.vehicleId);

        if (!marker) {
          marker = L.marker(latLng, { icon: vehicleIcon(vehicle.status) }).addTo(state.liveMap);
          marker.on('click', () => selectVehicle(vehicle.vehicleId));
          state.markers.set(vehicle.vehicleId, marker);
        }

        marker.setLatLng(latLng);
        marker.setIcon(vehicleIcon(vehicle.status));
        marker.bindTooltip(vehicle.vehicleId + ' · ' + String(vehicle.status || 'nominal').toUpperCase() + ' · ' + String(vehicle.speedKmh ?? 0) + ' km/h');
      });

      Array.from(state.markers.entries()).forEach(([vehicleId, marker]) => {
        if (!activeIds.has(vehicleId)) {
          marker.remove();
          state.markers.delete(vehicleId);
        }
      });
    }

    function selectVehicle(vehicleId) {
      state.selectedVehicleId = vehicleId;
      const vehicle = state.vehicles.find((entry) => entry.vehicleId === vehicleId);

      if (!vehicle) {
        els.selectedVehicle.textContent = 'No selection';
        els.selectedVehicleMeta.textContent = 'Pick a marker or attention row to inspect live telemetry.';
        renderVehicleList();
        return;
      }

      els.selectedVehicle.textContent = vehicle.vehicleId;
      els.selectedVehicleMeta.textContent = String(vehicle.status || 'nominal').toUpperCase()
        + ' · ' + String(vehicle.engineTempC ?? '-') + ' C'
        + ' · ' + String(vehicle.tirePressurePsi ?? '-') + ' PSI'
        + ' · ' + String(vehicle.speedKmh ?? '-') + ' km/h'
        + (vehicle.zone && vehicle.zone.name ? ' · ' + vehicle.zone.name : '');

      const marker = state.markers.get(vehicleId);
      if (marker && state.liveMap) {
        state.liveMap.flyTo(marker.getLatLng(), Math.max(state.liveMap.getZoom(), 8), { duration: 0.8 });
      }

      renderVehicleList();
    }

    function renderVehicleList() {
      const attentionVehicles = state.vehicles
        .filter((vehicle) => vehicle.status && vehicle.status !== 'nominal')
        .sort((left, right) => {
          if (left.status === right.status) {
            return String(left.vehicleId).localeCompare(String(right.vehicleId));
          }

          return left.status === 'critical' ? -1 : 1;
        });

      if (attentionVehicles.length === 0) {
        els.vehicleList.innerHTML = '<div class="empty">No vehicles need attention yet.</div>';
        return;
      }

      els.vehicleList.innerHTML = attentionVehicles.map((vehicle) => {
        const activeClass = vehicle.vehicleId === state.selectedVehicleId ? ' active' : '';
        const zoneText = vehicle.zone && vehicle.zone.name ? vehicle.zone.name : 'No zone';

        return '<button type="button" class="vehicle-row' + activeClass + '" data-vehicle-id="' + escapeHtml(vehicle.vehicleId) + '">'
          + '<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">'
          + '<div><strong>' + escapeHtml(vehicle.vehicleId) + '</strong><div class="subtle">' + escapeHtml(zoneText) + '</div></div>'
          + '<span class="chip ' + escapeHtml(vehicle.status) + '">' + escapeHtml(vehicle.status) + '</span>'
          + '</div>'
          + '<div class="subtle" style="margin-top:8px;">Engine ' + escapeHtml(vehicle.engineTempC) + ' C · Tire ' + escapeHtml(vehicle.tirePressurePsi) + ' PSI · Speed ' + escapeHtml(vehicle.speedKmh) + ' km/h</div>'
          + '</button>';
      }).join('');

      els.vehicleList.querySelectorAll('[data-vehicle-id]').forEach((button) => {
        button.addEventListener('click', () => selectVehicle(button.dataset.vehicleId));
      });
    }

    function renderAlertFeed() {
      if (state.alerts.length === 0) {
        els.alertList.innerHTML = '<div class="empty">Alerts will appear here once the simulation starts.</div>';
        return;
      }

      els.alertList.innerHTML = state.alerts.slice(0, 12).map((alert) => {
        return '<div class="alert-item ' + escapeHtml(alert.severity || 'warning') + '">'
          + '<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">'
          + '<div><strong>' + escapeHtml(alert.vehicleId || 'Vehicle') + '</strong><div class="subtle">' + escapeHtml(alert.type || 'alert') + '</div></div>'
          + '<span class="chip ' + escapeHtml(alert.severity || 'warning') + '">' + escapeHtml(alert.severity || 'warning') + '</span>'
          + '</div>'
          + '<div class="subtle" style="margin-top:8px;">' + escapeHtml(alert.message || '') + '</div>'
          + '<div class="tiny" style="margin-top:8px;">' + escapeHtml(formatTime(alert.timestamp || '')) + '</div>'
          + '</div>';
      }).join('');
    }

    function filteredAlerts() {
      const severity = els.filterSeverity.value;
      const type = els.filterType.value;
      const vehicle = els.filterVehicle.value.trim().toLowerCase();
      const text = els.filterText.value.trim().toLowerCase();

      return state.alerts.filter((alert) => {
        if (severity !== 'all' && alert.severity !== severity) {
          return false;
        }

        if (type !== 'all' && alert.type !== type) {
          return false;
        }

        if (vehicle && !String(alert.vehicleId || '').toLowerCase().includes(vehicle)) {
          return false;
        }

        if (text && !String(alert.message || '').toLowerCase().includes(text) && !String(alert.type || '').toLowerCase().includes(text)) {
          return false;
        }

        return true;
      });
    }

    function renderAlertFilters() {
      const types = Array.from(new Set(state.alerts.map((alert) => alert.type).filter(Boolean))).sort();
      const previous = els.filterType.value;
      els.filterType.innerHTML = '<option value="all">All types</option>' + types.map((type) => '<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>').join('');
      if (types.includes(previous)) {
        els.filterType.value = previous;
      }
    }

    function renderAlertTable() {
      renderAlertFilters();

      const alerts = filteredAlerts();

      if (alerts.length === 0) {
        els.alertTableBody.innerHTML = '<tr><td colspan="5"><div class="empty">No alerts match the active filters.</div></td></tr>';
        return;
      }

      els.alertTableBody.innerHTML = alerts.map((alert) => {
        return '<tr>'
          + '<td>' + escapeHtml(formatTime(alert.timestamp || '')) + '</td>'
          + '<td>' + escapeHtml(alert.vehicleId || '') + '</td>'
          + '<td><span class="chip ' + escapeHtml(alert.severity || 'warning') + '">' + escapeHtml(alert.severity || 'warning') + '</span></td>'
          + '<td>' + escapeHtml(alert.type || '') + '</td>'
          + '<td>' + escapeHtml(alert.message || '') + '</td>'
          + '</tr>';
      }).join('');
    }

    function renderZoneInventory() {
      if (state.zones.length === 0) {
        els.zoneInventory.innerHTML = '<div class="empty">No zones loaded.</div>';
        return;
      }

      els.zoneInventory.innerHTML = state.zones.map((zone) => {
        const action = zone.custom
          ? '<button type="button" class="inline-button danger" data-zone-delete="' + escapeHtml(zone.id) + '">Delete</button>'
          : '<span class="tiny">Default zone</span>';

        return '<div class="zone-item ' + escapeHtml(zone.severity) + '">'
          + '<div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;">'
          + '<div><strong>' + escapeHtml(zone.name) + '</strong><div class="subtle">' + escapeHtml(zone.source || 'default') + ' · ' + escapeHtml(zone.polygon.length) + ' points</div></div>'
          + '<span class="chip ' + escapeHtml(zone.severity) + '">' + escapeHtml(zone.severity) + '</span>'
          + '</div>'
          + '<div style="margin-top:10px;display:flex;justify-content:space-between;gap:12px;align-items:center;">'
          + '<div class="tiny">' + escapeHtml(zone.id) + '</div>'
          + action
          + '</div>'
          + '</div>';
      }).join('');

      els.zoneInventory.querySelectorAll('[data-zone-delete]').forEach((button) => {
        button.addEventListener('click', () => deleteZone(button.dataset.zoneDelete));
      });
    }

    function renderDraftZone() {
      els.builderPointCount.textContent = state.draftPoints.length + ' points';

      if (state.builderDraftLayer) {
        state.builderDraftLayer.clearLayers();
      }

      if (state.draftPoints.length === 0) {
        els.draftPointList.innerHTML = '<div class="empty">Map clicks will add polygon points here.</div>';
        return;
      }

      els.draftPointList.innerHTML = state.draftPoints.map((point, index) => {
        return '<div class="point-item"><strong>Point ' + String(index + 1) + '</strong><div class="subtle">' + escapeHtml(point.lat) + ', ' + escapeHtml(point.lng) + '</div></div>';
      }).join('');

      state.draftPoints.forEach((point, index) => {
        L.circleMarker([point.lat, point.lng], {
          radius: 5,
          weight: 2,
          color: '#2f7d4b',
          fillColor: '#2f7d4b',
          fillOpacity: 0.9,
        }).addTo(state.builderDraftLayer).bindTooltip('Point ' + String(index + 1));
      });

      if (state.draftPoints.length >= 2) {
        L.polyline(state.draftPoints.map((point) => [point.lat, point.lng]), {
          color: '#2f7d4b',
          dashArray: '4 6',
          weight: 2,
        }).addTo(state.builderDraftLayer);
      }

      if (state.draftPoints.length >= 3) {
        L.polygon(state.draftPoints.map((point) => [point.lat, point.lng]), {
          color: '#2f7d4b',
          fillColor: '#2f7d4b',
          fillOpacity: 0.12,
          weight: 2,
        }).addTo(state.builderDraftLayer);
      }
    }

    function renderZones() {
      if (!state.liveZoneLayer || !state.builderZoneLayer) {
        return;
      }

      state.liveZoneLayer.clearLayers();
      state.builderZoneLayer.clearLayers();

      state.zones.forEach((zone) => {
        const color = zoneColor(zone);
        const options = {
          color,
          fillColor: color,
          fillOpacity: zone.custom ? 0.14 : 0.18,
          weight: 2,
          dashArray: zone.custom ? '3 6' : '8 6'
        };

        [state.liveZoneLayer, state.builderZoneLayer].forEach((layerGroup) => {
          const polygon = L.polygon(zone.polygon.map((point) => [point.lat, point.lng]), options).addTo(layerGroup);
          polygon.bindPopup('<strong>' + escapeHtml(zone.name) + '</strong><br>' + escapeHtml(String(zone.severity).toUpperCase()) + ' no-go zone');
        });
      });

      renderZoneInventory();
    }

    function applySnapshot(payload) {
      state.vehicles = Array.isArray(payload.vehicles) ? payload.vehicles : [];
      state.alerts = Array.isArray(payload.alerts) ? payload.alerts : [];
      state.metrics = payload.metrics || state.metrics;
      state.simulation = payload.simulation || state.simulation;

      renderSummary();
      renderVehicleMarkers();
      renderVehicleList();
      renderAlertFeed();
      renderAlertTable();

      if (state.selectedVehicleId && !state.vehicles.some((vehicle) => vehicle.vehicleId === state.selectedVehicleId)) {
        state.selectedVehicleId = null;
        selectVehicle('');
      }
    }

    async function fetchJson(url, options) {
      const response = await fetch(url, options);
      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Request failed');
      }

      return data;
    }

    async function refreshFleet() {
      const payload = await fetchJson('/api/fleet.php');
      els.connectionState.textContent = 'Online';
      applySnapshot(payload);
    }

    async function refreshZones() {
      const payload = await fetchJson('/api/zones.php');
      state.zones = Array.isArray(payload.zones) ? payload.zones : [];
      renderZones();
    }

    async function postSimulation(action) {
      await fetchJson('/api/simulation.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action })
      });

      await refreshFleet();
      showToast('Simulation ' + action + ' request sent.');
    }

    async function saveZone() {
      const name = els.zoneName.value.trim();
      const severity = els.zoneSeverity.value;

      if (!name) {
        showToast('Zone name is required.', true);
        return;
      }

      if (state.draftPoints.length < 3) {
        showToast('At least three points are required.', true);
        return;
      }

      const payload = await fetchJson('/api/zones.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, severity, polygon: state.draftPoints })
      });
      state.zones = Array.isArray(payload.zones) ? payload.zones : state.zones;
      state.draftPoints = [];
      els.zoneName.value = '';
      renderDraftZone();
      renderZones();
      showToast('Zone saved.');
    }

    async function deleteZone(zoneId) {
      const payload = await fetchJson('/api/zones.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: zoneId })
      });

      state.zones = Array.isArray(payload.zones) ? payload.zones : state.zones;
      renderZones();
      showToast('Zone deleted.');
    }

    function initMaps() {
      state.liveMap = L.map('map', { zoomControl: true }).setView([-22.949, 14.532], 12);
      state.builderMap = L.map('zoneBuilderMap', { zoomControl: true }).setView([-22.949, 14.532], 12);

      [state.liveMap, state.builderMap].forEach((mapInstance) => {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 18,
          attribution: '&copy; OpenStreetMap contributors'
        }).addTo(mapInstance);

        mapInstance.setMaxBounds([
          [-23.01, 14.46],
          [-22.89, 14.63]
        ]);
      });

      state.liveZoneLayer = L.layerGroup().addTo(state.liveMap);
      state.builderZoneLayer = L.layerGroup().addTo(state.builderMap);
      state.builderDraftLayer = L.layerGroup().addTo(state.builderMap);

      state.builderMap.on('click', (event) => {
        state.draftPoints.push({
          lat: Number(event.latlng.lat.toFixed(6)),
          lng: Number(event.latlng.lng.toFixed(6)),
        });
        renderDraftZone();
      });

      renderZones();
    }

    function initEvents() {
      els.tabButtons.forEach((button) => {
        button.addEventListener('click', () => setTab(button.dataset.tab));
      });

      els.startBtn.addEventListener('click', () => postSimulation('start').catch(handleError));
      els.stopBtn.addEventListener('click', () => postSimulation('stop').catch(handleError));
      els.resetBtn.addEventListener('click', () => postSimulation('reset').catch(handleError));
      els.refreshBtn.addEventListener('click', () => refreshFleet().catch(handleError));
      els.saveZoneBtn.addEventListener('click', () => saveZone().catch(handleError));
      els.undoPointBtn.addEventListener('click', () => {
        state.draftPoints.pop();
        renderDraftZone();
      });
      els.clearPointsBtn.addEventListener('click', () => {
        state.draftPoints = [];
        renderDraftZone();
      });

      [els.filterSeverity, els.filterType, els.filterVehicle, els.filterText].forEach((element) => {
        element.addEventListener('input', renderAlertTable);
        element.addEventListener('change', renderAlertTable);
      });
    }

    function connectEvents() {
      if (state.eventSource) {
        state.eventSource.close();
      }

      state.eventSource = new EventSource('/api/events.php');
      state.eventSource.addEventListener('snapshot', (event) => {
        const payload = JSON.parse(event.data);
        els.connectionState.textContent = 'Live';
        applySnapshot(payload);
      });
      state.eventSource.onerror = () => {
        els.connectionState.textContent = 'Retrying';
      };
    }

    function handleError(error) {
      console.error(error);
      els.connectionState.textContent = 'Error';
      showToast(error.message || 'Request failed.', true);
    }

    async function boot() {
      initMaps();
      initEvents();
      renderDraftZone();
      renderZones();
      await refreshFleet();
      await refreshZones();
      connectEvents();
    }

    boot().catch(handleError);
  </script>
</body>
</html>
