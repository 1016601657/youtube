<?php

/**
 * Created by PhpStorm.
 * User: LJS
 * Date: 2017/5/18
 * Time: 12:04
 * 抓取youtube数据 解析
 */
class youtube
{
    public $url;
    public $content;
    public $html;
    public $channel_info_html;


    /**
     * 初始化链接并且完成抓取数据
     * @param $url
     */
    public function init($url){
        $this->url = $url;
        $this->content = $this->proxy_curl($this->url);
    }

    /**
     * 获取频道名称
     * @param $html
     * @return string
     */
    public function get_channel_name(){
        preg_match('/<title>(.*?)<\/title>/is', $this->content, $matches);
        $res = explode('-',$this->unescape($matches[1]));
        return trim($res[0]);
    }
    /**
     * 获取频道订阅量
     * @param $html
     * @return string
     */
    public function get_subscribers(){
        preg_match_all('/<span class="about-stat">(.*?)<\/span>/is', $this->content, $this->channel_info_html);
        preg_match('/<b>(.*?)<\/b>/is', $this->channel_info_html[1][0], $mat);
        $sub = trim($this->unescape($mat[1]));
        return str_replace(',','',$sub);
    }
    /**
     * 获取频道浏览量
     * @param $html
     * @return string
     */
    public function get_views(){
        preg_match('/<b>(.*?)<\/b>/is', $this->channel_info_html[1][1], $mat);
        $view = trim($this->unescape($mat[1]));
        return str_replace(',','',$view);
    }
    /**
     * 获取注册日期
     * @param $html
     * @return string
     */
    public function get_join_time(){
        $str = trim($this->unescape($this->channel_info_html[1][2]));
        $time = str_replace('Joined','',$str);
        return strtotime($time);
    }
    /**
     * 获取外链
     * @param $html
     * @return array
     */
    public function get_links(){
        $links = [];
        preg_match_all('/<li class="channel-links-item">(.*?)<\/li>/is', $this->content, $matches);
        foreach($matches[1] as $key){
            preg_match_all("/<a .*?href=\"(.*?)\".*?>/is",$key,$hrefmatch);// 获取href
            preg_match_all("/<a .*?title=\"(.*?)\".*?>/is",$key,$titlematch);// 获取title
            if($titlematch[1][0] == 'Facebook'){
                $links['facebook'] = $hrefmatch[1][0];
            }
            if($titlematch[1][0] == 'Instagram'){
                $links['instagram'] = $hrefmatch[1][0];
            }
            if($titlematch[1][0] == 'Twitter'){
                $links['twitter'] = $hrefmatch[1][0];
            }
        }
        return $links;
    }
    /**
     * 获取国家
     * @param $html
     * @return string
     */
    public function get_country()
    {
        preg_match('/<span class="country-inline">(.*?)<\/span>/is', $this->content, $matches);
        return trim($this->unescape($matches[1]));
    }

    /**
     * 获取最多十个视频的链接
     * @return array
     */
    public function get_video_link(){
        preg_match_all('/<li class="channels-content-item yt-shelf-grid-item">(.*?)<\/li>/is', $this->content, $matches);
        $i = 0;
        $videoLinks = [];
        foreach($matches[1] as $key){
            if($i >= 10){
                break;
            }
            preg_match_all("/<a .*?href=\"(.*?)\".*?>/is",$key,$hrefmatch);// 获取href
            $videoLinks[] = $hrefmatch[1][0];
            $i++;
        }
        return $videoLinks;
    }

    /**
     * 获取视频的标题,上传时间,浏览次数,类型
     * @return string
     */
    public function get_video_title(){
        preg_match('/<h1 class="watch-title-container" >(.*?)<\/h1>/is', $this->content, $matches);
        preg_match("/<span .*?title=\"(.*?)\".*?>/is",$matches[1],$titlematch);// 获取href
        return trim($this->unescape($titlematch[1]));
    }
    public function get_video_upload(){
        preg_match('/<strong class="watch-time-text">(.*?)<\/strong>/is', $this->content, $matches);
        $str = trim($this->unescape($matches[1]));
        $time = str_replace('Published on ','',$str);
        return strtotime($time);
    }
    public function get_video_view(){
        preg_match('/<div class="watch-view-count">(.*?)<\/div>/is', $this->content, $matches);
        $view = trim($this->unescape($matches[1]));
        $hh =  explode(' ',$view);
        return str_replace(',','',$hh[0]);
    }
    public function get_video_type(){
        preg_match('/<ul class="content watch-info-tag-list">(.*?)<\/ul>/is', $this->content, $matches);
        preg_match('/<li>(.*?)<\/li>/is', $matches[1], $limatches);
        preg_match('/<a .*?>(.*?)<\/a>/is', $limatches[1], $typematches);
        return trim($this->unescape($typematches[1]));
    }

