<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\admin\validate;
use think\Validate;
class Storage extends Validate{
    //put your code here
    protected $rule = [
        ['name','require','仓库名称不得为空！'],
        ['guid','require','必须设置仓库管理员！'],
        ['location_no','require','仓库编号不得为空！'],
    ];
}
