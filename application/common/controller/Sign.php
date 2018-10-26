<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace app\common\controller;

/**
 * Description of ValidateSign
 *
 * @author Administrator
 */
class Sign {
    private $secretKey = '';
    private $signMethod = '';
    private $params = [];
    private $sourceParams = [];
    public function mkSign($params=[],$secretKey='tBtxT6ggC!wYFFxaIjzD',$appKey='epinbao.cc',$signMethod='md5'){
        $timestamp = $params['timestamp'] = time();
        $this->sourceParams = $this->params = $params;
        $this->secretKey = $secretKey;
        $this->signMethod = $signMethod;
        $this->params['app_key'] = $appKey;
        //只需要获取的是一个签名
        $sign = $this->getSign();
        return ['sign'=>$sign,'timestamp'=>$timestamp];
    }
    public function validateSign($params=[],$secretKey='tBtxT6ggC!wYFFxaIjzD',$appKey='epinbao.cc',$signMethod='md5'){
        $sourceSign = isset($params['sign']) ? $params['sign'] : '';
        unset($params['sign']);
        $this->params = $params;
        $this->params['app_key'] = $appKey;
        $this->secretKey = $secretKey;
        $this->signMethod = $signMethod;
        $newSign = $this->getSign();
	//	return $newSign;
        if($newSign === $sourceSign){
            return true;
        }else{
            return false;
        }
    }
    private function getParamStr() {
        // 将参数以其参数名的字典序升序进行排序
        ksort($this->params);
        // 待签名字符串
        $str = $this->secretKey;
        foreach ($this->params as $k => $v) {
                //key/value对生成一个key=value格式的字符串，并拼接到待签名字符串后面
                $str .= "$k=$v";
        }
        return $str;
    }
	// 获取签名
    private function getSign() {
        $str = $this->getParamStr();
        // 加密算法     
        if ($this->signMethod == 'crypt') {
            $salt = '$5$rounds=5000$anexamplestringforsalt$';  // 盐值请随机生成，这里演示写死了成了SHA-256
            return crypt($str, $salt);
        } else {
            // 通过md5算法为签名字符串生成一个md5签名
            return strtoupper(md5($str));
        }
    }
}
