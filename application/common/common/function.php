<?php
// TwoThink常量定义 
define('TWOTHINK_VERSION','1.0' );
define('TWOTHINK_ADDON_PATH', ROOT_PATH. '/addons/' );
/**
 * 系统公共库文件
 * 主要定义系统公共函数库
 */
/**
 * 检测用户是否登录
 * @return integer 0-未登录，大于0-当前登录用户ID */
function is_login(){
    $user = session('user_auth');
    if (empty($user)) {
        return 0;
    } else {
        return session('user_auth_sign') == data_auth_sign($user) ? $user['uid'] : 0;
    }
}
/**
 * 检测当前用户是否为管理员
 * @return boolean true-管理员，false-非管理员 */
function is_administrator($uid = null){ 
    $uid = is_null($uid) ? is_login() : $uid;
    return $uid && (intval($uid) === config('user_administrator'));
}
/**
 * 字符串转换为数组，主要用于把分隔符调整到第二个参数
 * @param  string $str  要分割的字符串
 * @param  string $glue 分割符
 * @return array
 */
function str2arr($str, $glue = ','){
    return explode($glue, $str);
}
/**
 * 数组转换为字符串，主要用于把分隔符调整到第二个参数
 * @param  array  $arr  要连接的数组
 * @param  string $glue 分割符
 * @return string
 */
function arr2str($arr, $glue = ','){
    return implode($glue, $arr);
}
/**
 * 字符串截取，支持中文和其他编码
 * @static
 * @access public
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start, $length, $charset="utf-8", $suffix=true) {
    if(function_exists("mb_substr"))
        $slice = mb_substr($str, $start, $length, $charset);
    elseif(function_exists('iconv_substr')) {
        $slice = iconv_substr($str,$start,$length,$charset);
        if(false === $slice) {
            $slice = '';
        }
    }else{
        $re['utf-8']   = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk']    = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5']   = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("",array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice : $slice.'...';
}

/**
 * 系统加密方法
 * @param string $data 要加密的字符串
 * @param string $key  加密密钥
 * @param int $expire  过期时间 单位 秒
 * @return string
 */
function think_encrypt($data, $key = '', $expire = 0) {
    $key  = md5(empty($key) ? config('data_auth_key') : $key);
    $data = base64_encode($data);
    $x    = 0;
    $len  = strlen($data);
    $l    = strlen($key);
    $char = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    $str = sprintf('%010d', $expire ? $expire + time():0);
    for ($i = 0; $i < $len; $i++) {
        $str .= chr(ord(substr($data, $i, 1)) + (ord(substr($char, $i, 1)))%256);
    }
    return str_replace(array('+','/','='),array('-','_',''),base64_encode($str));
}
/**
 * 系统解密方法
 * @param  string $data 要解密的字符串 （必须是think_encrypt方法加密的字符串）
 * @param  string $key  加密密钥
 * @return string
*/
function think_decrypt($data, $key = ''){
    $key    = md5(empty($key) ? config('data_auth_key') : $key);
    $data   = str_replace(array('-','_'),array('+','/'),$data);
    $mod4   = strlen($data) % 4;
    if ($mod4) {
       $data .= substr('====', $mod4);
    }
    $data   = base64_decode($data);
    $expire = substr($data,0,10);
    $data   = substr($data,10);
    if($expire > 0 && $expire < time()) {
        return '';
    }
    $x      = 0;
    $len    = strlen($data);
    $l      = strlen($key);
    $char   = $str = '';
    for ($i = 0; $i < $len; $i++) {
        if ($x == $l) $x = 0;
        $char .= substr($key, $x, 1);
        $x++;
    }
    for ($i = 0; $i < $len; $i++) {
        if (ord(substr($data, $i, 1))<ord(substr($char, $i, 1))) {
            $str .= chr((ord(substr($data, $i, 1)) + 256) - ord(substr($char, $i, 1)));
        }else{
            $str .= chr(ord(substr($data, $i, 1)) - ord(substr($char, $i, 1)));
        }
    }
    return base64_decode($str);
}
/**
 * 数据签名认证
 * @param  array  $data 被认证的数据
 * @return string       签名
 */
function data_auth_sign($data) {
    //数据类型检测
    if(!is_array($data)){
        $data = (array)$data;
    }
    ksort($data); //排序
    $code = http_build_query($data); //url编码并生成query字符串
    $sign = sha1($code); //生成签名
    return $sign;
}
/**
* 对查询结果集进行排序
* @access public
* @param array $list 查询结果
* @param string $field 排序的字段名
* @param array $sortby 排序类型
* asc正向排序 desc逆向排序 nat自然排序
* @return array
*/
function list_sort_by($list,$field, $sortby='asc') {
   if(is_array($list)){
       $refer = $resultSet = array();
       foreach ($list as $i => $data)
           $refer[$i] = &$data[$field];
       switch ($sortby) {
           case 'asc': // 正向排序
                asort($refer);
                break;
           case 'desc':// 逆向排序
                arsort($refer);
                break;
           case 'nat': // 自然排序
                natcasesort($refer);
                break;
       }
       foreach ( $refer as $key=> $val)
           $resultSet[] = &$list[$key];
       return $resultSet;
   }
   return false;
}
/**
 * 把返回的数据集转换成Tree
 * @param array $list 要转换的数据集
 * @param string $pid parent标记字段
 * @param string $level level标记字段
 * @return array
*/
function list_to_tree($list, $pk='id', $pid = 'pid', $child = '_child', $root = 0) {
    // 创建Tree
    $tree = array(); 
    if(is_array($list)) {
        // 创建基于主键的数组引用
        $refer = array();
        foreach ($list as $key => $data) {
            $refer[$data[$pk]] =& $list[$key];
        }
        foreach ($list as $key => $data) {
            // 判断是否存在parent
            $parentId =  $data[$pid];
            if ($root == $parentId) {
                $tree[$data['id']] =& $list[$key];
            }else{
                if (isset($refer[$parentId])) {
                    $parent =& $refer[$parentId];
                    $parent[$child][$data['id']] =& $list[$key];
                }
            }
        }
    }
    return $tree;
}
/**
 * 将list_to_tree的树还原成列表
 * @param  array $tree  原来的树
 * @param  string $child 孩子节点的键
 * @param  string $order 排序显示的键，一般是主键 升序排列
 * @param  array  $list  过渡用的中间数组，
 * @return array        返回排过序的列表数组
 */
