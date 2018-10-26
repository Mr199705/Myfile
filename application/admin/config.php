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
    	'__RESOURCE__' => '/resource/admin',
        '__CSS__'    => '/static/admin/css',
        '__JS__'     => '/static/admin/js',
        '__IMG__' => '/static/admin/images',
        '__PUBLIC__' => '/static/admin',
        '__STATIC__' => '/static',
    	'__COMMON__' => '/static/common',
        '__GOODSIMG__' => 'http://sx.img.ljk.cc/',
        'LJK_VERSION' => '6.4.1714',
        'LJK_XT_TITLE' => '老窖客系统',
        'LJK_RELEASE' => '20170417'
    ),
	//配置session
	'session'                => [
		'id'             => '',
		// session_ID的提交变量,解决flash上传跨域
		'var_session_id' => 'ljk_session_id',
		// session 前缀
		'prefix'         => 'ljk_admin',
		'name' 			 => 't_skey',
		// 驱动方式 支持redis memcache memcached
		'type'           => '',
		// 是否自动开启 session
		'auto_start'     => true,
	],
	    // 默认跳转页面对应的模板文件
  //  'dispatch_success_tmpl'  => APP_PATH . 'wap/view/tpl/dispatch_jump.tpl',
  //  'dispatch_error_tmpl'    => APP_PATH . 'wap/view/tpl/dispatch_jump.tpl',
    // 默认跳转页面对应的模板文件
    'dispatch_success_tmpl'  => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'dispatch_error_tmpl'    => THINK_PATH . 'tpl' . DS . 'dispatch_jump.tpl',
    'car_state' => [
    '1' => '可用',
    '2' => '维修中',
    '3' => '报废',
    '4' => '使用中',
    ],
    //考勤类型
    'dktype' => [
    '1' => '上班打卡',
    '2' => '下班打卡'
    		],
    //管理员状态
    'user_status' => [
    	'-1' => '已删除',
        '1' => '正常',
        '2' => '禁止登录'
    ],   
	 //订单确认状态
    'order_status_status' => [
        '0' => '无效',
        '1' => '有效'
    ],
    'order_confirm_status' => [
    '-1'=>'无效订单',
    '0' => '未确认',
    '1' => '已确认',
    ],
	 //订单商品状态
    'order_goods_status' => [
        '1' => '销售',
        '2' => '赠品'
    ],
	 //订单配送状态
    'order_dispatch_status' => [
        '0' => '未配送',
        '1' => '配送中',
        '2' => '配送完成'
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
    //订单分成状态
    'order_fc_status' => [
    '0' => '未分成',
    '1' => '分成中',
    '2' => '已分成',
    '3' => '分成取消'
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
    'call_get' => [
    '0' => '未接通',
    '1' => '已接通',
    ],
    //积分制项目状态
    'jfz_status' => [
    '1' => '有效',
    '0' => '禁用'
    		],
	'order_type'=>[
				'0'=>'微信商城',
				'1'=>'呼叫中心',
				'2'=>'业务员',
				'3'=>'分销商',
				'4'=>'业务员PC',
				'5'=>'商城PC',
				'6'=>'营销订单',
		],
	'goods_apply_status' => [
		'3' => '未通过',
		'0' => '未审核',
		'1' => '已通过',
		'2' =>'审核中'
				],
	//仓储
	'storage_order_confirm' => [
		'-1' => '已作废',
		'0' => '未审核',
		'1' => '已锁定',
		'2' => '已通过'
				],
	'storage_order_finish' => [
		'0' => '未结束',
		'1' => '已结束',
			  ],
	'visit_type' => [
			  '1' => '日常拜访',
			  '2' => '上报库存',
			  '3' => '上报回收',
			  ],
	//提现
	'tx_verify_status' => [
		'0' => '待审核',
		'1' => '审核通过',
		'2' => '审核不通过',
			  ],
	'tx_pay_status' => [
			  '0' => '待支付',
			  '1' => '支付中',
			  '2' => '支付成功',
			  '3' => '支付失败',
			  '4' => '支付退回'
			  ],
    'upload_path' => '/uploads/m',
    //商品
    'goods_upload_path' => '/uploads/goods',
    'app_trace' => false
];
