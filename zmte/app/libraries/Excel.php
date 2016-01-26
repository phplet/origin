<?php
/**
 * some Excel common func
 * @author  BJP
 */
final class Excel
{
    /**
     * 从上传的Excel文件中读取数据并进行最基本的验证第一行为表头，读取数据，
     * 直到某一行第一列为空则停止读取
     * @param   array   $FILE   一个$_FILES文件的上传文件
     * @param   array   $title  列表表头
     * @return  array   list<list<string>>类型的单元格数据
     */
    public static function readSimpleUploadFile(array $FILE, array $title, array &$col_char)
    {
        $err = NULL;
        $err_map = array(
            UPLOAD_ERR_OK => '没有错误发生，文件上传成功',
            UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
            UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败');
        
        if ($FILE['error'] !== 0)
        {
            $err = $err_map[$FILE['error']];
            return $err;
        }
        if (strpos($FILE['type'], 'excel') === false)
        {
            $mime = mime_content_type($FILE['tmp_name']);
            if (!in_array($mime, array('application/vnd.ms-excel', 
                'application/vnd.ms-office', 
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')))
            {
                $err = "您上传的不是Excel文件($mime)";
                return $err;
            }
        }
        // 开始处理excel

        $excel = PHPExcel_IOFactory::load($FILE['tmp_name']);
        $sheet = $excel->getSheet(0);
        $row_num = $sheet->getHighestRow();
        $col_num = $sheet->getHighestColumn();

        $col_num = base_convert($col_num, 36, 10);
        if ($col_num < count($title))
        {
            $err = 'Excel列数验证未通过';
            return $err;
        }
        $col_num = count($title);
        $col_char = array();
        for ($j = 0; $j < $col_num; $j++)
        {
            $col_char[$j] = strtoupper(base_convert(10 + $j, 10, 36));
            if ($title[$j]
                !== trim($sheet->getCell($col_char[$j] . '1')->getValue()))
            {
                $err = $col_char[$j] . '列标题不符';
                break;
            }
        }
        if ($err)
        {
            return $err;
        }

        $rows = array();
        for ($i = 2; $i <= $row_num; $i++)
        {
            $rows[$i - 2] = array();
            for ($j = 0; $j < $col_num; $j++)
            {
                $rows[$i - 2][$j] = trim($sheet->getCell(
                    $col_char[$j] . $i)->getValue());
            }
            if ($rows[$i - 2][0] == '')
            {
                unset($rows[$i - 2]);
                break;
            }
        }
        unset($sheet);
        unset($excel);

        if (empty($rows))
        {
            $err = 'Excel文件工作表中没有任何要导入的记录';
            return $err;
        }
        return $rows;
    }
    
    /**
     * 从上传的Excel文件中读取数据并进行最基本的验证第一行为表头，读取数据，
     * 直到某一行第一列为空则停止读取
     * @param   array   $FILE   一个$_FILES文件的上传文件
     * @return  array   list<list<string>>类型的单元格数据
     */
    public static function readSimpleUploadFile2(array $FILE, array &$col_char)
    {
        $err = NULL;
        $err_map = array(
            UPLOAD_ERR_OK => '没有错误发生，文件上传成功',
            UPLOAD_ERR_INI_SIZE => '上传的文件超过了 php.ini 中 upload_max_filesize 选项限制的值',
            UPLOAD_ERR_FORM_SIZE => '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值',
            UPLOAD_ERR_PARTIAL => '文件只有部分被上传',
            UPLOAD_ERR_NO_FILE => '没有文件被上传',
            UPLOAD_ERR_NO_TMP_DIR => '找不到临时文件夹',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败');
    
        if ($FILE['error'] !== 0)
        {
            $err = $err_map[$FILE['error']];
            return $err;
        }
        
        if (strpos($FILE['type'], 'excel') === false)
        {
            $mime = mime_content_type($FILE['tmp_name']);
            if (!in_array($mime, array('application/vnd.ms-excel',
                'application/vnd.ms-office', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')))                
            {
                    $err = "您上传的不是Excel文件($mime)";
                    return $err;
            }
        }
        
        // 开始处理excel

        $excel = PHPExcel_IOFactory::load($FILE['tmp_name']);
        $sheet = $excel->getSheet(0);
        $row_num = $sheet->getHighestRow();
        $col_num = $sheet->getHighestColumn();

        $col_num = base_convert($col_num, 36, 10);
        $col_char = array();
        for ($j = 0; $j < $col_num; $j++)
        {
            $col_char[$j] = strtoupper(base_convert(10 + $j, 10, 36));
        }
        
        $rows = array();
        for ($i = 1; $i <= $row_num; $i++)
        {
            $rows[$i - 1] = array();
            for ($j = 0; $j < $col_num; $j++)
            {
                $rows[$i - 1][$j] = trim($sheet->getCell(
                    $col_char[$j] . $i)->getValue());
            }
        }
        unset($sheet);
        unset($excel);

        if (empty($rows))
        {
            $err = 'Excel文件工作表中没有任何要导入的记录';
            return $err;
        }
            
        return $rows;
    }
}
