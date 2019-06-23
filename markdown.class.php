<?php

class Markdown {
    public function __construct($connstr=""){

        #mysql:root@localhost/database/password
        if($connstr){
            preg_match("/mysql:(.*?)@/", $connstr, $user);
            preg_match("/@(.*?)\//", $connstr, $host);
            preg_match("/\/(.*?):::/", $connstr, $base);
            preg_match("/\/(.*?$)/", $connstr, $pass);

            if ($user[1] && $host[1] && $pass[1] && $base[1]){
                $this->db = mysqli_connect($host[1], $user[1], $pass[1], $base[1]);

                if (mysqli_connect_errno($this->db))
                    echo "连接 MySQL 失败:" . mysqli_connect_error();
            }
        }

        $this->gpcRuleb = "/\/\/(.*?)\\s*public function (.*?)\(/m";
        $this->opRule1 = "/^\/\/(.*?)\\s*if.*op==['\"](.*?)['\"]\)/m";
        $this->gpcRule1 = "/_GPC\[['\"](.*?)['\"]\]/";
        $this->gpcRule2 = "/request\[['\"](.*?)['\"]\]/";
        $this->opRule2 = "/\/\*\*(.*?)\*\/\s*public function (.*?)\(/m";
        $this->opRule3 = '/\/\*\*(.*?)\*\/\s*.*? function doPage(.*?)\(/m';
    }

    public function AutoInsertData(){
        $tableSql = "select distinct * from information_schema.tables 
        where table_schema = 'we7' and table_name like '%farm%' and table_schema = '$this->database'";
        $tableRes = mysqli_query($this->db,$tableSql) or die(mysqli_error($this->db));
        while($tableRow = mysqli_fetch_assoc($tableRes)){
            $tableName = $tableRow['TABLE_NAME'];
            $columnSql = "select distinct * from information_schema.columns 
            where table_name = '$tableName' and table_schema = '$this->database'";
            $columnRes = mysqli_query($this->db, $columnSql) or die(mysqli_error($this->db));
            $insertSql = "insert into $tableName";

            $insKeyArr = $insValArr = array();
            while($columnRow = mysqli_fetch_assoc($columnRes)){
                array_push($insKeyArr, $columnRow['COLUMN_NAME']);

                if(preg_match('/auto_increment/', $columnRow['EXTRA'])){
                    array_push($insValArr, 'NULL');
                }else if(isset($columnRow['COLUMN_DEFAULT'])){
                    array_push($insValArr, "'$columnRow[COLUMN_DEFAULT]'");
                }else if(preg_match('/id/', $columnRow['COLUMN_NAME'])){
                    array_push($insValArr, 2);
                }else if($columnRow['DATA_TYPE'] === "char"){
                    array_push($insValArr, "'这是字符测试数据'");
                }else if($columnRow['DATA_TYPE'] === "int" || $columnRow['DATA_TYPE'] === "tinyint"){
                    array_push($insValArr, 2);
                }else if($columnRow['DATA_TYPE'] === "float"){
                    array_push($insValArr, 2.0);
                }else if($columnRow['DATA_TYPE'] === "text"){
                    array_push($insValArr, "'这是文本测试数据'");
                }else if($columnRow['DATA_TYPE'] === "varchar"){
                    array_push($insValArr, "'这是可变长字符串测试数据'");
                }else{
                    dump($columnRow['DATA_TYPE']);exit;
                }
            }

            $keyStr = implode(',', $insKeyArr);
            $valStr = implode(',', $insValArr);
            $insSql = "$insertSql ($keyStr) values($valStr)";
            $insRes = mysqli_query($this->db, $insSql) or die(mysqli_error($this->db));
        }
    }//func: AutoInsertData



