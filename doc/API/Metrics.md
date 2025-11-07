# Metrics API

The Metrics API provides Prometheus-compatible metric endpoints for monitoring LibreNMS data. All endpoints return metrics in the Prometheus exposition format (text/plain) and require a valid API token with global-read access.

## Base Endpoint

### `metrics_index`

Get an overview of all available metrics endpoints.

Route: `/api/v0/metrics`

Input:
- None

Example:

```curl
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics
```

Output:
Returns an HTML page listing all available metrics endpoints with descriptions and usage examples.

## Standard Metrics vs. Including Device Metrics

### Standard Metrics (LibreNMS System Monitoring)
By default, all endpoints return only LibreNMS aggregate metrics.  This is useful for monitoring LibreNMS itself with a Prometheus system.

### Including Device Metrics
To include detailed metrics for the individual devices LibreNMS monitors, use the `include_devices` parameter:

- `include_devices` — boolean (true/false/1/0) to include detailed per-entity metrics (default: **false**)

⚠️ **Performance Warning**: When `include_devices=true`, response times and cardinality increase significantly.  

For more information see the [Prometheus Integration](../Extensions/metrics/Prometheus.md) documentation.

### Device Filtering (Only with include_devices=true)
When `include_devices=true`, additional filtering parameters become available:
- `device_id` or `device_ids` — single or comma-separated device IDs
- `hostname` or `hostnames` — single or comma-separated hostnames (matches `hostname` and `sysName`)
- `device_group` — a device group id or name; the group will be expanded to its member devices

**Endpoints that support include_devices metrics:**
- `access_points` - Wireless access point metrics
- `alerts` - Alert rules and status metrics  
- `applications` - Application monitoring metrics
- `customoids` - Custom SNMP OID metrics
- `devices` - Device status and performance metrics
- `mempools` - Memory pool utilization metrics
- `ports` - Network port statistics
- `ports_statistics` - Detailed port statistics (higher cardinality)
- `processors` - CPU/processor utilization metrics
- `sensors` - Hardware sensor readings
- `services` - Service check status metrics
- `storages` - Storage utilization metrics  
- `wireless_sensors` - Wireless-specific sensor metrics

**Endpoints that only return global metrics:**
- `pollers` - System-wide poller performance metrics only (no device filtering)

### Understanding the Pattern

This dual-metric pattern enables flexible monitoring strategies:

**System Monitoring (Default)**
- Only `*_total` metrics appear (e.g., `librenms_devices_total`)
- Fast, lightweight responses for LibreNMS system health
- Ideal for monitoring LibreNMS infrastructure itself

**Device Monitoring (include_devices=true)**
- `*_total_scraped` metrics show filtered counts
- `*_total` metrics still show complete system totals
- Per-entity metrics with full device labels
- Allows monitoring both LibreNMS health and individual devices simultaneously

## Usage Examples

### Recommended: Default High-Performance Mode
```bash
# Fast, low-cardinality system overviews (< 100ms response)
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/devices"
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/ports"
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/sensors"
```

### Advanced: Detailed Per-Entity Metrics
```bash
# Include all entity details (slower, high cardinality)
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/devices?include_devices=true"

# Include details with filtering to reduce load
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/ports?include_devices=true&device_id=1,2,3"
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/mempools?include_devices=true&hostnames=sw1,sw2"
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/sensors?include_devices=true&device_group=switches"
```

### Prometheus Configuration Examples
```yaml
- job_name: 'librenms-devices'
  scrape_interval: 30s
  static_configs:
    - targets: ['librenms.example.com']
  metrics_path: '/api/v0/metrics/devices'
- job_name: 'librenms-pollers'
  scrape_interval: 30s
  static_configs:
    - targets: ['librenms.example.com']
  metrics_path: '/api/v0/metrics/pollers'
```

## Available Metrics Endpoints

### `metrics_access_points`

Get access point metrics.

Route: `/api/v0/metrics/access_points`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global access point metrics only
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/access_points