    /**
     * 获取channel/about页面右侧的channel推荐链接
     */
    public function get_recommend_links(){
        preg_match('/<ul class="branded-page-related-channels-list">(.*?)<\/ul>/is', $this->content, $matches);
        preg_match_all('/<li .*?>(.*?)<\/li>/is', $matches[1], $liMatches);
        $recommendLinks = [];
        foreach($liMatches[1] as $key){
            preg_match("/<a .*?href=\"(.*?)\".*?>/is",$key,$hrefMatch);// 获取href
            if(strpos($hrefMatch[1],'user') !== false){
                $channelAbout = $this->get_channel_from_user($hrefMatch[1]);
                if($channelAbout){
                    $recommendLinks[] = $channelAbout;
                }
            }
            if(strpos($hrefMatch[1],'channel') !== false){
                $recommendLinks[] = 'https://www.youtube.com'.$hrefMatch[1].'/about';
            }
        }
        return $recommendLinks;
    }

    /**
     * 根据user的链接 进如该user的channel列表获取第一个channel的链接
     * return string
     */
    public function get_channel_from_user($user){
        $url = 'https://www.youtube.com'.$user.'/channels';
        $channelContent = $this->proxy_curl($url);
        preg_match('/<ul class="yt-uix-shelfslider-list">(.*?)<\/ul>/is', $channelContent, $matches);
        preg_match('/<li .*?>(.*?)<\/li>/is', $matches[1], $liMatches);
        preg_match("/<a .*?href=\"(.*?)\".*?>/is",$liMatches[1],$hrefMatch);// 获取href
        unset($channelContent);
        $channelLink = trim($this->unescape($hrefMatch[1]));
        if(strpos($channelLink,'channel') !== false){
            return 'https://www.youtube.com'.$channelLink.'/about';
        }else{
            return false;
        }
    }
    public function get_rand_channel(){
        preg_match_all('/<div class="yt-lockup-byline yt-ui-ellipsis yt-ui-ellipsis-2">(.*?)<\/div>/is', $this->content, $matches);
        $key = rand(0,count($matches[1]));
        preg_match("/<a .*?href=\"(.*?)\".*?>/is",$matches[1][$key],$hrefMatch);// 获取href
        $recommendLink = '';
        if(strpos($hrefMatch[1],'user') !== false){
            $recommendLink = $this->get_channel_from_user($hrefMatch[1]);
        }
        if(strpos($hrefMatch[1],'channel') !== false){
            $recommendLink = 'https://www.youtube.com'.$hrefMatch[1].'/about';
        }
        if(!$recommendLink){
            $this->get_rand_channel();
        }else{
            return $recommendLink;
        }
    }
    /**
     * unicode 转 中文
     * @param  String $str unicode 字符串
     * @return String
     */
    public function unescape($str){
        $str = rawurldecode($str);
        preg_match_all("/(?:%u.{4})|&#x.{4};|&#\d+;|.+/U", $str, $r);
        $ar = $r[0];
        foreach ($ar as $k => $v) {
            if (substr($v, 0, 2) == "%u") {
                $ar[$k] = iconv("UCS-2BE", "UTF-8", pack("H4", substr($v, -4)));
            } elseif (substr($v, 0, 3) == "&#x") {
                $ar[$k] = iconv("UCS-2BE", "UTF-8", pack("H4", substr($v, 3, -1)));
            } elseif (substr($v, 0, 2) == "&#") {
                $ar[$k] = iconv("UCS-2BE", "UTF-8", pack("n", substr($v, 2, -1)));
            }
        }
        return join("", $ar);
    }
    /**
     * curl请求(设置proxy端口为ss端口号 以此来翻墙)
     * @param $requestUrl
     * @return string
     */
    public function proxy_curl($requestUrl)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $requestUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_PROXYAUTH, CURLAUTH_BASIC); //代理认证模式
        curl_setopt($ch, CURLOPT_PROXY, "127.0.0.1"); //代理服务器地址
        curl_setopt($ch, CURLOPT_PROXYPORT, 1087); //代理服务器端口
        // curl_setopt($ch, CURLOPT_PROXYUSERPWD, ":"); //http代理认证帐号，username:password的格式
        curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_HTTP); //使用http代理模式
        $file_contents = curl_exec($ch);
        curl_close($ch);
        return $file_contents;
    }
}