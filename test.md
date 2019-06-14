# 微擎系统研究-数据库配置追踪
 
在开发微擎助手工具时，想读取MYSQL的账号密码配置信息，发现全局变量$_W 居然没有。   
明明在 `data/config.php`下面有配置好，所以产生两个问题：

1. 微擎底层是如何连接数据库
2. 微擎地产如何隐藏MYSQL账号信息

追踪一下，先从 最外面的 `index.php`开始

### index.php
- line 7:

``` php
require './framework/bootstrap.inc.php';   
```

在入口文件这里还没有 IN_IA 和 $_W变量， 这两个变量是在 `bootstrap.inc.php` 文件里产生的


### framework/bootstrap.inc.php   

- line 13:   
``` php
$configfile = IA_ROOT . "/data/config.php";

if(!file_exists($configfile)) {
    if(file_exists(IA_ROOT . '/install.php')) {
        header('Content-Type: text/html; charset=utf-8');
        require IA_ROOT . '/framework/version.inc.php';
        echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
        echo "·如果你还没安装本程序，请运行<a href='".(strpos($_SERVER['SCRIPT_NAME'], 'web') === false ? './install.php' : '../install.php')."'> install.php 进入安装&gt;&gt; </a><br/><br/>";
        echo "&nbsp;&nbsp;<a href='http://www.w7.cc' style='font-size:12px' target='_blank'>Power by WE7 " . IMS_VERSION . " &nbsp;微擎公众平台自助开源引擎</a>";
        exit();
    } else {
        header('Content-Type: text/html; charset=utf-8');
        exit('配置文件不存在或是不可读，请检查“data/config”文件或是重新安装！');
    }
}

require $configfile;

```

程序执行最初引入了一下配置文件，如果没有配置文件表示没有安装微擎。

- line 51: 
``` php
$_W['config'] = $config;
$_W['config']['db']['tablepre'] = !empty($_W['config']['db']['master']['tablepre']) ? $_W['config']['db']['master']['tablepre'] : $_W['config']['db']['tablepre'];
```

这里将配置文件导入到 $_W 变量里头，此时有 $_W 里有了MYSQL的账号密码，dump如下:
``` php  
Array
(
    [config] => Array
        (
            [db] => Array
                (
                    [master] => Array
                        (
                            [host] => localhost
                            [username] => root
                            [password] => root
                            [port] => 3306
                            [database] => application
                            [charset] => utf8
                            [pconnect] => 0
                            [tablepre] => ims_
                        )
                  )
         )
 )
 ```


- line 140:
``` php
setting_load();
```
可以发现执行 setting_load()之后 MYSQL配置项消失，因此我们进入该函数里面    
使用下面命令快速定位函数声明的文件

``` bash
 grep 'function setting_load' * -r
```

> framework/model/setting.mod.php:function setting_load($key = '') {

### framework/model/setting.mod.php
- line 29:

``` php  
function setting_load($key = '') {
    global $_W;
    $cachekey = cache_system_key('setting');
    $settings = cache_load($cachekey);
    if (empty($settings)) {
        $settings = pdo_getall('core_settings', array(), array(), 'key');
        if (is_array($settings)) {
            foreach ($settings as $k => &$v) {
                $settings[$k] = iunserializer($v['value']);
            }
        }
        cache_write($cachekey, $settings);
    }
    if (!is_array($_W['setting'])) {
        $_W['setting'] = array();
    }
    $_W['setting'] = array_merge($settings, $_W['setting']);
    if (!empty($key)) {
        return array($key => $settings[$key]);
    } else {
        return $settings;
    }
}
```

首先获取系统缓存的键名： `cache_system_key('setting') == "we7:setting"`  
接着加载键对应的缓存值： `$settings = cache_load($cachekey);`

这里监听 $_W 变量可以发现 cache_load 之后MYSQL配置信息就消失了，进入 cache_load 内部，传入参数为 "we7:setting"

### framework/function/cache.func.php

- line 34:

``` php
function cache_load($key, $unserialize = false) {
    global $_W;
    static $we7_cache;

    if (is_error($key)) {
        trigger_error($key['message'], E_USER_WARNING);
        return false;
    }
    if (!empty($we7_cache[$key])) {
        return $we7_cache[$key];
    }
    $data = $we7_cache[$key] = cache_read($key);
    if ($key == 'setting') {
        $_W['setting'] = $data;
        return $_W['setting'];
    } elseif ($key == 'modules') {
        $_W['modules'] = $data;
        return $_W['modules'];
    } elseif ($key == 'module_receive_enable' && empty($data)) {
                cache_build_module_subscribe_type();
        return cache_read($key);
    } else {
        return $unserialize ? iunserializer($data) : $data;
    }
}
```

内部里，简单判断$key是否合法后，如果是首次加载 $key 就会执行 cache_read ， 
而 $_W 也在cache_read后发生变化继续进入 cache_read 内部  


注意这里有四个文件定义了 cache_read
``` bash
grep 'function cache_read' * -r
``` 

> framework/function/cache.file.func.php:function cache_read($key, $dir = '', $include = true) {  
> framework/function/cache.memcache.func.php:function cache_read($key, $forcecache = true) {   
> framework/function/cache.redis.func.php:function cache_read($key) {   
> framework/function/cache.mysql.func.php:function cache_read($key) {   

观察文件名，其实就对应四种存储方式： file, memcache, redis, mysql 


### framework/function/cache.mysql.func.php  
- line 9   
``` php 
function cache_read($key) {
    global $_W;
    $cachedata = pdo_getcolumn('core_cache', array('key' => $key), 'value');
    if (empty($cachedata)) {
        return '';
    }
    $cachedata = iunserializer($cachedata);
    if (is_array($cachedata) && !empty($cachedata['expire']) && !empty($cachedata['data'])) {
        if ($cachedata['expire'] > TIMESTAMP) {
            return $cachedata['data'];
        } else {
            return '';
        }
    } else {
        return $cachedata;
    }
}
```

在 pdo_getcolumn() 函数之后 $_W变化，这里dump($cachedata) 可以看到是一串序列化的数据，也就是存在数据库里的配置信息
接着进入 pdo_getcolumn() 内部

### framework/function/pdo.func.php
- line 65:
``` php
function pdo_getcolumn($tablename, $condition = array(), $field) {
    return pdo()->getcolumn($tablename, $condition, $field);
}
```

这里只是简单的转移给pdo()对象处理，我们跟踪 pdo()对象，定义函数的位置在同一文件头部
- line 9:
``` php
function pdo() {
    global $_W;
    static $db;
    if(empty($db)) {
        if($_W['config']['db']['slave_status'] == true && !empty($_W['config']['db']['slave'])) {
            load()->classs('slave.db');
            $db = new SlaveDb('master');
        } else {
            load()->classs('db');
            if(empty($_W['config']['db']['master'])) {
                $_W['config']['db']['master'] = $GLOBALS['_W']['config']['db'];
                $db = new DB($_W['config']['db']);
            } else {
                $db = new DB('master');
            }
        }
    }
    return $db;
}
```

追了几十条街，终于看到答案了，pdo()函数在第一次调用的时候，会读取$_W里MYSQL配置信息
然后用配置信息创建一个 $db 的连接对象，$db 采用 static 声明，所以会执行期间会驻留内存里
创建好 $db 后会删除 $_W 里MYSQL配置信息，可能也是为了安全吧

