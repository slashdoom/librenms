<?php

namespace App\Api\Controllers\MetricsApi;

use App\Models\Alert;
use App\Models\AlertRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AlertsMetrics
{
    use Traits\MetricsHelpers;

    public function render(Request $request): string
    {
        $lines = [];

        // Parse request parameters
        $params = $this->parseMetricsParams($request);

        // Gather global metrics (always show system-wide counts)
        $total_rules = AlertRule::count();
        $this->appendMetricBlock($lines, 'librenms_alerts_rules_total', 'Total number of alert rules in the system', 'gauge', "librenms_alerts_rules_total {$total_rules}");

        $total_alerts_all = Alert::query()->count();

        $this->appendMetricBlock($lines, 'librenms_alerts_total', 'Total number of alert rows in the system', 'gauge', "librenms_alerts_total {$total_alerts_all}");
        
        // Return early if only global metrics are requested (default behavior)
        if (!$this->shouldIncludeDeviceMetrics($params)) {
            return implode("\n", $lines) . "\n";
        }
        
        $total_alerts_scraped = $total_alerts_all;
        if ($params['device_ids'] !== null) {
            $total_alerts_scraped = $this->applyDeviceFilter(Alert::query(), $params['device_ids'])->count();
        }
        $this->appendMetricBlock($lines, 'librenms_alerts_total_scraped', 'Number of alert rows included in this scrape', 'gauge', "librenms_alerts_total_scraped {$total_alerts_scraped}");

        // Alerts by state
        $state_lines = [];
        $statesQ = Alert::select('state', DB::raw('count(*) as cnt'))->groupBy('state');
        if ($params['include_devices'] && $params['device_ids'] !== null) {
            $statesQ = $this->applyDeviceFilter($statesQ, $params['device_ids']);
        }
        $states = $statesQ->get();
        /** @var \stdClass $s */
        foreach ($states as $s) {
            $state_lines[] = sprintf('librenms_alerts_by_state{state="%s"} %d', $s->state, $s->cnt);
        }
        $this->appendMetricBlock($lines, 'librenms_alerts_by_state', 'Number of alerts by state', 'gauge', $state_lines);

        // Rules by severity
        $severity_lines = [];
        $sevs = AlertRule::select('severity', DB::raw('count(*) as cnt'))->groupBy('severity')->get();
        /** @var \stdClass $sv */
        foreach ($sevs as $sv) {
            $sev = $this->escapeLabel((string) ($sv->severity ?? 'unknown'));
            $severity_lines[] = sprintf('librenms_alerts_rules_by_severity{severity="%s"} %d', $sev, $sv->cnt);
        }
        $this->appendMetricBlock($lines, 'librenms_alerts_rules_by_severity', 'Number of alert rules by severity', 'gauge', $severity_lines);

        // Active alert counts
        $active_all = Alert::where('state', 1)->count();
        $active_scraped = $active_all;
        if ($params['include_devices'] && $params['device_ids'] !== null) {
            $active_scraped = $this->applyDeviceFilter(Alert::where('state', 1), $params['device_ids'])->count();
        }
        $this->appendMetricBlock($lines, 'librenms_alerts_active', 'Number of active alerts (system-wide)', 'gauge', "librenms_alerts_active {$active_all}");
        $this->appendMetricBlock($lines, 'librenms_alerts_active_scraped', 'Number of active alerts included in this scrape', 'gauge', "librenms_alerts_active_scraped {$active_scraped}");

        // Acknowledged alert counts
        $ack_all = Alert::where('state', 2)->count();
        $ack_scraped = $ack_all;
        if ($params['include_devices'] && $params['device_ids'] !== null) {
            $ack_scraped = $this->applyDeviceFilter(Alert::where('state', 2), $params['device_ids'])->count();
        }
        $this->appendMetricBlock($lines, 'librenms_alerts_acknowledged', 'Number of acknowledged alerts (system-wide)', 'gauge', "librenms_alerts_acknowledged {$ack_all}");
        $this->appendMetricBlock($lines, 'librenms_alerts_acknowledged_scraped', 'Number of acknowledged alerts included in this scrape', 'gauge', "librenms_alerts_acknowledged_scraped {$ack_scraped}");

        return implode("\n", $lines) . "\n";
    }
}
