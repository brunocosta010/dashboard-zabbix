const $ = selector => document.querySelector(selector);
let refreshSeconds = 30;
let nextRefresh = Date.now() + refreshSeconds * 1000;
let loading = false;
let alerts = [];
let updates = [];
let alertPage = 0;
let updatePage = 0;
let renderedOnce = false;
let lastCountdownSecond = -1;
const ALERT_PAGE_SIZE = 5;
const UPDATE_PAGE_SIZE = 7;

const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

function updateClock() {
  const now = new Date();
  $('#clock').textContent = now.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
  $('#date').textContent = now.toLocaleDateString('pt-BR');
  $('#weekday').textContent = now.toLocaleDateString('pt-BR', {weekday:'long'});
}

function updateRefreshProgress() {
  const duration = Math.max(1000, refreshSeconds * 1000);
  const remaining = Math.max(0, nextRefresh - Date.now());
  const ratio = Math.min(1, remaining / duration);
  $('#progress').style.transform = `scaleX(${ratio})`;
  const seconds = Math.ceil(remaining / 1000);
  if (seconds !== lastCountdownSecond) {
    lastCountdownSecond = seconds;
    $('#refreshCountdown').textContent = seconds > 0 ? `próximo em ${seconds}s` : 'atualizando…';
  }
  requestAnimationFrame(updateRefreshProgress);
}

function relative(iso) {
  if (!iso) return 'sem leitura';
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso)) / 1000));
  if (seconds < 60) return `${seconds}s`;
  if (seconds < 3600) return `${Math.floor(seconds / 60)}min`;
  if (seconds < 86400) return `${Math.floor(seconds / 3600)}h`;
  return `${Math.floor(seconds / 86400)}d`;
}

function compactName(name, limit = 21) {
  const text = String(name ?? '');
  return text.length > limit ? `${text.slice(0, limit - 1)}…` : text;
}

function metricChart(rows, kind) {
  if (!rows?.length) return '<div class="empty-state">Nenhuma leitura disponível.</div>';
  return `<div class="native-chart metric-chart ${kind}">${rows.map(row => {
    const value = Math.max(0, Math.min(100, Number(row.value) || 0));
    const level = value >= 85 ? 'critical' : value >= 70 ? 'warning' : 'normal';
    return `<div class="native-row ${level}"><span class="native-label" title="${esc(row.host)}">${esc(compactName(row.host))}</span><span class="native-track"><i style="width:${value}%"></i></span><b>${value.toFixed(1)}%</b></div>`;
  }).join('')}</div>`;
}

function latencyChart(rows) {
  if (!rows?.length) return '<div class="empty-state">Nenhuma leitura disponível.</div>';
  const values = rows.map(row => Math.max(0, Number(row.value) || 0));
  const best = Math.min(...values);
  const worst = Math.max(...values);
  const average = values.reduce((total,value)=>total+value,0) / values.length;
  const highest = Math.max(50, worst);
  const scale = Math.ceil(highest / 25) * 25;
  return `<div class="latency-panel-body">
    <div class="latency-summary">
      <div class="best"><span>Melhor</span><strong>${best.toFixed(0)}<small>ms</small></strong></div>
      <div class="average"><span>Média</span><strong>${average.toFixed(0)}<small>ms</small></strong></div>
      <div class="worst"><span>Pior</span><strong>${worst.toFixed(0)}<small>ms</small></strong></div>
    </div>
    <div class="latency-list">${rows.map(row => {
    const value = Math.max(0, Number(row.value) || 0);
    const width = Math.min(100, (value / scale) * 100);
    const level = value >= 180 ? 'critical' : value >= 120 ? 'warning' : 'normal';
    return `<div class="latency-row ${level}"><span class="latency-label" title="${esc(row.host)}">${esc(compactName(row.host,16))}</span><span class="latency-meter"><i style="width:${width}%"><em></em></i></span><b>${value.toFixed(1)}<small>ms</small></b></div>`;
  }).join('')}</div></div>`;
}

function drawCharts(metrics) {
  $('#cpuChart').innerHTML = metricChart(metrics?.cpu, 'cpu');
  $('#memoryChart').innerHTML = metricChart(metrics?.memory, 'memory');
  $('#latencyChart').innerHTML = latencyChart(metrics?.latency);
}

