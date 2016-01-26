<?php if ( ! defined('BASEPATH')) exit();
class Region extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @description 地区列表
     * @author
     * @final
     * @param int $pid 父级地区id
     */
    public function index($pid = 0)
    {
        if (empty($pid)) $pid = 1;
        $data['region'] = RegionModel::get_region($pid);
        $data['region_parents']  = RegionModel::get_region_parents($pid);
        $data['region_children'] = RegionModel::get_regions($pid, TRUE);

        // 模版
        $this->load->view('region/index', $data);
    }

    /**
     * @description 地区编辑页面
     * @author
     * @final
     * @param int $id 地区id
     */
    public function edit($id = 0)
    {
        if ( ! $this->check_power('region_manage')) return;
        $this->load->helper('form');
        $data['region'] = RegionModel::get_region(intval($id));

        if (empty($data['region']))
        {
            message('地区不存在');
            return;
        }

        // 模版
        $this->load->view('region/edit', $data);
    }

    /**
     * @description 地区编辑页面
     * @author
     * @final
     * @param int $pid 父级地区id
     */
    public function add($pid = 0)
    {
        if ( ! $this->check_power('region_manage')) return;
        $this->load->helper('form');
        $data['parent'] = RegionModel::get_region(intval($pid));

        // 模版
        $this->load->view('region/add', $data);
    }

    /**
     * @description 地区编辑入库操作
     * @author
     * @final
     * @param int $region_id 地区id
     * @param int $parent_id 父级地区id
     * @param string $region_name 地区名称
     * @param int $status  地区状态
     */
    public function update()
    {
        if ( ! $this->check_power('region_manage')) return;

        $region_id = intval($this->input->post('region_id'));
        $parent_id = intval($this->input->post('parent_id'));
        $data['region_name'] = trim($this->input->post('region_name'));
        $data['status'] = intval($this->input->post('status')) ? 1 : 0;
        if ( empty($data['region_name']) )
        {
            message('地区名称不能为空');
            return;
        }
        $this->db->update('region', $data, array('region_id' => $region_id));
        admin_log('edit', 'region', $region_id);

        redirect('admin/region/index/');
    }

    /**
     * @description 地区添加入库操作
     * @author
     * @final
     * @param int $parent_id 父级地区id
     * @param string $region_name 地区名称
     * @param int $status  地区状态
     */
    public function create()
    {
        if ( ! $this->check_power('region_manage')) return;

        $data['parent_id'] = intval($this->input->post('parent_id'));
        $data['region_name'] = trim($this->input->post('region_name'));
        $data['status'] = intval($this->input->post('status')) ? 1 : 0;
        if ( empty($data['region_name']) )
        {
            message('地区名称不能为空');
            return;
        }

        $parent = RegionModel::get_region($data['parent_id']);
        $data['region_type'] = $parent ? $parent['region_type'] + 1 : 0;

        $this->db->insert('region', $data);

        admin_log('add', 'region', $this->db->insert_id());

        redirect('admin/region/index/');
    }

    /**
     * @description 删除地区
     * @author
     * @final
     * @param int $id 地区id
     */
    public function delete($id)
    {
        if ( ! $this->check_power('region_manage')) return;
        $id = intval($id);
        //echo $id;
        //$query = $this->db->query("SELECT COUNT(*) FROM {pre}region WHERE parent_id='$id'");
         $query = $this->db->query("SELECT * FROM {pre}region WHERE parent_id='$id'");
        //echo $query->num_rows();
        //die;
        if ($query->num_rows())
        {
            message('该地区还存在下级地区，不能删除！');
            return;
        }
        $this->db->delete('region', array('region_id'=>$id));
        admin_log('delete', 'region', $id);

        redirect('admin/region/index/'.$region['region_id']);
    }

    /**
     * @description 批量删除地区
     * @author
     * @final
     * @param array|int $ids 地区id
     * TODO
     */
    /*
    public function delete_batch()
    {
        if ( ! $this->check_power('region_manage')) return;

    }
    */


    /**
     * @description 批量删除地区
     * @author zh
     * @final
     * @param array|int $ids 地区id
     */
    public function delete_batch()
    {
        if ( ! $this->check_power('region_manage')) return;

        $ids = (array)$this->input->post('ids');
        if ($ids)
        {

            foreach ($ids as $k=>$v)
            {


                $query = $this->db->query("SELECT * FROM {pre}region WHERE parent_id='$v'");
                if ($query->num_rows())
                {
                    message('该地区还存在下级地区，不能删除！');
                    return;
                }
                else
                {

                    $this->db->delete('region', array('region_id'=>$v));
                    admin_log('delete', 'region', $id);
                }


            }

           // $this->db->where_in('region_id', $ids)->delete('region');
            //admin_log('delete', 'region', implode(',', $ids));

            message('地区删除成功！', 'admin/region/index');
        }
        else
        {
            message('请选择要删除的地区');
        }
    }



    // 导出js缓存
    public function export()
    {
        if ( ! $this->check_power('region_manage')) return;
        $provinces = RegionModel::get_regions(1, FALSE);
        $data = array();
        $cities = array();
        $areas = array();
        foreach ($provinces as $province)
        {
            $arr = array(
                $province['region_id'], $province['region_name'], $province['parent_id']
            );
            $data[] = $arr;
            $cities = array_merge($cities, 
                RegionModel::get_regions($province['region_id'], FALSE));
        }
        unset($provinces);

        foreach ($cities as $city)
        {
            $arr = array(
                $city['region_id'],$city['region_name'],$city['parent_id']
            );
            $data[] = $arr;
            $areas = array_merge($areas, 
                RegionModel::get_regions($city['region_id'], FALSE));
        }
        unset($cities);

        foreach ($areas as $area)
        {
            $arr = array(
                $area['region_id'],$area['region_name'],$area['parent_id']
            );
            $data[] = $arr;
        }
        unset($areas);


        $jingtai=str_replace("/system/", '', BASEPATH);

        $fp = fopen($jingtai."/cache/address.js", "w");//文件被清空后再写入
        if($fp)
        {

            $flag=fwrite($fp,"_regions =". json_encode($data));
            if(!$flag)
            {

                message('写入文件失败！', 'admin/region/index');
            }
            else
            {
                message('写入文件成功！', 'admin/region/index');

            }

        }
        else
        {
            message('打开文件失败！', 'admin/region/index');

        }
        fclose($fp);

       // echo json_encode($data);
    }

}