function tree_to_list($tree, $child = '_child', $order='id', &$list = array()){
    if(is_array($tree)) {
        foreach ($tree as $key => $value) {
            $reffer = $value;
            if(isset($reffer[$child])){
                unset($reffer[$child]);
                tree_to_list($value[$child], $child, $order, $list);
            }
            $list[] = $reffer;
        }
        $list = list_sort_by($list, $order, $sortby='asc');
    }
    return $list;
}
/**
 * 格式化字节大小
 * @param  number $size      字节数
 * @param  string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '') {
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++) $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}
/**
 * 设置跳转页面URL
 * 使用函数再次封装，方便以后选择不同的存储方式（目前使用cookie存储）
 */
function set_redirect_url($url){
    cookie('redirect_url', $url);
}
/**
 * 获取跳转页面URL
 * @return string 跳转页URL
 */
function get_redirect_url(){
    $url = cookie('redirect_url');
    return empty($url) ? __APP__ : $url;
} 
/**
 * 获取插件的模型名
 * @param strng $name 插件名
 * * @param strng $model 模型名
 */
function get_addon_model($name,$model){
	$class = "addons\\{$name}\model\\{$model}";
	return $class;
}
//根据数据库的内容自动生成url
function mkurl($param=[]){
    if(empty($param)){
        return '';
    }else{
    	if($param['url']){
    		$url='http://'.$_SERVER['HTTP_HOST'];
    		$urls=explode('.',$url);
    		if($urls[1]=='b'||$urls[1]=='j'){
    			if($urls[2]!='m') $url=$urls[0].'.'.$urls[1].'.m.'.$urls[2].'.'.$urls[3];
    		}else{
    			if($urls[0]!='m'){
    			    if($urls[1]!='m') $url=$urls[0].'.m.'.$urls[1].'.'.$urls[2];
    			}
    		}
    		if(!file_exists(substr($param['url'],1))){
    			return 'http://yun1.img.ljk.cc'.$param['url'] ;
    		}else{
    			return $url.$param['url'];
    		}
    	}elseif($param['id']){
    		$url='http://yun1.img.ljk.cc/index/index/index/id/'.$param['id'];
    		$html = file_get_contents($url);
    		return $html;
    	}else {
    		return config('upload_path').'/'.$param['savepath'].'/'.$param['savename'].'.'.$param['ext'];
    	}
    }
}

//根据数据库的内容自动生成url
//根据数据库的内容自动生成url
function mkgoodsimgurl($param=[]){
	if(empty($param)){
		return '';
	}else{
            if($param['url']){
                $parseUrl = parse_url($param['url']);
                $path = $parseUrl['path'];
                if(file_exists(substr($path,1))){			
                    return $path;
                }else{
                    return 'http://sx.img.ljk.cc'.$path ;
                }
            }
	}
}

/**
 * 插件显示内容里生成访问插件的url
 * @param string $url url
 * @param array $param 参数 */
function addons_url($url, $param = [])
{
    $url = parse_url($url);
    $case = config('url_convert');
    $addons = $case ? \think\Loader::parseName($url['scheme']) : $url['scheme'];
    $controller = $case ? \think\Loader::parseName($url['host']) : $url['host'];
    $action = trim($case ? strtolower($url['path']) : $url['path'], '/');
    /* 解析URL带的参数 */
    if (isset($url['query'])) {
        parse_str($url['query'], $query);
        $param = array_merge($query, $param);
    }
    // 生成插件链接新规则
    $actions = "{$addons}-{$controller}-{$action}";
    return url("addons/execute/{$actions}", $param);
}
/**
 * 时间戳格式化
 * @param int $time
 * @return string 完整的时间显示
 */
function time_format($time = NULL,$format='Y-m-d H:i'){
    $time = $time === NULL ? NOW_TIME : intval($time);
    return date($format, $time);
}
/**
 * 根据用户ID获取用户名
 * @param  integer $uid 用户ID
 * @return string       用户名
 */
function get_username($uid = 0){
    static $list;
    if(!($uid && is_numeric($uid))){ //获取当前登录用户名
        return session('user_auth.username');
    }
    /* 获取缓存数据 */
    if(empty($list)){
        $list = cache('sys_active_user_list');
    } 
    /* 查找用户信息 */
    $key = "u{$uid}";
    if(isset($list[$key])){ //已缓存，直接使用
        $name = $list[$key];
    } else { //调用接口获取用户信息
        $User = new app\user\api\UserApi();
        $info = $User->info($uid); 
        if($info && isset($info[1])){
            $name = $list[$key] = $info[1];
            /* 缓存用户 */
            $count = count($list);
            $max   = config('user_max_cache');
            while ($count-- > $max) {
                array_shift($list);
            }
            cache('sys_active_user_list', $list);
        } else {
            $name = '';
        }
    } 
    return $name;
}
/**
 * 根据用户ID获取用户昵称
 * @param  integer $uid 用户ID
 * @return string       用户昵称
 */
