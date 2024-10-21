<?php
/**
 *  Attoorm Framework / traits  可复用的类特征
 *  model\pkgCalcer 为 数据表记录实例 增加 规格计算 功能
 * 
 *  
 * 
 */

namespace Atto\Orm\traits\model;

trait pkgCalcer 
{
    //必须定义 规格数据来源
    //public $pkgFields = ["unit","netwt","maxunit","minnum"];

    /**
     * 定义计算字段
     */

    /**
     * Getter
     * @name pkg
     * @title 成品规格
     * @desc 用于规格计算的成品规格参数
     * @type varchar
     * @jstype object
     * @phptype JSON
     */
    protected function pkgGetter()
    {
        $pfds = $this->pkgFields;
        return [
            "unit" => $this->{$pfds[0]},
            "netwt" => $this->{$pfds[1]},
            "maxunit" => $this->{$pfds[2]},
            "minnum" => $this->{$pfds[3]}
        ];
    }
}