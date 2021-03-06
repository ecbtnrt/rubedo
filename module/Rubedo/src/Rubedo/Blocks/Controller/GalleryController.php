<?php
/**
 * Rubedo -- ECM solution
 * Copyright (c) 2013, WebTales (http://www.webtales.fr/).
 * All rights reserved.
 * licensing@webtales.fr
 *
 * Open Source License
 * ------------------------------------------------------------------------------------------
 * Rubedo is licensed under the terms of the Open Source GPL 3.0 license. 
 *
 * @category   Rubedo
 * @package    Rubedo
 * @copyright  Copyright (c) 2012-2013 WebTales (http://www.webtales.fr)
 * @license    http://www.gnu.org/licenses/gpl.html Open Source GPL 3.0 license
 */
namespace Rubedo\Blocks\Controller;

use Rubedo\Services\Manager;
use WebTales\MongoFilters\Filter;
use Zend\View\Model\JsonModel;
use Zend\Json\Json;

/**
 *
 * @author jbourdin
 * @category Rubedo
 * @package Rubedo
 */
class GalleryController extends ContentListController
{

    public function indexAction ()
    {
        $output = $this->_getList();
        
        $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/gallery.html.twig");
        
        $css = array();
        $js = array(
            $this->getRequest()->getBasePath() . '/' . Manager::getService('FrontOfficeTemplates')->getFileThemePath("js/gallery.js")
        );
        
        return $this->_sendResponse($output, $template, $css, $js);
    }

    public function xhrGetImagesAction ()
    {
        $twigVars = $this->_getList();
        $template = Manager::getService('FrontOfficeTemplates')->getFileThemePath("blocks/gallery/items.html.twig");
        $html = Manager::getService('FrontOfficeTemplates')->render($template, $twigVars);
        $data = array(
            'html' => $html
        );
        return new JsonModel($data);
    }

    /**
     * return a list of items based on the query
     *
     * @return array
     */
    protected function _getList ()
    {
        $this->init();
        $currentPage = $this->getParamFromQuery('page', 1);
        if ($this->getRequest()->isXmlHttpRequest()) {
            $limit = (int) $this->getParamFromQuery('itemsPerPage', 5);
            $query = Json::decode($this->getParamFromQuery("query", Json::encode(null)), Json::TYPE_ARRAY);
            $imgWidth = $this->getParamFromQuery('width', null);
            $imgHeight = $this->getParamFromQuery('height', null);
        } else {
            // Get queryId, blockConfig and Datalist
            $blockConfig = $this->getParamFromQuery('block-config');

            $limit = (int) (isset($blockConfig["pageSize"])) ? $blockConfig['pageSize'] : 5;
            $query = Json::decode($blockConfig["query"], Json::TYPE_ARRAY);
            $imgWidth = $blockConfig['imageThumbnailWidth'];
            $imgHeight = $blockConfig['imageThumbnailHeight'];
        }
            $prefix = $this->getParamFromQuery('prefix');
            $filter = $this->setFilters($query);

        $this->_dataService = Manager::getservice('Dam');
        if (!isset($filter['filter'])){
            $filter['filter'] = null;
        }
        if (!isset($filter['sort'])){
            $filter['sort'] = null;
        }
        
        // Get the number of pictures in database
        $allDamCount = $this->_dataService->count($filter['filter']);
        // Define the maximum number of pages
        $maxPage = (int) ($allDamCount / $limit);
        if ($allDamCount % $limit > 0) {
            $maxPage ++;
        }
        
        // Set the page to 1 if the user enter a bad page value in the URL
        if ($currentPage < 1 || $currentPage > $maxPage) {
            $currentPage = 1;
        }
        
        // Defines if the arrows of the carousel are displayed or none
        $next = true;
        $previous = true;
        
        if ($currentPage == $maxPage) {
            $next = false;
        }
        
        if ($currentPage <= 1) {
            $previous = false;
        }
        
        // Get the pictures
        $mediaArray = $this->_dataService->getList($filter['filter'], $filter['sort'], (($currentPage - 1) * $limit), $limit);
        
        // Set the ID and the title for each pictures
         $data=array();
        foreach ($mediaArray['data'] as $media) {
            $fields["image"] = (string) $media['id'];
            $fields["title"] = $media['title'];
            $data[] = $fields;
        }
        
        // Values sent to the view
        $output = $this->getParamFromQuery();
        $output['prefix'] = $prefix;
        $output['items'] = $data;
        $output['allDamCount'] = $allDamCount;
        $output['maxPage'] = $maxPage;
        $output['previous'] = $previous;
        $output['next'] = $next;
        $output['count'] = $mediaArray['count'];
        $output['pageSize'] = $limit;
        $output["image"]["width"] = isset($imgWidth) ? $imgWidth : null;
        $output["image"]["height"] = isset($imgHeight) ? $imgHeight : null;
        $output['currentPage'] = $currentPage;
        $output['jsonQuery'] = Json::encode($query);
        return $output;
    }

    protected function setFilters ($query)
    {
        
        if ($query != null) {
            $filters = Filter::factory();
            /* Add filters on TypeId and publication */
            $filters->addFilter(Filter::factory('In')->setName('typeId')
                ->setValue($query['DAMTypes']));
            
            /* Add filter on taxonomy */
            foreach ($query['vocabularies'] as $key => $value) {
                if (isset($value['rule'])) {
                    if ($value['rule'] == "some") {
                        $taxOperator = '$in';
                    } elseif ($value['rule'] == "all") {
                        $taxOperator = '$all';
                    } elseif ($value['rule'] == "someRec") {
                        if (count($value['terms']) > 0) {
                            foreach ($value['terms'] as $child) {
                                $terms = $this->_taxonomyReader->fetchAllChildren($child);
                                foreach ($terms as $taxonomyTerms) {
                                    $value['terms'][] = $taxonomyTerms["id"];
                                }
                            }
                        }
                        $taxOperator = '$in';
                    } else {
                        $taxOperator = '$in';
                    }
                } else {
                    $taxOperator = '$in';
                }
                if (count($value['terms']) > 0) {
                    $filters->addFilter(Filter::factory('OperatorToValue')->setName('taxonomy.' . $key)
                        ->setValue($value['terms'])
                        ->setOperator($taxOperator));
                }
            }
            $filters->addFilter(Filter::factory('In')->setName('target')
                ->setValue(array(
                $this->_workspace,
               'global'
            )));
            
            /*
             * Add Sort
             */
            if (isset($query['fieldRules'])) {
                foreach ($query['fieldRules'] as $field => $rule) {
                    $sort[] = array(
                        "property" => $field,
                        'direction' => $rule['sort']
                    );
                }
            } else {
                $sort[] = array(
                    'property' => 'id',
                    'direction' => 'DESC'
                );
            }
        } else {
            return array();
        }
        $returnArray = array(
            "filter" => $filters,
            "sort" => isset($sort) ? $sort : null
        );

        return $returnArray;
    }
}