function get_nickname($uid = 0){
    static $list;
    if(!($uid && is_numeric($uid))){ //获取当前登录用户名
        return session('user_auth.username');
    }
    /* 获取缓存数据 */
    if(empty($list)){
        $list = cache('sys_user_nickname_list');
    }
    /* 查找用户信息 */
    $key = "u{$uid}";
    if(isset($list[$key])){ //已缓存，直接使用
        $name = $list[$key];
    } else { //调用接口获取用户信息
        $info = db('Member')->field('nickname')->find($uid); 
        if($info !== false && $info['nickname'] ){
            $nickname = $info['nickname'];
            $name = $list[$key] = $nickname;
            /* 缓存用户 */
            $count = count($list);
            $max   = config('USER_MAX_CACHE');
            while ($count-- > $max) {
                array_shift($list);
            }
            cache('sys_user_nickname_list', $list);
        } else {
            $name = '';
        }
    }
    return $name;
}
/**
 * 获取分类信息并缓存分类
 * @param  integer $id    分类ID
 * @param  string  $field 要获取的字段名
 * @return string         分类信息
 * @author   艺品网络
 */
function get_category($id = null, $field = null){
    static $list;  
    /* 非法分类ID */
    if(!empty($id)){
    	if(!is_numeric($id))
    		return false;
    } 
    /* 读取缓存数据 */
    if(empty($list)){
        $list = cache('sys_category_list');
    }
    /* 获取分类名称 */
    if(empty($list)){
    	$data = db('document_category')->select();
    	foreach ($data as $key => $value) {
    		$list[$value['id']] = $value;
    	}
    	cache('sys_category_list',$list);
    } 
    if(empty($id)){
    	return $list;
    }else{
    	if(isset($list[$id])){
    		if(1 != $list[$id]['status']){ //不存在分类，或分类被禁用
    			return '';
    		}
    		return is_null($field) ? $list[$id] : $list[$id][$field];
    	}
    	return false;
    } 
}
/**
 * 获取分类树tree
 * @param  string  是否获取同级分类即同pid数据(true $id=pid)
 * @param  integer $id    分类ID
 * @param  string  $field 要获取的字段名
 * @param  string  $sor 排序字段
 * @param  string  $sortby 排序方式
 * @return string         分类信息
 * @author   艺品网络
 */
function get_category_tree($child=false,$id = null, $field = null, $sor = 'id', $sortby = 'desc'){
	if($child){
		$child_id = $id;
		$id = null;
	}
	if(!$list = get_category($id,$field)){
		return false;
	}
	//进行排序
	if(empty($id))
		$data=list_sort_by($list,$sor,$sortby); 
	//转成tree
	if(!isset($list['id']))
		$list=list_to_tree($list);
	if($child)
		return $list[$child_id]['_child'];
	return $list;
}
/* 根据ID获取分类标识 */
function get_category_name($id){
    return get_category($id, 'name');
}
/* 根据ID获取分类名称 */
function get_category_title($id){
    return get_category($id, 'title');
}
/**
 * 获取顶级模型信息
 */
function get_top_model($model_id=null){
    $map   = array('status' => 1, 'extend' => 0);
    if(!is_null($model_id)){
        $map['id']  =   array('neq',$model_id);
    }
    $model = db('Model')->where($map)->field(true)->select();
    foreach ($model as $value) {
        $list[$value['id']] = $value;
    }
    return $list;
}
/**
 * 获取文档模型信息
 * @param  integer $id    模型ID
 * @param  string  $field 模型字段
 * @return array
 */
function get_document_model($id = null, $field = null){
    static $list;
    /* 非法分类ID */
    if(!(is_numeric($id) || is_null($id))){
        return '';
    }
    /* 读取缓存数据 */
    if(empty($list)){
        $list = cache('document_model_list');
    } 
    /* 获取模型名称 */
    if(empty($list)){
//         $map   = array('status' => 1, 'extend' => 1);
    	$map   = array('status' => 1);
        $model = \think\Db::name('Model')->where($map)->field(true)->select(); 
        foreach ($model as $value) {
            $list[$value['id']] = $value;
        }
        cache('document_model_list', $list); //更新缓存
    }  
    /* 根据条件返回数据 */
    if(is_null($id)){
        return $list;
    } elseif(is_null($field)){
        return $list[$id];
    } else {
        return $list[$id][$field];
    }
}
/**
 * 解析UBB数据
 * @param string $data UBB字符串
 * @return string 解析为HTML的数据
 */
function ubb($data){
    //TODO: 待完善，目前返回原始数据
    return $data;
}
/**
 * 记录行为日志，并执行该行为的规则
 * @param string $action 行为标识
 * @param string $model 触发行为的模型名
 * @param int $record_id 触发行为的记录id
 * @param int $user_id 执行行为的用户id
 * @return boolean
 */
