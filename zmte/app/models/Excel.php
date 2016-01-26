<?php
/**
 * ExcelModel
 * @file    Excel.php
 * @author  BJP
 * @final   2015-06-16
 */
class ExcelModel
{
    private $head;
    private $body;

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function getExcelHeader($sheet, $col_cnt = 256, $row_cnt = 1)
    {
        $xml = <<<EOT
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" xmlns:html="http://www.w3.org/TR/REC-html40">
<DocumentProperties xmlns="urn:schemas-microsoft-com:office:office">
 <Author>CTDFv2.2</Author>
 <Created>2011-06-16T17:58:57</Created>
 </DocumentProperties>
<ExcelWorkbook xmlns="urn:schemas-microsoft-com:office:excel">
 <WindowWidth>19320</WindowWidth>
 <WindowHeight>9080</WindowHeight>
 <ProtectStructure>False</ProtectStructure>
 <ProtectWindows>False</ProtectWindows>
 </ExcelWorkbook>
<Styles>
 <Style ss:ID="Default" ss:Name="Normal">
  <Alignment/>
  <Borders/>
  <Font ss:FontName="宋体" x:CharSet="134" ss:Size="9"/>
  <Interior/>
  <NumberFormat/>
  <Protection/>
  </Style>
 <Style ss:ID="sNumber" ss:Name="千位分隔">
  <NumberFormat ss:Format="_ * #,##0.00_ ;_ * -#,##0.00_ ;_ * &quot;-&quot;??_ ;_ @_ "/>
  <Protection/>
  </Style>
 <Style ss:ID="sMoney" ss:Name="货币">
  <NumberFormat ss:Format="_ &quot;￥&quot;* #,##0.00_ ;_ &quot;￥&quot;* -#,##0.00_ ;_ &quot;￥&quot;* &quot;-&quot;??_ ;_ @_ "/>
  <Protection/>
  </Style>
 <Style ss:ID="sPercent" ss:Name="百分比">
  <NumberFormat ss:Format="0%"/>
  <Protection/>
  </Style>
 <Style ss:ID="sTitle">
  <Alignment ss:Horizontal="Center"/>
  <Font ss:Size="9" ss:Bold="1"/>
  </Style>
 </Styles>
<Worksheet ss:Name="{$sheet}">
 <Table ss:ExpandedColumnCount="{$col_cnt}" ss:ExpandedRowCount="{$row_cnt}" x:FullColumns="1" x:FullRows="1" ss:DefaultColumnWidth="100" ss:DefaultRowHeight="14.25">
EOT;
        return $xml;
    }

    public function getExcelFooter()
    {
        $xml = <<<EOT
  </Table>
 <WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel">
  <PageSetup>
   <Header x:Margin="0.511805555555556"/>
   <Footer x:Margin="0.511805555555556"/>
   </PageSetup>
  <Print>
   <HorizontalResolution>1200</HorizontalResolution>
   <VerticalResolution>1200</VerticalResolution>
   </Print>
  <Selected/>
  <PageBreakZoom>100</PageBreakZoom>
  <Panes>
   <Pane>
    <Number>0</Number>
    <ActiveRow>0</ActiveRow>
    <ActiveCol>0</ActiveCol>
    <RangeSelection>R1C4</RangeSelection>
    </Pane>
   </Panes>
  <ProtectObjects>False</ProtectObjects>
  <ProtectScenarios>False</ProtectScenarios>
  </WorksheetOptions>
 </Worksheet>
</Workbook>
EOT;
        return $xml;
    }
    

    /**
     * 导出到excel文件(一般导出中文的都会乱码，需要进行编码转换）
     * 使用方法如下
     * $excel = new Excel();
     * $excel->addHeader(array('列1','列2','列3','列4'));
     * $excel->addBody(
     array(
     array('数据1','数据2','数据3','数据4'),
     array('数据1','数据2','数据3','数据4'),
     array('数据1','数据2','数据3','数据4'),
     array('数据1','数据2','数据3','数据4')
     )
     );
     * $excel->downLoad();
     */
    public function addHeader($arr)
    {
        foreach($arr as $headVal)
        {
            $headVal = $this->charset($headVal);
            $this->head .= "{$headVal}\t ";
        }
        $this->head .= "\n";
    }
     
    /**
     *
     * @param type $arr 二维数组
     */
    public function addBody($arr)
    {
        foreach($arr as $arrBody)
        {
            foreach($arrBody as $bodyVal)
            {
                $bodyVal = $this->charset($bodyVal);
                $this->body .= "{$bodyVal}\t ";
            }
            $this->body .= "\n";
        }
    }
     
    /**
     * 下载excel文件
     */
    public function download($filename = '')
    {
        if(!$filename)
        {
            $filename = date('YmdHis',time()).'.xls';
        }
        header("Content-type:application/vnd.ms-excel");
        header("Content-Disposition:attachment;filename=$filename");
        header("Content-Type:charset=gb2312");
        if ($this->head)
        {
            echo $this->head;
        }
        echo $this->body;
    }
     
    /**
     * 编码转换
     * @param type $string
     * @return string
     */
    public function charset($string)
    {
        return iconv("utf-8", "gb2312", $string);
    }
}
