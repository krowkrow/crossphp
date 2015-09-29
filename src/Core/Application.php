<?php
/**
 * Cross - a micro PHP 5 framework
 *
 * @link        http://www.crossphp.com
 * @license     MIT License
 */
namespace Cross\Core;

use Cross\Cache\RequestCache;
use Cross\Exception\CoreException;
use ReflectionClass;
use ReflectionMethod;
use Exception;
use Closure;

/**
 * @Auth: wonli <wonli@live.com>
 * Class Application
 * @package Cross\Core
 */
class Application
{
    /**
     * action 名称
     *
     * @var string
     */
    protected $action;

    /**
     * 运行时的参数
     *
     * @var mixed
     */
    protected $params;

    /**
     * 控制器名称
     *
     * @var string
     */
    protected $controller;

    /**
     * 当前app名称
     *
     * @var string
     */
    private $app_name;

    /**
     * action 注释
     *
     * @var string
     */
    private static $action_annotate;

    /**
     * 控制器注释配置
     *
     * @var array
     */
    private static $controller_annotate_config = array();

    /**
     * @var Delegate
     */
    private $delegate;

    /**
     * 实例化Application
     *
     * @param string $app_name
     * @param Delegate $delegate
     */
    function __construct($app_name, Delegate $delegate)
    {
        $this->app_name = $app_name;
        $this->delegate = $delegate;
        $this->config = $delegate->getConfig();
    }

    /**
     * 解析router
     *
     * @param Router|string|array $router
     * @param string $args 当$router类型为string时,指定参数
     * @return array
     */
    private function getRouter($router, $args)
    {
        $controller = '';
        $action = '';
        $params = '';

        if ($router instanceof Router) {

            $controller = $router->getController();
            $action = $router->getAction();
            $params = $router->getParams();

        } elseif (is_array($router)) {

            $controller = $router['controller'];
            $action = $router['action'];
            $params = $router['params'];

        } elseif (is_string($router)) {

            if (strpos($router, ':')) {
                list($controller, $action) = explode(':', $router);
            } else {
                $controller = $router;
                $action = 'index';
            }

            $params = $args;
        }

        return array('controller' => ucfirst($controller), 'action' => $action, 'params' => $params);
    }

    /**
     * 获取控制器的命名空间
     *
     * @param string $controller_name
     * @return string
     */
    protected function getControllerNamespace($controller_name)
    {
        return 'app\\' . $this->app_name . '\\controllers\\' . $controller_name;
    }

    /**
     * 默认的视图控制器命名空间
     *
     * @param string $controller_name
     * @return string
     */
    protected function getViewControllerNameSpace($controller_name)
    {
        return 'app\\' . $this->app_name . '\\views\\' . $controller_name . 'View';
    }

    /**
     * 初始化控制器
     *
     * @param string $controller 控制器
     * @param string $action 动作
     * @return ReflectionClass
     * @throws CoreException
     */
    private function initController($controller, $action = null)
    {
        $controllerSpace = $this->getControllerNamespace($controller);
        $controllerRealFile = PROJECT_REAL_PATH . str_replace('\\', DIRECTORY_SEPARATOR, $controllerSpace) . '.php';

        if (!is_file($controllerRealFile)) {
            throw new CoreException("{$controllerSpace} 控制器不存在");
        }

        try {
            $class_reflection = new ReflectionClass($controllerSpace);
            if ($class_reflection->isAbstract()) {
                throw new CoreException("{$controllerSpace} 不允许访问的控制器");
            }
        } catch (Exception $e) {
            throw new CoreException($e->getMessage());
        }

        $this->setController($controller);
        //控制器全局注释配置(不检测父类注释配置)
        $controller_annotate = $class_reflection->getDocComment();
        if ($controller_annotate) {
            self::$controller_annotate_config = Annotate::getInstance($controller_annotate)->parse();
        }

        if ($action) {
            try {
                $is_callable = new ReflectionMethod($controllerSpace, $action);
            } catch (Exception $e) {
                try {
                    $is_callable = new ReflectionMethod($controllerSpace, '__call');
                } catch (Exception $e) {
                    throw new CoreException("{$controllerSpace}->{$action} 不能解析的请求");
                }
            }

            if (isset($is_callable) && $is_callable->isPublic() && true !== $is_callable->isAbstract()) {
                $this->setAction($action);
                self::setActionAnnotate($is_callable->getDocComment());
            } else {
                throw new CoreException("{$controllerSpace}->{$action} 不允许访问的方法");
            }
        }

        return $class_reflection;
    }

    /**
     * 初始化参数
     *
     * @param $params
     * @param array $annotate_params
     */
    private function initParams($params, $annotate_params = array())
    {
        $this->setParams($params, $annotate_params);
    }

