<?php
namespace app\admin\controller;

use think\Lang;

class Snsmember extends AdminControl
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        Lang::load(APP_PATH . 'admin/lang/'.config('default_lang').'/snsmember.lang.php');
    }

    /**
     * 标签列表
     */
    public function index() {
        // 实例化模型
        $snsmember_model = model('snsmember');
        $tag_list = $snsmember_model->getSnsmembertagList('mtag_sort asc', 10);
        $this->assign('showpage', $snsmember_model->page_info->render());
        $this->assign('tag_list', $tag_list);
        $this->setAdminCurItem('index');
        return $this->fetch();
    }

    /**
     * 添加标签
     */
    public function tag_add(){
        if (request()->isPost()) {
            /**
             * 验证
             */
            $data = [
                'membertag_name' => input('post.membertag_name'), 'membertag_sort' => input('post.membertag_sort'),
            ];

            $snsmember_validate = validate('snsmember');
            if (!$snsmember_validate->scene('tag_add')->check($data)){
                $this->error($snsmember_validate->getError());
            }
            else {
                /**
                 * 上传图片
                 */
                $default_dir = BASE_UPLOAD_PATH . DS . ATTACH_PATH . '/membertag';
                $img='';
                if (!empty($_FILES['membertag_img']['name'])) {
                    $file = request()->file('membertag_img');
                    $info = $file->rule('uniqid')->validate(['ext' =>ALLOW_IMG_EXT])->move($default_dir);
                    if ($info) {
                        $img = $info->getFilename();
                    }else {
                        $this->error($file->getError());
                    }
                }
                $insert = array(
                    'mtag_name' => input('post.membertag_name'), 
                    'mtag_sort' => intval(input('post.membertag_sort')),
                    'mtag_recommend' => intval(input('post.membertag_recommend')),
                    'mtag_desc' => trim(input('post.membertag_desc')), 
                    'mtag_img' => $img
                );
                $snsmember_model = model('snsmember');
                $result = $snsmember_model->addSnsmembertag($insert);
                if ($result) {
                    $this->log(lang('ds_add').lang('sns_member_tag') . '[' . input('post.membertag_name') . ']', 1);
                    dsLayerOpenSuccess(lang('ds_common_op_succ'));
                }
                else {
                    $this->error(lang('ds_common_op_fail'));
                }
            }
        }else{
            return $this->fetch();
        }
    }

    /**
     * 编辑标签
     */
    public function tag_edit()
    {
        // 实例化模型
        if (request()->isPost()) {
            /**
             * 验证
             */
            $data = [
                'membertag_name' => input('post.membertag_name'), 'membertag_sort' => input('post.membertag_sort'),
            ];
            $snsmember_validate = validate('snsmember');
            if (!$snsmember_validate->scene('tag_edit')->check($data)){
                $this->error($snsmember_validate->getError());
            }
            else {
                /**
                 * 上传图片
                 */
                $default_dir = BASE_UPLOAD_PATH . DS . ATTACH_PATH . '/membertag';

                $input='';
                if (!empty($_FILES['membertag_img']['name'])) {
                    $file = request()->file('membertag_img');
                    $info = $file->rule('uniqid')->validate(['ext' =>ALLOW_IMG_EXT])->move($default_dir);
                    if (!$info) {
                        $this->error($file->getError());
                    }
                    else {
                        $input = $info->getFilename();
                    }
                }
                $update = array();
                $update['mtag_id'] = intval(input('post.id'));
                $update['mtag_name'] = trim(input('post.membertag_name'));
                $update['mtag_sort'] = intval(input('post.membertag_sort'));
                $update['mtag_recommend'] = intval(input('post.membertag_recommend'));
                $update['mtag_desc'] = trim(input('post.membertag_desc'));
                $update['mtag_img'] = $input;

                $snsmember_model = model('snsmember');
                $result = $snsmember_model->editSnsmembertag($update);
                if ($result>=0) {
                    $this->log(lang('ds_edit').lang('sns_member_tag') . '[' . input('post.membertag_name') . ']', 1);
                    dsLayerOpenSuccess(lang('ds_common_op_succ'));
                }
                else {
                    $this->error(lang('ds_common_op_fail'));
                }
            }
        }  else {
            // 验证
            $mtag_id = intval(input('param.id'));
            if ($mtag_id <= 0) {
                $this->error(lang('param_error'));
            }
            $snsmember_model = model('snsmember');
            $mtag_info = $snsmember_model->getOneSnsmembertag($mtag_id);
            if (empty($mtag_info)) {
                $this->error(lang('param_error'));
            }
            $this->setAdminCurItem('tag_edit');
            //halt($mtag_info);
            $this->assign('mtag_info', $mtag_info);
            return $this->fetch();
        }
    }

    /**
     * 删除标签
     */
    public function tag_del()
    {
        $mtag_id = input('param.id');
        $mtag_id_array = ds_delete_param($mtag_id);
        if ($mtag_id_array == FALSE) {
            ds_json_encode('10001', lang('param_error'));
        }
        $condition = array();
        $condition['mtag_id'] = array('in', $mtag_id_array);
        $snsmember_model = model('snsmember');
        $result = $snsmember_model->delSnsmembertag($condition);
        if ($result) {
            $this->log(lang('ds_del').lang('sns_member_tag') . '[ID:' . $mtag_id . ']', 1);
            ds_json_encode('10000', lang('ds_common_del_succ'));
        }
        else {
            ds_json_encode('10001', lang('ds_common_del_fail'));
        }
    }

    /**
     * 标签所属会员列表
     */
    public function tag_member()
    {
        // 验证
        $mtag_id = intval(input('param.id'));
        if ($mtag_id <= 0) {
            $this->error(lang('param_error'));
        }
        $snsmember_model = model('snsmember');
        $count = $snsmember_model->getSnstagmemberCount(array('mtag_id' => $mtag_id));
        $tagmember_list = $snsmember_model->getSnsmtagmemberList(array('s.mtag_id' => $mtag_id),'s.*,m.member_avatar,m.member_name',10,'s.recommend desc, s.member_id asc',$count);
        $this->assign('tagmember_list', $tagmember_list);
        $this->assign('showpage', $snsmember_model->page_info->render());
        $this->setAdminCurItem('tag_member');
        return $this->fetch();
    }

    /**
     * 删除添加标签会员
     */
    public function mtag_del()
    {
        $snsmember_model = model('snsmember');
        $mtag_id = intval(input('param.id'));
        $member_id = intval(input('param.mid'));
        if ($mtag_id <= 0 || $member_id <= 0) {
            $this->error(lang('miss_argument'));
        }
        // 条件
        $where = array(
            'mtag_id' => $mtag_id,
            'member_id' => $member_id
        );
        $result = $snsmember_model->delSnsmtagmember($where);
        if ($result) {
            $this->log(lang('ds_del').lang('sns_member_tag') . '[ID:' . $mtag_id . ']', 1);
            $this->success(lang('ds_common_del_succ'));
        }
        else {
            $this->error(lang('ds_common_del_fail'));
        }
    }

    /**
     * ajax修改
     */
    public function ajax()
    {
        // 实例化模型
        $snsmember_model = model('snsmember');
        switch (input('param.branch')) {
            /**
             * 更新名称、排序、推荐
             */
            case 'membertag_name':
            case 'membertag_sort':
            case 'membertag_recommend':
                $update = array(
                    'mtag_id' => intval(input('param.id')), input('param.column') => input('param.value')
                );
                $snsmember_model->editSnsmembertag($update);
                echo 'true';
                break;
            /**
             * sns_mtagmember表推荐
             */
            case 'mtagmember_recommend':
                list($where['mtag_id'], $where['member_id']) = explode(',', input('param.id'));
                $update = array(
                    input('param.column') => input('param.value')
                );
                $snsmember_model->editSnsmtagmember($where,$update);
                echo 'true';
                break;
        }
    }

    protected function getAdminItemList()
    {
        $menu_array = array(
            array(
                'name' => 'index', 'text' => lang('sns_member_tag_manage'), 'url' => url('Snsmember/index')
            ), array(
                'name' => 'tag_add', 
                'text' => lang('ds_new'), 
                'url' => "javascript:dsLayerOpen('".url('Snsmember/tag_add')."','".lang('ds_new')."')"
            ),
        );
        if(request()->action()== 'tag_member') {
            $menu_array[] = array(
                'name' => 'tag_member', 'text' => lang('sns_member_member_list'), 'url' => ('#')
            );
        }
        return $menu_array;
    }
}