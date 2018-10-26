<?php
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
namespace app\m\model;
use think\Model;
/**
 * Description of HsModel
 *
 * @author Administrator
 */
class HsModel extends Model{
    //put your code here
    protected $table = 'ljk_goods_s';
    public function hsList($gid,$status=''){
        if(!!strlen($status)){
            return $this
                ->alias('h')
                ->field("h.*,g.goods_id,g.goods_name")
               // ->where('h.gid',$gid)
                ->where(function ($query) use($gid){
                    $jtid = session('jtid');
                    if($jtid != 0 && $jtid != 1){
                	$query->where('h.gid',$gid)
                	->whereOr('h.jtid',session('jtid'));
                    }else{
                        $query->where('h.gid',$gid);
                    }
                })
                ->where('status','IN',$status)
                ->join('__GOODS__ g','h.goodsid=g.goods_id','LEFT')
                ->select();
        }else{
            return $this
                ->alias('h')
                ->field("h.*,g.goods_id,g.goods_name")
              //  ->where('h.gid',$gid)
                ->where(function ($query) use($gid){
                    $jtid = session('jtid');
                    if($jtid != 0 && $jtid != 1){
                	$query->where('h.gid',$gid)
                	->whereOr('h.jtid',session('jtid'));
                    }else{
                        $query->where('h.gid',$gid);
                    }
                })
                ->join('__GOODS__ g','h.goodsid=g.goods_id','LEFT')
                ->select();
        }
    }
    public function getOneHs($id){
        return $this->find($id)->getData();
    }
}
