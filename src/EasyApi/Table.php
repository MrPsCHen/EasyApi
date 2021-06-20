<?php


namespace EasyApi;
use think\db\exception\DbException;
use think\facade\Db;

class Table implements \EasyApi\interFaces\Table
{
    protected string    $table              = '';//表名
    protected string    $prefix             = '';//表前缀
    protected string    $primary            = '';//主键字段
    protected array     $field_full         = [];//全部字段
    protected array     $field_unique       = [];//唯一字段
    protected array     $field_not_null     = [];//唯一字段
    protected array     $field_type         = [];
    protected array     $field_comment      = [];


    protected string    $extra_alias        = '';//别名
    protected array     $alias              = [];//字段别名
    protected array     $display_arr        = [];//显示字段
    protected array     $filter_arr         = [];//过滤字段
    protected string    $error_message      = '';
    protected array     $field_name_display =[];


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
                    $this->field_type[$v['Field']]      = $v['Type'];
                    $this->field_comment[$v['Field']]   = $v['Comment'];

                }

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

        if(!$this->checkValue($filed_name,$filed_value))return false;

        return true;

    }

    public function verifyNotNullFiled(array $array){

        $field = array_diff($this->field_not_null,$array);
        foreach ($field as $item){
            $display = isset($this->field_name_display[$item])?$this->field_name_display[$item]:$item;
            $this->error_message = "{$display}为必填";
            return false;

        }
        return true;

    }

    /**
     * 验证字段数据是否存在
     */
    public function filedValueHas(){

    }

    /**
     * 字段范围类型判断
     */
    public function checkValue(string $filed,?string $val){
        if(isset($this->field_type[$filed])){
            list($type,$length) = explode('(',trim($this->field_type[$filed],')'));
            if(isset(self::type[$type])){
                $display = isset($this->field_name_display[$filed])?$this->field_name_display[$filed]:$filed;

                switch ($confine = self::type[$type][0]){
                    case 'STRLEN':
                        if(strlen($val)>$length){
                            $this->error_message = "{$display}长度不能大于$length";
                            return false;
                        }
                        break;
                    case 'SIZE':
                        if(!is_numeric($val)){
                            $this->error_message = "{$display}不是有效数值";
                            return false;
                        }
                        $confine = self::type[$type][1];
                        if($val>self::type[$type][1][1]){
                            $this->error_message = "{$display}超出有效范围值:{$confine[1]}";
                            return false;
                        }else if($val<self::type[$type][1][0]){
                            $this->error_message = "{$display}超出有效范围值:{$confine[0]}";
                            return false;
                        }
                        break;
                    case 'IS_NUMERIC':
                        if(!is_numeric($val)){
                            $this->error_message = "{$display}不是有效数值";
                            return false;
                        }
                        break;
                    case 'IS_FLOAT':
                        if(!is_numeric($val)&&!is_float($val)){
                            $this->error_message = "{$display}不是有效数值";
                            return false;
                        }
                        break;
                    case 'DATA':

                        break;
                    default:

                }

            }
            return true;

        }else{
            $this->error_message = '为收录类型:'.$filed;
            return false;
        }
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



    /**
     * 字段名字设置显示
     * ['id'=>'序号'],['name'=>'名称'];
     * @param array $field_name_display
     */
    public function setFiledNameDisplay(array $field_name_display): Table
    {

        $this->field_name_display = $field_name_display;

        return $this;
    }

    /**
     * @return array
     */
    public function getFieldUnique(): array
    {
        return $this->field_unique;
    }

    /**
     * @return array
     */
    public function getFieldNameDisplay(): array
    {
        return $this->field_name_display;
    }










}