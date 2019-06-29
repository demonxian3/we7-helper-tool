<?php
//touch Notify.log && chmod 777 Notify.log

//XML格式转换
function xml_decode($xml){
    if (!$xml) die("xml数据异常！");
    libxml_disable_entity_loader(true);
    $data = json_decode(json_encode(simplexml_load_string($xml,
        'SimpleXMLElement', LIBXML_NOCDATA)), true);
    return $data;
}

//键值对拼接
function kvConcat($urlObj){
    $buff = "";
    foreach ($urlObj as $k => $v) {
        if ($k != "sign") {
            $buff .= $k . "=" . $v . "&";
        }
    }
    $buff = trim($buff, "&");
    return $buff;
}


//日志系统
function log_write($var){
    if(is_array($var) || is_object($var)){
        var_dump($var);
        $var = ob_get_clean();
    }

    file_put_contents('Notify.log', $var.PHP_EOL ,FILE_APPEND);
}

//不能用UA，通过参数key来区分支付宝和微信
function getNotifiedData(){
    if(isset($_POST['notify_type']) && $_POST['notify_type'] == 'trade_status_sync'){
        $_POST['notify_platform_type'] = 'alipay';
        return $_POST;
    }else{
        $notifiedData = file_get_contents('php://input');
        $data = xml_decode($notifiedData);
        $data['notify_platform_type'] = 'wxpay';
        return $data;
    }
}


//入口
function main(){

    //开启缓冲区
    ob_start();

    //获取支付宝/微信回调参数
    $notifiedData = getNotifiedData();

    //微信回调日志记录
    if ($notifiedData['notify_platform_type'] == 'wxpay'){
        log_write($notifiedData);
    }

    //支付宝回调日志记录
    else if($notifiedData['notify_platform_type'] == 'alipay'){
        log_write($notifiedData);
    }

    //这里没用加载进微擎数据库系统，使用原生的
    $conn = mysqli_connect('localhost', 'root', '30caa3216fbb4bac', 'cssc2_xyqkl_cn');

    //标记支付成功
    $sql = "update ims_fy_city_order set status = 2 where order_sn = '$notifiedData[out_trade_no]'";
    log_write($sql);
    $res = mysqli_query($conn, $sql);

    echo 'success';
    exit;
}

main();
