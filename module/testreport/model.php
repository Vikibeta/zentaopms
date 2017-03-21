<?php
/**
 * The model file of testreport module of ZenTaoCMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Yidong Wang <yidong@cnezsoft.com>
 * @package     testreport
 * @version     $Id$
 * @link        http://www.zentao.net
 */
class testreportModel extends model
{
    /**
     * Set menu.
     * 
     * @param  array  $products 
     * @param  int    $productID 
     * @param  int    $branch 
     * @access public
     * @return void
     */
    public function setMenu($products, $productID, $branch = 0)
    {
        $this->loadModel('product')->setMenu($products, $productID, $branch);
        $selectHtml = $this->product->select($products, $productID, 'testreport', 'browse', '', $branch);
        foreach($this->lang->testtask->menu as $key => $value)
        {
            $replace = ($key == 'product') ? $selectHtml : $productID;
            common::setMenuVars($this->lang->testreport->menu, $key, $replace);
        }
    }

    /**
     * Create report.
     * 
     * @access public
     * @return int
     */
    public function create()
    {
        $data = fixer::input('post')
            ->stripTags($this->config->testreport->editor->create['id'], $this->config->allowedTags)
            ->add('createdBy', $this->app->user->account)
            ->add('createdDate', helper::now())
            ->join('stories', ',')
            ->join('builds', ',')
            ->join('bugs', ',')
            ->join('cases', ',')
            ->join('members', ',')
            ->remove('files,labels,uid')
            ->get();
        $this->dao->insert(TABLE_TESTREPORT)->data($data)->autocheck()
             ->batchCheck($this->config->testreport->create->requiredFields, 'notempty')
             ->batchCheck('start,end', 'notempty')
             ->check('end', 'ge', $data->start)
             ->exec();
        if(dao::isError()) return false;
        $reportID = $this->dao->lastInsertID();
        $this->loadModel('file')->updateObjectID($this->post->uid, $reportID, 'testreport');
        $this->file->saveUpload('testreport', $reportID);
        return $reportID;
    }

    /**
     * Update report. 
     * 
     * @param  int    $reportID 
     * @access public
     * @return array
     */
    public function update($reportID)
    {
        $report = $this->getById($reportID);
        $data   = fixer::input('post')
            ->stripTags($this->config->testreport->editor->edit['id'], $this->config->allowedTags)
            ->join('stories', ',')
            ->join('builds', ',')
            ->join('bugs', ',')
            ->join('cases', ',')
            ->join('members', ',')
            ->remove('files,labels,uid')
            ->get();
        $this->dao->update(TABLE_TESTREPORT)->data($data)->autocheck()
             ->batchCheck($this->config->testreport->edit->requiredFields, 'notempty')
             ->batchCheck('start,end', 'notempty')
             ->check('end', 'ge', $data->start)
             ->where('id')->eq($reportID)
             ->exec();
        if(dao::isError()) return false;

        $this->loadModel('file')->updateObjectID($this->post->uid, $reportID, 'testreport');
        return common::createChanges($report, $data);
    }

    /**
     * Get report by id.
     * 
     * @param  int    $reportID 
     * @access public
     * @return object
     */
    public function getById($reportID)
    {
        $report = $this->dao->select('*')->from(TABLE_TESTREPORT)->where('id')->eq($reportID)->fetch();
        $report->files = $this->loadModel('file')->getByObject('testreport', $reportID);
        return $report;
    }

    /**
     * Get report list.
     * 
     * @param  int    $objectID 
     * @param  string $objectType 
     * @param  string $extra 
     * @param  string $orderBy 
     * @param  object $pager 
     * @access public
     * @return array
     */
    public function getList($objectID, $objectType, $extra = '', $orderBy = 'id_desc', $pager = null)
    {
        $objectID = (int)$objectID;
        return $this->dao->select('*')->from(TABLE_TESTREPORT)->where('deleted')->eq(0)
            ->beginIF($objectType == 'project')->andWhere('objectID')->eq($objectID)->andWhere('objectType')->eq('project')->fi()
            ->beginIF($objectType == 'product' and $extra)->andWhere('objectID')->eq((int)$extra)->andWhere('objectType')->eq('testtask')->fi()
            ->beginIF($objectType == 'product' and empty($extra))->andWhere('product')->eq($objectID)->fi()
            ->orderBy($orderBy)
            ->page($pager)
            ->fetchAll('id');
    }

