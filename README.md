# LXD Metrics Zabbix Template

Right now this is early stages and has bugs, **dont use this**.

Low level discovery template for Zabbix to import metrics data from LXD.

## Macros

| Variable | Description | Must Be Set Per Host | Default |
| -------  | ----------- | -------------------  | ------- |
| {$HOST}  | IP Address / DNS to connect to | Yes | -     |
| {$LXD_METRICS_CERT_PEM} | The .crt file to use to connect to LXD | Yes | - |
| {$LXD_METRICS_KEY_PEM} | The .key file to use to connect to LXD | Yes | - |
| {$PORT} | The port metrics are available for LXD | No | 8443 |
| {$URL} | The url where metrics can be found | No | /1.0/metrics |
| {$SCHEME} | The HTTP scheme to fetch data over | No | https:// |

## Setup

### 1. Zabbix Server Configuration
Edit your zabbix server conf (ubuntu: `/etc/zabbix/zabbix_server.conf`) and make
sure the following variables are uncommented and set to a location.

**Remeber this location, you'll need it later**

`SSLCertLocation=/home/zabbix`
`SSLKeyLocation=/home/zabbix`

Then restart zabbix `service zabbix-server restart`

### 2. LXD Certificates

Follow this guide to generate an authorized certificate and private key for
zabbix to connect to the LXD instance to gather metrics.

> If you do this once per host you will need to use better file names than
> in the attached guide as they will clash when you add them to the zabbix
> server

https://linuxcontainers.org/lxd/docs/master/metrics

### 3. Copy LXD certificate and private key to zabbix server

You'll then need to copy the outputed `metrics.crt` & `metrics.key` to your
zabbix server and place them under the path we set in "Step 1".

### 4. Import the template into Zabbix

Then download the `lxd-metrics-template.json` from this repository and import
it into Zabbix.

> Configuration -> Templates -> Create Template (very top right of the screen)

### 5. Attach the template to the host

*TODO explain creating a host*

Attach the  template to the host

> Configuration -> Hosts -> find & click your host -> Templates

### 6. Setup the macros on the host

As mentioned above you'll need to set some macros for the host;

> > Configuration -> Hosts -> find & click your host -> Macros

| Variable | Desired Value |
| -------- | ------------- |
| HOST     | IP Address / DNS Name (no HTTP scheme or URL)
| LXD_METRICS_CERT_PEM | The name of the `.crt` file above (not full path, just file name) |
| LXD_METRICS_KEY_PEM  | The name of the `.key` file above (not full path, just file name) |

### 7. Wait and view your data
Updates every 60 seconds, so give it 5 minutes and see what you can see.