    //合并OP与GPC 算法
    private function MergeOpAndGpc($opObjList, $gpcObjList){
        if( !$opObjList || !$gpcObjList) return False;

        //op与GPC整合
        array_unshift($opObjList,array('opName'=>'global', 'opPos'=>0));    //插入全局元素
        array_push($opObjList,array('opName'=>'ending', 'opPos'=>1000000)); //插入辅助元素
        foreach($opObjList as $idx=>$opObj){
            if($idx === count($opObjList)-1) break;                         //倒数第二个处理完后退出;
            $opPosition = $opObjList[$idx+1]['opPos'];                      //取下一个元素的op偏移量;
            $tmpGpcList = array();                                          //存放满足条件的gpc
            foreach($gpcObjList as $gpcObj){
                $gpcPosition = $gpcObj['gpcPos'];
                if($gpcPosition < $opPosition){                             //如果偏移量gpc < op加入当前元素的list
                    array_push($tmpGpcList, $gpcObj['gpcName']);
                    array_shift($gpcObjList);
                    if(count($gpcObjList) === 0){                           //如果gpc变量被清空了，做最后的整合
                        $tmpGpcList = array_unique($tmpGpcList);            //数组去重
                        $opObjList[$idx]['gpcList'] = $tmpGpcList;          //保存参数
                    }
                }else{
                    $tmpGpcList = array_unique($tmpGpcList);                //数组去重
                    $opObjList[$idx]['gpcList'] = $tmpGpcList;              //保存参数
                    break;
                }
            }
        }//foreach 
        array_pop($opObjList);                                              //移除辅助元素

        return $opObjList;
    }


    //返回预备参数
    private function PrepareUrlParam($paramArr){
        //构造系统参数, 先从url读取值，没有则赋予默认值
        $urlParamArr = array();

        $urlParamArr['page']   = 0;
        $urlParamArr['i']      = $paramArr['i']      ? $paramArr['i']     :2;
        $urlParamArr['m']      = $paramArr['m']      ? $paramArr['m']     :'xingyu_farm';
        $urlParamArr['a']      = $paramArr['a']      ? $paramArr['a']     :'wxapp';
        $urlParamArr['c']      = $paramArr['c']      ? $paramArr['c']     :'entry';
        $urlParamArr['do']     = $paramArr['do']     ? $paramArr['do']    :'coupon';
        $urlParamArr['action'] = $paramArr['action'] ? $paramArr['action']:'pt';

        return $urlParamArr;
    }


    //自动文档入口方法
    public function AutoMarkdown($scriptName){
        if(!$scriptName){
            echo "AutoMarkdown need __FILE__";
        }

        //获取URL，截成两部分
        $baseUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];  
        $partArr = $this->UrlParamSta($baseUrl);
        $curlUrl = $partArr[0];
        $curlPam = $partArr[1];

        
        //op和注释一起匹配，因为无注释的方法不匹配
        $curCode = file_get_contents($scriptName);
        preg_match_all($this->opRule3, $curCode, $opMatches, PREG_OFFSET_CAPTURE);
        preg_match_all($this->gpcRule1,$curCode, $gpcMatches, PREG_OFFSET_CAPTURE);

        //若一个都匹配不到，退出   
        if($gpcMatches === NULL || $opMatches === NULL)
            dump("[ERROR]: GPC or OP no match!!") + die();

        $gpcObjList = $opObjList = array();
        $opList  = $opMatches[2];               //匹配op
        $comList = $opMatches[1];               //匹配注释
        $gpcList = $gpcMatches[1];              //匹配gpc

        //合并[comList, opList] 依赖 preg_match_all 顺序
        foreach($opList as $idx => $opItem) 
            array_push($opObjList, ['opName'=>$opItem[0], 'opPos'=>$opItem[1], 'opCom'=>$comList[$idx][0]]);
        foreach($gpcList as $gpcItem) 
            array_push($gpcObjList, ['gpcName'=>$gpcItem[0], 'gpcPos'=>$gpcItem[1]]);


        //合并[opList, gpcList];
        $mergeObjList = $this->MergeOpAndGpc($opObjList, $gpcObjList);

        //预备请求参数
        $urlParamArr = $this->PrepareUrlParam($curlPam);

        //通用参数导入请求参数，op后面会被覆盖
        if(is_array($mergeObjList))
            foreach($mergeObjList[0]['gpcList'] as $key)
                $urlParamArr[$key] = 2;


