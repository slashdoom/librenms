<?php

namespace App\Api\Controllers\MetricsApi;

use App\Models\Device;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServicesMetrics
{
    use Traits\MetricsHelpers;

    public function render(Request $request): string
    {
        $lines = [];

        // Parse request parameters
        $params = $this->parseMetricsParams($request);

        // Gather global metrics
        $totalAll = Service::query()->count();
        
        // Append global metrics
        $this->appendMetricBlock($lines, 'librenms_services_total', 'Total number of services configured in the system', 'gauge', "librenms_services_total {$totalAll}");

        // Return early if only global metrics are requested (default behavior)
        if (!$this->shouldIncludeDeviceMetrics($params)) {
            return implode("\n", $lines) . "\n";
        }

        // Calculate scraped total (what's actually included in this response)
        $totalScraped = $totalAll; // Default: same as total
        if ($params['device_ids'] !== null) {
            $totalScraped = $this->applyDeviceFilter(Service::query(), $params['device_ids'])->count();
        }

        // Append scraped metrics
        $this->appendMetricBlock($lines, 'librenms_services_total_scraped', 'Number of services included in this scrape', 'gauge', "librenms_services_total_scraped {$totalScraped}");

        // counts by status (0=OK,1=WARNING,2=CRITICAL)
        $status_lines = [];
        $statusesQ = Service::select('service_status', DB::raw('count(*) as cnt'));
        $statuses = $this->applyDeviceFilter($statusesQ, $params['device_ids'])->groupBy('service_status')->get();
        /** @var \stdClass $s */
        foreach ($statuses as $s) {
            $status_lines[] = sprintf('librenms_services_by_status{status="%s"} %d', $s->service_status, $s->cnt);
        }
        $this->appendMetricBlock($lines, 'librenms_services_by_status', 'Number of services by status (0=OK,1=WARNING,2=CRITICAL)', 'gauge', $status_lines);

        // Ignored Service count
        $ignoredQ = Service::where('service_ignore', 1);
        $ignored = $this->applyDeviceFilter($ignoredQ, $params['device_ids'])->count();
        $this->appendMetricBlock($lines, 'librenms_services_ignored', 'Number of ignored services', 'gauge', "librenms_services_ignored {$ignored}");

        // Disabled Service count
        $disabledQ = Service::where('service_disabled', 1);
        $disabled = $this->applyDeviceFilter($disabledQ, $params['device_ids'])->count();
        $this->appendMetricBlock($lines, 'librenms_services_disabled', 'Number of disabled services', 'gauge', "librenms_services_disabled {$disabled}");

        // Prepare per-device counts by status (may be high-cardinality)
        $deviceIdsQuery = Service::select('device_id')->distinct();
        $deviceIds = $this->applyDeviceFilter($deviceIdsQuery, $params['device_ids'])->pluck('device_id');
        $devices = Device::select('device_id', 'hostname', 'sysName')->whereIn('device_id', $deviceIds)->get()->keyBy('device_id');

        $services_lines = [];
        $rowsQ = Service::select('device_id', 'service_status', DB::raw('count(*) as cnt'));
        $rows = $this->applyDeviceFilter($rowsQ, $params['device_ids'])->groupBy('device_id', 'service_status')->cursor();
        /** @var \stdClass $r */
        foreach ($rows as $r) {
            $dev = $devices->get($r->device_id);
            $device_hostname = $dev ? $this->escapeLabel((string) $dev->hostname) : '';
            $device_sysName = $dev ? $this->escapeLabel((string) $dev->sysName) : '';
            $labels = sprintf('device_id="%s",device_hostname="%s",device_sysName="%s",status="%s"',
                $r->device_id,
                $device_hostname,
                $device_sysName,
                $r->service_status
            );
            $services_lines[] = "librenms_services_by_device_and_status{{$labels}} {$r->cnt}";
        }

        // Append per-services by device metrics
        $this->appendMetricBlock($lines, 'librenms_services_by_device_and_status', 'Number of services per device by status', 'gauge', $services_lines);

        return implode("\n", $lines) . "\n";
    }
}