function action_log($action = null, $model = null, $record_id = null, $user_id = null){
    //参数检查
    if(empty($action) || empty($model) || empty($record_id)){
        return '参数不能为空';
    }
    if(empty($user_id)){
        $user_id = is_login();
    } 
    //查询行为,判断是否执行
    $action_info = \think\Db::name('Action')->getByName($action);
 
    if($action_info['status'] != 1){
        return '该行为被禁用或删除';
    }
    $now_time=time();
    //插入行为日志
    $data['action_id']      =   $action_info['id'];
    $data['user_id']        =   $user_id;
    $data['action_ip']      =   ip2long(get_client_ip());
    $data['model']          =   $model;
    $data['record_id']      =   $record_id;
    $data['create_time']    =   $now_time;
 
    //解析日志规则,生成日志备注
    if(!empty($action_info['log'])){
        if(preg_match_all('/\[(\S+?)\]/', $action_info['log'], $match)){
            $log['user']    =   $user_id;
            $log['record']  =   $record_id;
            $log['model']   =   $model;
            $log['time']    =   $now_time;
            $log['data']    =   array('user'=>$user_id,'model'=>$model,'record'=>$record_id,'time'=>$now_time);
            foreach ($match[1] as $value){
                $param = explode('|', $value);
                if(isset($param[1])){
                    $replace[] = call_user_func($param[1],$log[$param[0]]);
                }else{
                    $replace[] = $log[$param[0]];
                }
            }
            $data['remark'] =   str_replace($match[0], $replace, $action_info['log']);
        }else{
            $data['remark'] =   $action_info['log'];
        }
    }else{
        //未定义日志规则，记录操作url
        $data['remark']     =   '操作url：'.$_SERVER['REQUEST_URI'];
    }
    \think\Db::name('ActionLog')->insert($data);
    if(!empty($action_info['rule'])){
        //解析行为
        $rules = parse_action($action, $user_id);
        //执行行为
        $res = execute_action($rules, $action_info['id'], $user_id);
    }
}
/**
 * 解析行为规则
 * 规则定义  table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
 * 规则字段解释：table->要操作的数据表，不需要加表前缀；
 *              field->要操作的字段；
 *              condition->操作的条件，目前支持字符串，默认变量{$self}为执行行为的用户
 *              rule->对字段进行的具体操作，目前支持四则混合运算，如：1+score*2/2-3
 *              cycle->执行周期，单位（小时），表示$cycle小时内最多执行$max次
 *              max->单个周期内的最大执行次数（$cycle和$max必须同时定义，否则无效）
 * 单个行为后可加 ； 连接其他规则
 * @param string $action 行为id或者name
 * @param int $self 替换规则里的变量为执行用户的id
 * @return boolean|array: false解析出错 ， 成功返回规则数组
 */
function parse_action($action , $self){
    if(empty($action)){
        return false;
    }
    //参数支持id或者name
    if(is_numeric($action)){
        $map = array('id'=>$action);
    }else{
        $map = array('name'=>$action);
    }
    //查询行为信息
    $info = db('Action')->where($map)->find();
    if(!$info || $info['status'] != 1){
        return false;
    }
    //解析规则:table:$table|field:$field|condition:$condition|rule:$rule[|cycle:$cycle|max:$max][;......]
    $rules = $info['rule'];
    $rules = str_replace('{$self}', $self, $rules);
    $rules = explode(';', $rules);
    $return = array(); 
    foreach ($rules as $key=>&$rule){
        if(empty($rule)) 
            continue; 
        $rule = explode('|', $rule);
        foreach ($rule as $k=>$fields){
            $field = empty($fields) ? array() : explode(':', $fields);
            if(!empty($field)){
                $return[$key][$field[0]] = $field[1];
            }
        } 
        //cycle(检查周期)和max(周期内最大执行次数)必须同时存在，否则去掉这两个条件
        if(!array_key_exists('cycle', $return[$key]) || !array_key_exists('max', $return[$key])){
            unset($return[$key]['cycle'],$return[$key]['max']);
        } 
    }
    return $return;
}
/**
 * 执行行为
 * @param array $rules 解析后的规则数组
 * @param int $action_id 行为id
 * @param array $user_id 执行的用户id
 * @return boolean false 失败 ， true 成功
 */
function execute_action($rules = false, $action_id = null, $user_id = null){
    if(!$rules || empty($action_id) || empty($user_id)){
        return false;
    }
    $return = true;
    foreach ($rules as $rule){
        //检查执行周期
        $map = array('action_id'=>$action_id, 'user_id'=>$user_id);
        $map['create_time'] = array('gt', time() - intval($rule['cycle']) * 3600);
        $exec_count = db('ActionLog')->where($map)->count();
        if($exec_count > $rule['max']){
            continue;
        }
        //执行数据库操作
        $Model = db(ucfirst($rule['table']));
        $field = $rule['field'];
        $res = $Model->where($rule['condition'])->setField($field, array('exp', $rule['rule']));
        if(!$res){
            $return = false;
        }
    }
    return $return;
}
//基于数组创建目录和文件
function create_dir_or_files($files){ 
	if(is_dir($files[0]))
	    return false; 
    foreach ($files as $key => $value) {
        if(substr($value, -1) == '/'){
            mkdir($value);
        }else{
            @file_put_contents($value, '');
        }
    }
    return true;
}
if(!function_exists('array_column')){
    function array_column(array $input, $columnKey, $indexKey = null) {
        $result = array();
        if (null === $indexKey) {
            if (null === $columnKey) {
                $result = array_values($input);
            } else {
                foreach ($input as $row) {
                    $result[] = $row[$columnKey];
                }
            }
        } else {
            if (null === $columnKey) {
                foreach ($input as $row) {
                    $result[$row[$indexKey]] = $row;
                }
            } else {
                foreach ($input as $row) {
                    $result[$row[$indexKey]] = $row[$columnKey];
                }
            }
        }
        return $result;
    }
}
/**
 * 获取表名（不含表前缀）
 * @param string $model_id
 * @return string 表名
 */
function get_table_name($model_id = null){
    if(empty($model_id)){
        return false;
    }
    $Model = db('Model');
    $name = '';
    $info = $Model->getById($model_id);
    if($info['extend'] != 0){
        $name = $Model->getFieldById($info['extend'], 'name').'_';
    }
    $name .= $info['name'];
    return $name;
}
/**
 * 获取属性信息并缓存
 * @param  integer $id    属性ID
 * @param  string  $field 要获取的字段名
 * @return string         属性信息
 */
