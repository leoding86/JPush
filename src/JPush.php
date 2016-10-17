<?php
namespace leoding86\JPush;

/**
 * 极光推送REST接口方法，php5.2
 */
class JPush
{
    private $appKey = '';
    private $masterSecret = '';
    private $base64AuthString;
    private $arguments = array();
    private $response;
    private $error;
    private $debug = false;
    private $API_PUSH = 'https://api.jpush.cn/v3/push';
    private $API_VALIDATE = 'https://api.jpush.cn/v3/push/validate';
    public $ALL = 'all';
    public $ANDROID = 'android';
    public $IOS = 'ios';
    public $WINPHONE = 'winphone';
    public $TAG = 'tag';
    public $TAG_AND = 'tag_and';
    public $ALIAS = 'alias';
    public $REGISTRATION_ID = 'registration_id';

    public function __construct($app_key = '', $master_secret = '')
    {
        $this->setAppKey($app_key);
        $this->setMasterSecret($master_secret);
        $this->base64AuthString = base64_encode($this->appKey.':'.$this->masterSecret);
        $this->validOpitons = array('sendno', 'time_to_live', 'override_msg_id', 'apns_production', 'big_push_duration');
    }

    public function setAppKey($app_key) {
        $this->appKey = $app_key;
    }

    public function setMasterSecret($master_secret) {
        $this->masterSecret = $master_secret;
    }

    public function renewBase64AuthString($app_key, $master_secret) {
        $this->base64AuthString = base64_encode($app_key.':'.$master_secret);
        $this->clearArguments();
    }

    public function clearArguments() {
        $this->arguments = array();
    }

    public function _error()
    {
        return $this->error;
    }

    public function _debug($debug)
    {
        $this->debug = $debug;
    }

    /**
     * 设置接口返回错误
     */
    private function APIError($code = 0)
    {
        $error_string = '';
        switch ($code) {
            case '1000':
                $error_string = '系统内部错误';
                break;
            case '1001':
                $error_string = '只支持 HTTP Post 方法';
                break;
            case '1002':
                $error_string = '缺少了必须的参数';
                break;
            case '1003':
                $error_string = '参数值不合法';
                break;
            case '1004':
                $error_string = '验证失败';
                break;
            case '1005':
                $error_string = '消息体太大';
                break;
            case '1008':
                $error_string = 'app_key参数非法';
                break;
            case '1011':
                $error_string = '没有满足条件的推送目标';
                break;
            case '1020':
                $error_string = '只支持 HTTPS 请求';
                break;
            case '1030':
                $error_string = '内部服务超时';
                break;
            default:
                $error_string = '未知的接口错误';
                break;
        }
        return $error_string;
    }

    /**
     * 组建设置
     * @param string $key
     * @param mixed $value
     * @return void
     */
    private function buildArguments($key, $value)
    {
        $keys = explode('.', $key);
        if (count($keys) === 1)
        {
            $this->arguments[$key] = $value;
        }
        else
        {
            $this->arguments[$keys[0]][$keys[1]] = $value;
        }
    }

    /**
     * 分析设置
     */
    private function parseArguments()
    {
        if (empty($this->arguments))
        {
            $this->error = 'No arguments has been set';
            return false;
        }
        if (!isset($this->arguments['platform']))
        {
            $this->error = 'No platform has been set';
            return false;
        }
        if (!isset($this->arguments['notification']['alert']))
        {
            $this->error = 'No alert has been set';
            return false;
        }
        if (isset($this->arguments['notification']['ios']))
        {
            $ios = json_encode(isset($this->arguments['notification']['ios']));
            if (strlen($ios) > 2000)
            {
                $this->error = 'IOS alert is to long';
                return false;  
            }
        }
        else
        {
            if (strlen($this->arguments['notification']['alert']) > 2000)
            {
                $this->error = 'Alert is to long for iOS';
                return false;  
            }
        }
        if (!isset($this->arguments['audience']) || empty($this->arguments['audience']))
        {
            $this->buildArguments('audience', 'all');
        }
        return true;
    }

