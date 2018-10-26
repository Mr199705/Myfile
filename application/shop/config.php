<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
// $Id$
return [

    //模板参数替换
    'view_replace_str'       => array(
        '__CSS__'    => '/static/shop/css',
        '__JS__'     => '/static/shop/js',
        '__IMG__'     => '/static/shop/image',
    	'__IMGS__'     => '/static/shop/images',
    	'__STATIC__' => '/static/shop/default',
        '__COMMON__' => '/static/common'
    ),
    //配置session
    'session'            => [
        'id'             => '',
        // session_ID的提交变量,解决flash上传跨域
        'var_session_id' => 'ljk_shop',
        // session 前缀
        'prefix'         => 'index',
        // 驱动方式 支持redis memcache memcached
        'type'           => '',
        // 是否自动开启 session
        'auto_start'     => true,
    ],
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => APP_PATH . 'wap/view/tpl/dispatch_jump.tpl',
    'dispatch_error_tmpl'    => APP_PATH . 'wap/view/tpl/dispatch_jump.tpl',

    //管理员状态
    'user_status' => [
        '1' => '正常',
        '2' => '禁止登录'
    ],   
	 //订单确认状态
    'order_status_status' => [
        '0' => '无效',
        '1' => '有效'
    ],
    'order_confirm_status' => [
    	'0'=>'无效订单',
        '1' => '未确认',
        '2' => '已确认'
    ],
	 //订单商品状态
    'order_goods_status' => [
        '1' => '销售',
        '2' => '赠品'
    ],
	 //订单配送状态
    'order_dispatch_status' => [
        '0' => '未发货',
        '1' => '已发货',
        '2' => '已收货'
    ],
	 //订单付款状态
    'order_pay_status' => [
        '0' => '未付款',
        '1' => '已付款'
    ],
    'order_trade_status' => [
        '0' => '未结束',
        '1' => '已结束'
    ],
	 //客户认证状态
    'rz_status' => [
        '0' => '未认证',
        '1' => '已认证'
    ],
    //角色状态
    'role_status' => [
        '1' => '启用',
        '2' => '禁用'
    ],
    'order_type'=>[
        '0'=>'商城微信端',
        '1'=>'呼叫中心',
        '2'=>'业务员移动端',
        '3'=>'分销商移动端',
        '4'=>'业务员PC端',
        '5'=>'商城PC端',
        '6'=>'多级分销订单'
    ],   
    'upload_path' => '/uploads/shop',
    'goods_upload_path' => '/uploads/goods'
];
