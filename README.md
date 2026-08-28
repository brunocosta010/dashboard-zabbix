<<<<<<< HEAD
# Pulse — Dashboard PHP para Zabbix

Protótipo moderno de monitoramento de servidores. Ele inicia em **modo demonstração**, portanto funciona antes de você criar o token da API.

## Testar agora

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

Nunca envie o token pelo chat nem o coloque em `app.js`, `index.php` ou em um repositório. O arquivo `.env` já está ignorado pelo Git.

## Escopo desta primeira versão

- Dados simulados para validação visual imediata
- Resumo de disponibilidade e problemas
- Tabela pesquisável de servidores e uso de recursos
- Alertas recentes
- Atualização automática a cada 60 segundos
- Cliente JSON-RPC preparado para autenticação por Bearer token
- Layout responsivo para desktop e celular

Na integração real inicial, CPU, memória, disco e uptime aparecem como `—`. Esses indicadores dependem das chaves de itens/templates usados no seu Zabbix; devem ser mapeados na segunda etapa conforme o ambiente.
=======
# dashboard-zabbix
Criação de um painel utilizando a API oficial do zabbix integrado ao PHP.
>>>>>>> bdf5c88b9411cf4853bc0f45bc6d2298997abdf9
