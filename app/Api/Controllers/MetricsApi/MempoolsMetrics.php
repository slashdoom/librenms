<?php

namespace App\Api\Controllers\MetricsApi;

use App\Models\Device;
use App\Models\Mempool;
use Illuminate\Http\Request;

class MempoolsMetrics
{
    use Traits\MetricsHelpers;

    public function render(Request $request): string
    {
        $lines = [];

        // Parse request parameters
        $params = $this->parseMetricsParams($request);

        // Gather global metrics
        $total_all = Mempool::query()->count();

        // Append global metrics
        $this->appendMetricBlock($lines, 'librenms_mempools_total', 'Total number of mempools in the system', 'gauge', "librenms_mempools_total {$total_all}");

        // Return early if only global metrics are requested (default behavior)
        if (!$this->shouldIncludeDeviceMetrics($params)) {
            return implode("\n", $lines) . "\n";
        }

        // Calculate scraped total (what's actually included in this response)
        $total_scraped = $total_all;
        if ($params['device_ids'] !== null) {
            $total_scraped = $this->applyDeviceFilter(Mempool::query(), $params['device_ids'])->count();
        }

        // Append scraped metrics
        $this->appendMetricBlock($lines, 'librenms_mempools_total_scraped', 'Number of mempools included in this scrape', 'gauge', "librenms_mempools_total_scraped {$total_scraped}");

        // Prepare per-mempool arrays
        $used_lines = [];
        $free_lines = [];
        $total_lines = [];
        $perc_lines = [];

        // Gather device info mapping for labels (using helper)
        $deviceIdsQuery = Mempool::select('device_id')->distinct();
        $deviceIdsQuery = $this->applyDeviceFilter($deviceIdsQuery, $params['device_ids']);
        $deviceIds = $deviceIdsQuery->pluck('device_id');
        $devices = $this->gatherDevicesForIds($deviceIds);

        $mpQuery = Mempool::select('mempool_id', 'device_id', 'mempool_descr', 'mempool_class', 'mempool_used', 'mempool_free', 'mempool_total', 'mempool_perc');
        $mpQuery = $this->applyDeviceFilter($mpQuery, $params['device_ids']);
        foreach ($mpQuery->cursor() as $m) {
            $dev = $devices->get($m->device_id);
            $labelsArr = [
                'mempool_id' => (string) $m->mempool_id,
                'device_id' => (string) $m->device_id,
                'device_hostname' => $dev ? $this->escapeLabel((string) $dev->hostname) : '',
                'device_sysName' => $dev ? $this->escapeLabel((string) $dev->sysName) : '',
                'mempool_descr' => $this->escapeLabel((string) $m->mempool_descr),
                'mempool_class' => $this->escapeLabel((string) $m->mempool_class),
            ];

            $labels = $this->formatLabels($labelsArr);

            $used_lines[] = "librenms_mempools_used_bytes{{$labels}} " . ((int) $m->mempool_used ?: 0);
            $free_lines[] = "librenms_mempools_free_bytes{{$labels}} " . ((int) $m->mempool_free ?: 0);
            $total_lines[] = "librenms_mempools_total_bytes{{$labels}} " . ((int) $m->mempool_total ?: 0);
            $perc_lines[] = "librenms_mempools_used_percent{{$labels}} " . ((int) $m->mempool_perc ?: 0);
        }

        // Append per-mempool metrics
        $this->appendMetricBlock($lines, 'librenms_mempools_used_bytes', 'Used bytes in mempool', 'gauge', $used_lines);
        $this->appendMetricBlock($lines, 'librenms_mempools_free_bytes', 'Free bytes in mempool', 'gauge', $free_lines);
        $this->appendMetricBlock($lines, 'librenms_mempools_total_bytes', 'Total bytes in mempool', 'gauge', $total_lines);
        $this->appendMetricBlock($lines, 'librenms_mempools_used_percent', 'Percent used', 'gauge', $perc_lines);

        return implode("\n", $lines) . "\n";
    }
}
