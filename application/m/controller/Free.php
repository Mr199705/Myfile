<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\controller;
use think\Controller;
class Free extends Controller{
    public function driverOrderNavBaiduMap(){
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){

        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            
        }else{
            
        }
        $input = input();
        if(empty($input)){
            return json(['code'=>-1,'msg'=>'非法操作！']);
        }
        if(strpos($_SERVER['HTTP_USER_AGENT'], 'iPhone')||strpos($_SERVER['HTTP_USER_AGENT'], 'iPad')){
            $input['ostype'] = 1;
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'Android')){
            $input['ostype'] = 2;
        }else{
            $input['ostype'] = -1;
        }
        $this->assign('param',$input);
        return $this->fetch('/free/driver_order_nav_baidu_map');
    }
}


