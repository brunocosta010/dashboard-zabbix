<?php
declare(strict_types=1);
function loadEnv(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (getenv($key) === false) putenv($key . '=' . trim($value, chr(34) . chr(39)));
    }
}
function envList(string $name, string $fallback = '', string $separator = ';'): array
{
    $value = getenv($name);
    return array_values(array_filter(array_map('trim', explode($separator, $value === false ? $fallback : $value))));
}
loadEnv(__DIR__ . '/.env');
$timezone = getenv('APP_TIMEZONE') ?: 'America/Sao_Paulo';
if (in_array($timezone, timezone_identifiers_list(), true)) date_default_timezone_set($timezone);
return [
    'mode' => strtolower(getenv('ZABBIX_MODE') ?: 'demo'),
    'url' => rtrim(getenv('ZABBIX_URL') ?: '', '/'),
    'token' => getenv('ZABBIX_API_TOKEN') ?: '',
    'verify_ssl' => filter_var(getenv('ZABBIX_VERIFY_SSL') ?: 'true', FILTER_VALIDATE_BOOL),
    'ca_bundle' => getenv('ZABBIX_CA_BUNDLE') ?: '',
    'timeout' => max(2, (int) (getenv('ZABBIX_TIMEOUT') ?: 10)),
    'company_name' => getenv('COMPANY_NAME') ?: 'Minha Empresa',
    'company_logo' => getenv('COMPANY_LOGO') ?: 'assets/company-logo.svg',
    'refresh_seconds' => max(10, (int) (getenv('DASHBOARD_REFRESH_SECONDS') ?: 30)),
    'group_ids' => envList('ZABBIX_GROUP_IDS', '', ','),
    'item_keys' => [
        'cpu' => envList('ZABBIX_CPU_KEYS', 'system.cpu.util;system.cpu.util[,idle]'),
        'memory' => envList('ZABBIX_MEMORY_KEYS', 'vm.memory.util;vm.memory.size[pused]'),
        'latency' => envList('ZABBIX_LATENCY_KEYS', 'icmppingsec'),
        'status' => envList('ZABBIX_STATUS_KEYS', 'agent.ping;icmpping'),
    ],
    // Inventário reproduzido do dashboard Grafana enviado como referência.
    // Os nomes de grupo, host e item são usados literalmente nas consultas ao Zabbix.
    'dashboard' => [
        'metric_items' => [
            'cpu' => 'CPU utilization',
            'memory' => 'Memory utilization',
            'latency_host' => 'pfsense_novo',
            'latency_item' => 'Ping',
            'update_host' => 'SSH',
            'update_item' => 'Falha Atualizador Idempiere',
        ],
        'services' => [
            ['category'=>'clients','group'=>'Linux servers','host'=>'devco','item'=>'Status do idempiere service','label'=>'Devco ERP','logo'=>'https://i.ibb.co/B5KW4ZRG/ERP.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'atacarejo','item'=>'Status do idempiere service','label'=>'Atacarejo','logo'=>'https://atacarejo.erp.devco.cloud/webui/zkau/web/8ca4fecc/theme/iceblue_c/images/login-logo-atacarejo.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'Ferro e Aço','item'=>'Status do idempiere service','label'=>'Ferro e Aço','logo'=>'https://ferroeaco.erp.devco.cloud/webui/zkau/web/8ca4fecc/theme/iceblue_c/images/login-logo-ferro.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'devlog logistica','item'=>'Status do idempiere service','label'=>'DevLog Logística','logo'=>'https://i.ibb.co/cKNtLrd4/LOG.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'rimad','item'=>'Status do idempiere service','label'=>'Rimad','logo'=>'https://i.ibb.co/S7ns2VyY/rimad.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'udiaco','item'=>'Status do idempiere service','label'=>'Udiaço','logo'=>'https://udiaco.com.br/wp-content/uploads/2023/07/udiaco.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'ceva','item'=>'Status do idempiere service','label'=>'Ceva','logo'=>'https://i.ibb.co/8n2KLbCx/new-ceva-logo.jpg'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'acofer','item'=>'Status do idempiere service','label'=>'Açofer','logo'=>'https://i.ibb.co/r2KrDbFf/acofer.webp'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'importway','item'=>'Status do idempiere service','label'=>'Importway','logo'=>'https://importway.com.br/wp-content/uploads/2021/10/importway-logo.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'planetkids','item'=>'Status do idempiere service','label'=>'Planet Kids','logo'=>'https://images.tcdn.com.br/files/1098435/themes/45/img/settings/Fabrica-de-Camas-Elasticas-Planet-Kids-Brinquedos.png?916a8a2faf0977f3a359c57b198a9ae7'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'mgs','item'=>'Status do idempiere service','label'=>'MGS Plásticos','logo'=>'https://mgsplasticos.com.br/wp-content/uploads/2023/06/logo-edit.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'mbcorreias','item'=>'Status do idempiere service','label'=>'MB Correias','logo'=>'https://www.mbcorreias.com.br/novo/wp-content/uploads/2023/08/Logo-MB-Correias-SemFundo.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'udiacoadm','item'=>'Status do idempiere service','label'=>'Udiaço ADM','logo'=>'https://i.ibb.co/DPnwmzbS/udiaco.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'cpm','item'=>'Status do idempiere service','label'=>'CPM','logo'=>'https://i.ibb.co/M59MSL0J/cpm.webp'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'redentor','item'=>'Status do idempiere service','label'=>'Redentor','logo'=>'https://i.ibb.co/Y4yfGXMW/Redentor.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'playpark','item'=>'Status do idempiere service','label'=>'Playpark','logo'=>'https://images.tcdn.com.br/files/808318/themes/174/img/logo.svg?95566cfb92f3ec32a080641262501606'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'marcosboquilhas','item'=>'Status do idempiere service','label'=>'Marcos Boquilhas','logo'=>'https://i.ibb.co/zWkfZNs6/boquilhas.webp'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'evooc','item'=>'Status do idempiere service','label'=>'Evooc','logo'=>'https://evooc.com.br/wp-content/uploads/2021/05/LOGO-EVOOC-COSMETICOS-SO-TEXTO-1.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'pedrazzini','item'=>'Status do idempiere service','label'=>'Pedrazzini','logo'=>'https://static.wixstatic.com/media/325cc6_07c22a669eb5437cb2817c271b1ce904~mv2.png/v1/fill/w_250,h_30,al_c,q_85,usm_0.66_1.00_0.01,enc_avif,quality_auto/pedrazzini-new5.png'],
            ['category'=>'clients','group'=>'Clientes BrERP','host'=>'udiaco-devrebar','item'=>'Status do idempiere service','label'=>'Udiaço API','logo'=>'https://i.ibb.co/7dWWkDpv/udiaco-api.png'],

            ['category'=>'internal','group'=>'Linux servers','host'=>'DBDEV','item'=>'Zabbix agent availability','label'=>'PostgreSQL DBDEV','logo'=>'https://upload.wikimedia.org/wikipedia/commons/2/29/Postgresql_elephant.svg'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'Backup RustFS','item'=>'Zabbix agent availability','label'=>'Backup RustFS','logo'=>'https://i.ibb.co/hF85YHrd/Rust-FS.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'XRDP - Guacamole','item'=>'Zabbix agent availability','label'=>'XRDP / Guacamole','logo'=>'https://i.ibb.co/gLXqrpz0/rdp-icon.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'redmine','item'=>'Zabbix agent availability','label'=>'Redmine','logo'=>'https://www.icescrum.com/wp-content/uploads/2018/10/redmine.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'pfsense_novo','item'=>'Zabbix agent availability','label'=>'pfSense','logo'=>'https://www.itandgeneral.com/wp-content/uploads/2023/11/pfsense-logo-square.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'SSH','item'=>'Zabbix agent availability','label'=>'SSH','logo'=>'https://www.freeiconspng.com/thumbs/ssh-icon/ssh-icon-16.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'Metabase','item'=>'Zabbix agent availability','label'=>'Metabase','logo'=>'https://miro.medium.com/0*7Gdt-CZVkF05Ay4K.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'mundodocafe','item'=>'Status do idempiere service','label'=>'Mundo do Café','logo'=>'https://i.ibb.co/Cp9vhVwQ/Mundo-do-Caf.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'Jenkins','item'=>'Zabbix agent availability','label'=>'Jenkins','logo'=>'https://i.ibb.co/8gD1mGkP/Jenkins.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'J-Frog','item'=>'Zabbix agent availability','label'=>'JFrog','logo'=>'https://speedmedia2.jfrog.com/08612fe1-9391-4cf3-ac1a-6dd49c36b276/media.jfrog.com/wp-content/uploads/2024/08/08132607/jfrog-logo-2022.svg'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'DBDEV','item'=>'Status do sish service','label'=>'SISH','logo'=>'https://i.ibb.co/Fknxp9Mg/Captura-de-tela-2025-12-08-112656.png'],
            ['category'=>'internal','group'=>'Linux servers','host'=>'replicacao','item'=>'Zabbix agent availability','label'=>'Replicação','logo'=>'https://static.vecteezy.com/system/resources/previews/065/452/368/non_2x/replication-outline-icon-data-replication-icon-for-backup-solutions-illustration-vector.jpg'],
            ['category'=>'internal','group'=>'Zabbix servers','host'=>'Zabbix server','item'=>'Zabbix agent availability','label'=>'Zabbix Server','logo'=>'https://images.icon-icons.com/2699/PNG/512/zabbix_logo_icon_167937.png'],

            ['category'=>'tests','group'=>'Linux servers','host'=>'teste-udiaco','item'=>'Status do idempiere service','label'=>'Teste Udiaço','logo'=>'https://udiaco.com.br/wp-content/uploads/2023/07/udiaco.png'],
            ['category'=>'tests','group'=>'Linux servers','host'=>'teste-atacarejo','item'=>'Status do idempiere service','label'=>'Teste Atacarejo','logo'=>'https://i.ibb.co/1YgDZmzH/atacarejo.png'],
            ['category'=>'tests','group'=>'Linux servers','host'=>'teste-ferroeaco','item'=>'Status do idempiere service','label'=>'Teste Ferro e Aço','logo'=>'https://i.ibb.co/hJrrWPX9/ferroeaco.png'],
            ['category'=>'tests','group'=>'Linux servers','host'=>'teste-rimad','item'=>'Status do idempiere service','label'=>'Teste Rimad','logo'=>'https://i.ibb.co/S7ns2VyY/rimad.png'],
        ],
    ],
];
