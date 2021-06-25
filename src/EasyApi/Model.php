<?php


namespace EasyApi;


use think\db\BaseQuery;
use think\db\Raw;
use think\facade\Db;

class Model
{
    protected string $table = '';
    protected string $prefix = '';

    protected array $tables         = [];   //表对象
    protected int $page             = 1;    //页码
    protected int $limit            = 20;   //条数
    protected $cursor               = null; //游标对象
    protected ?array $back          = [];   //返回对象
    protected array $_ploy          = [];   //聚合参数
    protected array $_extra         = [];   //扩展参数

    protected array $full_field     = [];
    protected array $check_field    = [];
    protected array $cursor_like    = [];
    protected array $cursor_in      = [];
    protected array $cursor_where   = [];
    protected array $cursor_where_or= [];
    protected array $cursor_join    = [];
    protected string $error_message = '';
    protected array $param          = [];
    protected int $Last_Insert_Id   = 0;

    public function __construct(string $table = '', string $prefix = '')
    {
        if (empty($prefix . $table)) {
            if (empty($this->table . $this->prefix)) {
                $table = get_class($this);
                $table = basename(str_replace('\\', '/', $table));
            } else {
                $table = $this->table;
                $prefix = $this->prefix;
            }

            if (empty($prefix) && function_exists('env')) {
                $prefix = env('DATABASE.PREFIX');

            }
        }
        $this->table($table, $prefix);
        $this->cursor = Db::table($prefix . $table);
        return $this;
    }

    public function table(string $table, string $prefix = '')
    {
        $this->tables[$prefix . $table] = new Table($table, $prefix);
    }

    /*--------------------------------------------------------------------------------------------------------------------*/
//常规查询
    public function select()
    {

        $this->_outFiledFull();      //导出字段
        $this->_join();             //添加聚合表
        $this->_clearParam();       //清理不存在参数
        $this->_decorate_prefix();  //修饰词前缀

        $this->cursor->page($this->page, $this->limit);


        $this->cursor->where($this->_decorate($this->cursor_where));
        $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
        $this->back = $this->cursor->select()->toArray();
        $this->_extra_join();
        return $this;
    }

    public function find()
    {

        $this->_outFiledFull();
        $this->_join();
        $this->_decorate_prefix();
        $this->cursor->where($this->_decorate($this->cursor_where));
        $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
        $this->back = $this->cursor->find();
        $this->_extra_join(true);
        return $this->back;

    }

    public function count()
    {
        $this->_outFiledFull();      //导出字段
        $this->_join();             //添加聚合表
        $this->_clearParam();       //清理不存在参数
        $this->_decorate_prefix();  //修饰词前缀
        $this->cursor->where($this->_decorate($this->cursor_where));
        return $this->cursor->page(0, 1)->count();
    }

    public function update(?array $array = [])
    {

        $this->setMaster();
        $table = reset($this->tables);
        $this->cursor->where($this->_decorate($this->cursor_where));
        if(isset($this->param[$table->getPrimary()]))
        $this->cursor->where([$table->getPrimary()=>$this->param[$table->getPrimary()]]);
        $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
        $this->_clearParam($array);
        $table->verifyFiled($array);

        $this->_field_comparison($array);
        return $this->cursor->update($array);
    }

    public function delete(?array $array = [])
    {
        $this->setMaster();
        $where = $this->_format(array_merge($array, $this->param));
        $this->cursor->where($where);
        $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
        $back = $this->cursor->delete();
        if(!$back)$this->error_message = '数据不存在';
        return $back?true:false;
    }

    public function insert()
    {

        $this->setMaster();
        $back = $this->cursor->inster($this->param);
        $this->Last_Insert_Id = $this->cursor->getLastInsID();
        return $back;
    }

    /**
     * 插入记录
     * @access public
     * @param array   $data         数据
     * @param boolean $getLastInsID 返回自增主键
     * @return integer|string
     */
    public function add(array $data = [], bool $getLastInsID = false)
    {
        $this->cursor->insert($data,$getLastInsID);
    }

