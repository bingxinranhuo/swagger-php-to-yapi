<?php

require("vendor/autoload.php");
$swaggerToYapi = new SwaggerToYapi();
$swaggerToYapi->run();


/**
 *  生成文档,导入yapi
 * @author fman
 * @date 2019/2/15 13:47
 */
class SwaggerToYapi
{
    /*==== 必须正确填写的配置 ===*/
    ###############################START#######################################
    const LOG_PATH = '/home/work/logs/swagger-php/logs/';//日志路径
    const PROJECT_PATH = '/home/luojianglai/www/swagger-php/projects/';//项目仓库路径
    const YAPI_DOMAIN = 'http://127.0.0.1';//yapi域名
    const YAPI_IMPORT_API_URL = '/api/open/import_data';//导入文档接口地址 wiki https://yapi.ymfe.org/openapi.html

    //项目列表
    const PROJECT_LIST = [
        'projectName' => ['token' => '2c1cc61593f66101e13f', 'path' => self::PROJECT_PATH . 'projectName/home/controllers'],
    ];
    ################################END#########################################


    private $project;
    private $projectPath;
    private $projectToken;

    public function __construct()
    {
        $this->initLog();
        $this->initParams();
    }

    /**
     * 入口
     * @author fman
     * @date 2019/2/15 13:48
     */
    public function run()
    {
        //生成文档
        $apiDoc = $this->generatorApi(self::PROJECT_LIST[$this->project]['path']);
        //导入
        $this->import($this->projectToken, $apiDoc);
    }

    /**
     * 生成文档
     * @author fman
     * @date 2019/2/15 10:33
     */
    private function generatorApi($path)
    {
        $openapi = \OpenApi\scan($path);
        return $openapi->toJson();
    }


    /**
     * 导入yapi
     * @param $token
     * @param $apiDoc
     * @author fman
     * @date 2019/2/15 13:48
     */
    private function import($token, $apiDoc)
    {
        $postData = [
            'type' => 'swagger',
            'json' => $apiDoc,
            'dataSync' => 'good',
            'token' => $token
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::YAPI_DOMAIN . self::YAPI_IMPORT_API_URL);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode != 200) {
            $errMsg = '导入失败_请求导入异常 code:' . $httpCode . ' response:' . $response;
            echo $errMsg;
            Log::error($errMsg);
            die;
        }
        $responseArr = json_decode($response, JSON_OBJECT_AS_ARRAY);
        if (!isset($responseArr['errcode']) || $responseArr['errcode'] != 0) {
            $httpCode = $responseArr['errcode'] ?? '';
            $errMsg = '导入失败_请求导入异常 errcode:' . $httpCode . ' response:' . $response;
            echo $errMsg;
            Log::error($errMsg);
        }
        curl_close($ch);
        echo 'swagger-php:API文档导入成功, ' . $responseArr['errmsg'];
    }

    /**
     * 初始化参数
     * @author fman
     * @date 2019/2/15 14:49
     */
    private function initParams()
    {
        if (empty($_GET['project'])) {
            $errMsg = '参数错误:没有project';
            echo $errMsg;
            Log::error($errMsg);
            exit();
        } else {
            $project = $_GET['project'];
        }
        $this->project = $project;
        if (!isset(self::PROJECT_LIST[$project])) {
            $errMsg = '配置中没有该项目:' . $project;
            echo $errMsg;
            Log::error($errMsg);
            exit();
        }
        $this->projectPath = self::PROJECT_LIST[$project]['path'];
        $this->projectToken = self::PROJECT_LIST[$project]['token'];

        if (!is_dir($this->projectPath)) {
            $errMsg = '配置错误_项目接口路径不存在:' . $this->projectPath;
            echo $errMsg;
            Log::error($errMsg);
            exit();
        }
    }

    /**
     * 初始化日志
     * @author fman
     * @date 2019/2/15 14:35
     */
    private function initLog()
    {
        Log::init(self::LOG_PATH);
        register_shutdown_function(function () {
            error_reporting(0);
            $msg = error_get_last();
            if (empty($msg)) {
                return;
            }
            $content = 'url:' . $_SERVER['REQUEST_URI']
                . ' file:' . $msg['file']
                . ' line:' . $msg['line']
                . ' message:' . $msg['message'];

            switch ($msg['type']) {
                case 2:
                case 8:
                case 32:
                case 128:
                case 512:
                case 1024:
                    Log::warning($content);
                    break;
                case 1:
                case 4:
                case 16:
                case 64:
                case 256:
                    Log::error($content);
                    break;
                default:
                    Log::error($content);
                    break;
            }
            return;
        });
    }
}

/**
 * 日志
 * @author fshman
 * @date 2019/2/15 13:48
 */
class Log
{
    private static $logPath;         //日志路径/a/b
    private static $logLevel;        //日志的写入级别  debug < info < notice < warning < error
    private static $logPid;           //进程号
    private static $logId;           //日志唯一标识id
    private static $rollType;        //日志文件类型
    private static $noticeStr;       //追加notice日志

