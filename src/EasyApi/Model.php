<?php


namespace EasyApi;


use think\db\BaseQuery;
use think\db\Raw;
use think\facade\Db;

class Model
{
    protected string $table     = '';
    protected string $prefix    = '';

    protected array         $tables         = [];   //表对象
    protected int           $page           = 1;    //页码
    protected int           $limit          = 20;   //条数
    protected     $cursor         = null; //游标对象
    protected ?array        $back           = [];   //返回对象
    protected array         $_ploy          = [];   //聚合参数
    protected array         $_extra         = [];   //扩展参数

    protected array         $full_field     = [];
    protected array         $cursor_like    = [];
    protected array         $cursor_in      = [];
    protected array         $cursor_where   = [];
    protected array         $cursor_join    = [];
    protected string        $error_message  = '';
    protected array         $param          = [];

    public function __construct(string $table = '',string $prefix = '')
    {
        if(empty($prefix.$table)){
            if(empty($this->table.$this->prefix)){
                $table  = get_class($this);
                $table  = basename(str_replace('\\', '/', $table));
            }else{
                $table  = $this->table;
                $prefix = $this->prefix;
            }

            if(empty($prefix) && function_exists('env')){
                $prefix = env('DATABASE.PREFIX');

            }
        }
        $this->table($table,$prefix);
        $this->cursor = Db::table($prefix.$table);
        return $this;
    }

    public function table(string $table,string $prefix = '')
    {
        $this->tables[$prefix.$table] = new Table($table,$prefix);
    }

/*--------------------------------------------------------------------------------------------------------------------*/
//常规查询
    public function select()
    {

        $this->outFiledFull();
        $this->_join();
        $this->_decorate();
        $this->_decorate_prefix();
        $this->cursor->page($this->page,$this->limit);

        $this->cursor->where($this->cursor_where);
        $this->back = $this->cursor->select()->toArray();
        $this->_extra_join();
        return $this;
    }
    public function find()
    {
        $this->outFiledFull();
        $this->_join();
        $this->_decorate();
        $this->_decorate_prefix();
        $this->cursor->where($this->cursor_where);
        $this->back = $this->cursor->find();
        $this->_extra_join(true);
        return $this->back;

    }

    public function count(){
        return $this->cursor->page(0,1)->count();
    }
    public function update(?array $array = [])
    {
        $table = reset($this->tables);
        $table->verifyFiled();
        $this->cursor->update($array);
    }

    public function delete(?array $array = [])
    {
        $this->setMaster();
        $where = $this->_format(array_merge($array,$this->param));
        $this->cursor->where($where);
        return $this->cursor->delete();
    }

    public function insert()
    {
        $this->setMaster();
        return $this->cursor->inster($this->param);
    }

    public function save(array $param = [])
    {

        $this->outFiledFull();
        //判断参数
        if(empty($param))$param = $this->param;


        if(!$this->filedVerifier($param)){
            dd($this->error_message);
            return false;
        }


        $this->setMaster();
        if(isset($this->param[$table->getPrimary()])){
            return $this->cursor->where([$table->getPrimary()=>[$this->param[$table->getPrimary()]]])->update($this->param);
        }else{


            $result = $this->cursor->insert($this->param);
            return ;
        }
    }



/*--------------------------------------------------------------------------------------------------------------------*/
//语句修饰方法

    /**
     * 指定排序 order('id','desc') 或者 order(['id'=>'desc','create_time'=>'desc'])
     * @access public
     * @param string|array|Raw $field 排序字段
     * @param string           $order 排序
     * @return $this
     */
    public function order($field, string $order = '')
    {
        $this->cursor->order($field,$order);
        return $this;
    }

