# Zabbix Operations Dashboard

Dashboard PHP responsivo conectado diretamente à API do Zabbix. A interface reproduz o inventário e os itens do dashboard Grafana de referência, com uma apresentação moderna e otimizada para um command center.

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
