<meta charset='utf-8'>
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
//阿里支付文档:

function getPaymentPlatform(){
    if(isset($_SERVER['HTTP_USER_AGENT'])){
        $UA = $_SERVER['HTTP_USER_AGENT'];
        if(strstr($UA, 'QQBrowser')) return 'wxpay';
        else if(strstr($UA ,'AlipayClient')) return 'alipay'; 
        else return 'unknow';
    }

    return 'No HTTP_USER_AGENT';
}

//统一下单API
//JSAPI 必须传openid
function wxPayUnifiedOrder($shop_name,$total_price,$notify_url,$openid=""){
    global $_W;
    $appid = '';
    $appsecret='';
    $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
    $key = '';
    if(!empty($openid)) $data['openid'] = $openid;

    $data['appid']              = '';
    $data['mch_id']             = '';
    $data['nonce_str']          = makeNonceStr();
    $data['device_info']        = "WEB";
    $data['body']               = "$shop_name";
    $data['out_trade_no']       = makeOrderNo();
    $data['total_fee']          = $total_price;
    $data['spbill_create_ip']   = $_SERVER['REMOTE_ADDR'];
    $data['notify_url']         = "http://www.baidu.com";
    $data['trade_type']         = "JSAPI";
    $data['sign']               = makeSign($data, $key);
    dump($data, 0);
    $xml = xml_encode($data);
    $res = sendHttpXml($url, $xml);
    $data = xml_decode($res);
    dump($data);
}

//创建订单并支付
//阿里手机网站支付
function aliPayWapPay($config, $param){
    load()->classs('myalipay');
    $pay = new Myalipay($config);
    $pay->wapPay($param);
}


//生成订单号
function makeOrderNo(){
    return md5("123".time()."456");
}


//产生随机字符串
function makeNonceStr($length = 32)
{
    $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
    $str = "";
    for ($i = 0; $i < $length; $i++) {
        $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
    }
    return $str;
}


//md5签名算法
function makeSign($params, $key){
    dump($params,0);
    //按关键字序排序参数
    ksort($params);
    $string = toUrlParams($params);
    //在string后加入KEY
    $string = $string . "&key=" . $key;
    //MD5加密
    $string = md5($string);
    //所有字符转为大写
    $result = strtoupper($string);
    return $result;
}

//数组转URL参数字符
function toUrlParams($urlObj) {
    $buff = "";
    foreach ($urlObj as $k => $v) {
        if ($k != "sign") {
            $buff .= $k . "=" . $v . "&";
        }
    }
    $buff = trim($buff, "&");
    return $buff;
}

//数组转xml
//微擎自带： framework/function/global.func.php:924 array2Xml
function xml_encode($data){
    if (!is_array($data) || count($data) <= 0) {
        echo("数组数据异常！");
    }

    $xml = "<xml>";
    foreach ($data as $key => $val) {
        if (is_numeric($val)) {
            $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
        } else {
            $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
    }
    $xml .= "</xml>";
    return $xml;
}

//xml转数组
//微擎自带： framework/function/global.func.php:943 xml_decodeay
function xml_decode($xml){
    if (!$xml) { die("xml数据异常！"); }
    //将XML转为array 禁止引用外部xml实体
    libxml_disable_entity_loader(true);
    $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $data;
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

