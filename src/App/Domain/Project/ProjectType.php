<?phpnamespace App\Domain\Project;use function App\addLog;use App\Common\CommonDomain;use App\Common\Exception\WrongRequestException;use App\Model\Project\TaskTypeTemplate;use function App\nowTime;/** * 项目类型类 * * @author lws */class ProjectType extends CommonDomain{    private static $Model = null;    public function __construct()    {        if (self::$Model == null) {            self::$Model = new \App\Model\Project\ProjectType();        }    }    public function getList($param)    {        if (!is_array($param)) {            $param = get_object_vars($param);        }        if (isset($param['keyWord'])) {            $param['where']["name LIKE ? or memo = ? "] = array("%" . $param['keyWord'] . "%",$param['keyWord']);        }        $list = self::$Model->getList($param);        return $list;    }    public function getInfo($where,$field = '*')    {        return self::$Model->getInfo($where,$field);    }    /** 删除项目类型     * @param $ids id列表 如1,2,3     * @return int     */    public function delProjectType($ids)    {        $result = self::$Model->delItems($ids);        if ($result) {            $ids = json_encode($ids);            addLog("删除项目项目类型，编号：$ids");        }        return $result == true ? 0 : 1;    }    /**     *  新增项目类型     * @param $data     * @throws WrongRequestException     */    public function addProjectType($data)    {        $data['create_time'] = nowTime();        $id = self::$Model->insert($data);        if ($id === false) {            throw new WrongRequestException('新增失败', 8);        }        $model_task_type_template = new TaskTypeTemplate();        $template_data = $model_task_type_template->default_template;        foreach ($template_data as $template) {            $data = array();            $data['project_type_id'] = $id;            $data['name'] = $template['name'];            $data['create_time'] = nowTime();            $model_task_type_template->insert($data);        }        addLog('新增项目类型，编号：' . $id);    }    public function editProjectProjectType($id,$data)    {        $result = self::$Model->update($id,$data);        if ($result === false) {            throw new WrongRequestException('保存失败', 6);        }        addLog('修改项目类型，评级ID：' . $id);    }}