<?php
include_once("config.php");

$sid = $_COOKIE["phpbb3_6sg8y_sid"];
$uid = $_COOKIE["phpbb3_6sg8y_u"];
$domain = $_SERVER['HTTP_HOST'].";
$dir = "";

$dsn = 'mysql:host=localhost;dbname='.$dbname.';charset=utf8';

$room_id = $_GET['room_id'];
$uri = "https://old".$domain."/user/vst_roomtop.php?room_id=";

try{
    $dbh = new PDO($dsn, $dbuser, $dbpasswd);
    
    // 静的プレースホルダを指定
    $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
 
    $stmt = $dbh->prepare('select session_ip from phpbb_sessions where session_id = ? and session_user_id = ? limit 1');
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    
    $stmt->bindParam(1, $sid, PDO::PARAM_STR);
    $stmt->bindParam(2, $uid, PDO::PARAM_INT);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $sip = $row["session_ip"];
    }
    
    $stmt = $dbh->prepare('select username from phpbb_users where user_id = ? limit 1');
    $stmt->setFetchMode(PDO::FETCH_ASSOC);
    
    $stmt->bindParam(1, $uid, PDO::PARAM_STR);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $uname = $row["username"];
    }
    
}catch (PDOException $e){
    print('Error:'.$e->getMessage());
    die();
}

$dbh = null;

if($sip !== $_SERVER["REMOTE_ADDR"]) {
    echo "COOKIEとRefererが使用できる状態にして再度アクセスしてください。";
} elseif(!isset($room_id)) {
    echo "アクセス先が指定されていません。このリクエストは実行されません。";
} elseif($uid == 2) {
    $upw = "ev2019admin";
    $uname = "admin";
} elseif(isset($uname)) {
    
    $i = 2;
    $dsn = 'pgsql:dbname=fml;host=10.0.0.5;port=5432';
    $olddbu = 'kushiro_info';
    $olddbp = '';
    $sql = 'select user_passwd from system_user where user_id = ? limit 1';
    while($i > 0) {
        ## oldDB探索

        try{
            $dbh = new PDO($dsn, $olddbu, $olddbp);

            // 静的プレースホルダを指定
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $stmt = $dbh->prepare($sql);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            $stmt->bindParam(1, $uname, PDO::PARAM_STR);
            $stmt->execute();


            while ($row = $stmt->fetch()) {
                $upw = $row["user_passwd"];
            }
            
            if(isset($upw)) {
                $i = 0;
            } else {
                $uname = mb_strimwidth( "cmn_".$room_id, 0, 16);
                $i--;
            }


        }catch (PDOException $e){
            print('Error:'.$e->getMessage());
            die();
        }

        $dbh = null;
    }
    
} else {
    echo "セッション情報が不正です。このリクエストは実行されません。";
}


if(!isset($upw)) {
    // noaccount!
    echo '旧システムに対応するアカウントがありません。2019年以前には存在しない会議室であった可能性があります。';
} else {
    $uri = $uri.$room_id;
    setcookie('passwd', $upw, 0, $dir, $domain);
    setcookie('user_id', $uname, 0, $dir, $domain);
    echo $uri.'<br>'.$_SERVER['HTTP_REFERER'];
    header("Location:$uri");
}