    //日志类型
    const HOUR_ROLLING = 1;
    const DAY_ROLLING = 2;
    const MONTH_ROLLING = 3;

    //日志级别
    const DEBUG = 1;
    const INFO = 2;
    const NOTICE = 4;
    const WARNING = 8;
    const ERROR = 16;

    /**
     * @param string $path 日志路径 例/a/b
     * @param string $name 日志文件名 例 error info
     * @param int $level 日志级别   低于设定级别的日志不会被记录 error级别写入error文件 其他写入access文件
     * @param string $logId 日志唯一标识
     * @param string $rollType 日志文件类别 1:YmdH 2:Ymd 3:Ym 其他: .log
     */
    public static function init($path, $level = self::INFO, $logId = '', $rollType = self::DAY_ROLLING)
    {
        if (empty($path)) {
            die('日志目录及文件名不能为空');
        }
        if (!is_writable($path)) {
            die('日志目录不可写入');
        }

        self::$logPath = $path;
        self::$logLevel = $level;
        self::$logPid = posix_getpid();
        self::$logId = empty($logId) ? self::generateLogId() : $logId;
        self::$rollType = $rollType;
    }

    /**
     *设置logId
     */
    public static function generateLogId()
    {
        return md5(microtime() . posix_getpid() . uniqid());
    }

    /**
     * @param string|array $msg
     */
    public static function error($msg)
    {
        self::writeLog(self::ERROR, $msg);
    }

    /**
     * @param string|array $msg
     */
    public static function warning($msg)
    {
        self::writeLog(self::WARNING, $msg);
    }

    /**
     * @param string|array $msg
     */
    public static function notice($msg)
    {
        self::writeLog(self::NOTICE, $msg);
    }

    /**
     * @param string|array $msg
     */
    public static function info($msg)
    {
        self::writeLog(self::INFO, $msg);
    }

    /**
     * @param string|array $msg
     */
    public static function debug($msg)
    {
        self::writeLog(self::DEBUG, $msg);
    }


    /**
     * 追加nontice日志
     * @param $format
     * @param $arr_data
     */
    public static function pushNotice($msg)
    {
        if (is_array($msg)) {
            foreach ($msg as $k => $val) {
                if (is_array($val)) {
                    $val = json_encode($val);
                }
                self::$noticeStr .= " " . $k . ':' . $val;
            }
        } else {
            self::$noticeStr .= " " . $msg;
        }

    }

    /**
     * 写入日志
     * @param  int $level
     * @param string|array $msg
     */
    private static function writeLog($level, $msg)
    {
        if ($level < self::$logLevel) {//低于设定级别的日志不记录
            return;
        }

        $logLevelName = [1 => 'debug', 2 => 'info', 4 => 'notice', 8 => 'warning', 16 => 'error'];
        list($usec, $sec) = explode(" ", microtime());
        $str = sprintf(
            "[%s] %s.%-06d %s %s",
            $logLevelName[$level],
            date("Y-m-d H:i:s", $sec),
            $usec * 1000000,
            'logId:' . self::$logId,
            'pid:' . posix_getpid()
        );

        if (is_array($msg)) {
            foreach ($msg as $k => $val) {
                if (is_array($val)) {
                    $val = json_encode($val);
                }
                $str .= " " . $k . ':' . $val;
            }

        } else {
            $str .= " " . $msg;
        }

        if (!empty(self::$noticeStr) && $level == self::NOTICE) {
            $str .= self::$noticeStr;
        }
        $str .= "\n";

        $filePath = self::getLogFilePath($level);
        file_put_contents($filePath, $str, FILE_APPEND);
        self::$noticeStr = '';
        return;
    }

    /**
     * 获取日志的决绝路径
     * @param $level
     * @return string
     */
    private static function getLogFilePath($level)
    {
        $file = $level == self::ERROR ? 'error' : 'access';
        $filePath = rtrim(self::$logPath, '/') . '/' . $file;
        switch (self::$rollType) {
            case self::DAY_ROLLING:
                $filePath .= date('Ymd') . '.log';
                break;
            case self::MONTH_ROLLING:
                $filePath .= date('Ym') . '.log';
                break;
            case self::HOUR_ROLLING:
                $filePath .= date('YmdH') . '.log';
                break;
            default:
                $filePath .= '.log';
                break;
        }
        return $filePath;
    }

    /**
     * 获取去文件行号
     */
    public static function getFileLineNo()
    {
        $bt = debug_backtrace();
        if (isset($bt[1]) && isset($bt[1] ['file'])) {
            $c = $bt[1];
        } else {
            if (isset($bt[2]) && isset($bt[2] ['file'])) { //为了兼容回调函数使用log
                $c = $bt[2];
            } else {
                if (isset($bt[0]) && isset($bt[0] ['file'])) {
                    $c = $bt[0];
                } else {
                    $c = array('file' => 'faint', 'line' => 'faint');
                }
            }
        }
        return ['file' => $c ['file'], 'line' => $c ['line']];
    }

}