    /**
     * 获得参数配置
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * 设置推送平台
     * @param string $platform
     * @return void
     */
    public function setPlatform($platform)
    {
        $arguments = func_get_args();
        if (count($arguments) > 0)
        {
            if ($arguments[0] === $this->ALL)
            {
                $this->buildArguments('platform', 'all');
                return;
            }
            $platform = array();
            foreach ($arguments as $argument)
            {
                if ($argument === $this->ANDROID ||
                    $argument === $this->IOS ||
                    $argument === $this->WINPHONE) {
                    array_push($platform, $argument);
                }
            }
            if (count($platform) > 0)
            {
                if (isset($this->arguments['platform']))
                {
                    $platform = array_merge($this->arguments['platform'], $platform);
                }
                $this->buildArguments('platform', $platform);
                return;
            }
        }
        throw new Exception("setPlatform empty", 1);
    }

    /**
     * 设置推动内容
     * @param string $alert 通知通知
     * @param string $title 通知标题
     */
    public function setNotification($alert, $title = '')
    {
        $notification = array();
        $notification['alert'] = $alert;
        $this->buildArguments('notification', $notification);
        if ((string)$title !== '')
        {
            if ($this->arguments['platform'] === $this->ALL)
            {
                $this->setAndroid($alert, $title);
                $this->setWinPhone($alert, $title);
            }
            else
            {
                foreach ($this->arguments['platform'] as $platform)
                {
                    if ($platform === $this->ANDROID)
                    {
                        $this->setAndroid($alert, $title);
                    }
                    else if ($platform === $this->WINPHONE)
                    {
                        $this->setWinPhone($alert, $title);
                    }
                }
            }
        }
    }

    /**
     * 设置应用内消息
     * @param string $msg_content
     * @param string $title
     * @param string $content_type
     * @param array $extras
     */
    public function setMessage($msg_content, $title = '', $content_type = '', $extras = array())
    {
        $message = array();
        $message['msg_content'] = $msg_content;
        if ((string)$title !== '')
        {
            $message['title'] = $title;
        }
        if ((string)$content_type !== '')
        {
            $message['content_type'] = $content_type;
        }
        if (is_array($extras) && !empty($extras))
        {
            $message['extras'] = $extras;
        }
        $this->buildArguments('message', $message);
    }

    /**
     * 设置推送对象
     * @param string $type
     * @param array $values
     */
    public function setAudience($type, $values)
    {
        if (isset($this->arguments['audience']) && $this->arguments['audience'] === 'all')
        {
            return;
        }
        if ((string)$type === 'all')
        {
            $audience = 'all';
            $this->buildArguments('audience', $audience);
            return;
        }
        $audience = array();
        if ($type === $this->TAG ||
            $type === $this->TAG_AND ||
            $type === $this->ALIAS ||
            $type === $this->REGISTRATION_ID) {
            if (!isset($this->arguments['audience']))
            {
                $this->buildArguments('audience', array());
            }
            if (!isset($this->arguments['audience'][$type]))
            {
                $this->buildArguments('audience.'.$type, array());
            }
            $audience = array_merge($this->arguments['audience'][$type], $values);
            $this->buildArguments('audience.'.$type, $audience);
        }
    }

    /**
     * 设置android平台参数
     * @param string $alert
     * @param string $title
     * @param int $builder_id
     * @param array $extras
     */
    public function setAndroid($alert = '', $title = '', $builder_id = 1, $extras = array())
    {
        $android = array();
        if ((string)$alert !== '')
        {
            $android['alert'] = $alert;
        }
        else if (isset($this->arguments['notification']['alert']))
        {
            $android['alert'] = $this->arguments['notification']['alert'];
        }
        else
        {
            throw new Exception("Has no alert", 1);  
        }
        if ((string)$title !== '')
        {
            $android['title'] = $title;
        }
        $android['builder_id'] = (int)$builder_id;
        if (is_array($extras) && !empty($extras))
        {
            $android['extras'] = $extras;
        }
        $this->buildArguments('notification.android', $android);
    }

    /**
     * 设置ios平台参数
     * @param string $alert
     * @param string $sound
     * @param string $badge
     * @param boolean $content_available
     * @param string $category
     * @param array $extras
     */
    public function setIOS($alert = '', $sound = 'sound.caf', $badge = '+1', $content_available = true, $category = '', $extras = array())
    {
        $ios = array();
        if ((string)$alert !== '')
        {
            $ios['alert'] = $alert;
        }
        else if (isset($this->arguments['notification']['alert']))
        {
            $ios['alert'] = $this->arguments['notification']['alert'];
        }
        else
        {
            throw new Exception("Has no alert", 1);  
        }
        $ios['sound'] = $sound;
        $ios['badge'] = $badge;
        $ios['content-available'] = $content_available;
        if ((string)$category !== '')
        {
            $ios['category'] = $category;
        }
        if (is_array($extras) && !empty($extras))
        {
            $ios['extras'] = $extras;
        }
        $this->buildArguments('notification.ios', $ios);
    }

