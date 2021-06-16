<?php


namespace EasyApi;
use think\db\exception\DbException;
use think\facade\Db;

class Table
{
    protected string    $table          = '';//表名
    protected string    $prefix         = '';//表前缀
    protected string    $primary        = '';//主键字段
    protected array     $field_full     = [];//全部字段
    protected array     $field_unique   = [];//唯一字段
    protected array     $field_not_null = [];//唯一字段
    protected array     $field_type     = [];
    protected array     $field_comment  = [];
    protected array     $filed_type     = [];


    protected string    $extra_alias    = '';//别名
    protected array     $alias          = [];//字段别名
    protected array     $display_arr    = [];//显示字段
    protected array     $filter_arr     = [];//过滤字段
    protected string    $error_message  = '';


    public function __construct(string $table, string $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;
        $this->structure();
    }

    /**
     * @return void 解析表结构
     * @throws DbException
     */
    public function structure()
    {
        if (!empty($this->table))
        {

            try {
                foreach (Db::query('SHOW FULL COLUMNS FROM ' . $this->getTable()) as $k => $v) {
                    $this->field_full[] = $v['Field'];
                    $v['Key']   === 'PRI'   && $this->primary           = $v['Field'];
                    $v['Key']   === 'UNI'   && $this->field_unique[]    = $v['Field'];
                    $v['Null']  === 'NO'    && $v['Key'] !== 'PRI' && $this->field_not_null[] = $v['Field'];
                    $this->filed_type[$v['Field']]      = $v['Type'];
                    $this->field_comment[$v['Field']]   = $v['Comment'];
                }
                dd($this->filed_type,$this->field_comment);
            }catch (DbException $e){
//                throw new DbException('数据库或数据表不存在');
            }
        }
    }


    /**
     * @return string 获取完整表名
     */
    public function getTable()
    {
        return $this->prefix . $this->table;
    }

    /**
     * @param array $alias
     */
    public function setAlias(array $alias = []): Table
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * @param bool $field_prefix
     * @return array
     */
    public function outFiled(bool $field_prefix = true)
    {
        $back = [];

        foreach ($this->field_full as $key => $val) {
            if(in_array($val,$this->filter_arr))continue;
            if(!empty($this->display_arr) && !in_array($val,$this->display_arr))continue;
            $field_name = in_array($val,$this->alias)?$this->alias[$val]:"{$this->table}_{$val}";
            $alias = isset($this->alias[$val]) ? " AS `{$this->alias[$val]}`" : " AS `{$field_name}`";
            $back[$field_prefix?$field_name:$val] = "`{$this->getTable()}`.`{$val}`" . ($field_prefix ? $alias : "");
        }
        return $back;
    }

    /**
     * @return string
     */
    public function getPrimary(): string
    {
        return $this->primary;
    }

    /**
     * @param array $array
     * @return $this
     */
    public function display(array $array = [])
    {
        $this->display_arr = $array;
        return $this;
    }


    /**
     * @param array $array
     * @return $this
     */
    public function filter(array $array= [])
    {
        $this->filter_arr = $array;
        return $this;
    }

    /**
     * 字段验证
     */
    public function filedVerifier(string $filed_name,$filed_value = '',$filed_name_display = [])
    {
//        dd($this->);

        dd($filed_name,$filed_value);
        $this->error_message = 'error';


        return false;

    }

    /**
     * 验证字段数据是否存在
     */
    public function filedValueHas(){

    }

    /**
     * @return array
     */
    public function getFieldFull(): array
    {
        return $this->field_full;
    }

    /**
     * @return array
     */
    public function getDisplayArr(): array
    {
        return $this->display_arr;
    }

    /**
     * @return array
     */
    public function getFilterArr(): array
    {
        return $this->filter_arr;
    }

    /**
     * @return string
     */
    public function getErrorMessage(): string
    {
        return $this->error_message;
    }








}