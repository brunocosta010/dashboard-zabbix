<?php
declare(strict_types=1);

final class ZabbixClient
{
    public function __construct(private readonly array $config) {}

    public function dashboard(): array
    {
        if (($this->config['mode'] ?? 'demo') === 'demo') return $this->demoData();
        if (empty($this->config['url']) || empty($this->config['token'])) {
            throw new RuntimeException('Configure ZABBIX_URL e ZABBIX_API_TOKEN no arquivo .env.');
        }

        $hostParams = [
            'output' => ['hostid', 'host', 'name', 'status'],
            'selectInterfaces' => ['ip', 'dns', 'available', 'main'],
            'selectGroups' => ['name'],
            'monitored_hosts' => true,
            'sortfield' => 'name',
        ];
        if ($this->config['group_ids']) $hostParams['groupids'] = $this->config['group_ids'];

        $hosts = $this->request('host.get', $hostParams);
        $hostIds = array_column($hosts, 'hostid');
        $items = $hostIds ? $this->request('item.get', [
            'output' => ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'lastclock', 'units', 'state', 'status'],
            'hostids' => $hostIds,
            'monitored' => true,
        ]) : [];

        $itemsByHost = [];
        foreach ($items as $item) $itemsByHost[$item['hostid']][] = $item;
        $hostRows = array_map(fn (array $host): array => $this->mapHost($host, $itemsByHost[$host['hostid']] ?? []), $hosts);

        $problems = $this->request('problem.get', [
            'output' => ['eventid', 'name', 'severity', 'clock', 'acknowledged', 'opdata', 'suppressed'],
            'selectHosts' => ['hostid', 'name'],
            'source' => 0,
            'object' => 0,
            'sortfield' => ['eventid'],
            'sortorder' => 'DESC',
            'limit' => 50,
        ]);
        $alerts = array_map(fn (array $p): array => [
            'id' => $p['eventid'],
            'host' => $p['hosts'][0]['name'] ?? 'Host desconhecido',
            'message' => $p['name'],
            'details' => $p['opdata'] ?? '',
            'severity' => (int) $p['severity'],
            'time' => date(DATE_ATOM, (int) $p['clock']),
            'acknowledged' => ($p['acknowledged'] ?? '0') === '1',
            'suppressed' => ($p['suppressed'] ?? '0') === '1',
        ], $problems);

