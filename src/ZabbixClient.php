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
            'output' => ['itemid', 'hostid', 'name', 'key_', 'lastvalue', 'lastclock', 'units', 'value_type', 'state', 'status'],
            'hostids' => $hostIds,
            'monitored' => true,
        ]) : [];

        $itemsByHost = [];
        foreach ($items as $item) $itemsByHost[$item['hostid']][] = $item;
        $definitions = $this->config['dashboard']['services'] ?? [];
        $services = array_map(
            fn (array $definition): array => $this->mapService($definition, $hosts, $itemsByHost),
            $definitions
        );

        $metricConfig = $this->config['dashboard']['metric_items'];
        $cpu = $this->topMetric($hosts, $itemsByHost, $metricConfig['cpu'], 10);
        $memory = $this->topMetric($hosts, $itemsByHost, $metricConfig['memory'], 10);
        $latency = $this->latencyMetrics($hosts, $itemsByHost, $metricConfig['latency_host'], $metricConfig['latency_item']);
        $alerts = $this->problems();
        $updates = $this->updateHistory($hosts, $itemsByHost, $metricConfig['update_host'], $metricConfig['update_item']);

        $up = count(array_filter($services, fn (array $service): bool => $service['status'] === 'up'));
        $down = count(array_filter($services, fn (array $service): bool => $service['status'] === 'down'));
        $unknown = count($services) - $up - $down;
        return [
            'mode' => 'live',
            'updatedAt' => date(DATE_ATOM),
            'settings' => $this->publicSettings(),
            'summary' => ['services'=>count($services),'up'=>$up,'down'=>$down,'unknown'=>$unknown,'problems'=>count($alerts)],
            'services' => $services,
            'metrics' => ['cpu'=>$cpu,'memory'=>$memory,'latency'=>$latency],
            'alerts' => $alerts,
            'updates' => $updates,
        ];
    }

    private function mapService(array $definition, array $hosts, array $itemsByHost): array
    {
        $host = $this->findHost($hosts, $definition['host'], $definition['group']);
        $item = $host ? $this->findItemByName($itemsByHost[$host['hostid']] ?? [], $definition['item']) : null;
        $value = $this->number($item);
        $status = $value === null ? 'unknown' : ($value >= 1 ? 'up' : 'down');
        return [
            'id' => $host['hostid'] ?? 'missing-' . md5($definition['group'] . $definition['host'] . $definition['item']),
            'category' => $definition['category'],
            'group' => $definition['group'],
            'host' => $definition['host'],
            'label' => $definition['label'],
            'item' => $definition['item'],
            'logo' => $definition['logo'],
            'status' => $status,
            'value' => $value,
            'lastCheck' => $this->clock($item),
            'stale' => $item !== null && !$this->isFresh($item, max(300, ($this->config['refresh_seconds'] ?? 30) * 5)),
        ];
    }

    private function topMetric(array $hosts, array $itemsByHost, string $itemName, int $limit): array
    {
        $rows = [];
        foreach ($hosts as $host) {
            $item = $this->findItemByName($itemsByHost[$host['hostid']] ?? [], $itemName);
            $value = $this->number($item);
            if ($value === null) continue;
            $rows[] = ['host'=>$host['name'] ?: $host['host'],'value'=>round(max(0,min(100,$value)),1),'unit'=>'%','lastCheck'=>$this->clock($item)];
        }
        usort($rows, fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        return array_slice($rows, 0, $limit);
    }

    private function latencyMetrics(array $hosts, array $itemsByHost, string $hostName, string $itemNeedle): array
    {
        $host = $this->findHost($hosts, $hostName);
        if (!$host) return [];
        $rows = [];
        foreach ($itemsByHost[$host['hostid']] ?? [] as $item) {
            if (!$this->usable($item) || stripos($item['name'] ?? '', $itemNeedle) === false) continue;
            $value = $this->number($item);
            if ($value === null) continue;
            $units = strtolower(trim((string) ($item['units'] ?? '')));
            if (in_array($units, ['s','sec','seconds'], true)) $value *= 1000;
            $rows[] = ['host'=>$item['name'],'value'=>round($value,2),'unit'=>'ms','lastCheck'=>$this->clock($item)];
        }
        usort($rows, fn (array $a, array $b): int => $b['value'] <=> $a['value']);
        return array_slice($rows, 0, 10);
    }

    private function problems(): array
    {
        $problems = $this->request('problem.get', [
            'output'=>['eventid','objectid','name','severity','clock','acknowledged','opdata','suppressed'],
            'source'=>0,'object'=>0,'sortfield'=>['eventid'],'sortorder'=>'DESC','limit'=>100,
        ]);
        $triggerIds = array_values(array_unique(array_filter(array_column($problems, 'objectid'))));
        $triggerHosts = [];
        if ($triggerIds) {
            $triggers = $this->request('trigger.get', ['output'=>['triggerid'],'triggerids'=>$triggerIds,'selectHosts'=>['hostid','name']]);
            foreach ($triggers as $trigger) $triggerHosts[$trigger['triggerid']] = $trigger['hosts'][0]['name'] ?? 'Host desconhecido';
        }
        return array_map(fn (array $problem): array => [
            'id'=>$problem['eventid'],'host'=>$triggerHosts[$problem['objectid']] ?? 'Host desconhecido',
            'message'=>$problem['name'],'details'=>$problem['opdata'] ?? '','severity'=>(int)$problem['severity'],
            'time'=>date(DATE_ATOM,(int)$problem['clock']),'acknowledged'=>($problem['acknowledged'] ?? '0') === '1',
            'suppressed'=>($problem['suppressed'] ?? '0') === '1',
        ], $problems);
    }

    private function updateHistory(array $hosts, array $itemsByHost, string $hostName, string $itemName): array
    {
        $host = $this->findHost($hosts, $hostName);
        $item = $host ? $this->findItemByName($itemsByHost[$host['hostid']] ?? [], $itemName) : null;
        if (!$item) return [];
        try {
            $history = $this->request('history.get', [
                'output'=>'extend','history'=>(int)($item['value_type'] ?? 4),'itemids'=>[$item['itemid']],
                'time_from'=>time()-21600,'sortfield'=>'clock','sortorder'=>'DESC','limit'=>20,
            ]);
        } catch (Throwable) {
            $history = [];
        }
        if (!$history && ($item['lastvalue'] ?? '') !== '') $history[] = ['clock'=>$item['lastclock'],'value'=>$item['lastvalue']];
        return array_map(fn (array $row): array => [
            'time'=>date(DATE_ATOM,(int)$row['clock']),'message'=>trim((string)($row['value'] ?? 'Falha de atualização')),
            'host'=>$host['name'] ?: $host['host'],
        ], $history);
    }

    private function findHost(array $hosts, string $wanted, ?string $group = null): ?array
    {
        foreach ($hosts as $host) {
            $matchesName = strcasecmp((string)($host['name'] ?? ''),$wanted) === 0 || strcasecmp((string)($host['host'] ?? ''),$wanted) === 0;
            if (!$matchesName) continue;
            if ($group !== null && $group !== '') {
                $groups = array_column($host['groups'] ?? [], 'name');
                if (!array_filter($groups, fn (string $name): bool => strcasecmp($name,$group) === 0)) continue;
            }
            return $host;
        }
        return null;
    }

    private function findItemByName(array $items, string $wanted): ?array
    {
        $matches = array_values(array_filter($items, fn (array $item): bool => $this->usable($item) && strcasecmp((string)($item['name'] ?? ''),$wanted) === 0));
        usort($matches, fn (array $a, array $b): int => (int)($b['lastclock'] ?? 0) <=> (int)($a['lastclock'] ?? 0));
        return $matches[0] ?? null;
    }

    private function usable(array $item): bool
    {
        return ($item['state'] ?? '0') === '0' && ($item['status'] ?? '0') === '0';
    }

    private function number(?array $item): ?float
    {
        return $item === null || ($item['lastvalue'] ?? '') === '' || !is_numeric($item['lastvalue']) ? null : (float)$item['lastvalue'];
    }

    private function clock(?array $item): ?string
    {
        $clock = (int)($item['lastclock'] ?? 0);
        return $clock > 0 ? date(DATE_ATOM,$clock) : null;
    }

    private function isFresh(array $item, int $seconds): bool
    {
        return (int)($item['lastclock'] ?? 0) >= time() - $seconds;
    }

    private function publicSettings(): array
    {
        return ['companyName'=>$this->config['company_name'],'companyLogo'=>$this->config['company_logo'],'refreshSeconds'=>$this->config['refresh_seconds']];
    }

    private function request(string $method, array $params): array
    {
        if (!function_exists('curl_init')) throw new RuntimeException('A extensão cURL do PHP é necessária.');
        $payload = json_encode(['jsonrpc'=>'2.0','method'=>$method,'params'=>$params,'id'=>random_int(1,PHP_INT_MAX)],JSON_THROW_ON_ERROR);
        $curl = curl_init($this->config['url']);
        $options = [
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json-rpc','Authorization: Bearer '.$this->config['token']],
            CURLOPT_CONNECTTIMEOUT=>$this->config['timeout'],CURLOPT_TIMEOUT=>$this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER=>$this->config['verify_ssl'],CURLOPT_SSL_VERIFYHOST=>$this->config['verify_ssl'] ? 2 : 0,
        ];
        if (!empty($this->config['ca_bundle'])) $options[CURLOPT_CAINFO] = $this->config['ca_bundle'];
        curl_setopt_array($curl,$options);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        if ($body === false || $error !== '') throw new RuntimeException('Falha de conexão com o Zabbix: '.$error);
        if ($status < 200 || $status >= 300) throw new RuntimeException("O Zabbix respondeu com HTTP {$status}.");
        $decoded = json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if (isset($decoded['error'])) throw new RuntimeException('Erro da API Zabbix: '.($decoded['error']['data'] ?? $decoded['error']['message']));
        return $decoded['result'] ?? [];
    }

    private function demoData(): array
    {
        $services = [];
        foreach (($this->config['dashboard']['services'] ?? []) as $index => $definition) {
            $status = $index % 17 === 4 ? 'down' : ($index % 13 === 8 ? 'unknown' : 'up');
            $services[] = array_merge($definition,['id'=>(string)($index+1),'status'=>$status,'value'=>$status==='unknown'?null:($status==='up'?1:0),'lastCheck'=>date(DATE_ATOM,time()-(($index%8)*17)),'stale'=>false]);
        }
        $up = count(array_filter($services,fn (array $service): bool => $service['status']==='up'));
        $down = count(array_filter($services,fn (array $service): bool => $service['status']==='down'));
        $metric = fn (string $prefix,int $offset): array => array_map(fn (int $i): array => ['host'=>$prefix.'-'.str_pad((string)($i+1),2,'0',STR_PAD_LEFT),'value'=>(float)(88-($i*5)-$offset),'unit'=>'%','lastCheck'=>date(DATE_ATOM,time()-20)],range(0,9));
        return [
            'mode'=>'demo','updatedAt'=>date(DATE_ATOM),'settings'=>$this->publicSettings(),
            'summary'=>['services'=>count($services),'up'=>$up,'down'=>$down,'unknown'=>count($services)-$up-$down,'problems'=>3],
            'services'=>$services,
            'metrics'=>['cpu'=>$metric('srv-app',0),'memory'=>$metric('srv-db',4),'latency'=>[['host'=>'Ping Cloudflare','value'=>18.4,'unit'=>'ms','lastCheck'=>date(DATE_ATOM)]]],
            'alerts'=>[
                ['id'=>'101','host'=>'Backup RustFS','message'=>'Agente Zabbix indisponível','details'=>'Sem resposta','severity'=>5,'time'=>date(DATE_ATOM,time()-960),'acknowledged'=>false,'suppressed'=>false],
                ['id'=>'102','host'=>'DBDEV','message'=>'Uso de CPU acima de 80%','details'=>'CPU: 88%','severity'=>3,'time'=>date(DATE_ATOM,time()-2820),'acknowledged'=>false,'suppressed'=>false],
                ['id'=>'103','host'=>'SSH','message'=>'Falha no processo de atualização','details'=>'Verificar log','severity'=>2,'time'=>date(DATE_ATOM,time()-7440),'acknowledged'=>true,'suppressed'=>false],
            ],
            'updates'=>[['host'=>'SSH','message'=>'Falha ao atualizar ambiente de demonstração','time'=>date(DATE_ATOM,time()-1800)]],
        ];
    }
}
