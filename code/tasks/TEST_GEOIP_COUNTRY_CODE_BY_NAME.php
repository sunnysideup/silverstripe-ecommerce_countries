<?php

class TEST_GEOIP_COUNTRY_CODE_BY_NAME extends BuildTask {

    protected $title = "Test GEOIP Functionality";

    protected $description = "test a bunch of IP addresses using geoip_country_code_by_name";

    private static $test_ips = array(
        "95.141.32.46" => "IT",
        "95.211.217.68" => "NL",
        "91.109.115.41" => "PT",
        "83.170.113.210" => "GB",
        "188.138.118.144" => "DE",
        "174.34.224.167" => "US",
        "72.46.140.106" => "US",
        "76.72.172.208" => "US",
        "184.75.210.226" => "CA",
        "78.40.124.16" => "FR",
        "67.205.67.76" => "CA",
        "188.138.118.184" => "DE",
        "188.138.124.110" => "DE",
        "85.17.156.99" => "NL",
        "85.17.156.11" => "NL",
        "85.17.156.76" => "NL",
        "72.46.153.26" => "US",
        "208.64.28.194" => "US",
        "76.164.194.74" => "US",
        "184.75.210.90" => "CA",
        "184.75.208.210" => "CA",
        "184.75.209.18" => "CA",
        "46.165.195.139" => "DE",
        "199.87.228.66" => "US",
        "76.72.167.90" => "US",
        "94.247.174.83" => "SE",
        "69.64.56.47" => "US",
        "184.75.210.186" => "CA",
        "108.62.115.226" => "US",
        "94.46.4.1" => "PT",
        "46.20.45.18" => "DE",
        "50.23.94.74" => "US",
        "64.141.100.136" => "CA",
        "69.59.28.19" => "US",
        "178.255.154.2" => "CZ",
        "178.255.153.2" => "CH",
        "178.255.155.2" => "IT",
        "64.237.55.3" => "US",
        "178.255.152.2" => "AT",
        "212.84.74.156" => "GB",
        "173.204.85.217" => "US",
        "173.248.147.18" => "US",
        "72.46.130.42" => "US",
        "94.46.240.121" => "ES",
        "208.43.68.59" => "US",
        "67.228.213.178" => "US",
        "96.31.66.245" => "US",
        "82.103.128.63" => "DK",
        "174.34.156.130" => "US",
        "70.32.40.2" => "US",
        "174.34.162.242" => "US",
        "85.25.176.167" => "FR",
        "204.152.200.42" => "US",
        "95.211.87.85" => "NL",
        "5.178.78.77" => "SE",
        "207.244.80.239" => "US",
        "159.8.146.132" => "GB",
        "50.22.90.227" => "US",
        "69.64.56.153" => "US",
        "188.138.40.20" => "DE",
        "64.120.6.122" => "US",
        "158.58.173.160" => "IT",
        "76.72.171.180" => "US",
        "72.46.140.186" => "US",
        "78.31.69.179" => "DE",
        "95.211.198.87" => "NL"
    );

    public function run($request) {
        if(isset($_GET["ip"])) {
            $ar = array($_GET["ip"] => "user defined");
        }
        else {
            $ar = Config::inst()->get('TEST_GEOIP_COUNTRY_CODE_BY_NAME', 'test_ips');
        }
        if(count($ar)) {
            foreach($ar as $ip => $description) {
                $codeArray = Geoip::ip2country($ip);
                echo "<hr /><h2>$ip: ---".print_r($codeArray, 1)."---</h2>";
                if($codeArray["code"] != $description && strlen($description) == 2) {
                    DB::alteration_message("ERROR - this should be: ".$description, "deleted");
                }
            }
        }
        echo "
            <hr />
            <p>
                You can also add your own IP address at the end of the URL
                - e.g. dev/tasks/TEST_GEOIP_COUNTRY_CODE_BY_NAME?ip=111.111.111.111
                to test a specific IP address
            </p>";
    }
}