function get_model_attribute($model_id, $group = true,$fields=true){
    static $list;
    /* 非法ID */
    if(empty($model_id) || !is_numeric($model_id)){
        return '';
    }
    /* 获取属性 */
    if(!isset($list[$model_id])){
        $map = array('model_id'=>$model_id);
        $extend = db('Model')->getFieldById($model_id,'extend');
        if($extend){
            $map = array('model_id'=> array("in", array($model_id, $extend)));
        }
        $info = db('Attribute')->where($map)->field($fields)->select();
        $list[$model_id] = $info;
    }
    $attr = array();
    if($group){
        foreach ($list[$model_id] as $value) {
            $attr[$value['id']] = $value;
        }
        $model     = db("Model")->field("field_sort,attribute_list,attribute_alias")->find($model_id);
        $attribute = explode(",", $model['attribute_list']);
        if (empty($model['field_sort'])) { //未排序
            $group = array(1 => array_merge($attr));
        } else {
            $group = json_decode($model['field_sort'], true);
            $keys = array_keys($group);
            foreach ($group as &$value) {
                foreach ($value as $key => $val) {
                    $value[$key] = $attr[$val];
                    unset($attr[$val]);
                }
            }
            if (!empty($attr)) {
                foreach ($attr as $key => $val) {
                    if (!in_array($val['id'], $attribute)) {
                        unset($attr[$key]);
                    }
                }
                $group[$keys[0]] = array_merge($group[$keys[0]], $attr);
            }
        }
        if (!empty($model['attribute_alias'])) {
            $alias  = preg_split('/[;\r\n]+/s', $model['attribute_alias']);
            $fields = array();
            foreach ($alias as &$value) {
                $val             = explode(':', $value);
                $fields[$val[0]] = $val[1];
            }
            foreach ($group as &$value) {
                foreach ($value as $key => $val) {
                    if (!empty($fields[$val['name']])) {
                        $value[$key]['title'] = $fields[$val['name']];
                    }
                }
            }
        }
        $attr = $group;
    }else{
        foreach ($list[$model_id] as $value) {
            $attr[$value['name']] = $value;
        }
    }
    return $attr;
}
/**
 * 调用系统的API接口方法（静态方法）
 * api('User/getName','id=5'); 调用公共模块的User接口的getName方法
 * api('Admin/User/getName','id=5');  调用Admin模块的User接口
 * @param  string  $name 格式 [模块名]/接口名/方法名
 * @param  array|string  $vars 参数
 */
function api($name,$vars=array()){
    $array     = explode('/',$name);
    $method    = array_pop($array);
    $classname = array_pop($array);
    $module    = $array? array_pop($array) : 'common';
    $callback  = 'app\\'.$module.'\\Api\\'.$classname.'Api::'.$method;
    if(is_string($vars)) {
        parse_str($vars,$vars);
    }
    return call_user_func_array($callback,$vars);
}
/**
 * 根据条件字段获取指定表的数据
 * @param mixed $value 条件，可用常量或者数组
 * @param string $condition 条件字段
 * @param string $field 需要返回的字段，不传则返回整个数据
 * @param string $table 需要查询的表
 */
function get_table_field($value = null, $condition = 'id', $field = null, $table = null){
    if(empty($value) || empty($table)){
        return false;
    }
    //拼接参数
    $map[$condition] = $value;
    $info = db(ucfirst($table))->where($map);
    if(empty($field)){
        $info = $info->field(true)->find();
    }else{
        $info = $info->value($field);
    } 
    return $info;
}
/**
 * 获取链接信息
 * @param int $link_id
 * @param string $field
 * @return 完整的链接信息或者某一字段
 */
function get_link($link_id = null, $field = 'url'){
    $link = '';
    if(empty($link_id)){
        return $link;
    }
    $link = db('Url')->getById($link_id);
    if(empty($field)){
        return $link;
    }else{
        return $link[$field];
    }
}
/**
 * 获取文档封面图片
 * @param int $cover_id
 * @param string $field
 * @return 完整的数据  或者  指定的$field字段值
 */
function get_cover($cover_id, $field = null){
    if(empty($cover_id)){
        return false;
    }
    $picture = db('Picture')->where(array('status'=>1))->getById($cover_id);
    if($field == 'path'){
        if(!empty($picture['url'])){
            $picture['path'] = $picture['url'];
        }else{
            $picture['path'] = '/public'.$picture['path'];
        }
    }
    return empty($field) ? $picture : $picture[$field];
}
/**
 * 检查$pos(推荐位的值)是否包含指定推荐位$contain
 * @param number $pos 推荐位的值
 * @param number $contain 指定推荐位
 * @return boolean true 包含 ， false 不包含
 */
function check_document_position($pos = 0, $contain = 0){
    if(empty($pos) || empty($contain)){
        return false;
    }
    //将两个参数进行按位与运算，不为0则表示$contain属于$pos
    $res = $pos & $contain;
    if($res !== 0){
        return true;
    }else{
        return false;
    }
}
/**
 * 获取数据的所有子孙数据的id值
 */
function get_stemma($pids,$model, $field='id'){
    $collection = array();
    //非空判断
    if(empty($pids)){
        return $collection;
    }
    if( is_array($pids) ){
        $pids = trim(implode(',',$pids),',');
    }  
    $result     = $model->field($field)->where(array('pid'=>array('IN',(string)$pids)))->select();
    $child_ids  = array_column ((array)$result,'id');  
    while( !empty($child_ids) ){
        $collection = array_merge($collection,$result); 
        $result     = $model->field($field)->where( array( 'pid'=>array( 'IN', $child_ids ) ) )->select();
        $child_ids  = array_column((array)$result,'id');
    }  
    return $collection ? $collection : [];
}
/**
 * 验证分类是否允许发布内容
 * @param  integer $id 分类ID
 * @return boolean     true-允许发布内容，false-不允许发布内容
 */
function check_category($id){  
    if (is_array($id)) {
		$id['type']	=	!empty($id['type'])?$id['type']:2;
        $type = get_category($id['category_id'], 'type');
        $type = explode(",", $type);
        return in_array($id['type'], $type);
    } else {
        $publish = get_category($id, 'allow_publish');
        return $publish ? true : false;
    }
}
/**
 * 检测分类是否绑定了指定模型
 * @param  array $info 模型ID和分类ID数组
 * @return boolean     true-绑定了模型，false-未绑定模型
 */
