<?php

namespace App\Api\Controllers\MetricsApi;

use App\Models\Device;
use Illuminate\Http\Request;

class DevicesMetrics
{
    use Traits\MetricsHelpers;

    public function render(Request $request): string
    {
        $lines = [];

        // Parse request parameters
        $params = $this->parseMetricsParams($request);

        // Gather global metrics (always show total system counts)
        $totalAll = Device::query()->count();
        $upAll = Device::query()->where('status', 1)->count();
        $downAll = Device::query()->where('status', 0)->count();
        
        // Append global metrics
        $this->appendMetricBlock($lines, 'librenms_devices_total', 'Total number of devices in the system', 'gauge', ["librenms_devices_total {$totalAll}"]);
        $this->appendMetricBlock($lines, 'librenms_devices_up', 'Number of devices currently up (system-wide)', 'gauge', ["librenms_devices_up {$upAll}"]);
        $this->appendMetricBlock($lines, 'librenms_devices_down', 'Number of devices currently down (system-wide)', 'gauge', ["librenms_devices_down {$downAll}"]);

        // Return early if only global metrics are requested (default behavior)
        if (!$this->shouldIncludeDeviceMetrics($params)) {
            return implode("\n", $lines) . "\n";
        }

        // Calculate scraped totals (what's actually included in this response)
        $totalScraped = $totalAll;
        $upScraped = $upAll;
        $downScraped = $downAll;
        if ($params['device_ids'] !== null) {
            $totalScraped = $this->applyDeviceFilter(Device::query(), $params['device_ids'])->count();
            $upScraped = $this->applyDeviceFilter(Device::query()->where('status', 1), $params['device_ids'])->count();
            $downScraped = $this->applyDeviceFilter(Device::query()->where('status', 0), $params['device_ids'])->count();
        }

        // Append scraped metrics
        $this->appendMetricBlock($lines, 'librenms_devices_total_scraped', 'Number of devices included in this scrape', 'gauge', ["librenms_devices_total_scraped {$totalScraped}"]);
        $this->appendMetricBlock($lines, 'librenms_devices_up_scraped', 'Number of up devices included in this scrape', 'gauge', ["librenms_devices_up_scraped {$upScraped}"]);
        $this->appendMetricBlock($lines, 'librenms_devices_down_scraped', 'Number of down devices included in this scrape', 'gauge', ["librenms_devices_down_scraped {$downScraped}"]);

        // Prepare per-device arrays
        $device_up_lines = [];
        $polled_timetaken_lines = [];
        $discovered_timetaken_lines = [];
        $ping_timetaken_lines = [];
        $uptime_lines = [];

        // Gather per-device metrics
        $deviceQuery = Device::select('device_id', 'hostname', 'sysName', 'type', 'status', 'last_polled_timetaken', 'last_discovered_timetaken', 'last_ping_timetaken', 'uptime');
        $deviceQuery = $this->applyDeviceFilter($deviceQuery, $params['device_ids']);
        foreach ($deviceQuery->cursor() as $device) {
            $labels = sprintf('device_id="%s",device_hostname="%s",device_sysName="%s",device_type="%s"',
                $device->device_id,
                $this->escapeLabel((string) $device->hostname),
                $this->escapeLabel((string) $device->sysName),
                $this->escapeLabel((string) $device->type));

            $device_up_lines[] = "librenms_devices_up{{$labels}} " . ($device->status ? '1' : '0');

            $lastPolledTimeTaken = $device->status ? ((int) $device->last_polled_timetaken ?: 0) : 0;
            $polled_timetaken_lines[] = "librenms_devices_last_polled_timetaken_seconds{{$labels}} {$lastPolledTimeTaken}";

            $lastDiscoveredTimeTaken = $device->status ? ((int) $device->last_discovered_timetaken ?: 0) : 0;
            $discovered_timetaken_lines[] = "librenms_devices_last_discovered_timetaken_seconds{{$labels}} {$lastDiscoveredTimeTaken}";

            $lastPingTimeTaken = $device->status ? ((int) $device->last_ping_timetaken ?: 0) : 0;
            $ping_timetaken_lines[] = "librenms_devices_last_ping_timetaken_seconds{{$labels}} {$lastPingTimeTaken}";

            $uptime = $device->status ? ((int) $device->uptime ?: 0) : 0;
            $uptime_lines[] = "librenms_devices_uptime_seconds{{$labels}} {$uptime}";
        }

        // Append per-device metrics
        $this->appendMetricBlock($lines, 'librenms_devices_up', 'Whether a device is up (1) or not (0)', 'gauge', $device_up_lines);
        $this->appendMetricBlock($lines, 'librenms_devices_last_polled_timetaken_seconds', 'Last polled time taken in seconds', 'gauge', $polled_timetaken_lines);
        $this->appendMetricBlock($lines, 'librenms_devices_last_discovered_timetaken_seconds', 'Last discovered time taken in seconds', 'gauge', $discovered_timetaken_lines);
        $this->appendMetricBlock($lines, 'librenms_devices_last_ping_timetaken_seconds', 'Last ping time taken in seconds', 'gauge', $ping_timetaken_lines);
        $this->appendMetricBlock($lines, 'librenms_devices_uptime_seconds', 'Device uptime in seconds (0 if down)', 'gauge', $uptime_lines);

        return implode("\n", $lines) . "\n";
    }
}
