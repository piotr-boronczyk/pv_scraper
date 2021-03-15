<?php /** @noinspection SqlNoDataSourceInspection */

/*
 * This script requires environmental variables such as:
 * PV_SCRAPER_INVERTER_ADDRESS - local ip of fronius inverter
 * PV_SCRAPER_DB_HOST - pgsql database host
 * PV_SCRAPER_DB_PORT - pgsql database port
 * PV_SCRAPER_DB_DB - pgsql database db
 * PV_SCRAPER_DB_USERNAME - pgsql database username
 * PV_SCRAPER_DB_PASSWORD - pgsql database password
 * PV_SCRAPER_INFLUX_ADDRESS - influxdb address
 * PV_SCRAPER_INFLUX_TOKEN - influxdb token
 * PV_SCRAPER_INFLUX_ORG - influxdb organization name
 * PV_SCRAPER_INFLUX_BUCKET - influxdb data bucket
 *
 * */

use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;

require_once 'vendor/autoload.php';

function get(&$var, $default = null) {
    return isset($var) ? $var : $default;
}

$data = file_get_contents("http://" . getenv("PV_SCRAPER_INVERTER_ADDRESS") . "/solar_api/v1/GetInverterRealtimeData.cgi?Scope=Device&DeviceId=1&DataCollection=CommonInverterData");
$conn = pg_connect(
    "host="
    . getenv("PV_SCRAPER_DB_HOST") . " port=" . getenv("PV_SCRAPER_DB_PORT") . " dbname=" . getenv("PV_SCRAPER_DB_DB") . " user=" . getenv("PV_SCRAPER_DB_USERNAME") . " password=" . getenv("PV_SCRAPER_DB_PASSWORD"));
$result = pg_query($conn, "select * from public.users");
$data_json = json_decode($data);
$result = pg_insert($conn, "measurements", array("timestamp" => (string)date("c"), "current_pv" => $data_json->Body->Data->IDC->Value, "voltage_pv" => $data_json->Body->Data->UDC->Value, "current_grid" => $data_json->Body->Data->IAC->Value, "voltage_grid" => $data_json->Body->Data->UAC->Value, "day_production" => $data_json->Body->Data->DAY_ENERGY->Value));

$token = getenv("PV_SCRAPER_INFLUX_TOKEN");
$org = getenv("PV_SCRAPER_INFLUX_ORG");
$bucket = getenv("PV_SCRAPER_INFLUX_BUCKET");

$client = new Client([
    "url" => getenv("PV_SCRAPER_INFLUX_ADDRESS"),
    "token" => $token,
]);

$writeApi = $client->createWriteApi();

$dataArray = ['name' => 'instalacja_1',
    'fields' => [
        "current_pv" => (float)get($data_json->Body->Data->IDC->Value, 0.0),
        "voltage_pv" => (float)get($data_json->Body->Data->UDC->Value, 0.0),
        "current_grid" => (float)get($data_json->Body->Data->IAC->Value, 0.0),
        "voltage_grid" => (float)get($data_json->Body->Data->UAC->Value, 0.0),
        "day_production" => (float)get($data_json->Body->Data->DAY_ENERGY->Value, 0.0)
    ],
    'time' => microtime(true)
];

var_dump($dataArray);

$writeApi->write($dataArray, WritePrecision::S, $bucket, $org);
