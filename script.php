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
$result = pg_insert($conn, "measurements", array("timestamp" => (string)date("c"), "current_pv" => get($data_json->Body->Data->IDC->Value, 0.0), "voltage_pv" => get($data_json->Body->Data->UDC->Value,0.0), "current_grid" => get($data_json->Body->Data->IAC->Value, 0.0), "voltage_grid" => get($data_json->Body->Data->UAC->Value, 0.0), "day_production" => get($data_json->Body->Data->DAY_ENERGY->Value, 0.0)));

$token = getenv("PV_SCRAPER_INFLUX_TOKEN");
$org = getenv("PV_SCRAPER_INFLUX_ORG");
$bucket = getenv("PV_SCRAPER_INFLUX_BUCKET");

$client = new Client([
    "url" => getenv("PV_SCRAPER_INFLUX_ADDRESS"),
    "token" => $token,
]);

$writeApi = $client->createWriteApi();
var_dump($data);
var_dump($data_json->Body->Data->DeviceStatus->ErrorCode);
$dataArray = [];
if(!$data_json->Body->Data->DeviceStatus->StateToReset){
    switch ($data_json->Body->Data->DeviceStatus->ErrorCode){
        case 0:
            $dataArray = ['name' => 'instalacja_1',
                'fields' => [
                    "current_pv" => (float)get($data_json->Body->Data->IDC->Value, 0.0),
                    "voltage_pv" => (float)get($data_json->Body->Data->UDC->Value, 0.0),
                    "current_grid" => (float)get($data_json->Body->Data->IAC->Value, 0.0),
                    "voltage_grid" => (float)get($data_json->Body->Data->UAC->Value, 0.0),
                    "frequency_grid" =>(float)get($data_json->Body->Data->FAC->Value, 0.0),
                    "power_grid" =>(float)get($data_json->Body->Data->PAC->Value, 0.0),
                    "day_production" => (float)get($data_json->Body->Data->DAY_ENERGY->Value, 0.0)
                ],
                'time' => microtime(true)
            ];
            break;

        case 307:
        case 306:
            $dataArray = ['name' => 'instalacja_1',
                'fields' => [
                    "current_pv" => (float)get($data_json->Body->Data->IDC->Value, 0.0),
                    "voltage_pv" => (float)get($data_json->Body->Data->UDC->Value, 0.0),
                    "day_production" => (float)get($data_json->Body->Data->DAY_ENERGY->Value, 0.0)
                ],
                'time' => microtime(true)
            ];
            break;
        case 522:
            echo (string)date("c") . " 522 nie ma tu w sumie nic ciekawego";
            break;

        default:
            pg_insert($conn, "events", array("timestamp" => (string)gmdate("c"), "error_code" => $data_json->Body->Data->DeviceStatus->ErrorCode, "data" => $data));
            break;

    }
}


var_dump($dataArray);
if (!empty($dataArray)){
    $writeApi->write($dataArray, WritePrecision::S, $bucket, $org);
}
