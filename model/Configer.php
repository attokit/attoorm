<?php
/**
 * 数据表(模型) 类 参数设置工具类
 * 
 * 数据表(模型) 类 预设参数应保存在：
 *      [dbpath]/../config/ 路径下
 * 预设文件保存为：
 *      modelname.json
 * 预设文件可通过 build 生成：
 *      $model::configer->build();
 *      在每次修改预设参数后，都应执行 build 方法
 */

namespace Atto\Orm\model;

use Atto\Orm\Orm;
use Atto\Orm\Dbo;
use Atto\Orm\Model;

class Configer 
{
    /**
     * 缓存 configer 实例
     * key = "CFG_".md5($model::cls())
     */
    public static $CACHE = [];

    /**
     * 依赖：
     * 数据表(模型) 类
     */
    public $model = null;

    //解析得到的 数据表(模型) 类 参数
    public $context = [
        //表预设参数结构，在 [attoorm root]/model/sample_config.json 文件中查看
    ];

    /**
     * build 方法参数
     */
    //解析 model::预设 序列，按此顺序分别解析
    public $buildQueue = [
        "meta",     //解析 $model::$creation/$meta 参数，得到基本的 field 信息
        "special",  //解析 $model::$special 参数
    ];


    /**
     * 静态检查 model 是否已有 configer 实例，如果有则返回，没有则返回 false
     * @param Model $model 类全称
     * @return Configer instance  or  false
     */
    public static function hasConfiger($model)
    {
        if (!class_exists($model)) return false;
        $key = "CFG_".md5($model);
        if (!isset(self::$CACHE[$key])) return false;
        $cfger = self::$CACHE[$key];
        if (!$cfger instanceof Configer) return false;
        return $cfger;
    }

    /**
     * 构造
     * @param Model $model 类全称
     * @return void
     */
    public function __construct($model)
    {
        if (!class_exists($model)) return null;
        $this->model = $model;
        //执行 model config 参数初始化
        $this->initConfig();
    }

    /**
     * model config 参数初始化
     * 如果有 modelname.json 则读取
     * 如果没有 json 文件，则调用 build 方法，根据 model 预设参数，生成 json
     * @return Configer $this
     */
    public function initConfig()
    {
        $conf = $this->getConfigFileContent();
        if (empty($conf)) {
            //modelname.json 文件不存在，使用 build 方法创建
            $this->build();
        } else {
            //将预设参数写入 configer->context
            $this->context = $conf;
        }
        return $this;
    }



    /**
     * build 方法
     */

    /**
     * 生成 modelname.json 文件
     * @return Array 生成的 json 文件内容 []
     */
    public function build()
    {
        //解析 数据表(模型) 类 基本信息
        $this->buildModelMeta();
        //获取字段列表，包含 计算字段 Getter
        $this->buildGetFields();
        $this->buildGetGetterFields();
        //解析 join 关联表参数
        $this->buildJoin();


        //按解析序列顺序，依次解析
        $que = $this->buildQueue;
        $model = $this->model;
        for ($i=0;$i<count($que);$i++) {
            $key = $que[$i];
            $m = "buildField".ucfirst($key);
            if (method_exists($this, $m)) {
                $this->$m();
            }
        }

        $this->buildDefault();
        
        return $this;
    }

    /**
     * build 方法
     * 解析 model meta 数据：name，title，...
     * @return Configer $this
     */
    protected function buildModelMeta()
    {
        $conf = [];
        $model = $this->model;
        $db = $model::$db;
        if (!$db instanceof Dbo) return $this;
        $conf = [
            "app" => $db->app->name,
            "db" => $db->name,
        ];
        $ms = explode(",", "name,title,desc,xpath,table");
        foreach ($ms as $i => $mi) {
            if (isset($model::$$mi)) {
                $conf[$mi] = $model::$$mi;
            }
        }
        return $this->buildSetContext($conf);
    }

    /**
     * build 方法
     * 获取 model 所有字段名，创建 field 参数数据
     * @return Configer $this
     */
    protected function buildGetFields()
    {
        $conf = [];
        $model = $this->model;
        $creation = $model::$creation;
        $meta = $model::$meta;
        $fds = array_keys($creation);
        $conf["fields"] = $fds;
        $conf["field"] = [];
        foreach ($fds as $i => $fdi) {
            if (!isset($meta[$fdi])) continue;
            $mi = $meta[$fdi];
            $confi = [
                "name" => $fdi,
                "title" => $mi[0],
                "desc" => $mi[1] ?? "",
                "width" => $mi[2] ?? 3,
                "isGetter" => false
            ];
            $conf["field"][$fdi] = $confi;
        }
        return $this->buildSetContext($conf);
    }

