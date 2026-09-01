# Zabbix Operations Dashboard

Dashboard PHP conectado diretamente à API do Zabbix. A interface reproduz o inventário e os itens do dashboard Grafana de referência em uma composição densa para TV 16:9 e modo kiosk, sem depender de rolagem ou interação com mouse.

## Interface kiosk

- Grade fixa de 24 colunas com GridStack.js 13.2.0.
- Gráficos compactos de CPU, memória e latência com Apache ECharts 6.1.0.
- 37 serviços simultaneamente visíveis, divididos entre clientes, internos e testes.
- Serviços indisponíveis e sem dados são movidos automaticamente para o início de cada grupo.
- Painel de saúde geral e indicadores de pico facilitam a leitura à distância.
- Tabela de problemas e logs com paginação automática a cada 10 segundos.
- Bibliotecas armazenadas em `assets/vendor`, sem dependência de CDN durante a execução.
- Layout principal dimensionado automaticamente para ocupar uma tela 1920×1080 sem scroll.

## Métricas monitoradas

- Top 10 de `CPU utilization` e `Memory utilization`.
- Itens contendo `Ping` no host `pfsense_novo`.
- Disponibilidade dos clientes, serviços internos e bases de teste definidos em `config.php`.
- Problemas ativos do Zabbix.
- Histórico de 6 horas do item `Falha Atualizador Idempiere` no host `SSH`.

## Executar

Requer PHP 8.1 ou superior com as extensões cURL e JSON.

```bash
php -S localhost:8080
```

Acesse `http://localhost:8080`.

## Configuração

O arquivo `.env` controla a conexão e não deve ser versionado:

```dotenv
ZABBIX_MODE=live
ZABBIX_URL=https://zabbix.exemplo.com/api_jsonrpc.php
ZABBIX_API_TOKEN=seu-token
ZABBIX_VERIFY_SSL=true
COMPANY_NAME=Minha Empresa
COMPANY_LOGO=assets/company-logo.svg
DASHBOARD_REFRESH_SECONDS=30
APP_TIMEZONE=America/Sao_Paulo
```

Use um token de uma conta de serviço com acesso somente de leitura aos grupos monitorados. Para testar a interface sem consultar o Zabbix, altere `ZABBIX_MODE=demo`.
