<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
/**
 * 任务计划
 * @author TCG
 * @create 2015-08-07
 */
class Cron_schedule
{
    const STATUS_PENDING = 'pending';
    const STATUS_RUNNING = 'running';
    const STATUS_SUCCESS = 'success';
    const STATUS_MISSED = 'missed';
    const STATUS_ERROR = 'error';

    const CACHE_KEY_LAST_SCHEDULE_GENERATE_AT   = 'cron_last_schedule_generate_at';
    const CACHE_KEY_LAST_HISTORY_CLEANUP_AT     = 'cron_last_history_cleanup_at';
    
    // 参数的初始值 请在/application/config/config.php中配置
    protected $enable_cron = TRUE;
    protected $cron_schedule_table_name = 'cron_schedule';
    protected $cron_schedule_generate_every = '15';
    protected $cron_schedule_ahead_for = '20';
    protected $cron_schedule_lifetime = '15';
    protected $cron_history_cleanup_every = '10';
    protected $cron_history_success_lifetime = '60';
    protected $cron_history_failure_lifetime = '600';

    // 所有的cron schedule 请在/application/config/cron_schedules.php中添加
    protected $cron_schedule = array();
    protected $CI;
    
    public function __construct($params = array())
    {
        log_message('debug', 'Cron_schedule Class Initialized');
        
        $this->CI =& get_instance();
        
        foreach (array(
            'enable_cron',
            'cron_schedule_table_name',
            'cron_schedule_generate_every',
            'cron_schedule_ahead_for',
            'cron_schedule_lifetime',
            'cron_history_cleanup_every',
            'cron_history_success_lifetime',
            'cron_history_failure_lifetime'
            ) as $key)
        {
            $this->$key = (isset($params[$key])) ? $params[$key] : $this->CI->config->item($key);
        }
        
        if ($this->enable_cron == FALSE) {
            return;
        }

        if (defined('ENVIRONMENT') AND is_file(APPPATH.'config/'.ENVIRONMENT.'/cron_schedules.php')) {
            include(APPPATH.'config/'.ENVIRONMENT.'/cron_schedules.php');
        } elseif (is_file(APPPATH.'config/cron_schedules.php')) {
            include(APPPATH.'config/cron_schedules.php');
        }

        if ( ! isset($cron_schedule) OR ! is_array($cron_schedule)) {
            return;
        }
        
        $this->cron_schedule =& $cron_schedule;
        
        $this->CI->load->database();
    }
    
    /**
     * Get pending cron schedules
     *
     */
    public function getPendingSchedules()
    {
        return $this->CI->db->where('status', self::STATUS_PENDING)
                ->get($this->cron_schedule_table_name)
                ->result_array();
    }

    /**
     * Process cron queue
     * Geterate tasks schedule
     * Cleanup tasks schedule
     *  
     * @param $models
     */
    public function dispatch($models)
    {
        $schedules = $this->getPendingSchedules();
        $scheduleLifetime = $this->cron_schedule_lifetime * 60;
        $now = time();
        
        foreach ($schedules as $schedule) {
            $jobConfig = $this->cron_schedule[$schedule['job_code']];
            if (!$jobConfig || !$jobConfig['run']) {
                continue;
            }

            $runConfig = $jobConfig['run'];
            $time = strtotime($schedule['scheduled_at']);
            if ($time > $now) {
                //continue;
            }

            try {
                $errorStatus = self::STATUS_ERROR;
                $errorMessage = 'Unknown error.';
/*
                if ($time < $now - $scheduleLifetime) {
                    $errorStatus = self::STATUS_MISSED;
                    throw new Exception('Too late for the schedule.');
                }*/
                
                if ( ! isset($runConfig['filepath']) OR ! isset($runConfig['filename'])) {
                    throw new Exception('No filepath or filename found.');
                }
                
                $filepath = APPPATH.$runConfig['filepath'].'/'.$runConfig['filename'];
                
                if ( ! file_exists($filepath)) {
                    throw new Exception('No cron schedule file found.');
                }
                
                $class = FALSE;
                $function = FALSE;
                $params = '';
                
                if (isset($runConfig['class']) AND $runConfig['class'] != '') {
                    $class = $runConfig['class'];
                }
                
                if (isset($runConfig['function'])) {
                    $function = $runConfig['function'];
                }
                
                if (isset($runConfig['params'])) {
                    $params = $runConfig['params'];
                }
                
                if ($class === FALSE AND $function === FALSE) {
                    throw new Exception('No cron schedule function found.');
                }
/*
                $result = $this->CI->db->where('schedule_id', $schedule['schedule_id'])
                    ->where('status', self::STATUS_PENDING)
                    ->update($this->cron_schedule_table_name, array('status' => self::STATUS_RUNNING));
                
                if ( ! $result) {
                    continue;
                }
                

                $this->updateSchedule($schedule['schedule_id'], array(
                    'status'        => self::STATUS_RUNNING,
                    'executed_at'   => strftime('%Y-%m-%d %H:%M:%S', time())
                ));*/
                
                if ($class !== FALSE) {
                    if ( ! class_exists($class)) {
                        require($filepath);
                    }
                    
                    $SCHEDULE = new $class($models);
                    $SCHEDULE->$function($params);
                } else {
                    if ( ! function_exists($function)) {
                        require($filepath);
                    }
                    
                    $function($params, array_merge($params, $models));
                }

                /*
                $this->updateSchedule($schedule['schedule_id'], array(
                    'status'        => self::STATUS_SUCCESS,
                    'finished_at'   => strftime('%Y-%m-%d %H:%M:%S', time())
                ));*/

            } catch (Exception $e) {
            	/*
                $this->updateSchedule($schedule['schedule_id'], array(
                    'status'    => $errorStatus,
                    'messages'  => $e->__toString()
                ));*/
            }
        }
        /*
        $this->generate();
        $this->cleanup();*/
    }

