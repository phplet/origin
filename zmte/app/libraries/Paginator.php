<?php
class Paginator
{
    public $totalItemCount = 0;
    public $itemCountPerPage;
    public $pageCount = 0;
    public $current = 1;
    public $pageKey;
    public $itemCountPerPageKey;

    public function __construct($count, $page, $perpage, $page_key, $perpage_key)
    {
        if (!is_numeric($count) || $count < 1)
        {
            return;
        }
        $this->totalItemCount = $count;

        if (!is_numeric($page) || $page < 1)
        {
            $page = 1;
        }
        else
        {
            $page = intval($page);
        }

        if (!is_numeric($perpage) || $perpage < 1)
        {
            $perpage = 1;//Fn::config()->default->perpage_num;
        }
        else
        {
            $perpage = intval($perpage);
        }
        $this->itemCountPerPage = $perpage;
        $this->pageCount = ceil($count / $perpage);;
        if ($page > $this->pageCount)
        {
            $page = $this->pageCount;
        }
        $this->current = $page;
        $this->pageKey = $page_key;
        $this->itemCountPerPageKey = $perpage_key;
    }

    public function toArray()
    {
        $paginator = array();
        foreach ($this as $k => $v)
        {
            $paginator[$k] = $v;
        }
        return $paginator;
    }
}