    public function save(array $param = [])
    {

        $this->_outFiledFull();
        //判断参数
        if (empty($param) && empty($param = $this->param)){
            $this->error_message = '请求参数不能为空';
            return false;
        }

        //验证字段类型和长度
        if (!$this->filedVerifier($param))return false;


        $this->setMaster();
        $table = $this->choseTable();



        try {
            if (isset($param[$table->getPrimary()])) {
                $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
                return $this->cursor->where([$table->getPrimary() => [$param[$table->getPrimary()]]])->update($param);
            } else {
                //判断参数是否必填
                if(!$this->filedNotNullVerifier($param))return false;
                $result = $this->cursor->insert($param);
                $this->Last_Insert_Id = $this->cursor->getLastInsID();
            }
            return $result>0;
        }catch (\Exception $e){
            switch ($e->getCode()){
                case 10501:
                    $field = $table->getFieldUnique();
                    $display = $table->getFieldNameDisplay();
                    $show = [];
                    foreach ($field as $key=>$value){
                        if(isset($display[$value]))$show[]=$display[$value];
                        else $show[] = $value;
                    }
                    $back = implode('、',$show);
                    $this->error_message = $back.'不可重复';
                    
            }
        }
        return false;
    }
    /**
     * 得到某个列的数组
     * @access public
     * @param string|array $field 字段名 多个字段用逗号分隔
     * @param string $key   索引
     * @return array
     */
    public function column($field, string $key = '')
    {
        return $this->cursor->column($field,$key);
    }

    public function has():bool
    {
        $this->_outFiledFull();      //导出字段
        $this->_join();             //添加聚合表
        $this->_clearParam();       //清理不存在参数
        $this->_decorate_prefix();  //修饰词前缀
        $this->cursor->whereOr($this->_decorate($this->cursor_where_or));
        $this->cursor->where($this->_decorate($this->cursor_where));
        return $this->cursor->count()>0;
    }



    /*--------------------------------------------------------------------------------------------------------------------*/
//语句修饰方法
    /**
     * 指定查询字段
     * @access public
     * @param mixed $field 字段信息
     * @return $this
     */
    public function field($field){
        $this->cursor->field($field);
        return $this;
    }

    /**
     * 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])
     * @access public
     * @param string|array|Raw $field 排序字段
     * @param string $order 排序
     * @return $this
     */
    public function order($field, string $order = '')
    {
        $this->cursor->order($field, $order);
        return $this;
    }

    public function where(?array $array)
    {

		if(empty($this->full_field))$this->_outFiledFull();
        $filed_full = [];
        foreach ($this->full_field as $key =>$value){
            $filed_full[] = "{$value}.{$key}";
        }
        foreach ($array as $key=>$value){
            if(!is_array($value)&&!is_numeric($key)&&isset($this->full_field[$key])){
                $array["{$this->full_field[$key]}.{$key}"] = $value;
                unset($array[$key]);
            }else if(is_array($value)){
                if(!in_array($value[0],$filed_full)&&!isset($this->full_field[$value[0]])){
                    unset($array[$key]);
                }
            }
        }
        $this->_clearParam();
        $this->cursor->where($array);
        $this->cursor_where = $array;
        return $this;
    }

    /**
     * 指定OR查询条件
     * @access public
     * @param mixed $field     查询字段
     * @return $this
     */
    public function whereOr($field){
        foreach ($field as $key =>$item){
            if(is_array($item)){
                $this->cursor_where_or[] = $field;
            }else {
                $this->cursor_where_or[] = [$key,'=',$item];
            }
        }
        
        return $this;
    }

    /**
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * @param int $page
     */
    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return array|int
     */
    public function getLastInsertId()
    {
        return $this->Last_Insert_Id;
    }









    /*--------------------------------------------------------------------------------------------------------------------*/
//聚合查询

    public function ploy(string $table, string $prefix, string $contact_filed, string $join_type = 'LEFT', bool $master = true):Table
    {
        $this->_ploy[$prefix . $table] = [$contact_filed, $join_type, $master];
        !isset($this->tables[$prefix . $table]) && $this->tables[$prefix . $table] = new Table($table, $prefix);
        return $this->tables[$prefix . $table];
    }

    public function extra(string $table, string $prefix, $contact_filed):Table
    {
        $this->_extra[$prefix . $table] = [$contact_filed, true];
        !isset($this->tables[$prefix . $table]) && $this->tables[$prefix . $table] = new Table($table, $prefix);
        return $this->tables[$prefix . $table];
    }



    /*--------------------------------------------------------------------------------------------------------------------*/
//工具方法
    public function autoParam(?array $array = [])
    {
        if (isset($array['page'])) {
            $this->page = $array['page'];
            unset($array['page']);
        }
        if (isset($array['limit'])) {
            $this->limit = $array['limit'];
            unset($array['limit']);
        }
        if (isset($array['size'])) {
            $this->limit = $array['size'];
            unset($array['size']);
        }
        $this->param = $array;
        $this->cursor_where = $array;

    }

    public function error()
    {
        return $this->error_message;
    }