    public function where(?array $array)
    {
        $this->cursor->where($array);
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





/*--------------------------------------------------------------------------------------------------------------------*/
//聚合查询

    public function ploy(string $table,string $prefix,string $contact_filed,string $join_type = 'LEFT',bool $master = true)
    {
        $this->_ploy[$prefix.$table] = [$contact_filed,$join_type,$master];
        !isset($this->tables[$prefix.$table])&& $this->tables[$prefix.$table] = new Table($table,$prefix);
        return $this->tables[$prefix.$table];
    }

    public function extra(string $table,string $prefix,string $contact_filed)
    {
        $this->_extra[$prefix.$table] = [$contact_filed,true];
        !isset($this->tables[$prefix.$table])&& $this->tables[$prefix.$table] = new Table($table,$prefix);
        return $this->tables[$prefix.$table];
    }



/*--------------------------------------------------------------------------------------------------------------------*/
//工具方法
    public function autoParam(?array $array = [])
    {
        if(isset($array['page'])){$this->page = $array['page']; unset($array['page']);}
        if(isset($array['limit'])){$this->limit = $array['limit']; unset($array['limit']);}
        if(isset($array['size'])){$this->limit = $array['size']; unset($array['size']);}
        $this->param = $array;
        $this->cursor_where = $array;

    }
    public function error()
    {
        return $this->error_message;
    }

    public function display(array $filed,string $table = '')
    {
        if(isset($this->tables[$table])){
            $table = $this->tables[$table];
        }else{
            $table = reset($this->tables);
        }
        $table->display($filed);
        return $this;

    }
    public function filter(array $filed,string $table = '')
    {
        if(isset($this->tables[$table])){
            $table = $this->tables[$table];
        }else{
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
    public function in(array $array){
        $this->cursor_in = $array;
        return $this;
    }

    public function setMaster(string $table = ''){

        if(isset($this->tables[$table])){
            $table = $this->tables[$table];
        }else{
            $table = reset($this->tables);
        }
        $this->cursor = Db::table($table->getTable());
    }

    public function choseTable(?string $table = ''):Table
    {
        if(isset($this->tables[$table])){
            return $this->tables[$table];
        }else{
            return reset($this->tables);
        }
    }

    /**
     * @return array
     */
    public function getBack(): array
    {
        return $this->back;
    }


/*--------------------------------------------------------------------------------------------------------------------*/
//辅助方法

    /**
     * 修饰词：in,like等
     */
    protected function _decorate(){
        if(empty($this->cursor_like) && empty($this->cursor_in))return;
        foreach ($this->cursor_where as $k=>$v){
            //这里将过滤参数值为空得参数
            if(empty($v)){

                unset($this->cursor_where[$k]);
                continue;
            }
            if(in_array($k,$this->cursor_like) && isset($this->full_field[$k])){

                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'LIKE',"%{$v}%"];
                unset($this->cursor_where[$this->full_field[$k].'.'.$k]);

            }else if(in_array($k,$this->cursor_in) && isset($this->full_field[$k])){
                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'IN',is_array($v)?implode(',',$v):$v];
                unset($this->cursor_where[$this->full_field[$k].'.'.$k]);
            }else if(isset($this->full_field[$k])){
                $this->cursor_where[] = [$this->full_field[$k].'.'.$k,'=',$v];
            }
            unset($this->cursor_where[$k]);
        }

    }

    /**
     * 加前缀
     */
    protected function _decorate_prefix(){
        foreach ($this->cursor_where as $k =>$v){
            if(isset($this->full_field[$k])){
                $this->cursor_where[$this->full_field[$k].'.'.$k] = $v;
                unset($this->cursor_where[$k]);
            }
        }
    }


    //导出字段
    protected function outFiled():array
    {
        $table = reset($this->tables);

        $back = $table->outFiled(false);
        foreach ($this->_ploy as $k=>$v){
            $back = array_merge($back,$this->tables[$k]->outFiled());
        }
        return array_values($back);
    }

    //导出所有查询字段
    protected function outFiledFull(){
        foreach (array_reverse($this->tables) as $key => $table){
            $this->full_field = array_merge($this->full_field,array_fill_keys($table->getFieldFull(),$table->getTable()));
        }
    }


    //
    protected function outCondition():array
    {
        return [];
    }

    //
    protected function getTable(string $table = '',string $prefix = '')
    {
        return empty($prefix.$table)?($prefix.$table):get_class($this);
    }

    protected function _join(){
        //导出主表字段

        $this->cursor->field($this->outFiled());
        $table      = reset($this->tables);
        foreach ($this->_ploy as $key=>$value){
            if(in_array($key,$this->cursor_join))continue;
            $join_table = $this->tables[$key];
            if($value[2]){
                $condition = "{$table->getTable()}.{$value[0]}={$join_table->getTable()}.{$join_table->getPrimary()}";
            }else{
                $condition = "{$table->getTable()}.{$table->getPrimary()}={$join_table->getTable()}.{$value[0]}";
            }
            $this->cursor_join[] = $key;
            $this->cursor->join($key,$condition,$value[1]);
        }
    }


    protected function _extra_join(bool $flag_find = false){
        $table      = reset($this->tables);
        foreach ($this->_extra as $key=>$value){
            if($value[1]){

                $condition = $flag_find?$this->back[$value[0]]:array_column($this->back,$value[0]);
                $searchFiled = $this->tables[$key]->getPrimary();
            }else{
                $condition = $flag_find?$this->back[$value[0]]:array_column($this->back,$table->getPrimary());
                $searchFiled = $table->getPrimary();
            }

            $extra_arr = Db::table($key)
                      -> where([[$searchFiled,'IN',is_array($condition)?implode(',',$condition):$condition]])
                      -> column('*',$searchFiled);

            if($value[1]){
                if($flag_find){
                    $consult = $this->back[$value[0]];
                    $consult = explode(',',$consult);
                    $consult = array_flip($consult);
                    $this->back[$key] = array_merge(array_intersect_key($extra_arr,$consult),[]);
                }else{
                    foreach ($this->back as $_key =>$_val){
                        $consult = $_val[$value[0]];                //获取对应字段参数
                        $consult = explode(',',$consult);  //可能存在多个值，进行分割
                        $consult = array_flip($consult);           //键值对调，进行数组合并
                        $this->back[$_key][$key] = array_merge(array_intersect_key($extra_arr,$consult),[]);
                    }
                }


            }
        }
    }

    /**
     * @param array $array 格式化参数
     */
    protected function _format(array $array){
        $arr = [];
        foreach ($array as $key=>$val)
        {
            if(is_string($val) || is_numeric($val)){
                $arr[] = [$key,'=',$val];
            }else{
                $arr[] = $val;
            }

        }
        return $arr;
    }

    /**
     * 字段数据验证
     */
    protected function filedVerifier(&$param = null)
    {



        foreach ($param as $key =>$value){
            if(isset($this->full_field[$key])){
                
                if(($this->choseTable($this->full_field[$key])->filedVerifier($key,$value))===true){

                }else{
                    $this->error_message  = $this->choseTable($this->full_field[$key])->getErrorMessage();
                    return false;
                }


            }else{

                unset($param[$key]);
            }
        }
        return true;
    }












}