# Include per-access point details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/access_points?include_devices=true"
```

Output:

**Default behavior:**
```
# HELP librenms_accesspoints_total Total number of access points
# TYPE librenms_accesspoints_total gauge
librenms_accesspoints_total 25
```

**When include_devices=true (additional metrics):**
```
# HELP librenms_accesspoints_total_scraped Number of access points included in this scrape
# TYPE librenms_accesspoints_total_scraped gauge
librenms_accesspoints_total_scraped 25

# Per-access point metrics with full labels
# HELP librenms_accesspoints_clients Connected clients per access point
# TYPE librenms_accesspoints_clients gauge
librenms_accesspoints_clients{device_id="1",device_hostname="ap01",ap_name="Office-AP-01",type="indoor"} 15
librenms_accesspoints_clients{device_id="2",device_hostname="ap02",ap_name="Office-AP-02",type="indoor"} 8
```

### `metrics_alerts`

Get alert metrics.

Route: `/api/v0/metrics/alerts`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global alert metrics only
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/alerts

# Include per-device alert details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/alerts?include_devices=true"
```

Output:

**Default behavior (always included):**
```
# HELP librenms_alerts_rules_total Total number of alert rules
# TYPE librenms_alerts_rules_total gauge
librenms_alerts_rules_total 25

# HELP librenms_alerts_total Total number of alert entries
# TYPE librenms_alerts_total gauge
librenms_alerts_total 142

# HELP librenms_alerts_state Number of alerts by state (system-wide)
# TYPE librenms_alerts_state gauge
librenms_alerts_state{state="ok"} 120
librenms_alerts_state{state="alert"} 20
librenms_alerts_state{state="acknowledged"} 2

# HELP librenms_alerts_active Number of currently active alerts
# TYPE librenms_alerts_active gauge
librenms_alerts_active 20

# HELP librenms_alerts_acknowledged Number of acknowledged alerts
# TYPE librenms_alerts_acknowledged gauge
librenms_alerts_acknowledged 2
```

**When include_devices=true (additional metrics):**
```
# Scraped counts when filtering is applied
# HELP librenms_alerts_total_scraped Number of alert entries included in this scrape
# TYPE librenms_alerts_total_scraped gauge
librenms_alerts_total_scraped 45

# HELP librenms_alerts_state_scraped Number of alerts by state (scraped subset)
# TYPE librenms_alerts_state_scraped gauge
librenms_alerts_state_scraped{state="ok"} 30
librenms_alerts_state_scraped{state="alert"} 12
librenms_alerts_state_scraped{state="acknowledged"} 3

# Per-device alert details
# HELP librenms_alerts_device Alert details per device
# TYPE librenms_alerts_device gauge
librenms_alerts_device{device_id="1",device_hostname="sw01",rule_name="High CPU Usage",state="alert",severity="critical"} 1
librenms_alerts_device{device_id="2",device_hostname="fw01",rule_name="Interface Down",state="acknowledged",severity="warning"} 1
```

### `metrics_applications`

Get application metric values.

Route: `/api/v0/metrics/applications`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global application metrics only
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/applications

# Include per-application details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/applications?include_devices=true"
```

Output:

**Default behavior:**
```
# HELP librenms_applications_metrics_total Total number of application metrics
# TYPE librenms_applications_metrics_total gauge
librenms_applications_metrics_total 1543
```

**When include_devices=true (additional metrics):**
```
# HELP librenms_applications_metrics_total_scraped Number of application metrics included in this scrape
# TYPE librenms_applications_metrics_total_scraped gauge
librenms_applications_metrics_total_scraped 234

# Per-application metric values
# HELP librenms_applications_metric Application metric values
# TYPE librenms_applications_metric gauge
librenms_applications_metric{device_id="1",device_hostname="web01",device_sysName="web01.example.com",app_type="nginx",app_instance="default",metric="connections_active"} 42
librenms_applications_metric{device_id="1",device_hostname="web01",device_sysName="web01.example.com",app_type="nginx",app_instance="default",metric="requests_per_second"} 156.7
librenms_applications_metric{device_id="2",device_hostname="db01",device_sysName="db01.example.com",app_type="mysql",app_instance="production",metric="queries_per_second"} 892.1
```

### `metrics_customoids`

Get custom OID metrics.

Route: `/api/v0/metrics/customoids`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global custom OID metrics count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/customoids

# Include per-device custom OID values
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/customoids?include_devices=true"
```

