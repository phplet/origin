#错题集试题显示按知识点错误数量倒序排处理方案

## 1. 进行错题对应知识点错误数量排序并缓存
### 1.1 进行SQL统计
#### 1.1.1 知识点错误数量查询
```sql
	SELECT etr.uid, rk.knowledge_id, k.knowledge_name,
	COUNT(rk.knowledge_id) AS knowledge_errors
	FROM rd_exam_test_result etr
	LEFT JOIN rd_relate_knowledge rk ON rk.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
	LEFT JOIN rd_knowledge k ON k.id = rk.knowledge_id
	WHERE etr.test_score < etr.full_score AND rk.knowledge_id > 0 AND uid = ? AND k.subject_id = ?
	GROUP BY etr.uid, rk.knowledge_id
```

#### 1.1.2 方法策略错误数量查询
```sql
	SELECT etr.uid, rmt.method_tactic_id, mt.`name`,
	COUNT(rmt.method_tactic_id) AS mt_errors
	FROM rd_exam_test_result etr
	LEFT JOIN rd_relate_method_tactic rmt ON rmt.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
	LEFT JOIN rd_method_tactic mt ON mt.id = rmt.method_tactic_id 
	LEFT JOIN rd_subject_category_subject scs ON scs.subject_category_id = mt.subject_category_id
	WHERE etr.test_score < etr.full_score AND rmt.method_tactic_id > 0 AND uid = ? AND scs.subject_id = ?
	GROUP BY etr.uid, rmt.method_tactic_id
```
#### 1.1.3 信息提取方式错误数量查询
```sql
	SELECT etr.uid, rgt.group_type_id, gt.group_type_name,
	COUNT(rgt.group_type_id) AS gt_errors
	FROM rd_exam_test_result etr
	LEFT JOIN rd_relate_group_type rgt ON rgt.ques_id = IF(etr.sub_ques_id > 0, etr.sub_ques_id, etr.ques_id)
	LEFT JOIN rd_group_type gt ON gt.id = rgt.group_type_id
	WHERE etr.test_score < etr.full_score AND rgt.group_type_id > 0 AND uid = ?
	GROUP BY etr.uid, rgt.group_type_id
```

### 1.2 将统计结果缓存

>假设缓存结果保存为类似下面的数据结构:
```php
$kerrlist = array();
$kerrlist[] = array('k_id' => 12, 'k_name' => 'xxxxx', 'k_errcnt' => 322);
......
$kerrlist_map = array();
foreach ($kerrlist as $idx => $val)
{
    $kerrlist_map[$val['k_id']] = $idx;
}
```

## 2. 对错题集页面筛选出来的试题按知识点错误数量倒序排列
首先是按筛选条件将试题筛选出来，然后按照下面的步骤进行处理
假设试题列表（无序）为$queslist
### 2.1 生成错误知识点与试题对应关系
```php
$errk_ques_map = array();
foreach ($queslist as $idx => $val)
{
    foreach ($val['k_id_list'] as $k_id)
    {
        $errk_ques_map[$k_id][] = $idx;
    }
}
```

### 2.2 对错误知识点获取错误数量以进行排序
```php
$errk_errcnt_map = array();
foreach (array_keys($errk_ques_map) as $k_id)
{
    $errk_errcnt_map[$k_id] = $kerrlist[$kerrlist_map[$k_id]]['k_errcnt'];
}

$errk_errcnt_map = arsort($errk_errcnt_map, SORT_NUMERIC);
```
### 2.3 显示排序后的试题
```php
foreach ($errk_errcnt_map as $k_id => $errcnt)
{
    // $errcnt 决定知识点的显示样式,$errcnt越大,则显示越醒目
    foreach ($errk_ques_map[$k_id] as $ques_idx)
    {
        if (isset($queslist[$ques_idx]))
        {
            show_ques($queslist[$ques_idx]);
            unset($queslist[$ques_idx]);
        }
    }
}
```
