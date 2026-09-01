const $ = selector => document.querySelector(selector);
const $$ = selector => [...document.querySelectorAll(selector)];
let refreshSeconds = 30;
let nextRefresh = Date.now() + refreshSeconds * 1000;
let services = [];
let activeFilter = 'all';
let loading = false;

const esc = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
const normalize = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

function updateClock() {
  const now = new Date();
  $('#clock').textContent = now.toLocaleTimeString('pt-BR', {hour12:false});
  $('#date').textContent = now.toLocaleDateString('pt-BR', {weekday:'short', day:'2-digit', month:'short'}).replace('.', '');
  const remaining = Math.max(0, (nextRefresh - Date.now()) / (refreshSeconds * 1000));
  $('#progress').style.transform = `scaleX(${remaining})`;
}

function relative(iso) {
  if (!iso) return 'sem leitura';
  const seconds = Math.max(0, Math.floor((Date.now() - new Date(iso)) / 1000));
  if (seconds < 60) return `há ${seconds}s`;
  if (seconds < 3600) return `há ${Math.floor(seconds / 60)}min`;
  if (seconds < 86400) return `há ${Math.floor(seconds / 3600)}h`;
  return `há ${Math.floor(seconds / 86400)}d`;
}

function metricClass(value) {
  return value >= 85 ? 'critical' : value >= 70 ? 'warning' : 'healthy';
}

function ranking(rows, emptyMessage) {
  if (!rows?.length) return `<div class="empty-state"><span>—</span><p>${esc(emptyMessage)}</p></div>`;
  return rows.map((row, index) => {
    const value = Number(row.value) || 0;
    return `<div class="rank-row ${metricClass(value)}">
      <span class="rank-number">${String(index + 1).padStart(2, '0')}</span>
      <div class="rank-main"><div><strong title="${esc(row.host)}">${esc(row.host)}</strong><b>${value.toFixed(value % 1 ? 1 : 0)}${esc(row.unit)}</b></div><span class="bar"><i style="width:${Math.min(100, value)}%"></i></span></div>
    </div>`;
  }).join('');
}

function latencyView(rows) {
  if (!rows?.length) return '<div class="empty-state"><span>—</span><p>Nenhum item com “Ping” encontrado no pfsense_novo.</p></div>';
  const primary = rows[0];
  const value = Number(primary.value) || 0;
  const angle = Math.min(360, (value / 200) * 360);
  return `<div class="latency-gauge ${value >= 150 ? 'critical' : value >= 80 ? 'warning' : 'healthy'}" style="--gauge:${angle}deg">
    <div><strong>${value.toFixed(value < 10 ? 1 : 0)}</strong><span>ms</span></div>
  </div>
  <div class="latency-meta"><strong>${esc(primary.host)}</strong><span>Atualizado ${relative(primary.lastCheck)}</span></div>
  <div class="latency-pills">${rows.slice(1, 4).map(row => `<span title="${esc(row.host)}"><i></i>${Number(row.value).toFixed(1)} ms</span>`).join('')}</div>`;
}

function serviceCard(service) {
  const statusLabel = service.status === 'up' ? 'Operacional' : service.status === 'down' ? 'Indisponível' : 'Sem dados';
  const initials = service.label.split(/\s+/).slice(0, 2).map(word => word[0]).join('').toUpperCase();
  return `<article class="service-card ${esc(service.status)}${service.stale ? ' stale' : ''}" data-category="${esc(service.category)}" data-search="${esc(normalize(`${service.label} ${service.host} ${service.group}`))}">
    <div class="service-top"><div class="service-logo"><img src="${esc(service.logo)}" alt="" loading="lazy" referrerpolicy="no-referrer"><span>${esc(initials)}</span></div><span class="status-dot"><i></i>${statusLabel}</span></div>
    <div class="service-copy"><h3>${esc(service.label)}</h3><p>${esc(service.host)}</p></div>
    <div class="service-foot"><span title="${esc(service.item)}">${esc(service.item)}</span><time>${relative(service.lastCheck)}</time></div>
  </article>`;
}

function renderServices() {
  const query = normalize($('#serviceSearch').value);
  const filtered = services.filter(service => (activeFilter === 'all' || service.category === activeFilter) && normalize(`${service.label} ${service.host} ${service.group}`).includes(query));
  $('#serviceGrid').innerHTML = filtered.length ? filtered.map(serviceCard).join('') : '<div class="empty-state wide"><span>⌕</span><p>Nenhum serviço encontrado para este filtro.</p></div>';
  $$('.service-logo img').forEach(image => {
    image.addEventListener('error', () => image.classList.add('failed'), {once:true});
  });
}

function severityName(severity) {
  return ['Não classificado','Informação','Aviso','Médio','Alto','Desastre'][Number(severity)] || 'Evento';
}