    public function display(array $filed, string $table = '')
    {
        if (isset($this->tables[$table])) {
            $table = $this->tables[$table];
        } else {
            $table = reset($this->tables);
        }
        $table->display($filed);
        return $this;

    }

    public function filter(array $filed, string $table = '')
    {
        if (isset($this->tables[$table])) {
            $table = $this->tables[$table];
        } else {
            $table = reset($this->tables);
        }
        $table->filter($filed);
        return $this;
    }

    public function like(array $array)
    {
        $this->cursor_like = $array;
        return $this;
    }

    public function in(array $array)
    {
        $this->cursor_in = $array;
        return $this;
    }

    public function setMaster(string $table = '')
    {

        if (isset($this->tables[$table])) {
            $table = $this->tables[$table];
        } else {
            $table = reset($this->tables);
        }
        $this->cursor = Db::table($table->getTable());
    }

    public function choseTable(?string $table = ''): Table
    {
        if (isset($this->tables[$table])) {
            return $this->tables[$table];
        } else {
            return reset($this->tables);
        }
    }

    /**
     * @return array
     */
    public function getBack(): array
    {

        return $this->back??[];
    }

    /**
     * 清空查询条件
     */
    public function clearWhere(){
        $this->cursor_where = [];
        $this->cursor_where_or = [];
        return $this;
    }


    /*--------------------------------------------------------------------------------------------------------------------*/
//辅助方法

    /**
     * 修饰词：in,like等
     */
    protected function _decorate(array $array = [])
    {
        foreach ($array as $k => $v) {
            if (in_array($k, $this->cursor_like) && isset($this->full_field[$k])) {
                $array[] = [$this->full_field[$k] . '.' . $k, 'LIKE', "%{$v}%"];
                unset($array[$k]);
            } else if (in_array($k, $this->cursor_in) && isset($this->full_field[$k])) {
                $array[] = [$this->full_field[$k] . '.' . $k, 'IN', is_array($v) ? implode(',', $v) : $v];
                unset($array[$this->full_field[$k] . '.' . $k]);
            } else if (isset($this->full_field[$k])) {
                $array[] = [$this->full_field[$k] . '.' . $k, '=', $v];
            }
        }

        return $this->_restrict_prefix($array);
    }

    /**
     * 加前缀
     */
    protected function _decorate_prefix()
    {
        foreach ($this->cursor_where as $k => $v) {
            if(is_array($v)){
                $this->cursor_where[$k][0] = isset($this->full_field[$v[0]])?($this->full_field[$v[0]].'.'.$v[0]):$v[0];
            }else if (is_string($k) && !is_numeric($k)&&isset($this->full_field[$k])){
                $this->cursor_where["{$this->full_field[$k]}.{$k}"] = $v;
                unset($this->cursor_where[$k]);
            }
        }

    }

    /**
     * 条件字段添加表限定前缀
     * @param array $array
     */
    protected function _restrict_prefix(array $array)
    {
        if(empty($this->full_field))$this->_outFiledFull();
        $filed_full = [];
        foreach ($this->full_field as $key =>$value){
            $filed_full[] = "{$value}.{$key}";
        }
        foreach ($array as $key=>$value){
            if(!is_array($value)&&!is_numeric($key)&&isset($this->full_field[$key])){
                $array["{$this->full_field[$key]}.{$key}"] = $value;
                unset($array[$key]);
            }else if(is_array($value)&& isset($this->full_field[$value[0]]) ){
                $array[$key][0] = $this->full_field[$value[0]].'.'.$value[0];
            }
        }
        return $array;
    }


    //导出字段
    protected function outFiled(): array
    {
        $table = reset($this->tables);

        $back = $table->outFiled(false);
        foreach ($this->_ploy as $k => $v) {
            $back = array_merge($back, $this->tables[$k]->outFiled());
        }
        return array_values($back);
    }

    //导出所有查询字段
    protected function _outFiledFull()
    {
        foreach (array_reverse($this->tables) as $key => $table) {
            $tmp = array_fill_keys($table->getFieldFull(), $table->getTable());

            $check_field = &$this->check_field;
            array_map(function($val)use($tmp,&$check_field){
                $check_field[] = $val;
                $check_field[] = $tmp[$val].'.'.$val;
            },$table->getFieldFull());
            $this->full_field = array_merge($this->full_field, array_fill_keys($table->getFieldFull(), $table->getTable()));
        }

        $this->check_field = array_unique($this->check_field);
    }


    //
    protected function outCondition(): array
    {
        return [];
    }

    //
    protected function getTable(string $table = '', string $prefix = '')
    {
        return empty($prefix . $table) ? ($prefix . $table) : get_class($this);
    }

