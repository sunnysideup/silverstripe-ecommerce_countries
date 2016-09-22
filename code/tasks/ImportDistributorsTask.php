<?php


/**
 * allows the creation of distributors from a CSV
 */

class ImportDistributorsTask extends ImportStockistsTask
{
    protected $title = "Import all the Distributors";

    protected $description = "Does not delete any record, it only updates and adds.";

    /**
     * excluding base folder
     *
     * e.g. assets/files/mycsv.csv
     * @var String
     */
    protected $fileLocation = "assets/Uploads/distributors.csv";

    /**
     *
     */
    public function run($request)
    {
        increase_time_limit_to(3600);
        set_time_limit(3600);
        increase_memory_limit_to('1024M');
        $this->readFile();
        $this->createDistributors();
    }

    /**
     *
     * here we actuall
     */
    protected function createDistributors()
    {
        flush();
        ob_end_flush();
        DB::alteration_message("================================================ CREATING distributors ================================================");
        ob_start();
        $distributorsCompleted = array();
        $rowCount = 0;
        foreach ($this->csv as $row) {
            $rowCount++;
            //distributor page
            $name = trim($row["NAME"]);
            $distributor = Distributor::get()->filter(array("Name" => $name))->first();
            if (!$distributor) {
                $distributor = new Distributor();
                $distributor->Address1 = $row["ENTEREDADDRESS"];
                flush();
                ob_end_flush();
                DB::alteration_message(" --- Creating distributor: ".$row["NAME"], "created");
                ob_start();
            } else {
                flush();
                ob_end_flush();
                DB::alteration_message(" --- Updating distributor: ".$row["NAME"], "changed");
                ob_start();
            }
            $distributor->Name = $name;
            $distributor->IsDefault = false;
            $distributor->Email = $row["EMAIL"];
            $distributor->Phone = $row["PHONE"];
            $distributor->DisplayEmail = $row["EMAIL"];
            $distributor->WebAddress = $row["WEB"];
            $distributor->write();
        }
        flush();
        ob_end_flush();
        DB::alteration_message("====================== END ==========================");
        ob_start();
    }
}
