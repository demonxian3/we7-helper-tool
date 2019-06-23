# 微擎助手

### 安装
- 放到微擎framework目录即可

```
▾ framework/  
  ▸ builtin/ 
  ▾ class/  
      myalipay.class.php  
      mywxpay.class.php 
      markdown.class.php 
  ▾ function/
      helper.func.php
```

- 全局引入
``` bash
vim framework/bootstrap.inc.php
```

添加下面一句到 42 行   

``` php
load()->func('wdebug');     
dump(['hello']);
```


### 使用

1. 公共函数

``` php
dump($var);             #格式化打印变量
pdo_showsql(1);         #打印最后一条SQL语句
pdo_showsql();          #打印所有SQL语句
performanceTest(1);     #性能测试，输出内存和时间
//这里放被测试的代码 
performanceTest();
clearSession();         #清除会话
getPaymentPlatform();   #判断微信还是支付宝
```
 

2. 微信支付

``` php
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
```

3. 支付宝支付

``` php
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

```

4. 自动文档

``` php
$showdoc = new Markdown($connstr);
$showdoc->AutoMarkdown(__FILE__);
$showdoc->AutoResult($result);
$showdoc->showComment($tableName, $highLightArr);
$showdoc->showCommentByKeyword("user");
$showdoc->MkGpcTable($_GPC);
$showdoc->MdTable(['a','b']);
$showdoc->AutoInsertData();
```



