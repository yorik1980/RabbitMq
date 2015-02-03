<?php
/**
 * Work with RabbitMQ
 */

require_once 'vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class RabbitMq extends CApplicationComponent
{
    protected $connection = null;
    protected $channel = null;
    
    public $host;
    public $port;
    public $username;
    public $password;
    /**
     * Queue name
     * @var string
     */
    public $queue;
    /**
     * Backup file path. Used when no connection is established. Leave empty if you do not need it.
     * @var string
     */
    public $file;

    /**
     * Creates connection and channel, declares persistent queue
     * @param array $config connection params
     * @param string $queue queue name
     */
    public function init()
    {
        try {
            $this->connection = new AMQPConnection($this->host, $this->port, $this->username, $this->password);
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queue, false, true, false, false);
        } catch (Exception $e) {
            // if backup file isn't set - throw exception
            if (empty($this->file)) {
                throw $e;
            }
        }
    }
    
    /**
     * Publishes persistent message to queue
     * @param string $message
     * @param int $deliveryMode
     * @return boolean
     */
    public function publish($message, $deliveryMode = 2)
    {
        if (!is_null($this->channel)) {
            try {
                $msg = new AMQPMessage($message, array('delivery_mode' => $deliveryMode));
                $this->channel->basic_publish($msg, '', $this->queue);
            } catch (Exception $e) {
                if (!empty($this->file)) {
                    return $this->putToBackupFile($message);
                } 
                
                throw $e;
            }
            
            return true;
        } else if (!empty($this->file)) {
            return $this->putToBackupFile($message);
        } else {
            return false;
        }
    }

    /**
     * Consumes messages and sends them to callback
     * @param callable $callback
     * @param int $timeout time in second after which script will stop waiting for messages and exits
     */
    public function consume($callback, $timeout = 0)
    {
        try {
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume($this->queue, '', false, false, false, false, $callback);
            while(count($this->channel->callbacks)) {
                $this->channel->wait(null, false, $timeout);
            }
        } catch (PhpAmqpLib\Exception\AMQPTimeoutException $e) {
            
        }
    }
    
    /**
     * Purges queue
     */
    public function purge()
    {
        $this->channel->queue_purge($this->queue);
    }

    /**
     * Remove message from persistent queue
     * @param AMQPMessage $msg
     */
    public static function removeFromQueue($msg)
    {
        // TODO: message if not 'basic_ack' is owned by process until it dies - make possibility to release and requeue message on error in callback
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

    /**
     * Writes message to backup file
     * @param string $message
     * @return boolean
     */
    protected function putToBackupFile($message)
    {
        if (!$fp = fopen($this->file, "a")) {
            return false;
        }
        if (fwrite($fp, $message . "\n") === false) {
            return false;
        }
        fclose($fp);
        return true;
    }
    
    /**
     * Checks if backup file exist and connection estableshed and restores data to queue 
     * @return int count of restored messages
     */
    public function restoreFromBackupFile()
    {
        if (!$this->isConnected() || empty($this->file) || !is_file($this->file) || filesize($this->file) == 0) {
            return 0;
        }
        
        $newFileName = $this->file.'_'.date('Ymdhis');
        if (!rename($this->file, $newFileName)) {
            return 0;
        }

        if ($fp = fopen($newFileName, "r")) {
            $count = 0;
            while (($data = fgets($fp)) !== false) {
                $this->publish($data);
                $count++;
            }
            fclose($fp);
            unlink($newFileName);
            return $count;
        }
    }


    /**
     * Checks if connection established
     * @return boolean
     */
    public function isConnected()
    {
        return !is_null($this->channel);
    }
}