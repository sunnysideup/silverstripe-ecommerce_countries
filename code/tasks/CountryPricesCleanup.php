<?php


class CountryPricesCleanup extends BuildTask {

    protected $title = "Delete all Country Prices without an object";

    protected $description = "Go through each Country Price and delete it if the relating (parent) object does not exist or the price is zero OR the currency is obsolete.";

    function run($request) {
        echo "<h1>Total count: ".CountryPrice::get()->count()."</h1>";
        increase_time_limit_to(3600);
        increase_memory_limit_to('512M');
        for($i = 0; $i < 1000000; $i = $i + 100) {
            $objects = CountryPrice::get()->limit(100, $i);
            if($objects->count() == 0) {
                echo "<h1>Total count in the end: ".CountryPrice::get()->count()."</h1>";
                die("=========================== THE END =============================");
            }
            flush(); ob_end_flush(); DB::alteration_message("<h2>Limit $i, 100</h2>");ob_start();
            foreach($objects as $object) {
                if($object->Price == 0 || !$object->Buyable() || $object->isObsolete()) {
                    flush(); ob_end_flush(); DB::alteration_message("Deleting object: ".$object->ID, "deleted");ob_start();
                    $object->delete();
                    DB::query("DELETE FROM CountryPrice WHERE ID = ".$object->ID);
                }
                else {
                    flush(); ob_end_flush(); DB::alteration_message("object OK: ".$object->ID, "created");ob_start();
                }
            }
        }
    }

}
