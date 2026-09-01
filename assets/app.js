const $ = selector => document.querySelector(selector);
let refreshSeconds = 30;
let nextRefresh = Date.now() + refreshSeconds * 1000;
let loading = false;
let alerts = [];
let updates = [];
let alertPage = 0;
let updatePage = 0;
const ALERT_PAGE_SIZE = 6;
const UPDATE_PAGE_SIZE = 7;
const charts = {};

const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));

function gridCellHeight() {
  return Math.max(28, Math.floor((window.innerHeight - 50) / 22));
}

const kioskGrid = typeof GridStack !== 'undefined' ? GridStack.init({
  column: 24,
  cellHeight: gridCellHeight(),
  margin: 0,
  float: false,
  staticGrid: true,
  animate: false,
  disableOneColumnMode: true,
}, '#kioskGrid') : null;

function updateClock() {
  const now = new Date();
  $('#clock').textContent = now.toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit'});
  $('#date').textContent = now.toLocaleDateString('pt-BR');
  $('#weekday').textContent = now.toLocaleDateString('pt-BR', {weekday:'long'});
  const remaining = Math.max(0, (nextRefresh - Date.now()) / (refreshSeconds * 1000));
  $('#progress').style.transform = `scaleX(${remaining})`;
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

function metricColor(value) {
  if (value >= 85) return '#f2495c';
  if (value >= 70) return '#ff9830';
  return '#73bf69';
}

function baseChartOption(rows, kind) {
  const data = [...(rows || [])].reverse();
  return {
    animation: false,
    silent: true,
    grid: {left: 108, right: 43, top: 7, bottom: 7, containLabel: false},
    xAxis: {type:'value', min:0, max:100, show:false},
    yAxis: {
      type:'category',
      data:data.map(row => compactName(row.host)),
      axisLine:{show:false}, axisTick:{show:false},
      axisLabel:{color:'#c3ccd7', fontSize:8, width:100, overflow:'truncate', align:'right', margin:8},
    },
    series:[{
      type:'bar', barWidth:9, showBackground:true,
      backgroundStyle:{color:'#20262e', borderRadius:2},
      itemStyle:{borderRadius:2, color:params => kind === 'cpu'
        ? new echarts.graphic.LinearGradient(0,0,1,0,[{offset:0,color:'#73bf69'},{offset:.7,color:'#fade2a'},{offset:1,color:'#ff9830'}])
        : metricColor(params.value)},
      label:{show:true, position:'right', distance:7, fontFamily:'Consolas', fontSize:8, formatter:params => `{${params.value >= 85 ? 'bad' : params.value >= 70 ? 'warn' : 'ok'}|${Number(params.value).toFixed(1)}%}`, rich:{ok:{color:'#73bf69'},warn:{color:'#ffb357'},bad:{color:'#ff6174'}}},
      data:data.map(row => Number(row.value)),
    }],
  };
}

function latencyOption(rows) {
  const data = [...(rows || [])].reverse();
  const highest = Math.max(50, ...data.map(row => Number(row.value) || 0));
  const max = Math.ceil(highest / 25) * 25;
  return {
    animation:false, silent:true,
    grid:{left:82,right:44,top:7,bottom:7},
    xAxis:{type:'value',min:0,max,show:false},
    yAxis:{type:'category',data:data.map(row=>compactName(row.host,16)),axisLine:{show:false},axisTick:{show:false},axisLabel:{color:'#d0d6de',fontSize:7,width:75,overflow:'truncate',margin:6}},
    series:[{
      type:'pictorialBar',symbol:'rect',symbolRepeat:true,symbolClip:true,symbolSize:[5,9],symbolMargin:1,symbolBoundingData:max,
      itemStyle:{color:params => params.value >= 180 ? '#f2495c' : params.value >= 120 ? '#ff9830' : '#73bf69'},
      label:{show:true,position:'right',distance:6,color:'#ff806f',fontFamily:'Consolas',fontWeight:600,fontSize:8,formatter:'{c} ms'},
      data:data.map(row=>Number(row.value)),
    }],
  };
}

function drawCharts(metrics) {
  if (typeof echarts === 'undefined') return;
  const definitions = [
    ['cpu','cpuChart',baseChartOption(metrics?.cpu,'cpu')],
    ['memory','memoryChart',baseChartOption(metrics?.memory,'memory')],
    ['latency','latencyChart',latencyOption(metrics?.latency)],
  ];
  definitions.forEach(([key,id,option]) => {
    if (!charts[key]) charts[key] = echarts.init(document.getElementById(id), null, {renderer:'canvas'});
    charts[key].setOption(option, true);
  });
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
  $('#clientsGrid').innerHTML = groups.clients.map(serviceCard).join('');
  $('#internalGrid').innerHTML = groups.internal.map(serviceCard).join('');
  $('#testsGrid').innerHTML = groups.tests.map(serviceCard).join('');
  $('#clientsHealth').textContent = healthText(groups.clients);
  $('#internalHealth').textContent = healthText(groups.internal);
  $('#testsHealth').textContent = healthText(groups.tests);
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
  $('#problemPage').textContent = pages > 1 ? `PÁGINA ${alertPage + 1}/${pages} · ROTAÇÃO AUTOMÁTICA` : 'MONITORAMENTO ZABBIX';
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
  alerts = data.alerts || []; updates = data.updates || []; alertPage = 0; updatePage = 0;
  renderAlertPage(); renderUpdatePage();
  if (data.settings) {
    refreshSeconds = Number(data.settings.refreshSeconds) || 30;
    $('#companyName').textContent = data.settings.companyName;
    $('#companyLogo').src = data.settings.companyLogo;
  }
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

let resizeTimer;
window.addEventListener('resize',()=>{
  clearTimeout(resizeTimer);
  resizeTimer=setTimeout(()=>{
    if (kioskGrid && window.innerWidth > 900) kioskGrid.cellHeight(gridCellHeight());
    Object.values(charts).forEach(chart=>chart.resize());
  },120);
});
setInterval(updateClock,1000);
setInterval(()=>{if(Date.now()>=nextRefresh)load()},1000);
setInterval(()=>{if(alerts.length>ALERT_PAGE_SIZE){alertPage++;renderAlertPage()} if(updates.length>UPDATE_PAGE_SIZE){updatePage++;renderUpdatePage()}},10000);
updateClock();
load();
