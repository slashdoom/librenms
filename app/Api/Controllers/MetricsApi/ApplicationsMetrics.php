<?php

namespace App\Api\Controllers\MetricsApi;

use App\Models\Application;
use App\Models\ApplicationMetric;
use App\Models\Device;
use Illuminate\Http\Request;

class ApplicationsMetrics
{
    use Traits\MetricsHelpers;

    public function render(Request $request): string
    {
        $lines = [];

        // Parse request parameters
        $params = $this->parseMetricsParams($request);

        // Gather global metrics
        $totalAll = ApplicationMetric::query()->count();
        
        // Append global metrics
        $this->appendMetricBlock($lines, 'librenms_applications_metrics_total', 'Total number of application metrics in the system', 'gauge', "librenms_applications_metrics_total {$totalAll}");

        // Return early if only global metrics are requested (default behavior)
        if (!$this->shouldIncludeDeviceMetrics($params)) {
            return implode("\n", $lines) . "\n";
        }

        // Calculate scraped total (what's actually included in this response)
        $totalScraped = $totalAll; // Default: same as total
        if ($params['device_ids'] !== null) {
            $totalScraped = ApplicationMetric::join('applications', 'application_metrics.app_id', '=', 'applications.app_id')
                ->whereIn('applications.device_id', $params['device_ids']->all())
                ->count();
        }

        // Append scraped metrics
        $this->appendMetricBlock($lines, 'librenms_applications_metrics_total_scraped', 'Number of application metrics included in this scrape', 'gauge', "librenms_applications_metrics_total_scraped {$totalScraped}");

        $metric_lines = [];

        // Get device ids referenced by application metrics so we can preload devices
        $appJoin = Application::join('application_metrics', 'applications.app_id', '=', 'application_metrics.app_id')->distinct();
        if ($params['device_ids'] !== null) {
            $appJoin = $appJoin->whereIn('applications.device_id', $params['device_ids']->all());
        }
        $deviceIds = $appJoin->pluck('applications.device_id');

        $devices = Device::select('device_id', 'hostname', 'sysName')->whereIn('device_id', $deviceIds)->get()->keyBy('device_id');

        // join application_metrics -> applications to get app metadata alongside metric rows
        $query = ApplicationMetric::join('applications', 'application_metrics.app_id', '=', 'applications.app_id')
            ->select(
                'application_metrics.id',
                'application_metrics.app_id',
                'application_metrics.metric',
                'application_metrics.value',
                'applications.device_id',
                'applications.app_type',
                'applications.app_instance'
            );
        if ($params['device_ids'] !== null) {
            $query->whereIn('applications.device_id', $params['device_ids']->all());
        }

        /** @var \stdClass $am */
        foreach ($query->cursor() as $am) {
            $device = $devices->get($am->device_id);
            $device_hostname = $device ? $this->escapeLabel((string) $device->hostname) : '';
            $device_sysName = $device ? $this->escapeLabel((string) $device->sysName) : '';

            $labels = sprintf(
                'app_id="%s",app_type="%s",app_instance="%s",device_id="%s",device_hostname="%s",device_sysName="%s",metric="%s"',
                $am->app_id,
                $this->escapeLabel((string) $am->app_type),
                $this->escapeLabel((string) $am->app_instance),
                $am->device_id,
                $device_hostname,
                $device_sysName,
                $this->escapeLabel((string) $am->metric)
            );

            $metric_lines[] = "librenms_applications_metric_value{{$labels}} " . ((float) $am->value ?: 0);
        }

        // Append per-application metrics
        $this->appendMetricBlock($lines, 'librenms_applications_metric_value', 'Application metric value', 'gauge', $metric_lines);

        return implode("\n", $lines) . "\n";
    }
}