    /**
     * Generate cron schedule
     *
     */
    public function generate()
    {
        /**
         * check if schedule generation is needed
         */
        $this->CI->load->driver('cache');
        $lastRun = $this->CI->cache->file->get(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT);
        if ($lastRun > time() - $this->cron_schedule_generate_every*60) {
            return $this;
        }

        $schedules = $this->getPendingSchedules();
        $exists = array();
        foreach ($schedules as $schedule) {
            $exists[$schedule['job_code'].'/'.$schedule['scheduled_at']] = 1;
        }

        /**
         * generate crontab jobs
         */
        if ($this->cron_schedule) {
            $scheduleAheadFor = $this->cron_schedule_ahead_for*60;

            $schedule = array();

            foreach ($this->cron_schedule as $jobCode => $jobConfig) {
                $cronExpr = null;
                
                if (isset($jobConfig['schedule']['config_path']) && $jobConfig['schedule']['config_path']) {
                    // 可以由其他途径获取cron表达式 如config文件或数据库 请自行实现
                    // $cronExpr = ...
                }

                if (empty($cronExpr) && $jobConfig['schedule']['cron_expr']) {
                    $cronExpr = (string)$jobConfig['schedule']['cron_expr'];
                }
                if (!$cronExpr) {
                    continue;
                }

                $now = time();
                $timeAhead = $now + $scheduleAheadFor;
                
                $schedule = array();
                $schedule['job_code'] = $jobCode;
                $schedule['status'] = self::STATUS_PENDING;

                $cronExprArr = preg_split('#\s+#', $cronExpr, null, PREG_SPLIT_NO_EMPTY);
                
                if (sizeof($cronExprArr)<5 || sizeof($cronExprArr)>6) {
                    show_error('Cron_schedule', 'Invalid cron expression: '.$expr);
                }

                for ($time = $now; $time < $timeAhead; $time += 60) {
                    $ts = strftime('%Y-%m-%d %H:%M:00', $time);
                    if ( ! empty($exists[$jobCode.'/'.$ts])) {
                        // already scheduled
                        continue;
                    }
                    
                    if (!is_numeric($time)) {
                        $time = strtotime($time);
                    }
                    
                    $d = getdate($time);

                    $match = $this->matchCronExpression($cronExprArr[0], $d['minutes'])
                        && $this->matchCronExpression($cronExprArr[1], $d['hours'])
                        && $this->matchCronExpression($cronExprArr[2], $d['mday'])
                        && $this->matchCronExpression($cronExprArr[3], $d['mon'])
                        && $this->matchCronExpression($cronExprArr[4], $d['wday']);

                    if ($match) {
                        $schedule['created_at'] = strftime('%Y-%m-%d %H:%M:%S', time());
                        $schedule['scheduled_at'] = strftime('%Y-%m-%d %H:%M', $time);
                        $this->insertSchedule($schedule);
                    } else {
                        continue;
                    }
                }
            }
        }

        /**
         * save time schedules generation was ran with no expiration
         */
        $this->CI->cache->file->save(self::CACHE_KEY_LAST_SCHEDULE_GENERATE_AT, time());

        return $this;
    }