function incidentRow(item) {
  return `<article class="incident severity-${Number(item.severity)}">
    <span class="severity-mark"><i></i></span>
    <div class="incident-copy"><div><strong>${esc(item.message)}</strong>${item.acknowledged ? '<span class="ack">Reconhecido</span>' : ''}</div><p><b>${esc(item.host)}</b>${item.details ? ` · ${esc(item.details)}` : ''}</p></div>
    <div class="incident-meta"><span>${severityName(item.severity)}</span><time>${relative(item.time)}</time></div>
  </article>`;
}

function updateRow(item) {
  return `<article class="update-row"><span class="log-icon">›_</span><div><strong>${esc(item.host)}</strong><p>${esc(item.message)}</p></div><time>${relative(item.time)}</time></article>`;
}

function render(data) {
  const summary = data.summary;
  services = data.services || [];
  $('#totalServices').textContent = summary.services;
  $('#upServices').textContent = summary.up;
  $('#downServices').textContent = summary.down;
  $('#problemCount').textContent = summary.problems;
  $('#updatedAt').textContent = new Date(data.updatedAt).toLocaleTimeString('pt-BR', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
  $('#environmentLabel').textContent = data.mode === 'demo' ? 'Demonstração · dados simulados' : 'Zabbix conectado · operação ao vivo';
  $('#connectionState').classList.toggle('demo', data.mode === 'demo');
  $('#connectionState').classList.add('connected');

  const healthy = summary.down === 0 && summary.problems === 0;
  $('#healthLabel').textContent = healthy ? 'Todos os sistemas operacionais' : summary.down ? `${summary.down} serviço${summary.down === 1 ? '' : 's'} requer atenção` : `${summary.problems} problema${summary.problems === 1 ? '' : 's'} em acompanhamento`;
  $('#healthDetail').textContent = `${summary.up} online · ${summary.unknown} sem dados · ${summary.problems} problemas ativos`;
  $('.hero-status').classList.toggle('attention', !healthy);

  $('#cpuRanking').innerHTML = ranking(data.metrics?.cpu, 'Nenhum item “CPU utilization” encontrado.');
  $('#memoryRanking').innerHTML = ranking(data.metrics?.memory, 'Nenhum item “Memory utilization” encontrado.');
  $('#latencyContent').innerHTML = latencyView(data.metrics?.latency);

  const counts = category => services.filter(service => category === 'all' || service.category === category).length;
  $('#countAll').textContent = counts('all'); $('#countClients').textContent = counts('clients');
  $('#countInternal').textContent = counts('internal'); $('#countTests').textContent = counts('tests');
  renderServices();

  $('#incidentBadge').textContent = `${summary.problems} ${summary.problems === 1 ? 'ativo' : 'ativos'}`;
  $('#incidentList').innerHTML = data.alerts?.length ? data.alerts.map(incidentRow).join('') : '<div class="empty-state"><span>✓</span><p>Nenhum problema ativo. Ambiente estável.</p></div>';
  $('#updateList').innerHTML = data.updates?.length ? data.updates.map(updateRow).join('') : '<div class="empty-state"><span>✓</span><p>Nenhuma falha de atualização nas últimas 6 horas.</p></div>';

  if (data.settings) {
    refreshSeconds = Number(data.settings.refreshSeconds) || 30;
    $('#companyName').textContent = data.settings.companyName;
    $('#companyLogo').src = data.settings.companyLogo;
  }
  $('#refreshInterval').textContent = `${refreshSeconds}s`;
  nextRefresh = Date.now() + refreshSeconds * 1000;
}

function showError(message) {
  const banner = $('#errorBanner');
  banner.textContent = message;
  banner.classList.add('show');
  $('#connectionState').classList.remove('connected');
  $('#environmentLabel').textContent = 'Falha na conexão com o Zabbix';
  clearTimeout(showError.timer);
  showError.timer = setTimeout(() => banner.classList.remove('show'), 9000);
}

async function load() {
  if (loading) return;
  loading = true;
  $('#refreshButton').classList.add('loading');
  try {
    const response = await fetch(`api.php?t=${Date.now()}`, {headers:{Accept:'application/json'}});
    const data = await response.json();
    if (!response.ok || data.error) throw new Error(data.message || 'Falha ao consultar o Zabbix.');
    render(data);
  } catch (error) {
    showError(error.message);
  } finally {
    loading = false;
    $('#refreshButton').classList.remove('loading');
    nextRefresh = Date.now() + refreshSeconds * 1000;
  }
}

$('#serviceTabs').addEventListener('click', event => {
  const button = event.target.closest('button[data-filter]');
  if (!button) return;
  activeFilter = button.dataset.filter;
  $$('#serviceTabs button').forEach(item => item.classList.toggle('active', item === button));
  renderServices();
});
$('#serviceSearch').addEventListener('input', renderServices);
$('#refreshButton').addEventListener('click', load);
setInterval(updateClock, 1000);
setInterval(() => { if (Date.now() >= nextRefresh) load(); }, 1000);
updateClock();
load();
