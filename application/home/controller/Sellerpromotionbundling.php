<?php

namespace app\home\controller;

use think\Lang;

class Sellerpromotionbundling extends BaseSeller
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'home/lang/'.config('default_lang').'/sellerpromotionbundling.lang.php');
        if (intval(config('promotion_allow')) !== 1) {
            $this->error(lang('promotion_unavailable'), url('Seller/index'));
        }
    }


    public function index()
    {
        $pbundling_model = model('pbundling');

        // 更新套装状态
        $where = array();
        $where['store_id'] = session('store_id');
        $where['blquota_endtime'] = array('lt', TIMESTAMP);
        $pbundling_model->editBundlingQuotaClose($where);

        $isPlatformStore = check_platform_store() ? true : false;
        $this->assign('isPlatformStore', $isPlatformStore);
        $hasList = $isPlatformStore;
        $bundling_published='';
        if (!$isPlatformStore) {
            // 检查是否已购买套餐
            $where = array();
            $where['store_id'] = session('store_id');
            $bundling_quota = $pbundling_model->getBundlingQuotaInfo($where);
            $this->assign('bundling_quota', $bundling_quota);
            if (!empty($bundling_quota)) {
                // 计算已经发布活动、剩余活动数量
                $bundling_published = $pbundling_model->getBundlingCount(array('store_id' => session('store_id')));
                $bundling_surplus = intval(config('promotion_bundling_sum')) - intval($bundling_published);
                $this->assign('bundling_published', $bundling_published);
                $this->assign('bundling_surplus', $bundling_surplus);

                $hasList = true;
            }
        }

        if ($hasList) {
            // 查询活动
            $where = array();
            $where['store_id'] = session('store_id');
            if (input('param.bundling_name') != '') {
                $where['bl_name'] = array('like', '%' . trim(input('param.bundling_name')) . '%');
            }
            if (is_numeric(input('param.state'))) {
                $where['bl_state'] = input('param.state');
            }
            $bundling_list = $pbundling_model->getBundlingList($where, '*', 'bl_id desc', 10, 0, $bundling_published);
            $bundling_list = array_under_reset($bundling_list, 'bl_id');
            $this->assign('show_page', $pbundling_model->page_info->render());
            if (!empty($bundling_list)) {
                $blid_array = array_keys($bundling_list);

                $bgoods_array = $pbundling_model->getBundlingGoodsList(array('bl_id' => array('in', $blid_array), 'blgoods_appoint' => 1), 'bl_id,goods_id,count(*) as count', 'blgoods_appoint desc', 'bl_id');
                $bgoods_array1 = array_under_reset($bgoods_array, 'goods_id');

                if (!empty($bgoods_array1)) {
                    $goodsid_array = array_keys($bgoods_array1);
                    $goods_array = model('goods')->getGoodsList(array(
                       'goods_id' => array('in', $goodsid_array)), 'goods_id,goods_image');
                    $goods_array = array_under_reset($goods_array, 'goods_id');
                }
                $bgoods_array = array_under_reset($bgoods_array, 'bl_id');

                foreach ($bundling_list as $key => $val) {
                    $bundling_list[$key]['goods_id'] = $bgoods_array[$val['bl_id']]['goods_id'];
                    $bundling_list[$key]['count'] = $bgoods_array[$val['bl_id']]['count'];
                    $bundling_list[$key]['img'] = goods_thumb($goods_array[$bgoods_array[$val['bl_id']]['goods_id']], 60);
                }
            }
            $this->assign('bundling_list', $bundling_list);

            // 状态数组
            $state_array = array(0 => lang('bundling_status_0'), 1 => lang('bundling_status_1'));
            $this->assign('state_array', $state_array);
        }
        $this->setSellerCurMenu('Sellerpromotionbundling');
        $this->setSellerCurItem('index');
        return $this->fetch($this->template_dir . 'index');
    }

    /**
     * 套餐购买
     */
    public function bundling_quota_add()
    {
        if (request()->isPost()) {
            $quantity = intval(input('post.bundling_quota_quantity')); // 购买数量（月）
            $price_quantity = $quantity * intval(config('promotion_bundling_price')); // 扣款数
            if ($quantity <= 0 || $quantity > 12) {
                ds_json_encode(10001,lang('bundling_quota_price_fail'));
            }
            // 实例化模型
            $pbundling_model = model('pbundling');

            $data = array();
            $data['store_id'] = session('store_id');
            $data['store_name'] = session('store_name');
            $data['member_id'] = session('member_id');
            $data['member_name'] = session('member_name');
            $data['blquota_month'] = $quantity;
            $data['blquota_starttime'] = TIMESTAMP;
            $data['blquota_endtime'] = TIMESTAMP + 60 * 60 * 24 * 30 * $quantity;
            $data['blquota_state'] = 1;

            $return = $pbundling_model->addBundlingQuota($data);
            if ($return) {
                // 添加店铺费用记录
                $this->recordStorecost($price_quantity, '购买优惠套装');

                // 添加任务队列
                $end_time = TIMESTAMP + 60 * 60 * 24 * 30 * $quantity;
                $this->addcron(array('exetime' => $end_time, 'exeid' => session('store_id'), 'type' => 3), true);

                $this->recordSellerlog('购买' . $quantity . '套优惠套装，单位元');
                ds_json_encode(10000,lang('bundling_quota_price_succ'));
            }
            else {
                ds_json_encode(10001,lang('bundling_quota_price_fail'));
            }
        }
        // 输出导航
        $this->setSellerCurMenu('Sellerpromotionbundling');
        $this->setSellerCurItem('bundling_quota_add');
        return $this->fetch($this->template_dir . 'quota_add');
    }

    /**
     * 套餐续费
     */
    public function bundling_renew()
    {
        if (request()->isPost()) {
            $pbundling_model = model('pbundling');
            $quantity = intval(input('post.bundling_quota_quantity')); // 购买数量（月）
            $price_quantity = $quantity * intval(config('promotion_bundling_price')); // 扣款数
            if ($quantity <= 0 || $quantity > 12) {
                ds_json_encode(10001,lang('bundling_quota_price_fail'));
            }
            $where = array();
            $where['store_id'] = session('store_id');
            $bundling_quota = $pbundling_model->getBundlingQuotaInfo($where);
            if ($bundling_quota['blquota_endtime'] > TIMESTAMP) {
                // 套餐未超时(结束时间+购买时间)
                $update['blquota_endtime'] = intval($bundling_quota['blquota_endtime']) + 60 * 60 * 24 * 30 * $quantity;
            }
            else {
                // 套餐已超时(当前时间+购买时间)
                $update['blquota_endtime'] = TIMESTAMP + 60 * 60 * 24 * 30 * $quantity;
            }
            $return = $pbundling_model->editBundlingQuotaOpen($update, $where);

            if ($return) {
                // 添加店铺费用记录
                $this->recordStorecost($price_quantity, '购买优惠套装');

                // 添加任务队列
                $this->addcron(array(
                                   'exetime' => $update['blquota_endtime'], 'exeid' => session('store_id'), 'type' => 3
                               ), true);

                $this->recordSellerlog('续费' . $quantity . '套优惠套装，单位元');
                ds_json_encode(10000,lang('bundling_quota_price_succ'));
            }
            else {
                ds_json_encode(10001,lang('bundling_quota_price_fail'));
            }
        }

        $this->setSellerCurMenu('Sellerpromotionbundling');
        $this->setSellerCurItem('bundling_quota_add');
        return $this->fetch($this->template_dir . 'quota_add');
    }

    /**
     * 套餐活动添加
     */
    public function bundling_add()
    {
        /**
         * 实例化模型
         */
        $pbundling_model = model('pbundling');

        // 验证套餐数量
        if (intval(config('promotion_bundling_sum')) != 0 && !isset($_REQUEST['bundling_id'])) {
            $count = $pbundling_model->getBundlingCount(array('store_id' => session('store_id')));
            if (intval(config('promotion_bundling_sum')) <= intval($count)) {
                $this->error(lang('bundling_add_fail_quantity_beyond'));
                ds_json_encode(10001,lang('goods_index_consult_fail'));
            }
        }

        if (request()->isPost()) {
            // 插入套餐
            $data = array();
            
            $bl_id = intval(input('post.bundling_id'));
            
            if ($bl_id<=0) {
                $data['bl_name'] = input('post.bundling_name');
                $data['store_id'] = session('store_id');
                $data['store_name'] = session('store_name');
                $data['bl_discount_price'] = input('post.discount_price');
                $data['bl_freight_choose'] = input('post.bundling_freight_choose');
                $data['bl_freight'] = input('post.bundling_freight');
                $data['bl_state'] = intval(input('post.state'));
                $bl_id = $pbundling_model->addBundling($data);
                if (!$bl_id) {
                    ds_json_encode(10001,lang('ds_common_op_fail'));
                }
            }else{
                $condition['bl_id'] = $bl_id;
                $condition['store_id'] = session('store_id');
                $data['bl_name'] = input('post.bundling_name');
                $data['bl_discount_price'] = input('post.discount_price');
                $data['bl_freight_choose'] = input('post.bundling_freight_choose');
                $data['bl_freight'] = input('post.bundling_freight');
                $data['bl_state'] = intval(input('post.state'));
                $pbundling_model->editBundling($data,$condition);
            }
            

            // 插入套餐商品
            $goods_model = model('goods');
            $data_goods = array();
            $appoint_goodsid = false;
            if (input('post.bundling_id')) {
                $pbundling_model->delBundlingGoods(array('bl_id' => $bl_id));
            }
            
            $goods_array = input('post.goods/a');#获取数组
            if (!empty($goods_array) && is_array($goods_array)) {
                foreach ($goods_array as $key => $val) {
                    // 验证是否为本店铺商品
                    $goods_info = $goods_model->getGoodsInfoByID($val['gid']);
                    if (empty($goods_info) || $goods_info['store_id'] != session('store_id')) {
                        continue;
                    }
                    $data = array();
                    $data['bl_id'] = $bl_id;
                    $data['goods_id'] = $goods_info['goods_id'];
                    $data['goods_name'] = $goods_info['goods_name'];
                    $data['goods_image'] = $goods_info['goods_image'];
                    $data['blgoods_price'] = ds_price_format($val['price']);
                    $data['blgoods_appoint'] = intval($val['appoint']);
                    if (!$appoint_goodsid && intval($val['appoint']) == 1) {
                        $appoint_goodsid = intval($val['gid']);
                    }
                    $data_goods[] = $data;
                }
            }
            // 插入数据
            $return = $pbundling_model->addBundlingGoodsAll($data_goods);

            if (!input('post.bundling_id') && !$appoint_goodsid) {
                // 自动发布动态
                // bl_id,bl_name,image_path,bl_discount_price,bl_freight_choose,bl_freight,store_id
                $data_array = array();
                $data_array['bl_id'] = $return;
                $data_array['goods_id'] = $appoint_goodsid;
                $data_array['bl_name'] = input('post.bundling_name');
                $data_array['bl_img'] = '';
                $data_array['bl_discount_price'] = $data['bl_discount_price'];
                $data_array['bl_freight_choose'] = $data['bl_freight_choose'];
                $data_array['bl_freight'] = $data['bl_freight'];
                $data_array['store_id'] = session('store_id');
                $this->storeAutoShare($data_array, 'bundling');
            }

            $this->recordSellerlog('添加优惠套装，名称：' . input('post.bundling_name') . ' id：' . $return);

            ds_json_encode(10000,lang('ds_common_op_succ'));
        } else {
            // 是否能使用编辑器
            if (check_platform_store()) { // 平台店铺可以使用编辑器
                $editor_multimedia = true;
            } else {    // 三方店铺需要
                $editor_multimedia = false;
                if ($this->store_grade['storegrade_function'] == 'editor_multimedia') {
                    $editor_multimedia = true;
                }
            }
            $this->assign('editor_multimedia', $editor_multimedia);

            if (intval(input('param.bundling_id')) > 0) {
                $bundling_info = $pbundling_model->getBundlingInfo(array('bl_id' => intval(input('param.bundling_id')), 'store_id' => session('store_id')));
                // halt($bundling_info);
                $this->assign('bundling_info', $bundling_info);
                // 验证是否属于自己的组合套餐
                if (empty($bundling_info['store_id'])) {
                    $this->error(lang('wrong_argument'), url('Sellerpromotionbundling/index'));
                }

                $b_goods_list = $pbundling_model->getBundlingGoodsList(array('bl_id' => intval(input('param.bundling_id'))));
                if (!empty($b_goods_list)) {
                    $goodsid_array = array();
                    foreach ($b_goods_list as $val) {
                        $goodsid_array[] = $val['goods_id'];
                    }
                    $goods_list = model('goods')->getGoodsList(array('goods_id' => array('in', $goodsid_array)), 'goods_id,goods_price,goods_image,goods_name');
                    $this->assign('goods_list', array_under_reset($goods_list, 'goods_id'));
                }
                $this->assign('b_goods_list', $b_goods_list);
                // 输出导航
                $this->setSellerCurMenu('Sellerpromotionbundling');
                $this->setSellerCurItem('bundling_edit');
            } else {
                // 输出导航
                $this->setSellerCurMenu('Sellerpromotionbundling');
                $this->setSellerCurItem('bundling_add');
            }
            return $this->fetch($this->template_dir . 'bundling_add');
        }
    }

    /**
     * 套餐活动添加商品
     */
    public function bundling_add_goods()
    {
        /**
         * 实例化模型
         */
        $goods_model = model('goods');

        // where条件
        $where = array();
        $where['store_id'] = session('store_id');
        if (intval(input('param.storegc_id')) > 0) {
            $where['goods_stcids'] = array('like', '%,' . intval(input('param.storegc_id')) . ',%');
        }
        if (trim(input('param.keyword')) != '') {
            $where['goods_name'] = array('like', '%' . trim(input('param.keyword')) . '%');
        }

        $goods_list = $goods_model->getGoodsListForPromotion($where, '*', 8, 'bundling');
        $this->assign('show_page', $goods_model->page_info->render());
        $this->assign('goods_list', $goods_list);

        /**
         * 商品分类
         */
        $store_goods_class = model('storegoodsclass')->getClassTree(array('store_id' => session('store_id'),'storegc_state' => '1'));
        $this->assign('store_goods_class', $store_goods_class);

        return $this->fetch($this->template_dir . 'bundling_add_goods');
    }

    /**
     * 删除优惠套装活动
     */
    public function drop_bundling()
    {
        /**
         * 参数验证
         */
        $blids = trim(input('param.bundling_id'));
        if (empty($blids)) {
            ds_json_encode(10001,lang('param_error'));
        }

        $return = model('pbundling')->delBundling($blids, session('store_id'));
        halt($return);
        if ($return) {
            $this->recordSellerlog('删除优惠套装，套餐id：' . $blids);
            ds_json_encode(10000,lang('bundling_delete_success'));
        }
        else {
            ds_json_encode(10001,lang('bundling_delete_fail'));
        }
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $name 当前导航的name
     * @return
     */
    protected function getSellerItemList()
    {
        $menu_array = array();
        switch (request()->action()) {
            case 'index':
            case 'bundling_quota_list':
                $menu_array = array(
                    array(
                        'name' => 'index', 'text' => lang('bundling_list'),
                        'url' => url('Sellerpromotionbundling/index')
                    )
                );
                break;
            case 'bundling_quota_add':
                $menu_array = array(
                    array(
                        'name' => 'index', 'text' => lang('bundling_list'),
                        'url' => url('Sellerpromotionbundling/index')
                    ), array(
                        'name' => 'bundling_quota_add', 'text' => lang('bundling_quota_add'),
                        'url' => url('Sellerpromotionbundling/bundling_quota_add')
                    )
                );
                break;
            case 'bundling_renew':
                $menu_array = array(
                    array(
                        'name' => 'index', 'text' => lang('bundling_list'),
                        'url' => url('Sellerpromotionbundling/index')
                    ), array(
                        'name' => 'bundling_renew', 'text' => '套餐续费',
                        'url' => url('Sellerpromotionbundling/bundling_renew')
                    )
                );
                break;
            case 'bundling_add':
                $menu_array = array(
                    array(
                        'name' => 'index', 'text' => lang('bundling_list'),
                        'url' => url('Sellerpromotionbundling/index')
                    ), array(
                        'name' => 'bundling_add', 'text' => lang('bundling_add'),
                        'url' => url('Sellerpromotionbundling/bundling_add')
                    )
                );
                break;
            case 'bundling_edit':
                $menu_array = array(
                    array(
                        'name' => 'index', 'text' => lang('bundling_list'),
                        'url' => url('Sellerpromotionbundling/index')
                    ), array(
                        'name' => 'bundling_edit', 'text' => lang('bundling_edit'),
                        'url' => url('Sellerpromotionbundling/bundling_edit')
                    )
                );
                break;
        }
        return $menu_array;
    }
}