    /**
     * build 方法
     * 获取 计算字段
     * 在 model 类中定义了 protected fooBarGetter() 方法，
     * 且 有注释：
     *      /**
     *       * getter
     *       * @name fooBar
     *       * @title 字段名
     *       * @desc 字段说明
     *       * @width 3
     *       * @type varchar
     *       * @jstype object
     *       * @phptype JSON
     *       * ...
     * 
     * 则有计算字段 fooBar
     */
    protected function buildGetGetterFields()
    {
        $conf = [
            "getterFields" => [],
            "field" => []
        ];
        $model = $this->model;
        $methods = cls_get_ms($model, function($mi) {
            if (substr($mi->name, -6)==="Getter") {
                $doc = $mi->getDocComment();
                if (strpos($doc, "* getter")!==false || strpos($doc, "* Getter")!==false) {
                    return true;
                }
            }
            return false;
        }, "protected");
        if (empty($methods)) return $this->buildSetContext($conf);
        //对找到的方法，进行处理
        foreach ($methods as $k => $mi) {
            $doc = $mi->getDocComment();
            $doc = str_replace("\\r\\n", "", $doc);
            $doc = str_replace("\\r", "", $doc);
            $doc = str_replace("\\n", "", $doc);
            $doc = str_replace("*\/", "", $doc);
            $da = explode("* @", $doc);
            array_shift($da);   //* getter
            $confi = [];
            foreach ($da as $i => $di) {
                $dai = explode(" ", trim(explode("*", $di)[0]));
                if (count($dai)<2) continue;
                $confi[$dai[0]] = implode(" ",array_slice($dai, 1));
            }
            $name = $confi["name"] ?? str_replace("Getter","", $k);
            $confi["isGetter"] = true;
            $conf["getterFields"][] = $name;
            $conf["field"][$name] = $confi;
        }
        return $this->buildSetContext($conf);
    }

    /**
     * build 方法
     * 解析 $model::$join/$useJoin 参数，得到关联表参数信息
     * @return Configer $this
     */
    protected function buildJoin()
    {
        $model = $this->model;
        $join = $model::$join;
        $use = $model::$useJoin;
        $conf = [
            "param" => $join,
            "availabel" => !empty($join),   //join 参数是否可用
            "use" => $use,                  //是否每次查询都默认启用 join 关联表查询
            "tables" => [],                 //关联表 表名 数组，全小写
            "field" => [                    //有关联表的 字段参数
                /*
                "field name" => [
                    "table name" => [
                        "linkto" => "关联表中字段名"
                        "relate" => ">|<|<>|>< == left|right|full|inner join"
                    ],
                ]
                */
            ],
        ];
        if (empty($join)) return $this->buildSetContext(["join"=>$conf]);
        //获取 关联表 表明列表
        //$db = $model::$db;
        //if (!$db instanceof Dbo) return $this->buildSetContext(["join"=>$conf]);
        $tbs = [];
        $jfd = [];
        foreach ($join as $k => $v) {
            //$k like '[>]table (alias)'
            $ka = explode("]", $k);
            $rl = str_replace("[","",$ka[0]);   //join 类型
            $ka = explode("(", $ka[1]);
            $tbn = trim($ka[0]);
            //$v like 'fdn' or [fdn1,fdn2] or [fdn1=>fdn2, ... ]
            if (is_string($v)) {
                $fdn = [$v];
                $lfdn = [$v];
            } else if (is_indexed($v)) {
                $fdn = $v;
                $lfdn = $v;
            } else if (is_associate($v)) {
                $fdn = array_keys($v);
                $fdn = array_filter($fdn, function($i) {
                    //join 参数中 [ "table.field" => "..." ] 是其他 表的关联参数，此处不做处理
                    return strpos($i, ".")===false;
                });
                $lfdn = array_map(function ($i) use ($v) {
                    return $v[$i];
                }, $fdn);
            } else {
                $fdn = [];
                $lfdn = [];
            }
            //写入 关联表 名称数组
            if (!in_array($tbn, $tbs)) $tbs[] = $tbn;
            //写入 有关联表的 字段数组
            if (empty($fdn)) continue;
            for ($i=0;$i<count($fdn);$i++) {
                $fdi = $fdn[$i];
                $lfdi = $lfdn[$i];
                if (!isset($jfd[$fdi])) $jfd[$fdi] = [];
                if (!isset($jfd[$fdi][$tbn])) $jfd[$fdi][$tbn] = [];
                $jfd[$fdi][$tbn] = [
                    "linkto" => $lfdi,
                    "relate" => $rl
                ];
            }
        }
        $conf["tables"] = $tbs;
        $conf["field"] = $jfd;
        //写入 field 字段参数 isJoin, join
        $fdc = [];
        $fds = $this->context["fields"];
        foreach ($fds as $fi => $fdi) {
            if (empty($jfd) || !isset($jfd[$fdi])) {
                $fdc[$fdi] = [
                    "join" => [],
                    "isJoin" => false
                ];
            } else {
                $fdc[$fdi] = [
                    "join" => $jfd[$fdi],
                    "isJoin" => true
                ];
            }
        }
        return $this->buildSetContext([
            "join" => $conf,
            "field" => $fdc
        ]);
    }

