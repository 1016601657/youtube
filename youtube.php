<?php
// 引入youtube类
include 'youtube.class.php';
include 'medoo.php';
$config = [
    'database_type' => 'mysql',
    'database_name' => 'youtube',
    'server' => '127.0.0.1',
    'username' => 'root',
    'password' => 'root',
    'charset' => 'utf8',
    'port' => 3306,
    'option' => [PDO::ATTR_CASE => PDO::CASE_NATURAL]
];
$db = new medoo($config);
$url = "https://www.youtube.com/channel/UCgC4Nn0rqqdeqACnzaIMo_Q/about";
//$url = "https://www.youtube.com/channel/UC7duzDwGoGU6S51Qyask0kA/about";
//$url = "https://www.youtube.com/channel/UCay_OLhWtf9iklq8zg_or0g/about";

index($db,$url);//封装方法,用来递归
function index($db,$url){
    $startTime = time();
// 初始化类
    $youtube = new youtube();

    $youtubeInfo = [];
// 获取所需字段信息
    $youtubeInfo['ytb_id'] = substr($url,32,24);
    $url = is_exist_channel($db, $youtube, $url, $youtubeInfo['ytb_id']);
    echo '开始抓取频道信息...';
    echo chr(10);
    $youtube->init($url);
    $youtubeInfo['username'] = $youtube->get_channel_name();
    $youtubeInfo['subscribers'] = $youtube->get_subscribers();
    $youtubeInfo['views'] = $youtube->get_views();
    $youtubeInfo['country'] = $youtube->get_country();
    $youtubeInfo['user_created'] = $youtube->get_join_time();
    $youtubeInfo['user_url'] = $youtube->url;
    $friendLink = $youtube->get_links();
    $youtubeInfo['user_twitter'] = $friendLink['twitter'];
    $youtubeInfo['user_instagram'] = $friendLink['instagram'];
    $youtubeInfo['user_facebook'] = $friendLink['facebook'];
    $youtubeInfo['created'] = time();
    $id = $db->insert("ytb_channels", $youtubeInfo);

    echo '频道信息插入成功 id:'.$id;
    echo chr(10);
// 获取视频信息
    $videourl = str_replace('about','videos',$url);
    $youtube->init($videourl);
    $videoLinks = $youtube->get_video_link();
    $videoDetail = [];
    $videotype = [];
    echo '开始插入视频...';
    echo chr(10);
    $i = 1;
    foreach($videoLinks as $k){
        $detail = [];
        $detailUrl = 'https://www.youtube.com'.$k;
        $youtube->init($detailUrl);
        $detail['channel_id'] = $id;
        $detail['title'] = $youtube->get_video_title();
        $detail['upload_time'] = $youtube->get_video_upload();
        $detail['view'] = $youtube->get_video_view();
        $detail['type'] = $youtube->get_video_type();
        $videotype[$detail['type']]++;
        $res = $db->insert("ytb_video", $detail);

        echo '第'.$i.'条视频插入成功!';
        echo chr(10);
        $i++;
    }
    arsort($videotype);
    reset($videotype);
    $first_key = key($videotype);
    $db->update("ytb_channels", ['type'=>$first_key],['id'=>$id]);

    /**获取下一个channel的信息--------------start-----------*/
    $recommendLinks = $youtube->get_recommend_links();
    foreach($recommendLinks as $v){
        index($db,$v);
    }
    /**获取下一个channel的信息--------------end-------------*/
    // 记录程序运行时间
    $endTime = time();
    $second = $endTime-$startTime;
    echo '运行完成';
    echo chr(10);
    echo '时间共计:'.date('i',$second).'分'.date('s',$second).'秒';
}
function is_exist_channel($db, $youtube, $url, $ytb_id){
    echo '检查此频道是否抓取过';
    echo chr(10);
    $ytbInfo = $db->select("ytb_channels", "*", ['ytb_id'=>$ytb_id]);
    // 如果该频道已经获取过则进入youtube首页 随机获取一条channel
    if($ytbInfo){
        echo '此频道已存在 正在从首页重新随机获取...';
        echo chr(10);
        $youtube->init('https://www.youtube.com');
        $newurl = $youtube->get_rand_channel();
        $new_ytb_id = substr($url,32,24);
        $newYtbInfo = $db->select("ytb_video", "*", ['ytb_id'=>$new_ytb_id]);
        if(!$newYtbInfo){
            unset($youtube);
            return $newurl;
        }else{
            is_exist_channel($db, $youtube, $newurl, $new_ytb_id);
        }
    }else{
        echo '此频道尚未抓取过';
        echo chr(10);
        unset($youtube);
        return $url;
    }
}