        $up = count(array_filter($hostRows, fn ($h) => $h['status'] === 'up'));
        $down = count(array_filter($hostRows, fn ($h) => $h['status'] === 'down'));
        return [
            'mode' => 'live',
            'updatedAt' => date(DATE_ATOM),
            'settings' => $this->publicSettings(),
            'summary' => ['hosts' => count($hostRows), 'up' => $up, 'down' => $down, 'unknown' => count($hostRows) - $up - $down, 'problems' => count($alerts)],
            'hosts' => $hostRows,
            'alerts' => $alerts,
        ];
    }

    private function mapHost(array $host, array $items): array
    {
        $cpuItem = $this->findItem($items, $this->config['item_keys']['cpu']);
        $memoryItem = $this->findItem($items, $this->config['item_keys']['memory']);
        $latencyItem = $this->findItem($items, $this->config['item_keys']['latency']);
        $statusItem = $this->findItem($items, $this->config['item_keys']['status']);
        $interfaces = $host['interfaces'] ?? [];
        usort($interfaces, fn ($a, $b) => (int) ($b['main'] ?? 0) <=> (int) ($a['main'] ?? 0));
        $interface = $interfaces[0] ?? [];

        $status = 'unknown';
        if ($statusItem !== null && $this->isFresh($statusItem, 300)) {
            $status = (float) $statusItem['lastvalue'] > 0 ? 'up' : 'down';
        } elseif (array_filter($interfaces, fn ($i) => ($i['available'] ?? '0') === '1')) {
            $status = 'up';
        } elseif (array_filter($interfaces, fn ($i) => ($i['available'] ?? '0') === '2')) {
            $status = 'down';
        }

        $cpu = $this->percentage($cpuItem);
        if ($cpuItem && str_contains($cpuItem['key_'], '[,idle]') && $cpu !== null) $cpu = 100 - $cpu;
        $latency = $this->number($latencyItem);
        if ($latency !== null && ($latencyItem['units'] ?? '') !== 'ms') $latency *= 1000;
        $memory = $this->percentage($memoryItem);

        return [
            'id' => $host['hostid'],
            'name' => $host['name'] ?: $host['host'],
            'group' => $host['groups'][0]['name'] ?? 'Sem grupo',
            'ip' => ($interface['ip'] ?? '') ?: ($interface['dns'] ?? '—'),
            'status' => $status,
            'cpu' => $cpu === null ? null : round($cpu, 1),
            'memory' => $memory === null ? null : round($memory, 1),
            'latency' => $latency === null ? null : round($latency, 1),
            'lastCheck' => $this->latestClock([$statusItem, $cpuItem, $memoryItem, $latencyItem]),
        ];
    }

    private function findItem(array $items, array $keys): ?array
    {
        foreach ($keys as $wanted) {
            foreach ($items as $item) {
                if (($item['state'] ?? '0') === '0' && ($item['status'] ?? '0') === '0' && ($item['key_'] ?? '') === $wanted) return $item;
            }
        }
        foreach ($keys as $wanted) {
            $prefix = explode('[', $wanted, 2)[0];
            foreach ($items as $item) {
                if (($item['state'] ?? '0') === '0' && ($item['status'] ?? '0') === '0' && str_starts_with($item['key_'] ?? '', $prefix)) return $item;
            }
        }
        return null;
    }

    private function number(?array $item): ?float
    {
        return $item === null || $item['lastvalue'] === '' || !is_numeric($item['lastvalue']) ? null : (float) $item['lastvalue'];
    }

    private function percentage(?array $item): ?float
    {
        $value = $this->number($item);
        return $value === null ? null : max(0, min(100, $value));
    }

    private function isFresh(array $item, int $seconds): bool
    {
        return (int) ($item['lastclock'] ?? 0) >= time() - $seconds;
    }

    private function latestClock(array $items): ?string
    {
        $clocks = array_map(fn ($item) => (int) ($item['lastclock'] ?? 0), array_filter($items));
        $latest = $clocks ? max($clocks) : 0;
        return $latest ? date(DATE_ATOM, $latest) : null;
    }

    private function publicSettings(): array
    {
        return [
            'companyName' => $this->config['company_name'],
            'companyLogo' => $this->config['company_logo'],
            'refreshSeconds' => $this->config['refresh_seconds'],
        ];
    }

    private function request(string $method, array $params): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP é necessária.');
        $payload = json_encode(['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => random_int(1, PHP_INT_MAX)], JSON_THROW_ON_ERROR);
        $curl = curl_init($this->config['url']);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json-rpc', 'Authorization: Bearer ' . $this->config['token']],
            CURLOPT_CONNECTTIMEOUT => $this->config['timeout'],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => $this->config['verify_ssl'],
            CURLOPT_SSL_VERIFYHOST => $this->config['verify_ssl'] ? 2 : 0,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $error !== '') throw new RuntimeException('Falha de conexão com o Zabbix: ' . $error);
        if ($status < 200 || $status >= 300) throw new RuntimeException("O Zabbix respondeu com HTTP {$status}.");
        $decoded = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (isset($decoded['error'])) throw new RuntimeException('Erro da API Zabbix: ' . ($decoded['error']['data'] ?? $decoded['error']['message']));
        return $decoded['result'] ?? [];
    }

    private function demoData(): array
    {
        $raw = [
            ['1','SRV-APP-01','Aplicações','10.20.1.21','up',38.2,62.0,1.8],
            ['2','SRV-DB-01','Banco de dados','10.20.1.31','up',82.4,76.1,2.3],
            ['3','SRV-FILE-01','Arquivos','10.20.1.41','up',19.5,45.8,1.2],
            ['4','SRV-AD-01','Identidade','10.20.1.11','up',24.1,51.0,.9],
            ['5','SRV-BKP-01','Backup','10.20.1.51','down',null,null,null],
            ['6','SRV-WEB-01','Web','10.20.1.61','up',31.6,58.2,4.7],
            ['7','SRV-MON-01','Monitoramento','10.20.1.71','up',16.9,49.0,1.1],
            ['8','SRV-ERP-01','Aplicações','10.20.1.81','unknown',57.3,69.4,8.6],
        ];
        $hosts = array_map(fn ($h) => ['id'=>$h[0],'name'=>$h[1],'group'=>$h[2],'ip'=>$h[3],'status'=>$h[4],'cpu'=>$h[5],'memory'=>$h[6],'latency'=>$h[7],'lastCheck'=>date(DATE_ATOM,time()-20)], $raw);
        return [
            'mode'=>'demo',
            'updatedAt'=>date(DATE_ATOM),
            'settings'=>$this->publicSettings(),
            'summary'=>['hosts'=>8,'up'=>6,'down'=>1,'unknown'=>1,'problems'=>3],
            'hosts'=>$hosts,
            'alerts'=>[
                ['id'=>'101','host'=>'SRV-BKP-01','message'=>'Agente Zabbix indisponível','details'=>'Sem resposta há 16 minutos','severity'=>5,'time'=>date(DATE_ATOM,time()-960),'acknowledged'=>false,'suppressed'=>false],
                ['id'=>'102','host'=>'SRV-DB-01','message'=>'Uso de CPU acima de 80%','details'=>'CPU: 82.4%','severity'=>3,'time'=>date(DATE_ATOM,time()-2820),'acknowledged'=>false,'suppressed'=>false],
                ['id'=>'103','host'=>'SRV-FILE-01','message'=>'Espaço livre em disco abaixo de 20%','details'=>'Disponível: 16%','severity'=>2,'time'=>date(DATE_ATOM,time()-7440),'acknowledged'=>true,'suppressed'=>false],
            ],
        ];
    }
}