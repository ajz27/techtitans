<?php
require_once(__DIR__ . "/vendor/autoload.php");
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class rabbitMQServer
{
	private $BROKER_HOST = "127.0.0.1";
	private $BROKER_PORT = 5672;
	private $USER = "guest";
	private $PASSWORD = "guest";
	private $VHOST = "/";
	private $exchange = "testExchange";
	private $queue = "testQueue";
	private $callback;
	private $conn_queue;

	function get_connection() {
		return new AMQPStreamConnection($this->BROKER_HOST, $this->BROKER_PORT, $this->USER, $this->PASSWORD, $this->VHOST);
	}

	function process_message($req) {
		echo "Processing message...\n";
		try {
			if ($req->get('reply_to')) {
				$body = $req->body;
				$payload = json_decode($body, true);
				$response = call_user_func($this->callback, $payload);
				
				$conn = $this->get_connection();
				$channel = $conn->channel();
				$msg = new AMQPMessage(
					json_encode($response),
					array('correlation_id' => $req->get('correlation_id'))
				);
				$channel->basic_publish($msg, '', $req->get('reply_to'));
				$channel->close();
				$conn->close();
				return;
			}
		} catch (Exception $e) {
			echo "Error: " . $e->getMessage() . "\n";
		}
	}

	function process_requests($callback) {
		$this->callback = $callback;
		echo "Starting RabbitMQ Server...\n";
		$conn = $this->get_connection();
		$channel = $conn->channel();
		$this->conn_queue = $channel;

		$channel->queue_declare($this->queue, false, false, false, false);
		$channel->basic_qos(null, 1, null);
		$channel->basic_consume($this->queue, '', false, true, false, false, array($this, 'process_message'));

		while ($channel->is_consuming()) {
			$channel->wait();
		}
	}
}

class rabbitMQClient
{
	private $BROKER_HOST = "127.0.0.1";
	private $BROKER_PORT = 5672;
	private $USER = "guest";
	private $PASSWORD = "guest";
	private $VHOST = "/";
	private $queue = "testQueue";
	private $callback_queue;
	private $channel;
	private $response_queue = [];

	function get_connection() {
		return new AMQPStreamConnection($this->BROKER_HOST, $this->BROKER_PORT, $this->USER, $this->PASSWORD, $this->VHOST);
	}

	function __construct() {
		$conn = $this->get_connection();
		$this->channel = $conn->channel();
		list($this->callback_queue, ,) = $this->channel->queue_declare("", false, false, true, false);
		$this->channel->basic_consume(
			$this->callback_queue,
			'',
			false,
			true,
			false,
			false,
			array($this, 'process_response')
		);
	}

	function process_response($response) {
		$uid = $response->get('correlation_id');
		$this->response_queue[$uid] = json_decode($response->body, true);
	}

	function send_request($message) {
		$uid = uniqid();
		$this->response_queue[$uid] = null;
		$msg = new AMQPMessage(
			json_encode($message),
			array(
				'correlation_id' => $uid,
				'reply_to' => $this->callback_queue,
				'content_type' => 'application/json'
			)
		);
		$this->channel->basic_publish($msg, '', $this->queue);
		while ($this->response_queue[$uid] === null) {
			$this->channel->wait();
		}
		$response = $this->response_queue[$uid];
		unset($this->response_queue[$uid]);
		return $response;
	}
}
?>
