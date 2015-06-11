<?php namespace Toplan\Sms;

use \Session;

class SmsManager {

    /**
     * the application instance
     * @var
     */
    protected $app;

    /**
     * agent instances
     * @var
     */
    protected $agents;

    /**
     * sms data
     * @var
     */
    protected $smsData;

    /**
     * construct
     * @param $app
     */
	public function __construct($app)
    {
        $this->app = $app;
        $this->init();
    }

    /**
     * sms manager init
     */
    private function init()
    {
        $data = [
                'sent' => false,
                'mobile' => '',
                'code' => '',
                'deadline_time' => 0,
                'rules' => config('laravel-sms.rules'),
            ];
        $this->smsData = $data;
    }

    /**
     * get data
     * 获取发送相关信息
     * @return mixed
     */
    public function getSmsData()
    {
        return $this->smsData;
    }

    /**
     * set sent data
     * 设置发送相关信息
     * @param array $data
     */
    public function setSmsData(Array $data)
    {
        $this->smsData = $data;
    }

    /**
     * put sms data to session
     * @param array $data
     */
    public function storeSmsDataToSession(Array $data = [])
    {
        $data = $data ?: $this->smsData;
        $this->smsData = $data;
        Session::put($this->getSessionKey(), $data);
    }

    /**
     * get sms data from session
     * @return mixed
     */
    public function getSmsDataFromSession()
    {
        return Session::get($this->getSessionKey(), []);
    }

    /**
     * remove sms data from session
     */
    public function forgetSmsDataFromSession()
    {
        Session::forget($this->getSessionKey());
    }

    /**
     * Is there a designated validation rule
     * 是否有指定的验证规则
     * @param $name
     * @param $ruleName
     *
     * @return bool
     */
    public function hasRule($name, $ruleName)
    {
        $data = $this->getSmsData();
        return isset($data['rules']["$name"]['rules']["$ruleName"]);
    }

    /**
     * get rule by name
     * @param $name
     *
     * @return mixed
     */
    public function getRule($name)
    {
        $data = $this->getSmsData();
        $ruleName = $data['rules']["$name"]['choose_rule'];
        return $data['rules']["$name"]['rules']["$ruleName"];
    }

    /**
     * set rule
     * @param $name
     * @param $value
     *
     * @return mixed
     */
    public function rule($name, $value)
    {
        $data = $this->getSmsData();
        $data['rules']["$name"]['choose_rule'] = $value;
        $this->setSmsData($data);
        return $data;
    }

    /**
     * is verify
     * @param string $name
     *
     * @return mixed
     */
    public function isCheck($name = 'mobile')
    {
        $data = $this->getSmsData();
        return $data['rules']["$name"]['is_check'];
    }

    /**
     * get verify sms template id
     * @param String $agentName
     * @return mixed
     */
    public function getVerifySmsTemplateId($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        $agentConfig = config('laravel-sms.'.$agentName);
        if ($agentConfig) {
            return $agentConfig['verifySmsTemplateId'];
        }
        throw new \InvalidArgumentException("get verify sms template id failed, because agent [$agentName] not support");
    }

    /**
     * get verify sms content
     * @return mixed
     */
    public function getVerifySmsContent()
    {
        return config('laravel-sms.verifySmsContent');
    }

    /**
     * generate verify code
     * @param null $length
     * @param null $characters
     *
     * @return string
     */
    public function generateCode($length = null, $characters = null)
    {
        $length = $length ?: (int) config('laravel-sms.codeLength');
        $characters = $characters ?: '123456789';
        $charLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; ++$i) {
            $randomString .= $characters[mt_rand(0, $charLength - 1)];
        }
        return $randomString;
    }

    /**
     * get code valid time (minutes)
     * @return mixed
     */
    public function getCodeValidTime()
    {
        return config('laravel-sms.codeValidTime');//minutes
    }

    /**
     * get session key
     * @return mixed
     */
    public function getSessionKey()
    {
        return config('laravel-sms.sessionKey');
    }

    /**
     * get the default agent name
     * @return mixed
     */
    public function getDefaultAgent()
    {
        return config('laravel-sms.agent');
    }

    /**
     * set the default agent name
     * @param $name
     * @return string
     */
    public function setDefaultAgent($name)
    {
        config(['laravel-sms.agent' => $name]);
        return config('laravel-sms.agent');
    }

    /**
     * get a agent instance
     * @param null $agentName
     *
     * @return mixed
     */
    public function agent($agentName = null)
    {
        $agentName = $agentName ?: $this->getDefaultAgent();
        if (! isset($this->agents[$agentName])) {
            $this->agents[$agentName] = $this->createAgent($agentName);
        }
        return $this->agents[$agentName];
    }

    /**
     * create a agent instance by agent name
     * @param $agentName
     *
     * @return mixed
     */
    public function createAgent($agentName)
    {
        $method = 'create'.ucfirst($agentName).'Agent';
        if (method_exists($this, $method)) {
            $agentConfig = $this->getAgentConfig($agentName);
            return $this->$method($agentConfig);
        }
        throw new \InvalidArgumentException("Agent [$agentName] not supported.");
    }

    /**
     * get agent config
     * @param $agentName
     *
     * @return array
     */
    public function getAgentConfig($agentName)
    {
        $config = config("laravel-sms.$agentName", []);
        $config['smsSendQueue'] = config('laravel-sms.smsSendQueue');
        $config['smsWorker'] = config('laravel-sms.smsWorker', 'Toplan\Sms\SmsWorker');
        $config['nextAgentEnable'] = config('laravel-sms.alternate.enable', false);
        $config['nextAgentName'] = $this->getAlternateAgentNameByCurrentName($agentName);
        if ( ! class_exists($config['smsWorker'])) {
            throw new \InvalidArgumentException("Worker [" . $config['worker'] . "] not support.");
        }
        return $config;
    }

    /**
     * get alternate agent name by current agent name
     * @param $agentName
     *
     * @return null
     */
    public function getAlternateAgentNameByCurrentName($agentName)
    {
        $agents = config("laravel-sms.alternate.agents", []);
        if ( ! count($agents)) {
            return null;
        }
        if ( ! in_array($agentName, $agents)) {
            return $agents[0];
        }
        $currentKey = array_search($agentName, $agents);
        if (($currentKey + 1) < count($agents)) {
            return $agents[$currentKey + 1];
        }
        return null;
    }

    /**
     * create a YunTongXun(云通讯) agent instance
     * YunTongXun`s official website:
     * http://www.yuntongxun.com/
     * @param $agentConfig
     * @return YunTongXunAgent
     */
    public function createYunTongXunAgent(Array $agentConfig)
    {
        return new YunTongXunAgent($agentConfig);
    }

    /**
     * create a YunPian agent instance
     * @param array $agentConfig
     *
     * @return YunPianAgent
     */
    public function createYunPianAgent(Array $agentConfig)
    {
        return new YunPianAgent($agentConfig);
    }

}