function serviceCard(service) {
  const state = service.status === 'up' ? 'UP' : service.status === 'down' ? 'DOWN' : 'N/D';
  const initials = service.label.split(/\s+/).slice(0,2).map(word=>word[0]).join('').toUpperCase();
  return `<article class="service-card ${esc(service.status)}${service.stale ? ' stale' : ''}" title="${esc(`${service.label} · ${service.host} · ${service.item}`)}">
    <div class="service-logo"><img src="${esc(service.logo)}" alt="" referrerpolicy="no-referrer"><span>${esc(initials)}</span></div>
    <h4>${esc(service.label)}</h4><div class="service-state">${state}</div>
  </article>`;
}

function healthText(items) {
  const down = items.filter(item => item.status === 'down').length;
  const unknown = items.filter(item => item.status === 'unknown').length;
  if (down) return `${down} DOWN · ${items.length - down - unknown} UP`;
  if (unknown) return `${unknown} SEM DADOS · ${items.length - unknown} UP`;
  return `${items.length}/${items.length} OPERACIONAIS`;
}

function renderServices(services) {
  const groups = {clients:[],internal:[],tests:[]};
  services.forEach(service => groups[service.category]?.push(service));
  const priority = {down:0,unknown:1,up:2};
  Object.values(groups).forEach(items => items.sort((a,b) => priority[a.status] - priority[b.status]));
  $('#clientsGrid').innerHTML = groups.clients.map(serviceCard).join('');
  $('#internalGrid').innerHTML = groups.internal.map(serviceCard).join('');
  $('#testsGrid').innerHTML = groups.tests.map(serviceCard).join('');
  $('#clientsHealth').textContent = healthText(groups.clients);
  $('#internalHealth').textContent = healthText(groups.internal);
  $('#testsHealth').textContent = healthText(groups.tests);
  [['clientsHealth',groups.clients],['internalHealth',groups.internal],['testsHealth',groups.tests]].forEach(([id,items]) => {
    $(`#${id}`).classList.toggle('attention',items.some(item=>item.status==='down'));
  });
  document.querySelectorAll('.service-logo img').forEach(image => image.addEventListener('error',()=>image.classList.add('failed'),{once:true}));
}

function severityName(severity) {
  return ['Info','Info','Aviso','Médio','Alto','Desastre'][Number(severity)] || 'Evento';
}

function renderAlertPage() {
  if (!alerts.length) {
    $('#incidentList').innerHTML = '<div class="empty-state">✓ Nenhum problema ativo. Ambiente estável.</div>';
    $('#problemPage').textContent = 'MONITORAMENTO ZABBIX';
    return;
  }
  const pages = Math.ceil(alerts.length / ALERT_PAGE_SIZE);
  alertPage %= pages;
  const visible = alerts.slice(alertPage * ALERT_PAGE_SIZE, (alertPage + 1) * ALERT_PAGE_SIZE);
  $('#incidentList').innerHTML = visible.map(item => `<article class="problem-row">
    <span>${esc(item.host)}</span><span class="severity s${Number(item.severity)}">${severityName(item.severity)}</span>
    <span class="problem-status">PROBLEMA</span><span class="problem-message">${esc(item.message)}${item.details ? ` · ${esc(item.details)}` : ''}</span>
    <span class="problem-age">${relative(item.time)}</span><span class="problem-time">${new Date(item.time).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit',second:'2-digit'})}</span>
  </article>`).join('');
  $('#problemPage').textContent = pages > 1 ? `PÁGINA ${alertPage + 1}/${pages} · PRÓXIMA NO REFRESH` : 'MONITORAMENTO ZABBIX';
}

function renderUpdatePage() {
  if (!updates.length) {
    $('#updateList').innerHTML = '<div class="empty-state">✓ Nenhuma falha nas últimas 6 horas.</div>';
    return;
  }
  const pages = Math.ceil(updates.length / UPDATE_PAGE_SIZE);
  updatePage %= pages;
  const visible = updates.slice(updatePage * UPDATE_PAGE_SIZE,(updatePage + 1) * UPDATE_PAGE_SIZE);
  $('#updateList').innerHTML = visible.map(item => `<article class="update-row"><span class="log-icon">›_</span><div><strong>${esc(item.host)}</strong><p>${esc(item.message)}</p></div><time>${relative(item.time)}</time></article>`).join('');
}

