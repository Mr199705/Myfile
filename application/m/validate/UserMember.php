<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\m\validate;
use think\Validate;
/**
 * Description of UserMember
 *
 * @author Administrator
 */
class UserMember extends Validate{
    //put your code here
    protected $rule = [
        ['realname','require|max:50','用户名不得为空！|用户名不得大于50位'],
       // ['email','email','邮箱地址格式不正确！'],
        ['mobile','require','手机号码不得为空']
    ];
    protected $scene = [
        'edit'=>['realname','phone'],
        'add'=>['realname','phone']
    ];
}
