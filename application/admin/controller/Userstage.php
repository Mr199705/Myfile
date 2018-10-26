<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/24
 * Time: 9:33
 */

namespace app\admin\controller;
use think\Db;

class Userstage
{
    public function __construct(\think\Request $request = null) {
        parent::__construct($request);
        $this->initData = [
            'sign'=>$this->sign,
            'requestFunc'=>$this->requestFunc,
            'requestUrl'=>$this->requestUrl,
            'cUrl'=>$this->cUrl,
            'jsName'=>$this->jsName,
        ];
        $this->assign('initData',$this->initData);
    }


}