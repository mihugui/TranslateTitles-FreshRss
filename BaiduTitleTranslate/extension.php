<?php

class TranslateTitlesExtension extends Minz_Extension{
    // 默认配置
    private const ApiUrl = 'https://fanyi-api.baidu.com/api/trans/vip/translate';


    public function init(){
        // 注册钩子
        $this->registerHook('feed_before_insert', array($this, 'addTranslationOption'));
        $this->registerHook('entry_before_insert', array($this, 'translateTitle'));
    }

    public function handleConfigureAction() {
        if (Minz_Request::isPost()) {

            // 保存需要翻译的 feed id
            $translateTitles = Minz_Request::param('TranslateTitles', array());
            FreshRSS_Context::$user_conf->TranslateTitles = $translateTitles;

            // 保存 百度 api 地址 key secret
            $baiduApiUrl = Minz_Request::param('BaiduApiUrl', self::ApiUrl);
            $baiduApiKey = Minz_Request::param('BaiduApiKey', '');
            $baiduApiSecret = Minz_Request::param('BaiduApiSecret', '');
            FreshRSS_Context::$user_conf->baiduApiUrl = $baiduApiUrl;
            FreshRSS_Context::$user_conf->baiduApiKey = $baiduApiKey;
            FreshRSS_Context::$user_conf->baiduApiSecret = $baiduApiSecret;


            FreshRSS_Context::$user_conf->save();
        }
    }


    public function handleUninstallAction() {
        // 清除所有与插件相关的用户配置
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles)) {
            unset(FreshRSS_Context::$user_conf->TranslateTitles);
        }
        if (isset(FreshRSS_Context::$user_conf->baiduApiUrl)) {
            unset(FreshRSS_Context::$user_conf->baiduApiUrl);
        }
        if (isset(FreshRSS_Context::$user_conf->baiduApiKey)) {
            unset(FreshRSS_Context::$user_conf->baiduApiKey);
        }
        if (isset(FreshRSS_Context::$user_conf->baiduApiSecret)) {
            unset(FreshRSS_Context::$user_conf->baiduApiSecret);
        }

        FreshRSS_Context::$user_conf->save();
    }


    public function translateTitle($entry) {
        // 检查是否启用了翻译
        $feedId = $entry->feed()->id();
        if (isset(FreshRSS_Context::$user_conf->TranslateTitles[$feedId]) && FreshRSS_Context::$user_conf->TranslateTitles[$feedId] == '1') {
            $title = $entry->title();
            $baiduApiUrl = FreshRSS_Context::$user_conf->baiduApiUrl;
            $baiduApiKey = FreshRSS_Context::$user_conf->baiduApiKey;
            $baiduApiSecret = FreshRSS_Context::$user_conf->baiduApiSecret;

            // 翻译标题
            $salt = rand(10000, 99999);
            $sign = md5($baiduApiKey . $title . $salt . $baiduApiSecret);
            $url = $baiduApiUrl . '?q=' . urlencode($title) . '&from=auto&to=zh&appid=' . $baiduApiKey . '&salt=' . $salt . '&sign=' . $sign;
            $result = file_get_contents($url);
            $result = json_decode($result, true);

            // 解析百度翻译结果
            if (isset($result['trans_result'][0]['dst'])) {
                $result = $result['trans_result'][0]['dst'];
            } else {
                $result = '';
            }

            // 更新标题
            if (!empty($result)) {
                $entry->_title($result . ' - ' . $title); // 将翻译后的标题放在前，原文标题放在后
            } else {
                $entry->_title($title);
            }
        }
        return $entry;
    }




    public function addTranslationOption($feed) {
        $feed->TranslateTitles = '0';
        return $feed;
    }

}