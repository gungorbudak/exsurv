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

    function get_exons($query, $cancer, $pval, $db) {
        $final = array();
        $stmt = $db->prepare('SELECT
            Gene.Gene_ENS_ID AS gene_id,
            Gene.Gene_Symbol AS gene_symbol,
            Transcript.Transcript_ENS_ID AS transcript_id,
            Transcript_Exon.Exon_ID AS exon_id,
            Cancer_Exon_Survival.pval AS pval,
            Cancer_Exon_Survival.Cancer_Name AS cancer
            FROM Gene, Transcript, Transcript_Exon, Cancer_Exon_Survival
            WHERE Gene.Gene_ENS_ID = Transcript.Gene_ENS_ID
            AND Transcript.Transcript_ENS_ID = Transcript_Exon.Transcript_ENS_ID
            AND Transcript_Exon.Exon_ID = Cancer_Exon_Survival.Exon_ID
            AND (Gene.Gene_ENS_ID = :query OR Gene.Gene_Symbol = :query)
            AND Cancer_Exon_Survival.Cancer_Name = :cancer
            AND Cancer_Exon_Survival.pval < :pval');
        $stmt->execute(array(
            ':query' => $query,
            ':cancer' => $cancer,
            ':pval' => $pval
        ));
        $exons = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($exons as $exon) {
            $final[$exon['exon_id']] = $exon;
        }
        return $final;
    }

    function get_survival_data($exons, $cancer, $pval, $db) {
        $in = join(',', array_pad(array(), count($exons), '?'));
        $params = array_merge(array($cancer), array_keys($exons));
        $stmt = $db->prepare("SELECT
            Exon_Survival_Data.Exon_ID AS exon_id,
            Exon_Survival_Data.Group_ AS _group,
            Exon_Survival_Data.Expression AS expression,
            Cancer_Patient_Info.Patient_ID AS patient_id,
            Cancer_Patient_Info.Time_ AS _time,
            Cancer_Patient_Info.Event_ AS _event
            FROM Exon_Survival_Data, Cancer_Patient_Info
            WHERE Exon_Survival_Data.Patient_ID = Cancer_Patient_Info.Patient_ID
            AND Exon_Survival_Data.Cancer_Name = ?
            AND Exon_Survival_Data.Exon_ID IN ($in)");
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    function surv($json_path, $format) {
        $cmd = array('Rscript',
            dirname(__FILE__) . '/surv.R',
            $json_path,
            $format, '2>&1');
        $out = shell_exec(escapeshellcmd(implode(' ', $cmd)));
        return json_decode($out);
    }

    function export_tsv($survival_data) {
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
    Queries database, runs the analysis and return results
    */
    function main($query, $cancer, $pval, $format, $db) {
        // final array for results
        $final = array();
        // query the database for matching exons
        $exons = ($format == 'png')
            ? get_exons($query, $cancer, $pval, $db)
            : array(
                $query => array(
                    'exon_id' => $query,
                    'cancer' => $cancer
            ));
        // if there is any exons matched
        if (count($exons) > 0) {
            // query survival data for those exons
            $survival_data = get_survival_data($exons, $cancer, $pval, $db);
            // return TSV format without calling R
            if ($format == 'tsv') {
                return export_tsv($survival_data);
            }
            // make a temp JSON file from data
            $json_path = make_json($survival_data);
            // final results including plots
            $final = surv($json_path, $format);
            // delete the temp JSON file
            unlink($json_path);
            // sort the array by p-value
            if ($format == 'png') {
                usort($final, function($a, $b) {
                    $a = floatval($a['info']['pval']);
                    $b = floatval($b['info']['pval']);
                    if ($a == $b) {
                        return 0;
                    }
                    return ($a > $b) ? 1 : -1;
                });
            }
        }
        return $final;
    }

    // collect GET variables
    $query = (isset($_GET['query']) === true && empty($_GET['query']) === false) ? sanitize($_GET['query']): '';
    $cancer = (isset($_GET['cancer']) === true && empty($_GET['cancer']) === false) ? sanitize($_GET['cancer']): '';
    $pval = (isset($_GET['pval']) === true && empty($_GET['pval']) === false) ? floatval(sanitize($_GET['pval'])): 0.05;
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
        $results = main($query, $cancer, $pval, $format, $db);
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
