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
            if (Orm::appInsed($key)) return Orm::$APP[$key];
            $cls = cls("app/$key");
            if (!class_exists($cls)) return null;
            return new $cls();
        }
        /**
         * Orm::AppDbn()        返回 DbApp 实例中 Dbo 数据库实例
         * Orm::Dbn()           == Orm::[当前App]Dbn()
         * Orm::AppDbnModel()   返回 DbApp 实例中 Dbo 数据库实例中 数据表(模型) 类全称
         * Orm::DbnModel()      == Orm::[当前App]DbnModel()
         * Orm::Model()         == Orm::[当前App][Main]Model()
         * 
         * Orm::AppDbnModel(useJoin true|false)    返回 DbApp 实例中 Dbo 数据库实例中 针对 数据表(模型) 的 curd 操作实例
         */
        $ks = strtosnake($key, "-");    //FooBar --> foo-bar
        $ks = explode("-", $ks);
        $ks = array_map(function($ki) {return ucfirst($ki);}, $ks);
        //if (count($ks)>1) {
            //获取 DbApp 实例
            if (Orm::hasApp($ks[0])!=true) {
                //可以省略 appname 使用 Request::$current->app
                $apn = ucfirst(Request::$current->app);
                if (Orm::hasApp($apn)!=true) return null;
            } else {
                $apn = array_shift($ks);
            }
            $app = Orm::$apn();
            if (!$app instanceof DbApp) return null;
            if (empty($ks)) return $app;
            //获取 Dbo 实例
            if ($app->hasDb(strtolower($ks[0]))!=true) {
                //当 dbn 为 main 时，可以省略
                $dbn = "main";
            } else {
                $dbn = array_shift($ks);
            }
            $dbk = strtolower($dbn)."Db";   //$app->mainDb
            $dbo = $app->$dbk;
            if (!$dbo instanceof Dbo) return null;
            if (empty($ks)) return $dbo;
            //获取 数据表(模型) 类全称
            $mdn = array_shift($ks);
            $mdo = $dbo->getModel($mdn);
            if (!class_exists($mdo) || !is_subclass_of($mdo, Orm::cls("Model"))) return null;
            if (empty($args)) return $mdo;
            //当传入 bool 参数时，返回 针对 数据表(模型) 的 curd 操作实例
            $useJoin = is_bool($args[0]) ? $args[0] : true;
            $curd = $dbo->$mdn;
            $curd->join($useJoin);
            return $curd;

        //}

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