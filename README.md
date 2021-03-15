Simplest data loader from fronius inverter

I run it from crontab every 15 sec.

Requires environmental variables such as:

 * **PV_SCRAPER_INVERTER_ADDRESS** - local ip of fronius inverter
 * **PV_SCRAPER_DB_HOST** - pgsql database host
 * **PV_SCRAPER_DB_PORT** - pgsql database port
 * **PV_SCRAPER_DB_DB** - pgsql database db
 * **PV_SCRAPER_DB_USERNAME** - pgsql database username
 * **PV_SCRAPER_DB_PASSWORD** - pgsql database password
 * **PV_SCRAPER_INFLUX_ADDRESS** - influxdb address
 * **PV_SCRAPER_INFLUX_TOKEN** - influxdb token
 * **PV_SCRAPER_INFLUX_ORG** - influxdb organization name
 * **PV_SCRAPER_INFLUX_BUCKET** - influxdb data bucket