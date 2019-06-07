<?php

class Wdebug {
    public function __construct($connstr=""){

        #mysql:root@localhost/database:::password
        if($connstr){
            preg_match("/mysql:(.*?)@/", $connstr, $user);
            preg_match("/@(.*?)\//", $connstr, $host);
            preg_match("/\/(.*?):::/", $connstr, $base);
            preg_match("/:::(.*?$)/", $connstr, $pass);

            if ($user[1] && $host[1] && $pass[1] && $base[1]){
                $this->db = mysqli_connect($host[1], $user[1], $pass[1], $base[1]);

                if (mysqli_connect_errno($this->db))
                    echo "杩炴帴 MySQL 澶辫触:" . mysqli_connect_error();
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
                    array_push($insValArr, "'杩欐槸瀛楃娴嬭瘯鏁版嵁'");
                }else if($columnRow['DATA_TYPE'] === "int" || $columnRow['DATA_TYPE'] === "tinyint"){
                    array_push($insValArr, 2);
                }else if($columnRow['DATA_TYPE'] === "float"){
                    array_push($insValArr, 2.0);
                }else if($columnRow['DATA_TYPE'] === "text"){
                    array_push($insValArr, "'杩欐槸鏂囨湰娴嬭瘯鏁版嵁'");
                }else if($columnRow['DATA_TYPE'] === "varchar"){
                    array_push($insValArr, "'杩欐槸鍙彉闀垮瓧绗︿覆娴嬭瘯鏁版嵁'");
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



    //鍚堝苟OP涓嶨PC 绠楁硶
    private function MergeOpAndGpc($opObjList, $gpcObjList){
        if( !$opObjList || !$gpcObjList) return False;

        //op涓嶨PC鏁村悎
        array_unshift($opObjList,array('opName'=>'global', 'opPos'=>0));    //鎻掑叆鍏ㄥ眬鍏冪礌
        array_push($opObjList,array('opName'=>'ending', 'opPos'=>1000000)); //鎻掑叆杈呭姪鍏冪礌
        foreach($opObjList as $idx=>$opObj){
            if($idx === count($opObjList)-1) break;                         //鍊掓暟绗簩涓鐞嗗畬鍚庨€€鍑�;
            $opPosition = $opObjList[$idx+1]['opPos'];                      //鍙栦笅涓€涓厓绱犵殑op鍋忕Щ閲�;
            $tmpGpcList = array();                                          //瀛樻斁婊¤冻鏉′欢鐨刧pc
            foreach($gpcObjList as $gpcObj){
                $gpcPosition = $gpcObj['gpcPos'];
                if($gpcPosition < $opPosition){                             //濡傛灉鍋忕Щ閲廹pc < op鍔犲叆褰撳墠鍏冪礌鐨刲ist
                    array_push($tmpGpcList, $gpcObj['gpcName']);
                    array_shift($gpcObjList);
                    if(count($gpcObjList) === 0){                           //濡傛灉gpc鍙橀噺琚竻绌轰簡锛屽仛鏈€鍚庣殑鏁村悎
                        $tmpGpcList = array_unique($tmpGpcList);            //鏁扮粍鍘婚噸
                        $opObjList[$idx]['gpcList'] = $tmpGpcList;          //淇濆瓨鍙傛暟
                    }
                }else{
                    $tmpGpcList = array_unique($tmpGpcList);                //鏁扮粍鍘婚噸
                    $opObjList[$idx]['gpcList'] = $tmpGpcList;              //淇濆瓨鍙傛暟
                    break;
                }
            }
        }//foreach 
        array_pop($opObjList);                                              //绉婚櫎杈呭姪鍏冪礌

        return $opObjList;
    }


    //杩斿洖棰勫鍙傛暟
    private function PrepareUrlParam($paramArr){
        //鏋勯€犵郴缁熷弬鏁�, 鍏堜粠url璇诲彇鍊硷紝娌℃湁鍒欒祴浜堥粯璁ゅ€�
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


    //鑷姩鏂囨。鍏ュ彛鏂规硶
    public function AutoMarkdown($scriptName){
        if(!$scriptName){
            echo "AutoMarkdown need __FILE__";
        }

        //鑾峰彇URL锛屾埅鎴愪袱閮ㄥ垎
        $baseUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];  
        $partArr = $this->UrlParamSta($baseUrl);
        $curlUrl = $partArr[0];
        $curlPam = $partArr[1];

        
        //op鍜屾敞閲婁竴璧峰尮閰嶏紝鍥犱负鏃犳敞閲婄殑鏂规硶涓嶅尮閰�
        $curCode = file_get_contents($scriptName);
        preg_match_all($this->opRule3, $curCode, $opMatches, PREG_OFFSET_CAPTURE);
        preg_match_all($this->gpcRule1,$curCode, $gpcMatches, PREG_OFFSET_CAPTURE);

        //鑻ヤ竴涓兘鍖归厤涓嶅埌锛岄€€鍑�   
        if($gpcMatches === NULL || $opMatches === NULL)
            dump("[ERROR]: GPC or OP no match!!") + die();

        $gpcObjList = $opObjList = array();
        $opList  = $opMatches[2];               //鍖归厤op
        $comList = $opMatches[1];               //鍖归厤娉ㄩ噴
        $gpcList = $gpcMatches[1];              //鍖归厤gpc

        //鍚堝苟[comList, opList] 渚濊禆 preg_match_all 椤哄簭
        foreach($opList as $idx => $opItem) 
            array_push($opObjList, ['opName'=>$opItem[0], 'opPos'=>$opItem[1], 'opCom'=>$comList[$idx][0]]);
        foreach($gpcList as $gpcItem) 
            array_push($gpcObjList, ['gpcName'=>$gpcItem[0], 'gpcPos'=>$gpcItem[1]]);


        //鍚堝苟[opList, gpcList];
        $mergeObjList = $this->MergeOpAndGpc($opObjList, $gpcObjList);

        //棰勫璇锋眰鍙傛暟
        $urlParamArr = $this->PrepareUrlParam($curlPam);

        //閫氱敤鍙傛暟瀵煎叆璇锋眰鍙傛暟锛宱p鍚庨潰浼氳瑕嗙洊
        if(is_array($mergeObjList))
            foreach($mergeObjList[0]['gpcList'] as $key)
                $urlParamArr[$key] = 2;


        //绋嬪簭娴佷富鍏ュ彛
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

    //瀵硅薄 => URL
    public function UrlParamAts($arr){
        $str = "";
        foreach($arr as $k => $v)
            $str .=  "&$k=$v";
        return $str;
    }

    //URL => 瀵硅薄
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
        echo "**绠€瑕佹弿杩帮細**<br>";
        if($opName === "global")
            echo "- 閫氱敤鍙傛暟: <br><br>";
        else
            echo "- $opCom <br><br>";
        echo "**璇锋眰URL锛�**<br>";
        echo "`$opUrl`<br><br>";
        echo "**璇锋眰鏂瑰紡锛�**<br>";
        echo "- GET<br><br>";
        echo "<br>";
        echo "**鍙傛暟锛�**<br>";
        echo "<br><br>";
        $this->MkGpcTable($opList) ;
        echo "<br><br>";
        if($opName !== "global"){
            echo " **杩斿洖绀轰緥**<br>";
            #dump($this->HttpGet($opUrl));
        }
        echo "<br><br>**杩斿洖璇存槑**<br>";
        echo "</div>";
    }//func: MarkDownHead



    public function MkGpcTable($gpcList){
        if(!count($gpcList)) {
            echo "鏃犲弬鏁�<br>";
            return;
        }
    
        echo "|鍙傛暟鍚峾蹇呴€墊绫诲瀷|璇存槑|<br>";
        echo "| --- | --- | --- | --- |<br>";
        $filter = array('i','m','c','a','do','action','state');
    
        foreach($gpcList as $key => $v){
            if(preg_match("/(^__)/",$v))
                continue;
            if($key === "op") {
                echo "|op|鏄瘄string|$v|<br>";
            }
            else if($v === "uniacid")
                echo "|uniacid|鏄瘄int|绋嬪簭id|<br>";
    
            else if($v === "uid")
                echo "|uid|鏄瘄int|鐢ㄦ埛id|<br>";
                
            else if($v === "op") continue;    
            else if(!in_array($v, $filter)){
                $commentSql = "select COLUMN_COMMENT from information_schema.columns where COLUMN_NAME = '$v'";
                $commentRes = mysqli_query($this->db, $commentSql);
                $comment = "";
                while($commentRow = mysqli_fetch_assoc($commentRes)){
                    if(!empty($comment)) break;
                    $comment = $commentRow['COLUMN_COMMENT'];
                }
                echo "|$v|鏄瘄int|$commentRow[COLUMN_COMMENT]|<br>";
            }
        }
    }//func: MkGpcTable

    
    public function MdTable($array){
        $rnt = [];
        $keyList = [];
        $tableBanner = "|鍙傛暟鍚峾蹇呴€墊绫诲瀷|璇存槑|<br>";
        $tableBanner .= "| --- | --- | --- | --- |<br>";
        foreach($array as $v){
            $tableBanner .= "|$v|鏄瘄int| |<br>";
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
                //瀵逛簬缁撴瀯鏁扮粍鍙彇绗竴鏉℃暟鎹�,鍏朵粬鐨勮烦杩�
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
        echo '**杩斿洖璇存槑: **<br><br>';
        echo '|鍙傛暟|绫诲瀷|鎻忚堪|<br>';
        echo '|:-------|:-------|:-------|<br>';
        foreach($list as $arr){
            foreach($arr as $k => $v){
                echo "|$k|$v|";
                mysql_connect("localhost","root","root");
                $res = mysql_query("select COLUMN_COMMENT from information_schema.columns where COLUMN_NAME = '$k' and COLUMN_COMMENT != '' ");
                $row = mysql_fetch_assoc($res);
                if($row) echo $row['COLUMN_COMMENT'] ;
                else echo "鏃�";
                echo "|<br>";
            }
        }
    }

    public function AutoResult($result, $showAll=false){
        $keylist = Array();
        $keylist = $this->foreachArr($result, $keylist, $showAll);

        echo '**杩斿洖绀轰緥: **<br><br>';
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
    
    
            echo "|瀛楁鍚峾绫诲瀷|閿€紎鏄惁涓虹┖|鎵╁睍|娉ㄩ噴|<br>";
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
