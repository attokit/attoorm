<?php
/**
 * CURD 操作类
 * 每次 curd 操作，将生成一个 Curd 实例
 * 操作结束后，此实例将释放
 */

namespace Atto\Orm;

use Atto\Orm\Dbo;
use Atto\Orm\Model;
use Medoo\Medoo;

class Curd 
{
    //关联的数据库实例 Dbo
    public $db = null;

    //关联的 模型(数据表) 类全称
    public $model = "";

    /**
     * curd 参数
     * 采用 medoo 方法，参数为 medoo 方法的参数
     */
    //curd 操作针对的 table 表名称
    public $table = "";
    //表关联，可以在 模型(数据表)类中 预定义
    public $join = [
        /*"[<]table" => [
            "pid" => "pid"
        ]*/
    ];
    //是否 join 关联表
    public $useJoin = false;
    //要返回值的 字段名 []
    public $field = "*";
    //where 参数
    public $where = [];

    /**
     * 构造 curd 操作实例
     * @param Dbo $db 数据库实例
     * @param String $model 要执行 curd 的 数据表(模型) 类全称
     */
    public function __construct($db, $model)
    {
        if (!$db instanceof Dbo || !class_exists($model) || !$db->hasModel($model::$name)) return null;
        $this->db = $db;
        $this->model = $model;
        $this->table = $model::$table;
        $this->join = $model::$join;
    }

    /**
     * curd 操作实例 是否 ready
     * 已经有 必要参数 table field
     * @return Bool
     */
    public function ready()
    {
        $db = $this->db;
        $model = $this->model;
        $table = $this->table;
        $field = $this->field;
        return 
            $db instanceof Dbo &&
            class_exists($model) &&
            $table!="" && 
            $table==$model::$table && 
            (
                is_notempty_str($field) ||
                is_notempty_arr($field)
            );
    }

    /**
     * 是否连接查询关联表
     * @param Bool $join 默认 true
     * @return Curd $this
     */
    public function join($join=true)
    {
        $this->useJoin = $join;
        return $this;
    }
    public function nojoin() {return $this->join(false);}

    /**
     * 处理关联表 join 参数
     * @return Array join 参数
     */
    public function parseJoin()
    {
        $join = $this->join;
        if (empty($join)) return [];

        //处理 TODO ...

        return $join;
    }

    /**
     * 构造 medoo 查询参数
     * 指定要返回值的 字段名 or 字段名数组 
     * @param Mixed $field 与 medoo field 参数格式一致
     * @return Curd $this
     */
    public function field($field="*")
    {
        $this->field = $field;
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * 直接编辑 where 参数 
     * @param Array $where 与 medoo where 参数格式一致
     * @return Curd $this
     */
    public function where($where=[])
    {
        $ow = $this->where;
        $this->where = arr_extend($ow, $where);
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * limit 参数 
     * @param Array $limit 与 medoo limit 参数格式一致
     * @return Curd $this
     */
    public function limit($limit=[])
    {
        if (
            (is_numeric($limit) && $limit>0) ||
            (is_notempty_arr($limit) && is_indexed($limit))
        ) {
            $this->where["LIMIT"] = $limit;
        }
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * order 参数 
     * @param Array $order 与 medoo order 参数格式一致
     * @return Curd $this
     */
    public function order($order=[])
    {
        if (
            is_notempty_str($order) ||
            (is_notempty_arr($order) && is_associate($order))
        ) {
            $this->where["ORDER"] = $order;
        }
        return $this;
    }

    /**
     * 构造 medoo 查询参数
     * match 参数 全文搜索
     * @param Array $match 与 medoo match 参数格式一致
     * @return Curd $this
     */
    public function match($match=[])
    {
        if (!empty($match)) {
            $this->where["MATCH"] = $match;
        }
        return $this;
    }



    /**
     * 执行查询
     * 使用 __call 方法
     * @param String $method medoo 查询方法
     * @param Array $args 输入参数
     */
    public function __call($method, $args)
    {
        $ms = explode(",", "select,insert,update,delete,replace,get,has,rand,count,max,min,avg,sum");
        if (in_array($method, $ms)) {
            //调用 medoo 查询方法
            if (!$this->ready()) return null;
            //join
            $join = $this->parseJoin();
            $canJoin = $this->useJoin==true && !empty($join);
            //准备 medoo 方法参数
            $ps = [];
            $ps[] = $this->table;
            switch ($method) {
                case "select":
                case "get":
                case "rand":
                case "count":
                case "max":
                case "min":
                case "avg":
                case "sum":
                    if ($canJoin) $ps[] = $join;
                    $ps[] = $this->field;
                    if (!empty($this->where)) $ps[] = $this->where;
                    break;
                case "insert":
                case "update":
                    if (is_notempty_arr($args)) {
                        $ps[] = $args;
                    } else {
                        return null;
                    }
                    if ($method=="update" && !empty($this->where)) $ps[] = $this->where;
                    break;
                case "delete":
                    if (!empty($this->where)) {
                        $ps[] = $this->where;
                    } else {
                        return null;
                    }
                    break;
                case "replace":
                    $ps[] = $this->field;
                    if (!empty($this->where)) $ps[] = $this->where;
                    break;
                case "has":
                    if ($canJoin) $ps[] = $join;
                    if (!empty($this->where)) $ps[] = $this->where;
                    break;
            }
            //执行 medoo 方法
            $rst = $this->db->medoo($method, ...$ps);
            
            return $rst;
        }
    }


    
}