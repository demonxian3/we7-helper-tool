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
        echo "淇敼鎴愬姛!<br>";
    }else{
        echo "淇敼澶辫触<br>";
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
        echo "鑰楁椂: ".round(microtime(true)-$t, 3)."绉�<br>";
        echo '鑰楀瓨: '.round(memory_get_usage()/1000000,3).'M<br/>';
        $t = 0;
    }
}
?>
