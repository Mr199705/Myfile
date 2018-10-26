<?php
// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://ljk.cc All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: itkk <it_kk@qq.com>
// +----------------------------------------------------------------------
namespace app\shop\controller;
use think\Db;
class Index extends Base{
    public function index(){
        //  轮播商品
        $whereA = array();
        $whereA['gid'] 		= $this->gid;
        $whereA['status'] 	= 1;
        $whereA['shopid']       = $this->groupShop['id'];
        $reData = Db::name('shop_top')
            ->where($whereA)
            ->limit(4)
            ->select();
        //  底部推荐商品
        $whereB = [];
        $whereB['gid']    		= $this->gid;
        $whereB['recommend']    = 1;
        $whereB['status']       = 1;
        $whereB['sell_status']  = 1;
        $whereB['status']  = 1;
        $whereB['unitgid'] = ['NEQ',0];
        $field = 'bj.id,bj.goodsName,bj.goods_id,bj.tuanprice,bj.goodsThumb,bj.unit,bj.goodsImg,bj.retailprice';
        $dbc = config('database');
        $dbPrefix = $dbc['prefix'];
        $order = '';
        $rs = '';
        if(!!$this->rankid){
            if($this->gid == -1){
                if($this->rankid !== 68){
                    $rs = "AND bjsp.rankid IN({$this->rankid},68)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,68]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else if($this->gid == 205){
                if($this->rankid !== 333){
                    $rs = "AND bjsp.rankid IN({$this->rankid},333)";
                    $whereBjsp['rankid'] = ['IN',[$this->rankid,333]];
                    $order = 'field(rankid,' . $this->rankid . ') DESC';
                }else{
                    $rs = "AND bjsp.rankid={$this->rankid}";
                    $whereBjsp['rankid'] = $this->rankid;
                    $order = 'rankid DESC';
                }
            }else{
                $rs = "AND bjsp.rankid IN({$this->rankid},0)";
                $whereBjsp['rankid'] = ['IN',[0,$this->rankid]];
                $order = 'rankid DESC';
            }
        }else{
            if($this->gid == -1){
                $rs = "AND bjsp.rankid=68";
                $whereBjsp['rankid'] = 68;
                $order = 'rankid DESC';
            }else if($this->gid == 205){
                $rs = "AND bjsp.rankid=333";
                $whereBjsp['rankid'] = 333;
                $order = 'rankid DESC';
            }else{
                $rs = "AND bjsp.rankid=0";
                $whereBjsp['rankid'] = 0;
                $order = 'rankid DESC';
            }
        }
        if($this->gid == 205 || $this->gid == -1){
            $whereBj = [];
            foreach($whereB as $k => $v){
                $whereBj['bj.' . $k] = $v;
            }
            $whereBjspj['bjsp.gid'] = $this->gid;
            $whereBjspj['bjsp.rankid'] = $whereBjsp['rankid'];
            $whereBjspj['bjsp.tuanprice'] = ['GT',0];
            $indes = Db::name('baojia bj')
                ->field($field)
                ->join('baojia_skuprice bjsp','bj.id=bjsp.bjid','LEFT')
                ->where($whereBj)
                ->where($whereBjspj)
                ->group('bj.id')
                ->having('count(bjsp.id) >= 1')
                ->order(['bj.id'=>'DESC'])
                ->limit(10)
	        ->select();
        }else{
            $sql = <<<SQL
                SELECT 
                    {$field}
                FROM
                    {$dbPrefix}baojia bj
                WHERE
                    `bj`.`gid` = {$this->gid}
                AND `bj`.`recommend` = 1
                AND `bj`.`status` = 1
                AND `bj`.`sell_status` = 1
                AND `bj`.`unitgid` <> 0
                AND
                    CASE WHEN 
                        (SELECT COUNT(*) 
                            FROM  
                                {$dbPrefix}baojia_skuprice bjsp
                            WHERE 
                                bjsp.gid={$this->gid}
                            AND
                                bjsp.tuanprice > 0
                            AND
                                bjsp.bjid=bj.id
                                {$rs}
                                ) >= 1
                            THEN 
                                1 
                            ELSE  
                                `bj`.`tuanprice` > 0
                            END
                ORDER BY
                    bj.id
                DESC
                LIMIT 10
SQL;
            $indes = Db::connect()->query($sql);
        }
        foreach ($indes as $ind => $val ){
            if(!trim($val['goodsThumb']) && !trim($val['goodsImg']) || !trim($val['goodsName'])){
                $whereG['goods_id'] = $val['goods_id'];
                $goods = Db::name('goods')->field('goods_thumb,goods_name,goods_img')->where($whereG)->find();
            }
            if(!!trim($val['goodsThumb'])){
                $goodsThumb = $val['goodsThumb'];
            }else if(!!trim($val['goodsImg'])){
                $goodsThumb = $val['goodsImg'];
            }else{
                if(!empty($goods)){
                    if(!!$goods['goods_thumb']){
                        $goodsThumb = $goods['goods_thumb'];
                    }else if(!!$goods['goods_img']){
                        $goodsThumb = $goods['goods_img'];
                    }
                }
            }
            if(!trim($val['goodsName'])){
                if(!empty($goods)){
                    if(!!$goods['goods_name']){
                        $indes[$ind]['goodsName'] = $goods['goods_name'];
                    }
                }
            }
            if(!!$goodsThumb){
                $goodsUrl = mkgoodsimgurl(['url'=>$goodsThumb]);
            }else{
                $goodsUrl = '';
            }
            $indes[$ind]['goodsImg'] = $goodsUrl;
            $bjid = $val['id'];
            $whereBs['baojia_id'] = $bjid;
            $whereBs['gid'] = $this->gid;
            $whereBs['sell_status'] = 1;
            $oneSku = Db::name('baojia_sku')->field('id,attrs_value')->where($whereBs)->find();
            if(!empty($oneSku)){
                $whereBjsp['skuid'] = $oneSku['id'];
                $whereBjsp['bjid'] = $bjid;
                $whereBjsp['status'] = 1;
                $whereBjsp['sell_status'] = 1;
                $whereBjsp['tuanprice'] = ['NEQ',0];
                $bsp = Db::name('baojia_skuprice')->field('id,tuanprice,unitgid,unitid,retailprice')->where($whereBjsp)->order($order)->find();
                $whereU['gid'] = $this->gid;
                $whereU['id'] = $bsp['unitid'];
                $whereU['unitgid'] = $bsp['unitgid'];
                $indes[$ind]['unit'] = Db::name('unit')->where($whereU)->value('uname');
                $indes[$ind]['unitid'] = $bsp['unitid'];
                $indes[$ind]['tuanprice'] = $bsp['tuanprice'];
                $indes[$ind]['retailprice'] = $bsp['retailprice'];
                $indes[$ind]['bspid'] = $bsp['id'];
                if(!!$oneSku['attrs_value']){
                    $oneSku['attrs_value'] = explode(',',trim($oneSku['attrs_value']));
                }
                $indes[$ind]['sku'] = $oneSku;
            }else if($this->rankid != 0){
                $whereBr['rank_id'] = $this->rankid;
                $whereBr['gid'] = $this->gid;
                $whereBr['goods_id'] = $indes[$ind]['goods_id'];
                $whereBr['tuanprice'] = ['NEQ',0];
                $br = Db::name('baojia_rank')->field('id,tuanprice,retailprice')->where($whereBr)->find();
                if(!empty($br)){
                    $br['tuanprice'] != 0 ? $indes[$ind]['tuanprice'] = $br['tuanprice'] : null;
                    $br['retailprice'] != 0 ? $indes[$ind]['retailprice'] = $br['retailprice'] : null;
                }
            }
        }
        //	一级分类
        $whereC = [];
        $whereC['gid']          = $this->gid;
        $whereC['is_show']    	= 1;
        $whereC['pid']    		= 0;
        $cate = Db::name('category')
        	->where($whereC)
        	->field(['id','img','title','cat_desc'])
        	->select();
        $this->assign( 'category', $cate);
        $this->assign('list',$reData);
//        dump($indes);die();
        $this->assign('inde',$indes);
        return view('/ljk/index');
    }
}
