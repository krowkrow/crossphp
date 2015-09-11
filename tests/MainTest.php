<?php
define('PROJECT_PATH', __DIR__.DIRECTORY_SEPARATOR.'project'.DIRECTORY_SEPARATOR);
require __DIR__."/../boot.php";

use Cross\Core\Delegate;
use Cross\Core\Loader;

/**
 * @Auth: wonli <wonli@live.com>
 * Class MainTest
 */
class MainTest extends PHPUnit_Framework_TestCase
{
    /**
     * 是否能正确加载app
     */
    function testLoadApp()
    {
        $app = Delegate::loadApp('test');
        $this->assertInstanceOf('Cross\\Core\\Delegate', $app, 'loadApp error');
    }

    /**
     * 读取app配置文件
     */
    function testReadAppConfig()
    {
        $result = $this->getAppResponse('Main:getAppConfig');
        $ori_file = Loader::read('::app/test/init.php');
        $this->assertJsonStringEqualsJsonString($result, json_encode($ori_file['router'], true), 'read app/init.php error...');
    }

    /**
     * 设置appConfig
     */
    function testSetAppConfig()
    {
        $params = array('a'=>array(1, 2, 3, 'name' => array('a', 'b', 'c')));
        $result = $this->getAppResponse('Main:setAppConfig', $params);

        $this->assertEquals($result, json_encode($params), 'set app config error...');
    }

    /**
     * 测试注释配置
     */
    function testAnnotate()
    {
        $params = array(1, 2, 3);
        $result = $this->getAppResponse('Main:annotate', $params);
        $this->assertEquals($result, array('a' => 1, 'b' => 2, 'c' => 3), 'parse annotate error...');
    }

    /**
     * 测试生成连接
     */
    function testMakeLink()
    {
        $dot = '/';
        $ext = '';
        $params = array('p1'=>1, 'p2'=>2, 'p3'=>3);
        $params['dot'] = $dot;
        $params['ext'] = $ext;
        $params['index'] = 'index.php';

        for($link_type=1; $link_type <= 5; $link_type ++) {
            $params['type'] = $link_type;
            $result = $this->getAppResponse('Main:makeLink', $params);

            switch($link_type)
            {
                case 1:
                    $this->assertEquals("/?/Main{$dot}getUrlSecurityParams{$dot}1{$dot}2{$dot}3{$ext}", $result, 'url->type=>1 make link error');
                    break;
                case 2:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$ext}?p1=1&p2=2&p3=3", $result, 'url->type=>2 make link error');
                    break;
                case 3:
                    $this->assertEquals("/?/Main{$dot}getUrlSecurityParams{$dot}p1{$dot}1{$dot}p2{$dot}2{$dot}p3{$dot}3{$ext}", $result, 'url->type=>3 make link error');
                    break;
                case 4:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$dot}p1{$dot}1{$dot}p2{$dot}2{$dot}p3{$dot}3{$ext}", $result, 'url->type=>4 make link error');
                    break;

                case 5:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$dot}1{$dot}2{$dot}3{$ext}", $result, 'url->type=>5 make link error');
                    break;
            }
        }
    }

    /**
     * 生成加密连接测试
     */
    function testMakeEncryptLink()
    {
        $dot = '/';
        $ext = '';
        $params = array('p1'=>1, 'p2'=>2, 'p3'=>3);
        $params['dot'] = $dot;
        $params['ext'] = $ext;
        $params['index'] = 'index.php';

        for($link_type=1; $link_type <= 5; $link_type ++) {
            $params['type'] = $link_type;
            $result = $this->getAppResponse('Main:makeEncryptLink', $params);

            switch($link_type)
            {
                case 1:
                    $this->assertEquals("/?/Main{$dot}getUrlSecurityParams{$dot}5c38a0417051803{$ext}", $result, 'url->type=>1 make link error');
                    break;
                case 2:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$ext}?cd4b145090a061643540a041115560803", $result, 'url->type=>2 make link error');
                    break;
                case 3:
                    $this->assertEquals("/?/Main{$dot}getUrlSecurityParams{$dot}692ad450918061f435418041815561a03{$ext}", $result, 'url->type=>3 make link error');
                    break;

                case 4:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$dot}692ad450918061f435418041815561a03{$ext}", $result, 'url->type=>4 make link error');
                    break;

                case 5:
                    $this->assertEquals("/index.php/Main{$dot}getUrlSecurityParams{$dot}5c38a0417051803{$ext}", $result, 'url->type=>5 make link error');
                    break;
            }
        }
    }

    /**
     * url加密 参数解密测试
     */
    function testMakeEncryptLinkAndDecryptParams()
    {
        $dot = '/';
        $ext = '';
        $params = array('p1'=>1, 'p2'=>2, 'p3'=>3);
        $params['dot'] = $dot;
        $params['ext'] = $ext;

        for($link_type=1; $link_type <= 5; $link_type ++) {
            $params['link_type'] = $link_type;
            $result = $this->getAppResponse('Main:makeEncryptLinkAndDecryptParams', $params);
            $this->assertEquals(json_encode($params), $result, "url type {$link_type} encrypt link failure!");
        }
    }

    /**
     * 调用app指定controller
     *
     * @param $controller
     * @param array $params
     * @return string
     */
    protected function getAppResponse($controller, $params=array())
    {
        return Delegate::loadApp('test')->get($controller, $params, true) ;
    }
}
