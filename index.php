<?php
declare(strict_types=1);
$config = require __DIR__ . '/config.php';
$company = htmlspecialchars($config['company_name'], ENT_QUOTES, 'UTF-8');
$logo = htmlspecialchars($config['company_logo'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#071018">
  <title><?= $company ?> — Operação de infraestrutura</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css">
</head>
<body>
  <main class="dashboard">
    <header class="header">
      <div class="identity"><img id="companyLogo" src="<?= $logo ?>" alt="Logo <?= $company ?>"><div><strong id="companyName"><?= $company ?></strong><span>CENTRAL DE OPERAÇÕES</span></div></div>
      <div class="environment"><i></i><span id="environmentLabel">Conectando ao Zabbix</span></div>
      <div class="time"><strong id="clock">--:--:--</strong><span id="date">--</span></div>
    </header>
    <section class="summary" aria-label="Resumo da infraestrutura">
      <article><span class="summary-icon">▦</span><div><b id="totalHosts">—</b><small>HOSTS</small></div></article>
      <article class="positive"><span class="summary-icon">↑</span><div><b id="upHosts">—</b><small>UP</small></div></article>
      <article class="negative"><span class="summary-icon">↓</span><div><b id="downHosts">—</b><small>DOWN</small></div></article>
      <article class="warning"><span class="summary-icon">!</span><div><b id="problemCount">—</b><small>PROBLEMAS</small></div></article>
      <div class="updated"><span id="progress"></span><small>ÚLTIMA ATUALIZAÇÃO</small><b id="updatedAt">aguardando</b></div>
    </section>
    <section class="hosts-section">
      <div class="section-title"><div><span>STATUS EM TEMPO REAL</span><h1>Servidores</h1></div><div class="legend"><span><i class="up"></i>UP</span><span><i class="down"></i>DOWN</span><span><i class="unknown"></i>SEM DADOS</span></div></div>
      <div class="host-grid" id="hostGrid"><div class="loading">Carregando hosts…</div></div>
    </section>
    <section class="incidents">
      <div class="incidents-head"><div><span>ATENÇÃO NECESSÁRIA</span><h2>Problemas e alertas</h2></div><b id="incidentBadge">0 ativos</b></div>
      <div class="incident-list" id="incidentList"><div class="loading">Carregando eventos…</div></div>
    </section>
  </main>
  <div class="error-banner" id="errorBanner" role="alert"></div>
  <script src="assets/app.js" defer></script>
</body>
</html>