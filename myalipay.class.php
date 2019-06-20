<?php 
class Myalipay{

    public function __construct($config){

        $norurl = 'https://openapi.alipay.com/gateway.do';    //正式
        $devurl = 'https://openapi.alipaydev.com/gateway.do'; //沙箱
        $charset = 'UTF-8';
        $sign_type = 'RSA2';
        $version = '1.0';
        $this->config = $config;

        //检查必填配置
        $this->chkConfig('app_id');
        $this->chkConfig('notify_url');
        $this->chkConfig('return_url');
        $this->chkConfig('merchant_private_key');
        $this->chkConfig('alipay_public_key');

        //公共请求参数
        $this->pubParam = array();
        $this->pubParam['format']       = 'JSON';
        $this->pubParam['charset']      = $charset;
        $this->pubParam['version']      = $version;
        $this->pubParam['sign_type']    = $sign_type;
        $this->pubParam['app_id']       = $config['app_id'];
        $this->pubParam['return_url']   = $config['return_url'];
        $this->pubParam['notify_url']   = $config['notify_url'];

        //设置公私钥,网关
        $this->gateway_url              = $devurl;
        $this->alipay_public_key        = $config['alipay_public_key'];
        $this->merchant_private_key     = $config['merchant_private_key'];

    }


    private function chkConfig($name){
        $item = $this->config[$name];
        if(empty($item) || trim($item)==''){
            echo $name.' should not be NULL!';
            exit;
        }
    }


    public function wapPay($param){
        //这里记得补上日志
        $this->payParam = array();
        $this->biz_content = array();

        //支付参数
        $this->payParam['timeout_express']  = '1m';
        $this->payParam['product_code'] = 'QUICK_WAP_WAY';
        $this->payParam['body'] = $param['body'];
        $this->payParam['subject'] = $param['subject'];
        $this->payParam['out_trade_no'] = $param['out_trade_no'];
        $this->payParam['total_amount'] = $param['total_amount'];

        //公共参数
        $this->pubParam['method'] = 'alipay.trade.wap.pay';
        $this->pubParam['timestamp'] = date('Y-m-d H:i:s');
        $this->pubParam['biz_content'] = json_encode($this->payParam);
        
        
        //对公共参数签名
        $sign = $this->mkSign("RSA2");
        $this->pubParam['sign'] = $sign;

        //发送请求，此时客户端会弹出支付宝支付了
        $this->sendHttpForm();
    }


    protected function mkSign($sign_type = "RSA") {

        //排序与拼接
        ksort($this->pubParam);
        $this->url_param_str = $data = $this->kvConcat($this->pubParam);

        $res = "-----BEGIN RSA PRIVATE KEY-----\n".
               wordwrap($this->merchant_private_key, 64, "\n", true) .
               "\n-----END RSA PRIVATE KEY-----";

        //密钥文件读取方式
        #$priKey = file_get_contents($this->rsaPrivateKeyFilePath);
        #$res = openssl_get_privatekey($priKey);
        //用完记得释放
        #if(!$this->checkEmpty($this->rsaPrivateKeyFilePath)){
        #    openssl_free_key($res);
        #}

        ($res) or die('您使用的私钥格式错误，请检查RSA私钥配置');

        //签名后的数据会保存在$sign
        if ("RSA2" == $sign_type) {
            openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
        } else {
            openssl_sign($data, $sign, $res);
        }

        $sign = base64_encode($sign);
        return $sign;
    }

    //键值拼接
    function kvConcat($urlObj) {
        $buff = "";
        foreach ($urlObj as $k => $v) {
            if ($k != "sign") {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    protected function sendHttpForm() {
        $gateway_url = $this->gateway_url;
        $charset = $this->pubParam['charset'];
        $pubParam = $this->pubParam;

        $shtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$gateway_url."?charset=".trim($charset)."' method='POST'>";
        while (list ($key, $val) = each ($pubParam)) {
            $val = str_replace("'","&apos;",$val);
            $shtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
        }
        //submit按钮控件请不要含有name属性
        $shtml = $shtml."<input type='submit' value='ok' style='display:none;''></form>";
        $shtml = $shtml."<script>document.forms['alipaysubmit'].submit();</script>";
        echo $shtml;
        #return $shtml;
    }

    
}