    /**
     * 设置winphone平台参数
     * @param string $alert
     * @param string $title
     * @param string $_open_page
     * @param array $extras
     */
    public function setWinPhone($alert = '', $title = '', $_open_page = '', $extras = array())
    {
        $winphone = array();
        if ((string)$alert !== '')
        {
            $winphone['alert'] = $alert;
        }
        else if (isset($this->arguments['notification']['alert']))
        {
            $winphone['alert'] = $this->arguments['notification']['alert'];
        }
        else
        {
            throw new Exception("Has no alert", 1);  
        }
        if ((string)$title !== '')
        {
            $winphone['title'] = $title;
        }
        if ((string)$_open_page !== '')
        {
            $winphone['_open_page'] = $_open_page;
        }
        if (is_array($extras) && !empty($extras))
        {
            $winphone['extras'] = $extras;
        }
        $this->buildArguments('notification.winphone', $winphone);
    }

    /**
     * 设置其他参数
     * @param int $sendno 推送序号
     * @param int $time_to_live 离线消息保留时长(秒)
     * @param long $override_msg_id 要覆盖的消息ID
     * @param boolean $apns_production APNs是否生产环境
     * @param int $big_push_duration 定速推送时长(分钟)
     */
    public function setOptions($sendno = null, $time_to_live = null, $override_msg_id = null, $apns_production = true, $big_push_duration = null)
    {
        $options = array();
        if ($sendno)
        {
            $options['sendno'] = (int)$sendno;
        }
        if ($time_to_live)
        {
            $time_to_live = (int)$time_to_live;
            if ($time_to_live > 0 && $time_to_live <= 86400*10)
            {
                $options['time_to_live'] = (int)$time_to_live;
            }
        }
        if ((string)$override_msg_id)
        {
            $options['override_msg_id'] = (string)$override_msg_id;
        }
        $options['apns_production'] = (bool)$apns_production ? true : false;
        if ($big_push_duration)
        {
            $big_push_duration = (int)$big_push_duration;
            if ($big_push_duration > 0 && $big_push_duration <= 1400)
            {
                $options['big_push_duration'] = $big_push_duration;
            }
        }
        $this->buildArguments('options', $options);
    }

    /**
     * 发送请求
     */
    public function sendPush()
    {
        // 分析阐述
        if (!$this->parseArguments())
        {

            throw new Exception($this->error, 1);
        }
        // 构造curl
        $ch = curl_init();
        $header = array('Authorization: Basic '.$this->base64AuthString);
        $data = json_encode($this->arguments);
        if ($this->debug)
        {
            curl_setopt($ch, CURLOPT_URL, $this->API_VALIDATE);
        }
        else
        {
            curl_setopt($ch, CURLOPT_URL, $this->API_PUSH);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 27);
        $this->response = curl_exec($ch);
        if (curl_errno($ch))
        {
            $this->error = curl_error($ch);
            return false;
        }
        curl_close($ch);
        if ($this->debug)
        {
            $this->print_debug();
        }
        try {
            $this->response = json_decode($this->response, true);
            if (isset($this->response['error']))
            {
                $this->error = $this->APIError($this->response['error']['code']);
                return false;
            }
        } catch (Exception $e) {
            $this->error = '网络错误';
            return false;
        }
        return true;
    }

    public function printArguments()
    {
        print_r(json_encode($this->arguments));
    }

    public function printResponse()
    {
        print_r($this->response);
    }
    
    public function print_debug()
    {
        $this->printArguments() . '<br />';
        $this->printResponse() . '<br />';
        echo $this->error;
    }
}
// $j = new JPush();
// $j->_debug(1);
// $j->setPlatform($j->IOS);
// $j->setNotification('api接口测试api接口测试api接口测试api接口测试api接口测试ap', 'test title');
// $j->setIOS('api接口测试');
// $j->setPlatform($j->ANDROID);
// $j->setAudience($j->TAG, array('中澳自贸协议零关税'));
// $j->sendPush();