Output:
**Default behavior:** System-wide count of custom SNMP OID metrics configured in LibreNMS.

**When include_devices=true:** Individual custom OID values per device with full labels including device info, OID description, and current values.

### `metrics_devices`

Get device-level metrics.

Route: `/api/v0/metrics/devices`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global metrics only (fast)
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/devices

# Include per-device details (slower)
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/devices?include_devices=true"

# Include details with filtering
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/devices?include_devices=true&device_group=switches"
```

Output:

**Default behavior (include_devices=false or omitted):**
```
# HELP librenms_devices_total Total number of devices in the system
# TYPE librenms_devices_total gauge
librenms_devices_total 47

# HELP librenms_devices_up Number of devices currently up (system-wide)
# TYPE librenms_devices_up gauge
librenms_devices_up 45

# HELP librenms_devices_down Number of devices currently down (system-wide)
# TYPE librenms_devices_down gauge
librenms_devices_down 2
```

**When include_devices=true (additional metrics):**
```
# Additional scraped counts (when filtering is applied)
# HELP librenms_devices_total_scraped Number of devices included in this scrape
# TYPE librenms_devices_total_scraped gauge
librenms_devices_total_scraped 5

# HELP librenms_devices_up_scraped Number of up devices included in this scrape
# TYPE librenms_devices_up_scraped gauge
librenms_devices_up_scraped 5

# HELP librenms_devices_down_scraped Number of down devices included in this scrape
# TYPE librenms_devices_down_scraped gauge
librenms_devices_down_scraped 0