function check_category_model($info){
    $cate   =   get_category($info['category_id']);
    $array  =   explode(',', $info['pid'] ? $cate['model_sub'] : $cate['model']);
    return in_array($info['model_id'], $array);
}

/**
 * 获取扩展模型对象
 * @param  integer $model_id 模型编号
 * @return object         模型对象
 */
function logic($model_id){
	$name  = get_document_model($model_id);  
	if($name['extend'] != 0)
		 $name  = get_document_model($name['extend'], 'name').'_'.$name['name'];
	//判断模型是否存在
	$module = \think\Request::instance()->module();
	$class = \think\Loader::parseClass($module, 'logic', $name, config('class_suffix'));
	if (!class_exists($class)) {//判断app\{$model}\logic\是否存在模型
		$common = 'common';
		$class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
		if (!class_exists($class)) {//判断app\common\logic\是否存在模型 
			 $class = \think\Loader::parseClass($module, 'logic', 'Base', config('class_suffix'));
			if(!class_exists($class)){
				$class = 'app\common\logic\Base';
				$class = str_replace('\\' . $module . '\\', '\\' . $common . '\\', $class);
			} 
			return new $class($name);
		}else{
			return \think\Loader::model($name,'logic');
		}
	}else{
		return \think\Loader::model($name,'logic');
	}
}
/*****************************************完美分割符*********************************************************************/
/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装） */
function get_client_ip($type = 0, $adv = false) {
    $type      = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) {
        return $ip[$type];
    }
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) {
                unset($arr[$pos]);
            }
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip   = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}
// 不区分大小写的in_array实现
function in_array_case($value, $array) { 
    return in_array(strtolower($value), array_map('strtolower', $array));
} 
//获取地理位置
function baiduxy($x,$y)
	{
		$url="http://api.map.baidu.com/geocoder/v2/?ak=F1e135ea865b3fab751d242cee1f3a67&callback=renderReverse&location=".$x.",".$y."&output=json&pois=1";
		$strRes=file_get_contents($url);
		$strRes=str_replace('renderReverse&&renderReverse(','',$strRes);
		$strRes=str_replace('}})','}}',$strRes);
		$arrResponse=json_decode($strRes,true);
		if($arrResponse['status']==0)
		{
		/**错误处理*/
		echo iconv('UTF-8','GBK',$arrResponse['err_msg'])."\n";
		}
		/** tinyurl */
		return $arrResponse['result'];
	}
//获取转换地理位置
function getbaidugps($lats,$lngs, $gps=false, $google=false)
  {
    $lat=$lats;
    $lng=$lngs;
    if($gps)
        $c=file_get_contents("http://api.map.baidu.com/ag/coord/convert?from=0&to=4&x=$lng&y=$lat");
    else if($google)
        $c=file_get_contents("http://api.map.baidu.com/ag/coord/convert?from=2&to=4&x=$lng&y=$lat");
    else 
    return array($lat,$lng);
    $arr=(array)json_decode($c);
    if(!$arr['error'])
    {
        $lat=base64_decode($arr['y']);
        $lng=base64_decode($arr['x']);
    }
    return array($lat,$lng);
 }
 //添加坐标点到数据库
 function addgps($uid,$zid=0,$fromid=0,$type=0){
		$lbs=session('lbsinfo');
		if($uid&&$lbs){
			if(isset($lbs['isnew'])&&$lbs['isnew']){
				if($lbs['address']) $lbsinfo['address']=$lbs['address'];
				if($lbs['business']) $lbsinfo['business']=$lbs['business'];
				if($lbs['province']) $lbsinfo['province']=$lbs['province'];
				if($lbs['city']) $lbsinfo['city']=$lbs['city'];
				if($lbs['district']) $lbsinfo['district']=$lbs['district'];
				if($lbs['street']) $lbsinfo['street']=$lbs['street'];
				if($lbs['street_number']) $lbsinfo['street_number']=$lbs['street_number'];
				if($lbs['citycode']) $lbsinfo['citycode']=$lbs['cityCode'];
			}else{
				if($lbs['gps']){
					$zhxy=getbaidugps($lbs['x'],$lbs['y'],true);
					$lbs['x']=$zhxy[0];
					$lbs['y']=$zhxy[1];
				}
				$bd=baiduxy($lbs['x'],$lbs['y']);
				if($bd['formatted_address']) $lbsinfo['address']=$bd['formatted_address'];
				if($bd['business']) $lbsinfo['business']=$bd['business'];
				if($bd['addressComponent']['province']) $lbsinfo['province']=$bd['addressComponent']['province'];
				if($bd['addressComponent']['city']) $lbsinfo['city']=$bd['addressComponent']['city'];
				if($bd['addressComponent']['district']) $lbsinfo['district']=$bd['addressComponent']['district'];
				if($bd['addressComponent']['street']) $lbsinfo['street']=$bd['addressComponent']['street'];
				if($bd['addressComponent']['street_number']) $lbsinfo['street_number']=$bd['addressComponent']['street_number'];
				if($bd['citycode']) $lbsinfo['citycode']=$bd['cityCode'];
				if($bd['sematic_description']) $lbsinfo['sematic_description']=$bd['sematic_description'];
			}
			$lbsinfo['gid']=session('gid');
			$lbsinfo['uid']=$uid;
			$lbsinfo['zid']=$zid;
			$lbsinfo['pid']=$fromid;
			$lbsinfo['ip']=get_client_ip();
			$lbsinfo['user_agent']=$_SERVER['HTTP_USER_AGENT'];
			$lbsinfo['createtime']=time();
			$lbsinfo['x']=$lbs['x'];
			$lbsinfo['y']=$lbs['y'];
			$lbsinfo['type']=$type;//0默认为客户  1为提案 2订单
			//print_r($lbsinfo);
			db('group_memberlbs')->insert($lbsinfo);
		 }
	 }
