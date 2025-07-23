<?php
/**
 * Test script to demonstrate the differences between Sample 1 and Sample 2
 * This script shows how to use both sets of RabbitMQ clients
 */

echo "=== RabbitMQ Sample Comparison Test ===" . PHP_EOL;
echo "This script demonstrates the differences between the two sample sets." . PHP_EOL;
echo PHP_EOL;

echo "Sample 1 Features:" . PHP_EOL;
echo "- Basic echo functionality" . PHP_EOL;
echo "- Simple login validation" . PHP_EOL;
echo "- Session validation" . PHP_EOL;
echo "- Generic message processing" . PHP_EOL;
echo PHP_EOL;

echo "Sample 2 Features:" . PHP_EOL;
echo "- Enhanced greeting with timestamps" . PHP_EOL;
echo "- Mathematical calculations (add, subtract, multiply, divide)" . PHP_EOL;
echo "- Time queries with timezone support" . PHP_EOL;
echo "- Service status monitoring" . PHP_EOL;
echo "- Improved error handling" . PHP_EOL;
echo PHP_EOL;

echo "To test Sample 1:" . PHP_EOL;
echo "1. Start server: php RabbitMQServerSample.php" . PHP_EOL;
echo "2. Run client: php RabbitMQClientSample.php" . PHP_EOL;
echo PHP_EOL;

echo "To test Sample 2:" . PHP_EOL;
echo "1. Start server: php RabbitMQServerSample2.php" . PHP_EOL;
echo "2. Run client: php RabbitMQClientSample2.php" . PHP_EOL;
echo PHP_EOL;

echo "Sample 2 supports these message types:" . PHP_EOL;
echo "- greet: Personalized greetings with sender info" . PHP_EOL;
echo "- calculate: Mathematical operations" . PHP_EOL;
echo "- time: Current time in specified timezone" . PHP_EOL;
echo "- status: Service status checks" . PHP_EOL;
echo "- echo: Enhanced echo with server identification" . PHP_EOL;
echo PHP_EOL;

echo "Example manual test commands for Sample 2:" . PHP_EOL;
echo "php -r \"" . PHP_EOL;
echo "require_once('path.inc');" . PHP_EOL;
echo "require_once('get_host_info.inc');" . PHP_EOL;
echo "require_once('rabbitMQLib.inc');" . PHP_EOL;
echo "\$client = new RabbitMQClient('testRabbitMQ.ini', 'testServer');" . PHP_EOL;
echo "\$msg = array('type'=>'calculate', 'operation'=>'multiply', 'num1'=>7, 'num2'=>8);" . PHP_EOL;
echo "\$response = \$client->send_request(\$msg);" . PHP_EOL;
echo "print_r(\$response);" . PHP_EOL;
echo "\"" . PHP_EOL;

?>
