<?php
class Common extends S_Controller
{
    /**
     * 公共页面接口
     *
     */
    public function index()
    {
        exit('Directory access is forbidden.');
    }

    // ajax加载学校列表
    public function schools() 
    {
        $schools = array();
        $sql = <<<EOT
SELECT school_id, school_name FROM rd_school
EOT;
        $where = array();
        $bind = array();
        if ($keyword = trim($this->input->post('keyword')))
        {
            $where[] = 'school_name LIKE ?';
            $bind[] = '%' . $keyword . '%';
        }
        if ($grade_id = intval($this->input->post('grade_id')))
        {
            $grade_period = get_grade_period($grade_id);
            if ($grade_period) 
            {
                $where[] = 'grade_period LIKE ?';
                $bind[] = '%' . $grade_period . '%';
            }
        }
        if ($where)
        {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $schools = Fn::db()->fetchAll($sql, $bind);
        echo json_encode($schools);
    }

    // ajax加载学校列表
    public function school_list()
    {
        $where = array();
        if ($area_id = intval($this->input->get('area_id')))
        {
            $where['area_id'] = $area_id;
        }
        
        if ($city_id = intval($this->input->get('city_id')))
        {
            $where['city_id'] = $city_id;
        }

        if ($school_property = intval($this->input->get('school_property')))
        {
            $where['school_property'] = $school_property;
        }

        if ($keyword = trim($this->input->get('keyword')))
        {
            $where['keyword'] = $keyword;
        }

        if ($grade_id = intval($this->input->get('grade_id')))
        {
            $grade_period = get_grade_period($grade_id);
            
            if ($grade_period)
            {
                $where['grade_period'] = $grade_period;
            }
        }

        $schools = SchoolModel::search_school($where);
        echo json_encode($schools);
    }

    public function regions()
    {
        $p_id = $this->input->get('p_id');
        $p_id = $p_id ? $p_id : 1;
        $regions = RegionModel::get_regions($p_id);
        echo json_encode($regions);
    }
}
