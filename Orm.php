<?php
/**
 * Attokit/Attoorm common methods
 */

namespace Atto\Orm;

use Atto\Orm\DbApp;
use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Atto\Box\Request;

class Orm 
{
    const NS = "\\Atto\\Orm\\";

    /**
     * 缓存已实例化的 DbApp 
     */
    public static $APP = [];

    /**
     * __callStatic
     * @param String $key
     * @param Mixed $args
     */
    public static function __callStatic($key, $args)
    {
        /**
         * Orm::App()
         * 返回 DbApp 实例
         */
        if (Orm::hasApp($key)) {
            //已实例化的，直接返回
            if (Orm::appInsed($key)) return Orm::$APP[$key];
            //实例化 DbApp
            $cls = cls("app/$key");
            if (!class_exists($cls)) return null;
            $app = new $cls();
            //缓存
            Orm::cacheApp($app);
            //返回 DbApp 实例
            return $app;
        }

        return null;
    }

    /**
     * 获取 app 路径下所有 可用的 DbApp name
     * @return Array [ DbAppName, ... ]
     */
    public static function apps()
    {
        $appcls = Orm::cls("DbApp");
        $dir = APP_PATH;
        $dh = @opendir($dir);
        $apps = [];
        while(($app = readdir($dh))!==false) {
            if ($app=="." || $app=="..") continue;
            $dp = $dir.DS.$app;
            if (!is_dir($dp)) continue;
            if (!file_exists($dp.DS.ucfirst($app).EXT)) continue;
            $cls = cls("app/".ucfirst($app));
            if (!class_exists($cls)) continue;
            if (!is_subclass_of($cls, $appcls)) continue;
            $apps[] = ucfirst($app);
        }
        return $apps;
    }

    /**
     * 判断是否存在 给出的 DbApp
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function hasApp($app)
    {
        $apps = Orm::apps();
        return in_array(ucfirst($app), $apps);
    }

    /**
     * 判断给出的 DbApp 是否已经实例化
     * @param String $app DbApp name like: Uac
     * @return Bool
     */
    public static function appInsed($app)
    {
        return isset(Orm::$APP[ucfirst($app)]);
    }
    
    /**
     * 获取 attoorm 类
     * @param String $clspath like: foo/Bar  --> \Atto\Orm\foo\Bar
     * @return Class 类全称
     */
    public static function cls($clspath)
    {
        return self::NS.str_replace("/", "\\", trim($clspath, "/"));
    }

    /**
     * 缓存 DbApp 实例
     * @param DbApp $app 实例
     * @return Orm self
     */
    public static function cacheApp($app)
    {
        if (!$app instanceof DbApp) return self::class;
        $appname = $app->name;
        Orm::$APP[$appname] = $app;
        return self::class;
    }
}