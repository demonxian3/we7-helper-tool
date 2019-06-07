# we7-tool-helper
微擎工具助手

### 如何使用? how to use?

1. 引入wdebug 方法 ( load wdebug function )

``` bash
    vim framework/bootstrap.inc.php
```

添加下面一句到 42 行   (Add the following sentence to 42 lines )

>  load()-\>func('wdebug');     

<br>
<br>
<br>
<br>

2. 存放进加载类 ( Put in the file )

> framework/class/wdebug.class.php   
> framework/function/wdebug.func.php  


3. 功能说明 
格式化打印变量          function dump($var)
打印最近SQL语句         function pdo\_lastsql()
打印所有SQL语句         function pdo\_showsql()
依据条件插入数据        function pdo\_setdata($tableName, $update, $condition)
生成Markdown头部        function showHead($filename)
生成Markdown尾部        function showFoot($result)
显示表格注释MD          function showComment($tableName, $highLightArr=[])
关键字显示表格注释MD    function showCommentByKey($keyword="")
显示GPC变量表格         function showGpcTable($arr)
显示数组变量表格        function showMdTable($arr)
生成测试数据            function makeTestData()
性能测试                function performanceTest($begin=false)


