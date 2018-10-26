<?php
namespace app\shop\controller;

use think\Controller;
use think\Db;
class Category extends Base{
    //  TODO:: 分类
    public function index(){
        //  获取一级分类
        $userM = $this->getUserId();
        $whereA = array();
        $whereA['gid']      = $userM['gid'];
        $whereA['pid']      = 0;    //  一级分类
        $whereA['is_show']  = 1;
        $reData = Db::name('category')
            ->where($whereA)
            ->field(['id','img','title','cat_desc'])
            ->select();
        $this->assign( 'category', $reData);
        return view('/ljk/category');
    }
    //  TODO::category异步数据获取
    public function categoryFun(){
        if(request()->isAjax() && request()->isPost()){
            $input = input('post.');
            $action = [];
            if(isset($input['action']) && $action = trim($input['action'])){
                unset($input['action']);
            }
            switch($action){
                case 'category':return $this->categoryJson($input);
                case 'categorylist':return $this->categoryListJson($input);
            }
        }
    }
    //  TODO::商品分类
    public function categoryJson( $input = '' ){
        $userM = $this->getUserId();
        $page = isset($input['page']) ? intval($input['page']) : 1;
        $whereA = [];
        $whereA['gid']      = $userM['gid'];
        $whereA['pid']      = isset($input['pid']) ? intval($input['pid']) : 0;
        $whereA['is_show']  = 1;
        try {
            $reData = Db::name('category')
                ->where($whereA)
                ->field([ 'id', 'img', 'title'])
                ->order('id ASC')
                ->select();
            if( $page === 1 ){
                $pClas = Db::name('category')->where([ 'id' => $whereA['pid'] ])->field([ 'id', 'img', 'title'])->find();
                if( !empty($pClas) ){
                    $pClas['title'] = '所有';
                    array_unshift( $reData, $pClas);
                }
            }
        } catch (\Exception $e) {
            return $this->reJson( 0, 0, '获取失败');
        }
        return $this->reJson( 1, $reData, '获取成功:' . count($reData));
    }
    //  TODO::该分类商品列表页面
    public function categorylist(){
        $clas = !empty(input('clas')) ? input('clas') : 0;
        $w = !empty(input('w')) ? input('w') : '';
        $this->assign('clas', $clas);
        $this->assign('w', $w);
        return view('/ljk/goodslist');
    }
    //  TODO::该分类下的商品列表获取
    private function categoryListJson($input = [] ){
        $g = new Goods();
        return $g->getGoodsList($input);
    }
}