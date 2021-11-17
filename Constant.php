<?php


namespace Server;


/**
 * Class Constant
 * @package Server
 */
class Constant
{

	const START = 'Start';
	const SHUTDOWN = 'Shutdown';
	const BEFORE_SHUTDOWN = 'beforeShutdown';
	const WORKER_START = 'WorkerStart';
	const WORKER_STOP = 'WorkerStop';
	const WORKER_EXIT = 'WorkerExit';
	const CONNECT = 'Connect';
	const HANDSHAKE = 'handshake';
	const OPEN = 'open';
	const DISCONNECT = 'disconnect';
	const MESSAGE = 'message';
	const RECEIVE = 'Receive';
	const PACKET = 'Packet';
	const REQUEST = 'request';
	const CLOSE = 'Close';
	const TASK = 'Task';
	const FINISH = 'Finish';
	const PIPE_MESSAGE = 'PipeMessage';
	const WORKER_ERROR = 'WorkerError';
	const MANAGER_START = 'ManagerStart';
	const MANAGER_STOP = 'ManagerStop';
	const BEFORE_RELOAD = 'BeforeReload';
	const AFTER_RELOAD = 'AfterReload';


	const SERVER_TYPE_HTTP = 'http';
	const SERVER_TYPE_WEBSOCKET = 'ws';
	const SERVER_TYPE_TCP = 'tcp';
	const SERVER_TYPE_UDP = 'udp';
	const SERVER_TYPE_BASE = 'base';


	const STATUS_404_MESSAGE = '<h2>HTTP 404 Not Found</h2><hr><i>Powered by Swoole</i>';
	const STATUS_405_MESSAGE = '<h2>HTTP 405 Method allow</h2><hr><i>Powered by Swoole</i>';


