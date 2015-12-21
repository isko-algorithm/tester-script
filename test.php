<?php
/**
 * Created by PhpStorm.
 * User: gabrielgagno
 * Date: 11/27/15
 * Time: 5:12 PM
 */

$resArray = array();
$link = mysqli_connect('localhost', 'root');
if (!$link) {
    die('Not connected : ' . mysql_error());
}

// make foo the current db
$db_selected = mysqli_select_db($link, 'nutch');
if (!$db_selected) {
    die ('Can\'t use foo : ' . mysql_error());
}
$sampArray = ['business.csv', 'community.csv', 'shop.csv', 'tattoo.csv', 'www.csv'];

# curl the URL
$ch = curl_init();
$successCtr = 0;
$overAllCtr = 0;
$goodEnoughCtr = 0;
$logicProblem = 0;
$topNArray = array(0,0,0,0,0);
$notCrawledCounter = 0;
$ctr = 0;
foreach($sampArray as $sRow) {
    echo "NOW OPERATING ON: ".$sRow;
    $file = fopen($sRow, 'r');
    $data = fgetcsv($file);
    while(!feof($file)){
        $data = fgetcsv($file);
        $curlUrl = 'http://50.18.169.52/search-data?q='.urlencode($data[0]);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_URL => $curlUrl,
            CURLOPT_RETURNTRANSFER => 1,
        ));
        $response = curl_exec($ch);
        $decodedResponse = json_decode($response);
        $success = false;
        $actual = null;
        $expected = null;
        echo "SEARCH TERM: ".$data[0]."\n";
        for($i=0;$i<5;$i++) {
            $success = false;
            if(!isset($decodedResponse->result[$i]->_source->url)){
                continue;
            }

            $urlRes = $decodedResponse->result[$i]->_source->url;

            if(substr($urlRes, 0, strlen('http://')) === 'http://'){
                $actual = str_replace('http://', '', $urlRes);
            }
            else if(substr($urlRes, 0, strlen('https://')) === 'https://'){
                $actual = str_replace('https://', '', $urlRes);
            }

            if(substr($data[1], 0, strlen('http://')) === 'http://'){
                $expected = str_replace('http://', '', $data[1]);
            }
            else if(substr($data[1], 0, strlen('https://')) === 'https://'){
                $expected = str_replace('https://', '', $data[1]);
            }

            if(strcmp($actual, $expected)==0) {
                echo "SUCCESS! RANK: ".($i+1)."\n";
                $topNArray[$i]++;
                if($i==0) {
                    echo "TOP\n";
                    $successCtr++;
                }
                else {
                    $goodEnoughCtr++;
                }
                $success = true;
                $ctr++;
                break;
            }
        }
        if(!$success) {
            $sql = 'select * from nutch.webpage where baseUrl like \'%'.$expected.'%\'';
            $result = $link->query($sql);
            if($result->num_rows > 0){
                echo "FAIL (logic problem)\n";
                $logicProblem++;
                $ctr++;
            }
            else{
                echo "FAIL (not crawled)\n";
                $notCrawledCounter++;
            }
        }
        echo "EXPECTED TOP RESULT: $data[1]'\n";
        if(isset($decodedResponse->result[0]->_source->url)) {
            echo "ACTUAL:" . $decodedResponse->result[0]->_source->url . "'\n";
        }
        echo "...\n";
        $overAllCtr++;
    }
    fclose($file);
}

echo "FINAL RESULTS:\n".
    "SAMPLE SIZE: ".$overAllCtr."\n".
    "ALL VALID SITES (CRAWLED SITES):".$ctr."\n***\n".
    "PERCENTAGE OF SUCCESS OVER CRAWLED SITES: ".(($successCtr/$ctr)*100)."%\n".
    "PERCENTAGE OF SUCCESS INCLUDING INVALID SITES".(($successCtr/$overAllCtr)*100)."%\n***\n".
    "PERCENTAGE OF GOOD OVER CRAWLED SITES: ".(($goodEnoughCtr/$ctr)*100)."%\n".
    "PERCENTAGE OF GOOD INCLUDING INVALID SITES".(($goodEnoughCtr/$overAllCtr)*100)."%\n***\n".
    "SITES NOT CRAWLED: ".$notCrawledCounter."(".(($notCrawledCounter/$overAllCtr)*100)."%)\n".
    "SITES WITH LOGIC PROBLEMS:".$logicProblem."(".(($logicProblem/$overAllCtr)*100)."%)\n".
    "-percentage overall".(($logicProblem/$overAllCtr)*100)."%\n".
    "-percentage over valid sites".(($logicProblem/$ctr)*100)."%\n".
    "\nITEMS FOUND WITHIN THE TOP 5\n";
for($i=0;$i<5;$i++) {
    echo "TOP ".($i+1).": ".$topNArray[$i]."\n";
}