    /**
     * 运行框架
     *
     * @param object|string $router 要解析的理由
     * @param null $args 指定参数
     * @param bool $run_controller 是否只返回控制器实例
     * @param bool $return_response_content 是输出还是直接返回结果
     * @return array|mixed|string
     * @throws CoreException
     */
    public function dispatcher($router, $args = null, $run_controller = true, $return_response_content = false)
    {
        $router = $this->getRouter($router, $args);
        $action = $run_controller ? $router ['action'] : null;
        $cr = $this->initController($router ['controller'], $action);

        $action_config = self::getActionConfig();
        $action_params = array();
        if (isset($action_config['params'])) {
            $action_params = $action_config['params'];
        }
        $this->initParams($router ['params'], $action_params);

        $this->delegate->getClosureContainer()->run('dispatcher');
        $cache = false;
        if (isset($action_config['cache']) && Request::getInstance()->isGetRequest()) {
            $cache = $this->initRequestCache($action_config['cache']);
        }

        if (isset($action_config['before'])) {
            $this->getClassInstanceByName($action_config['before']);
        }

        if (!empty($action_config['basicAuth'])) {
            Response::getInstance()->basicAuth($action_config['basicAuth']);
        }

        if ($cache && $cache->getExpireTime()) {
            $response_content = $cache->get();
        } else {
            $action = $this->getAction();
            $controller_name = $this->getController();
            $cr->setStaticPropertyValue('action_annotate', $action_config);
            $cr->setStaticPropertyValue('view_controller_namespace', $this->getViewControllerNameSpace($controller_name));
            $cr->setStaticPropertyValue('controller_name', $controller_name);
            $cr->setStaticPropertyValue('call_action', $action);
            $cr->setStaticPropertyValue('url_params', $this->getParams());
            $cr->setStaticPropertyValue('app_delegate', $this->delegate);
            $controller = $cr->newInstance();

            if (Response::getInstance()->isEndFlush()) {
                return true;
            }

            if (true === $run_controller) {
                ob_start();
                $response_content = $controller->$action();
                if (!$response_content) {
                    $response_content = ob_get_contents();
                }
                ob_end_clean();
                if ($cache) {
                    $cache->set(null, $response_content);
                }
            } else {
                return $controller;
            }
        }

        if (!empty($action_config['response'])) {
            $this->setResponseConfig($action_config['response']);
        }

        if ($return_response_content) {
            return $response_content;
        } else {
            Response::getInstance()->display($response_content);
        }

        if (isset($action_config['after'])) {
            $this->getClassInstanceByName($action_config['after']);
        }

        return true;
    }

    /**
     * 设置controller
     *
     * @param $controller
     */
    private function setController($controller)
    {
        $this->controller = $controller;
    }

    /**
     * 设置action
     *
     * @param $action
     */
    private function setAction($action)
    {
        $this->action = $action;
    }

    /**
     * 设置params
     *
     * @param null $url_params
     * @param array $annotate_params
     */
    private function setParams($url_params = null, $annotate_params = array())
    {
        $url_config = $this->config->get('url');
        //获取附加参数
        $reset_annotate_params = false;
        $router_addition_params = array();
        if (!empty($url_config['router_addition_params']) && is_array($url_config['router_addition_params'])) {
            $router_addition_params = $url_config['router_addition_params'];
            $reset_annotate_params = true;
        }

        switch ($url_config['type']) {
            case 1:
            case 5:
                if ($reset_annotate_params) {
                    $now_annotate_params = array();
                    foreach ($annotate_params as $key) {
                        if (!isset($router_addition_params[$key])) {
                            $now_annotate_params[] = $key;
                        }
                    }
                    $annotate_params = $now_annotate_params;
                }
                $params = self::combineParamsAnnotateConfig($url_params, $annotate_params);
                break;

            case 3:
            case 4:
                $params = self::oneDimensionalToAssociativeArray($url_params);
                if (empty($params)) {
                    $params = $url_params;
                }
                break;
            default:
                $params = $url_params;
                break;
        }

        if (empty($params)) {
            $this->params = $router_addition_params;
        } elseif (is_array($params)) {
            $this->params = array_merge($router_addition_params, $params);
        } else {
            $this->params = $params;
        }
    }

    /**
     * 合并参数注释配置
     *
     * @param null $params
     * @param array $annotate_params
     * @return array|null
     */
    public static function combineParamsAnnotateConfig($params = null, $annotate_params = array())
    {
        if (empty($annotate_params)) {
            return $params;
        }

        if (!empty($params)) {
            $params_set = array();
            foreach ($params as $k => $p) {
                if (isset($annotate_params[$k])) {
                    $params_set [$annotate_params[$k]] = $p;
                } else {
                    $params_set [] = $p;
                }
            }
            $params = $params_set;
        }

        return $params;
    }

    /**
     * 字符类型的参数转换为一个关联数组
     *
     * @param string $stringParams
     * @param string $separator
     * @return array
     */
    public static function stringParamsToAssociativeArray($stringParams, $separator)
    {
        return self::oneDimensionalToAssociativeArray(explode($separator, $stringParams));
    }

