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
];