<?php

namespace Mindy\Pagination;

use Mindy\Exception\Exception;
use Mindy\Helper\Traits\Accessors;
use Mindy\Helper\Traits\Configurator;

/**
 * All rights reserved.
 *
 * @author Falaleev Maxim
 * @email max@studio107.ru
 * @version 1.0
 * @company Studio107
 * @site http://studio107.ru
 * @date 09/05/14.05.2014 14:56
 */
abstract class BasePagination
{
    use Accessors, Configurator;

    /**
     * @var int
     */
    public static $defaultPageSize = 10;
    /**
     * @var string
     */
    public $key;
    /**
     * @var int
     */
    public $pageSize;
    /**
     * @var array
     */
    public $pageSizes = [10, 20, 50, 100];
    /**
     * @var array|\Mindy\Orm\QuerySet
     */
    public $source = [];
    /**
     * @var array
     */
    public $data = [];
    /**
     * @var int current page
     */
    public $page;
    /**
     * @var int total records or elements in array
     */
    protected $total;
    /**
     * @var int autoincrement pagination classes on the page
     */
    private static $id = 0;
    /**
     * @var int current pagination id
     */
    private $_id;
    /**
     * @var bool is QuerySet?
     */
    private $isQs = false;

    public function __construct($source, array $config = [])
    {
        $this->source = $source;
        $this->configure($config);
        $this->init();
    }

    public function init()
    {
        self::$id++;

        $this->_id = self::$id;
        if(class_exists('\Mindy\Orm\QuerySet')) {
            $this->isQs = $this->source instanceof \Mindy\Orm\QuerySet;
        }
    }

    public function getUrl($page)
    {
        $uri = parse_url($_SERVER['REQUEST_URI']);
        if(!isset($uri['query'])) {
            $uri['query'] = '';
        }
        parse_str($uri['query'], $params);
        $params[$this->getName()] = $page;
        return "?" . http_build_query($params);
    }

    public function urlPageSize($pageSize)
    {
        $uri = parse_url($_SERVER['REQUEST_URI']);
        if(!isset($uri['query'])) {
            $uri['query'] = '';
        }
        parse_str($uri['query'], $params);
        $params[$this->getPageSizeKey()] = $pageSize;
        return "?" . http_build_query($params);
    }

    /**
     * Return PageSize
     * @return int
     */
    public function getPageSize()
    {
        if ($this->pageSize === null) {
            if (isset($_GET[$this->getPageSizeKey()])) {
                $pageSize = (int)$_GET[$this->getPageSizeKey()];
                if ($pageSize) {
                    $this->pageSize = $pageSize;
                } else {
                    $this->pageSize = self::$defaultPageSize;
                }
            } else {
                $this->pageSize = self::$defaultPageSize;
            }
        }

        return $this->pageSize;
    }

    /**
     * @return string
     */
    public function getPageSizeKey()
    {
        return $this->getName() . '_PageSize';
    }

    public function getTotal()
    {
        return $this->total;
    }

    /**
     * @return integer number of pages
     */
    public function getPagesCount()
    {
        $pageSize = $this->getPageSize();
        return (int)(($this->getTotal() + $pageSize - 1) / $pageSize);
    }

    public function hasNextPage()
    {
        return ($this->getPagesCount() - $this->page) > 0;
    }

    public function hasPrevPage()
    {
        return $this->page > 1;
    }

    public function getCurrentPage()
    {
        return $this->page;
    }

    protected function fetchPage($key = null)
    {
        $page = isset($_GET[$key]) ? (int)$_GET[$key] : 1;
        if ($page <= 0) {
            return $page = 1;
        } elseif ($page > $this->getTotal()) {
            return $page = $this->getPagesCount();
        } else {
            return $page;
        }
    }

    public function getPage()
    {
        if (!$this->page) {
            $this->page = $this->fetchPage($this->getName());
        }
        return $this->page;
    }

    public function setPage($page)
    {
        return $this->page = $page;
    }

    /**
     * Apply limits to source
     * @throws \Mindy\Exception\Exception
     * @return $this
     */
    public function paginate()
    {
        if(is_array($this->source)) {
            return $this->applyLimitArray();
        } else if($this->source instanceof \Mindy\Orm\HasManyManager || $this->source instanceof \Mindy\Orm\ManyToManyManager) {
            $this->source = $this->source->getQuerySet();
            return $this->applyLimitQuerySet();
        } else if($this->source instanceof \Mindy\Orm\QuerySet) {
            return $this->applyLimitQuerySet();
        } else if($this->source instanceof \Mindy\Query\Query) {
            return $this->applyLimitQuery();
        } else {
            throw new Exception("Unknown source");
        }
    }

    /**
     * @return array
     */
    protected function applyLimitArray()
    {
        $this->total = count($this->source);
        $page = $this->getPage();
        $pageSize = $this->getPageSize();
        $this->data = array_slice($this->source, $pageSize * ($page <= 1 ? 0 : $page - 1), $pageSize);
        return $this->data;
    }

    /**
     * @return array
     */
    protected function applyLimitQuery()
    {
        $this->total = $this->source->count();
        $offset = $this->page > 1 ? $this->pageSize * ($this->page - 1) : 0;
        $this->data = $this->source->limit($this->pageSize)->offset($offset)->all();
        return $this->data;
    }

    /**
     * @return array
     */
    protected function applyLimitQuerySet()
    {
        $this->total = $this->source->count();
        $this->data = $this->source->paginate($this->getPage(), $this->getPageSize())->all();
        return $this->data;
    }

    public function getName()
    {
        if ($this->isQs) {
            $base = $this->source->model->classNameShort();
        } else {
            $base = 'Pager';
        }

        return $base . '_' . $this->_id;
    }

    public function iterPrevPage($count = 3)
    {
        $pages = [];
        foreach(array_reverse(range(1, $count)) as $i) {
            $page = $this->getCurrentPage() - $i;
            if($page > 0) {
                $pages[] = $page;
            }
        }
        return $pages;
    }

    public function iterNextPage($count = 3)
    {
        $pages = [];
        foreach(range(1, $count) as $i) {
            $page = $this->getCurrentPage() + $i;
            if($page <= $this->getPagesCount()) {
                $pages[] = $page;
            }
        }
        return $pages;
    }
}