    /**
     * 一维数组按顺序转换为关联数组
     *
     * @param array $oneDimensional
     * @return array
     */
    public static function oneDimensionalToAssociativeArray($oneDimensional)
    {
        $result = array();
        for ($max = count($oneDimensional), $i = 0; $i < $max; $i++) {
            if (isset($oneDimensional[$i]) && isset($oneDimensional[$i + 1])) {
                $result[$oneDimensional[$i]] = $oneDimensional[$i + 1];
            }
            array_shift($oneDimensional);
        }

        return $result;
    }

    /**
     * 初始化request cache
     *
     * @param $request_cache_config
     * @return bool|\cross\cache\FileCache|\cross\cache\MemcacheBase|\cross\cache\RedisCache|\cross\cache\RequestMemcache|\cross\cache\RequestRedisCache
     * @throws \cross\exception\CoreException
     */
    private function initRequestCache($request_cache_config)
    {
        if (!is_array($request_cache_config) ||
            !isset($request_cache_config[1]) || !is_array($request_cache_config[1])
        ) {
            throw new CoreException('Request Cache 配置格式不正确');
        }

        list($cache_enable, $cache_config) = $request_cache_config;
        if (!$cache_enable) {
            return false;
        }

        if (empty($cache_config['type'])) {
            throw new CoreException('请指定Cache类型');
        }

        $display = $this->config->get('sys', 'display');
        Response::getInstance()->setContentType($display);
        if (!isset($cache_config ['cache_path'])) {
            $cache_config ['cache_path'] = PROJECT_REAL_PATH . 'cache' . DIRECTORY_SEPARATOR . 'request';
        }

        if (!isset($cache_config ['file_ext'])) {
            $cache_config ['file_ext'] = '.' . strtolower($display);
        }

        if (!isset($cache_config['key_dot'])) {
            $cache_config ['key_dot'] = DIRECTORY_SEPARATOR;
        }

        $cache_key_conf = array(
            'app_name' => $this->app_name,
            'tpl_dir_name' => $this->config->get('sys', 'default_tpl_dir'),
            'controller' => strtolower($this->getController()),
            'action' => $this->getAction(),
        );

        $params = $this->getParams();
        if (isset($cache_config ['key'])) {
            if ($cache_config ['key'] instanceof Closure) {
                $cache_key = call_user_func_array($cache_config ['key'], array($cache_key_conf, $params));
            } else {
                $cache_key = $cache_config['key'];
            }

            if (empty($cache_key)) {
                throw new CoreException("缓存key不能为空");
            }
        } else {
            if (!empty($params)) {
                $cache_key_conf['params'] = md5(implode($cache_config ['key_dot'], $params));
            }
            $cache_key = implode($cache_config['key_dot'], $cache_key_conf);
        }

        $cache_config['key'] = $cache_key;
        return RequestCache::factory($cache_config);
    }

    /**
     * 设置Response
     *
     * @param $config
     */
    private function setResponseConfig($config)
    {
        if (isset($config['content_type'])) {
            Response::getInstance()->setContentType($config['content_type']);
        }

        if (isset($config['status'])) {
            Response::getInstance()->setResponseStatus($config['status']);
        }
    }

    /**
     * 实例化一个数组中指定的所有类
     *
     * @param string|array $class_name
     * @throws CoreException
     */
    private function getClassInstanceByName($class_name)
    {
        if (!is_array($class_name)) {
            $class_array = array($class_name);
        } else {
            $class_array = $class_name;
        }

        foreach ($class_array as $class) {
            try {
                if (is_string($class)) {
                    $obj = new ReflectionClass($class);
                    $obj->newInstance();
                }
            } catch (Exception $e) {
                throw new CoreException('初始化类失败');
            }
        }
    }

    /**
     * 设置action注释
     *
     * @param string $annotate
     */
    private static function setActionAnnotate($annotate)
    {
        self::$action_annotate = $annotate;
    }

    /**
     * 获取action注释
     *
     * @return string
     */
    private static function getActionAnnotate()
    {
        return self::$action_annotate;
    }

    /**
     * 获取action注释配置
     *
     * @return array|bool
     */
    public static function getActionConfig()
    {
        $action_annotate_config = Annotate::getInstance(self::getActionAnnotate())->parse();
        if (empty(self::$controller_annotate_config)) {
            return $action_annotate_config;
        }

        if (is_array($action_annotate_config) && is_array(self::$controller_annotate_config)) {
            return array_merge(self::$controller_annotate_config, $action_annotate_config);
        }

        return self::$controller_annotate_config;
    }

    /**
     * 获取控制器名称
     *
     * @return mixed
     */
    public function getController()
    {
        return $this->controller;
    }

    /**
     * 获取action名称
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * 获取参数
     *
     * @return mixed
     */
    public function getParams()
    {
        return $this->params;
    }
}

