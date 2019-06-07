<?php 
defined('IN_IA') or exit('Access Denied');


function dump($var){
    echo '<pre>';
    var_dump($var);
    echo '</pre>';
}


function pdo_lastsql(){
    $debug = pdo_debug(0);
    $count = count($debug);
    $lastInfo = $debug[$count-1];
    $lastSql = $lastInfo['sql'];
    foreach($lastInfo['params'] as $k => $v){
        $lastSql = str_replace($k, $v, $lastSql);
    }
    echo "[sql]=><br>". $lastSql ."<br>";
    echo "[error]=><br>";
    dump($lastInfo['error']);
}

function pdo_showsql(){
    $debug = pdo_debug(0);

    foreach($debug as $val){
        $sql = $val['sql'];
        $key = $val['params'];

        foreach($key as $k => $v){
            $sql = str_replace($k, "'$v'", $sql);
        }

        echo '<div style="border:1px solid #ccc; background:#eee; padding:20px;">';
        echo $sql . "<br>";
        foreach($key as $k => $v){
            echo "[$k] => $v<br>";
        }
        echo '</div>';
    }
}

function pdo_setdata($tableName, $update, $condition){
    if(!preg_match('/cqxingyu_farm_/',$tableName))
        $tableName = 'cqxingyu_farm_' . $tableName;
    $res = pdo_update($tableName, $update, $condition);
    echo "<div style='border:1px solid #ccc;padding:20px;background:#eee'>";
    if($res){
        echo "修改成功!<br>";
    }else{
        echo "修改失败<br>";
        pdo_lastsql();
    }
    echo "</div>";
}
load()->classs("wdebug");


/**
 * @filename __FILE__
 * @connstr mysql:root@localhost/database
 * @password mysql_pass_word
 */

function showHead($filename, $connstr){
    $showdoc = new Wdebug($connstr);
    $showdoc->AutoMarkdown($filename);
}

function showFoot($result){
    $showdoc = new Wdebug;
    error_reporting(0);
    $showdoc->AutoResult($result);
}

function showComment($tableName, $highLightArr=[]){
    $showdoc = new Wdebug;
    $showdoc->showComment($tableName, $highLightArr);
}

function showCommentByKey($keyword=""){
    $showdoc = new Wdebug;
    $showdoc->showCommentByKeyword($keyword);
}

function showGpcTable($arr){
    $showdoc = new Wdebug;
    $showdoc->MkGpcTable($arr);
}

function showMdTable($arr){
    $showdoc = new Wdebug;
    $showdoc->MdTable($arr);
}

function makeTestData(){
    $showdoc = new Wdebug;
    $showdoc->AutoInsertData();
}

function performanceTest($begin=false){
    static $t;
    if($begin){
        $t = microtime(true);
    }else{
        echo "耗时: ".round(microtime(true)-$t, 3)."秒<br>";
        echo '耗存: '.round(memory_get_usage()/1000000,3).'M<br/>';
        $t = 0;
    }
}

function sendHttpGet($url){
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    return curl_exec($con);
}


function sendHttpPost($url, $data){
    $con = curl_init((string)$url);
    if(gettype($data) === 'array') 
        $data = json_encode($data);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    curl_setopt($con, CURLOPT_VERBOSE, 1);
    return curl_exec($con);
}


function sendHttpDel($url, $headers){
    if($ch = curl_init($url)){
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (int) $status;
    }
    else
        return false;
}


function sendHttpPut($url, $field, $headers){
    $fields = (is_array($fields)) ? http_build_query($fields) : $fields;

    if($headers)
        $headers[] = "Content-Length: ". strlen($fields);

    if($ch = curl_init($url)){
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return (int) $status;
    }
    else
        return false;
}

?>
