<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
$company = htmlspecialchars($config['company_name'], ENT_QUOTES, 'UTF-8');
$logo = htmlspecialchars($config['company_logo'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#08111f">
  <title><?= $company ?> — Command Center</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Roboto+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
  <main class="shell">
    <header class="topbar">
      <div class="brand">
        <span class="brand-mark"><img id="companyLogo" src="<?= $logo ?>" alt=""></span>
        <div><strong id="companyName"><?= $company ?></strong><span>Infrastructure command center</span></div>
      </div>
      <div class="live-pill" id="connectionState"><i></i><span id="environmentLabel">Conectando ao Zabbix</span></div>
      <div class="top-actions">
        <div class="last-sync"><span>Última sincronização</span><strong id="updatedAt">aguardando</strong></div>
        <button class="icon-button" id="refreshButton" type="button" aria-label="Atualizar agora" title="Atualizar agora">
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11a8.1 8.1 0 0 0-15.5-2M4 4v5h5M4 13a8.1 8.1 0 0 0 15.5 2M20 20v-5h-5"/></svg>
        </button>
        <div class="clock"><strong id="clock">--:--:--</strong><span id="date">--</span></div>
      </div>
    </header>

    <section class="hero">
      <div><span class="eyebrow">VISÃO GERAL DA OPERAÇÃO</span><h1>Saúde da infraestrutura</h1><p>Monitoramento consolidado de clientes, serviços internos e ambientes de teste.</p></div>
      <div class="hero-status"><span class="pulse"></span><div><b id="healthLabel">Carregando dados</b><small id="healthDetail">Aguarde a primeira sincronização</small></div></div>
    </section>

    <section class="summary-grid" aria-label="Resumo da infraestrutura">
      <article class="summary-card total"><span class="summary-icon"><svg viewBox="0 0 24 24"><rect x="4" y="4" width="16" height="6" rx="2"/><rect x="4" y="14" width="16" height="6" rx="2"/><path d="M8 7h.01M8 17h.01"/></svg></span><div><b id="totalServices">—</b><small>Serviços monitorados</small></div><span class="trend neutral">inventário do JSON</span></article>
      <article class="summary-card success"><span class="summary-icon"><svg viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span><div><b id="upServices">—</b><small>Operacionais</small></div><span class="trend">online</span></article>
      <article class="summary-card danger"><span class="summary-icon"><svg viewBox="0 0 24 24"><path d="M12 8v5M12 17h.01"/><circle cx="12" cy="12" r="9"/></svg></span><div><b id="downServices">—</b><small>Indisponíveis</small></div><span class="trend">atenção</span></article>
      <article class="summary-card warning"><span class="summary-icon"><svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 4.2 2.7 17.4A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.6L13.7 4.2a2 2 0 0 0-3.4 0Z"/></svg></span><div><b id="problemCount">—</b><small>Problemas ativos</small></div><span class="trend">Zabbix</span></article>
    </section>

    <section class="metrics-grid">
      <article class="panel metric-panel"><div class="panel-heading"><div><span class="eyebrow">TOP 10</span><h2>Uso de CPU</h2></div><span class="panel-unit">%</span></div><div class="ranking" id="cpuRanking"><div class="skeleton tall"></div></div></article>
      <article class="panel metric-panel"><div class="panel-heading"><div><span class="eyebrow">TOP 10</span><h2>Uso de memória</h2></div><span class="panel-unit">%</span></div><div class="ranking" id="memoryRanking"><div class="skeleton tall"></div></div></article>
      <article class="panel latency-panel"><div class="panel-heading"><div><span class="eyebrow">PFSENSE_NOVO</span><h2>Latência</h2></div><span class="panel-unit">ms</span></div><div class="latency-content" id="latencyContent"><div class="skeleton tall"></div></div></article>
    </section>

    <section class="services-section">
      <div class="section-heading">
        <div><span class="eyebrow">DISPONIBILIDADE EM TEMPO REAL</span><h2>Serviços e ambientes</h2></div>
        <div class="service-tools">
          <label class="search"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg><input id="serviceSearch" type="search" placeholder="Buscar serviço" autocomplete="off"></label>
          <div class="tabs" id="serviceTabs" role="tablist"><button class="active" data-filter="all" type="button">Todos <b id="countAll">0</b></button><button data-filter="clients" type="button">Clientes <b id="countClients">0</b></button><button data-filter="internal" type="button">Internos <b id="countInternal">0</b></button><button data-filter="tests" type="button">Testes <b id="countTests">0</b></button></div>
        </div>
      </div>
      <div class="service-grid" id="serviceGrid"><div class="skeleton cards"></div></div>
    </section>

    <section class="operations-grid">
      <article class="panel incidents-panel">
        <div class="panel-heading"><div><span class="eyebrow">EVENTOS DO ZABBIX</span><h2>Problemas ativos</h2></div><span class="count-pill" id="incidentBadge">0 ativos</span></div>
        <div class="incident-list" id="incidentList"><div class="skeleton tall"></div></div>
      </article>
      <article class="panel updates-panel">
        <div class="panel-heading"><div><span class="eyebrow">SSH · ÚLTIMAS 6 HORAS</span><h2>Falhas de atualização</h2></div><span class="panel-unit">log</span></div>
        <div class="update-list" id="updateList"><div class="skeleton tall"></div></div>
      </article>
    </section>

    <footer><span>Dados atualizados automaticamente a cada <b id="refreshInterval">30s</b></span><span class="refresh-track"><i id="progress"></i></span><span>Zabbix Operations Dashboard</span></footer>
  </main>
  <div class="error-banner" id="errorBanner" role="alert"></div>
  <script src="assets/app.js" defer></script>
</body>
</html>
