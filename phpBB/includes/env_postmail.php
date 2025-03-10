<?php
// include_once($phpbb_root_path.'config.'.$phpEx);
#$phpbb_root_path = '/var/www/html/phpBB3/';
#$phpbb_root_path = '';
include_once($phpbb_root_path.'includes/env_config.'.$phpEx);

function env_postmail($sql_data, $attached){
    
    $max_size = 10485760;

    

//    mb_language("uni");
//    mb_internal_encoding("UTF-8");
    mb_language("uni");
    mb_internal_encoding("UTF-8");
    
    function out_log($obj, $i = 0) {
        $current_dir = './';
        if($i) {
            ob_start();
            var_dump($obj);
            $obj =ob_get_contents();
            ob_end_clean();
        }
        error_log(print_r($obj."\n", true),"3", "/var/www/html/phpBB3/includes/debug.log");
    }
    
    
    
    out_log("----開始--phpBB->ML-----------------------------");
    global $ev_ml_addr, $ev_dataroom, $ev_dr_name, $ev_ml_domain, $dbname, $dbuser, $dbpasswd;
    
    $pid = $sql_data["phpbb_topics"]["sql"]["topic_last_post_id"];  // post id
    $tid = $sql_data["phpbb_posts"]["sql"]["topic_id"];             // topic id
    $fid = $sql_data["phpbb_posts"]["sql"]["forum_id"];             // forum id
    $uid = $sql_data["phpbb_posts"]["sql"]["poster_id"];            // user id
    $subject = $sql_data["phpbb_posts"]["sql"]["post_subject"];
    $ptype = [];
    $flist = '';
    
    out_log("Subject:\n");
    out_log($sql_data["phpbb_posts"]["sql"]["post_subject"]);
    out_log("Message:\n");
    out_log($sql_data["phpbb_posts"]["sql"]["post_text"]);
    
    
    // 送信先アドレスあるとき送信元アドレス取得
    if(isset($ev_ml_addr[$fid])) {
        $ptype["isdr"] = false;
        $message = '';
        $sendto = $ev_ml_addr[$fid];
    } elseif (isset($ev_ml_addr[$ev_dataroom[$fid]])) {
        $ptype["isdr"] = true;
        $message = "";
        $sendto = $ev_ml_addr[$ev_dataroom[$fid]];
    } else {
        out_log("権限のない送信先、または送信エラー");
        return;
    }
        
    $message .= strip_tags($sql_data["phpbb_posts"]["sql"]["post_text"]);
    
    $dsn = 'mysql:host=localhost;dbname='.$dbname.';charset=utf8';

    try{
        $dbh = new PDO($dsn, $dbuser, $dbpasswd);

        // 静的プレースホルダを指定
        $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $stmt = $dbh->prepare('select user_email, user_sig from phpbb_users where user_id = ? limit 1');
        $stmt->setFetchMode(PDO::FETCH_ASSOC);

        $stmt->bindParam(1, $uid, PDO::PARAM_STR);
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $sendaddr = $row["user_email"];
            $sendname = strip_tags($row["user_sig"]);
        }

    } catch (PDOException $e){
        print('Error:'.$e->getMessage());
        die();
    }

    $dbh = null;
    
    // 送信元アドレスあるときNew TopicかReplyか判断
    if(isset($sendaddr) && strlen($sendaddr) > 5){
        // MESSAGE-ID生成
        $mid = hash('sha256', time().rand()).$ev_ml_domain;
        
        try{
            $dbh = new PDO($dsn, $dbuser, $dbpasswd);

            // 静的プレースホルダを指定
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $stmt = $dbh->prepare('insert into id_list (post_id, message_id) values (?, ?);');
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            $stmt->bindParam(1, $pid, PDO::PARAM_STR);
            $stmt->bindParam(2, $mid, PDO::PARAM_STR);
            $stmt->execute();

        } catch (PDOException $e){
            print('Error:'.$e->getMessage());
            die();
        }

        $dbh = null;



        // ひとつ前のリプライ取得
        try{
            $dbh = new PDO($dsn, $dbuser, $dbpasswd);

            // 静的プレースホルダを指定
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

            $stmt = $dbh->prepare('select post_id, poster_id, post_time, post_text, post_attachment from phpbb_posts where topic_id = ? order by post_id desc limit 2;');
            $stmt->setFetchMode(PDO::FETCH_ASSOC);

            $stmt->bindParam(1, $tid, PDO::PARAM_STR);
            $stmt->execute();

            while ($row = $stmt->fetch()) {
                $pfid[] = $row["post_id"];
                $repto[] = $row["poster_id"];
                $time[] = date('Y年m月d日 H:i', $row["post_time"]);
                $ptex[] = $row["post_text"];
            }
            
        } catch (PDOException $e){
            print('Error:'.$e->getMessage());
            die();
        }
        
        
        // New Topic判定
        if(isset($pfid[1]) && $pid == $pfid[1]) { //
            // New Topic
            $reply_mid = '';
            $ptype["isre"] = false;
        } else {
            // Reply
            $ptype["isre"] = true;
            try{
                $dbh = new PDO($dsn, $dbuser, $dbpasswd);

                // 静的プレースホルダを指定
                $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                $stmt = $dbh->prepare('select message_id from id_list where post_id = ? limit 1;');
                $stmt->setFetchMode(PDO::FETCH_ASSOC);

                $stmt->bindParam(1, $pfid[1], PDO::PARAM_STR);
                $stmt->execute();

                while ($row = $stmt->fetch()) {
                    $reply_mid = 'In-Reply-To: <'.$row["message_id"].">\n";
                }

            } catch (PDOException $e){
                print('Error:'.$e->getMessage());
                die();
            }


            try{
                $dbh = new PDO($dsn, $dbuser, $dbpasswd);

                // 静的プレースホルダを指定
                $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                $stmt = $dbh->prepare('select user_email, user_sig from phpbb_users where user_id = ? limit 1');
                $stmt->setFetchMode(PDO::FETCH_ASSOC);

                $stmt->bindParam(1, $repto[1], PDO::PARAM_STR);
                $stmt->execute();

                while ($row = $stmt->fetch()) {
                    $repfrom = strip_tags($row["user_sig"])." <".strip_tags($row['user_email']).">";
                }

            } catch (PDOException $e){
                print('Error:'.$e->getMessage());
                die();
            }

            $dbh = null;
            
            if(isset($ptex[1])) {
                $message .= "\n\n$time[1] $repfrom:\n".preg_replace('/^/um', '> ', strip_tags($ptex[1]));
            }
            
            $message = htmlspecialchars_decode($message);
        }
        
        
        // 添付判定
        if($attached == 1) {
            $boundary = "__BOUNDARY_".md5(rand())."__";
            $ctype = "Content-Type: multipart/mixed;boundary=\"{$boundary}\"\n";
            $body = "--{$boundary}\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\n";
            $body .= "Content-Transfer-Encoding: base64\n\n";
//            $body .= "Content-Type: text/plain; charset=\"ISO-2022-JP\"\n\n";
            $files = "--{$boundary}";
            try{
                $dbh = new PDO($dsn, $dbuser, $dbpasswd);

                // 静的プレースホルダを指定
                $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

                $stmt = $dbh->prepare('select physical_filename, real_filename, mimetype from phpbb_attachments where post_msg_id = ?;');
                $stmt->setFetchMode(PDO::FETCH_ASSOC);

                $stmt->bindParam(1, $pfid[0], PDO::PARAM_STR);
                $stmt->execute();
                
                $total = 0;

                
                while ($row = $stmt->fetch()) {
                    $phy = '/var/www/html/phpBB3/files/'.$row["physical_filename"];
                    $fnm = mb_encode_mimeheader($row["real_filename"]);
                    $mtype = $row["mimetype"];
                    if(!empty($phy)) {
                        $total += filesize($phy);
                        $handle = fopen($phy, 'r');
                        $fsize = filesize($phy);
                        $atfil = fread($handle, $fsize);
                        fclose($handle);
                        $files .= "\nContent-Type: {$mtype}; name=\"{$fnm}\"\n";
                        $files .= 'Content-Disposition: attachment; filename="'.$fnm."\"\n";
                        $files .= "Content-Transfer-Encoding: base64\n\n";
                        $files .= chunk_split(base64_encode($atfil))."\n";
                        $files .= "--{$boundary}";
                        $flist .= $row["real_filename"]." (".floor($fsize/1000)." KB)\n";
                        out_log("filename:");
                        out_log($fnm);
                        out_log("type:");
                        out_log($mtype);
                    }
                }
                
                out_log("size:");
                out_log($total);
                if ($ptype["isdr"]) {
                    $message = "会議室 ".$ev_dr_name[$fid]." へ以下の資料の登録がありました。\n（このメールには返信しないでください。）\n\n■資料名\n".$flist."\n■ダウンロードURL\n".(empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST']."/viewtopic.php?f=".$fid.'&t='.$tid."\n\n■登録メンバー名\n".$sendname."\n\n■登録日時\n".$time[0]."\n\n■資料概要\n".$subject."\n\n■登録メンバーからのメッセージ".$message;
                    $files = "--{$boundary}";
                } elseif ($total > $max_size) {
                    $message .= "\n\nこのメッセージには10MB以上の添付ファイルが設定されています。\nWebサイト上でご確認ください。";
                    $files = "--{$boundary}";
                }
                
                $body .= chunk_split(base64_encode($message))."\n\n";
                

            } catch (PDOException $e){
                print('Error:'.$e->getMessage());
                die();
            }
            
            $body .= $files."--";
            
        } else {
            if($ptype["isdr"] && $ptype["isre"]) {
                $message = "会議室 ".$ev_dr_name[$fid]." へ以下の資料へのコメントがありました。\n（このメールには返信しないでください。）\n\n■URL\n".(empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST']."/viewtopic.php?f=".$fid.'&t='.$tid."\n\n■登録メンバー名\n".$sendname."\n\n■登録日時\n".$time[1]."\n\n■資料概要\n".$subject."\n\n■登録メンバーからのメッセージ".$message;
            } elseif ($ptype["isdr"]) {
                $message = "会議室 ".$ev_dr_name[$fid]." の資料室で以下の投稿がありました\n（このメールには返信できません。資料を添付しないやりとりは会議室でお願いします。）\n\n■URL\n".(empty($_SERVER['HTTPS']) ? 'http://' : 'https://').$_SERVER['HTTP_HOST']."/viewtopic.php?f=".$fid.'&t='.$tid."\n\n■登録メンバー名\n".$sendname."\n\n■登録日時\n".$time[1]."\n\n■資料概要\n".$subject."\n\n■登録メンバーからのメッセージ".$message;                
            }
            $ctype = "Content-Type: text/plain; charset=\"UTF-8\"\nContent-Transfer-Encoding: BASE64\n";
            $body = base64_encode($message);
        }
        

        $header = "Mime-Version: 1.0\n";
        $header .= $ctype;
        $header .= "Return-Path: ".$sendaddr."\n";
        $header .= "Message-Id: <".$mid.">\n";
        $header .= $reply_mid;
        $header .= "From: ".mb_encode_mimeheader($sendname)." <$sendaddr>\n";
//        $header .= "From: ".$sendname." <$sendaddr>\n";
        $header .= "Sender: ".mb_encode_mimeheader($sendname)."\n";
//        $header .= "Sender: ".$sendname."\n";
//        $header .= "Organization: " . $sendaddr . " \n";
        $header .= "X-Sender: ".$sendaddr."\n";
        $header .= "X-Priority: 3\n";
        
        

        //file_put_contents('/tmp/dump.txt', $header);
        mail($sendto, mb_encode_mimeheader($subject), $body, $header);
        //out_log('Body:');
        //out_log($body);
        out_log("Sent!");
    }
}


// phpinfo();

?>
