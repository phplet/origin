<?php
/**
 * 外部对比信息模块 ComparisonInfoModel
 * @file    ComparisonInfo.php
 * @author  BJP
 * @final   2015-06-18
 */
class ComparisonInfoModel
{
    /**
     * 按 id 读取对比信息分类 单条信息
     * @param   int     类型ID
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_comparison_info_by_id($id = 0, $item = '*')
    {
        if ($id == 0)
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_comparison_info WHERE cmp_info_id = {$id}
EOT;
        $row =  Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }
    
    /**
     * 按分类id,年费读取单条信息
     *          
     * @param   int  	 分类id
     * @param   int	  	 分类名称
     * @param   string  字段列表(多个字段用逗号分割，默认取全部字段)
     * @return  mixed   指定单个字段则返回该字段值，否则返回关联数组
     */
    public static function get_comparison_info_by_year($type_id = 0, 
        $year = '', $item = '*')
    {
        if (empty($type_id) OR empty($year))
        {
            return FALSE;
        }
        $sql = <<<EOT
SELECT {$item} FROM rd_comparison_info 
WHERE cmp_type_id = {$type_id} AND cmp_info_year = {$year}
EOT;
        $row = Fn::db()->fetchRow($sql);
        if ($item && isset($row[$item]))
        {
            return $row[$item];
        }
        else
        {
            return $row;
        }
    }

    /**
     * 通过条件获取分类列表
     *          
     * @param   array      查询条件
     * @param   int        当前页数
     * @param   int        每页个数
     * @param   string     排序
     * @param   string     选择的字段值，逗号分割
     * @return  array
     */
    public static function get_comparison_info_list($condition = array(), 
        $page = 1, $page_size = 15, $order_by = null, $select_what = '*')
    {
        $sql = <<<EOT
SELECT {$select_what} FROM rd_comparison_info
EOT;

        if (is_array($condition) AND $condition)
        {
            $sql_where = array();
            foreach ($condition as $key => $val)
            {
                switch($key) 
                {
                    case 'cmp_type_id' :
                    case 'cmp_info_year' :                    
                        $sql_where[] = "$key = $val";
                        break;
                    default : break;
                }
            }
            if ($sql_where)
            {
                $sql .= ' WHERE ' . implode(' AND ', $sql_where);
            }
        }

        if ($order_by)
        {
            $sql .= " ORDER BY $order_by";
        }

        $page = intval($page);
        if ($page > 0) {
            $page_size = intval($page_size);
            $start = ($page - 1) * $page_size;
            $sql .= " LIMIT $page_size OFFSET $start";
        }
        $rows = Fn::db()->fetchAll($sql);
        return $rows;
    }

    /**
     * 通过条件获取分类个数
     *          
     * @param   array      查询条件
     * @return  int
     */
    public static function get_comparison_info_count($condition = array())
    {
        $result = self::get_comparison_info_list($condition, null, null, null, 
            'COUNT(*) total');
        return $result ? $result[0]['total'] : 0;
    }
    
    /**
     * 通过信息id, 获取信息对比项（知识点）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用知识点(item_knowledge_id)作为数组键值
     * @return  array
     */    
    public static function get_comparison_items($info_id, 
        $use_knowledge_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_knowledge_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['item_knowledge_id']] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
    