    /**
     * build 方法
     * 获取 默认值 数组
     * !! 必须在 field 参数解析完成后 执行
     * @return Configer $this
     */
    protected function buildDefault()
    {
        $fc = $this->context["field"];
        $dft = [];
        foreach ($fc as $fdn => $fdc) {
            if (is_null($fdc["default"])) continue;
            $dft[$fdn] = $fdc["default"];
        }
        return $this->buildSetContext([
            "default" => $dft
        ]);
    }

    /**
     * build 方法
     * 循环 fields 执行 callback 解析某个设置项，将结果 buildSetContext([])
     * !! 必须先解析出 fields 字段数组
     * @param String $key   设置项名称
     * @param Callable $callback
     * @return $this
     */
    public function buildEachField($key, $callback)
    {
        $fields = $this->context["fields"];
        $model = $this->model;
        $conf = $this->context["field"];
        $rst = [];
        for ($i=0;$i<count($fields);$i++) {
            $fdn = $fields[$i];
            $rst[$fdn] = $callback($fdn, $conf[$fdn], $model);
        }
        if (!empty($rst)) {
            $this->buildSetContext([
                "field" => $rst
            ]);
        }
        return $this;
    }

    /**
     * build 方法
     * 按 buildQueue 顺序解析参数
     * buildFieldMeta 解析字段基础参数
     * 解析 $model::$creation/$meta 参数
     * @return Configer $this
     */
    protected function buildFieldMeta()
    {
        return $this->buildEachField("meta", function($fdn, $oconf, $model) {
            $conf = [
                "type" => "varchar",
                "jstype" => "string",
                "phptype" => "String",
                "isPk" => false,
                "isId" => false,
                "isRequired" => false,
                //"isNumber" => false,
                //"isBool" => false,
                "isJson" => false,
                "default" => null
            ];
            $ci = $model::$creation[$fdn];
            if (strpos($ci, "PRIMARY KEY")!==false) {
                $conf["isPk"] = true;
                $ci = str_replace("PRIMARY KEY","", $ci);
            }
            if (strpos($ci, "AUTOINCREMENT")!==false) {
                $conf["isId"] = true;
                $ci = str_replace("AUTOINCREMENT","", $ci);
            }
            if (strpos($ci, "NOT NULL")!==false) {
                $conf["isRequired"] = true;
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
                $conf["default"] = $dv;
                $ci = $cia[0];
            }
            $tps = explode(",", "integer,varchar,float,text,blob,numeric");
            for ($j=0;$j<count($tps);$j++) {
                $tpi = $tps[$j];
                if (strpos($ci, $tpi)===false && strpos($ci, strtoupper($tpi))===false) continue;
                $conf["type"] = $tpi;
                switch ($tpi) {
                    case "integer":
                    case "float":
                        //$conf["isNumber"] = true;
                        $conf["jstype"] = $tpi;
                        $conf["phptype"] = $tpi=="integer" ? "Int" : "Number";
                        if ($tpi=="integer" && !$conf["ai"] && !$conf["pk"] && ($conf["default"]==0 || $conf["default"]==1)){
                            $conf["jstype"] = "boolean";
                            $conf["phptype"] = "Bool";
                        }
                        break;
                    case "numeric":
                        //$conf["isNumber"] = true;
                        $conf["jstype"] = "float";
                        $conf["phptype"] = "Number";
                        break;
                    case "varchar":
                    case "text":
                        $conf["jstype"] = "string";
                        $conf["phptype"] = "String";
                        if (!is_null($conf["default"])) {
                            $dft = $conf["default"];
                            if (
                                (substr($dft, 0, 1)=="{" && substr($dft, -1)=="}") ||
                                (substr($dft, 0, 1)=="[" && substr($dft, -1)=="]")
                             ) {
                                $jdft = j2a($dft);
                                $conf["default"] = $jdft;
                                $conf["isJson"] = true;
                                $jtype = substr($dft, 0, 1)=="{" ? "object" : "array";
                                $conf["json"] = [
                                    "type" => $jtype,
                                    "default" => $jdft
                                ];
                                $conf["jstype"] = $jtype;
                                $conf["phptype"] = "JSON";
                            }
                        }
                        break;
                    default:
                        $conf["jstype"] = $tpi;
                        $conf["phptype"] = $tpi;
                        break;
    
                }
            }
    
            return $conf;
        });
    }

