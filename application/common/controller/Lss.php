<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\common\controller;
use think\Controller;
use think\Db;
/**
 * Description of Lss
 *
 * @author Administrator
 */
class Lss extends Controller{
    //put your code here
    public function index($input = []){
        $sign = new Sign();
        if($sign->validateSign($input) === true){
            $oid = isset($input['oid']) ? intval($input['oid']) : false;
            $gid = isset($input['gid']) ? intval($input['gid']) : false;
            $uid = isset($input['uid']) ? intval($input['uid']) : false;
            $confirm = isset($input['confirm']) ? intval($input['confirm']) : false;
            $trade = isset($input['trade']) ? intval($input['trade']) : false;
            $pay = isset($input['pay']) ? intval($input['pay']) : false;
            $dispatch = isset($input['dispatch']) ? intval($input['dispatch']) : false;
            if(!$oid || !$gid || !$uid){
                return ['code' => -1, 'msg' => '参数不完整！'];
            }else{
                $whereO['oid'] = $oid;
                $whereO['gid'] = $gid;
                $whereO['uid'] = $uid;
                if($confirm !== false){
                    $whereO['confirm'] = $confirm;
                }
                if($trade !== false){
                    $whereO['trade'] = $trade;
                }
                if($pay !== false){
                    $whereO['pay'] = $pay;
                }
                if($dispatch !== false){
                    $whereO['dispatchy'] = $dispatch;
                }
                $ord = Db::name('order')->where($whereO)->find();
                if(empty($ord)){
                    return ['code' => -1, 'msg' => '订单不存在！'];
                }else{
                    $whereOg['oid'] = $oid;
                    $whereOg['gid'] = $gid;
                    $whereOg['sku_id'] = ['NEQ',0];
                    $ogs = Db::name('order_goods')->field('id,name goodsName,baojia_id bjid,oid,num,unitgid,unitid,sku_id skuid,avs')->where($whereOg)->select();
                    $units = [];
                    $lssd = [];
                    for($i = 0, $l = count($ogs); $i < $l; $i++){
                        $og = $ogs[$i];
                        if(!isset($units[$og['unitid']])){
                            $whereU = [];
                            $whereU['gid'] = $gid;
                            $whereU['id'] = $og['unitid'];
                            $whereU['unitgid'] = $og['unitgid'];
                            $units[$og['unitid']] = Db::name('unit')->field('id,coefficient,unitgid,uname')->where($whereU)->find();
                        }
                        $u = $units[$og['unitid']];
                        if(!isset($lssd[$og['skuid']])){
                            $lssd[$og['skuid']] = [
                                'where' => [
                                    'gid' => $gid,
                                    'oid' => $og['oid'],
                                    'skuid' => $og['skuid'],
                                    'bjid' => $og['bjid'],
                                    'unitgid' => $og['unitgid']
                                ],
                                'lnum' => 0,
                                'gid' => $gid,
                                'skuid' => $og['skuid'],
                                'goodsName' => $og['goodsName'],
                                'oid' => $og['oid'],
                                'unitgid' => $og['unitgid'],
                                'bjid' => $og['bjid']
                            ];
                        }
                        $lssd[$og['skuid']]['lnum'] += $u['coefficient'] * $og['num'];
                    }
                    $whereSku['gid'] = $gid;
                    try{
                        foreach($lssd as $v){
                            $whereSl = $v['where'];
                            unset($v['where']);
                            $sl = Db::name('sku_lock')->field('id,lnum,skuid')->where($whereSl)->find();
                            if(!empty($sl)){
                                //已经有锁定记录
                                if($v['lnum'] !== $sl['lnum']){
                                    //需要进行更新 多退少补
                                    $inc = -($v['lnum'] - $sl['lnum']);
                                    $whereSl['id'] = $sl['id'];
                                    Db::name('sku_lock')->where($whereSl)->update($v);
                                }else{
                                    $inc = 0;
                                }
                            }else{
                                $v['ctime'] = time();
                                $inc = -$v['lnum'];
                                Db::name('sku_lock')->insert($v);
                            }
                            if($inc != 0){
                                $whereSku['id'] = $v['skuid'];
                                $whereSku['baojia_id'] = $v['bjid'];
                                Db::name('baojia_sku')->where($whereSku)->setInc('storage_num',$inc);
                            }
                        }
                        return ['code' => 0,'msg' => '已锁定库存！'];
                    }catch(\think\Exception $e){
                        return ['code' => -1,'msg' => $e->getMessage()];
                    }
                }
            }
        }else{
            return ['code' => -1, 'msg' => '签名错误！'];
        }
    }
}