//更新员工坐标信息
function updategps($id){
	if($id){
		$gpsinfo=db('group_memberlbs')->find($id);
		$bd=baiduxy($gpsinfo['x'],$gpsinfo['y']);
		if($bd['formatted_address']) $lbsinfo['address']=$bd['formatted_address'];
		if($bd['business']) $lbsinfo['business']=$bd['business'];
		if($bd['addressComponent']['province']) $lbsinfo['province']=$bd['addressComponent']['province'];
		if($bd['addressComponent']['city']) $lbsinfo['city']=$bd['addressComponent']['city'];
		if($bd['addressComponent']['district']) $lbsinfo['district']=$bd['addressComponent']['district'];
		if($bd['addressComponent']['street']) $lbsinfo['street']=$bd['addressComponent']['street'];
		if($bd['addressComponent']['street_number']) $lbsinfo['street_number']=$bd['addressComponent']['street_number'];
		if($bd['citycode']) $lbsinfo['citycode']=$bd['cityCode'];
		if($bd['sematic_description']) $lbsinfo['sematic_description']=$bd['sematic_description'];
		$lbsinfo['upnum']=$gpsinfo['upnum']+1;
		db('group_memberlbs')->where('id',$id)->update($lbsinfo);
	}else{
		$lbsinfo=null;
	}
	return $lbsinfo;
}
//订单号	 
function mkOrderNumber(){
    return date("YmdHis") . str_pad(mt_rand(0,999999),6,'0',STR_PAD_LEFT);
}
//费率千分之6 
function getRechargeAmount($amount,$isk=1,$fl=0.006){
	if($isk){
		$sxf=round($amount*$fl,2);
	    $je=$amount-$sxf;
		$a=[$je,$sxf];
	}else{
		$a=[$amount,0];	
			}
    return $a;
}
/**
 	* 获取当前页面完整URL地址
	 */
