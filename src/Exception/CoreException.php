<?php
/**
 * Cross - a micro PHP 5 framework
 *
 * @link        http://www.crossphp.com
 * @license     MIT License
 */
namespace Cross\Exception;

use Cross\Http\Response;
use Exception;

/**
 * @Auth: wonli <wonli@live.com>
 * Class CoreException
 * @package Cross\Exception
 */
class CoreException extends CrossException
{
    function errorHandler(Exception $e)
    {
        $cp_error = $this->cpExceptionSource($e);
        $code = $e->getCode() ? $e->getCode() : 500;

        return Response::getInstance()->setResponseStatus($code)
            ->display($cp_error, __DIR__ . '/_tpl/front_error.tpl.php');
    }
}