    /**
     * build 方法
     * 解析 $model::$special 参数，得到详细 字段参数
     * @return Configer $this
     */
    protected function buildFieldSpecial()
    {
        //直接将 special 参数写入 context
        $this->buildSetContext([
            "special" => $this->model::$special
        ]);

        return $this->buildEachField("special", function($fdn, $oconf, $model) {
            $conf = [];
            $spc = $model::$special;
            
            $spfs = explode(",", "hideintable,hideinform,sort,filter,search,money,bool");
            $isks = explode(",", "showInTable,showInForm,sortable,filterable,searchable,isMoney,isSwitch");
            foreach ($spfs as $i => $ki) {
                $arr = $spc[$ki] ?? [];
                $inarr = in_array($fdn, $arr);
                $ik = $isks[$i];
                $rev = substr($ik, 0,6)=="showIn";
                $conf[$isks[$i]] = $rev ? !$inarr : $inarr;
            }

            $spfs2 = explode(",", "times,numbers,jsons,generators");
            $isks2 = explode(",", "isTime,isNumber,isJson,isGenerator");
            $pks = explode(",", "time,number,json,generator");
            foreach ($spfs2 as $k => $kk) {
                $arr2 = $spc[$kk] ?? [];
                $inarr2 = isset($arr2[$fdn]);
                $conf[$isks2[$k]] = $inarr2;
                if ($inarr2) {
                    $conf[$pks[$k]] = $arr2[$fdn];
                }
            }

            if ($conf["isSwitch"]) {
                $conf["phptype"] = "Bool";
            }
            if ($conf["isJson"]) {
                if (isset($conf["json"]["default"])) {
                    $conf["default"] = $conf["json"]["default"];
                }
            }

            return $conf;
        });
    }

    /**
     * build 方法
     * 解析得到的参数，写入 context
     * @param Array $conf 
     * @return Configer $this
     */
    protected function buildSetContext($conf=[])
    {
        if (!is_notempty_arr($conf)) return $this;
        $this->context = arr_extend($this->context, $conf);
        return $this;
    }



    /**
     * tools
     */

    /**
     * tools
     * 获取 modelname.json 的保存路径
     * @return String 保存路径  or  null
     */
    public function getConfigFilePath()
    {
        $model = $this->model;
        $db = $model::$db;
        if (!$db instanceof Dbo) return null;
        $pi = $db->pathinfo;
        $cfp = $pi["dirname"].DS."..".DS."config";
        if (!is_dir($cfp)) @mkdir($cfp, 0777);
        $cfp = path_fix($cfp);
        return $cfp;
    }

    /**
     * tools
     * 读取 modelname.json 文件
     * 如果不存在，则返回 null
     * @return Array json --> []
     */
    public function getConfigFileContent()
    {
        $cfp = $this->getConfigFilePath();
        $model = $this->model;
        $mn = strtolower($model::$name);
        $cf = $cfp.DS.$mn.".json";
        if (!file_exists($cf)) return null;
        return j2a(file_get_contents($cf));
    }

    
}