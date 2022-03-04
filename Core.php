<?php

namespace libraries;

// 框架根目錄
defined('CORE_PATH') or define('CORE_PATH', __DIR__);

/**
 * 框架核心
 */
class Core
{
    // 讀取設定
    protected $config = [];

    public function __construct()
    {
        $this->setReporting();
        $this->loadSystemAuthentication();
        $config = require(APP_PATH . 'config/config.php');
        $this->config = $config;
    }

    // 執行框架
    public function run()
    {
        spl_autoload_register(array($this, 'loadClass'));
        $this->unregisterGlobals();
        $this->setSiteConfig();
        $this->setDbConfig();
        $this->setSMTP();
        $this->setReCAPTCHA();
        $this->setLicenseConfig();
        $this->setSubjectCategory();
        $this->definePath();
        $this->route();
    }

    // 路由設定
    public function route()
    {
        $controllerName = $this->config['defaultController'];
        $actionName = $this->config['defaultAction'];
        $param = array();

        $url = $_SERVER['REQUEST_URI'];
        // 清除?之後的内容
        $position = strpos($url, '?');
        $url = $position === false ? $url : substr($url, 0, $position);
        // 刪除前後的“/”
        $url = trim($url, '/');

        if ($url) {
            // 使用“/”分割字串，並保存在陣列中
            $urlArray = explode('/', $url);
            // 刪除空元素
            $urlArray = array_filter($urlArray);

            // 取得控制器名稱
            $controllerName = ucfirst($urlArray[0]);

            // 取得動作名稱
            array_shift($urlArray);
            $actionName = $urlArray ? $urlArray[0] : $actionName;

            // 取得URL参參數
            array_shift($urlArray);
            $param = $urlArray ? $urlArray : array();
        }

        // 判斷控制器與操作是否有效
        $controller = 'app\\controllers\\' . $controllerName . 'Controller';
        if (!class_exists($controller)) {
            exit($controller . ' Controller Not Exist!');
        }
        if (!method_exists($controller, $actionName)) {
            exit($actionName . ' Action Not Exist!');
        }

        // 如果控制器和操作名存在，则实例化控制器，因为控制器对象里面
        // 还会用到控制器名和操作名，所以实例化的时候把他们俩的名称也
        // 传进去。结合Controller基类一起看
        $dispatch = new $controller($controllerName, $actionName);

        // $dispatch保存控制器实例化后的对象，我们就可以调用它的方法，
        // 也可以像方法中传入参数，以下等同于：$dispatch->$actionName($param)
        call_user_func_array(array($dispatch, $actionName), $param);
    }

    // 偵測Debug mode
    public function setReporting()
    {
        if (APP_DEBUG === true) {
            error_reporting(E_ALL);
            ini_set('display_errors', 'On');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', 'Off');
            ini_set('log_errors', 'On');
        }
    }

    public function stripSlashesDeep($value)
    {
        $value = is_array($value) ? array_map(array($this, 'stripSlashesDeep'), $value) : stripslashes($value);
        return $value;
    }

    // 检测自定义全局变量并移除。因为 register_globals 已经弃用，如果
    // 已经弃用的 register_globals 指令被设置为 on，那么局部变量也将
    // 在脚本的全局作用域中可用。 例如， $_POST['foo'] 也将以 $foo 的
    // 形式存在，这样写是不好的实现，会影响代码中的其他变量。 相关信息，
    // 参考: http://php.net/manual/zh/faq.using.php#faq.register-globals
    public function unregisterGlobals()
    {
        if (ini_get('register_globals')) {
            $array = array('_SESSION', '_POST', '_GET', '_COOKIE', '_REQUEST', '_SERVER', '_ENV', '_FILES');
            foreach ($array as $value) {
                foreach ($GLOBALS[$value] as $key => $var) {
                    if ($var === $GLOBALS[$key]) {
                        unset($GLOBALS[$key]);
                    }
                }
            }
        }
    }

    //
    public function setSiteConfig()
    {
    }

    // 資料庫設定
    public function setDbConfig()
    {
        if ($this->config['db']) {
            define('DB_HOST', $this->config['db']['host']);
            define('DB_NAME', $this->config['db']['dbname']);
            define('DB_USER', $this->config['db']['username']);
            define('DB_PASS', $this->config['db']['password']);
        }
    }

