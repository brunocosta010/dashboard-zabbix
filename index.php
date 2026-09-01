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
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
  <meta name="theme-color" content="#0b0e14">
  <title><?= $company ?> — Kiosk Operations</title>
  <link rel="stylesheet" href="assets/vendor/gridstack.min.css">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <header class="kiosk-header">
    <div class="brand"><span class="brand-mark"><img id="companyLogo" src="<?= $logo ?>" alt=""></span><div><strong id="companyName"><?= $company ?></strong><span>Operations center</span></div></div>
    <div class="connection" id="connectionState"><i></i><span id="environmentLabel">Conectando ao Zabbix</span></div>
    <div class="header-summary" aria-label="Resumo operacional">
      <span><i class="dot up"></i><b id="upServices">—</b> online</span>
      <span><i class="dot down"></i><b id="downServices">—</b> down</span>
      <span><i class="dot unknown"></i><b id="unknownServices">—</b> sem dados</span>
      <span><i class="dot problem"></i><b id="problemCount">—</b> problemas</span>
    </div>
    <div class="sync"><span>Atualizado</span><b id="updatedAt">--:--:--</b><i class="sync-track"><em id="progress"></em></i></div>
  </header>

  <main class="grid-stack kiosk-grid" id="kioskGrid">
    <section class="grid-stack-item" gs-x="0" gs-y="0" gs-w="6" gs-h="7"><div class="grid-stack-item-content panel">
      <header class="panel-title"><div><span class="accent cpu"></span><h2>Uso de CPU</h2></div><small id="cpuPeak">TOP 10 · ÚLTIMA LEITURA</small></header>
      <div class="chart" id="cpuChart" role="img" aria-label="Top 10 de uso de CPU"></div>
    </div></section>

    <section class="grid-stack-item" gs-x="0" gs-y="7" gs-w="6" gs-h="7"><div class="grid-stack-item-content panel">
      <header class="panel-title"><div><span class="accent memory"></span><h2>Uso de memória</h2></div><small id="memoryPeak">TOP 10 · ÚLTIMA LEITURA</small></header>
      <div class="chart" id="memoryChart" role="img" aria-label="Top 10 de uso de memória"></div>
    </div></section>

    <section class="grid-stack-item" gs-x="6" gs-y="0" gs-w="11" gs-h="14"><div class="grid-stack-item-content panel services-panel">
      <header class="panel-title"><div><span class="accent services"></span><h2>Serviços monitorados</h2></div><small><b id="totalServices">—</b> ITENS DO INVENTÁRIO</small></header>
      <div class="service-groups">
        <section class="service-group clients-group"><header><h3>Clientes</h3><span id="clientsHealth">—</span></header><div class="mini-grid clients" id="clientsGrid"></div></section>
        <section class="service-group internal-group"><header><h3>Internos</h3><span id="internalHealth">—</span></header><div class="mini-grid internal" id="internalGrid"></div></section>
        <section class="service-group tests-group"><header><h3>Bases de teste</h3><span id="testsHealth">—</span></header><div class="mini-grid tests" id="testsGrid"></div></section>
      </div>
    </div></section>

    <section class="grid-stack-item" gs-x="17" gs-y="0" gs-w="5" gs-h="6"><div class="grid-stack-item-content panel">
      <header class="panel-title"><div><span class="accent latency"></span><h2>Latência</h2></div><small id="latencyPeak">PFSENSE_NOVO · MS</small></header>
      <div class="chart" id="latencyChart" role="img" aria-label="Latência do pfSense"></div>
    </div></section>

    <section class="grid-stack-item" gs-x="22" gs-y="0" gs-w="2" gs-h="4"><div class="grid-stack-item-content panel time-panel">
      <span id="date">--/--/----</span><strong id="clock">--:--</strong><small id="weekday">---</small>
    </div></section>

    <section class="grid-stack-item" gs-x="22" gs-y="4" gs-w="2" gs-h="2"><div class="grid-stack-item-content panel health-panel" id="healthPanel">
      <i></i><div><strong id="overallState">ANALISANDO</strong><span id="overallDetail">Aguardando dados</span></div>
    </div></section>

    <section class="grid-stack-item" gs-x="17" gs-y="6" gs-w="7" gs-h="8"><div class="grid-stack-item-content panel updates-panel">
      <header class="panel-title"><div><span class="accent updates"></span><h2>Falhas de atualização</h2></div><small>SSH · ÚLTIMAS 6 HORAS</small></header>
      <div class="update-list" id="updateList"><div class="loading">Carregando registros…</div></div>
    </div></section>

    <section class="grid-stack-item" gs-x="0" gs-y="14" gs-w="24" gs-h="8"><div class="grid-stack-item-content panel problems-panel">
      <header class="panel-title"><div><span class="accent problems"></span><h2>Problemas ativos</h2><span class="problem-badge" id="incidentBadge">0</span></div><small id="problemPage">MONITORAMENTO ZABBIX</small></header>
      <div class="problem-table">
        <div class="problem-head"><span>Host</span><span>Severidade</span><span>Status</span><span>Problema</span><span>Idade</span><span>Horário</span></div>
        <div class="problem-body" id="incidentList"><div class="loading">Carregando eventos…</div></div>
      </div>
    </div></section>
  </main>

  <div class="error-banner" id="errorBanner" role="alert"></div>
  <script src="assets/vendor/gridstack-all.js"></script>
  <script src="assets/vendor/echarts.min.js"></script>
  <script src="assets/app.js"></script>
</body>
</html>
