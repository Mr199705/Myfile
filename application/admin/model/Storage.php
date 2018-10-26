<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
/**
 * Description of Storage
 * 
 * @author Administrator
 */
namespace app\common\model;
use think\Model;
use think\Db;
class Storage extends Model{
    //put your code here
    public $rows = 10;
    private $gid = 0;
    private $guid = 0;
    private $unitg = [];
    private $units = [];
    private $unitgroup = [];
    private $unit = [];
    private $ruleType = 1;
    private $ownerInfo = [];
    private $userInfo = [];
    public function __construct($data = array()) {
        parent::__construct($data);
        $this->gid = isset($data['gid']) ? $data['gid'] : 0;
        $this->guid = isset($data['guid']) ? $data['guid'] : 0;
        $this->ruleType = isset($data['rule_type']) ? $data['rule_type'] : $this->ruleType;
    }
    public function getStorages($whereS = [],$rows = 10){
        if(!empty($whereS)){
            $res = Db::name('storage s')
                ->field('s.*,gm.mobile,gm.realname')
                ->join('group_member gm','s.guid=gm.uid','LEFT')
                ->where($whereS)
                ->order('s.sort DESC')
                ->paginate($rows);
            return $res;
        }else{
            return false;
        }
    }
    public function addStorage($data){
        try{
            if(Db::name('storage')->insertGetId($data)){
                return true;
            }
        }catch(\think\Exception $e){
            return false;
        }
    }
    public function editStorage($whereS=[],$data=[]){
        if(empty($whereS) || empty($data)){
            return false;
        }else{
            try{
                if(Db::name('storage')->where($whereS)->update($data) !== false){
                    return true;
                }
            }catch(\think\Exception $e){
                return false;
            }
        }
    }
}