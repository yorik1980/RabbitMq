# RabbitMq
Simple RabbitMq extension for Yii.

Provides simple publish/consume interface with optional file backup. If connnection to RabbitMq not established while publishing, backup file will be used.

To publish backuped messages run restoreFromBackupFile method.

## Usage
Copy to the extensions dir of your project and run composer update inside of extension/RabbitMq dir.

### Configuring The RabbitMq Connection
```php
'components' => array(
	'rabbitMq' => array(
		'class' => 'ext.RabbitMq.RabbitMq',
		'host' => 'localhost,
		'port' => 5672,
		'username' => 'youruser',
		'password' => 'yourpassword',
		'queue' => 'queue_name',
		'file' => 'backup_file_name_with_path',
	),
	...
),
```

### Publish example
```php
Yii::app()->rabbitMq->publish(json_encode('Hello world!'));
```

### Consume example
```php
Yii::app()->rabbitMqSendMessage->consume(function($msg) {
	echo $msg . "\n";
	RabbitMq::removeFromQueue($msg);
}, 300);
```

### Publish from backup file
```php
$count = Yii::app()->rabbitMq->restoreFromBackupFile();
if ($count > 0) {
	echo Yii::app()->rabbitMq->queue . ": {$count} messages published\n";
}
```
