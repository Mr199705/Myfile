<?php
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\m\validate;
use think\Validate;

class OrderGoodsValidate extends Validate
{
    protected $rule = [
       ['num', 'number|between:1,9999', '请输入正确的商品数量！|请输入正确的商品数量！']
    ];
    
}