    const OPTION_REACTOR_NUM = 'reactor_num';
    const OPTION_WORKER_NUM = 'worker_num';
    const OPTION_MAX_REQUEST = 'max_request';
    const OPTION_MAX_CONN = 'max_connection';
    const OPTION_TASK_WORKER_NUM = 'task_worker_num';
    const OPTION_TASK_IPC_MODE = 'task_ipc_mode';
    const OPTION_TASK_MAX_REQUEST = 'task_max_request';
    const OPTION_TASK_TMPDIR = 'task_tmpdir';
    const OPTION_TASK_ENABLE_COROUTINE = 'task_enable_coroutine';
    const OPTION_TASK_USE_OBJECT = 'task_use_object';
    const OPTION_DISPATCH_MODE = 'dispatch_mode';
    const OPTION_DISPATCH_FUNC = 'dispatch_func';
    const OPTION_MESSAGE_QUEUE_KEY = 'message_queue_key';
    const OPTION_DAEMONIZE = 'daemonize';
    const OPTION_BACKLOG = 'backlog';
    const OPTION_LOG_FILE = 'log_file';
    const OPTION_LOG_LEVEL = 'log_level';
    const OPTION_LOG_DATE_WITH_MICROSECONDS = 'log_date_with_microseconds';
    const OPTION_LOG_ROTATION = 'log_rotation';
    const OPTION_LOG_DATE_FORMAT = 'log_date_format';
    const OPTION_OPEN_TCP_KEEPALIVE = 'open_tcp_keepalive';
    const OPTION_HEARTBEAT_CHECK_INTERVAL = 'heartbeat_check_interval';
    const OPTION_HEARTBEAT_IDLE_TIME = 'heartbeat_idle_time';
    const OPTION_OPEN_EOF_CHECK = 'open_eof_check';
    const OPTION_OPEN_EOF_SPLIT = 'open_eof_split';
    const OPTION_PACKAGE_EOF = 'package_eof';
    const OPTION_OPEN_LENGTH_CHECK = 'open_length_check';
    const OPTION_PACKAGE_LENGTH_TYPE = 'package_length_type';
    const OPTION_PACKAGE_LENGTH_FUNC = 'package_length_func';
    const OPTION_PACKAGE_MAX_LENGTH = 'package_max_length';
    const OPTION_OPEN_HTTP_PROTOCOL = 'open_http_protocol';
    const OPTION_OPEN_MQTT_PROTOCOL = 'open_mqtt_protocol';
    const OPTION_OPEN_REDIS_PROTOCOL = 'open_redis_protocol';
    const OPTION_OPEN_WEBSOCKET_PROTOCOL = 'open_websocket_protocol';
    const OPTION_OPEN_WEBSOCKET_CLOSE_FRAME = 'open_websocket_close_frame';
    const OPTION_OPEN_TCP_NODELAY = 'open_tcp_nodelay';
    const OPTION_OPEN_CPU_AFFINITY = 'open_cpu_affinity';
    const OPTION_CPU_AFFINITY_IGNORE = 'cpu_affinity_ignore';
    const OPTION_TCP_DEFER_ACCEPT = 'tcp_defer_accept';
    const OPTION_SSL_CERT_FILE = 'ssl_cert_file';
    const OPTION_SSL_KEY_FILE = 'ssl_key_file';
    const OPTION_SSL_METHOD = 'ssl_method';
    const OPTION_SSL_PROTOCOLS = 'ssl_protocols';
    const OPTION_SSL_SNI_CERTS = 'ssl_sni_certs';
    const OPTION_SSL_CIPHERS = 'ssl_ciphers';
    const OPTION_SSL_VERIFY_PEER = 'ssl_verify_peer';
    const OPTION_SSL_ALLOW_SELF_SIGNED = 'ssl_allow_self_signed';
    const OPTION_SSL_CLIENT_CERT_FILE = 'ssl_client_cert_file';
    const OPTION_SSL_COMPRESS = 'ssl_compress';
    const OPTION_SSL_VERIFY_DEPTH = 'ssl_verify_depth';
    const OPTION_SSL_PREFER_SERVER_CIPHERS = 'ssl_prefer_server_ciphers';
    const OPTION_SSL_DHPARAM = 'ssl_dhparam';
    const OPTION_SSL_ECDH_CURVE = 'ssl_ecdh_curve';
    const OPTION_USER = 'user';
    const OPTION_GROUP = 'group';
    const OPTION_CHROOT = 'chroot';
    const OPTION_PID_FILE = 'pid_file';
    const OPTION_BUFFER_INPUT_SIZE = 'buffer_input_size';
    const OPTION_BUFFER_OUTPUT_SIZE = 'buffer_output_size';
    const OPTION_SOCKET_BUFFER_SIZE = 'socket_buffer_size';
    const OPTION_ENABLE_UNSAFE_EVENT = 'enable_unsafe_event';
    const OPTION_DISCARD_TIMEOUT_REQUEST = 'discard_timeout_request';
    const OPTION_ENABLE_REUSE_PORT = 'enable_reuse_port';
    const OPTION_ENABLE_DELAY_RECEIVE = 'enable_delay_receive';
    const OPTION_RELOAD_ASYNC = 'reload_async';
    const OPTION_MAX_WAIT_TIME = 'max_wait_time';
    const OPTION_TCP_FASTOPEN = 'tcp_fastopen';
    const OPTION_REQUEST_SLOWLOG_FILE = 'request_slowlog_file';
    const OPTION_ENABLE_COROUTINE = 'enable_coroutine';
    const OPTION_MAX_COROUTINE = 'max_coroutine';
    const OPTION_SEND_YIELD = 'send_yield';
    const OPTION_SEND_TIMEOUT = 'send_timeout';
    const OPTION_HOOK_FLAGS = 'hook_flags';
    const OPTION_BUFFER_HIGH_WATERMARK = 'buffer_high_watermark';
    const OPTION_BUFFER_LOW_WATERMARK = 'buffer_low_watermark';
    const OPTION_TCP_USER_TIMEOUT = 'tcp_user_timeout';
    const OPTION_STATS_FILE = 'stats_file';
    const OPTION_EVENT_OBJECT = 'event_object';
    const OPTION_START_SESSION_ID = 'start_session_id';
    const OPTION_SINGLE_THREAD = 'single_thread';
    const OPTION_MAX_QUEUED_BYTES = 'max_queued_bytes';


}
