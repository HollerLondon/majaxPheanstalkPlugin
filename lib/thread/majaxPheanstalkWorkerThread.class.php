<?php
/**
 * Base class for all Symfony Pheanstalk workers
 *
 * @package majaxPheanstalkPlugin
 */
abstract class majaxPheanstalkWorkerThread {
  
  /**
   * Memory limit
   *
   * @var integer
   */
  protected $memory_limit;

  /**
   * Sleep interval in milliseconds between recursions
   *
   * @var integer
   */
  protected $sleep_ms;
  
  /**
   * True if Pheanstalk is enabled
   *
   * @var boolean
   */
  protected $enable_pheanstalk = true;

  /**
   * Path to the worker thread class to execute
   *
   * @var string
   */
  private $path;

  /**
   * Instance of the task that created this trhead
   *
   * @var sfBaseTask
   **/
  private $task = false;

  /**
   * Construct a new majaxPheanstalkWorkerThread
   *
   * @param string $path 
   */
  public function __construct($path, sfTask $task = null)
  {
    $this->setBasePath($path);
    $this->log('starting');
    $this->doConstruct();
    
    $this->memory_limit = sfConfig::get('app_pheanstalk_memory_limit',100000000);
    $this->sleep_ms     = sfConfig::get('app_pheanstalk_sleep_ms', 100000);
    
    if($task instanceof sfTask)
    {
      $this->task = $task;
    }
    
    if ($this->enable_pheanstalk) $this->pheanstalk = majaxPheanstalk::getInstance();
  }

  /**
   * Allows extending classes to inject their own functionality during when
   * constructed without overloading the constructor
   *
   */
  protected function doConstruct()
  {
    // placeholder for subclasses
    // Legacy support
    $this->doInit();
  }
  
  /**
   * Inject code when this instance class is constructed
   *
   * @deprecated Use majaxPheanstalkWortherThread::doConstruct() instead
   * @return void
   * @author Ben Lancaster
   */
  protected function doInit() {
    // placeholder for subclasses
    // depreciated.
  }

  /**
   * Destructor
   *
   */
  public function __destruct() {
    $this->doDestruct();
    $this->log('ending');
  }

  /**
   * Hook for extending classes - called during destruction
   *
   * @return void
   * @author Ben Lancaster
   */
  protected function doDestruct()
  {
    // placeholder for subclasses
  }

  /**
   * Set the base bath
   *
   * @param string $path 
   */
  private function setBasePath($path) {
    $this->path = $path;
  }

  /**
   * Loops through available jobs, sleeping on each iteration
   *
   */
  public function run() {
    $this->log('starting to run');
    $cnt = 0;

    while(1) {
      
      try
      {
        $this->doRun();
      }
      catch(Exception $e)
      {
        $message = sprintf("%s (on line %u in %s):\n%s", $e->getMessage(), $e->getLine(), $e->getFile(), $e->getTraceAsString());
        $this->log($message,'fatal', $e);
        throw $e;
      }
      
      $cnt++;

      $memory = memory_get_usage();

      $this->log('memory:' . $memory);

      if($this->memory_limit > 0 && $memory > $this->memory_limit) {
        $this->log('exiting run due to memory limit');
        exit;
      }

      usleep($this->sleep_ms);
    }
  }

  /**
   * Must be overloaded by extended classes - does the actual work on the
   * current job
   *
   */
  protected function doRun()
  {
    throw new Exception('You must override doRun()');
  }
  
  /**
   * Retrieve a job from the given pipe
   *
   * @param string $pipe 
   * @return mixed Pheanstalk_Job or false
   */
  protected function getJob($pipe)
  {
    if ($this->enable_pheanstalk)
      return $this->pheanstalk->watch($pipe)->ignore('default')->reserve();
    throw new Exception('Pheanstalk is not enabled.');
  }

  /**
   * Facade method for pheanstalk::delete()
   *
   * @param string $job 
   */
  protected function deleteJob($job)
  {
    if ($this->enable_pheanstalk)
      return $this->pheanstalk->delete($job);
    throw new Exception('Pheanstalk is not enabled.');
  }

  /**
   * Facade method for pheanstalk::bury()
   *
   * @param string $job 
   * @see pheanstalk::bury()
   */
  protected function buryJob($job)
  {
    if ($this->enable_pheanstalk)
      return $this->pheanstalk->bury($job);
    throw new Exception('Pheanstalk is not enabled.');
  }

  /**
   * Log the given message to a file
   *
   * @param string $txt Log message
   * @return void
   */
  protected function log($message, $status = 'info', $namespace = null) {
    
    $class = is_object($namespace) ? $namespace : $this;
    
    $log_string = sprintf('%s pheanstalk [%s] {%s} %s',
      date('M d H:i:s'),
      $status,
      get_class($class),
      $message
    );
    
    $filename = sprintf('%s/%s_%s_%s.log', $this->path, sfConfig::get('sf_app'), sfConfig::get('sf_environment'), get_class($this));
    
    if($this->task instanceof sfTask && !$class instanceof Exception)
    {
      $this->task->logSection('worker',$message);
    }
    
    file_put_contents($filename, $log_string . "\n", FILE_APPEND);
  }
}