        //程序流主入口
        $this->main($mergeObjList, $curlUrl, $urlParamArr);
    }//func: AutoMarkdown



    private function main($mergeObjList, $curlUrl, $urlParamArr){
        if(is_array($mergeObjList))
        foreach($mergeObjList as $k => $obj){
            $tmpParamArr = $urlParamArr;
            if($obj['opName'] === 'markdown')
            continue;
            
            $tmpParamArr['op'] = $obj['opName'];
            $tmpParamArr['uniacid'] = 2;
            $tmpParamArr['uid'] = 2;
            foreach($obj["gpcList"] as $gpc){
                if($gpc === "page") $tmpParamArr["page"]=0;
                else $tmpParamArr[$gpc] = 2;
            }

            $obj['gpcList']['op'] = $obj['opName'];
            $opUrl = $curlUrl . "?" . $this->UrlParamAts($tmpParamArr);
            $this->MarkDownHead($obj['opName'], $obj['opCom'], $opUrl, $obj['gpcList']);

        }
    }

    //对象 => URL
    public function UrlParamAts($arr){
        $str = "";
        foreach($arr as $k => $v)
            $str .=  "&$k=$v";
        return $str;
    }

    //URL => 对象
    public function UrlParamSta($url){
        $urlParts = explode("?", $url);
        $url = $urlParts[0];
        $uri = $urlParts[1];

        $paramArr = [];
        $uriArr = explode("&", $uri);

        foreach($uriArr as $paramStr){
            $tmpArr = explode("=", $paramStr);
            $key = $tmpArr[0];
            $paramArr[$key] = $tmpArr[1];
        }

        return [$url, $paramArr];
    }

    private function HttpGet($url){
        $con = curl_init((string)$url);
        curl_setopt($con, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($con, CURLOPT_TIMEOUT, 3);
        return curl_exec($con);
    }//func: HttpGet


    private function MarkDownHead($opName, $opCom, $opUrl,$opList){

        echo "<div style=\"border:1px solid #888;padding:20px;background:#eee\">";
        echo "**简要描述：**<br>";
        if($opName === "global")
            echo "- 通用参数: <br><br>";
        else
            echo "- $opCom <br><br>";
        echo "**请求URL：**<br>";
        echo "`$opUrl`<br><br>";
        echo "**请求方式：**<br>";
        echo "- GET<br><br>";
        echo "<br>";
        echo "**参数：**<br>";
        echo "<br><br>";
        $this->MkGpcTable($opList) ;
        echo "<br><br>";
        if($opName !== "global"){
            echo " **返回示例**<br>";
            #dump($this->HttpGet($opUrl));
        }
        echo "<br><br>**返回说明**<br>";
        echo "</div>";
    }//func: MarkDownHead



    public function MkGpcTable($gpcList){
        if(!count($gpcList)) {
            echo "无参数<br>";
            return;
        }
    
        echo "|参数名|必选|类型|说明|<br>";
        echo "| --- | --- | --- | --- |<br>";
        $filter = array('i','m','c','a','do','action','state');
    
        foreach($gpcList as $key => $v){
            if(preg_match("/(^__)/",$v))
                continue;
            if($key === "op") {
                echo "|op|是|string|$v|<br>";
            }
            else if($v === "uniacid")
                echo "|uniacid|是|int|程序id|<br>";
    
            else if($v === "uid")
                echo "|uid|是|int|用户id|<br>";
                
            else if($v === "op") continue;    
            else if(!in_array($v, $filter)){
                $commentSql = "select COLUMN_COMMENT from information_schema.columns where COLUMN_NAME = '$v'";
                $commentRes = mysqli_query($this->db, $commentSql);
                $comment = "";
                while($commentRow = mysqli_fetch_assoc($commentRes)){
                    if(!empty($comment)) break;
                    $comment = $commentRow['COLUMN_COMMENT'];
                }
                echo "|$v|是|int|$commentRow[COLUMN_COMMENT]|<br>";
            }
        }
    }//func: MkGpcTable

    
    public function MdTable($array){
        $rnt = [];
        $keyList = [];
        $tableBanner = "|参数名|必选|类型|说明|<br>";
        $tableBanner .= "| --- | --- | --- | --- |<br>";
        foreach($array as $v){
            $tableBanner .= "|$v|是|int| |<br>";
        }
        echo $tableBanner;
    }//func


    public function DecodeUnicode($str){
        return preg_replace_callback('/\\\\u([0-9a-f]{4})/i', 
            create_function('$matches', 
            'return iconv("UCS-2BE","UTF-8",pack("H*", $matches[1]));'), $str);
    }



    public function foreachArr($arr, $keylist, $showAll=false){
        foreach($arr as $k => $v){
            if(!isset($keylist[$k]) || gettype($k) === "integer"){  
                //对于结构数组只取第一条数据,其他的跳过
                if(!$showAll){
                    if(gettype($k) === "integer" && !empty($k)) 
                    continue;
                }
                $type = gettype($v);
                if($type === "array" ){
                    if(gettype($k)!== "integer"){
                        array_push($keylist, array($k => "object"));
                    }
                    $keylist = $this->foreachArr($v, $keylist, $showAll);
                }else{
                    if(gettype($k)!== "integer")
                        array_push($keylist, array($k => $type));
                }
            }
        }
        return $keylist;
    }

    public function MarkDownFoot($list){
        echo '**返回说明: **<br><br>';
        echo '|参数|类型|描述|<br>';
        echo '|:-------|:-------|:-------|<br>';
        foreach($list as $arr){
            foreach($arr as $k => $v){
                echo "|$k|$v|";
                mysql_connect("localhost","root","root");
                $res = mysql_query("select COLUMN_COMMENT from information_schema.columns where COLUMN_NAME = '$k' and COLUMN_COMMENT != '' ");
                $row = mysql_fetch_assoc($res);
                if($row) echo $row['COLUMN_COMMENT'] ;
                else echo "无";
                echo "|<br>";
            }
        }
    }

    public function AutoResult($result, $showAll=false){
        $keylist = Array();
        $keylist = $this->foreachArr($result, $keylist, $showAll);

        echo '**返回示例: **<br><br>';
        $result = json_encode($result, JSON_PRETTY_PRINT);
        $result = $this->DecodeUnicode($result);
        echo "<pre>``` JSON\n $result\n ```</pre>";

        #$this->MarkDownFoot($keylist);
    }

    
    
    public function showCommentByKeyword($keyword){
        mysql_connect("localhost","root","root");
        $res = mysql_query("select table_name from information_schema.tables where table_schema = 'we7' and table_name like '%$keyword%' and table_name not like '%ecstyle%' and table_name like '%cqxingyu%'") or die(mysql_error());
    
        while($row = mysql_fetch_assoc($res)){
            if($row['table_name'])
                showComment($row['table_name']);
        }
        
    }
    
    public function showComment($tableName, $highLightArr){
        if(!preg_match('/(ims_)/',$tableName))
            $tableName = "ims_" . $tableName;
    
        mysql_connect("localhost","root","root");
        $res = mysql_query("select * from information_schema.columns where table_name = '$tableName'") or die(mysql_error());
    
        echo '<div style="background:#ccc;padding:20px;border:1px solid gray;margin:10px">';
            echo "<br><span style='color:blue'>** $tableName **</span><br><br>";
    
    
            echo "|字段名|类型|键值|是否为空|扩展|注释|<br>";
            echo "| ---- | -- | -- | ------ | -- | -- | -- |<br>";
            while($row = mysql_fetch_assoc($res)){
                if($highLightArr && in_array($row[column_name], $highLightArr)) {
                echo "<span style='color:red'>";
                echo "|$row[COLUMN_NAME]|$row[COLUMN_TYPE]|$row[COLUMN_KEY]|$row[IS_NULLABLE]|$row[EXTRA]|$row[COLUMN_COMMENT]|</SPAN><BR>";
                }else{
                echo "|$row[COLUMN_NAME]|$row[COLUMN_TYPE]|$row[COLUMN_KEY]|$row[IS_NULLABLE]|$row[EXTRA]|$row[COLUMN_COMMENT]|<BR>";
                }
    
            }
        echo '</div>';
        
    }//func

} //class
?>
