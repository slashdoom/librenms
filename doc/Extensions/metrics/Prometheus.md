# Prometheus Integration

LibreNMS provides two methods for integrating with Prometheus:

1. **[Scrape](#scrape)** - Prometheus scrapes metrics directly from metrics endpoints in the LibreNMS API
2. **[Push Gateway](#push-gateway)** - LibreNMS pushes metrics to a Prometheus Push Gateway during polling

## Scrape

The Scrape method allows Prometheus to directly scrape device metrics from LibreNMS using dedicated metrics API endpoints. This enables you to export monitoring data for all devices managed by LibreNMS into Prometheus for analysis, alerting, and visualization.

This approach involves Prometheus pulling device data from the LibreNMS web server which queries the LibreNMS database. The amount of data exported depends on your device count and filtering configuration, so performance testing is recommended for large deployments.

### Requirements

- Prometheus >= 2.0
- LibreNMS API token with global-read access
- Network connectivity from Prometheus to LibreNMS

### Available Metrics Endpoints

LibreNMS provides multiple metrics endpoints for different types of data:

- `/api/v0/metrics/devices` — Device-level metrics (status, uptime, polling times)
- `/api/v0/metrics/ports` — Network port metrics (traffic, errors, status)
- `/api/v0/metrics/sensors` — Hardware sensor data (temperature, power, etc.)
- `/api/v0/metrics/alerts` — Alert system metrics
- `/api/v0/metrics/applications` — Application monitoring data
- `/api/v0/metrics/mempools` — Memory usage metrics
- `/api/v0/metrics/processors` — CPU utilization metrics
- `/api/v0/metrics/storages` — Storage usage metrics
- `/api/v0/metrics/services` — Service check results
- And more...

For complete API documentation, see [Metrics API](../../API/Metrics.md).

### Authentication

Create an API token in LibreNMS:

1. Navigate to `/api-access/` in your LibreNMS web interface
2. Click 'Create API access token'
3. Select a user with global-read access
4. Enter a description (e.g., "Prometheus scraping")
5. Click 'Create API Token'
6. Save the generated token securely

### Device Data Export

To export device metrics to Prometheus, you must use the `include_devices=true` parameter. Without this parameter, endpoints only return aggregate counts suitable for monitoring LibreNMS itself, not the individual devices it monitors.

```yaml
# Export device metrics to Prometheus
- job_name: 'librenms-devices'
  scrape_interval: 300s  # Recommended interval
  scrape_timeout: 60s    # Allow time for large queries
  metrics_path: /api/v0/metrics/devices
  params:
    include_devices: ['true']
```

### Device Filtering

Use filtering to control which devices are exported to Prometheus:

```yaml
# Export specific devices by ID
params:
  include_devices: ['true']
  device_ids: ['1,2,3']

# Export devices by hostname
params:
  include_devices: ['true'] 
  hostnames: ['sw01,sw02,fw01']

# Export devices by group (recommended for large deployments)
params:
  include_devices: ['true']
  device_group: ['core-switches']
```

### Prometheus Configuration

Configure your `prometheus.yml` to scrape LibreNMS metrics:

#### Basic Device Export Configuration

Export device data from LibreNMS to Prometheus:

```yaml
global:
  scrape_interval: 300s  # 5 minutes (match LibreNMS polling)

scrape_configs:
  # Device status and performance metrics
  - job_name: 'librenms-devices'
    static_configs:
      - targets: ['your.librenms.example:443']
    scheme: https
    metrics_path: /api/v0/metrics/devices
    params:
      include_devices: ['true']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 300s
    scrape_timeout: 60s

  # Network port metrics
  - job_name: 'librenms-ports'
    static_configs:
      - targets: ['your.librenms.example:443']
    scheme: https
    metrics_path: /api/v0/metrics/ports
    params:
      include_devices: ['true']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 150s  # More frequent for network metrics
    scrape_timeout: 30s

  # Hardware sensor data
  - job_name: 'librenms-sensors'
    static_configs:
      - targets: ['your.librenms.example:443']
    scheme: https
    metrics_path: /api/v0/metrics/sensors
    params:
      include_devices: ['true']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 300s
    scrape_timeout: 30s
```

#### Filtered Device Export (Recommended for Large Deployments)

Use device group filtering to export only specific devices:

```yaml
scrape_configs:
  # Critical infrastructure only
  - job_name: 'librenms-critical-devices'
    static_configs:
      - targets: ['your.librenms.example:443']
    scheme: https
    metrics_path: /api/v0/metrics/devices
    params:
      include_devices: ['true']
      device_group: ['core-switches', 'core-routers']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 300s
    scrape_timeout: 60s

  # Server monitoring
  - job_name: 'librenms-servers'
    static_configs:
      - targets: ['your.librenms.example:443']
    scheme: https
    metrics_path: /api/v0/metrics/devices
    params:
      include_devices: ['true']
      device_group: ['servers']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 300s

  # Application metrics for servers
  - job_name: 'librenms-applications'
    static_configs:
      - targets: ['your.librenms.example:443'] 
    scheme: https
    metrics_path: /api/v0/metrics/applications
    params:
      include_devices: ['true']
      device_group: ['application-servers']
    headers:
      X-Auth-Token: 'YOURAPITOKENHERE'
    scrape_interval: 300s
```

### Scrape Intervals

If you scrape at the exact interval that your poller is polling at, 5 minutes (300 seconds) by default, you'll likely find that you get anomalies in your data. The reason is that due to poller drift, every once in a while you'll have instances where Prometheus scrapes data that hasn't yet been updated so the next scrape ends up with twice the data.

Example:
```
12:00:00 - Poller write 500
12:00:01 - Prometheus scrapes 500
12:05:00 - Poller writes 600
12:05:01 - Prometheus scrapes 600
12:10:01 - Prometheus scrapes 600
12:10:20 - Poller writes 700
12:15:00 - Poller writes 800
12:15:01 - Prometheus scrapes 800
```

So the data series in Prometheus is 500,600,600,800 which will produce a inaccurate result when calculated with a rate function.

You can overcome this by scraping more frequently, like half the polling duration, 150 seconds.

Example:
```
12:00:00 - Poller write 500
12:00:01 - Prometheus scrapes 500
12:02:31 - Prometheus scrapes 500
12:05:00 - Poller writes 600
12:05:01 - Prometheus scrapes 600
12:07:31 - Prometheus scrapes 600
12:10:01 - Prometheus scrapes 600
12:10:20 - Poller writes 700
12:12:31 - Prometheus scrapes 700
12:15:00 - Poller writes 800
12:15:01 - Prometheus scrapes 800
12:17:31 - Prometheus scrapes 800
```

So a series of 500,500,600,600,600,700,800,800.  So if you run your rate function against the data with a 5 minutes step interval, all polls will be accounted for.

### Performance Considerations

#### Resource Impact of Device Export

Device data export with `include_devices=true` has significant resource requirements:

- **Response time**: 500ms - 10+ seconds depending on device count
- **Database load**: Heavy (per-entity queries with JOINs)
- **Memory usage**: High (proportional to entity count)
- **Cardinality**: 100s to 10,000s+ metrics per endpoint
- **Network bandwidth**: Large response bodies

#### Scaling Guidelines for Device Export

**Small deployments (< 100 devices)**
- Can export all devices on most endpoints
- Use 150s scrape intervals to avoid poller drift
- Monitor LibreNMS web server and database load

**Medium deployments (100-1000 devices)**
- Use device group filtering to export subsets
- Use 300s+ scrape intervals
- Consider separate Prometheus instances for different device groups
- Monitor query performance and response times

**Large deployments (1000+ devices)**
- Mandatory device group filtering
- Export only critical devices to Prometheus
- Consider federation or separate monitoring clusters
- Monitor LibreNMS database performance impact carefully
- Use multiple LibreNMS instances if needed

#### Optimization Strategies for Device Export

1. **Filter by device groups**: `device_group: ['critical-infrastructure']`
2. **Stagger scrape intervals**: Different intervals for different endpoints
3. **Monitor selectively**: Export only devices that need Prometheus analysis
4. **Split by purpose**: Separate jobs for alerting vs. capacity planning
5. **Scale horizontally**: Multiple Prometheus instances for large deployments
6. **Database optimization**: Ensure proper indexing on device-related tables

### Example Device Metrics

When `include_devices=true` is used, you'll get detailed per-device metrics like these:

#### Device Status and Performance
```
# Device operational status
librenms_devices_status{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com",device_type="network",device_os="ios"} 1

# Device uptime
librenms_devices_uptime_seconds{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com"} 8640000

# Polling performance
librenms_devices_last_polled_timetaken_seconds{device_id="1",device_hostname="sw01",device_sysName="sw01.example.com"} 12.5
```

#### Network Port Metrics
```
# Port traffic counters
librenms_ports_ifInOctets_total{device_id="1",device_hostname="sw01",port_id="1",ifName="GigabitEthernet0/1",ifAlias="uplink"} 1234567890
librenms_ports_ifOutOctets_total{device_id="1",device_hostname="sw01",port_id="1",ifName="GigabitEthernet0/1",ifAlias="uplink"} 9876543210

# Port status
librenms_ports_ifOperStatus{device_id="1",device_hostname="sw01",port_id="1",ifName="GigabitEthernet0/1"} 1
```

#### Hardware Sensors
```
# Temperature readings
librenms_sensors_temperature_celsius{device_id="1",device_hostname="sw01",sensor_id="1",sensor_descr="CPU Temperature",sensor_class="temperature"} 42.5

# Power consumption
librenms_sensors_power_watts{device_id="1",device_hostname="sw01",sensor_id="2",sensor_descr="PSU1 Power",sensor_class="power"} 150.3
```

#### Application Metrics
```
# Application performance data
librenms_applications_metric{device_id="2",device_hostname="web01",device_sysName="web01.example.com",app_type="nginx",app_instance="default",metric="connections_active"} 42
librenms_applications_metric{device_id="2",device_hostname="web01",device_sysName="web01.example.com",app_type="nginx",app_instance="default",metric="requests_per_second"} 156.7
```

## Push Gateway

The Push Gateway method configures LibreNMS to push metrics to a Prometheus Push Gateway during polling. The Push Gateway then acts as an intermediary that Prometheus scrapes.

> **Warning**: Prometheus Push Gateway support is considered experimental. It hasn't been extensively tested and is still in development. Use at your own risk!

### Requirements

(Older versions may work but haven't been tested)

- Prometheus >= 2.0
- Prometheus Push Gateway >= 0.4.0  
- Grafana (for visualization)
- PHP-CURL

The setup of Prometheus, Push Gateway, and Grafana is out of scope for this documentation.

### Limitations

- No built-in visualizations - you need to create your own dashboards in Grafana
- Limited support - this integration is experimental
- RRD storage continues to function normally alongside Prometheus export

### Configuration

Enable Push Gateway integration in LibreNMS:

!!! setting "poller/prometheus"
    ```bash
    lnms config:set prometheus.enable true
    lnms config:set prometheus.url 'http://127.0.0.1:9091'
    lnms config:set prometheus.job 'librenms'
    lnms config:set prometheus.prefix 'librenms'
    ```

If your Push Gateway uses basic authentication, configure the following:

!!! setting "poller/prometheus"
    ```bash
    lnms config:set prometheus.user username
    lnms config:set prometheus.password password
    ```

### Metric Prefix

Setting the 'prefix' option will cause all metric names to begin with the configured value.

For instance without setting this option metric names will be something like this:

```
OUTUCASTPKTS
ifOutUcastPkts_rate
INOCTETS
ifInErrors_rate
```

Configuring a prefix name, for example 'librenms', instead causes those metrics to be exposed with the following names:

```
librenms_OUTUCASTPKTS
librenms_ifOutUcastPkts_rate
librenms_INOCTETS
librenms_ifInErrors_rate
```

### Prometheus Configuration for Push Gateway

Configure Prometheus to scrape the Push Gateway:

```yaml
scrape_configs:
  - job_name: pushgateway
    scrape_interval: 300s
    honor_labels: true
    static_configs:
      - targets: ['127.0.0.1:9091']
```

The same data stored in RRD will be sent to Prometheus and recorded. You can then create graphs within Grafana to display the information you need.