    // 資料庫設定
    public function setSMTP()
    {
        if ($this->config['SMTP']) {
            define('SMTP_HOST', $this->config['SMTP']['Host']);
            define('SMTP_AUTH', $this->config['SMTP']['SMTPAuth']);
            define('SMTP_USERNAME', $this->config['SMTP']['Username']);
            define('SMTP_PASS', $this->config['SMTP']['Password']);
            define('SMTP_SECURE', $this->config['SMTP']['SMTPSecure']);
            define('SMTP_PORT', $this->config['SMTP']['Port']);
            define('SMTP_CHAR', $this->config['SMTP']['CharSet']);
            define('SMTP_FROMADDR', $this->config['SMTP']['fromAddress']);
            define('SMTP_FROMNAME', $this->config['SMTP']['fromName']);
        }
    }

    // 載入授權單位
    public function setLicenseConfig()
    {
        if ($this->config['license']) {
            define('SITE_CONFIG', $this->config['siteConfig']);
            define('AUTH_CONFIG', $this->config['license']['authConfig']);
            define('CAMPUS_CONFIG', $this->config['license']['campusConfig']);
            define('APP_SETTING', $this->config['license']['appConfig']);
            define('COLLEGE', $this->config['license']['campusConfig']['collegeListing']);
            define('CDIVISION', $this->config['license']['campusConfig']['campusDivision']);
            define('CSYSTEM', $this->config['license']['campusConfig']['campusSystem']);
        }
    }

    // 載入預定義授課類別
    public function setSubjectCategory()
    {
        if ($this->config['subject']) {
            define('SUBJECT_CATEGORY', $this->config['subject']['category']);
        }
    }

    // 載入reCAPTCHA設定
    public function setReCAPTCHA()
    {
        if ($this->config['reCAPTCHA']) {
            define('RECAPTCHA', $this->config['reCAPTCHA']);
        } else {
            die('reCAPTCHA config missing');
        }
    }

    // 定義路由路徑
    public function definePath()
    {
        $pathMap = [
            'dashboard' => [
                'name' => '管理儀表板',
                'subPath' => [
                    'index' => '首頁暨相關系統資訊°'
                ]
            ],
            'user' => [
                'name' => '個人檔案',
                'subPath' => [
                    'login' => '登入',
                    'forgot' => '忘記密碼',
                    'profile' => '請自行維護基本資料。'
                ]
            ],
            'classAndStu' => [
                'name' => '班級學生管理',
                'subPath' => [
                    'profile' => '新增學生資料',
                    'classList' => '現有註冊班級',
                    'classArchived' => '已封存註冊班級',
                    'classAdd' => '新增註冊班級',
                    'classView' => '檢視班級學生資料',
                    'classEdit' => '編輯註冊班級',
                    'studentAdd' => '新增學生資料',
                    'studentEdit' => '編輯學生資料',
                    'studentsUpload' => '學生資料整批上傳',
                ]
            ],
            'staffManage' => [
                'name' => '教職員帳號管理',
                'subPath' => [
                    'staffList' => '現有教職員',
                    'staffAdd' => '新增教職員帳號',
                    'staffEdit' => '編輯帳號',
                    'staffArchived' => '已封存帳號',
                ]
            ],
            'subject' => [
                'name' => '教學授課管理',
                'subPath' => [
                    'subjectList' => '現有開課清單 (本學期)',
                    'subjectAdd' => '我要開課',
                    'subjectArchived' => '開課歷史記錄 (不含本學期)',
                    'subjectEdit' => '編輯開課基本資料',
                    'subjectReopen' => '重新開課',
                    'subjectRegStu' => '加退選作業',
                    'subjectRegStuUpload' => '批次加退選作業',
                    'subjectView' => '授(修)課科目詳細內容',
                    'subjectSyllabus' => '相關課綱資訊',
                ]
            ],
            'application' => [
                'name' => '進階參數設定',
                'subPath' => [
                    'appSetting' => '請依據需求自行管理',
                ],
            ],
        ];
        define('PATH_MAP', $pathMap);
    }

    // 自動載入Class
    public function loadClass($className)
    {
        //echo 'Trying to load ', $className, ' via ', __METHOD__, "()\n";
        if (strpos($className, '\\') !== false) {
            // 包含应用（application目录）文件
            $file = APP_PATH . str_replace('\\', '/', $className) . '.php';
            if (!is_file($file)) {
                return;
            }
        } else {
            return;
        }

        include $file;
    }

    private function loadSystemAuthentication()
    {
        $licenseKey = 'c900482c-4261-4bd0-b141-b0f92f763b85';
        define('LICENSE_KEY', $licenseKey);
    }
}