    protected function _join()
    {
        //导出主表字段

        $this->cursor->field($this->outFiled());
        $table = reset($this->tables);
        foreach ($this->_ploy as $key => $value) {
            if (in_array($key, $this->cursor_join)) continue;
            $join_table = $this->tables[$key];
            if ($value[2]) {
                $condition = "{$table->getTable()}.{$value[0]}={$join_table->getTable()}.{$join_table->getPrimary()}";
            } else {
                $condition = "{$table->getTable()}.{$table->getPrimary()}={$join_table->getTable()}.{$value[0]}";
            }
            $this->cursor_join[] = $key;
            $this->cursor->join($key, $condition, $value[1]);
        }
    }


    protected function _extra_join(bool $flag_find = false)
    {
        $table = reset($this->tables);

        foreach ($this->_extra as $key => $value) {
            if(is_array($value[0])){
                $keys = array_keys($value[0]);
                $vals = array_values($value[0]);
                if($value[1])$back = [$this->back];
                $condition = array_column($back, reset($keys));
                $searchFiled = reset($vals);
                
//                $keys = array_keys($value[0]);
//                $vals = array_values($value[0]);
//                $condition = array_column($this->back, $key);
//                dd($this->back);
//                $searchFiled = reset($keys);
            }else if ($value[1]) {

                $condition = $flag_find ? $this->back[$value[0]] : array_column($this->back, $value[0]);
                $searchFiled = $this->tables[$key]->getPrimary();
            } else {
                $condition = $flag_find ? $this->back[$value[0]] : array_column($this->back, $table->getPrimary());
                $searchFiled = $table->getPrimary();
            }
            $key_table = $this->choseTable($key);
            $fields = implode(',',array_values($key_table->outFiled()));
            $fields = empty($fields)?'*':$fields;
            $extra_arr = Db::table($key)
                ->where([[$searchFiled, 'IN', is_array($condition) ? implode(',', $condition) : $condition]])
//                ->fetchSql()
                ->column($fields.",{$key_table->getPrimary()}", $searchFiled);

            $key = empty($key_table->getExtraAlias())?$key:$key_table->getExtraAlias();

            if ($value[1]) {
                if ($flag_find) {
                    $keys = is_array($value[0])?array_keys($value[0])[0]:$value;
                    if(!$this->back)return;
                    $consult = $this->back[$keys];
                    $consult = explode(',', $consult);
                    $consult = array_flip($consult);

                    $this->back[$key] = array_merge(array_intersect_key($extra_arr, $consult), []);

                } else {
                    foreach ($this->back as $_key => $_val) {
                        $consult = $_val[$value[0]];                //获取对应字段参数
                        $consult = explode(',', $consult);  //可能存在多个值，进行分割
                        $consult = array_flip($consult);             //键值对调，进行数组合并
                        $this->back[$_key][$key] = array_merge(array_intersect_key($extra_arr, $consult), []);
                    }
                }


            }
        }

    }

    /**
     * @param array $array 格式化参数
     */
    protected function _format(array $array)
    {
        $arr = [];
        foreach ($array as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $arr[] = [$key, '=', $val];
            } else {
                $arr[] = $val;
            }

        }
        return $arr;
    }

    /**
     * 清理参数
     */
    protected function _clearParam(?array $param = [])
    {
        $param = empty($param)?$this->param:$param;

        foreach ($param as $key => $val){
            if(is_string($val) && !in_array($key,$this->check_field)){
                unset($param[$key]);
            }else if(is_array($val)&&!in_array(reset($val),$this->check_field)){
                unset($param[$key]);
            }
        }
        $this->cursor_where = $param;

    }
    //将字段比对导出
    protected function _field_comparison(array &$field)
    {
        foreach ($field as $key =>$val){
            if(!isset($this->full_field[$key]))unset($field[$key]);
        }
    }

    /**
     * 字段数据验证
     */
    protected function filedVerifier(array &$param = null)
    {
        foreach ($param as $key => $value) {
            if (isset($this->full_field[$key])) {
                if (($this->choseTable($this->full_field[$key])->filedVerifier($key, $value)) == false) {
                    $this->error_message = $this->choseTable($this->full_field[$key])->getErrorMessage();
                    return false;
                }
            } else {

                unset($param[$key]);
            }

        }
        return true;

    }


    /**
     * 验证非空字段
     * @param array|null $param
     */
    protected function filedNotNullVerifier(array &$param = null)
    {


        if($this->choseTable()->verifyNotNullFiled(array_keys($param))){

            return true;
        }else{

            $this->error_message = $this->choseTable()->getErrorMessage();
            return false;
        }


    }





















}