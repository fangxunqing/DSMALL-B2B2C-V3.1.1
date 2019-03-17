<?php

namespace app\home\controller;

use think\Lang;

class Sellertransport extends BaseSeller
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'home/lang/'.config('default_lang').'/sellertransport.lang.php');
        $type = input('param.type');
        if ($type != '' && $type != 'select') {
            $type = 'select';
        }
    }

    /**
     * 售卖区域列表
     *
     */
    public function index()
    {
        $transport_model = model('transport');
        $transport_list = $transport_model->getTransportList(array('store_id' => session('store_id')), 4);
        $extend = '';
        if (!empty($transport_list) && is_array($transport_list)) {
            $transport = array();
            foreach ($transport_list as $v) {
                if (!array_key_exists($v['transport_id'], $transport)) {
                    $transport[$v['transport_id']] = $v['transport_title'];
                }
            }
            $extend = $transport_model->getTransportextendList(array('transport_id' => array('in', array_keys($transport))));
            //halt($extend);
            // 整理
            if (!empty($extend)) {
                $tmp_extend = array();
                foreach ($extend as $val) {
                    $tmp_extend[$val['transport_id']]['data'][] = $val;
                    $tmp_extend[$val['transport_id']]['price'] = $val['transportext_sprice'];
                }
                $extend = $tmp_extend;
            }
        }
        //dump($transport_list);
        //dump($extend);exit;
        /**
         * 页面输出
         */
        $this->assign('transport_list', $transport_list);
        $this->assign('extend', $extend);
        $this->assign('show_page', $transport_model->page_info->render());
        $this->setSellerCurMenu('sellertransport');
        $this->setSellerCurItem('index');
        return $this->fetch($this->template_dir . 'index');
    }

    /**
     * 新增售卖区域
     *
     */
    public function add()
    {
        $areas = model('area')->getAreas();
        $this->assign('areas', $areas);
        $this->setSellerCurMenu('sellertransport');
        $this->setSellerCurItem('index');
        return $this->fetch($this->template_dir . 'add');
    }

    public function edit()
    {
        $id = intval(input('get.id'));
        $transport_model = model('transport');
        $transport = $transport_model->getTransportInfo(array('transport_id' => $id));
        $extend = $transport_model->getExtendInfo(array('transport_id' => $id));
        $this->assign('transport', $transport);
        $this->assign('extend', $extend);

        $areas = model('area')->getAreas();

        $this->assign('areas', $areas);

        $this->setSellerCurItem('index');
        $this->setSellerCurMenu('sellertransport');
        return $this->fetch($this->template_dir . 'add');
    }

    public function delete()
    {
        $id = intval(input('param.id'));
        $transport_model = model('transport');
        $transport = $transport_model->getTransportInfo(array('transport_id' => $id));
        if ($transport['store_id'] != session('store_id')) {
            $this->error(lang('transport_op_fail'));
        }
        //查看是否正在被使用
        if ($transport_model->isTransportUsing($id)) {
            $this->error(lang('transport_op_using'));
        }
        if ($transport_model->delTansport(array('transport_id' => $id))) {
            header('location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
        else {
            $this->error(lang('transport_op_fail'));
        }
    }

    public function cloned()
    {
        $id = intval(input('get.id'));
        $transport_model = model('transport');
        $transport = $transport_model->getTransportInfo(array('transport_id' => $id));
        unset($transport['transport_id']);
        $transport['transport_title'] .= lang('transport_clone_name');
        $transport['transport_updatetime'] = time();

        try {
            $transport_model->startTrans();
            $insert = $transport_model->addTransport($transport);
            if ($insert) {
                $extend = $transport_model->getTransportextendList(array('transport_id' => $id));
                foreach ($extend as $k => $v) {
                    foreach ($v as $key => $value) {
                        $extend[$k]['transport_id'] = $insert;
                    }
                    unset($extend[$k]['transportext_id']);
                }
                $insert = $transport_model->addExtend($extend);
            }
            if (!$insert) {
                exception(lang('transport_op_fail'));
            }
            $transport_model->commit();
            header('location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        } catch (Exception $e) {
            $transport_model->rollback();
            $this->error($e->getMessage(), $_SERVER['HTTP_REFERER']);
        }
    }

    public function save()
    {

        if (!request()->isPost()) {
            return false;
        }

        $trans_info = array();
        $trans_info['transport_title'] = input('post.title');
        $trans_info['send_tpl_id'] = 1;
        $trans_info['store_id'] = session('store_id');
        $trans_info['transport_updatetime'] = TIMESTAMP;

        $transport_model = model('transport');

        $transport_id = input('post.transport_id');
        if (is_numeric($transport_id)) {
            //编辑时，删除所有附加表信息
            $transport_id = intval($transport_id);
            $transport_model->editTransport($trans_info, array('transport_id' => $transport_id));
            $transport_model->delTransportextend($transport_id);
        }
        else {
            //新增
            $transport_id = $transport_model->addTransport($trans_info);
        }

        $post = input('post.');#获取POST 数据
        
        $trans_list = array();
        $areas = !empty($post['areas']['kd']) ? $post['areas']['kd'] : '';
        $special = !empty($post['special']['kd']) ? $post['special']['kd'] : '';

        //默认运费
        $default = $post['default']['kd'];
        $trans_list[]=array(
            'transportext_area_id' =>'',
            'transportext_area_name' =>'默认运费',
            'transportext_sprice' =>$default['postage'],
            'transport_id'  =>$transport_id,
            'transport_title' =>input('post.title'),
            'transportext_snum'   =>$default['start'],
            'transportext_xnum'   =>$default['plus'],
            'transportext_xprice'  =>$default['postageplus'],
            'transportext_is_default' =>'1',
            'transportext_top_area_id' =>''
        );

        if (is_array($special)) {
            foreach ($special as $key => $value) {
                $tmp = array();
                if (empty($areas[$key])) {
                    continue;
                }
                $areas[$key] = explode('|||', $areas[$key]);
                $tmp['transportext_area_id'] = ',' . $areas[$key][0] . ',';
                $tmp['transportext_area_name'] = $areas[$key][1];
                $tmp['transportext_sprice'] = $value['postage'];
                $tmp['transport_id'] = $transport_id;
                $tmp['transport_title'] = input('post.title');
                $tmp['transportext_snum'] = $value['start'];
                $tmp['transportext_xnum'] = $value['plus'];
                $tmp['transportext_xprice'] =$value['postageplus'];
                $tmp['transportext_is_default'] ='0';
                //计算省份ID
                $province = array();
                $tmp1 = explode(',', $areas[$key][0]);
                if (!empty($tmp1) && is_array($tmp1)) {
                    $city = model('area')->getCityProvince();
                    foreach ($tmp1 as $t) {
                        $pid = isset($city[$t]) ? $city[$t] : array();
                        if (!in_array($pid, $province) && !empty($pid)) {
                            $province[] = $pid;
                        }
                    }
                }
                if (count($province) > 0) {
                    $tmp['transportext_top_area_id'] = ',' . implode(',', $province) . ',';
                }
                else {
                    $tmp['transportext_top_area_id'] = '';
                }
                $trans_list[] = $tmp;
            }
        }
        $result = $transport_model->addExtend($trans_list);
        $type = input('param.type');
        if ($result) {
            $this->redirect('sellertransport/index', ['type' => $type]);
        }
        else {
            $this->error(lang('transport_op_fail'));
        }
    }

    /**
     * 货到付款地区设置
     *
     */
    public function offpay_area()
    {
        if (!check_platform_store()) {
            ds_json_encode(10001,'本功能仅限平台自营店使用');
        }
        $offpayarea_model = model('offpayarea');
        $area_model = model('area');
        $store_id = session('store_id');

        if (request()->isPost()) {
            
            $county_array = input('post.county');#获取字符串
            if (!preg_match('/^[\d,]+$/', $county_array)) {
                $county_array = '';
            }
            //内置自营店ID
            $area_info = $offpayarea_model->getOffpayareaInfo(array('store_id' => $store_id));
            $data = array();
            $county = trim($county_array, ',');
            $county_array = explode(',', $county);
            $all_array = array();

            $province_array = input('post.province/a');#获取数组
            if (!empty($province_array) && is_array($province_array)) {
                foreach ($province_array as $v) {
                    $all_array[$v] = $v;
                }
            }

            $city_array = input('post.city/a');#获取数组
            if (!empty($city_array) && is_array($city_array)) {
                foreach ($city_array as $v) {
                    $all_array[$v] = $v;
                }
            }


            foreach ($county_array as $pid) {
                $all_array[$pid] = $pid;
                $temp = $area_model->getChildsByPid($pid);
                if (!empty($temp) && is_array($temp)) {
                    foreach ($temp as $v) {
                        $all_array[$v] = $v;
                    }
                }
            }

            $all_array = array_values($all_array);
            $data['area_id'] = serialize($all_array);
            if (!$area_info) {
                $data['store_id'] = $store_id;
                $result = $offpayarea_model->addOffpayarea($data);
            }
            else {
                $result = $offpayarea_model->editOffpayarea(array('store_id' => $store_id), $data);
            }
            if ($result) {
                ds_json_encode(10000,'保存成功');
            }
            else {
                ds_json_encode(10001,'保存失败');
            }
        }else  {
            //取出支持货到付款的县ID及上级市ID
            $parea_info = $offpayarea_model->getOffpayareaInfo(array('store_id' => $store_id));
            if (!empty($parea_info['area_id'])) {
                $parea_ids = @unserialize($parea_info['area_id']);
            }
            if (empty($parea_ids)) {
                $parea_ids = array();
            }

            $this->assign('areaIds', $parea_ids);

            $area_model = model('area');
            $areas = $area_model->getAreas();
            $this->assign('areas', $areas);
            //取出支持货到付款县ID的上级市ID
            $city_checked_child_array = array();

            foreach ($parea_ids as $i) {
                if (isset($areas['parent'][$i])) {
                    $city_checked_child_array[$areas['parent'][$i]][] = $i;
                }
            }
            $this->assign('city_checked_child_array', $city_checked_child_array);

            //市级下面的县是不是全部支持货到付款，如果全部支持，默认选中
            //如果其中部分县支持货到付款，默认不选中但显示一个支付到付县的数量
            //格式 city_id => 下面支持到付的县ID数量
            $city_count_array = array();

            //格式 city_id => 是否选中true/false
            $city_checked_array = array();

            foreach ($city_checked_child_array as $city_id => $c) {
                $city_count_array[$city_id] = count($areas['children'][$city_id]);

                $c = count($c);
                if ($c > 0 && $c == $city_count_array[$city_id]) {
                    $city_checked_array[$city_id] = true;
                }
            }

            $this->assign('city_count_array', $city_count_array);
            $this->assign('city_checked_array', $city_checked_array);

            //计算哪些省需要默认选中(即该省下面的所有县都支持到付，即所有市都是选中状态)
            $province_checked_array = array();
            foreach ($areas['children'][0] as $province_id) {
                $b = true;

                if (is_array($areas['children'][$province_id])) {
                    foreach ($areas['children'][$province_id] as $city_id) {
                        if (empty($city_checked_array[$city_id])) {
                            $b = false;
                            break;
                        }
                    }
                }
                if ($b) {
                    $province_checked_array[$province_id] = true;
                }
            }
            $this->assign('province_checked_array', $province_checked_array);

            $area_array_json = json_encode($area_model->getAreaArrayForJson());
            $this->assign('area_array_json', $area_array_json);

            $this->setSellerCurMenu('sellertransport');
            $this->setSellerCurItem('offpay_area');
            return $this->fetch($this->template_dir . 'offpay_area');
        }
    }

    /**
     * 用户中心右边，小导航
     *
     * @param string $menu_type 导航类型
     * @param string $name 当前导航的name
     *
     * @return
     */
    protected function getSellerItemList()
    {
        $menu_array = array(
            array(
                'name' => 'transport', 'text' => lang('ds_member_path_postage'), 'url' => url('Sellertransport/index')
            ),
        );
        $menu_array[] = array(
            'name' => 'offpay_area', 'text' => '配送地区', 'url' => url('Sellertransport/offpay_area')
        );

        return $menu_array;
    }
}