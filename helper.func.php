<?php 
defined('IN_IA') or exit('Access Denied');

//注意使用 load()->func() 导致静态全局变量失效,只能声明常量使用;
defined('CR') or define('CR', '<br>');
defined('HDIVELE') or define('HDIVELE','<div style="border:1px solid #ccc; background:#eee;padding:20px;">');
defined('HPREELE') or define('HPREELE','<pre style="font-size:16px;font-family:微软雅黑;white-space:pre-wrap!important;word-wrap:break-word!important;*white-space:normal!important;">');


defined('HDIVEND') or define('HDIVEND','</div>');
defined('HPREEND') or define('HPREEND','</pre>');

load()->classs("markdown");


/****************************
          Common
****************************/
function dump($var, $quit=true, $file=""){

    echo HDIVELE . HPREELE;
    $varType = gettype($var);

    switch($varType){
        case 'array':
            $var['__count'] = count($var,COUNT_RECURSIVE); 
            print_r($var);
            break;

        case 'object':
            $className = get_class($var);
            $var->__properties = get_object_vars($var);
            $var->__methods = get_class_methods($className);
            $var->__className = $className;
            $var->__parentClassName = get_parent_class($className);
            print_r($var);
            break;

        case 'string':
            echo 'string('.strlen($var).') '. CR . htmlspecialchars($var);
            break;

        default:
            echo var_dump($var);
    }

    if($file) echo "@@ ".$file;
    echo HPREEND.HDIVEND;
    if($quit) exit;

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

function clearSession(){
    session_start();
    $_SESSION = array();
    if(isset($_COOKIE[session_name()]))
        setcookie(session_name(), '', time()-42000, '/');
    session_destroy();
}

/****************************
          Database
 ****************************/
function pdo_showsql($onlyLast = false){
    $debug = pdo_debug(0);

    if($onlyLast){
        $count = count($debug);
        $debug = array($debug[$count-1]);
    }

    foreach($debug as $val){
        echo HDIVELE;
        $sql = $val['sql'];
        $key = $val['params'];

        foreach($key as $k => $v){ 
            $sql = str_replace($k, "'$v'", $sql); 
        }
        foreach($key as $k => $v){ 
            $sql .= CR. "[$k] => $v" ;
        }
        echo $sql. HDIVEND;
    }
}

function pdo_setdata($tableName, $update, $condition){
    echo HDIVELE;
    $prefix = tablename();
    if(!preg_match('prefix',$tableName))
        $tableName = tablename($tableName);

    $res = pdo_update($tableName, $update, $condition);
    if($res){ 
        echo "修改成功!<br>"; 
    } else{
        echo "修改失败<br>";
        pdo_lastsql();
    }
    echo HDIVEND;
}

/****************************
          payment
****************************/
//微信支付文档: https://pay.weixin.qq.com/wiki/doc/api/index.html
//阿里支付文档: https://docs.open.alipay.com/203

//通过UA判断支付方式
function getPaymentPlatform(){
    if(isset($_SERVER['HTTP_USER_AGENT'])){
        $UA = $_SERVER['HTTP_USER_AGENT'];
        if(strstr($UA, 'QQBrowser')) return 'wxpay';
        else if(strstr($UA ,'AlipayClient')) return 'alipay'; 
        else return 'unknow';
    }
    return 'No HTTP_USER_AGENT';
}

//微信下单示例，仅供参考
if (!function_exists(wxpay)){
function wxpay(){
    $config['mch_id']     = 'your mch_id';
    $config['appid']      = 'your appid';
    $config['appsecret']  = 'your appsecret';
    $config['key']        = 'your pay key';
    $config['notify_url'] = 'http://'. $_SERVER['SERVER_ADDR'].'/notify.php';

    $param['body'] = 'your payment title';
    $param['total_fee'] = 300;
    $param['openid'] = $openid;
    $param['out_trade_no'] = $out_trade_no;

    //记得配置授权目录，支付目录
    load()->classs('mywxpay');
    $pay = new Mywxpay($config);
    $jsp = $pay->unifiedOrder($param);
    include $this->template('wxpay');
    exit;
}
}

//阿里下单示例,仅供参考
if (!function_exists(alipay)){
    function alipay(){
        $config['app_id'] = 'your app_id';
        $config['notify_url'] = 'http://'. $_SERVER['SERVER_ADDR'].'/notify_url.php';
        $config['return_url'] = 'http://'. $_SERVER['SERVER_ADDR'].'/return_url.php';
        $config['alipay_public_key'] = 'MIIBIjANBg...';
        $config['merchant_private_key'] = 'MIIEvQIBADANBgkqhkiG9...';
    
        $param['subject']       = 'payment subject';
        $param['body']          = 'customer description';
        $param['total_amount']  = 300;
        $param['out_trade_no']  = date('YmdHis').time();
    
        //执行完自动弹出支付,界面不像微信要自己写
        load()->classs('myalipay');
        $pay = new Myalipay($config);
        $pay->wapPay($param);
    }
}

/****************************
          Markdown
****************************/

//@param1  __FILE__
//@param2  mysql:root@localhost/database/password
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


/****************************
          Netowrk 
 ****************************/
function sendHttpGet($url){
    $con = curl_init((string)$url);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
}

function sendHttpForm($url, $data){
    $con = curl_init((string)$url);
    if(is_array($data)) 
        $data = http_build_query($data);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_HTTPHEADER, Array("Content-Type:application/x-www-form-urlencoded; charset=utf-8"));
    curl_setopt($con, CURLOPT_POSTFIELDS, $data);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    curl_setopt($con, CURLOPT_VERBOSE, 1);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
}

function sendHttpJson($url, $json){
    $con = curl_init((string)$url);
    if(gettype($json) === 'array') 
        $json = json_encode($json);
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_HTTPHEADER, Array("Content-Type:application/json; charset=utf-8"));
    curl_setopt($con, CURLOPT_POSTFIELDS, $json);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    curl_setopt($con, CURLOPT_VERBOSE, 1);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
}

function sendHttpXml($url, $xml){
    $con = curl_init((string)$url);
    if(!stristr($xml,"<xml>")) 
        return "xml invalid";
    curl_setopt($con, CURLOPT_POST, true);
    curl_setopt($con, CURLOPT_HTTPHEADER, Array("Content-Type:text/xml; charset=utf-8"));
    curl_setopt($con, CURLOPT_POSTFIELDS, $xml);
    curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($con, CURLOPT_TIMEOUT, 5);
    curl_setopt($con, CURLOPT_VERBOSE, 1);
    $result = curl_exec($con);
    curl_close($con);
    return $result;
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

