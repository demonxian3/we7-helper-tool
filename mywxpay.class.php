<?php  
class Mywxpay{
    public function __construct($config){
        $this->chkItem($config, ['appid', 'mch_id', 'appsecret','notify_url','key']);
        $this->config = $config;
    }


    public function unifiedOrder($param){
        //文档 https://pay.weixin.qq.com/wiki/doc/api/jsapi.php?chapter=9_1
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        //检查必填项
        $this->chkItem($param, ['openid', 'body', 'total_fee']);

        //默认使用外部订单号，否则自己构造
        if(empty($param['out_trade_no']) && trim($param['out_trade_no']))
            $this->payParam['out_trade_no'] = $this->mkTradeNo();
        else
            $this->payParam['out_trade_no'] = $param['out_trade_no'];
        

        //构造下单必填XML
        $this->payParam['appid']        = $this->config['appid'];
        $this->payParam['mch_id']       = $this->config['mch_id'];
        $this->payParam['notify_url']   = $this->config['notify_url'];
        $this->payParam['nonce_str']     = $this->mkNonceStr();
        $this->payParam['openid']       = $param['openid'];
        $this->payParam['body']         = $param['body'];
        $this->payParam['total_fee']    = $param['total_fee'] * 100;
        $this->payParam['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
        $this->payParam['trade_type']   = 'JSAPI';
        $this->payParam['sign']         = $this->mkSign($this->payParam, $this->config['key']);

        //发送请求
        $xml =  $this->xml_encode($this->payParam);
        $rep =  $this->sendHttpXml($url, $xml);
        $data = $this->xml_decode($rep);

        //构造JSAPI调用参数: 注意需要配置授权目录和支付目录
        $res['appId'] =  $data['appid'];
        $res['timeStamp'] = time();
        $res['nonceStr'] = $this->mkNonceStr();
        if(!isset($data['prepay_id']) || isset($data['err_code_des']))
            $res["error_msg"] = $data['err_code_des'];
        else
            $res["package"] = "prepay_id=".$data["prepay_id"];
        $res['signType'] = 'MD5';
        $res['paySign'] = $this->mkSign($res, $this->config['key']);
        return json_encode($res);
    }

    //检查必填参数是否已填
    private function chkItem($array, $names){
        foreach($names as $name){
            $item = $array[$name];
            if(empty($item) || trim($item)==''){
                echo $name.' should not be NULL!';
                exit;
            }
        }
    }


    //生成订单号: 假设同用户不能1秒内下两单
    private function mkTradeNo(){
        return md5(time().$openid);
    }

    //随机字符串
    private function mkNonceStr($length = 32){
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    //MD5签名: key是支付密钥
    private function mkSign($params, $key){
        //按关键字序排序参数
        ksort($params);
        $string = $this->kvConcat($params);
        //在string后加入KEY
        $string = $string . "&key=" . $key;
        //MD5加密
        $string = md5($string);
        //所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    //键值拼接
    private function kvConcat($urlObj){
        $buff = "";
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    //也可以用微擎的  array2Xml
    private function xml_encode($data){
        if (!is_array($data) || count($data) <= 0) {
            echo("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($data as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<$key>$val</$key>";
            } else {
                $xml .= "<$key><![CDATA[$val]]></$key>";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    //也可以用微擎的  xml2Array
    private function xml_decode($xml){
        if (!$xml) die("xml数据异常！");

        //将XML转为array 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }

    //发送XML请求
    private function sendHttpXml($url, $xml){
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
}
?>
