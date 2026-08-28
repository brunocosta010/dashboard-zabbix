# Dashboard PHP para Zabbix

Requisitos: PHP 8.1 ou superior.

```bash
cd zabbix-dashboard
php -S localhost:8080
```

Abra `http://localhost:8080` no navegador.

## Conectar ao Zabbix depois

1. No Zabbix, crie uma conta de serviço com acesso somente de leitura aos grupos de hosts desejados.
2. Em **Usuários > Tokens de API** (o caminho pode variar conforme a versão), gere um token para essa conta.
3. Copie `.env.example` para `.env`.
4. No `.env`, altere `ZABBIX_MODE=live`, informe `ZABBIX_URL` e `ZABBIX_API_TOKEN`.
5. Garanta que a extensão PHP cURL esteja habilitada e reinicie o servidor PHP.