<?php
/**
 * Attokit/Attoorm common methods
 */

namespace Atto\Orm;

class Orm 
{
    const NS = "\\Atto\\Orm\\";
    
    /**
     * 获取 attoorm 类
     * @param String $clspath like: foo/Bar  --> \Atto\Orm\foo\Bar
     * @return Class 类全称
     */
    public static function cls($clspath)
    {
        return self::NS.str_replace("/", "\\", trim($clspath, "/"));
    }
}