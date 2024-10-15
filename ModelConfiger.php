<?php
/**
 * 数据表(模型) 参数解析器
 */

namespace Atto\Orm;

class ModelConfiger
{
    /**
     * 要解析参数的 数据表(模型) 类
     */
    public $model = null;

    //解析后应得到的 field config 数据结构
    public $dftConf = [
        "name" => "",
        "pk" => false,
        "ai" => false,
        "dbtype" => "varchar",
        "jstype" => "string",
        "phptype" => "string",
        "required" => false,
        "bool" => false,
        "isjson" => false,
        "default" => null
    ];

    //解析得到的 field config 数据
    public $field = [];
    //解析得到的 field 默认值 数据
    public $default = [];

    /**
     * 要解析的设置项目
     * 按顺序解析
     */
    public $keys = [
        "creation", //解析 creation 参数

        /*
        "meta",     //name,title,desc
        "type",     //type,isJson,json,isTime,time
        "view",     //width,showInTable
        "query",    //filterable,sortable,sort,searchable
        "special",  //isId,isPk,isEnable
        "form",     //showInForm,isSelector,selector,isSwitch,isGenerator,required,validate
        "inputer",  //formType,inputer
        "default",  //default
        "virtual",  //isVirtual,virtual
        "sum",      //isSum

        //解析数据表显示模式
        "mode",
        */
    ];

    //构造
    public function __construct($model)
    {
        $this->model = $model;
        //按顺序解析
        $this->parseFields();
        $this->parseFieldConfig();
        $this->parseFieldDefault();
    }

    /**
     * 根据 model 数据表(模型) creation 参数，获得 fields 字段列表
     * @return Array 字段名 数组
     */
    public function parseFields()
    {
        $fields = array_keys($this->model::$creation);
        $this->fields = $fields;
        $this->model::$fields = $fields;
        return $this;
    }

    /**
     * 解析 模型(表) 预设参数，获得 field config 数据
     * @return Array field config 数据
     */
    public function parseFieldConfig()
    {
        //按 keys 序列顺序，解析 model 预设参数，
        $keys = $this->keys;
        for ($i=0;$i<count($keys);$i++) {
            $key = $keys[$i];
            $m = "parse".ucfirst($key);
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }

        //解析 default 
        return $this;


        //解析预设参数
        $fdc = static::parseFieldConfig();
        $fdd = [];
        foreach ($fdc as $fdn => $c) {
            if ($c["ai"]==true) continue;
            if (is_null($c["default"])) continue;
            $fdd[$fdn] = $c["default"];
        }
        return [
            "fields" => array_keys(static::$creation),
            "field" => $fdc,
            "default" => $fdd
        ];
    }

    /**
     * 根据解析得到的 field config 生成 default 默认值
     * @return Array 数据表(模型) 默认值
     */
    public function parseFieldDefault()
    {
        $fdc = $this->field;
        $fdd = [];
        foreach ($fdc as $fdn => $c) {
            if ($c["ai"]==true || is_null($c["default"])) {
                $fdd[$fdn] = null;
            }
            $fdd[$fdn] = $c["default"];
        }
        //写入 
        $this->default = $fdd;
        $this->model::$default = $fdd;
        return $this;
    }




    /**
     * 将解析获得的参数附加到 数据表(模型) 类
     * @param Array $conf   [ fdn => [...], ... ]
     * @return $this
     */
    public function setConf($conf = [])
    {
        $this->field = arr_extend($this->field, $conf);
        $this->model::$field = $this->field;
        return $this;
    }

    /**
     * 查找已经解析获得的 字段参数
     * @param String $key
     * @return Mixed
     */
    public function getConf($key = null)
    {
        $conf = $this->$field;
        if (!is_notempty_str($key)) return $conf;
        return arr_item($conf, $key);
    }

    /**
     * 循环 fields 执行 callback 解析某个设置项，将结果 setConf($rst)
     * @param String $key   设置项名称
     * @param Callable $callback
     * @return $this
     */
    public function eachfield($key, $callback)
    {
        $fields = $this->model::$fields;
        $rst = [];
        for ($i=0;$i<count($fields);$i++) {
            $fdn = $fields[$i];
            $rst[$fdn] = $callback($fdn, $this->model);
        }
        if (!empty($rst)) {
            $this->setConf($rst);
        }
        return $this;
    }

    /**
     * 解析方法
     */
    //解析 creation 参数
    protected function parseCreation()
    {
        return $this->eachField("creation", function($fdn, $model) {
            $confi = [
                "name" => $fdn,
                "pk" => false,
                "ai" => false,
                "dbtype" => "varchar",
                "jstype" => "string",
                "phptype" => "string",
                "required" => false,
                "bool" => false,
                "isjson" => false,
                "default" => null
            ];
            $ci = $model::$creation[$fdn];
            if (strpos($ci, "PRIMARY KEY")!==false) {
                $confi["pk"] = true;
                $ci = str_replace("PRIMARY KEY","", $ci);
            }
            if (strpos($ci, "AUTOINCREMENT")!==false) {
                $confi["ai"] = true;
                $ci = str_replace("AUTOINCREMENT","", $ci);
            }
            if (strpos($ci, "NOT NULL")!==false) {
                $confi["required"] = true;
                $ci = str_replace("NOT NULL", "", $ci);
            }
            if (strpos($ci, "DEFAULT ")!==false) {
                $cia = explode("DEFAULT ", $ci);
                $dv = $cia[1] ?? null;
                if (is_notempty_str($dv)) {
                    if (substr($dv, 0,1)=="'" && substr($dv, -1)=="'") {
                        $dv = str_replace("'","",$dv);
                    } else {
                        $dv = $dv*1;
                    }
                }
                $confi["default"] = $dv;
                $ci = $cia[0];
            }
            $tps = explode(",", "integer,varchar,float,text,blob,numeric");
            for ($j=0;$j<count($tps);$j++) {
                $tpi = $tps[$j];
                if (strpos($ci, $tpi)===false && strpos($ci, strtoupper($tpi))===false) continue;
                $confi["dbtype"] = $tpi;
                switch ($tpi) {
                    case "integer":
                    case "float":
                        $confi["jstype"] = $tpi;
                        $confi["phptype"] = $tpi;
                        break;
                    case "numeric":
                        $confi["jstype"] = "float";
                        $confi["phptype"] = "float";
                        break;
                    case "varchar":
                    case "text":
                        $confi["jstype"] = "string";
                        $confi["phptype"] = "string";
                        if (!is_null($confi["default"])) {
                            $dft = $confi["default"];
                            if (
                                (substr($dft, 0, 1)=="{" && substr($dft, -1)=="}") ||
                                (substr($dft, 0, 1)=="[" && substr($dft, -1)=="]")
                             ) {
                                $jdft = j2a($dft);
                                $confi["default"] = $jdft;
                                $confi["isjson"] = true;
                                $jtype = substr($dft, 0, 1)=="{" ? "object" : "array";
                                $confi["json"] = [
                                    "type" => $jtype,
                                    "default" => $jdft
                                ];
                                $confi["jstype"] = $jtype;
                                $confi["phptype"] = "array";
                            }
                        }
                        break;
                    default:
                        $confi["jstype"] = $tpi;
                        $confi["phptype"] = $tpi;
                        break;
    
                }
            }
            if ($confi["dbtype"]=="integer" && $confi["required"]==true && ($confi["default"]==0 || $confi["default"]==1)) {
                $confi["bool"] = true;
            }
    
            return $confi;
        });
    }
    
}