function render(data) {
  const summary = data.summary;
  $('#totalServices').textContent = summary.services;
  $('#upServices').textContent = summary.up;
  $('#downServices').textContent = summary.down;
  $('#unknownServices').textContent = summary.unknown;
  $('#problemCount').textContent = summary.problems;
  $('#incidentBadge').textContent = summary.problems;
  $('#updatedAt').textContent = new Date(data.updatedAt).toLocaleTimeString('pt-BR',{hour12:false});
  $('#environmentLabel').textContent = data.mode === 'demo' ? 'Demonstração · dados simulados' : 'Zabbix conectado · ao vivo';
  $('#connectionState').classList.toggle('demo',data.mode === 'demo');
  $('#connectionState').classList.add('connected');
  renderServices(data.services || []);
  drawCharts(data.metrics || {});
  const cpuPeak = data.metrics?.cpu?.[0];
  const memoryPeak = data.metrics?.memory?.[0];
  const latencyPeak = data.metrics?.latency?.[0];
  $('#cpuPeak').textContent = cpuPeak ? `PICO ${Number(cpuPeak.value).toFixed(1)}% · ${compactName(cpuPeak.host,12)}` : 'SEM LEITURAS';
  $('#memoryPeak').textContent = memoryPeak ? `PICO ${Number(memoryPeak.value).toFixed(1)}% · ${compactName(memoryPeak.host,12)}` : 'SEM LEITURAS';
  $('#latencyPeak').textContent = latencyPeak ? `PICO ${Number(latencyPeak.value).toFixed(0)} MS` : 'SEM LEITURAS';
  const healthPanel = $('#healthPanel');
  healthPanel.classList.toggle('attention',summary.down > 0);
  healthPanel.classList.toggle('warning',summary.down === 0 && (summary.problems > 0 || summary.unknown > 0));
  $('#overallState').textContent = summary.down > 0 ? 'INCIDENTE' : summary.problems > 0 ? 'ATENÇÃO' : summary.unknown > 0 ? 'DEGRADADO' : 'ESTÁVEL';
  $('#overallDetail').textContent = summary.down > 0 ? `${summary.down} serviço${summary.down === 1 ? '' : 's'} down` : summary.problems > 0 ? `${summary.problems} evento${summary.problems === 1 ? '' : 's'} ativo${summary.problems === 1 ? '' : 's'}` : summary.unknown > 0 ? `${summary.unknown} sem leitura` : 'Operação normal';
  alerts = data.alerts || [];
  updates = data.updates || [];
  alertPage = renderedOnce && alerts.length > ALERT_PAGE_SIZE ? alertPage + 1 : 0;
  updatePage = 0;
  renderAlertPage(); renderUpdatePage();
  if (data.settings) {
    refreshSeconds = Number(data.settings.refreshSeconds) || 30;
    $('#companyName').textContent = data.settings.companyName;
    $('#companyLogo').src = data.settings.companyLogo;
  }
  renderedOnce = true;
  nextRefresh = Date.now() + refreshSeconds * 1000;
}

function showError(message) {
  const banner = $('#errorBanner');
  banner.textContent = message; banner.classList.add('show');
  $('#connectionState').classList.remove('connected');
  $('#environmentLabel').textContent = 'Falha na conexão com o Zabbix';
  clearTimeout(showError.timer); showError.timer = setTimeout(()=>banner.classList.remove('show'),9000);
}

async function load() {
  if (loading) return;
  loading = true;
  try {
    const response = await fetch(`api.php?t=${Date.now()}`,{headers:{Accept:'application/json'}});
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.message || 'Falha ao consultar o Zabbix.');
    render(data);
  } catch (error) { showError(error.message); }
  finally { loading=false; nextRefresh=Date.now()+refreshSeconds*1000; }
}

setInterval(updateClock,1000);
setInterval(()=>{if(Date.now()>=nextRefresh)load()},1000);
setInterval(()=>{if(updates.length>UPDATE_PAGE_SIZE){updatePage++;renderUpdatePage()}},10000);
updateClock();
updateRefreshProgress();
load();
