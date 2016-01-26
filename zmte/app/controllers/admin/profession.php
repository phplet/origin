<?php
/**
 * 职业管理Controller
 * @author TCG
 * @final  2015-10-21 13:57 
 */
class Profession extends A_Controller
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function index()
    {
        $param['profession_name'] = trim($this->input->get('profession_name'));
        $param['profession_emerging'] = implode(',', $this->input->get('profession_emerging'));
        
        $size   = C('default_perpage_num');
        $page   = isset($_GET['page']) && intval($_GET['page'])>1 ? intval($_GET['page']) : 1;
        $offset = ($page - 1) * $size;

        $total = ProfessionModel::professionListCount($param);
        if ($total)
        {
            $data['list'] = ProfessionModel::professionList($param, $page, $size);
        }
        
        // 分页
        $purl = site_url('admin/profession/index/') . ($param ? '?'.implode('&',$param) : '');
        $data['pagination'] = multipage($total, $size, $page, $purl);
        $data['search'] = $param;
        $data['priv_edit'] = $this->check_power_new('profession_edit', FALSE);
        $data['priv_delete'] = $this->check_power_new('profession_remove', FALSE);
        $data['priv_import'] = $this->check_power_new('profession_import', FALSE);
        
        $this->load->view('profession/index', $data);
    }
    
    public function edit($profession_id = null)
    {
        if ($profession_id)
        {
            $data['profession'] = ProfessionModel::professionInfo($profession_id);
        }
        
        $this->load->view('profession/edit', $data);
    }
    
    public function save()
    {
        $param['profession_id'] = intval($this->input->post('profession_id'));
        $param['profession_name'] = htmlspecialchars(trim($this->input->post('profession_name')));
        $param['profession_emerging'] = intval($this->input->post('profession_emerging'));
        $param['profession_explain'] = htmlspecialchars(trim($this->input->post('profession_explain')));
        
        try 
        {
            if ($param['profession_id'])
            {
                $result = ProfessionModel::setProfession($param);
                admin_log('edit', 'profession', $param['profession_id']);
            }
            else 
            {
                $result = ProfessionModel::addProfession($param);
                admin_log('add', 'profession', $result);
            }
            
            message(($param['profession_id'] ? '编辑' : '新增') . ($result ? '成功' : '失败'), 
                '/admin/profession/index');
        }
        catch (Exception $e)
        {
            message($e->getMessage());
        }
    }
    
    public function remove($profession_id)
    {
        if (!$profession_id 
            && $profession_id = $this->input->get('profession_ids'))
        {
            $profession_id = implode(',', $profession_id);
        }
        
        if (!Validate::isJoinedIntStr($profession_id))
        {
            message('请选择需要删除的职业');
        }
        
        if (ProfessionModel::removeProfession($profession_id))
        {
            admin_log('delete', 'profession', $profession_id);
            message('删除成功', '/admin/profession/index');
        }
        else
        {
            message('删除失败', '/admin/profession/index');
        }
    }
    
    public function import()
    {
        $this->load->view('profession/import');
    }
    
    public function save_import()
    {
        set_time_limit(0);
        
        if (!$_FILES['profession_file'])
        {
            $message[] = '请选择导入的Excel文件';
        }
        
        /**
         * 上传文件
         */
        $upload_path = '../../cache/excel/';
        $file_name = microtime(true) . '.' . end(explode('.', $_FILES['profession_file']['name']));
        $upload_file = $upload_path . $file_name;
        if (!is_dir($upload_path))
        {
            mkdir($upload_path, '0777', true);
        }
        
        if (!@move_uploaded_file($_FILES['profession_file']['tmp_name'], $upload_file))
        {
            message('导入文件失败，请重新导入！');
        }
        
        //导入结果信息统计
        $stat = array(
            'total' => 0,
            'success' => 0,
            'fail' => 0
        );
        
        /**
         * 读取excel
        */
        $this->load->library('PHPExcel');
        $this->load->library('PHPExcel/IOFactory');
        $inputFileType = IOFactory::identify($upload_file);
        $objReader = IOFactory::createReader($inputFileType);
        $objPHPExcel = $objReader->load($upload_file);
    
        $sheetcount = $objPHPExcel->getSheetCount();
        
        for ($i = 0; $i < $sheetcount; $i++)
        {
            $list = array_filter($objPHPExcel->getSheet($i)->toArray());
            if (empty($list))
            {
                continue;
            }
            
            foreach ($list as $k => $v)
            {
                if (!$k)
                {
                    continue;
                }
                
                $stat['total']++;
                
                $data = array();
                
                try 
                {
                    if (!Validate::isNotEmpty($v[0]))
                    {
                        $stat['fail']++;
                        continue;
                    }
                    
                    $data['profession_name'] = htmlspecialchars(trim($v[0]));
                    
                    $data['profession_emerging'] = 0;
                    if (trim($v[1]) == '是')
                    {
                        $data['profession_emerging'] = 1;
                    }
                    
                    $data['profession_explain'] = htmlspecialchars(trim($v[2]));
                    
                    if (ProfessionModel::addProfession($data))
                    {
                        $stat['success']++;
                    }
                    else 
                    {
                        $stat['fail']++;
                    }
                }
                catch (Exception $e)
                {
                    $stat['fail']++;
                }
            }
        }
        
        @unlink($upload_file);
        
        message("本次导入共有{$stat['total']}个职业，成功导入{$stat['success']}个，失败{$stat['fail']}个。", '/admin/profession/index');
    }
}