function get_url() {
	  $sys_protocal = isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == '443' ? 'https://' : 'http://';
	  $php_self = $_SERVER['PHP_SELF'] ? $_SERVER['PHP_SELF'] : $_SERVER['SCRIPT_NAME'];
	  $path_info = isset($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : '';
	  $relate_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $php_self.(isset($_SERVER['QUERY_STRING']) ? '?'.$_SERVER['QUERY_STRING'] : $path_info);
	  return $sys_protocal.(isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '').$relate_url;
	}
	
function is_weixin(){
		if(strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
			return true;
		}
		return false;
}
function cdir($path){
	$np  = trim(trim($path),'/');
	if(!$np){
		return false;
	}else if(file_exists($np)){
		return true;
	}else{
		//将$np组合成层级关系$p
		$p = [];
		$x = explode('/',$np);
		while($x){
			$p[] = implode($x,'/');
			array_pop($x);
		}
		while($p){
			$d = array_pop($p);
			if(!file_exists($d)){
				mkdir($d, 0777);
			}
		}
	}
}

function pageInfo($res){
	if(!is_object($res)){
		return [];
	}
	return [
	'currentPage' => $res->currentPage(),
	'total' => $res->total(),
	'prev' => ($res->currentPage() - 1 >= 1) ? $res->currentPage() - 1 : '',
	'listRows' => $res->listRows(),
	'lastPage' => $res->lastPage(),
	'next' => ($res->currentPage() + 1 <= $res->lastPage()) ? $res->currentPage() + 1 : '',
	'f' => (( $res->currentPage() - 1 ) * $res->listRows() + 1) >= 0 ? (( $res->currentPage() - 1 ) * $res->listRows() + 1) : 0,
	't' => ($res->currentPage() * $res->listRows() > $res->total()) ? $res->total() : $res->currentPage() * $res->listRows()
	];
}

/**
 * 模拟POST请求
 *
 * @param string $url
 * @param array $fields
 * @param string $data_type
 *
 * @return mixed
 *
 * Examples:
 * ```
 * HttpCurl::post('http://api.example.com/?a=123', array('abc'=>'123', 'efg'=>'567'), 'json');
 * HttpCurl::post('http://api.example.com/', '这是post原始内容', 'json');
 * 文件post上传
 * HttpCurl::post('http://api.example.com/', array('abc'=>'123', 'file1'=>'@/data/1.jpg'), 'json');
 * ```
 */
function sendpost($url, $fields, $data_type='text')
{
	$cl = curl_init();
	if(stripos($url, 'https://') !== FALSE) {
		curl_setopt($cl, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($cl, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($cl, CURLOPT_SSLVERSION, 1);
	}
	curl_setopt($cl, CURLOPT_URL, $url);
	curl_setopt($cl, CURLOPT_RETURNTRANSFER, 1 );
	curl_setopt($cl, CURLOPT_POST, true);
	curl_setopt($cl, CURLOPT_POSTFIELDS, $fields);
	$content = curl_exec($cl);
	$status = curl_getinfo($cl);
	//print_r($content);
	//print_r($status);
	curl_close($cl);
	if (isset($status['http_code']) && $status['http_code'] == 200) {
		if ($data_type == 'json') {
			$content = json_decode($content);
		}
		return $content;
	} else {
		return FALSE;
	}
}

function strexists($string, $find) {
	return !(strpos($string, $find) === FALSE);
}


/**
 * 计算两组经纬度坐标 之间的距离
 * params ：lat1 纬度1； lng1 经度1； lat2 纬度2； lng2 经度2； len_type （1:m or 2:km);
 * return m or km
 */
function GetDistance($lat1, $lng1, $lat2, $lng2, $len_type = 1, $decimal = 2)
{
	//define('EARTH_RADIUS', 6378.137);//地球半径
	// define('PI', 3.1415926);
	$pi=3.1415926;
	$earth_ranius=6378.137;
	$radLat1 = $lat1 * $pi / 180.0;
	$radLat2 = $lat2 * $pi / 180.0;
	$a = $radLat1 - $radLat2;
	$b = ($lng1 * $pi / 180.0) - ($lng2 * $pi / 180.0);
	$s = 2 * asin(sqrt(pow(sin($a/2),2) + cos($radLat1) * cos($radLat2) * pow(sin($b/2),2)));
	$s = $s * $earth_ranius;
	$s = round($s * 1000);
	if ($len_type > 1)
	{
		$s /= 1000;
	}
	return round($s, $decimal);
	//echo GetDistance(39.908156,116.4767, 39.908452,116.450479, 1);//输出距离/米
}

/**
 *计算某个经纬度的周围某段距离的正方形的四个点
 *@param lng float 经度
 *@param lat float 纬度
 *@param distance float 该点所在圆的半径，该圆与此正方形内切，默认值为0.5千米
 *@return array 正方形的四个点的经纬度坐标
 */

function GetSquarePoint($lng, $lat,$distance = 0.5){
	$earth_ranius=6378.137;
	$dlng =  2 * asin(sin($distance / (2 * $earth_ranius)) / cos(deg2rad($lat)));
	$dlng = rad2deg($dlng);
	$dlat = $distance/$earth_ranius;
	$dlat = rad2deg($dlat);
	return array(
			'left-top'=>array('lat'=>$lat + $dlat,'lng'=>$lng-$dlng),
			'right-top'=>array('lat'=>$lat + $dlat, 'lng'=>$lng + $dlng),
			'left-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng - $dlng),
			'right-bottom'=>array('lat'=>$lat - $dlat, 'lng'=>$lng + $dlng)
	);
}

function exChangeSeconds($s = 0){
	if($s <= 0){
		$x = '0秒';
	}else{
		$x = '';
		$h = floor($s / 3600);
		$h > 0 ? ($x .= $h .'时') : null;
		$s -=  $h * 3600;
		$m = floor($s / 60);
		$m > 0 ? ($x .= $m .'分') : null;
		$s -=  $m * 60;
		$s > 0 ? ($x .= $s .'秒') : null;
	}
	return $x;
}

function ueditorUploadConfig($uploadPath = ''){
	if($uploadPath === ''){
		config('upload_path_common');
	}
	$ueUploadConfig ='
        /* 前后端通信相关的配置,注释只允许使用多行方式 */
        {
            /* 上传图片配置项 */
            "imageUrl": "/public'.$uploadPath .'",
            "imageActionName": "goodsAddImgs", /* 执行上传图片的action名称 */
            "imageFieldName": "goodsAddImg", /* 提交的图片表单名称 */
            "imageMaxSize": 2048000, /* 上传大小限制，单位B */
            "imageAllowFiles": [".png", ".jpg", ".jpeg", ".gif", ".bmp"], /* 上传图片格式显示 */
            "imageCompressEnable": true, /* 是否压缩图片,默认是true */
            "imageCompressBorder": 1600, /* 图片压缩最长边限制 */
            "imageInsertAlign": "none", /* 插入的图片浮动方式 */
            "imagePathFormat": "", /*格式化路径*/
            "imageUrlPrefix": "" /*前缀*/
        }';
	$CONFIG = json_decode(preg_replace("/\/\*[\s\S]+?\*\//", "", $ueUploadConfig));
	return json_encode($CONFIG);
}
//生成唯一标识符
function mkUniqueSign($prefix = '',$params = []){
	$s = $prefix . uniqid();
	if(!empty($params)){
		foreach($params as $k=>$v){
			$s .= $k . $v;
		}
	}
	return md5($s);
}
function wksort(&$arr = [],$sign = 0,$rule = SORT_REGULAR){
	if(empty($arr)){
		return [];
	}else{
		$v = array_values($arr);
		switch($sign){
			case 0:
				sort($v, $rule);break;
			case 1:
				rsort($v, $rule);break;
			default:
				sort($v, $rule);
		}
		$newarr = [];
		for($i = 0 ,$l = count($v); $i < $l; $i++){
			$newarr[array_search($v[$i], $arr)] = $v[$i];
		}
		$arr = $newarr;
		return true;
	}
}
function setInterval($param){
	$ss = str_replace('T','',trim($param['ss']));
	$ee = str_replace('T','',trim($param['ee']));
	$s = !!$ss ? strtotime($ss) : false;
	$e = !!$ee ? strtotime($ee) : false;
	return ['ss'=>$s,'ee'=>$e];
}
function pwd($inputPwd = '',$salt = ''){
    $key = ['$','#','&','%','?','^'];
    if($salt === ''){
        $salt = mt_rand(0,5).mt_rand(0,5).mt_rand(0,5).mt_rand(0,5).mt_rand(0,5).mt_rand(0,5);
    }
    if($inputPwd === ''){
        $pwd = [mt_rand(1,9),mt_rand(0,9),mt_rand(0,9),mt_rand(0,9),mt_rand(0,9),mt_rand(0,9)];
        $spwd = implode('',$pwd);
    }else{
        $pwd = [];
        $spwd = $inputPwd;
        for($j = 0, $k = strlen($inputPwd); $j < $k;$j++){
            $pwd[] = $inputPwd{$j};
        }
    }
    for($i = 0, $l = strlen($salt); $i < $l;$i++){
        $y = intval($salt{$i});
        $k = $key[$y];
        array_splice($pwd,$y,0,$k);
    }
    //同时作为穿插进 $pwd;
    $pspwd = base64_encode(base64_encode(urlencode(urlencode(implode('', $pwd)))));
    $res =  ['pwd' => $pspwd,'spwd'=>$spwd,'salt' => $salt];
    return $res;
}
function br2nl($text){
    return preg_replace('/<br\\s*?\/??>/i',"\r\n",$text);
}