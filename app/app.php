<?php

    /*
    ExSurv backend PHP application
    Author: Gungor Budak, gbudak@iupui.edu
    */

    require_once 'config.php';
    // this PHP file has an array like following
    // kept separate for security reasons
    /*
    <?php
        $config = array(
            'host' => 'host',
            'port' => port,
            'dbname' => 'dbname',
            'user' => 'user',
            'pass' => 'pass'
        );
    ?>
    */

    /*
    Makes sure user input is safe
    */
    function sanitize($input) {
        return htmlspecialchars(strip_tags(trim($input)));
    }

    /*
    Given a length, generates random string of that length
    */
    function make_name($length){
        return substr(str_repeat(md5(rand()), ceil($length/32)), 0, $length);
    }

    /*
    Generates a JSON file for the surv.R script
    */
    function make_json($data) {
        // generate JSON path with a random filename
        $json_path = __dir__ . '/datasets/' . make_name(10) . '.json';
        // convert PHP array into JSON string
        $json_data = json_encode($data);
        // write the JSON string to the file
        file_put_contents($json_path, $json_data);
        return $json_path;
    }

    /*
    Prepares the table for exporting search results
    */
    function make_tsv($survival_data) {
        $final = "exon_id\tcancer\tpatient_id\tevent\ttime\texpression\n";
        foreach ($survival_data as $data) {
            for ($i = 0; $i < count($data['data']['patient_id']); $i++) {
                $final .= $data['info']['exon_id'] ."\t"
                    . $data['info']['cancer'] ."\t"
                    . $data['data']['patient_id'][$i] ."\t"
                    . $data['data']['event'][$i] ."\t"
                    . $data['data']['time'][$i] ."\t"
                    . $data['data']['expression'][$i] ."\n";
            }
        }
        return $final;
    }

    /*
    Gets exons matching the query gene symbol or Ensembl gene ID
    limiting to given cancer type and p-value cutoff
    */
    // function get_exons($query, $pval, $cancer, $survival, $classification, $db) {
    function get_exons($query, $pval, $cancer, $classification, $db) {
        $results = array();
        $stmt = $db->prepare("SELECT
            Gene.Gene_ENS_ID AS gene_id,
            Gene.Gene_Symbol AS gene_symbol,
            Transcript.Transcript_ENS_ID AS transcript_id,
            Transcript_Exon.Exon_ID AS exon_id,
            Cancer_Exon_Survival.pval AS log_rank_pval,
            Cancer_Exon_Survival.hazard_ratio AS hazard_ratio,
            Cancer_Exon_Survival.hazard_ratio_pval AS hazard_ratio_pval,
            Cancer_Exon_Survival.qvalue AS hazard_ratio_qval,
            Cancer_Exon_Survival.Cancer_Name AS cancer,
            Cancer_Exon_Survival.Analysis_Type AS classification
            FROM Gene, Transcript, Transcript_Exon, Cancer_Exon_Survival
            WHERE Gene.Gene_ENS_ID = Transcript.Gene_ENS_ID
            AND Transcript.Transcript_ENS_ID = Transcript_Exon.Transcript_ENS_ID
            AND Transcript_Exon.Exon_ID = Cancer_Exon_Survival.Exon_ID
            AND (Gene.Gene_ENS_ID = :query OR Gene.Gene_Symbol = :query)
            AND Cancer_Exon_Survival.pval < :pval
            AND Cancer_Exon_Survival.hazard_ratio_pval < :pval
            AND Cancer_Exon_Survival.Cancer_Name = :cancer
            AND Cancer_Exon_Survival.Analysis_Type = :classification");

        // if ($survival == 'LR') {
        //   $stmt = $db->prepare('SELECT
        //       Gene.Gene_ENS_ID AS gene_id,
        //       Gene.Gene_Symbol AS gene_symbol,
        //       Transcript.Transcript_ENS_ID AS transcript_id,
        //       Transcript_Exon.Exon_ID AS exon_id,
        //       Cancer_Exon_Survival.pval AS pval,
        //       Cancer_Exon_Survival.Cancer_Name AS cancer,
        //       Cancer_Exon_Survival.Analysis_Type AS classification
        //       FROM Gene, Transcript, Transcript_Exon, Cancer_Exon_Survival
        //       WHERE Gene.Gene_ENS_ID = Transcript.Gene_ENS_ID
        //       AND Transcript.Transcript_ENS_ID = Transcript_Exon.Transcript_ENS_ID
        //       AND Transcript_Exon.Exon_ID = Cancer_Exon_Survival.Exon_ID
        //       AND (Gene.Gene_ENS_ID = :query OR Gene.Gene_Symbol = :query)
        //       AND Cancer_Exon_Survival.pval < :pval
        //       AND Cancer_Exon_Survival.Cancer_Name = :cancer
        //       AND Cancer_Exon_Survival.Analysis_Type = :classification');
        // } elseif ($survival == 'HR') {
        //   $stmt = $db->prepare('SELECT
        //       Gene.Gene_ENS_ID AS gene_id,
        //       Gene.Gene_Symbol AS gene_symbol,
        //       Transcript.Transcript_ENS_ID AS transcript_id,
        //       Transcript_Exon.Exon_ID AS exon_id,
        //       Cancer_Exon_Survival.hazard_ratio AS hazard_ratio,
        //       Cancer_Exon_Survival.hazard_ratio_pval AS pval,
        //       Cancer_Exon_Survival.Cancer_Name AS cancer,
        //       Cancer_Exon_Survival.Analysis_Type AS classification
        //       FROM Gene, Transcript, Transcript_Exon, Cancer_Exon_Survival
        //       WHERE Gene.Gene_ENS_ID = Transcript.Gene_ENS_ID
        //       AND Transcript.Transcript_ENS_ID = Transcript_Exon.Transcript_ENS_ID
        //       AND Transcript_Exon.Exon_ID = Cancer_Exon_Survival.Exon_ID
        //       AND (Gene.Gene_ENS_ID = :query OR Gene.Gene_Symbol = :query)
        //       AND Cancer_Exon_Survival.hazard_ratio_pval < :pval
        //       AND Cancer_Exon_Survival.Cancer_Name = :cancer
        //       AND Cancer_Exon_Survival.Analysis_Type = :classification');
        // }
        $stmt->execute(array(
            ':query' => $query,
            ':pval' => $pval,
            ':cancer' => $cancer,
            ':classification' => $classification
        ));
        $exons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($exons as $exon) {
            $results[$exon['exon_id']] = $exon;
        }
        return $results;
    }

    /*
    Gets survival data for given exons, cancer type,
    survival analysis method and classification method
    */
    function get_survival_data($exons, $cancer, $classification, $db) {
        // generate question marks for inputting exons to the statement
        $in = join(',', array_pad(array(), count($exons), '?'));
        // generate parameters array with cancer type and exons
        $params = array_merge(array($cancer), array_keys($exons));
        // generate the statement according to the given classification method
        if ($classification == 'Median') {
          $stmt = $db->prepare("SELECT
              Exon_Survival_Data_Median.Exon_ID AS exon_id,
              Exon_Survival_Data_Median.Group_ AS _group,
              Exon_Survival_Data_Median.Expression AS expression,
              Cancer_Patient_Info.Patient_ID AS patient_id,
              Cancer_Patient_Info.Time_ AS _time,
              Cancer_Patient_Info.Event_ AS _event
              FROM Exon_Survival_Data_Median, Cancer_Patient_Info
              WHERE Exon_Survival_Data_Median.Patient_ID = Cancer_Patient_Info.Patient_ID
              AND Exon_Survival_Data_Median.Cancer_Name = ?
              AND Exon_Survival_Data_Median.Exon_ID IN ($in)");
        } elseif ($classification == 'Quartile') {
          $stmt = $db->prepare("SELECT
              Exon_Survival_Data_Quartile.Exon_ID AS exon_id,
              Exon_Survival_Data_Quartile.Group_ AS _group,
              Exon_Survival_Data_Quartile.Expression AS expression,
              Cancer_Patient_Info.Patient_ID AS patient_id,
              Cancer_Patient_Info.Time_ AS _time,
              Cancer_Patient_Info.Event_ AS _event
              FROM Exon_Survival_Data_Quartile, Cancer_Patient_Info
              WHERE Exon_Survival_Data_Quartile.Patient_ID = Cancer_Patient_Info.Patient_ID
              AND Exon_Survival_Data_Quartile.Cancer_Name = ?
              AND Exon_Survival_Data_Quartile.Exon_ID IN ($in)");
        }
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // organize results
        $final = array();
        foreach ($results as $result) {
            if (!array_key_exists($result['exon_id'], $final)) {
                $final[$result['exon_id']] = array(
                    'info' => $exons[$result['exon_id']],
                    'data' => array(
                        'patient_id' => array(),
                        'expression' => array(),
                        'time' => array(),
                        'event' => array(),
                        'group' => array()
                    )
                );
            }
            $final[$result['exon_id']]['data']['patient_id'][] = $result['patient_id'];
            $final[$result['exon_id']]['data']['expression'][] = floatval($result['expression']);
            $final[$result['exon_id']]['data']['time'][] = intval($result['_time']);
            $final[$result['exon_id']]['data']['event'][] = intval($result['_event']);
            $final[$result['exon_id']]['data']['group'][] = $result['_group'];
        }
        return $final;
    }

    /*
    Runs R survival script and gets plots
    */
    function get_plots($survival_data, $format) {
        // make a temp JSON file from data
        $json_path = make_json($survival_data);

        // run the analysis
        $cmd = array('Rscript',
            dirname(__FILE__) . '/surv.R',
            $json_path,
            $format, '2>&1');
        $out = shell_exec(escapeshellcmd(implode(' ', $cmd)));

        // remove the temp file
        unlink($json_path);

        return json_decode($out);
    }

    /*
    Queries database, runs the analysis and return results
    */
    // function main($query, $pval, $cancer, $survival, $classification, $format, $db) {
    function main($query, $pval, $cancer, $classification, $format, $db) {

        // final array for results
        $results = array();

        // query the database for matching exons
        $exons = ($format == 'png')
            // ? get_exons($query, $pval, $cancer, $survival, $classification, $db)
            ? get_exons($query, $pval, $cancer, $classification, $db)
            : array(
                $query => array(
                    'exon_id' => $query,
                    'cancer' => $cancer
            ));

            // if there is any exons matched
        if (count($exons) > 0) {

            // query survival data for those exons
            $survival_data = get_survival_data($exons, $cancer, $classification, $db);

            // return TSV format without calling R
            if ($format == 'tsv') {
                return make_tsv($survival_data);
            }

            // final results including plots
            // $results = $survival_data;
            $results = get_plots($survival_data, $format);
        }
        return $results;
    }

    // collect GET variables
    $query = (isset($_GET['query']) === true && empty($_GET['query']) === false) ? sanitize($_GET['query']): '';
    $pval = (isset($_GET['pval']) === true && empty($_GET['pval']) === false) ? floatval(sanitize($_GET['pval'])): 0.05;
    $cancer = (isset($_GET['cancer']) === true && empty($_GET['cancer']) === false) ? sanitize($_GET['cancer']): '';
    // $survival = (isset($_GET['survival']) === true && empty($_GET['survival']) === false) ? sanitize($_GET['survival']): '';
    $classification = (isset($_GET['classification']) === true && empty($_GET['classification']) === false) ? sanitize($_GET['classification']): '';
    $format = (isset($_GET['format']) === true && empty($_GET['format']) === false) ? sanitize($_GET['format']): 'png';

    // empty array for results
    $results = array();
    // these conds for querying data
    if ($query != '' && $cancer != '') {
        // db connection
        try {
            $db = new PDO(
                'mysql:host='. $config['host']
                .';port='. $config['port']
                .';dbname='. $config['dbname'], $config['user'], $config['pass']);
        } catch (PDOException $e) {
            // set header for JSON response
            header('Content-Type: application/json');
            echo json_encode(array('error' => $e->getMessage()));
            die();
        }

        // $results = main($query, $pval, $cancer, $survival, $classification, $format, $db);
        $results = main($query, $pval, $cancer, $classification, $format, $db);
        if ($format == 'tsv') {
            echo $results;
            die();
        }

        // set header for JSON response
        header('Content-Type: application/json');
        echo json_encode($results);
        die();
    }

?>