# Per-device metrics with full labels
# HELP librenms_devices_status Device status (1=up, 0=down)
# TYPE librenms_devices_status gauge
librenms_devices_status{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com",device_type="network",device_os="ios"} 1
librenms_devices_status{device_id="2",device_hostname="sw02",device_sysName="sw02.example.com",device_type="network",device_os="ios"} 1

# HELP librenms_devices_last_polled_timetaken_seconds Time taken for last poll
# TYPE librenms_devices_last_polled_timetaken_seconds gauge
librenms_devices_last_polled_timetaken_seconds{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com"} 12.5

# HELP librenms_devices_uptime_seconds Device uptime in seconds
# TYPE librenms_devices_uptime_seconds gauge
librenms_devices_uptime_seconds{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com"} 2592000
```

### `metrics_mempools`

Get memory pool usage metrics.

Route: `/api/v0/metrics/mempools`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global memory pool count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/mempools

# Include per-device memory details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/mempools?include_devices=true"
```

Output:
**Default behavior:** Total count of memory pools across all devices.

**When include_devices=true:** Memory pool utilization per device including total, used, free memory values and utilization percentages with device labels.

### `metrics_pollers`

Get poller performance and cluster metrics.

Route: `/api/v0/metrics/pollers`

Input:
- None (no device filtering supported)

Example:

```curl
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/pollers
```

Output:
Prometheus metrics for poller performance including polling times, queue depths, and cluster coordination metrics.

### `metrics_ports`

Get port metrics.

Route: `/api/v0/metrics/ports`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global port count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/ports

# Include per-port details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/ports?include_devices=true"
```

Output:

**Default behavior:**
```
# HELP librenms_ports_total Total number of ports
# TYPE librenms_ports_total gauge
librenms_ports_total 1247
```

**When include_devices=true (additional metrics):**
```
# HELP librenms_ports_total_scraped Number of ports included in this scrape
# TYPE librenms_ports_total_scraped gauge
librenms_ports_total_scraped 24

# Per-port traffic and status metrics
# HELP librenms_ports_ifInOctets_total Port input octets
# TYPE librenms_ports_ifInOctets_total counter
librenms_ports_ifInOctets_total{device_id="1",device_hostname="sw01",port_id="1",ifName="GigabitEthernet0/1",ifAlias="uplink"} 1234567890

# HELP librenms_ports_ifOutOctets_total Port output octets  
# TYPE librenms_ports_ifOutOctets_total counter
librenms_ports_ifOutOctets_total{device_id="1",device_hostname="sw01",port_id="1",ifName="GigabitEthernet0/1",ifAlias="uplink"} 9876543210
```

### `metrics_ports_statistics`

Get higher-cardinality per-port statistics.

Route: `/api/v0/metrics/ports_statistics`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global port statistics count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/ports_statistics

# Include detailed per-port statistics
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/ports_statistics?include_devices=true"
```

Output:
**Default behavior:** Total count of port statistics entries.

**When include_devices=true:** Detailed port statistics per port including broadcast packets, multicast packets, discards, and other detailed counters with full device and port labels.

### `metrics_processors`

Get processor usage metrics.

Route: `/api/v0/metrics/processors`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global processor count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/processors

# Include per-processor details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/processors?include_devices=true"
```

Output:
**Default behavior:** Total count of processors across all devices.

**When include_devices=true:** CPU utilization per processor including usage percentages and load averages with device and processor labels.

### `metrics_sensors`

Get health sensor metrics.

Route: `/api/v0/metrics/sensors`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global sensor count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/sensors

# Include per-sensor readings
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/sensors?include_devices=true"
```

Output:

**Default behavior:**
```
# HELP librenms_sensors_total Total number of sensors
# TYPE librenms_sensors_total gauge
librenms_sensors_total 432
```

**When include_devices=true (additional metrics):**
```
# HELP librenms_sensors_total_scraped Number of sensors included in this scrape
# TYPE librenms_sensors_total_scraped gauge
librenms_sensors_total_scraped 48

# Per-sensor readings by type
# HELP librenms_sensors_temperature_celsius Temperature sensor readings in Celsius
# TYPE librenms_sensors_temperature_celsius gauge
librenms_sensors_temperature_celsius{device_id="1",device_hostname="sw01",sensor_id="1",sensor_descr="CPU Temperature",sensor_class="temperature"} 42.5

# HELP librenms_sensors_power_watts Power sensor readings in Watts
# TYPE librenms_sensors_power_watts gauge
librenms_sensors_power_watts{device_id="1",device_hostname="sw01",sensor_id="2",sensor_descr="PSU1 Power",sensor_class="power"} 150.3
```

### `metrics_services`

Get service check status metrics.

Route: `/api/v0/metrics/services`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global service counts
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/services

# Include per-service details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/services?include_devices=true"
```

Output:
**Default behavior:** Total service counts by status (up, down, disabled, ignored).

**When include_devices=true:** Individual service check results including status, response times, and check output with device and service labels.

### `metrics_storages`

Get storage usage metrics.

Route: `/api/v0/metrics/storages`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global storage count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/storages

# Include per-storage details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/storages?include_devices=true"
```

Output:
**Default behavior:** Total count of storage entities across all devices.

**When include_devices=true:** Storage utilization per device including disk usage, free space, total capacity, and utilization percentages with device and storage labels.

### `metrics_wireless_sensors`

Get wireless sensor metrics.

Route: `/api/v0/metrics/wireless_sensors`

Input:
- `include_devices` (optional) - include detailed per-entity metrics (default: false)
- Device filtering parameters (optional, only when include_devices=true)

Example:

```curl
# Default: Global wireless sensor count
curl -H 'X-Auth-Token: YOURAPITOKENHERE' https://foo.example/api/v0/metrics/wireless_sensors

# Include per-sensor wireless details
curl -H 'X-Auth-Token: YOURAPITOKENHERE' "https://foo.example/api/v0/metrics/wireless_sensors?include_devices=true"
```

Output:
**Default behavior:** Total count of wireless sensors across all devices.

**When include_devices=true:** Wireless sensor readings per device including signal strength, noise levels, quality metrics, and other wireless-specific data with device and sensor labels.

## Authentication

All metrics endpoints require authentication via the `X-Auth-Token` header with a valid API token that has global-read access.

## Content Type

All metrics endpoints return data in the Prometheus exposition format with content type `text/plain; version=0; charset=utf-8`.