    /**
     * 通过信息id, 获取信息对比项（信息提取方式）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用知识点(item_group_type_id)作为数组键值
     * @return  array
     */
    public static function get_comparison_items2($info_id, 
        $use_group_type_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item2 WHERE cmp_info_id = {$info_id}
EOT;
        $list = Fn::db()->fetchAll($sql);
        if ($use_group_type_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['item_group_type_id']] = $val;
            }
            $list = &$new_list;
        }
        return $list;
    }
        
    /**
     * 插入信息
     *
     * @param   array		     信息字段数组
     * @param   array		     对比项数据
     * @return  boolean
     */
    public static function insert($info, &$insert_id, $data = array())
    {
    	$items = array();
    	$items2 = array();
    	$external_items = array();
    	$item_difficulties = array();
    	$external_difficulties = array();
    	$item_method_tactics = array();
    	$external_method_tactics = array();
    	
    	isset($data['items']) && $items = $data['items'];//对比项(一级知识点)
    	isset($data['items2']) && $items2 = $data['items2'];//对比项(信息提取方式)
    	isset($data['external_items']) && $external_items = $data['external_items'];//对比项(外部一级知识点)
    	isset($data['item_difficulties']) && $item_difficulties = $data['item_difficulties'];//对比项(难易度)
    	isset($data['external_difficulties']) && $external_difficulties = $data['external_difficulties'];//对比项(外部题型 难易度)
    	isset($data['item_method_tactics']) && $item_method_tactics = $data['item_method_tactics'];//对比项(方法策略)
    	isset($data['external_method_tactics']) && $external_method_tactics = $data['external_method_tactics'];//对比项(外部方法策略)
    	
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
    	{
    		$info['updatetime'] = time();
    		$info['addtime'] = time();
    		
    		$id = null;
    		// 关闭错误信息   		
                if ($db->insert('rd_comparison_info', $info))
    		{
    			$id = $db->lastInsertId('rd_comparison_info', 'cmp_info_id');
                        if ($items)
                        {
                            foreach ($items as &$val)
                            {
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item', $val);
                            }
    			}
    			
                        if ($items2)
                        {
                            foreach ($items2 as &$val)
                            {
    			        $val['cmp_info_id'] = $id;
    			        $db->insert('rd_comparison_item2', $val);
    			    }
    			}
    			
                        if ($external_items)
                        {
                            foreach ($external_items as &$val)
                            {
	    		        $val['cmp_info_id'] = $id;
	    			$db->insert('rd_comparison_item_external', $val);
	    		    }
    			}
    			
                        if ($item_difficulties)
                        {
                            foreach ($item_difficulties as &$val)
                            {
	    		        $val['cmp_info_id'] = $id;
	    			$db->insert('rd_comparison_item_difficulty', $val);
                            }
    			}
    			
                        if ($external_difficulties)
                        {
                            foreach ($external_difficulties as &$val)
                            {
	    		        $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_external_difficulty', $val);
	    		    }
    			}
    			
                        if ($item_method_tactics)
                        {
                            foreach ($item_method_tactics as &$val)
                            {
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_method_tactic', $val);
                            }
                        }
    			
                        if ($external_method_tactics)
                        {
                            foreach ($external_method_tactics as &$val)
                            {
                                $val['cmp_info_id'] = $id;
	    			$db->insert('rd_comparison_item_external_method_tactic', $val);
                            }
    			}
                }
                else
                {
                    log_message('error', 'mysql error:' . $db->errorInfo()[2]);
    		}
                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
                $insert_id = $id;
    	}
        return $bOk;
    }

    /**
     * 更新信息
     *
     * @param   int     	     分类id
     * @param   array		     更新字段数组		  
     * @param   array		     外部知识点更新字段数组		  
     * @return  boolean
     */
    public static function update($id, $info, $data = array())
    {
    	$items = array();
    	$items2 = array();
    	$external_items = array();
    	$item_difficulties = array();
    	$external_difficulties = array();
    	$item_method_tactics = array();
    	$external_method_tactics = array();
    	
    	isset($data['items']) && $items = $data['items'];//对比项(一级知识点)
    	isset($data['items2']) && $items2 = $data['items2'];//对比项(信息提取方式)
    	isset($data['external_items']) && $external_items = $data['external_items'];//对比项(外部一级知识点)
    	isset($data['item_difficulties']) && $item_difficulties = $data['item_difficulties'];//对比项(难易度)
    	isset($data['external_difficulties']) && $external_difficulties = $data['external_difficulties'];//对比项(外部题型 难易度)
    	isset($data['item_method_tactics']) && $item_method_tactics = $data['item_method_tactics'];//对比项(方法策略)
    	isset($data['external_method_tactics']) && $external_method_tactics = $data['external_method_tactics'];//对比项(外部方法策略)
    	
        $db = Fn::db();
        $bOk = false;
        if ($db->beginTransaction())
        {    		
    		$info['updatetime'] = time();

                $res = $db->update('rd_comparison_info', $info, 'cmp_info_id = ' . $id);
                if ($res)
                {
    			// get old items
    			$old_items = self::get_comparison_items($id, TRUE); 
    			// deal with items
                        foreach ($items as $val)
                        {
                            if (isset($old_items[$val['item_knowledge_id']]))
                            {
                                $db->update('rd_comparison_item', $val, 
                                    "cmp_info_id = $id AND item_knowledge_id = " . $val['item_knowledge_id']);
                                unset($old_items[$val['item_knowledge_id']]);
                            }
                            else
                            {
                                // insert
	    			$val['cmp_info_id'] = $id;
    				$db->insert('rd_comparison_item', $val);
    			    }   				
    			}
                        if ($old_items)
                        {
    			    // delete
                            $db->delete('rd_comparison_item', 
                                "cmp_info_id = $id AND item_knowledge_id IN (" . implode(',', array_keys($old_items)) . ")");
    			}
    			
    			//===========external items <对比项（外部一级知识点）>===========
    			// get old items
    			$old_items = self::get_comparison_items_external($id, TRUE); 
    			// deal with items
                        foreach ($external_items as $val)
                        {
                            if (isset($old_items[$val['external_knowledge_name']]))
                            {
                                // update
                                $update_what = array('item_percent' => $val['item_percent']);
                                $db->update('rd_comparison_item_external', $update_what, 
                                    "cmp_info_id = $id AND external_knowledge_name = " . $val['external_knowledge_name']);
                                unset($old_items[$val['external_knowledge_name']]);
                            }
                            else
                            {
                                // insert
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_external', $val);
                            }   				
    			}
                        if ($old_items)
                        {
                            // delete
                            $db->delete('rd_comparison_item_external', 
                                "cmp_info_id = $id AND external_knowledge_name IN (" . implode(',', array_keys($old_items)) . ")");
    			}
    			
    			// get old items2
    			$old_items2 = self::get_comparison_items2($id, TRUE);
    			// deal with items2
                        foreach ($items2 as $val)
                        {
                            if (isset($old_items2[$val['item_group_type_id']]))
                            {
    			        // update
                                $db->update('rd_comparison_item2', $val, 
                                    "cmp_info_id = $id AND item_group_type_id = " . $val['item_group_type_id']);
    			        unset($old_items2[$val['item_group_type_id']]);
                            }
                            else
                            {
    			        // insert
    			        $val['cmp_info_id'] = $id;
    			        $db->insert('rd_comparison_item2', $val);
    			    }
    			}
                        if ($old_items)
                        {
    			    // delete
    			    $db->delete('rd_comparison_item2', "cmp_info_id = $id AND item_group_type_id IN (" . implode(',', array_keys($old_items2)) . ")");
    			}
    			
    			//===========item difficulties <对比项（难易度）>===========
    			// get old items
    			$old_items = self::get_comparison_items_difficutly($id, TRUE); 

    			// deal with items
    			$insert_data = array();
                        foreach ($item_difficulties as $val)
                        {
                            $k = $val['q_type'];
                            if (isset($old_items[$k]))
                            {
                                // update
                                $update_what = array(
                                                        'difficulty_percent' => $val['difficulty_percent'],
                                                        'question_amount' => $val['question_amount'],
                                );
                                
                                $db->update('rd_comparison_item_difficulty', $update_what, 
                                    "cmp_info_id = $id AND q_type = ". $val['q_type']
                                );
                                unset($old_items[$k]);
                            }
                            else
                            { 
                                // insert
                                $val['cmp_info_id'] = $id;
                                $insert_data[] = $val;
                            }   				
    			}
    			
                        if (count($insert_data))
                        {
                            foreach ($insert_data as $row)
                            {
                                $db->insert('rd_comparison_item_difficulty', $row);
                            }
                            $insert_data = array();
    			}
    			
                        if ($old_items)
                        {
                            // delete
                            foreach ($old_items as $val)
                            {
                                $condition = array(
                                );
                                $db->delete('rd_comparison_item_difficulty', 
                                    "cmp_info_id = $id AND q_type = " . $val['q_type']
                                );
                            }
    			}
    			
    			//===========external difficulties <对比项（外部题型 难易度）>===========
    			// get old items
    			$old_items = self::get_comparison_items_external_difficutly($id, TRUE); 
    			
    			// deal with items
                        foreach ($external_difficulties as $val)
                        {
                            if (isset($old_items[$val['name']]))
                            {
                                // update
                                $update_what = array('question_amount' => $val['question_amount']);
                                $update_where = array(
                                );
                                
                                $db->update('rd_comparison_item_external_difficulty', $update_what, 
                                    "cmp_info_id = $id AND name = ?", array($val['name']));
                                unset($old_items[$val['name']]);
                            }
                            else
                            {
                                // insert
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_external_difficulty', $val);
                            }   				
    			}
                        if ($old_items)
                        {
                            // delete
                            $db->delete('rd_comparison_item_external_difficulty', 
                                "cmp_info_id = $id AND name IN ('" . implode("','", array_keys($old_items)) . "')");
    			}
    			
    			//===========method_tactic <对比项（方法策略）>===========
    			// get old items
    			$old_items = self::get_comparison_items_method_tactic($id, TRUE);
    			// deal with items
                        foreach ($item_method_tactics as $val)
                        {
                            if (isset($old_items[$val['method_tactic_id']])) {
                                    // update
                                    $update_where = array(
                                    );
                                    $db->update('rd_comparison_item_method_tactic', $val, 
                                                    "cmp_info_id = $id AND method_tactic_id = " . $val['method_tactic_id']);
                                    unset($old_items[$val['method_tactic_id']]);
                            }
                            else
                            {
                                // insert
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_method_tactic', $val);
                            }
    			}
                        if ($old_items)
                        {
                            // delete
                            $db->delete('rd_comparison_item_method_tactic', "cmp_info_id = $id AND method_tactic_id IN (" . implode(',', array_keys($old_items)) . ")");
    			}
    			 
    			//===========external method_tactic <对比项（外部方法策略）>===========
    			// get old items
    			$old_items = self::get_comparison_items_external_method_tactic($id, TRUE);
    			// deal with items
                        foreach ($external_method_tactics as $val)
                        {
                            if (isset($old_items[$val['name']]))
                            {
                                // update
                                $update_what = array('percent' => $val['percent']);
                                $update_where = array(
                                                'cmp_info_id' => $id,
                                                'name' => $val['name']
                                );
                                        
                                $db->update('rd_comparison_item_external_method_tactic', $update_what, 
                                    "cmp_info_id =$id AND name = ?", array($val['name']));
                                unset($old_items[$val['name']]);
                            }
                            else
                            {
                                // insert
                                $val['cmp_info_id'] = $id;
                                $db->insert('rd_comparison_item_external_method_tactic', $val);
                            }
    			}
                        if ($old_items)
                        {
                            // delete
                            $db->delete('rd_comparison_item_external_method_tactic', 
                                "cmp_info_id = $id AND name IN ('" . implode("','", array_keys($old_items)) . "')");
    			}
                }
                else
                {
                    log_message('error', 'uddate comparison_info failed. sql:' . $db->errorInfo()[2]);
    		}

                $bOk = $db->commit();
                if (!$bOk)
                {
                    $db->rollBack();
                }
    	}
        return $bOk;
    }
    
    /**
     * 删除信息
     *
     * @param   int/array        信息id
     * @return  boolean
     */
    public static function delete($id)
    {
        try
        {
            $db = Fn::db();
            if (is_array($id))
            {
                $db->delete('rd_comparison_info', "cmp_info_id = $id");
                $db->delete('rd_comparison_item', "cmp_info_id = $id");
                $db->delete('rd_comparison_item_external', "cmp_info_id = $id");
            }
            else
            {
                $db->delete('rd_comparison_info', "cmp_info_id = $id");
                $db->delete('rd_comparison_item', "cmp_info_id = $id");
                $db->delete('rd_comparison_item_external', "cmp_info_id = $id");
            }
            return TRUE;
        }
        catch (Exception $e)
        {
    	    return FALSE;
    	}
    }
    
    /**********************一级知识点*************************/

    /**
     * 通过信息id, 获取信息对比项（知识点）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用知识点(external_knowledge_name)作为数组键值
     * @return  array
     */
    public static function get_comparison_items_external($info_id, $use_knowledge_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item_external WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_knowledge_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['external_knowledge_name']] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
    
    /**********************难易度*************************/

    /**
     * 通过信息id, 获取信息对比项（ 难易度）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用cmp_info_id-q_type-difficulty_type作为数组键值
     * @return  array
     */
    public static function get_comparison_items_difficutly($info_id, $use_unique_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item_difficulty WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_unique_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $k = $val['q_type'];
                $new_list[$k] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
    
    /**
     * 通过信息id, 获取信息对比项（外部题型 难易度占比）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用题型名称(name)作为数组键值
     * @return  array
     */
    public static function get_comparison_items_external_difficutly($info_id, $use_knowledge_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item_external_difficulty WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_knowledge_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['name']] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
    
    /**********************方法策略*************************/

    /**
     * 通过信息id, 获取信息对比项（ 方法策略）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用方法策略(method_tactic_id)作为数组键值
     * @return  array
     */
    public static function get_comparison_items_method_tactic($info_id, $use_unique_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item_method_tactic WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_unique_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['method_tactic_id']] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
    
    /**
     * 通过信息id, 获取信息对比项（外部方法策略占比）列表
     *
     * @param   int			信息id
     * @param	bool		是否使用名称(name)作为数组键值
     * @return  array
     */
    public static function get_comparison_items_external_method_tactic($info_id, $use_knowledge_key = false)
    {
        if (!$info_id = intval($info_id))
        {
            return array();
        }
        $sql = <<<EOT
SELECT * FROM rd_comparison_item_external_method_tactic WHERE cmp_info_id = {$info_id}
EOT;
    	$list = Fn::db()->fetchAll($sql);
        if ($use_knowledge_key)
        {
            $new_list = array();
            foreach ($list as $val)
            {
                $new_list[$val['name']] = $val;
            }
            $list = &$new_list;
    	}
    	return $list;
    }
}