    /**
     * Get bug info.
     * 
     * @param  array  $tasks 
     * @param  array  $productIdList 
     * @param  string $begin 
     * @param  string $end 
     * @param  array  $builds 
     * @access public
     * @return array
     */
    public function getBugInfo($tasks, $productIdList, $begin, $end, $builds)
    {
        $bugsByTask     = $this->dao->select('*')->from(TABLE_BUG)->where('testtask')->in(array_keys($tasks))->andWhere('testtask')->ne(0)->andWhere('deleted')->eq(0)->fetchAll('id');
        $severityGroups = $statusGroups = $openedByGroups = $resolvedByGroups = $resolutionGroups = $moduleGroups = array();
        $resolvedBugs   = 0;
        foreach($bugsByTask as $bug)
        {
            $severityGroups[$bug->severity] = isset($severityGroups[$bug->severity])     ? $severityGroups[$bug->severity]     + 1 : 1;
            $statusGroups[$bug->status]     = isset($statusGroups[$bug->status])         ? $statusGroups[$bug->status]         + 1 : 1;
            $openedByGroups[$bug->openedBy] = isset($openedByGroups[$bug->openedBy])     ? $openedByGroups[$bug->openedBy]     + 1 : 1;
            $moduleGroups[$bug->module]     = isset($moduleGroups[$bug->module])         ? $moduleGroups[$bug->module]         + 1 : 1;
            if($bug->resolvedBy)$resolvedByGroups[$bug->resolvedBy] = isset($resolvedByGroups[$bug->resolvedBy]) ? $resolvedByGroups[$bug->resolvedBy] + 1 : 1;
            if($bug->resolution)$resolutionGroups[$bug->resolution] = isset($resolutionGroups[$bug->resolution]) ? $resolutionGroups[$bug->resolution] + 1 : 1;
            if($bug->status == 'resolved' or $bug->status == 'closed') $resolvedBugs ++;
        }

        $newBugs     = $this->dao->select('*')->from(TABLE_BUG)->where('product')->in($productIdList)->andWhere('openedDate')->ge($begin)->andWhere('openedDate')->le("$end 23:59:59")->andWhere('deleted')->eq(0)->fetchAll();
        $legacyBugs  = array();
        $byCaseNum   = 0;
        $buildIdList = array_keys($builds) + array('trunk' => 'trunk');
        foreach($newBugs as $bug)
        {
            if(!array_diff(explode(',', $bug->openedBuild), $buildIdList) and ($bug->status == 'active' OR $bug->resolvedDate > "$end 23:59:59")) $legacyBugs[$bug->id] = $bug;
            if($bug->case) $byCaseNum ++;
        }
        $bugInfo['legacyBugs']          = $legacyBugs;
        $bugInfo['countBugByTask']      = count($bugsByTask);
        $bugInfo['bugSeverityGroups']   = $severityGroups;
        $bugInfo['bugStatusGroups']     = $statusGroups;
        $bugInfo['bugResolutionGroups'] = $resolutionGroups;
        $bugInfo['bugOpenedByGroups']   = $openedByGroups;
        $bugInfo['bugModuleGroups']     = $moduleGroups;
        $bugInfo['bugResolvedByGroups'] = $resolvedByGroups;
        $bugInfo['bugConfirmedRate']    = empty($resolvedBugs) ? 0 : round((count(zget($resolutionGroups, 'fixed', array())) + count(zget($resolutionGroups, 'postponed', array()))) / $resolvedBugs * 100, 2);
        $bugInfo['bugCreateByCaseRate'] = empty($byCaseNum) ? 0 : round($byCaseNum / count($newBugs) * 100, 2);
        return $bugInfo;
    }

    /**
     * Get task cases.
     * 
     * @param  array  $tasks 
     * @param  string $idList 
     * @access public
     * @return array
     */
    public function getTaskCases($tasks, $idList = '')
    {
        return $this->dao->select('t2.*,t1.assignedTo,t1.lastRunner,t1.lastRunDate,t1.lastRunResult,t1.status')->from(TABLE_TESTRUN)->alias('t1')
            ->leftJoin(TABLE_CASE)->alias('t2')->on('t1.case=t2.id')
            ->where('t1.task')->in(array_keys($tasks))
            ->beginIF($idList)->andWhere('t2.id')->in($idList)->fi()
            ->andWhere('t2.deleted')->eq(0)
            ->fetchAll('id');
    }

    /**
     * Get result summary.
     * 
     * @param  array    $tasks 
     * @param  array    $cases 
     * @access public
     * @return string
     */
    public function getResultSummary($tasks, $cases)
    {
        $results = $this->dao->select('t1.*')->from(TABLE_TESTRESULT)->alias('t1')
            ->leftJoin(TABLE_TESTRUN)->alias('t2')->on('t1.run=t2.id')
            ->where('t2.task')->in(array_keys($tasks))
            ->andWhere('t1.`case`')->in(array_keys($cases))
            ->fetchAll();
        $failResults = array();
        $runCasesNum = array();
        foreach($results as $result)
        {
            $runCasesNum[$result->case] = $result->case;
            if($result->caseResult == 'fail') $failResults[$result->case] = $result->case;
        }
        return sprintf($this->lang->testreport->caseSummary, count($cases), count($runCasesNum), count($results), count($failResults));
    }

    /**
     * Get bugs for test
     * 
     * @param  array  $builds 
     * @param  array  $product 
     * @param  string $begin 
     * @param  string $end 
     * @param  string $type 
     * @access public
     * @return void
     */
    public function getBugs4Test($builds, $product, $begin, $end, $type = 'build')
    {
        $bugIdList = '';
        if(is_array($builds))
        {
            foreach($builds as $build) $bugIdList .= $build->bugs . ',';
        }
        return $this->dao->select('*')->from(TABLE_BUG)->where('deleted')->eq(0)
            ->andWhere('product')->in($product)
            ->andWhere('openedDate')->lt($begin)
            ->beginIF(is_array($builds) and $type == 'build')->andWhere('id')->in(trim($bugIdList, ','))->fi()
            ->beginIF(!is_array($builds) and $type == 'build')->andWhere("(resolvedBuild = 'trunk' and resolvedDate >= '$begin' and resolvedDate <= '$end 23:59:59')")->fi()
            ->beginIF($type == 'project')->andWhere("(id " . helper::dbIN(trim($bugIdList, ',')) . " OR (resolvedBuild = 'trunk' and resolvedDate >= '$begin' and resolvedDate <= '$end 23:59:59'))")
            ->fetchAll('id');
    }
}