    public function cleanup()
    {
        $this->CI->load->driver('cache');
        // check if history cleanup is needed
        $lastCleanup = $this->CI->cache->file->get(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT);
        
        if ($lastCleanup > time() - $this->cron_history_cleanup_every*60) {
            return $this;
        }

        $history = $this->CI->db->where_in('status', array(
                self::STATUS_SUCCESS,
                self::STATUS_MISSED,
                self::STATUS_ERROR,
            ))->get($this->cron_schedule_table_name)
            ->result_array();

        $historyLifetimes = array(
            self::STATUS_SUCCESS => $this->cron_history_success_lifetime*60,
            self::STATUS_MISSED => $this->cron_history_failure_lifetime*60,
            self::STATUS_ERROR => $this->cron_history_failure_lifetime*60,
        );

        $now = time();
        foreach ($history as $record) {
            if ((strtotime($record['executed_at']) > 0) &&
                (strtotime($record['executed_at']) < $now-$historyLifetimes[$record['status']])) {
                $this->deleteSchedule($record['schedule_id']);
            }
        }

        // save time history cleanup was ran with no expiration
        $this->CI->cache->file->save(self::CACHE_KEY_LAST_HISTORY_CLEANUP_AT, time());

        return $this;
    }

    public function matchCronExpression($expr, $num)
    {
        // handle ALL match
        if ($expr==='*') {
            return true;
        }

        // handle multiple options
        if (strpos($expr,',')!==false) {
            foreach (explode(',',$expr) as $e) {
                if ($this->matchCronExpression($e, $num)) {
                    return true;
                }
            }
            return false;
        }

        // handle modulus
        if (strpos($expr,'/')!==false) {
            $e = explode('/', $expr);
            if (sizeof($e)!==2) {
                show_error('Cron_schedule', "Invalid cron expression, expecting 'match/modulus': ".$expr);
            }
            if (!is_numeric($e[1])) {
                show_error('Cron_schedule', "Invalid cron expression, expecting numeric modulus: ".$expr);
            }
            $expr = $e[0];
            $mod = $e[1];
        } else {
            $mod = 1;
        }

        // handle all match by modulus
        if ($expr==='*') {
            $from = 0;
            $to = 60;
        }
        // handle range
        elseif (strpos($expr,'-')!==false) {
            $e = explode('-', $expr);
            if (sizeof($e)!==2) {
                show_error('Cron_schedule', "Invalid cron expression, expecting 'from-to' structure: ".$expr);
            }

            $from = $this->getNumeric($e[0]);
            $to = $this->getNumeric($e[1]);
        }
        // handle regular token
        else {
            $from = $this->getNumeric($expr);
            $to = $from;
        }

        if ($from===false || $to===false) {
            show_error('Cron_schedule', "Invalid cron expression: ".$expr);
        }

        return ($num>=$from) && ($num<=$to) && ($num%$mod===0);
    }

    public function getNumeric($value)
    {
        static $data = array(
            'jan'=>1, 'feb'=>2, 'mar'=>3, 'apr'=>4, 'may'=>5, 'jun'=>6,
            'jul'=>7, 'aug'=>8, 'sep'=>9, 'oct'=>10, 'nov'=>11, 'dec'=>12,
            'sun'=>0, 'mon'=>1, 'tue'=>2, 'wed'=>3, 'thu'=>4, 'fri'=>5, 'sat'=>6,
        );

        if (is_numeric($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(substr($value,0,3));
            if (isset($data[$value])) {
                return $data[$value];
            }
        }

        return false;
    }

    public function insertSchedule($schedule)
    {
        return $this->CI->db->insert($this->cron_schedule_table_name, $schedule);
    }

    public function updateSchedule($schedule_id, $schedule_data)
    {
        return $this->CI->db->where('schedule_id', $schedule_id)
            ->update($this->cron_schedule_table_name, $schedule_data);
    }
    
    public function deleteSchedule($schedule_id)
    {
        return $this->CI->db->where('schedule_id', $schedule_id)
            ->delete($this->cron_schedule_table_name);
    }
}
