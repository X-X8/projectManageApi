<?phpnamespace App\Domain\Project;use App\Common\CommonDomain;use App\Domain\Common\Notify;use App\Model\User\User;use function App\nowTime;/** * 项目任务日志类 * * @author lws */class TaskLog extends CommonDomain{    private static $Model = null;    public function __construct()    {        if (self::$Model == null) {            self::$Model = new \App\Model\Project\TaskLog();        }    }    /**获取日志列表     * @param $param     * @return array 数据对象     */    public function getList($param)    {        if (!is_array($param)) {            $param = get_object_vars($param);        }        if (isset($param['keyWord'])) {            $param['where']["content LIKE ? or id = ? "] = array("%" . $param['keyWord'] . "%", $param['keyWord']);        }        if (isset($param['search_date']) and count($param['search_date']) > 0) {            $param['where']["create_time >= ? "] = $param['search_date'][0];            $param['where']["create_time <= ? "] = date('Y-m-d H:i:s', strtotime($param['search_date'][1]) + 24 * 3600);        }        $list = self::$Model->getList($param);        if ($list['list']) {            $model_user = new User();            foreach ($list['list'] as &$item) {                if ($item) {                    $user = $model_user->get($item['user_id']);                    $item['realname'] = $user['realname'];                    $item['user_info'] = $user;                }            }            unset($item);        }        return $list;    }    /**     *  获取团队任务动态     * @param $team_id     * @param int $page_num     * @param int $page_size     * @return array     */    public function getTeamTaskLog($team_id,$page_num = 1,$page_size = PAGE_SIZE)    {        $offset = ($page_num - 1) * $page_size;        $sql = "SELECT tl.id as id ,u.id as u_id,u.realname,u.avatar,tl.task_id,tl.content,tl.memo,tl.ticket,tl.create_time,tl.user_id,t.name as task_name,p.name as project_name,p.id as project_id FROM pms_task_log AS tl RIGHT JOIN (SELECT task_id,MAX(id) AS id FROM pms_task_log GROUP BY task_id) as tl2 on tl.task_id = tl2.task_id AND tl.id = tl2.id JOIN pms_user AS u ON tl.user_id = u.id JOIN pms_task as t ON t.id = tl.task_id JOIN pms_project as p on t.project = p.id WHERE tl.user_id IN (SELECT u.id FROM pms_user AS u JOIN pms_team_user AS tu ON u.id = tu.user_id JOIN pms_company_team AS t ON t.id = tu.team_id WHERE t.id = :team_id) OR tl.to_user_id IN (SELECT u.id FROM pms_user AS u JOIN pms_team_user AS tu ON u.id = tu.user_id JOIN pms_company_team AS t ON t.id = tu.team_id WHERE t.id = :team_id) GROUP BY tl.task_id ORDER BY tl.id desc";        $params = array(':team_id'=>$team_id);        $counts = \PhalApi\DI()->notorm->notTable->queryRows($sql, $params);        $count = count($counts);        $sql .= " LIMIT {$offset},{$page_size}";        $lists = \PhalApi\DI()->notorm->notTable->queryRows($sql, $params);        $list = array('list' => $lists, 'count' => $count);        return $list;    }    public function formatTaskLog($task_log)    {    }    public function getLogContent($task_data)    {        $model_task = new \App\Model\Project\Task();        $task_info = $model_task->get($task_data['task_id']);        $content = '';        $log_data = array('task_id'=>$task_data['task_id']);        $log_data['memo'] = '';        $log_data['log_type'] = '';        $log_data['content'] = '';        if (isset($task_data['begin_time'])) {            if ($task_data['begin_time'] === null or $task_data['begin_time'] != $task_info['begin_time']  ) {                if ($task_data['begin_time'] !== null ) {                    $time = strtotime($task_data['begin_time']);                    $begin_time = date('m月d日 H:i',$time);                    $content =  "更新开始时间为 {$begin_time}";                }else{                    $begin_time = null;                    $content =  "清除了开始时间";                }                $log_data['log_type'] = 'date';            }        }        if (isset($task_data['end_time'])) {            if ($task_data['end_time'] === null or $task_data['end_time'] != $task_info['end_time']) {                if ($task_data['end_time'] !== null) {                    $time = strtotime($task_data['end_time']);                    $end_time = date('m月d日 H:i',$time);                    $content =  "更新截止时间为 {$end_time}";                }else{                    $end_time = null;                    $content =  "清除了截止时间";                }                $log_data['log_type'] = 'date';            }        }        if (isset($task_data['desc']) and $task_data['desc'] != '') {            $content = "更新了备注";            $log_data['memo'] = $task_data['desc'];            $log_data['log_type'] = 'content';        }        if (isset($task_data['name']) and $task_data['name'] != '') {            $content =  "更新了内容";            $log_data['content'] = $content;            $log_data['memo'] = $task_data['name'];            $log_data['log_type'] = 'title';        }        if (isset($task_data['task_type']) and $task_data['task_type'] != '') {            $domain_task = new Task();            $task_type_name = $domain_task->getTaskType($task_data['task_type'],$task_data['project']);            $content = "更改任务类型为 {$task_type_name}";            $log_data['content'] = $content;            $log_data['log_type'] = 'task_type';        }        if (isset($task_data['execute_state']) and $task_data['execute_state'] >= 0) {            $domain_task = new Task();            $task_execute_name = $domain_task->getTaskExecuteStateName($task_data['execute_state']);            if ($task_data['execute_state'] == 0) {                $content = "停止执行任务";            }elseif($task_data['execute_state']== 1){                $content = "正在执行任务";            }else{                $content = $task_execute_name."任务";            }            $log_data['content'] = $content;            $log_data['log_type'] = 'task_execute';        }        if (isset($task_data['task_tag'])) {            $task_tag_model = new \App\Model\Project\TaskTag();            $task_type_name_list = $task_tag_model->getListByIds(json_decode($task_data['task_tag']));            if ($task_type_name_list) {                $content = "编辑标签： ";                foreach ($task_type_name_list as $key=>$item) {                    if ($key == count($task_type_name_list) - 1) {                        $content .= $item['name'];                    }else{                        $content .= $item['name'] . '，';                    }                }            }else{                $content = "移除标签";            }            $log_data['content'] = $content;            $log_data['log_type'] = 'task_tag';        }        if (isset($task_data['pri']) and $task_data['pri'] >= 0) {            $domain_task = new Task();            $task_level_name = $domain_task->getTaskLevel($task_data['pri']);            $content = "更改任务级别为 {$task_level_name}";            $log_data['content'] = $content;            $log_data['log_type'] = 'task_level';        }        if (isset($task_data['task_state'])) {            if ($task_data['task_state'] == 1) {                $content =  "完成了任务";                $log_data['log_type'] = 'done';            }else{                $content = "重做了任务";                $log_data['log_type'] = 'again';            }            $log_data['content'] = $content;        }        $log_data['content'] = $content;        return $log_data;    }    public function getTaskLogTypeList()    {        $log_type = array();        $log_type[] = array('name' => 'add', 'icon' => 'person');    //添加任务        $log_type[] = array('name' => 'done', 'icon' => 'person'); //完成任务        $log_type[] = array('name' => 'again', 'icon' => 'person');  //重做任务        $log_type[] = array('name' => 'content', 'icon' => 'person'); //修改备注        $log_type[] = array('name' => 'title', 'icon' => 'person'); //修改标题        $log_type[] = array('name' => 'date', 'icon' => 'person');  //修改日期        $log_type[] = array('name' => 'upload', 'icon' => 'person');  //上传文件        $log_type[] = array('name' => 'add_executor', 'icon' => 'person');  //设置执行人        $log_type[] = array('name' => 'add_member', 'icon' => 'person');  //添加任务成员        $log_type[] = array('name' => 'task_tag', 'icon' => 'person');  //添加任务标记        return $log_type;    }    /**     * 获取任务日志类型名称     * @param $name     * @return mixed     */    public function getTaskLogType($name)    {        $task_list = $this->getTaskLogTypeList();        foreach ($task_list as $item) {            if ($item['name'] == $name) {                return $item;            }        }    }    /**     * @param $content     * @param int $task_id     * @param int $user_id     * @param string $log_type     * @param string $memo     * @param string $ticket     * @param int $to_user_id     * @param bool $is_synchronize  日志是否来源自同步原系统     * @return \PhalApi\long     */    public static function addLog($content, $task_id = 0, $user_id = 0, $log_type = 'add', $memo = '', $ticket = '',$to_user_id = 0,$is_synchronize = false)    {        $param = array();        $param['memo'] = $memo;        $param['content'] = $content;        $param['task_id'] = $task_id;        $param['user_id'] = $user_id;        $param['ticket'] = $ticket;        $param['log_type'] = $log_type;        $param['to_user_id'] = $to_user_id;        $param['create_time'] = nowTime();        $model = new \App\Model\Project\TaskLog();        $result = $model->insert($param);        $domain_notify = new Notify();        $param['is_synchronize'] = $is_synchronize;        $domain_notify->NotifyHook('task',$param);        return $result;    }}